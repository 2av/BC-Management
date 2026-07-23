using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;

namespace MitraNiidhi.Application.Bidding;

public record GetMonthBidsQuery(int GroupId, int MonthNumber) : IRequest<Result<IReadOnlyList<BidItemDto>>>;

public class GetMonthBidsQueryHandler(IAppDbContext db)
    : IRequestHandler<GetMonthBidsQuery, Result<IReadOnlyList<BidItemDto>>>
{
    public async Task<Result<IReadOnlyList<BidItemDto>>> Handle(GetMonthBidsQuery request, CancellationToken cancellationToken)
    {
        var bids = await db.MemberBids
            .Include(b => b.Member)
            .Include(b => b.GroupMember)
            .Where(b => b.GroupId == request.GroupId && b.MonthNumber == request.MonthNumber)
            .OrderBy(b => b.BidAmount)
            .ToListAsync(cancellationToken);

        var seats = await db.GroupMembers
            .Where(gm => gm.GroupId == request.GroupId)
            .ToListAsync(cancellationToken);

        var result = bids.Select(b =>
        {
            var seat = b.GroupMemberId is int sid
                ? seats.FirstOrDefault(s => s.Id == sid)
                : seats.FirstOrDefault(s => s.MemberId == b.MemberId);
            return new BidItemDto(
                b.Id,
                b.MemberId,
                b.GroupMemberId ?? seat?.Id,
                b.Member.MemberName,
                seat?.MemberNumber ?? 0,
                seat?.HandLabel,
                b.BidAmount,
                b.BidStatus,
                b.BidDate);
        }).ToList();

        return Result<IReadOnlyList<BidItemDto>>.Success(result);
    }
}
