using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Entities;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Auth;

public record LoginCommand(LoginRequest Request) : IRequest<Result<AuthUserDto>>;

public class LoginCommandHandler(
    IAppDbContext db,
    IPasswordHasher passwordHasher,
    IJwtTokenService jwt) : IRequestHandler<LoginCommand, Result<AuthUserDto>>
{
    public async Task<Result<AuthUserDto>> Handle(LoginCommand command, CancellationToken cancellationToken)
    {
        var req = command.Request;
        if (string.IsNullOrWhiteSpace(req.Username) || string.IsNullOrWhiteSpace(req.Password))
            return Result<AuthUserDto>.Failure("Username or mobile number and password are required.");

        var result = req.Portal switch
        {
            UserRole.SuperAdmin => await LoginSuperAdminAsync(req, cancellationToken),
            UserRole.ClientAdmin => await LoginClientAdminAsync(req, cancellationToken),
            UserRole.Member => await LoginMemberAsync(req, cancellationToken),
            _ => Result<AuthUserDto>.Failure("Invalid portal.")
        };

        if (result is { Succeeded: true, Data: { } user })
        {
            db.AuditLogs.Add(new AuditLog
            {
                ClientId = user.ClientId,
                UserType = user.Role switch
                {
                    UserRole.SuperAdmin => "super_admin",
                    UserRole.ClientAdmin => "client_admin",
                    _ => "member"
                },
                UserId = user.Id,
                Action = "login",
                TableName = "auth",
                CreatedAt = DateTime.UtcNow
            });
            try { await db.SaveChangesAsync(cancellationToken); }
            catch { /* audit must not block login if table missing mid-migrate */ }
        }

        return result;
    }

    private async Task<Result<AuthUserDto>> LoginSuperAdminAsync(LoginRequest req, CancellationToken ct)
    {
        var login = req.Username.Trim();
        var admin = await db.SuperAdmins
            .FirstOrDefaultAsync(x => x.Username == login && x.Status == "active", ct);

        if (admin is null && PhoneNormalizer.LooksLikePhoneLogin(login))
        {
            var last10 = PhoneNormalizer.Last10Digits(login)!;
            var candidates = await db.SuperAdmins
                .Where(x => x.Status == "active" && x.Phone != null && x.Phone != "")
                .ToListAsync(ct);
            admin = candidates.FirstOrDefault(x => PhoneNormalizer.Matches(x.Phone, last10));
        }

        if (admin is null || !passwordHasher.Verify(req.Password, admin.PasswordHash))
            return Result<AuthUserDto>.Failure("Invalid username/mobile or password.");

        var token = jwt.CreateAccessToken(admin.Id, admin.Username, admin.FullName, UserRole.SuperAdmin, null);
        return Result<AuthUserDto>.Success(new AuthUserDto(admin.Id, admin.Username, admin.FullName, UserRole.SuperAdmin, null, token));
    }

    private async Task<Result<AuthUserDto>> LoginClientAdminAsync(LoginRequest req, CancellationToken ct)
    {
        var login = req.Username.Trim();

        // Project only needed columns so login works even if optional subscription columns are missing.
        var admin = await db.ClientAdmins
            .Where(x => x.Username == login && x.Status == "active")
            .Select(x => new
            {
                x.Id,
                x.Username,
                x.FullName,
                x.PasswordHash,
                x.ClientId,
                ClientStatus = x.Client.Status
            })
            .FirstOrDefaultAsync(ct);

        if (admin is null && PhoneNormalizer.LooksLikePhoneLogin(login))
        {
            var last10 = PhoneNormalizer.Last10Digits(login)!;
            var candidates = await db.ClientAdmins
                .Where(x => x.Status == "active" && x.Phone != null && x.Phone != "")
                .Select(x => new
                {
                    x.Id,
                    x.Username,
                    x.FullName,
                    x.PasswordHash,
                    x.ClientId,
                    x.Phone,
                    ClientStatus = x.Client.Status
                })
                .ToListAsync(ct);
            var match = candidates.FirstOrDefault(x => PhoneNormalizer.Matches(x.Phone, last10));
            if (match is not null)
            {
                admin = new
                {
                    match.Id,
                    match.Username,
                    match.FullName,
                    match.PasswordHash,
                    match.ClientId,
                    match.ClientStatus
                };
            }
        }

        if (admin is not null)
        {
            if (!passwordHasher.Verify(req.Password, admin.PasswordHash))
                return Result<AuthUserDto>.Failure("Invalid username/mobile or password.");

            if (admin.ClientStatus is "suspended" or "inactive")
                return Result<AuthUserDto>.Failure("Client account is not active.");

            var token = jwt.CreateAccessToken(admin.Id, admin.Username, admin.FullName, UserRole.ClientAdmin, admin.ClientId);
            return Result<AuthUserDto>.Success(new AuthUserDto(admin.Id, admin.Username, admin.FullName, UserRole.ClientAdmin, admin.ClientId, token));
        }

        // Legacy admin_users fallback (matches PHP login)
        var legacy = await db.AdminUsers
            .FirstOrDefaultAsync(x => x.Username == login, ct);

        if (legacy is null || !passwordHasher.Verify(req.Password, legacy.PasswordHash))
            return Result<AuthUserDto>.Failure("Invalid username/mobile or password.");

        var legacyToken = jwt.CreateAccessToken(legacy.Id, legacy.Username, legacy.FullName, UserRole.ClientAdmin, 1);
        return Result<AuthUserDto>.Success(new AuthUserDto(legacy.Id, legacy.Username, legacy.FullName, UserRole.ClientAdmin, 1, legacyToken));
    }

    private async Task<Result<AuthUserDto>> LoginMemberAsync(LoginRequest req, CancellationToken ct)
    {
        var login = req.Username.Trim();
        var member = await db.Members
            .FirstOrDefaultAsync(x => x.Username == login && x.Status == "active", ct);

        if (member is null && PhoneNormalizer.LooksLikePhoneLogin(login))
        {
            var last10 = PhoneNormalizer.Last10Digits(login)!;
            var candidates = await db.Members
                .Where(x => x.Status == "active" && x.Phone != null && x.Phone != "")
                .ToListAsync(ct);
            member = candidates.FirstOrDefault(x => PhoneNormalizer.Matches(x.Phone, last10));
        }

        if (member is null || string.IsNullOrEmpty(member.PasswordHash)
            || !passwordHasher.Verify(req.Password, member.PasswordHash))
            return Result<AuthUserDto>.Failure("Invalid username/mobile or password.");

        if (string.IsNullOrWhiteSpace(member.Username))
            return Result<AuthUserDto>.Failure("Member login is not set up for this account.");

        var clientId = await db.GroupMembers
            .Where(gm => gm.MemberId == member.Id && gm.Status == "active")
            .Join(db.BcGroups, gm => gm.GroupId, g => g.Id, (gm, g) => g.ClientId)
            .FirstOrDefaultAsync(ct);

        var token = jwt.CreateAccessToken(member.Id, member.Username, member.MemberName, UserRole.Member, clientId);
        return Result<AuthUserDto>.Success(new AuthUserDto(
            member.Id, member.Username, member.MemberName, UserRole.Member, clientId, token, member.MustChangePassword));
    }
}
