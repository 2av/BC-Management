using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Application.Notifications;
using MitraNiidhi.Domain.Entities;
using MitraNiidhi.Domain.Enums;
using MitraNiidhi.Domain.Services;

namespace MitraNiidhi.Application.Payments;

public record UpdatePaymentCommand(int PaymentId, UpdatePaymentRequest Request) : IRequest<Result>;

public class UpdatePaymentCommandHandler(IAppDbContext db)
    : IRequestHandler<UpdatePaymentCommand, Result>
{
    public async Task<Result> Handle(UpdatePaymentCommand command, CancellationToken cancellationToken)
    {
        var payment = await db.MemberPayments
            .Include(p => p.Group)
            .FirstOrDefaultAsync(p => p.Id == command.PaymentId, cancellationToken);
        if (payment is null)
            return Result.Failure("Payment not found.");

        if (!PaymentStatusMapper.TryParse(command.Request.PaymentStatus, out var status))
            return Result.Failure("Invalid payment status. Use pending, paid, or failed.");

        if (command.Request.PaymentAmount is decimal amount)
        {
            if (amount < 0)
                return Result.Failure("Payment amount cannot be negative.");
            payment.PaymentAmount = amount;
        }

        payment.PaymentStatus = status;
        if (command.Request.PaymentDate.HasValue)
            payment.PaymentDate = command.Request.PaymentDate;
        else if (status == PaymentStatus.Paid && payment.PaymentDate is null)
            payment.PaymentDate = DateOnly.FromDateTime(DateTime.Today);

        if (!string.IsNullOrWhiteSpace(command.Request.PaymentMethod))
            payment.PaymentMethod = command.Request.PaymentMethod;
        if (command.Request.TransactionId is not null)
            payment.TransactionId = command.Request.TransactionId;
        if (command.Request.Notes is not null)
            payment.Notes = command.Request.Notes;

        payment.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(cancellationToken);
        await MemberSummaryRefresh.RefreshAsync(db, payment.GroupId, payment.MemberId, cancellationToken);

        if (status == PaymentStatus.Paid)
        {
            NotificationWriter.Add(
                db, "member", payment.MemberId,
                "Payment confirmed",
                $"Month {payment.MonthNumber} in {payment.Group.GroupName} marked paid (₹{payment.PaymentAmount:N0}).",
                "success");
            await db.SaveChangesAsync(cancellationToken);
        }
        else if (status == PaymentStatus.Failed)
        {
            NotificationWriter.Add(
                db, "member", payment.MemberId,
                "Payment issue",
                $"Month {payment.MonthNumber} in {payment.Group.GroupName} was marked failed. Contact admin.",
                "danger");
            await db.SaveChangesAsync(cancellationToken);
        }

        return Result.Success();
    }
}

public record BulkMarkPaidCommand(int GroupId, BulkMarkPaidRequest Request) : IRequest<Result<int>>;

public class BulkMarkPaidCommandHandler(IAppDbContext db)
    : IRequestHandler<BulkMarkPaidCommand, Result<int>>
{
    public async Task<Result<int>> Handle(BulkMarkPaidCommand command, CancellationToken cancellationToken)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, cancellationToken);
        if (group is null)
            return Result<int>.Failure("Group not found.");

        var query = db.MemberPayments.Where(p =>
            p.GroupId == command.GroupId &&
            p.MonthNumber == command.Request.MonthNumber &&
            p.PaymentStatus == PaymentStatus.Pending);

        if (command.Request.PaymentIds is { Count: > 0 } ids)
            query = query.Where(p => ids.Contains(p.Id));

        var payments = await query.ToListAsync(cancellationToken);
        if (payments.Count == 0)
            return Result<int>.Failure("No pending payments found for this month.");

        var payDate = command.Request.PaymentDate ?? DateOnly.FromDateTime(DateTime.Today);
        foreach (var payment in payments)
        {
            payment.PaymentStatus = PaymentStatus.Paid;
            payment.PaymentDate = payDate;
            payment.UpdatedAt = DateTime.UtcNow;
        }

        await db.SaveChangesAsync(cancellationToken);

        foreach (var memberId in payments.Select(p => p.MemberId).Distinct())
        {
            await MemberSummaryRefresh.RefreshAsync(db, command.GroupId, memberId, cancellationToken);
            NotificationWriter.Add(
                db, "member", memberId,
                "Payment confirmed",
                $"Month {command.Request.MonthNumber} in {group.GroupName} marked paid.",
                "success");
        }

        await db.SaveChangesAsync(cancellationToken);
        return Result<int>.Success(payments.Count);
    }
}

internal static class MemberSummaryRefresh
{
    public static async Task RefreshAsync(IAppDbContext db, int groupId, int memberId, CancellationToken cancellationToken)
    {
        var seats = await db.GroupMembers
            .Where(gm => gm.GroupId == groupId && gm.MemberId == memberId)
            .ToListAsync(cancellationToken);

        if (seats.Count == 0)
        {
            await RefreshSeatAsync(db, groupId, null, memberId, cancellationToken);
            return;
        }

        foreach (var seat in seats)
            await RefreshSeatAsync(db, groupId, seat.Id, memberId, cancellationToken);
    }

    public static async Task RefreshSeatAsync(
        IAppDbContext db, int groupId, int? groupMemberId, int memberId, CancellationToken cancellationToken)
    {
        var group = await db.BcGroups.FirstAsync(g => g.Id == groupId, cancellationToken);
        var summary = groupMemberId is int seatId
            ? await db.MemberSummaries.FirstOrDefaultAsync(
                s => s.GroupId == groupId && s.GroupMemberId == seatId, cancellationToken)
            : await db.MemberSummaries.FirstOrDefaultAsync(
                s => s.GroupId == groupId && s.MemberId == memberId && s.GroupMemberId == null, cancellationToken);

        if (summary is null)
        {
            summary = new Domain.Entities.MemberSummary
            {
                GroupId = groupId,
                ClientId = group.ClientId,
                MemberId = memberId,
                GroupMemberId = groupMemberId
            };
            db.MemberSummaries.Add(summary);
        }

        var paymentsQuery = db.MemberPayments.Where(p => p.GroupId == groupId && p.PaymentStatus == PaymentStatus.Paid);
        paymentsQuery = groupMemberId is int sid
            ? paymentsQuery.Where(p => p.GroupMemberId == sid)
            : paymentsQuery.Where(p => p.MemberId == memberId);

        var winsQuery = db.MonthlyBids.Where(b => b.GroupId == groupId);
        winsQuery = groupMemberId is int winSeat
            ? winsQuery.Where(b => b.TakenByGroupMemberId == winSeat)
            : winsQuery.Where(b => b.TakenByMemberId == memberId);

        var totalPaid = await paymentsQuery.SumAsync(p => (decimal?)p.PaymentAmount, cancellationToken) ?? 0;
        var given = await winsQuery.SumAsync(b => (decimal?)b.NetPayable, cancellationToken) ?? 0;

        summary.TotalPaid = totalPaid;
        summary.GivenAmount = given;
        summary.Profit = BcCalculationService.Profit(given, totalPaid);
        await db.SaveChangesAsync(cancellationToken);
    }
}

public record CreatePaymentCommand(int GroupId, CreatePaymentRequest Request) : IRequest<Result<int>>;

public class CreatePaymentCommandHandler(IAppDbContext db)
    : IRequestHandler<CreatePaymentCommand, Result<int>>
{
    public async Task<Result<int>> Handle(CreatePaymentCommand command, CancellationToken cancellationToken)
    {
        var req = command.Request;
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, cancellationToken);
        if (group is null)
            return Result<int>.Failure("Group not found.");

        if (req.MonthNumber < 1 || req.MonthNumber > group.TotalMembers)
            return Result<int>.Failure($"Month must be between 1 and {group.TotalMembers}.");

        if (req.PaymentAmount <= 0)
            return Result<int>.Failure("Payment amount must be greater than 0.");

        if (!PaymentStatusMapper.TryParse(req.PaymentStatus, out var status))
            return Result<int>.Failure("Invalid payment status. Use pending, paid, or failed.");

        var seatIds = (req.GroupMemberIds ?? Array.Empty<int>())
            .Concat(req.GroupMemberId is int single ? new[] { single } : Array.Empty<int>())
            .Where(id => id > 0)
            .Distinct()
            .ToList();

        if (seatIds.Count == 0)
            return Result<int>.Failure("Select at least one member seat.");

        var seats = await db.GroupMembers
            .Where(gm => gm.GroupId == command.GroupId && seatIds.Contains(gm.Id))
            .ToListAsync(cancellationToken);

        if (seats.Count != seatIds.Count)
            return Result<int>.Failure("One or more member seats were not found in this group.");

        var existing = await db.MemberPayments
            .Where(p => p.GroupId == command.GroupId && p.MonthNumber == req.MonthNumber)
            .ToListAsync(cancellationToken);

        var blockedSeatIds = existing
            .Where(p => p.GroupMemberId is int sid)
            .Select(p => p.GroupMemberId!.Value)
            .ToHashSet();
        var blockedMemberIds = existing
            .Where(p => p.GroupMemberId == null)
            .Select(p => p.MemberId)
            .ToHashSet();

        var eligible = seats
            .Where(s => !blockedSeatIds.Contains(s.Id) && !blockedMemberIds.Contains(s.MemberId))
            .ToList();

        if (eligible.Count == 0)
            return Result<int>.Failure("Selected seats already have a payment for this month.");

        var paymentDate = req.PaymentDate
            ?? (status == PaymentStatus.Paid ? DateOnly.FromDateTime(DateTime.Today) : null);
        var method = string.IsNullOrWhiteSpace(req.PaymentMethod) ? "upi" : req.PaymentMethod.Trim();

        foreach (var seat in eligible)
        {
            db.MemberPayments.Add(new MemberPayment
            {
                GroupId = command.GroupId,
                ClientId = group.ClientId,
                MemberId = seat.MemberId,
                GroupMemberId = seat.Id,
                MonthNumber = req.MonthNumber,
                PaymentAmount = req.PaymentAmount,
                PaymentStatus = status,
                PaymentDate = paymentDate,
                PaymentMethod = method,
                TransactionId = req.TransactionId,
                Notes = req.Notes
            });
        }

        await db.SaveChangesAsync(cancellationToken);

        foreach (var memberId in eligible.Select(s => s.MemberId).Distinct())
            await MemberSummaryRefresh.RefreshAsync(db, command.GroupId, memberId, cancellationToken);

        if (status == PaymentStatus.Paid)
        {
            foreach (var seat in eligible)
            {
                NotificationWriter.Add(
                    db, "member", seat.MemberId,
                    "Payment recorded",
                    $"Month {req.MonthNumber} in {group.GroupName} recorded as paid (₹{req.PaymentAmount:N0}).",
                    "success");
            }
            await db.SaveChangesAsync(cancellationToken);
        }

        return Result<int>.Success(eligible.Count);
    }
}

public record SetMonthPaymentAmountCommand(int GroupId, SetMonthPaymentAmountRequest Request) : IRequest<Result>;

public class SetMonthPaymentAmountCommandHandler(IAppDbContext db)
    : IRequestHandler<SetMonthPaymentAmountCommand, Result>
{
    public async Task<Result> Handle(SetMonthPaymentAmountCommand command, CancellationToken cancellationToken)
    {
        var req = command.Request;
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, cancellationToken);
        if (group is null)
            return Result.Failure("Group not found.");

        if (req.MonthNumber < 1 || req.MonthNumber > group.TotalMembers)
            return Result.Failure($"Month must be between 1 and {group.TotalMembers}.");

        if (req.PaymentAmount is decimal amt && amt <= 0)
            return Result.Failure("Payment amount must be greater than 0, or clear it to use BC default.");

        var status = await db.MonthBiddingStatuses.FirstOrDefaultAsync(
            s => s.GroupId == command.GroupId && s.MonthNumber == req.MonthNumber,
            cancellationToken);

        if (status is null)
        {
            status = new Domain.Entities.MonthBiddingStatus
            {
                GroupId = command.GroupId,
                ClientId = group.ClientId,
                MonthNumber = req.MonthNumber,
                BiddingStatus = Domain.Enums.BiddingStatus.NotStarted
            };
            db.MonthBiddingStatuses.Add(status);
        }

        status.PaymentDueAmount = req.PaymentAmount is decimal set && set > 0 ? set : null;
        status.UpdatedAt = DateTime.UtcNow;

        var effective = UpiPaymentHelper.ResolveDueAmount(
            group.MonthlyContribution,
            status.PaymentDueAmount,
            (await db.MonthlyBids.FirstOrDefaultAsync(
                b => b.GroupId == command.GroupId && b.MonthNumber == req.MonthNumber,
                cancellationToken))?.GainPerMember);

        var bid = await db.MonthlyBids.FirstOrDefaultAsync(
            b => b.GroupId == command.GroupId && b.MonthNumber == req.MonthNumber,
            cancellationToken);
        if (bid is not null && status.PaymentDueAmount is decimal due)
        {
            bid.GainPerMember = due;
        }

        var pending = await db.MemberPayments
            .Where(p => p.GroupId == command.GroupId
                        && p.MonthNumber == req.MonthNumber
                        && p.PaymentStatus == PaymentStatus.Pending)
            .ToListAsync(cancellationToken);

        foreach (var payment in pending)
        {
            payment.PaymentAmount = effective;
            payment.UpdatedAt = DateTime.UtcNow;
        }

        await db.SaveChangesAsync(cancellationToken);
        return Result.Success();
    }
}
