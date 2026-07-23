using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;

namespace MitraNiidhi.Application.Groups;

public record GetGroupLedgerQuery(int GroupId) : IRequest<Result<GroupLedgerDto>>;

public class GetGroupLedgerQueryHandler(IAppDbContext db)
    : IRequestHandler<GetGroupLedgerQuery, Result<GroupLedgerDto>>
{
    public async Task<Result<GroupLedgerDto>> Handle(GetGroupLedgerQuery request, CancellationToken cancellationToken)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == request.GroupId, cancellationToken);
        if (group is null)
            return Result<GroupLedgerDto>.Failure("Group not found.");

        var bids = await db.MonthlyBids
            .Where(b => b.GroupId == request.GroupId)
            .OrderBy(b => b.MonthNumber)
            .Select(b => new
            {
                b.MonthNumber,
                b.TakenByMemberId,
                b.TakenByGroupMemberId,
                TakenByName = b.TakenByMember != null ? b.TakenByMember.MemberName : null,
                b.IsBid,
                b.BidAmount,
                b.NetPayable,
                b.GainPerMember,
                b.PaymentDate
            })
            .ToListAsync(cancellationToken);

        var seats = await db.GroupMembers
            .Where(gm => gm.GroupId == request.GroupId && gm.Status == "active")
            .OrderBy(gm => gm.MemberNumber)
            .Select(gm => new
            {
                gm.Id,
                gm.MemberId,
                gm.MemberNumber,
                gm.HandLabel,
                gm.Member.MemberName
            })
            .ToListAsync(cancellationToken);

        var payments = await db.MemberPayments
            .Where(p => p.GroupId == request.GroupId)
            .ToListAsync(cancellationToken);

        var summaries = await db.MemberSummaries
            .Where(s => s.GroupId == request.GroupId)
            .ToListAsync(cancellationToken);

        var monthNumbers = bids.Select(b => b.MonthNumber).ToList();
        if (monthNumbers.Count == 0)
            monthNumbers = Enumerable.Range(1, group.TotalMembers).ToList();

        var seatById = seats.ToDictionary(s => s.Id);

        var rows = seats.Select(m =>
        {
            var byMonth = monthNumbers.ToDictionary(
                month => month,
                month =>
                {
                    var payment = payments.FirstOrDefault(p =>
                        p.MonthNumber == month
                        && (p.GroupMemberId == m.Id
                            || (p.GroupMemberId == null && p.MemberId == m.MemberId)));
                    return payment?.PaymentAmount;
                });

            var summary = summaries.FirstOrDefault(s =>
                s.GroupMemberId == m.Id
                || (s.GroupMemberId == null && s.MemberId == m.MemberId));

            return new MemberLedgerRowDto(
                m.Id,
                m.MemberId,
                m.MemberNumber,
                SeatHelper.FormatDisplayName(m.MemberName, m.HandLabel, m.MemberNumber),
                m.HandLabel,
                byMonth!,
                summary?.TotalPaid ?? byMonth.Values.Where(v => v.HasValue).Sum(v => v!.Value),
                summary?.GivenAmount ?? 0,
                summary?.Profit ?? 0);
        }).ToList();

        var bidDtos = bids.Select(b =>
        {
            string? name = b.TakenByName;
            if (b.TakenByGroupMemberId is int seatId && seatById.TryGetValue(seatId, out var seat))
                name = SeatHelper.FormatDisplayName(seat.MemberName, seat.HandLabel, seat.MemberNumber);
            return new MonthlyBidDto(
                b.MonthNumber,
                name,
                b.TakenByMemberId,
                b.TakenByGroupMemberId,
                b.IsBid,
                b.BidAmount,
                b.NetPayable,
                b.GainPerMember,
                b.PaymentDate);
        }).ToList();

        return Result<GroupLedgerDto>.Success(new GroupLedgerDto(
            group.Id,
            group.GroupName,
            group.TotalMembers,
            group.MonthlyContribution,
            group.TotalMonthlyCollection,
            group.StartDate,
            group.Status.ToString().ToLowerInvariant(),
            bidDtos,
            rows));
    }
}
