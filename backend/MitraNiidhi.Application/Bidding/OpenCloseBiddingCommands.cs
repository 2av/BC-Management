using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Bidding;

public record OpenBiddingCommand(int GroupId, OpenBiddingRequest Request) : IRequest<Result>;

public class OpenBiddingCommandHandler(IAppDbContext db)
    : IRequestHandler<OpenBiddingCommand, Result>
{
    public async Task<Result> Handle(OpenBiddingCommand command, CancellationToken cancellationToken)
    {
        var req = command.Request;
        if (req.MinBidAmount < 0)
            return Result.Failure("Minimum bid amount cannot be negative.");
        if (req.MaxBidAmount <= req.MinBidAmount)
            return Result.Failure("Maximum bid must be greater than minimum bid.");

        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, cancellationToken);
        if (group is null)
            return Result.Failure("Group not found.");

        if (await db.MonthlyBids.AnyAsync(b => b.GroupId == command.GroupId && b.MonthNumber == req.MonthNumber, cancellationToken))
            return Result.Failure("This month already has a recorded winner.");

        var status = await db.MonthBiddingStatuses
            .FirstOrDefaultAsync(m => m.GroupId == command.GroupId && m.MonthNumber == req.MonthNumber, cancellationToken);

        if (status is null)
        {
            status = new Domain.Entities.MonthBiddingStatus
            {
                GroupId = command.GroupId,
                ClientId = group.ClientId,
                MonthNumber = req.MonthNumber
            };
            db.MonthBiddingStatuses.Add(status);
        }

        if (status.BiddingStatus is BiddingStatus.Completed)
            return Result.Failure("This month is already completed.");

        status.BiddingStatus = BiddingStatus.Open;
        status.BiddingStartDate = DateOnly.FromDateTime(DateTime.Today);
        status.BiddingEndDate = req.EndDate;
        status.MinimumBidAmount = req.MinBidAmount;
        status.MaximumBidAmount = req.MaxBidAmount;
        status.UpdatedAt = DateTime.UtcNow;

        await db.SaveChangesAsync(cancellationToken);
        return Result.Success();
    }
}

public record CloseBiddingCommand(int GroupId, int MonthNumber) : IRequest<Result>;

public class CloseBiddingCommandHandler(IAppDbContext db)
    : IRequestHandler<CloseBiddingCommand, Result>
{
    public async Task<Result> Handle(CloseBiddingCommand command, CancellationToken cancellationToken)
    {
        var status = await db.MonthBiddingStatuses
            .FirstOrDefaultAsync(m => m.GroupId == command.GroupId && m.MonthNumber == command.MonthNumber, cancellationToken);

        if (status is null)
            return Result.Failure("Bidding month not found.");
        if (status.BiddingStatus != BiddingStatus.Open)
            return Result.Failure("Only open bidding can be closed.");

        status.BiddingStatus = BiddingStatus.Closed;
        status.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(cancellationToken);
        return Result.Success();
    }
}
