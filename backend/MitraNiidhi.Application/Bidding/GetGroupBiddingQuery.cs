using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Entities;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Bidding;

public record GetGroupBiddingQuery(int GroupId) : IRequest<Result<GroupBiddingOverviewDto>>;

public class GetGroupBiddingQueryHandler(IAppDbContext db)
    : IRequestHandler<GetGroupBiddingQuery, Result<GroupBiddingOverviewDto>>
{
    public async Task<Result<GroupBiddingOverviewDto>> Handle(GetGroupBiddingQuery request, CancellationToken cancellationToken)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == request.GroupId, cancellationToken);
        if (group is null)
            return Result<GroupBiddingOverviewDto>.Failure("Group not found.");

        // Ensure month_bidding_status rows exist for the full cycle
        var existing = await db.MonthBiddingStatuses
            .Where(m => m.GroupId == request.GroupId)
            .ToListAsync(cancellationToken);

        if (existing.Count < group.TotalMembers)
        {
            var existingMonths = existing.Select(x => x.MonthNumber).ToHashSet();
            for (var month = 1; month <= group.TotalMembers; month++)
            {
                if (existingMonths.Contains(month)) continue;
                var completed = await db.MonthlyBids.AnyAsync(
                    b => b.GroupId == request.GroupId && b.MonthNumber == month, cancellationToken);
                db.MonthBiddingStatuses.Add(new MonthBiddingStatus
                {
                    GroupId = request.GroupId,
                    ClientId = group.ClientId,
                    MonthNumber = month,
                    BiddingStatus = completed ? BiddingStatus.Completed : BiddingStatus.NotStarted
                });
            }
            await db.SaveChangesAsync(cancellationToken);
            existing = await db.MonthBiddingStatuses
                .Where(m => m.GroupId == request.GroupId)
                .ToListAsync(cancellationToken);
        }

        var bidCounts = await db.MemberBids
            .Where(b => b.GroupId == request.GroupId)
            .GroupBy(b => b.MonthNumber)
            .Select(g => new { Month = g.Key, Count = g.Count() })
            .ToDictionaryAsync(x => x.Month, x => x.Count, cancellationToken);

        var winnerSeatIds = existing.Where(x => x.WinnerGroupMemberId.HasValue).Select(x => x.WinnerGroupMemberId!.Value).Distinct().ToList();
        var winnerSeats = await db.GroupMembers
            .Include(gm => gm.Member)
            .Where(gm => winnerSeatIds.Contains(gm.Id))
            .ToDictionaryAsync(gm => gm.Id, cancellationToken);

        var winnerIds = existing.Where(x => x.WinnerMemberId.HasValue).Select(x => x.WinnerMemberId!.Value).Distinct().ToList();
        var winners = await db.Members
            .Where(m => winnerIds.Contains(m.Id))
            .ToDictionaryAsync(m => m.Id, m => m.MemberName, cancellationToken);

        var months = existing
            .OrderBy(m => m.MonthNumber)
            .Select(m =>
            {
                string? winnerName = null;
                if (m.WinnerGroupMemberId is int seatId && winnerSeats.TryGetValue(seatId, out var seat))
                    winnerName = $"{seat.Member.MemberName}" + (string.IsNullOrWhiteSpace(seat.HandLabel) ? "" : $" · {seat.HandLabel}");
                else if (m.WinnerMemberId is int mid && winners.TryGetValue(mid, out var name))
                    winnerName = name;

                return new MonthBiddingDto(
                    m.MonthNumber,
                    ToStatus(m.BiddingStatus),
                    m.BiddingStartDate,
                    m.BiddingEndDate,
                    m.MinimumBidAmount,
                    m.MaximumBidAmount,
                    m.WinnerMemberId,
                    m.WinnerGroupMemberId,
                    winnerName,
                    m.WinningBidAmount,
                    bidCounts.GetValueOrDefault(m.MonthNumber));
            })
            .ToList();

        return Result<GroupBiddingOverviewDto>.Success(new GroupBiddingOverviewDto(
            group.Id,
            group.GroupName,
            group.TotalMembers,
            group.MonthlyContribution,
            group.TotalMonthlyCollection,
            months));
    }

    private static string ToStatus(BiddingStatus status) => status switch
    {
        BiddingStatus.NotStarted => "not_started",
        BiddingStatus.Open => "open",
        BiddingStatus.Closed => "closed",
        BiddingStatus.Completed => "completed",
        _ => status.ToString().ToLowerInvariant()
    };
}
