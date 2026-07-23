using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Entities;

namespace MitraNiidhi.Application.Common;

internal static class SeatHelper
{
    public static string FormatDisplayName(string memberName, string? handLabel, int memberNumber) =>
        string.IsNullOrWhiteSpace(handLabel)
            ? $"{memberName} (#{memberNumber})"
            : $"{memberName} · {handLabel} (#{memberNumber})";

    public static async Task<Result<GroupMember>> ResolveSeatAsync(
        IAppDbContext db,
        int groupId,
        int memberId,
        int? groupMemberId,
        CancellationToken cancellationToken,
        bool requireActive = true)
    {
        if (groupMemberId is int seatId)
        {
            var seat = await db.GroupMembers
                .Include(gm => gm.Member)
                .FirstOrDefaultAsync(gm => gm.Id == seatId && gm.GroupId == groupId, cancellationToken);
            if (seat is null)
                return Result<GroupMember>.Failure("Seat not found in this group.");
            if (seat.MemberId != memberId)
                return Result<GroupMember>.Failure("Seat does not belong to this member.");
            if (requireActive && seat.Status != "active")
                return Result<GroupMember>.Failure("Seat is not active.");
            return Result<GroupMember>.Success(seat);
        }

        var seats = await db.GroupMembers
            .Include(gm => gm.Member)
            .Where(gm => gm.GroupId == groupId && gm.MemberId == memberId
                         && (!requireActive || gm.Status == "active"))
            .OrderBy(gm => gm.MemberNumber)
            .ToListAsync(cancellationToken);

        if (seats.Count == 0)
            return Result<GroupMember>.Failure("No active seat found for this member in the group.");
        if (seats.Count > 1)
            return Result<GroupMember>.Failure("Multiple hands found — select which seat (groupMemberId) to use.");
        return Result<GroupMember>.Success(seats[0]);
    }

    public static async Task<bool> SeatHasWonAsync(
        IAppDbContext db, int groupId, int groupMemberId, CancellationToken cancellationToken)
    {
        if (await db.MonthlyBids.AnyAsync(
                b => b.GroupId == groupId && b.TakenByGroupMemberId == groupMemberId, cancellationToken))
            return true;

        var seat = await db.GroupMembers.FirstOrDefaultAsync(
            gm => gm.Id == groupMemberId && gm.GroupId == groupId, cancellationToken);
        if (seat is null) return false;

        // Legacy wins keyed only by member_id (pre multi-hand).
        return await db.MonthlyBids.AnyAsync(
            b => b.GroupId == groupId
                 && b.TakenByGroupMemberId == null
                 && b.TakenByMemberId == seat.MemberId,
            cancellationToken);
    }

    public static async Task<HashSet<int>> WonSeatIdsAsync(
        IAppDbContext db, int groupId, CancellationToken cancellationToken)
    {
        var wins = await db.MonthlyBids
            .Where(b => b.GroupId == groupId)
            .Select(b => new { b.TakenByGroupMemberId, b.TakenByMemberId })
            .ToListAsync(cancellationToken);

        var seatIds = wins
            .Where(w => w.TakenByGroupMemberId.HasValue)
            .Select(w => w.TakenByGroupMemberId!.Value)
            .ToHashSet();

        var legacyMemberIds = wins
            .Where(w => !w.TakenByGroupMemberId.HasValue && w.TakenByMemberId.HasValue)
            .Select(w => w.TakenByMemberId!.Value)
            .Distinct()
            .ToList();

        if (legacyMemberIds.Count > 0)
        {
            var legacySeats = await db.GroupMembers
                .Where(gm => gm.GroupId == groupId && legacyMemberIds.Contains(gm.MemberId))
                .Select(gm => gm.Id)
                .ToListAsync(cancellationToken);
            foreach (var id in legacySeats)
                seatIds.Add(id);
        }

        return seatIds;
    }
}
