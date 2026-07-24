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

/// <summary>
/// Assigns Month 1 pot to the group organiser (no bid). Works even when bidding was never opened —
/// e.g. all members already paid contribution and admin needs the ledger to show who receives the pot.
/// </summary>
public record AllocateOrganiserMonthCommand(int GroupId, int MonthNumber = 1) : IRequest<Result>;

public class AllocateOrganiserMonthCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<AllocateOrganiserMonthCommand, Result>
{
    public async Task<Result> Handle(AllocateOrganiserMonthCommand command, CancellationToken cancellationToken)
    {
        if (command.MonthNumber != 1)
            return Result.Failure("Only Month 1 can be allocated to the organiser. Use bidding for later months.");

        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, cancellationToken);
        if (group is null)
            return Result.Failure("Group not found.");
        if (group.Status == GroupStatus.Completed)
            return Result.Failure("Completed groups cannot be updated.");

        GroupMember? organiserSeat = null;
        if (group.OrganiserGroupMemberId is int seatId)
        {
            organiserSeat = await db.GroupMembers.FirstOrDefaultAsync(
                gm => gm.Id == seatId && gm.GroupId == group.Id && gm.Status == "active",
                cancellationToken);
        }
        else if (group.OrganiserMemberId is int mid)
        {
            organiserSeat = await db.GroupMembers
                .Where(gm => gm.GroupId == group.Id && gm.MemberId == mid && gm.Status == "active")
                .OrderBy(gm => gm.MemberNumber)
                .FirstOrDefaultAsync(cancellationToken);
        }

        if (organiserSeat is null)
            return Result.Failure("Set the group organiser first (Edit group → Organiser).");

        if (await SeatHelper.SeatHasWonAsync(db, command.GroupId, organiserSeat.Id, cancellationToken))
            return Result.Failure("Organiser seat has already received a month pot.");

        if (await db.MonthlyBids.AnyAsync(
                b => b.GroupId == command.GroupId && b.MonthNumber == command.MonthNumber, cancellationToken))
            return Result.Failure("Month 1 pot is already allocated in the ledger.");

        var status = await db.MonthBiddingStatuses
            .FirstOrDefaultAsync(
                m => m.GroupId == command.GroupId && m.MonthNumber == command.MonthNumber,
                cancellationToken);
        if (status is null)
        {
            status = new MonthBiddingStatus
            {
                GroupId = group.Id,
                ClientId = group.ClientId,
                MonthNumber = command.MonthNumber,
                BiddingStatus = BiddingStatus.NotStarted
            };
            db.MonthBiddingStatuses.Add(status);
            await db.SaveChangesAsync(cancellationToken);
        }

        if (status.BiddingStatus == BiddingStatus.Completed)
            return Result.Failure("Month 1 is already marked completed.");

        var settlement = BcCalculationService.CalculateMonth(
            group.MonthlyContribution,
            group.TotalMembers,
            bidAmount: 0,
            isBid: false);

        // Prefer admin-set month due; else full contribution (no bid).
        var dueAmount = status.PaymentDueAmount is decimal due && due > 0
            ? due
            : settlement.GainPerMember;

        if (db is not DbContext efContext)
            return Result.Failure("Database context unavailable.");

        await using var tx = await efContext.Database.BeginTransactionAsync(cancellationToken);
        try
        {
            status.BiddingStatus = BiddingStatus.Completed;
            status.WinnerMemberId = organiserSeat.MemberId;
            status.WinnerGroupMemberId = organiserSeat.Id;
            status.WinningBidAmount = 0;
            status.PaymentDueAmount = dueAmount;
            status.AdminApprovedBy = currentUser.UserId;
            status.AdminApprovedAt = DateTime.UtcNow;
            status.UpdatedAt = DateTime.UtcNow;

            var winner = await db.Members.FirstAsync(m => m.Id == organiserSeat.MemberId, cancellationToken);
            winner.HasWonMonth = command.MonthNumber;
            winner.WonAmount = 0;

            db.MonthlyBids.Add(new MonthlyBid
            {
                GroupId = command.GroupId,
                ClientId = group.ClientId,
                MonthNumber = command.MonthNumber,
                TakenByMemberId = organiserSeat.MemberId,
                TakenByGroupMemberId = organiserSeat.Id,
                IsBid = false,
                BidAmount = 0,
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
                         && p.MonthNumber == command.MonthNumber,
                    cancellationToken);

                if (existingPayment is null)
                {
                    existingPayment = await db.MemberPayments.FirstOrDefaultAsync(
                        p => p.GroupId == command.GroupId
                             && p.GroupMemberId == null
                             && p.MemberId == seat.MemberId
                             && p.MonthNumber == command.MonthNumber,
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
                        MonthNumber = command.MonthNumber,
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

            await db.SaveChangesAsync(cancellationToken);

            foreach (var seat in seats)
            {
                await MemberSummaryRefreshSeat.RefreshAsync(
                    db, command.GroupId, seat.Id, seat.MemberId, cancellationToken);
            }

            await db.SaveChangesAsync(cancellationToken);
            await tx.CommitAsync(cancellationToken);

            var winnerLabel = SeatHelper.FormatDisplayName(
                winner.MemberName, organiserSeat.HandLabel, organiserSeat.MemberNumber);

            NotificationWriter.Add(
                db, "admin", null,
                "Month 1 allocated to organiser",
                $"{group.GroupName}: Month 1 pot → {winnerLabel} (₹{settlement.NetPayable:N0}, no bid).",
                "info");

            NotificationWriter.Add(
                db, "member", organiserSeat.MemberId,
                "You received Month 1 (organiser)",
                $"{group.GroupName}: Month 1 collection ₹{settlement.NetPayable:N0} is allocated to you as organiser.",
                "success");

            await db.SaveChangesAsync(cancellationToken);
            return Result.Success();
        }
        catch (Exception ex)
        {
            await tx.RollbackAsync(cancellationToken);
            return Result.Failure($"Failed to allocate Month 1: {ex.Message}");
        }
    }
}
