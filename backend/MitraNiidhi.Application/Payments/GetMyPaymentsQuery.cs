using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Payments;

public record GetMyPaymentsQuery : IRequest<Result<MemberPaymentsDto>>;

public class GetMyPaymentsQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetMyPaymentsQuery, Result<MemberPaymentsDto>>
{
    public async Task<Result<MemberPaymentsDto>> Handle(GetMyPaymentsQuery request, CancellationToken cancellationToken)
    {
        if (currentUser.UserId is null)
            return Result<MemberPaymentsDto>.Failure("Not authenticated.");

        var memberId = currentUser.UserId.Value;

        var payments = await db.MemberPayments
            .Include(p => p.Group)
            .Where(p => p.MemberId == memberId)
            .OrderByDescending(p => p.MonthNumber)
            .ThenBy(p => p.Group.GroupName)
            .ToListAsync(cancellationToken);

        var groupIds = payments.Select(p => p.GroupId).Distinct().ToList();
        var bids = await db.MonthlyBids
            .Include(b => b.TakenByMember)
            .Where(b => groupIds.Contains(b.GroupId))
            .ToListAsync(cancellationToken);

        var seats = await db.GroupMembers
            .Where(gm => gm.MemberId == memberId && groupIds.Contains(gm.GroupId))
            .ToListAsync(cancellationToken);

        var bidLookup = bids.ToDictionary(b => (b.GroupId, b.MonthNumber));

        var items = payments.Select(p =>
        {
            bidLookup.TryGetValue((p.GroupId, p.MonthNumber), out var bid);
            var seat = p.GroupMemberId is int sid
                ? seats.FirstOrDefault(s => s.Id == sid)
                : seats.FirstOrDefault(s => s.GroupId == p.GroupId);
            return new MemberPaymentItemDto(
                p.Id,
                p.GroupId,
                p.Group.GroupName,
                p.GroupMemberId ?? seat?.Id,
                seat?.MemberNumber,
                seat?.HandLabel,
                p.MonthNumber,
                p.PaymentAmount,
                PaymentStatusMapper.ToApi(p.PaymentStatus),
                p.PaymentDate,
                bid?.TakenByMember?.MemberName);
        }).ToList();

        return Result<MemberPaymentsDto>.Success(new MemberPaymentsDto(
            items.Where(i => i.PaymentStatus == "pending").Sum(i => i.PaymentAmount),
            items.Where(i => i.PaymentStatus == "paid").Sum(i => i.PaymentAmount),
            items));
    }
}
