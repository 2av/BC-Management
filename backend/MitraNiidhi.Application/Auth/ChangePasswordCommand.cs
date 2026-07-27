using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Application.Notifications;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Auth;

public record ChangePasswordRequest(string CurrentPassword, string NewPassword);

public record ChangePasswordCommand(ChangePasswordRequest Request) : IRequest<Result>;

public class ChangePasswordCommandHandler(
    IAppDbContext db,
    ICurrentUser currentUser,
    IPasswordHasher passwordHasher) : IRequestHandler<ChangePasswordCommand, Result>
{
    public async Task<Result> Handle(ChangePasswordCommand command, CancellationToken cancellationToken)
    {
        if (currentUser.UserId is null || currentUser.Role is null)
            return Result.Failure("Not authenticated.");

        var req = command.Request;
        if (string.IsNullOrWhiteSpace(req.NewPassword) || req.NewPassword.Length < 6)
            return Result.Failure("New password must be at least 6 characters.");

        return currentUser.Role switch
        {
            UserRole.Member => await ChangeMember(req, cancellationToken),
            UserRole.ClientAdmin => await ChangeClientAdmin(req, cancellationToken),
            UserRole.SuperAdmin => await ChangeSuperAdmin(req, cancellationToken),
            _ => Result.Failure("Unsupported role.")
        };
    }

    private async Task<Result> ChangeMember(ChangePasswordRequest req, CancellationToken ct)
    {
        var user = await db.Members.FirstOrDefaultAsync(m => m.Id == currentUser.UserId, ct);
        if (user?.PasswordHash is null) return Result.Failure("Account not found.");
        if (!passwordHasher.Verify(req.CurrentPassword, user.PasswordHash))
            return Result.Failure("Current password is incorrect.");
        user.PasswordHash = passwordHasher.Hash(req.NewPassword);
        user.MustChangePassword = false;
        NotificationWriter.Add(
            db, "member", user.Id,
            "Password changed",
            "Your password was updated successfully.",
            "success");
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }

    private async Task<Result> ChangeClientAdmin(ChangePasswordRequest req, CancellationToken ct)
    {
        var ca = await db.ClientAdmins.FirstOrDefaultAsync(a => a.Id == currentUser.UserId, ct);
        if (ca is not null)
        {
            if (!passwordHasher.Verify(req.CurrentPassword, ca.PasswordHash))
                return Result.Failure("Current password is incorrect.");
            ca.PasswordHash = passwordHasher.Hash(req.NewPassword);
            NotificationWriter.Add(
                db, "admin", ca.Id,
                "Password changed",
                "Your password was updated successfully.",
                "success");
            await db.SaveChangesAsync(ct);
            return Result.Success();
        }

        var legacy = await db.AdminUsers.FirstOrDefaultAsync(a => a.Id == currentUser.UserId, ct);
        if (legacy is null) return Result.Failure("Account not found.");
        if (!passwordHasher.Verify(req.CurrentPassword, legacy.PasswordHash))
            return Result.Failure("Current password is incorrect.");
        legacy.PasswordHash = passwordHasher.Hash(req.NewPassword);
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }

    private async Task<Result> ChangeSuperAdmin(ChangePasswordRequest req, CancellationToken ct)
    {
        var user = await db.SuperAdmins.FirstOrDefaultAsync(a => a.Id == currentUser.UserId, ct);
        if (user is null) return Result.Failure("Account not found.");
        if (!passwordHasher.Verify(req.CurrentPassword, user.PasswordHash))
            return Result.Failure("Current password is incorrect.");
        user.PasswordHash = passwordHasher.Hash(req.NewPassword);
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}
