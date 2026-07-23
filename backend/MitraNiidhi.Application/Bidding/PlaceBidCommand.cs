using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Entities;
using MitraNiidhi.Domain.Enums;

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

        if (req.BidAmount <= 0)
            return Result.Failure("Bid amount must be greater than 0.");

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

        if (req.BidAmount >= group.TotalMonthlyCollection)
            return Result.Failure($"Bid must be less than total collection ({group.TotalMonthlyCollection:0}).");

        if (status.MinimumBidAmount > 0 && req.BidAmount < status.MinimumBidAmount)
            return Result.Failure($"Bid must be at least {status.MinimumBidAmount:0}.");

        if (status.MaximumBidAmount > 0 && req.BidAmount > status.MaximumBidAmount)
            return Result.Failure($"Bid cannot exceed {status.MaximumBidAmount:0}.");

        if (await db.MemberBids.AnyAsync(
                b => b.GroupId == command.GroupId
                     && b.MonthNumber == req.MonthNumber
                     && (b.GroupMemberId == seat.Id
                         || (b.GroupMemberId == null && b.MemberId == memberId)),
                cancellationToken))
            return Result.Failure("This hand already placed a bid for this month.");

        db.MemberBids.Add(new MemberBid
        {
            GroupId = command.GroupId,
            ClientId = group.ClientId,
            MemberId = memberId,
            GroupMemberId = seat.Id,
            MonthNumber = req.MonthNumber,
            BidAmount = req.BidAmount,
            BidStatus = "pending",
            BidDate = DateTime.UtcNow
        });

        await db.SaveChangesAsync(cancellationToken);
        return Result.Success();
    }
}
