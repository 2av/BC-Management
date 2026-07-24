using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Payments;

public record GetGroupPaymentsQuery(int GroupId, int? MonthNumber = null, string? Status = null)
    : IRequest<Result<GroupPaymentsOverviewDto>>;

public class GetGroupPaymentsQueryHandler(IAppDbContext db)
    : IRequestHandler<GetGroupPaymentsQuery, Result<GroupPaymentsOverviewDto>>
{
    public async Task<Result<GroupPaymentsOverviewDto>> Handle(GetGroupPaymentsQuery request, CancellationToken cancellationToken)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == request.GroupId, cancellationToken);
        if (group is null)
            return Result<GroupPaymentsOverviewDto>.Failure("Group not found.");

        var query = db.MemberPayments
            .Include(p => p.Member)
            .Where(p => p.GroupId == request.GroupId);

        if (request.MonthNumber is int month)
            query = query.Where(p => p.MonthNumber == month);

        if (!string.IsNullOrWhiteSpace(request.Status) &&
            PaymentStatusMapper.TryParse(request.Status, out var statusFilter))
            query = query.Where(p => p.PaymentStatus == statusFilter);

        var payments = await query
            .OrderBy(p => p.MonthNumber)
            .ThenBy(p => p.Member.MemberName)
            .ToListAsync(cancellationToken);

        var seats = await db.GroupMembers
            .Where(gm => gm.GroupId == request.GroupId)
            .ToListAsync(cancellationToken);

        var bids = await db.MonthlyBids
            .Include(b => b.TakenByMember)
            .Include(b => b.TakenByGroupMember)
            .Where(b => b.GroupId == request.GroupId)
            .ToDictionaryAsync(b => b.MonthNumber, cancellationToken);

        var dueByMonth = await db.MonthBiddingStatuses
            .Where(s => s.GroupId == request.GroupId)
            .ToDictionaryAsync(s => s.MonthNumber, s => s.PaymentDueAmount, cancellationToken);

        var items = payments.Select(p =>
        {
            bids.TryGetValue(p.MonthNumber, out var bid);
            dueByMonth.TryGetValue(p.MonthNumber, out var due);
            var expected = UpiPaymentHelper.ResolveDueAmount(
                group.MonthlyContribution, due, bid?.GainPerMember);
            var seat = p.GroupMemberId is int sid
                ? seats.FirstOrDefault(s => s.Id == sid)
                : seats.FirstOrDefault(s => s.MemberId == p.MemberId);
            var winnerName = bid?.TakenByGroupMember is not null
                ? $"{bid.TakenByMember?.MemberName ?? "?"} · {bid.TakenByGroupMember.HandLabel ?? $"#{bid.TakenByGroupMember.MemberNumber}"}"
                : bid?.TakenByMember?.MemberName;
            return new PaymentItemDto(
                p.Id,
                p.GroupId,
                group.GroupName,
                p.MemberId,
                p.GroupMemberId ?? seat?.Id,
                p.Member.MemberName,
                seat?.MemberNumber ?? 0,
                seat?.HandLabel,
                p.MonthNumber,
                p.PaymentAmount,
                expected,
                PaymentStatusMapper.ToApi(p.PaymentStatus),
                p.PaymentDate,
                p.TransactionId,
                winnerName,
                bid?.BidAmount,
                bid?.GainPerMember);
        }).ToList();

        var allForStats = await db.MemberPayments
            .Where(p => p.GroupId == request.GroupId)
            .ToListAsync(cancellationToken);

        var monthDues = Enumerable.Range(1, group.TotalMembers)
            .Select(m =>
            {
                dueByMonth.TryGetValue(m, out var due);
                bids.TryGetValue(m, out var bid);
                return new MonthPaymentDueDto(
                    m,
                    due,
                    UpiPaymentHelper.ResolveDueAmount(group.MonthlyContribution, due, bid?.GainPerMember));
            })
            .ToList();

        return Result<GroupPaymentsOverviewDto>.Success(new GroupPaymentsOverviewDto(
            group.Id,
            group.GroupName,
            group.MonthlyContribution,
            group.TotalMonthlyCollection,
            allForStats.Count(p => p.PaymentStatus == PaymentStatus.Pending),
            allForStats.Where(p => p.PaymentStatus == PaymentStatus.Pending).Sum(p => p.PaymentAmount),
            allForStats.Count(p => p.PaymentStatus == PaymentStatus.Paid),
            allForStats.Where(p => p.PaymentStatus == PaymentStatus.Paid).Sum(p => p.PaymentAmount),
            items,
            monthDues));
    }
}
