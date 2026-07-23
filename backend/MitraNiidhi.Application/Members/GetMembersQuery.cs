using System.Text.RegularExpressions;
using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Entities;

namespace MitraNiidhi.Application.Members;

public record GetMembersQuery(string? Search = null, string? Status = null)
    : IRequest<Result<IReadOnlyList<MemberListItemDto>>>;

public class GetMembersQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetMembersQuery, Result<IReadOnlyList<MemberListItemDto>>>
{
    public async Task<Result<IReadOnlyList<MemberListItemDto>>> Handle(GetMembersQuery request, CancellationToken cancellationToken)
    {
        var clientGroupIds = await db.BcGroups.Select(g => g.Id).ToListAsync(cancellationToken);

        var memberships = await db.GroupMembers
            .Include(gm => gm.Group)
            .Include(gm => gm.Member)
            .Where(gm => clientGroupIds.Contains(gm.GroupId))
            .ToListAsync(cancellationToken);

        var byMember = memberships
            .GroupBy(gm => gm.MemberId)
            .Select(g =>
            {
                var member = g.First().Member;
                return new MemberListItemDto(
                    member.Id,
                    member.MemberName,
                    member.Username,
                    member.Phone,
                    member.Email,
                    member.Address,
                    member.Status,
                    g.Count(),
                    g.OrderBy(x => x.Group.GroupName).ThenBy(x => x.MemberNumber)
                        .Select(x => new MemberGroupBriefDto(
                            x.Id,
                            x.GroupId,
                            x.Group.GroupName,
                            x.MemberNumber,
                            x.HandLabel,
                            x.Status,
                            x.JoinedDate))
                        .ToList());
            })
            .AsEnumerable();

        if (!string.IsNullOrWhiteSpace(request.Status))
            byMember = byMember.Where(m => m.Status.Equals(request.Status, StringComparison.OrdinalIgnoreCase));

        if (!string.IsNullOrWhiteSpace(request.Search))
        {
            var q = request.Search.Trim().ToLowerInvariant();
            byMember = byMember.Where(m =>
                m.MemberName.ToLowerInvariant().Contains(q) ||
                (m.Username?.ToLowerInvariant().Contains(q) ?? false) ||
                (m.Phone?.Contains(q) ?? false) ||
                (m.Email?.ToLowerInvariant().Contains(q) ?? false));
        }

        // Super-admin / empty tenant edge: also include orphan members with no groups when no client filter
        if (currentUser.IsSuperAdmin && !memberships.Any())
        {
            var all = await db.Members.OrderBy(m => m.MemberName).ToListAsync(cancellationToken);
            return Result<IReadOnlyList<MemberListItemDto>>.Success(
                all.Select(m => new MemberListItemDto(m.Id, m.MemberName, m.Username, m.Phone, m.Email, m.Address, m.Status, 0, Array.Empty<MemberGroupBriefDto>())).ToList());
        }

        return Result<IReadOnlyList<MemberListItemDto>>.Success(
            byMember.OrderBy(m => m.MemberName).ToList());
    }
}

public record GetGroupMembersQuery(int GroupId) : IRequest<Result<IReadOnlyList<GroupMemberRosterItemDto>>>;

public class GetGroupMembersQueryHandler(IAppDbContext db)
    : IRequestHandler<GetGroupMembersQuery, Result<IReadOnlyList<GroupMemberRosterItemDto>>>
{
    public async Task<Result<IReadOnlyList<GroupMemberRosterItemDto>>> Handle(GetGroupMembersQuery request, CancellationToken cancellationToken)
    {
        if (!await db.BcGroups.AnyAsync(g => g.Id == request.GroupId, cancellationToken))
            return Result<IReadOnlyList<GroupMemberRosterItemDto>>.Failure("Group not found.");

        var rows = await db.GroupMembers
            .Include(gm => gm.Member)
            .Where(gm => gm.GroupId == request.GroupId)
            .OrderBy(gm => gm.MemberNumber)
            .Select(gm => new GroupMemberRosterItemDto(
                gm.Id,
                gm.MemberId,
                gm.Member.MemberName,
                gm.Member.Username,
                gm.Member.Phone,
                gm.MemberNumber,
                gm.HandLabel,
                gm.Status,
                gm.JoinedDate))
            .ToListAsync(cancellationToken);

        return Result<IReadOnlyList<GroupMemberRosterItemDto>>.Success(rows);
    }
}

internal static class MemberUsernameHelper
{
    public static async Task<string> EnsureUniqueUsernameAsync(
        IAppDbContext db,
        string baseName,
        int? excludeMemberId,
        CancellationToken cancellationToken)
    {
        var slug = Regex.Replace(baseName.Trim().ToLowerInvariant(), @"[^a-z0-9]+", "");
        if (string.IsNullOrWhiteSpace(slug))
            slug = "member";

        var candidate = slug;
        var i = 1;
        while (await db.Members.AnyAsync(
                   m => m.Username == candidate && (!excludeMemberId.HasValue || m.Id != excludeMemberId.Value),
                   cancellationToken))
        {
            candidate = $"{slug}{i++}";
        }

        return candidate;
    }

    public static async Task EnsureSummaryAsync(
        IAppDbContext db, BcGroup group, int memberId, CancellationToken cancellationToken, int? groupMemberId = null)
    {
        if (groupMemberId is int seatId)
        {
            var existsSeat = await db.MemberSummaries.AnyAsync(
                s => s.GroupId == group.Id && s.GroupMemberId == seatId, cancellationToken);
            if (existsSeat) return;
            db.MemberSummaries.Add(new MemberSummary
            {
                GroupId = group.Id,
                ClientId = group.ClientId,
                MemberId = memberId,
                GroupMemberId = seatId
            });
            return;
        }

        var exists = await db.MemberSummaries.AnyAsync(
            s => s.GroupId == group.Id && s.MemberId == memberId && s.GroupMemberId == null, cancellationToken);
        if (exists) return;

        db.MemberSummaries.Add(new MemberSummary
        {
            GroupId = group.Id,
            ClientId = group.ClientId,
            MemberId = memberId
        });
    }
}
