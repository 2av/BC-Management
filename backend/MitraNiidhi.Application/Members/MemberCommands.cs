using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Application.Notifications;
using MitraNiidhi.Domain.Entities;
using MitraNiidhi.Domain.Services;

namespace MitraNiidhi.Application.Members;

public record CreateMemberCommand(CreateMemberRequest Request) : IRequest<Result<MemberListItemDto>>;

public class CreateMemberCommandHandler(IAppDbContext db, IPasswordHasher passwordHasher)
    : IRequestHandler<CreateMemberCommand, Result<MemberListItemDto>>
{
    public async Task<Result<MemberListItemDto>> Handle(CreateMemberCommand command, CancellationToken cancellationToken)
    {
        var req = command.Request;
        if (string.IsNullOrWhiteSpace(req.MemberName))
            return Result<MemberListItemDto>.Failure("Member name is required.");

        var username = string.IsNullOrWhiteSpace(req.Username)
            ? await MemberUsernameHelper.EnsureUniqueUsernameAsync(db, req.MemberName, null, cancellationToken)
            : req.Username.Trim().ToLowerInvariant();

        if (await db.Members.AnyAsync(m => m.Username == username, cancellationToken))
            return Result<MemberListItemDto>.Failure($"Username '{username}' is already taken.");

        var password = string.IsNullOrWhiteSpace(req.Password) ? "member123" : req.Password.Trim();
        if (password.Length < 6)
            return Result<MemberListItemDto>.Failure("Password must be at least 6 characters.");

        var member = new Member
        {
            MemberName = req.MemberName.Trim(),
            Username = username,
            PasswordHash = passwordHasher.Hash(password),
            Phone = string.IsNullOrWhiteSpace(req.Phone) ? null : req.Phone.Trim(),
            Email = string.IsNullOrWhiteSpace(req.Email) ? null : req.Email.Trim(),
            Address = string.IsNullOrWhiteSpace(req.Address) ? null : req.Address.Trim(),
            Status = "active",
            MustChangePassword = true
        };
        db.Members.Add(member);
        await db.SaveChangesAsync(cancellationToken);

        NotificationWriter.Add(
            db, "member", member.Id,
            "Account created",
            "Your Mitra Niidhi account is ready. Sign in and set a new password when prompted.",
            "info");
        await db.SaveChangesAsync(cancellationToken);

        var groups = new List<MemberGroupBriefDto>();
        if (req.GroupId is int groupId)
        {
            var assign = await AssignInternal.AssignAsync(
                db, groupId, member.Id, req.MemberNumber, cancellationToken);
            if (!assign.Succeeded)
                return Result<MemberListItemDto>.Failure(assign.Error!);

            var gm = await db.GroupMembers.Include(x => x.Group)
                .Where(x => x.GroupId == groupId && x.MemberId == member.Id)
                .OrderBy(x => x.MemberNumber)
                .FirstAsync(cancellationToken);
            groups.Add(new MemberGroupBriefDto(
                gm.Id, gm.GroupId, gm.Group.GroupName, gm.MemberNumber, gm.HandLabel, gm.Status, gm.JoinedDate));
        }

        return Result<MemberListItemDto>.Success(new MemberListItemDto(
            member.Id, member.MemberName, member.Username, member.Phone, member.Email, member.Address,
            member.Status, groups.Count, groups));
    }
}

public record UpdateMemberCommand(int MemberId, UpdateMemberRequest Request) : IRequest<Result>;

public class UpdateMemberCommandHandler(IAppDbContext db, IPasswordHasher passwordHasher)
    : IRequestHandler<UpdateMemberCommand, Result>
{
    public async Task<Result> Handle(UpdateMemberCommand command, CancellationToken cancellationToken)
    {
        var member = await db.Members.FirstOrDefaultAsync(m => m.Id == command.MemberId, cancellationToken);
        if (member is null)
            return Result.Failure("Member not found.");

        var req = command.Request;
        if (string.IsNullOrWhiteSpace(req.MemberName))
            return Result.Failure("Member name is required.");

        var status = req.Status.Trim().ToLowerInvariant();
        if (status is not ("active" or "inactive"))
            return Result.Failure("Status must be active or inactive.");

        string? username = member.Username;
        if (!string.IsNullOrWhiteSpace(req.Username))
        {
            username = req.Username.Trim().ToLowerInvariant();
            if (await db.Members.AnyAsync(m => m.Username == username && m.Id != member.Id, cancellationToken))
                return Result.Failure($"Username '{username}' is already taken.");
        }

        var profileChanged =
            member.MemberName != req.MemberName.Trim()
            || member.Username != username
            || (member.Phone ?? "") != (string.IsNullOrWhiteSpace(req.Phone) ? "" : req.Phone.Trim())
            || (member.Email ?? "") != (string.IsNullOrWhiteSpace(req.Email) ? "" : req.Email.Trim())
            || (member.Address ?? "") != (string.IsNullOrWhiteSpace(req.Address) ? "" : req.Address.Trim())
            || member.Status != status;

        member.MemberName = req.MemberName.Trim();
        member.Username = username;
        member.Phone = string.IsNullOrWhiteSpace(req.Phone) ? null : req.Phone.Trim();
        member.Email = string.IsNullOrWhiteSpace(req.Email) ? null : req.Email.Trim();
        member.Address = string.IsNullOrWhiteSpace(req.Address) ? null : req.Address.Trim();
        member.Status = status;

        var passwordReset = false;
        if (!string.IsNullOrWhiteSpace(req.NewPassword))
        {
            var pwd = req.NewPassword.Trim();
            if (pwd.Length < 6)
                return Result.Failure("New password must be at least 6 characters.");
            member.PasswordHash = passwordHasher.Hash(pwd);
            member.MustChangePassword = true;
            passwordReset = true;
        }

        if (passwordReset)
        {
            NotificationWriter.Add(
                db, "member", member.Id,
                "Password reset by admin",
                "An admin reset your password. Sign in with the temporary password and set a new one.",
                "warning");
        }
        else if (profileChanged)
        {
            NotificationWriter.Add(
                db, "member", member.Id,
                "Profile updated",
                "An admin updated your account details. Please review your profile.",
                "info");
        }

        await db.SaveChangesAsync(cancellationToken);
        return Result.Success();
    }
}

public record AssignMemberCommand(int GroupId, AssignMemberRequest Request) : IRequest<Result>;

public class AssignMemberCommandHandler(IAppDbContext db, IPasswordHasher passwordHasher)
    : IRequestHandler<AssignMemberCommand, Result>
{
    public async Task<Result> Handle(AssignMemberCommand command, CancellationToken cancellationToken)
    {
        var req = command.Request;
        int memberId;

        if (req.MemberId is int existingId)
        {
            if (!await db.Members.AnyAsync(m => m.Id == existingId, cancellationToken))
                return Result.Failure("Member not found.");
            memberId = existingId;
        }
        else
        {
            if (string.IsNullOrWhiteSpace(req.MemberName))
                return Result.Failure("Member name is required when creating a new member.");

            var username = string.IsNullOrWhiteSpace(req.Username)
                ? await MemberUsernameHelper.EnsureUniqueUsernameAsync(db, req.MemberName, null, cancellationToken)
                : req.Username.Trim().ToLowerInvariant();

            if (await db.Members.AnyAsync(m => m.Username == username, cancellationToken))
                return Result.Failure($"Username '{username}' is already taken.");

            var member = new Member
            {
                MemberName = req.MemberName.Trim(),
                Username = username,
                PasswordHash = passwordHasher.Hash(string.IsNullOrWhiteSpace(req.Password) ? "member123" : req.Password),
                Phone = string.IsNullOrWhiteSpace(req.Phone) ? null : req.Phone.Trim(),
                Email = string.IsNullOrWhiteSpace(req.Email) ? null : req.Email.Trim(),
                Address = string.IsNullOrWhiteSpace(req.Address) ? null : req.Address.Trim(),
                Status = "active",
                MustChangePassword = true
            };
            db.Members.Add(member);
            await db.SaveChangesAsync(cancellationToken);
            memberId = member.Id;
        }

        return await AssignInternal.AssignAsync(
            db, command.GroupId, memberId, req.MemberNumber, cancellationToken, req.AddHand);
    }
}

public record UnassignMemberCommand(int GroupId, int MemberId, int? GroupMemberId = null) : IRequest<Result>;

public class UnassignMemberCommandHandler(IAppDbContext db)
    : IRequestHandler<UnassignMemberCommand, Result>
{
    public async Task<Result> Handle(UnassignMemberCommand command, CancellationToken cancellationToken)
    {
        GroupMember? gm;
        if (command.GroupMemberId is int seatId)
        {
            gm = await db.GroupMembers.FirstOrDefaultAsync(
                x => x.Id == seatId && x.GroupId == command.GroupId, cancellationToken);
        }
        else
        {
            var seats = await db.GroupMembers
                .Where(x => x.GroupId == command.GroupId && x.MemberId == command.MemberId)
                .OrderBy(x => x.MemberNumber)
                .ToListAsync(cancellationToken);
            if (seats.Count > 1)
                return Result.Failure("Member has multiple hands — specify which seat to remove (groupMemberId).");
            gm = seats.FirstOrDefault();
        }

        if (gm is null)
            return Result.Failure("Assignment not found.");

        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, cancellationToken);
        if (group is null)
            return Result.Failure("Group not found.");
        if (group.Status == Domain.Enums.GroupStatus.Completed)
            return Result.Failure("This group is completed — seats cannot be removed.");

        var hasPayments = await db.MemberPayments.AnyAsync(
            p => p.GroupId == command.GroupId
                 && (p.GroupMemberId == gm.Id || (p.GroupMemberId == null && p.MemberId == gm.MemberId)),
            cancellationToken);
        var hasBids = await db.MemberBids.AnyAsync(
            b => b.GroupId == command.GroupId
                 && (b.GroupMemberId == gm.Id || (b.GroupMemberId == null && b.MemberId == gm.MemberId)),
            cancellationToken);
        var hasWin = await db.MonthlyBids.AnyAsync(
            b => b.GroupId == command.GroupId
                 && (b.TakenByGroupMemberId == gm.Id
                     || (b.TakenByGroupMemberId == null && b.TakenByMemberId == gm.MemberId)),
            cancellationToken);

        if (hasPayments || hasBids || hasWin)
        {
            gm.Status = "inactive";
            await db.SaveChangesAsync(cancellationToken);
            return Result.Success();
        }

        db.GroupMembers.Remove(gm);
        var summary = await db.MemberSummaries.FirstOrDefaultAsync(
            s => s.GroupId == command.GroupId
                 && (s.GroupMemberId == gm.Id || (s.GroupMemberId == null && s.MemberId == gm.MemberId)),
            cancellationToken);
        if (summary is not null)
            db.MemberSummaries.Remove(summary);

        await db.SaveChangesAsync(cancellationToken);
        return Result.Success();
    }
}

internal static class AssignInternal
{
    public static async Task<Result> AssignAsync(
        IAppDbContext db,
        int groupId,
        int memberId,
        int? memberNumber,
        CancellationToken cancellationToken,
        bool addHand = false)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == groupId, cancellationToken);
        if (group is null)
            return Result.Failure("Group not found.");

        if (group.Status == Domain.Enums.GroupStatus.Completed)
            return Result.Failure("This group is completed — roster cannot be changed.");

        var existingSeats = await db.GroupMembers
            .Where(gm => gm.GroupId == groupId && gm.MemberId == memberId)
            .OrderBy(gm => gm.MemberNumber)
            .ToListAsync(cancellationToken);

        if (!addHand && existingSeats.Count > 0)
        {
            var existing = existingSeats[0];
            if (existing.Status == "active")
                return Result.Failure("Member is already assigned to this group. Use Add hand to create another seat.");
            existing.Status = "active";
            await db.SaveChangesAsync(cancellationToken);
            await MemberUsernameHelper.EnsureSummaryAsync(db, group, memberId, cancellationToken, existing.Id);
            await db.SaveChangesAsync(cancellationToken);
            return Result.Success();
        }

        var usedNumbers = await db.GroupMembers
            .Where(gm => gm.GroupId == groupId)
            .Select(gm => gm.MemberNumber)
            .ToListAsync(cancellationToken);

        int number;
        if (memberNumber is int requested)
        {
            if (requested < 1)
                return Result.Failure("Member number must be at least 1.");
            if (usedNumbers.Contains(requested))
                return Result.Failure($"Member number {requested} is already taken in this group.");
            number = requested;
        }
        else
        {
            number = 1;
            while (usedNumbers.Contains(number)) number++;
        }

        if (number > group.TotalMembers)
        {
            group.TotalMembers = number;
            group.TotalMonthlyCollection = BcCalculationService.TotalMonthlyCollection(
                group.MonthlyContribution, group.TotalMembers);
        }

        var handIndex = existingSeats.Count + 1;
        string? handLabel = null;
        if (addHand || existingSeats.Count > 0)
        {
            handLabel = $"Hand {handIndex}";
            // Label the first seat if it was unlabeled.
            foreach (var (seat, i) in existingSeats.Select((s, i) => (s, i)))
            {
                if (string.IsNullOrWhiteSpace(seat.HandLabel))
                    seat.HandLabel = $"Hand {i + 1}";
            }
        }

        var newSeat = new GroupMember
        {
            GroupId = groupId,
            MemberId = memberId,
            MemberNumber = number,
            HandLabel = handLabel,
            Status = "active",
            JoinedDate = DateTime.UtcNow
        };
        db.GroupMembers.Add(newSeat);
        await db.SaveChangesAsync(cancellationToken);

        await MemberUsernameHelper.EnsureSummaryAsync(db, group, memberId, cancellationToken, newSeat.Id);
        await db.SaveChangesAsync(cancellationToken);
        return Result.Success();
    }
}
