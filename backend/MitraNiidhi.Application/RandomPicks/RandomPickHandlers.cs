using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Entities;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.RandomPicks;

public record RandomPickDto(
    int Id,
    int MonthNumber,
    int SelectedMemberId,
    int? SelectedGroupMemberId,
    string SelectedMemberName,
    int? AdminOverrideMemberId,
    int? AdminOverrideGroupMemberId,
    string? AdminOverrideMemberName,
    int EffectiveMemberId,
    int? EffectiveGroupMemberId,
    string EffectiveMemberName,
    string PickedByType,
    DateTime PickedAt);

public record AvailableMemberDto(
    int MemberId,
    int GroupMemberId,
    string MemberName,
    int MemberNumber,
    string? HandLabel);

public record AvailableRandomMembersDto(
    int? ActiveMonth,
    bool CanCustomPick,
    bool CanPlacePick,
    string? BlockReason,
    IReadOnlyList<AvailableMemberDto> Members);

public record PlaceRandomPickRequest(int MonthNumber);
public record CustomRandomPickRequest(int MonthNumber, int SelectedMemberId, int? SelectedGroupMemberId = null);
public record OverrideRandomPickRequest(int MemberId, int? GroupMemberId = null);

public record GetRandomPicksQuery(int GroupId) : IRequest<Result<IReadOnlyList<RandomPickDto>>>;
public record GetAvailableRandomMembersQuery(int GroupId) : IRequest<Result<AvailableRandomMembersDto>>;
public record PlaceRandomPickCommand(int GroupId, PlaceRandomPickRequest Request) : IRequest<Result<RandomPickDto>>;
public record CustomRandomPickCommand(int GroupId, CustomRandomPickRequest Request) : IRequest<Result<RandomPickDto>>;
public record OverrideRandomPickCommand(int GroupId, int MonthNumber, OverrideRandomPickRequest Request) : IRequest<Result>;
public record ClearOverrideCommand(int GroupId, int MonthNumber) : IRequest<Result>;

public class GetRandomPicksQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetRandomPicksQuery, Result<IReadOnlyList<RandomPickDto>>>
{
    public async Task<Result<IReadOnlyList<RandomPickDto>>> Handle(GetRandomPicksQuery request, CancellationToken cancellationToken)
    {
        var access = await RandomPickRules.EnsureGroupAccessAsync(db, currentUser, request.GroupId, cancellationToken);
        if (!access.Succeeded) return Result<IReadOnlyList<RandomPickDto>>.Failure(access.Error!);

        var picks = await db.RandomPicks
            .Include(p => p.SelectedMember)
            .Include(p => p.AdminOverrideMember)
            .Include(p => p.SelectedGroupMember)!.ThenInclude(gm => gm!.Member)
            .Include(p => p.AdminOverrideGroupMember)!.ThenInclude(gm => gm!.Member)
            .Where(p => p.GroupId == request.GroupId)
            .OrderBy(p => p.MonthNumber)
            .ToListAsync(cancellationToken);

        return Result<IReadOnlyList<RandomPickDto>>.Success(picks.Select(Map).ToList());
    }

    internal static RandomPickDto Map(RandomPick p)
    {
        var selectedName = p.SelectedGroupMember is not null
            ? SeatHelper.FormatDisplayName(
                p.SelectedGroupMember.Member.MemberName,
                p.SelectedGroupMember.HandLabel,
                p.SelectedGroupMember.MemberNumber)
            : p.SelectedMember.MemberName;

        string? overrideName = null;
        if (p.AdminOverrideGroupMember is not null)
        {
            overrideName = SeatHelper.FormatDisplayName(
                p.AdminOverrideGroupMember.Member.MemberName,
                p.AdminOverrideGroupMember.HandLabel,
                p.AdminOverrideGroupMember.MemberNumber);
        }
        else if (p.AdminOverrideMember is not null)
        {
            overrideName = p.AdminOverrideMember.MemberName;
        }

        var effectiveName = overrideName ?? selectedName;

        return new(
            p.Id,
            p.MonthNumber,
            p.SelectedMemberId,
            p.SelectedGroupMemberId,
            selectedName,
            p.AdminOverrideMemberId,
            p.AdminOverrideGroupMemberId,
            overrideName,
            p.EffectiveMemberId,
            p.EffectiveGroupMemberId,
            effectiveName,
            p.PickedByType,
            p.PickedAt);
    }
}

public class GetAvailableRandomMembersQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetAvailableRandomMembersQuery, Result<AvailableRandomMembersDto>>
{
    public async Task<Result<AvailableRandomMembersDto>> Handle(
        GetAvailableRandomMembersQuery request,
        CancellationToken cancellationToken)
    {
        var access = await RandomPickRules.EnsureGroupAccessAsync(db, currentUser, request.GroupId, cancellationToken);
        if (!access.Succeeded) return Result<AvailableRandomMembersDto>.Failure(access.Error!);

        var activeMonth = await RandomPickRules.GetActiveMonthAsync(db, request.GroupId, cancellationToken);
        var available = await RandomPickRules.GetAvailableAsync(db, request.GroupId, cancellationToken);
        var canCustom = RandomPickRules.CanCustomPick(currentUser);
        string? block = null;
        var canPlace = true;
        if (activeMonth is null)
        {
            canPlace = false;
            block = "All months are already completed.";
        }
        else if (activeMonth == 1)
        {
            canPlace = false;
            block = "Month 1 is reserved for the organiser — random pick starts from month 2.";
        }

        return Result<AvailableRandomMembersDto>.Success(new AvailableRandomMembersDto(
            activeMonth, canCustom, canPlace, block, available));
    }
}

public class PlaceRandomPickCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<PlaceRandomPickCommand, Result<RandomPickDto>>
{
    public async Task<Result<RandomPickDto>> Handle(PlaceRandomPickCommand command, CancellationToken cancellationToken)
    {
        var guard = await RandomPickRules.ValidatePlaceAsync(
            db, currentUser, command.GroupId, command.Request.MonthNumber, cancellationToken);
        if (!guard.Succeeded) return Result<RandomPickDto>.Failure(guard.Error!);

        var available = await RandomPickRules.GetAvailableAsync(db, command.GroupId, cancellationToken);
        if (available.Count == 0)
            return Result<RandomPickDto>.Failure("No eligible seats left for random pick.");

        var pick = available[Random.Shared.Next(available.Count)];
        return await RandomPickRules.SavePickAsync(
            db, currentUser, command.GroupId, command.Request.MonthNumber, pick.MemberId, pick.GroupMemberId, cancellationToken);
    }
}

public class CustomRandomPickCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<CustomRandomPickCommand, Result<RandomPickDto>>
{
    public async Task<Result<RandomPickDto>> Handle(CustomRandomPickCommand command, CancellationToken cancellationToken)
    {
        if (!RandomPickRules.CanCustomPick(currentUser))
            return Result<RandomPickDto>.Failure("You are not allowed to decide the winner. Use a fair random pick.");

        var guard = await RandomPickRules.ValidatePlaceAsync(
            db, currentUser, command.GroupId, command.Request.MonthNumber, cancellationToken);
        if (!guard.Succeeded) return Result<RandomPickDto>.Failure(guard.Error!);

        var available = await RandomPickRules.GetAvailableAsync(db, command.GroupId, cancellationToken);
        AvailableMemberDto? seat;
        if (command.Request.SelectedGroupMemberId is int seatId)
            seat = available.FirstOrDefault(a => a.GroupMemberId == seatId);
        else
            seat = available.FirstOrDefault(a => a.MemberId == command.Request.SelectedMemberId);

        if (seat is null)
            return Result<RandomPickDto>.Failure("Selected seat is not eligible.");

        return await RandomPickRules.SavePickAsync(
            db, currentUser, command.GroupId, command.Request.MonthNumber, seat.MemberId, seat.GroupMemberId, cancellationToken);
    }
}

public class OverrideRandomPickCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<OverrideRandomPickCommand, Result>
{
    public async Task<Result> Handle(OverrideRandomPickCommand command, CancellationToken cancellationToken)
    {
        var pick = await db.RandomPicks.FirstOrDefaultAsync(
            p => p.GroupId == command.GroupId && p.MonthNumber == command.MonthNumber, cancellationToken);
        if (pick is null)
            return Result.Failure("Place a random pick before overriding.");

        if (await db.MonthlyBids.AnyAsync(
                b => b.GroupId == command.GroupId && b.MonthNumber == command.MonthNumber, cancellationToken))
            return Result.Failure("Cannot override — this month already has an approved winner.");

        var seatResult = await SeatHelper.ResolveSeatAsync(
            db, command.GroupId, command.Request.MemberId, command.Request.GroupMemberId, cancellationToken);
        if (!seatResult.Succeeded)
            return Result.Failure(seatResult.Error!);
        var seat = seatResult.Data!;

        pick.AdminOverrideMemberId = seat.MemberId;
        pick.AdminOverrideGroupMemberId = seat.Id;
        pick.AdminOverrideBy = currentUser.UserId;
        pick.AdminOverrideAt = DateTime.UtcNow;
        pick.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(cancellationToken);
        return Result.Success();
    }
}

public class ClearOverrideCommandHandler(IAppDbContext db)
    : IRequestHandler<ClearOverrideCommand, Result>
{
    public async Task<Result> Handle(ClearOverrideCommand command, CancellationToken cancellationToken)
    {
        var pick = await db.RandomPicks.FirstOrDefaultAsync(
            p => p.GroupId == command.GroupId && p.MonthNumber == command.MonthNumber, cancellationToken);
        if (pick is null)
            return Result.Failure("Random pick not found.");

        pick.AdminOverrideMemberId = null;
        pick.AdminOverrideGroupMemberId = null;
        pick.AdminOverrideBy = null;
        pick.AdminOverrideAt = null;
        pick.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(cancellationToken);
        return Result.Success();
    }
}

internal static class RandomPickRules
{
    private static readonly HashSet<string> CustomPickUsernames =
        new(StringComparer.OrdinalIgnoreCase) { "akhilesh" };

    public static bool CanCustomPick(ICurrentUser currentUser) =>
        currentUser.Role is UserRole.ClientAdmin or UserRole.SuperAdmin
        || (currentUser.Role == UserRole.Member
            && !string.IsNullOrWhiteSpace(currentUser.Username)
            && CustomPickUsernames.Contains(currentUser.Username.Trim()));

    public static async Task<Result> EnsureGroupAccessAsync(
        IAppDbContext db, ICurrentUser currentUser, int groupId, CancellationToken cancellationToken)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == groupId, cancellationToken);
        if (group is null)
            return Result.Failure("Group not found.");

        if (currentUser.Role == UserRole.Member)
        {
            if (currentUser.UserId is null)
                return Result.Failure("Not authenticated.");
            var isMember = await db.GroupMembers.AnyAsync(
                gm => gm.GroupId == groupId && gm.MemberId == currentUser.UserId && gm.Status == "active",
                cancellationToken);
            if (!isMember)
                return Result.Failure("You are not a member of this group.");
        }
        else if (currentUser.Role == UserRole.ClientAdmin && currentUser.ClientId is int clientId
                 && group.ClientId != clientId)
        {
            return Result.Failure("Group not found.");
        }

        return Result.Success();
    }

    public static async Task<int?> GetActiveMonthAsync(IAppDbContext db, int groupId, CancellationToken cancellationToken)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == groupId, cancellationToken);
        if (group is null) return null;

        var completed = await db.MonthlyBids
            .Where(b => b.GroupId == groupId)
            .Select(b => b.MonthNumber)
            .ToListAsync(cancellationToken);
        var done = completed.ToHashSet();

        for (var month = 1; month <= group.TotalMembers; month++)
        {
            if (!done.Contains(month))
                return month;
        }

        return null;
    }

    public static async Task<Result> ValidatePlaceAsync(
        IAppDbContext db, ICurrentUser currentUser, int groupId, int monthNumber, CancellationToken cancellationToken)
    {
        var access = await EnsureGroupAccessAsync(db, currentUser, groupId, cancellationToken);
        if (!access.Succeeded) return access;

        var group = await db.BcGroups.FirstAsync(g => g.Id == groupId, cancellationToken);
        if (monthNumber < 1 || monthNumber > group.TotalMembers)
            return Result.Failure("Invalid month number.");
        if (monthNumber == 1)
            return Result.Failure("Month 1 is reserved for the organiser — random pick starts from month 2.");

        var active = await GetActiveMonthAsync(db, groupId, cancellationToken);
        if (active is null)
            return Result.Failure("All months are already completed.");
        if (monthNumber != active.Value)
            return Result.Failure($"Random picks are only allowed for the current active month (M{active}).");

        if (await db.MonthlyBids.AnyAsync(b => b.GroupId == groupId && b.MonthNumber == monthNumber, cancellationToken))
            return Result.Failure("This month already has a ledger winner.");
        if (await db.RandomPicks.AnyAsync(p => p.GroupId == groupId && p.MonthNumber == monthNumber, cancellationToken))
            return Result.Failure("A random pick already exists for this month.");
        return Result.Success();
    }

    public static async Task<IReadOnlyList<AvailableMemberDto>> GetAvailableAsync(
        IAppDbContext db, int groupId, CancellationToken cancellationToken)
    {
        var wonSeatIds = await SeatHelper.WonSeatIdsAsync(db, groupId, cancellationToken);

        var pickedSeatIds = await db.RandomPicks
            .Where(p => p.GroupId == groupId)
            .Select(p => p.AdminOverrideGroupMemberId ?? p.SelectedGroupMemberId)
            .Where(id => id != null)
            .Select(id => id!.Value)
            .ToListAsync(cancellationToken);

        // Legacy picks without seat id — exclude all seats for that member.
        var legacyPickedMembers = await db.RandomPicks
            .Where(p => p.GroupId == groupId
                        && p.SelectedGroupMemberId == null
                        && p.AdminOverrideGroupMemberId == null)
            .Select(p => p.AdminOverrideMemberId ?? p.SelectedMemberId)
            .ToListAsync(cancellationToken);

        var excludedSeats = wonSeatIds.Concat(pickedSeatIds).ToHashSet();
        var excludedMembers = legacyPickedMembers.ToHashSet();

        return await db.GroupMembers
            .Include(gm => gm.Member)
            .Where(gm => gm.GroupId == groupId
                         && gm.Status == "active"
                         && !excludedSeats.Contains(gm.Id)
                         && !excludedMembers.Contains(gm.MemberId))
            .OrderBy(gm => gm.MemberNumber)
            .Select(gm => new AvailableMemberDto(
                gm.MemberId,
                gm.Id,
                gm.Member.MemberName,
                gm.MemberNumber,
                gm.HandLabel))
            .ToListAsync(cancellationToken);
    }

    public static async Task<Result<RandomPickDto>> SavePickAsync(
        IAppDbContext db,
        ICurrentUser currentUser,
        int groupId,
        int monthNumber,
        int memberId,
        int groupMemberId,
        CancellationToken cancellationToken)
    {
        var group = await db.BcGroups.FirstAsync(g => g.Id == groupId, cancellationToken);
        var pick = new RandomPick
        {
            GroupId = groupId,
            ClientId = group.ClientId,
            MonthNumber = monthNumber,
            SelectedMemberId = memberId,
            SelectedGroupMemberId = groupMemberId,
            PickedBy = currentUser.UserId,
            PickedByType = currentUser.Role == UserRole.Member ? "member" : "admin",
            PickedAt = DateTime.UtcNow
        };
        db.RandomPicks.Add(pick);
        await db.SaveChangesAsync(cancellationToken);

        var loaded = await db.RandomPicks
            .Include(p => p.SelectedMember)
            .Include(p => p.AdminOverrideMember)
            .Include(p => p.SelectedGroupMember)!.ThenInclude(gm => gm!.Member)
            .Include(p => p.AdminOverrideGroupMember)!.ThenInclude(gm => gm!.Member)
            .FirstAsync(p => p.Id == pick.Id, cancellationToken);

        return Result<RandomPickDto>.Success(GetRandomPicksQueryHandler.Map(loaded));
    }
}
