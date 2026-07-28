using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Entities;
using MitraNiidhi.Domain.Enums;
using MitraNiidhi.Domain.Services;

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

        if (BcCalculationService.TrySyncStoredCollection(group))
            await db.SaveChangesAsync(cancellationToken);

        await GetGroupBcChartQueryHandler.EnsureChartRowsAsync(db, group, cancellationToken);

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

        var bestDiscountByMonth = await db.MemberBids
            .Where(b => b.GroupId == request.GroupId)
            .GroupBy(b => b.MonthNumber)
            .Select(g => new { Month = g.Key, BestDiscount = g.Max(x => x.BidAmount) })
            .ToDictionaryAsync(x => x.Month, x => x.BestDiscount, cancellationToken);

        var charts = await db.GroupMonthCharts
            .Where(c => c.GroupId == request.GroupId)
            .ToDictionaryAsync(c => c.MonthNumber, cancellationToken);

        var step = group.BoliStepAmount > 0 ? group.BoliStepAmount : 1000m;

        var activeSeatCount = await db.GroupMembers
            .CountAsync(gm => gm.GroupId == request.GroupId && gm.Status == "active", cancellationToken);

        var paymentByMonth = await db.MemberPayments
            .Where(p => p.GroupId == request.GroupId)
            .GroupBy(p => p.MonthNumber)
            .Select(g => new
            {
                Month = g.Key,
                Paid = g.Count(x => x.PaymentStatus == PaymentStatus.Paid),
                Pending = g.Count(x => x.PaymentStatus == PaymentStatus.Pending),
            })
            .ToDictionaryAsync(x => x.Month, cancellationToken);

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

                charts.TryGetValue(m.MonthNumber, out var chart);
                decimal? currentBestBoli = null;
                decimal? nextBoli = null;
                if (chart?.BoliStartAmount is decimal start)
                {
                    if (bestDiscountByMonth.TryGetValue(m.MonthNumber, out var bestDiscount))
                        currentBestBoli = BcChartService.ToReceive(group.TotalMonthlyCollection, bestDiscount);
                    nextBoli = BcChartService.NextBoliReceive(start, step, currentBestBoli);
                }

                var paymentDone = false;
                if (paymentByMonth.TryGetValue(m.MonthNumber, out var pay) && activeSeatCount > 0)
                {
                    paymentDone = pay.Pending == 0 && pay.Paid >= activeSeatCount;
                }

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
                    bidCounts.GetValueOrDefault(m.MonthNumber),
                    chart?.RandomAmount,
                    chart?.BoliStartAmount,
                    nextBoli,
                    currentBestBoli,
                    paymentDone);
            })
            .ToList();

        string? organiserName = null;
        if (group.OrganiserGroupMemberId is int orgSeatId)
        {
            var orgSeat = await db.GroupMembers
                .Include(gm => gm.Member)
                .FirstOrDefaultAsync(gm => gm.Id == orgSeatId, cancellationToken);
            if (orgSeat is not null)
                organiserName = $"{orgSeat.Member.MemberName}" +
                    (string.IsNullOrWhiteSpace(orgSeat.HandLabel) ? "" : $" · {orgSeat.HandLabel}");
        }
        else if (group.OrganiserMemberId is int orgMid)
        {
            organiserName = await db.Members
                .Where(m => m.Id == orgMid)
                .Select(m => m.MemberName)
                .FirstOrDefaultAsync(cancellationToken);
        }

        var month1Allocated = await db.MonthlyBids.AnyAsync(
            b => b.GroupId == request.GroupId && b.MonthNumber == 1, cancellationToken);

        return Result<GroupBiddingOverviewDto>.Success(new GroupBiddingOverviewDto(
            group.Id,
            group.GroupName,
            group.TotalMembers,
            group.MonthlyContribution,
            group.TotalMonthlyCollection,
            months,
            group.OrganiserMemberId,
            group.OrganiserGroupMemberId,
            organiserName,
            month1Allocated,
            step));
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
