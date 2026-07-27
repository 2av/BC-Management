using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;

namespace MitraNiidhi.Application.Members;

public record MemberGroupDto(
    int GroupMemberId,
    int GroupId,
    string GroupName,
    int MemberNumber,
    string? HandLabel,
    string Status,
    decimal MonthlyContribution,
    int TotalMembers,
    DateOnly StartDate,
    DateOnly EndDate,
    int CompletedMonths,
    int PendingMonths,
    decimal TotalPaid,
    decimal GivenAmount,
    decimal Profit,
    decimal PendingAmount,
    int PendingPaymentCount,
    int? NextPendingMonth);

public record MemberDashboardDto(
    string FullName,
    int GroupCount,
    decimal TotalPaid,
    decimal TotalReceived,
    decimal PendingDues,
    IReadOnlyList<MemberGroupDto> Groups);

public record GetMemberDashboardQuery : IRequest<Result<MemberDashboardDto>>;

public class GetMemberDashboardQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetMemberDashboardQuery, Result<MemberDashboardDto>>
{
    public async Task<Result<MemberDashboardDto>> Handle(GetMemberDashboardQuery request, CancellationToken cancellationToken)
    {
        if (currentUser.UserId is null)
            return Result<MemberDashboardDto>.Failure("Not authenticated.");

        var memberId = currentUser.UserId.Value;
        var member = await db.Members.FirstOrDefaultAsync(m => m.Id == memberId, cancellationToken);
        if (member is null)
            return Result<MemberDashboardDto>.Failure("Member not found.");

        var memberships = await db.GroupMembers
            .Where(gm => gm.MemberId == memberId && gm.Status == "active")
            .Select(gm => new
            {
                gm.Id,
                gm.GroupId,
                gm.MemberNumber,
                gm.HandLabel,
                gm.Group.GroupName,
                gm.Group.MonthlyContribution,
                gm.Group.TotalMembers,
                gm.Group.StartDate,
                gm.Group.Status
            })
            .ToListAsync(cancellationToken);

        var groupIds = memberships.Select(m => m.GroupId).Distinct().ToList();

        var summaries = await db.MemberSummaries
            .Where(s => s.MemberId == memberId && groupIds.Contains(s.GroupId))
            .ToListAsync(cancellationToken);

        var completedByGroup = await db.MonthlyBids
            .Where(b => groupIds.Contains(b.GroupId))
            .GroupBy(b => b.GroupId)
            .Select(g => new { GroupId = g.Key, Count = g.Count() })
            .ToDictionaryAsync(x => x.GroupId, x => x.Count, cancellationToken);

        var pendingPayments = await db.MemberPayments
            .Where(p => p.MemberId == memberId
                        && groupIds.Contains(p.GroupId)
                        && p.PaymentStatus == Domain.Enums.PaymentStatus.Pending)
            .Select(p => new
            {
                p.GroupId,
                p.GroupMemberId,
                p.MonthNumber,
                p.PaymentAmount
            })
            .ToListAsync(cancellationToken);

        var pending = pendingPayments.Sum(p => p.PaymentAmount);

        var groups = memberships.Select(m =>
        {
            var summary = summaries.FirstOrDefault(s =>
                s.GroupMemberId == m.Id
                || (s.GroupMemberId == null && s.GroupId == m.GroupId && s.MemberId == memberId));
            var completed = completedByGroup.GetValueOrDefault(m.GroupId, 0);
            var pendingMonths = Math.Max(0, m.TotalMembers - completed);
            var endDate = m.StartDate.AddMonths(Math.Max(0, m.TotalMembers));

            // Prefer seat-scoped rows; fall back to member+group when GroupMemberId is null.
            var forSeat = pendingPayments
                .Where(p => p.GroupId == m.GroupId && p.GroupMemberId == m.Id)
                .ToList();
            if (forSeat.Count == 0)
            {
                forSeat = pendingPayments
                    .Where(p => p.GroupId == m.GroupId && p.GroupMemberId == null)
                    .ToList();
            }

            var pendingAmount = forSeat.Sum(p => p.PaymentAmount);
            var pendingCount = forSeat.Count;
            int? nextMonth = forSeat.Count == 0
                ? null
                : forSeat.Min(p => p.MonthNumber);

            return new MemberGroupDto(
                m.Id,
                m.GroupId,
                m.GroupName,
                m.MemberNumber,
                m.HandLabel,
                m.Status.ToString().ToLowerInvariant(),
                m.MonthlyContribution,
                m.TotalMembers,
                m.StartDate,
                endDate,
                completed,
                pendingMonths,
                summary?.TotalPaid ?? 0,
                summary?.GivenAmount ?? 0,
                summary?.Profit ?? 0,
                pendingAmount,
                pendingCount,
                nextMonth);
        }).ToList();

        return Result<MemberDashboardDto>.Success(new MemberDashboardDto(
            member.MemberName,
            groups.Count,
            groups.Sum(g => g.TotalPaid),
            groups.Sum(g => g.GivenAmount),
            pending,
            groups));
    }
}
