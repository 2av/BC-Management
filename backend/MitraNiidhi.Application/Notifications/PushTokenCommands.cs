using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Entities;

namespace MitraNiidhi.Application.Notifications;

public record RegisterPushTokenRequest(string Token, string? Platform);

public record RegisterPushTokenCommand(RegisterPushTokenRequest Request) : IRequest<Result>;

public record UnregisterPushTokenCommand(string Token) : IRequest<Result>;

public class RegisterPushTokenCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<RegisterPushTokenCommand, Result>
{
    public async Task<Result> Handle(RegisterPushTokenCommand command, CancellationToken ct)
    {
        if (currentUser.UserId is null || currentUser.Role != Domain.Enums.UserRole.Member)
            return Result.Failure("Not authenticated as member.");

        var token = command.Request.Token?.Trim() ?? "";
        if (string.IsNullOrWhiteSpace(token) || token.Length < 20)
            return Result.Failure("Invalid push token.");

        var platform = string.IsNullOrWhiteSpace(command.Request.Platform)
            ? "unknown"
            : command.Request.Platform.Trim().ToLowerInvariant();
        if (platform.Length > 20) platform = platform[..20];

        var memberId = currentUser.UserId.Value;
        var existing = await db.MemberPushTokens.FirstOrDefaultAsync(t => t.Token == token, ct);
        if (existing is not null)
        {
            existing.MemberId = memberId;
            existing.Platform = platform;
            existing.UpdatedAt = DateTime.UtcNow;
        }
        else
        {
            db.MemberPushTokens.Add(new MemberPushToken
            {
                MemberId = memberId,
                Token = token,
                Platform = platform,
                CreatedAt = DateTime.UtcNow,
                UpdatedAt = DateTime.UtcNow,
            });
        }

        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}

public class UnregisterPushTokenCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<UnregisterPushTokenCommand, Result>
{
    public async Task<Result> Handle(UnregisterPushTokenCommand command, CancellationToken ct)
    {
        if (currentUser.UserId is null) return Result.Failure("Not authenticated.");
        var token = command.Token?.Trim() ?? "";
        var row = await db.MemberPushTokens.FirstOrDefaultAsync(
            t => t.Token == token && t.MemberId == currentUser.UserId, ct);
        if (row is null) return Result.Success();
        db.MemberPushTokens.Remove(row);
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}
