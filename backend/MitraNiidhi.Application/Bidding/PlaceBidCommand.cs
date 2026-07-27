using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Entities;
using MitraNiidhi.Domain.Enums;
using MitraNiidhi.Domain.Services;

namespace MitraNiidhi.Application.Bidding;

public record PlaceBidCommand(int GroupId, PlaceBidRequest Request) : IRequest<Result>;

public class PlaceBidCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<PlaceBidCommand, Result>
{
    public async Task<Result> Handle(PlaceBidCommand command, CancellationToken cancellationToken)
    {
        if (currentUser.UserId is null || currentUser.Role != UserRole.Member)
            return Result.Failure("Only members can place bids.");

        var memberId = currentUser.UserId.Value;
        var req = command.Request;

        if (req.BoliAmount <= 0)
            return Result.Failure("Boli amount must be greater than 0.");

        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, cancellationToken);
        if (group is null)
            return Result.Failure("Group not found.");

        var seatResult = await SeatHelper.ResolveSeatAsync(
            db, command.GroupId, memberId, req.GroupMemberId, cancellationToken);
        if (!seatResult.Succeeded)
            return Result.Failure(seatResult.Error!);
        var seat = seatResult.Data!;

        if (await SeatHelper.SeatHasWonAsync(db, command.GroupId, seat.Id, cancellationToken))
            return Result.Failure("This hand has already won a month in this group.");

        if (await db.MonthlyBids.AnyAsync(b => b.GroupId == command.GroupId && b.MonthNumber == req.MonthNumber, cancellationToken))
            return Result.Failure("This month already has a winner.");

        var status = await db.MonthBiddingStatuses
            .FirstOrDefaultAsync(m => m.GroupId == command.GroupId && m.MonthNumber == req.MonthNumber, cancellationToken);

        if (status is null || status.BiddingStatus != BiddingStatus.Open)
            return Result.Failure("Bidding is not open for this month.");

        await GetGroupBcChartQueryHandler.EnsureChartRowsAsync(db, group, cancellationToken);
        var chart = await db.GroupMonthCharts.FirstOrDefaultAsync(
            c => c.GroupId == command.GroupId && c.MonthNumber == req.MonthNumber, cancellationToken);
        if (chart?.BoliStartAmount is null)
            return Result.Failure("No boli start configured for this month on the BC chart.");

        var step = group.BoliStepAmount > 0 ? group.BoliStepAmount : 1000m;
        var existingBids = await db.MemberBids
            .Where(b => b.GroupId == command.GroupId && b.MonthNumber == req.MonthNumber)
            .ToListAsync(cancellationToken);

        decimal? currentBestReceive = existingBids.Count == 0
            ? null
            : existingBids.Min(b => BcChartService.ToReceive(group.TotalMonthlyCollection, b.BidAmount));

        if (!BcChartService.IsAllowedBoliReceive(req.BoliAmount, chart.BoliStartAmount, step, currentBestReceive))
        {
            var next = BcChartService.NextBoliReceive(chart.BoliStartAmount, step, currentBestReceive);
            return Result.Failure(next is null
                ? "No further boli steps are available for this month."
                : $"Next boli must be exactly ₹{next:0} (step ₹{step:0}).");
        }

        if (req.BoliAmount >= group.TotalMonthlyCollection)
            return Result.Failure($"Boli must be less than total collection ({group.TotalMonthlyCollection:0}).");

        if (await db.MemberBids.AnyAsync(
                b => b.GroupId == command.GroupId
                     && b.MonthNumber == req.MonthNumber
                     && (b.GroupMemberId == seat.Id
                         || (b.GroupMemberId == null && b.MemberId == memberId)),
                cancellationToken))
            return Result.Failure("This hand already placed a bid for this month.");

        var discount = BcChartService.ToDiscount(group.TotalMonthlyCollection, req.BoliAmount);

        db.MemberBids.Add(new MemberBid
        {
            GroupId = command.GroupId,
            ClientId = group.ClientId,
            MemberId = memberId,
            GroupMemberId = seat.Id,
            MonthNumber = req.MonthNumber,
            BidAmount = discount,
            BidStatus = "pending",
            BidDate = DateTime.UtcNow
        });

        // Keep max as highest discount seen (lowest boli) for legacy displays.
        if (discount > status.MaximumBidAmount)
            status.MaximumBidAmount = discount;
        status.UpdatedAt = DateTime.UtcNow;

        await db.SaveChangesAsync(cancellationToken);
        return Result.Success();
    }
}
