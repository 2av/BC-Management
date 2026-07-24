using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common;
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

        var organiserSeatIds = groups
            .Where(g => g.OrganiserGroupMemberId.HasValue)
            .Select(g => g.OrganiserGroupMemberId!.Value)
            .Distinct()
            .ToList();
        var organiserSeats = await db.GroupMembers
            .Include(gm => gm.Member)
            .Where(gm => organiserSeatIds.Contains(gm.Id))
            .ToDictionaryAsync(gm => gm.Id, cancellationToken);

        var organiserMemberIds = groups
            .Where(g => g.OrganiserMemberId.HasValue && !g.OrganiserGroupMemberId.HasValue)
            .Select(g => g.OrganiserMemberId!.Value)
            .Distinct()
            .ToList();
        var organiserNames = await db.Members
            .Where(m => organiserMemberIds.Contains(m.Id))
            .ToDictionaryAsync(m => m.Id, m => m.MemberName, cancellationToken);

        var list = groups.Select(g =>
        {
            string? orgName = null;
            if (g.OrganiserGroupMemberId is int sid && organiserSeats.TryGetValue(sid, out var seat))
                orgName = SeatHelper.FormatDisplayName(seat.Member.MemberName, seat.HandLabel, seat.MemberNumber);
            else if (g.OrganiserMemberId is int mid && organiserNames.TryGetValue(mid, out var n))
                orgName = n;

            return new GroupListItemDto(
                g.Id,
                g.GroupName,
                g.TotalMembers,
                g.MonthlyContribution,
                g.TotalMonthlyCollection,
                g.StartDate,
                g.Status.ToString().ToLowerInvariant(),
                completedMonths.GetValueOrDefault(g.Id),
                pendingByGroup.GetValueOrDefault(g.Id),
                g.OrganiserMemberId,
                g.OrganiserGroupMemberId,
                orgName);
        }).ToList();

        return Result<IReadOnlyList<GroupListItemDto>>.Success(list);
    }
}
