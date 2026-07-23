using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;

namespace MitraNiidhi.Application.Groups;

public record GetGroupsQuery : IRequest<Result<IReadOnlyList<GroupListItemDto>>>;

public class GetGroupsQueryHandler(IAppDbContext db)
    : IRequestHandler<GetGroupsQuery, Result<IReadOnlyList<GroupListItemDto>>>
{
    public async Task<Result<IReadOnlyList<GroupListItemDto>>> Handle(GetGroupsQuery request, CancellationToken cancellationToken)
    {
        var groups = await db.BcGroups
            .OrderByDescending(g => g.CreatedAt)
            .ToListAsync(cancellationToken);

        var completedMonths = await db.MonthlyBids
            .GroupBy(b => b.GroupId)
            .Select(g => new { GroupId = g.Key, Count = g.Count() })
            .ToDictionaryAsync(x => x.GroupId, x => x.Count, cancellationToken);

        var pendingByGroup = await db.MemberPayments
            .Where(p => p.PaymentStatus == Domain.Enums.PaymentStatus.Pending)
            .GroupBy(p => p.GroupId)
            .Select(g => new { GroupId = g.Key, Amount = g.Sum(x => x.PaymentAmount) })
            .ToDictionaryAsync(x => x.GroupId, x => x.Amount, cancellationToken);

        var list = groups.Select(g => new GroupListItemDto(
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

        return Result<IReadOnlyList<GroupListItemDto>>.Success(list);
    }
}
