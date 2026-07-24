using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Application.Payments;
using MitraNiidhi.Application.Settings;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Members;

public record MemberProfileDto(
    int Id,
    string MemberName,
    string? Username,
    string? Phone,
    string? Email,
    string? Address,
    string Status,
    DateTime CreatedAt);

public record UpdateMemberProfileRequest(
    string MemberName,
    string? Phone,
    string? Email,
    string? Address);

public record PaymentMonthOptionDto(
    int GroupId,
    string GroupName,
    int MonthNumber,
    decimal Amount,
    string Status,
    DateOnly? PaymentDate);

public record MemberPaymentDetailDto(
    int? PaymentId,
    int GroupId,
    string GroupName,
    int MonthNumber,
    decimal Amount,
    string MemberName,
    string? WinnerName,
    string PaymentStatus,
    string? TransactionId,
    string PayeeName,
    string PaymentNote,
    bool QrEnabled,
    string? UpiId,
    string? QrImageUrl,
    string? UpiUrl);

public record GetMemberProfileQuery : IRequest<Result<MemberProfileDto>>;
public record UpdateMemberProfileCommand(UpdateMemberProfileRequest Request) : IRequest<Result<MemberProfileDto>>;
public record GetPaymentOptionsQuery : IRequest<Result<IReadOnlyList<PaymentMonthOptionDto>>>;
public record GetMemberPaymentDetailQuery(int GroupId, int MonthNumber) : IRequest<Result<MemberPaymentDetailDto>>;

public class GetMemberProfileQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetMemberProfileQuery, Result<MemberProfileDto>>
{
    public async Task<Result<MemberProfileDto>> Handle(GetMemberProfileQuery request, CancellationToken ct)
    {
        if (currentUser.UserId is null) return Result<MemberProfileDto>.Failure("Not authenticated.");
        var m = await db.Members.FirstOrDefaultAsync(x => x.Id == currentUser.UserId, ct);
        if (m is null) return Result<MemberProfileDto>.Failure("Member not found.");
        return Result<MemberProfileDto>.Success(new MemberProfileDto(
            m.Id, m.MemberName, m.Username, m.Phone, m.Email, m.Address, m.Status, m.CreatedAt));
    }
}

public class UpdateMemberProfileCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<UpdateMemberProfileCommand, Result<MemberProfileDto>>
{
    public async Task<Result<MemberProfileDto>> Handle(UpdateMemberProfileCommand command, CancellationToken ct)
    {
        if (currentUser.UserId is null) return Result<MemberProfileDto>.Failure("Not authenticated.");
        var m = await db.Members.FirstOrDefaultAsync(x => x.Id == currentUser.UserId, ct);
        if (m is null) return Result<MemberProfileDto>.Failure("Member not found.");

        var req = command.Request;
        if (string.IsNullOrWhiteSpace(req.MemberName))
            return Result<MemberProfileDto>.Failure("Name is required.");

        var oldName = m.MemberName;
        m.MemberName = req.MemberName.Trim();
        m.Phone = req.Phone?.Trim();
        m.Email = req.Email?.Trim();
        m.Address = req.Address?.Trim();

        // Sync name across all member rows with same legacy name
        if (!string.Equals(oldName, m.MemberName, StringComparison.Ordinal))
        {
            var sameName = await db.Members.Where(x => x.MemberName == oldName && x.Id != m.Id).ToListAsync(ct);
            foreach (var other in sameName) other.MemberName = m.MemberName;
        }

        await db.SaveChangesAsync(ct);
        return Result<MemberProfileDto>.Success(new MemberProfileDto(
            m.Id, m.MemberName, m.Username, m.Phone, m.Email, m.Address, m.Status, m.CreatedAt));
    }
}

public class GetPaymentOptionsQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetPaymentOptionsQuery, Result<IReadOnlyList<PaymentMonthOptionDto>>>
{
    public async Task<Result<IReadOnlyList<PaymentMonthOptionDto>>> Handle(GetPaymentOptionsQuery request, CancellationToken ct)
    {
        if (currentUser.UserId is null)
            return Result<IReadOnlyList<PaymentMonthOptionDto>>.Failure("Not authenticated.");

        var memberId = currentUser.UserId.Value;
        var memberships = await db.GroupMembers
            .Where(gm => gm.MemberId == memberId && gm.Status == "active")
            .Include(gm => gm.Group)
            .ToListAsync(ct);

        var options = new List<PaymentMonthOptionDto>();
        foreach (var gm in memberships)
        {
            var group = gm.Group;
            var bids = await db.MonthlyBids.Where(b => b.GroupId == group.Id).ToListAsync(ct);
            var payments = await db.MemberPayments
                .Where(p => p.GroupId == group.Id && p.MemberId == memberId)
                .ToListAsync(ct);
            var dues = await db.MonthBiddingStatuses
                .Where(s => s.GroupId == group.Id)
                .ToDictionaryAsync(s => s.MonthNumber, s => s.PaymentDueAmount, ct);

            for (var month = 1; month <= group.TotalMembers; month++)
            {
                var bid = bids.FirstOrDefault(b => b.MonthNumber == month);
                var payment = payments.FirstOrDefault(p => p.MonthNumber == month);
                dues.TryGetValue(month, out var due);
                var amount = UpiPaymentHelper.ResolveDueAmount(
                    group.MonthlyContribution, due, bid?.GainPerMember);
                string status;
                DateOnly? paidDate = null;

                if (payment?.PaymentStatus == PaymentStatus.Paid)
                {
                    status = "paid";
                    amount = payment.PaymentAmount;
                    paidDate = payment.PaymentDate;
                }
                else if (bid is not null || due is > 0 || payment is not null)
                {
                    status = "pending";
                    if (payment is not null && payment.PaymentAmount > 0)
                        amount = payment.PaymentAmount;
                }
                else
                {
                    status = "not_ready";
                }

                options.Add(new PaymentMonthOptionDto(group.Id, group.GroupName, month, amount, status, paidDate));
            }
        }

        return Result<IReadOnlyList<PaymentMonthOptionDto>>.Success(options);
    }
}

public record MemberPaymentMethodsDto(
    bool QrEnabled,
    string UpiId,
    string PayeeName,
    string PaymentNote,
    string? QrImageUrl,
    string? UpiUrl);

public record GetMemberPaymentMethodsQuery : IRequest<Result<MemberPaymentMethodsDto>>;

public class GetMemberPaymentMethodsQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetMemberPaymentMethodsQuery, Result<MemberPaymentMethodsDto>>
{
    public async Task<Result<MemberPaymentMethodsDto>> Handle(GetMemberPaymentMethodsQuery request, CancellationToken ct)
    {
        if (currentUser.UserId is null)
            return Result<MemberPaymentMethodsDto>.Failure("Not authenticated.");

        var resolvedClientId = currentUser.ClientId
            ?? await db.GroupMembers
                .Where(gm => gm.MemberId == currentUser.UserId && gm.Status == "active")
                .Join(db.BcGroups, gm => gm.GroupId, g => g.Id, (gm, g) => g.ClientId)
                .FirstOrDefaultAsync(ct)
            ?? 1;

        var map = await GetPaymentConfigQueryHandler.GetConfigMap(db, resolvedClientId, ct);
        // Fallback: legacy payment_config rows may exist without matching client scope.
        if (string.IsNullOrWhiteSpace(map.GetValueOrDefault("upi_id")))
            map = await GetPaymentConfigQueryHandler.GetConfigMap(db, 1, ct);

        var qrEnabled = map.GetValueOrDefault("qr_enabled", "0") == "1";
        var upiId = map.GetValueOrDefault("upi_id", "");
        var payee = UpiPaymentHelper.BrandPayee;
        var note = UpiPaymentHelper.PaymentNote();
        string? upiUrl = null;
        string? qrImageUrl = null;
        if (qrEnabled && !string.IsNullOrWhiteSpace(upiId))
        {
            (upiUrl, qrImageUrl) = UpiPaymentHelper.BuildUrls(upiId, payee, note);
        }

        return Result<MemberPaymentMethodsDto>.Success(new MemberPaymentMethodsDto(
            qrEnabled && !string.IsNullOrWhiteSpace(upiId),
            upiId,
            payee,
            note,
            qrImageUrl,
            upiUrl));
    }
}

public class GetMemberPaymentDetailQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetMemberPaymentDetailQuery, Result<MemberPaymentDetailDto>>
{
    public async Task<Result<MemberPaymentDetailDto>> Handle(GetMemberPaymentDetailQuery request, CancellationToken ct)
    {
        if (currentUser.UserId is null) return Result<MemberPaymentDetailDto>.Failure("Not authenticated.");
        var memberId = currentUser.UserId.Value;

        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == request.GroupId, ct);
        if (group is null) return Result<MemberPaymentDetailDto>.Failure("Group not found.");

        var isMember = await db.GroupMembers.AnyAsync(
            gm => gm.GroupId == request.GroupId && gm.MemberId == memberId && gm.Status == "active", ct);
        if (!isMember) return Result<MemberPaymentDetailDto>.Failure("Access denied.");

        var member = await db.Members.FirstAsync(m => m.Id == memberId, ct);
        var bid = await db.MonthlyBids
            .Include(b => b.TakenByMember)
            .FirstOrDefaultAsync(b => b.GroupId == request.GroupId && b.MonthNumber == request.MonthNumber, ct);
        var payment = await db.MemberPayments.FirstOrDefaultAsync(
            p => p.GroupId == request.GroupId && p.MemberId == memberId && p.MonthNumber == request.MonthNumber, ct);
        var due = await db.MonthBiddingStatuses
            .Where(s => s.GroupId == request.GroupId && s.MonthNumber == request.MonthNumber)
            .Select(s => s.PaymentDueAmount)
            .FirstOrDefaultAsync(ct);

        var amount = UpiPaymentHelper.ResolveDueAmount(
            group.MonthlyContribution, due, bid?.GainPerMember);
        if (payment is not null && payment.PaymentStatus != PaymentStatus.Paid && payment.PaymentAmount > 0)
            amount = payment.PaymentAmount;
        var status = payment?.PaymentStatus.ToString().ToLowerInvariant() ?? "pending";

        var clientId = group.ClientId ?? currentUser.ClientId ?? 1;
        var configMap = await GetPaymentConfigQueryHandler.GetConfigMap(db, clientId, ct);
        if (string.IsNullOrWhiteSpace(configMap.GetValueOrDefault("upi_id")))
            configMap = await GetPaymentConfigQueryHandler.GetConfigMap(db, 1, ct);

        var unpaid = payment?.PaymentStatus != PaymentStatus.Paid;
        var upiId = configMap.GetValueOrDefault("upi_id", "");
        var payee = UpiPaymentHelper.BrandPayee;
        var note = UpiPaymentHelper.PaymentNote(group.GroupName, request.MonthNumber);
        var qrEnabled = configMap.GetValueOrDefault("qr_enabled", "0") == "1"
                        && unpaid
                        && !string.IsNullOrWhiteSpace(upiId);
        string? upiUrl = null;
        string? qrImageUrl = null;
        if (configMap.GetValueOrDefault("qr_enabled", "0") == "1" && !string.IsNullOrWhiteSpace(upiId))
        {
            (upiUrl, qrImageUrl) = UpiPaymentHelper.BuildUrls(
                upiId, payee, note, unpaid ? amount : null);
        }

        return Result<MemberPaymentDetailDto>.Success(new MemberPaymentDetailDto(
            payment?.Id,
            group.Id,
            group.GroupName,
            request.MonthNumber,
            amount,
            member.MemberName,
            bid?.TakenByMember?.MemberName,
            status,
            payment?.TransactionId,
            payee,
            note,
            qrEnabled || (unpaid && !string.IsNullOrWhiteSpace(qrImageUrl)),
            string.IsNullOrWhiteSpace(upiId) ? null : upiId,
            qrImageUrl,
            upiUrl));
    }
}

public record SubmitPaymentUtrRequest(string TransactionId, int? GroupMemberId = null);

public record SubmitPaymentUtrCommand(int GroupId, int MonthNumber, SubmitPaymentUtrRequest Request)
    : IRequest<Result>;

public class SubmitPaymentUtrCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<SubmitPaymentUtrCommand, Result>
{
    public async Task<Result> Handle(SubmitPaymentUtrCommand command, CancellationToken ct)
    {
        if (currentUser.UserId is null)
            return Result.Failure("Not authenticated.");

        var utr = command.Request.TransactionId?.Trim() ?? "";
        if (utr.Length < 6)
            return Result.Failure("Enter a valid UTR / UPI reference (at least 6 characters).");
        if (utr.Length > 64)
            return Result.Failure("UTR is too long.");

        var memberId = currentUser.UserId.Value;
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, ct);
        if (group is null)
            return Result.Failure("Group not found.");

        var seats = await db.GroupMembers
            .Where(gm => gm.GroupId == command.GroupId && gm.MemberId == memberId && gm.Status == "active")
            .ToListAsync(ct);
        if (seats.Count == 0)
            return Result.Failure("Access denied.");

        var seat = command.Request.GroupMemberId is int seatId
            ? seats.FirstOrDefault(s => s.Id == seatId)
            : seats.OrderBy(s => s.MemberNumber).FirstOrDefault();
        if (seat is null)
            return Result.Failure("Member seat not found in this group.");

        if (command.MonthNumber < 1 || command.MonthNumber > group.TotalMembers)
            return Result.Failure("Invalid month.");

        var payment = await db.MemberPayments.FirstOrDefaultAsync(
            p => p.GroupId == command.GroupId
                 && p.MonthNumber == command.MonthNumber
                 && (p.GroupMemberId == seat.Id
                     || (p.GroupMemberId == null && p.MemberId == memberId)),
            ct);

        if (payment is null)
        {
            var bid = await db.MonthlyBids.FirstOrDefaultAsync(
                b => b.GroupId == command.GroupId && b.MonthNumber == command.MonthNumber, ct);
            var due = await db.MonthBiddingStatuses
                .Where(s => s.GroupId == command.GroupId && s.MonthNumber == command.MonthNumber)
                .Select(s => s.PaymentDueAmount)
                .FirstOrDefaultAsync(ct);
            payment = new Domain.Entities.MemberPayment
            {
                GroupId = command.GroupId,
                ClientId = group.ClientId,
                MemberId = memberId,
                GroupMemberId = seat.Id,
                MonthNumber = command.MonthNumber,
                PaymentAmount = UpiPaymentHelper.ResolveDueAmount(
                    group.MonthlyContribution, due, bid?.GainPerMember),
                PaymentStatus = PaymentStatus.Pending,
                PaymentMethod = "upi",
                TransactionId = utr,
                Notes = "UTR submitted by member"
            };
            db.MemberPayments.Add(payment);
        }
        else
        {
            if (payment.PaymentStatus == PaymentStatus.Paid)
                return Result.Failure("This month is already marked paid.");
            payment.GroupMemberId ??= seat.Id;
            payment.TransactionId = utr;
            payment.PaymentMethod = "upi";
            payment.UpdatedAt = DateTime.UtcNow;
            if (string.IsNullOrWhiteSpace(payment.Notes))
                payment.Notes = "UTR submitted by member";
        }

        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}
