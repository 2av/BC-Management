using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Application.Members;
using MitraNiidhi.Domain.Entities;
using MitraNiidhi.Domain.Enums;
using MitraNiidhi.Domain.Services;

namespace MitraNiidhi.Application.Groups;

public record CreateGroupCommand(CreateGroupRequest Request) : IRequest<Result<GroupListItemDto>>;

public class CreateGroupCommandHandler(IAppDbContext db, ICurrentUser currentUser, IPasswordHasher passwordHasher)
    : IRequestHandler<CreateGroupCommand, Result<GroupListItemDto>>
{
    public async Task<Result<GroupListItemDto>> Handle(CreateGroupCommand command, CancellationToken cancellationToken)
    {
        var req = command.Request;
        if (string.IsNullOrWhiteSpace(req.GroupName))
            return Result<GroupListItemDto>.Failure("Group name is required.");
        if (req.TotalMembers is < 2 or > 50)
            return Result<GroupListItemDto>.Failure("Total members must be between 2 and 50.");
        if (req.MonthlyContribution <= 0)
            return Result<GroupListItemDto>.Failure("Monthly BC amount must be greater than 0.");

        var slots = (req.Members ?? Array.Empty<CreateGroupMemberInput>())
            .Where(m => m.MemberId.HasValue || !string.IsNullOrWhiteSpace(m.MemberName))
            .ToList();

        if (slots.Count == 0 && req.MemberNames is { Count: > 0 })
        {
            slots = req.MemberNames
                .Where(n => !string.IsNullOrWhiteSpace(n))
                .Select(n => new CreateGroupMemberInput(null, n.Trim()))
                .ToList();
        }

        if (slots.Count != req.TotalMembers)
            return Result<GroupListItemDto>.Failure($"Select or create exactly {req.TotalMembers} members for this group.");

        if (currentUser.ClientId is null && !currentUser.IsSuperAdmin)
            return Result<GroupListItemDto>.Failure("Client context required.");

        // Validate existing member ids up front.
        var existingIds = slots.Where(s => s.MemberId.HasValue).Select(s => s.MemberId!.Value).ToList();
        if (existingIds.Count > 0)
        {
            var found = await db.Members.Where(m => existingIds.Contains(m.Id)).Select(m => m.Id).ToListAsync(cancellationToken);
            var missing = existingIds.Except(found).ToList();
            if (missing.Count > 0)
                return Result<GroupListItemDto>.Failure($"Unknown member id(s): {string.Join(", ", missing)}.");
        }

        var collection = BcCalculationService.TotalMonthlyCollection(req.MonthlyContribution, req.TotalMembers);
        var group = new BcGroup
        {
            ClientId = currentUser.ClientId,
            GroupName = req.GroupName.Trim(),
            TotalMembers = req.TotalMembers,
            MonthlyContribution = req.MonthlyContribution,
            TotalMonthlyCollection = collection,
            StartDate = req.StartDate,
            Status = GroupStatus.Active
        };
        db.BcGroups.Add(group);
        await db.SaveChangesAsync(cancellationToken);

        // Track how many seats each login already got so we can label hands.
        var seatCounts = new Dictionary<int, int>();
        var createdSeats = new List<GroupMember>();
        var plannedByMemberId = slots
            .Where(s => s.MemberId.HasValue)
            .GroupBy(s => s.MemberId!.Value)
            .ToDictionary(g => g.Key, g => g.Count());

        for (var i = 0; i < slots.Count; i++)
        {
            var slot = slots[i];
            int memberId;
            if (slot.MemberId is int existingId)
            {
                memberId = existingId;
            }
            else
            {
                var displayName = slot.MemberName!.Trim();
                var username = await MemberUsernameHelper.EnsureUniqueUsernameAsync(db, displayName, null, cancellationToken);
                var member = new Member
                {
                    MemberName = displayName,
                    Username = username,
                    PasswordHash = passwordHasher.Hash("member123"),
                    Status = "active"
                };
                db.Members.Add(member);
                await db.SaveChangesAsync(cancellationToken);
                memberId = member.Id;
            }

            seatCounts.TryGetValue(memberId, out var prior);
            var handIndex = prior + 1;
            seatCounts[memberId] = handIndex;

            string? handLabel = null;
            if (plannedByMemberId.GetValueOrDefault(memberId) > 1)
                handLabel = $"Hand {handIndex}";

            var seat = new GroupMember
            {
                GroupId = group.Id,
                MemberId = memberId,
                MemberNumber = i + 1,
                HandLabel = handLabel,
                Status = "active"
            };
            db.GroupMembers.Add(seat);
            await db.SaveChangesAsync(cancellationToken);
            await MemberUsernameHelper.EnsureSummaryAsync(db, group, memberId, cancellationToken, seat.Id);
            createdSeats.Add(seat);
        }

        if (req.OrganiserSlotIndex is int orgIndex)
        {
            if (orgIndex < 0 || orgIndex >= createdSeats.Count)
                return Result<GroupListItemDto>.Failure("Organiser must be one of the group members.");
            var orgSeat = createdSeats[orgIndex];
            group.OrganiserMemberId = orgSeat.MemberId;
            group.OrganiserGroupMemberId = orgSeat.Id;
        }
        else
        {
            // Default: first seat is organiser (Month 1 recipient).
            var first = createdSeats[0];
            group.OrganiserMemberId = first.MemberId;
            group.OrganiserGroupMemberId = first.Id;
        }

        for (var month = 1; month <= group.TotalMembers; month++)
        {
            db.MonthBiddingStatuses.Add(new MonthBiddingStatus
            {
                GroupId = group.Id,
                ClientId = group.ClientId,
                MonthNumber = month,
                BiddingStatus = BiddingStatus.NotStarted
            });
        }

        await db.SaveChangesAsync(cancellationToken);

        return Result<GroupListItemDto>.Success(new GroupListItemDto(
            group.Id, group.GroupName, group.TotalMembers, group.MonthlyContribution,
            group.TotalMonthlyCollection, group.StartDate, "active", 0, 0,
            group.OrganiserMemberId, group.OrganiserGroupMemberId, null));
    }
}

public record UpdateGroupCommand(int GroupId, UpdateGroupRequest Request) : IRequest<Result>;

public class UpdateGroupCommandHandler(IAppDbContext db)
    : IRequestHandler<UpdateGroupCommand, Result>
{
    public async Task<Result> Handle(UpdateGroupCommand command, CancellationToken cancellationToken)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, cancellationToken);
        if (group is null)
            return Result.Failure("Group not found.");

        var req = command.Request;
        if (string.IsNullOrWhiteSpace(req.GroupName))
            return Result.Failure("Group name is required.");
        if (req.MonthlyContribution <= 0)
            return Result.Failure("Monthly BC amount must be greater than 0.");

        var status = req.Status.Trim().ToLowerInvariant();
        if (status is not ("active" or "completed"))
            return Result.Failure("Status must be active or completed.");

        group.GroupName = req.GroupName.Trim();
        group.StartDate = req.StartDate;
        group.Status = status == "completed" ? GroupStatus.Completed : GroupStatus.Active;
        group.MonthlyContribution = req.MonthlyContribution;
        group.TotalMonthlyCollection = BcCalculationService.TotalMonthlyCollection(
            req.MonthlyContribution,
            group.TotalMembers);

        if (req.OrganiserGroupMemberId is int orgSeatId || req.OrganiserMemberId is int)
        {
            GroupMember? orgSeat = null;
            if (req.OrganiserGroupMemberId is int seatId)
            {
                orgSeat = await db.GroupMembers.FirstOrDefaultAsync(
                    gm => gm.Id == seatId && gm.GroupId == group.Id && gm.Status == "active",
                    cancellationToken);
            }
            else if (req.OrganiserMemberId is int mid)
            {
                orgSeat = await db.GroupMembers
                    .Where(gm => gm.GroupId == group.Id && gm.MemberId == mid && gm.Status == "active")
                    .OrderBy(gm => gm.MemberNumber)
                    .FirstOrDefaultAsync(cancellationToken);
            }

            if (orgSeat is null)
                return Result.Failure("Organiser must be an active member of this group.");

            group.OrganiserMemberId = orgSeat.MemberId;
            group.OrganiserGroupMemberId = orgSeat.Id;
        }

        await db.SaveChangesAsync(cancellationToken);
        return Result.Success();
    }
}

public record CloneGroupCommand(int SourceGroupId, CloneGroupRequest Request) : IRequest<Result<GroupListItemDto>>;

public class CloneGroupCommandHandler(IAppDbContext db, ICurrentUser currentUser, IPasswordHasher passwordHasher)
    : IRequestHandler<CloneGroupCommand, Result<GroupListItemDto>>
{
    public async Task<Result<GroupListItemDto>> Handle(CloneGroupCommand command, CancellationToken cancellationToken)
    {
        var source = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.SourceGroupId, cancellationToken);
        if (source is null)
            return Result<GroupListItemDto>.Failure("Source group not found.");

        var req = command.Request;
        if (string.IsNullOrWhiteSpace(req.NewGroupName))
            return Result<GroupListItemDto>.Failure("New group name is required.");

        var newNames = req.NewMemberNames?.Where(n => !string.IsNullOrWhiteSpace(n)).Select(n => n.Trim()).ToList()
                       ?? new List<string>();
        var selectedIds = req.SelectedMemberIds.Distinct().ToList();
        var total = selectedIds.Count + newNames.Count;
        if (total < 2)
            return Result<GroupListItemDto>.Failure("Clone needs at least 2 members.");

        var existingMembers = await db.Members
            .Where(m => selectedIds.Contains(m.Id))
            .ToListAsync(cancellationToken);
        if (existingMembers.Count != selectedIds.Count)
            return Result<GroupListItemDto>.Failure("One or more selected members were not found.");

        var collection = BcCalculationService.TotalMonthlyCollection(source.MonthlyContribution, total);
        var group = new BcGroup
        {
            ClientId = currentUser.ClientId ?? source.ClientId,
            GroupName = req.NewGroupName.Trim(),
            TotalMembers = total,
            MonthlyContribution = source.MonthlyContribution,
            TotalMonthlyCollection = collection,
            StartDate = req.StartDate,
            Status = GroupStatus.Active
        };
        db.BcGroups.Add(group);
        await db.SaveChangesAsync(cancellationToken);

        var number = 1;
        foreach (var member in existingMembers.OrderBy(m => m.MemberName))
        {
            var seat = new GroupMember
            {
                GroupId = group.Id,
                MemberId = member.Id,
                MemberNumber = number++,
                Status = "active"
            };
            db.GroupMembers.Add(seat);
            await db.SaveChangesAsync(cancellationToken);
            await MemberUsernameHelper.EnsureSummaryAsync(db, group, member.Id, cancellationToken, seat.Id);
        }

        foreach (var name in newNames)
        {
            var username = await MemberUsernameHelper.EnsureUniqueUsernameAsync(db, name, null, cancellationToken);
            var member = new Member
            {
                MemberName = name,
                Username = username,
                PasswordHash = passwordHasher.Hash("member123"),
                Status = "active"
            };
            db.Members.Add(member);
            await db.SaveChangesAsync(cancellationToken);
            var seat = new GroupMember
            {
                GroupId = group.Id,
                MemberId = member.Id,
                MemberNumber = number++,
                Status = "active"
            };
            db.GroupMembers.Add(seat);
            await db.SaveChangesAsync(cancellationToken);
            await MemberUsernameHelper.EnsureSummaryAsync(db, group, member.Id, cancellationToken, seat.Id);
        }

        await db.SaveChangesAsync(cancellationToken);

        // Copy organiser if they were included in the clone roster.
        if (source.OrganiserMemberId is int srcOrgMid && selectedIds.Contains(srcOrgMid))
        {
            var orgSeat = await db.GroupMembers
                .Where(gm => gm.GroupId == group.Id && gm.MemberId == srcOrgMid && gm.Status == "active")
                .OrderBy(gm => gm.MemberNumber)
                .FirstOrDefaultAsync(cancellationToken);
            if (orgSeat is not null)
            {
                group.OrganiserMemberId = orgSeat.MemberId;
                group.OrganiserGroupMemberId = orgSeat.Id;
            }
        }

        if (group.OrganiserMemberId is null)
        {
            var firstSeat = await db.GroupMembers
                .Where(gm => gm.GroupId == group.Id && gm.Status == "active")
                .OrderBy(gm => gm.MemberNumber)
                .FirstOrDefaultAsync(cancellationToken);
            if (firstSeat is not null)
            {
                group.OrganiserMemberId = firstSeat.MemberId;
                group.OrganiserGroupMemberId = firstSeat.Id;
            }
        }

        for (var month = 1; month <= group.TotalMembers; month++)
        {
            db.MonthBiddingStatuses.Add(new MonthBiddingStatus
            {
                GroupId = group.Id,
                ClientId = group.ClientId,
                MonthNumber = month,
                BiddingStatus = BiddingStatus.NotStarted
            });
        }

        await db.SaveChangesAsync(cancellationToken);
        return Result<GroupListItemDto>.Success(new GroupListItemDto(
            group.Id, group.GroupName, group.TotalMembers, group.MonthlyContribution,
            group.TotalMonthlyCollection, group.StartDate, "active", 0, 0,
            group.OrganiserMemberId, group.OrganiserGroupMemberId, null));
    }
}

public record DeleteGroupCommand(int GroupId) : IRequest<Result>;

/// <summary>
/// Permanently deletes a group and all related data (payments, bids, seats, etc.),
/// matching the legacy PHP manage_groups delete — allowed even when payments exist.
/// </summary>
public class DeleteGroupCommandHandler(IAppDbContext db) : IRequestHandler<DeleteGroupCommand, Result>
{
    public async Task<Result> Handle(DeleteGroupCommand command, CancellationToken cancellationToken)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, cancellationToken);
        if (group is null)
            return Result.Failure("Group not found.");

        var month1Paid = await db.MemberPayments.AnyAsync(
            p => p.GroupId == command.GroupId
                 && p.MonthNumber == 1
                 && p.PaymentStatus == Domain.Enums.PaymentStatus.Paid,
            cancellationToken);
        if (month1Paid)
            return Result.Failure("Cannot delete this group — Month 1 payment is already done.");

        if (db is not DbContext efContext)
            return Result.Failure("Database context unavailable.");

        await using var tx = await efContext.Database.BeginTransactionAsync(cancellationToken);
        try
        {
            // Break FKs that point at group_members before removing seats.
            group.OrganiserMemberId = null;
            group.OrganiserGroupMemberId = null;
            await db.SaveChangesAsync(cancellationToken);

            var monthlyBids = await db.MonthlyBids
                .Where(b => b.GroupId == command.GroupId)
                .ToListAsync(cancellationToken);
            foreach (var b in monthlyBids)
                b.TakenByGroupMemberId = null;

            var statuses = await db.MonthBiddingStatuses
                .Where(s => s.GroupId == command.GroupId)
                .ToListAsync(cancellationToken);
            foreach (var s in statuses)
                s.WinnerGroupMemberId = null;

            var payments = await db.MemberPayments
                .Where(p => p.GroupId == command.GroupId)
                .ToListAsync(cancellationToken);
            foreach (var p in payments)
                p.GroupMemberId = null;

            var summaries = await db.MemberSummaries
                .Where(s => s.GroupId == command.GroupId)
                .ToListAsync(cancellationToken);
            foreach (var s in summaries)
                s.GroupMemberId = null;

            var memberBids = await db.MemberBids
                .Where(b => b.GroupId == command.GroupId)
                .ToListAsync(cancellationToken);
            foreach (var b in memberBids)
                b.GroupMemberId = null;

            var picks = await db.RandomPicks
                .Where(p => p.GroupId == command.GroupId)
                .ToListAsync(cancellationToken);
            foreach (var p in picks)
            {
                p.SelectedGroupMemberId = null;
                p.AdminOverrideGroupMemberId = null;
            }

            await db.SaveChangesAsync(cancellationToken);

            db.RandomPicks.RemoveRange(picks);
            db.MemberBids.RemoveRange(memberBids);
            db.MonthBiddingStatuses.RemoveRange(statuses);
            db.MemberPayments.RemoveRange(payments);
            db.MemberSummaries.RemoveRange(summaries);
            db.MonthlyBids.RemoveRange(monthlyBids);
            await db.SaveChangesAsync(cancellationToken);

            var seats = await db.GroupMembers
                .Where(gm => gm.GroupId == command.GroupId)
                .ToListAsync(cancellationToken);
            db.GroupMembers.RemoveRange(seats);
            await db.SaveChangesAsync(cancellationToken);

            db.BcGroups.Remove(group);
            await db.SaveChangesAsync(cancellationToken);
            await tx.CommitAsync(cancellationToken);
            return Result.Success();
        }
        catch (Exception ex)
        {
            await tx.RollbackAsync(cancellationToken);
            return Result.Failure($"Failed to delete group: {ex.Message}");
        }
    }
}
