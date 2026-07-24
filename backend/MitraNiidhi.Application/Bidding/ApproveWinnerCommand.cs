using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Application.Members;
using MitraNiidhi.Application.Notifications;
using MitraNiidhi.Domain.Entities;
using MitraNiidhi.Domain.Enums;
using MitraNiidhi.Domain.Services;

namespace MitraNiidhi.Application.Bidding;

public record ApproveWinnerCommand(int GroupId, ApproveWinnerRequest Request) : IRequest<Result>;

public class ApproveWinnerCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<ApproveWinnerCommand, Result>
{
    public async Task<Result> Handle(ApproveWinnerCommand command, CancellationToken cancellationToken)
    {
        var req = command.Request;
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, cancellationToken);
        if (group is null)
            return Result.Failure("Group not found.");

        var status = await db.MonthBiddingStatuses
            .FirstOrDefaultAsync(m => m.GroupId == command.GroupId && m.MonthNumber == req.MonthNumber, cancellationToken);
        if (status is null)
            return Result.Failure("Bidding month not found.");
        if (status.BiddingStatus is BiddingStatus.Completed)
            return Result.Failure("Winner already approved for this month.");
        if (status.BiddingStatus is BiddingStatus.NotStarted)
            return Result.Failure("Open or close bidding before approving a winner.");

        var seatResult = await SeatHelper.ResolveSeatAsync(
            db, command.GroupId, req.WinnerMemberId, req.WinnerGroupMemberId, cancellationToken);
        if (!seatResult.Succeeded)
            return Result.Failure(seatResult.Error!);
        var winnerSeat = seatResult.Data!;

        if (await SeatHelper.SeatHasWonAsync(db, command.GroupId, winnerSeat.Id, cancellationToken))
            return Result.Failure("This seat has already won a previous month.");

        if (await db.MonthlyBids.AnyAsync(b => b.GroupId == command.GroupId && b.MonthNumber == req.MonthNumber, cancellationToken))
            return Result.Failure("This month already has a winner in the ledger.");

        var settlement = BcCalculationService.CalculateMonth(
            group.MonthlyContribution,
            group.TotalMembers,
            req.WinningBidAmount,
            isBid: req.WinningBidAmount > 0);

        var dueAmount = req.PaymentAmount is decimal overrideAmt && overrideAmt > 0
            ? overrideAmt
            : settlement.GainPerMember;

        if (db is not DbContext efContext)
            return Result.Failure("Database context unavailable.");

        await using var tx = await efContext.Database.BeginTransactionAsync(cancellationToken);
        try
        {
            status.BiddingStatus = BiddingStatus.Completed;
            status.WinnerMemberId = winnerSeat.MemberId;
            status.WinnerGroupMemberId = winnerSeat.Id;
            status.WinningBidAmount = req.WinningBidAmount;
            status.PaymentDueAmount = dueAmount;
            status.AdminApprovedBy = currentUser.UserId;
            status.AdminApprovedAt = DateTime.UtcNow;
            status.UpdatedAt = DateTime.UtcNow;

            var winner = await db.Members.FirstAsync(m => m.Id == winnerSeat.MemberId, cancellationToken);
            // Legacy member-level flags kept for display; eligibility is seat-scoped.
            winner.HasWonMonth = req.MonthNumber;
            winner.WonAmount = req.WinningBidAmount;

            db.MonthlyBids.Add(new MonthlyBid
            {
                GroupId = command.GroupId,
                ClientId = group.ClientId,
                MonthNumber = req.MonthNumber,
                TakenByMemberId = winnerSeat.MemberId,
                TakenByGroupMemberId = winnerSeat.Id,
                IsBid = settlement.IsBid,
                BidAmount = settlement.BidAmount,
                NetPayable = settlement.NetPayable,
                GainPerMember = dueAmount,
                PaymentDate = DateOnly.FromDateTime(DateTime.Today)
            });

            var seats = await db.GroupMembers
                .Where(gm => gm.GroupId == command.GroupId && gm.Status == "active")
                .ToListAsync(cancellationToken);

            foreach (var seat in seats)
            {
                var existingPayment = await db.MemberPayments.FirstOrDefaultAsync(
                    p => p.GroupId == command.GroupId
                         && p.GroupMemberId == seat.Id
                         && p.MonthNumber == req.MonthNumber,
                    cancellationToken);

                if (existingPayment is null)
                {
                    // Legacy fallback: payment keyed only by member_id
                    existingPayment = await db.MemberPayments.FirstOrDefaultAsync(
                        p => p.GroupId == command.GroupId
                             && p.GroupMemberId == null
                             && p.MemberId == seat.MemberId
                             && p.MonthNumber == req.MonthNumber,
                        cancellationToken);
                    if (existingPayment is not null)
                        existingPayment.GroupMemberId = seat.Id;
                }

                if (existingPayment is null)
                {
                    db.MemberPayments.Add(new MemberPayment
                    {
                        GroupId = command.GroupId,
                        ClientId = group.ClientId,
                        MemberId = seat.MemberId,
                        GroupMemberId = seat.Id,
                        MonthNumber = req.MonthNumber,
                        PaymentAmount = dueAmount,
                        PaymentStatus = PaymentStatus.Pending
                    });
                }
                else if (existingPayment.PaymentStatus == PaymentStatus.Pending)
                {
                    existingPayment.PaymentAmount = dueAmount;
                    existingPayment.UpdatedAt = DateTime.UtcNow;
                }

                await MemberUsernameHelper.EnsureSummaryAsync(
                    db, group, seat.MemberId, cancellationToken, seat.Id);
            }

            var bids = await db.MemberBids
                .Where(b => b.GroupId == command.GroupId && b.MonthNumber == req.MonthNumber)
                .ToListAsync(cancellationToken);
            foreach (var bid in bids)
            {
                var isWinner = bid.GroupMemberId == winnerSeat.Id
                    || (bid.GroupMemberId == null && bid.MemberId == winnerSeat.MemberId);
                bid.BidStatus = isWinner ? "approved" : "rejected";
                bid.UpdatedAt = DateTime.UtcNow;
            }

            await db.SaveChangesAsync(cancellationToken);

            foreach (var seat in seats)
            {
                await MemberSummaryRefreshSeat.RefreshAsync(db, command.GroupId, seat.Id, seat.MemberId, cancellationToken);
            }

            await db.SaveChangesAsync(cancellationToken);
            await tx.CommitAsync(cancellationToken);

            var winnerLabel = SeatHelper.FormatDisplayName(
                winner.MemberName, winnerSeat.HandLabel, winnerSeat.MemberNumber);

            NotificationWriter.Add(
                db, "admin", null,
                "Winner approved",
                $"{group.GroupName}: Month {req.MonthNumber} → {winnerLabel} (bid ₹{req.WinningBidAmount:N0}).",
                "info");

            NotificationWriter.Add(
                db, "member", winnerSeat.MemberId,
                $"You won Month {req.MonthNumber}",
                $"{group.GroupName}: {winnerLabel} — bid ₹{req.WinningBidAmount:N0}, net payable ₹{settlement.NetPayable:N0}.",
                "success");

            foreach (var memberId in seats.Select(s => s.MemberId).Distinct().Where(id => id != winnerSeat.MemberId))
            {
                NotificationWriter.Add(
                    db, "member", memberId,
                    $"Payment due — {group.GroupName} M{req.MonthNumber}",
                    $"Winner: {winnerLabel}. Amount due per seat: ₹{dueAmount:N0}.",
                    "warning");
            }

            await db.SaveChangesAsync(cancellationToken);
            return Result.Success();
        }
        catch (Exception ex)
        {
            await tx.RollbackAsync(cancellationToken);
            return Result.Failure($"Failed to approve winner: {ex.Message}");
        }
    }
}

internal static class MemberSummaryRefreshSeat
{
    public static async Task RefreshAsync(
        IAppDbContext db, int groupId, int groupMemberId, int memberId, CancellationToken cancellationToken)
    {
        var group = await db.BcGroups.FirstAsync(g => g.Id == groupId, cancellationToken);
        var summary = await db.MemberSummaries.FirstOrDefaultAsync(
            s => s.GroupId == groupId && s.GroupMemberId == groupMemberId, cancellationToken);

        if (summary is null)
        {
            summary = new MemberSummary
            {
                GroupId = groupId,
                ClientId = group.ClientId,
                MemberId = memberId,
                GroupMemberId = groupMemberId
            };
            db.MemberSummaries.Add(summary);
        }

        var allPaidLike = await db.MemberPayments
            .Where(p => p.GroupId == groupId && p.GroupMemberId == groupMemberId)
            .SumAsync(p => (decimal?)p.PaymentAmount, cancellationToken) ?? 0;
        var given = await db.MonthlyBids
            .Where(b => b.GroupId == groupId && b.TakenByGroupMemberId == groupMemberId)
            .SumAsync(b => (decimal?)b.NetPayable, cancellationToken) ?? 0;

        summary.TotalPaid = allPaidLike;
        summary.GivenAmount = given;
        summary.Profit = BcCalculationService.Profit(given, allPaidLike);
    }
}
