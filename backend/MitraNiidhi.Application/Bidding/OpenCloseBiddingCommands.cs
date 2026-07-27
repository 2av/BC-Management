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
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, cancellationToken);
        if (group is null)
            return Result.Failure("Group not found.");

        if (await db.MonthlyBids.AnyAsync(b => b.GroupId == command.GroupId && b.MonthNumber == req.MonthNumber, cancellationToken))
            return Result.Failure("This month already has a recorded winner.");

        if (req.MonthNumber == 1)
            return Result.Failure("Month 1 is reserved for the organiser — use “Assign Month 1 to organiser” instead of opening bids.");

        await GetGroupBcChartQueryHandler.EnsureChartRowsAsync(db, group, cancellationToken);
        var chart = await db.GroupMonthCharts.FirstOrDefaultAsync(
            c => c.GroupId == command.GroupId && c.MonthNumber == req.MonthNumber, cancellationToken);
        if (chart is null)
            return Result.Failure("BC chart is missing for this month. Open Admin → BC Chart and save amounts first.");
        if (chart.BoliStartAmount is null or <= 0)
            return Result.Failure("This month has no boli start on the BC chart (random-only month). Use random pick / set winner manually.");

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

        // Snapshot ladder bounds for legacy fields: first boli discount → max "bid" discount rises as ladder goes down.
        var startDiscount = group.TotalMonthlyCollection - chart.BoliStartAmount.Value;
        status.BiddingStatus = BiddingStatus.Open;
        status.BiddingStartDate = DateOnly.FromDateTime(DateTime.Today);
        status.BiddingEndDate = req.EndDate;
        status.MinimumBidAmount = startDiscount;
        status.MaximumBidAmount = startDiscount;
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
