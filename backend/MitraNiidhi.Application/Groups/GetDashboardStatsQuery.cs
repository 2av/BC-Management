using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Groups;

public record GetDashboardStatsQuery : IRequest<Result<DashboardStatsDto>>;

public class GetDashboardStatsQueryHandler(IAppDbContext db)
    : IRequestHandler<GetDashboardStatsQuery, Result<DashboardStatsDto>>
{
    public async Task<Result<DashboardStatsDto>> Handle(GetDashboardStatsQuery request, CancellationToken cancellationToken)
    {
        var groups = await db.BcGroups
            .OrderByDescending(g => g.CreatedAt)
            .ToListAsync(cancellationToken);

        var groupIds = groups.Select(g => g.Id).ToList();

        var memberCount = await db.GroupMembers
            .Where(gm => groupIds.Contains(gm.GroupId) && gm.Status == "active")
            .Select(gm => gm.MemberId)
            .Distinct()
            .CountAsync(cancellationToken);

        var totalCollected = await db.MemberPayments
            .Where(p => p.PaymentStatus == PaymentStatus.Paid)
            .SumAsync(p => (decimal?)p.PaymentAmount, cancellationToken) ?? 0;

        var totalDistributed = await db.MonthlyBids
            .SumAsync(b => (decimal?)b.NetPayable, cancellationToken) ?? 0;

        var monthStart = new DateOnly(DateTime.UtcNow.Year, DateTime.UtcNow.Month, 1);
        var thisMonthCollected = await db.MemberPayments
            .Where(p => p.PaymentStatus == PaymentStatus.Paid && p.PaymentDate >= monthStart)
            .SumAsync(p => (decimal?)p.PaymentAmount, cancellationToken) ?? 0;

        var completedMonths = await db.MonthlyBids
            .GroupBy(b => b.GroupId)
            .Select(g => new { GroupId = g.Key, Count = g.Count() })
            .ToDictionaryAsync(x => x.GroupId, x => x.Count, cancellationToken);

        var pendingByGroup = await db.MemberPayments
            .Where(p => p.PaymentStatus == PaymentStatus.Pending)
            .GroupBy(p => p.GroupId)
            .Select(g => new { GroupId = g.Key, Amount = g.Sum(x => x.PaymentAmount) })
            .ToDictionaryAsync(x => x.GroupId, x => x.Amount, cancellationToken);

        var recent = groups.Take(8).Select(g => new GroupListItemDto(
            g.Id,
            g.GroupName,
            g.TotalMembers,
            g.MonthlyContribution,
            g.TotalMonthlyCollection,
            g.StartDate,
            g.Status.ToString().ToLowerInvariant(),
            completedMonths.GetValueOrDefault(g.Id),
            pendingByGroup.GetValueOrDefault(g.Id)
        )).ToList();

        var dto = new DashboardStatsDto(
            groups.Count,
            groups.Count(g => g.Status == GroupStatus.Active),
            groups.Count(g => g.Status == GroupStatus.Completed),
            memberCount,
            totalCollected,
            totalDistributed,
            totalCollected - totalDistributed,
            thisMonthCollected,
            recent);

        return Result<DashboardStatsDto>.Success(dto);
    }
}
