using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Auth;

public record AdminProfileDto(
    int Id,
    string Username,
    string FullName,
    string? Email,
    string? Phone,
    string Role,
    int? ClientId);

public record UpdateAdminProfileRequest(string FullName, string? Email, string? Phone);

public record GetAdminProfileQuery : IRequest<Result<AdminProfileDto>>;
public record UpdateAdminProfileCommand(UpdateAdminProfileRequest Request) : IRequest<Result<AdminProfileDto>>;

public class GetAdminProfileQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetAdminProfileQuery, Result<AdminProfileDto>>
{
    public async Task<Result<AdminProfileDto>> Handle(GetAdminProfileQuery request, CancellationToken ct)
    {
        if (currentUser.UserId is null || currentUser.Role is null)
            return Result<AdminProfileDto>.Failure("Not authenticated.");

        return currentUser.Role switch
        {
            UserRole.ClientAdmin => await GetClientAdmin(ct),
            UserRole.SuperAdmin => await GetSuperAdmin(ct),
            _ => Result<AdminProfileDto>.Failure("Profile not available for this role.")
        };
    }

    private async Task<Result<AdminProfileDto>> GetClientAdmin(CancellationToken ct)
    {
        var ca = await db.ClientAdmins.FirstOrDefaultAsync(a => a.Id == currentUser.UserId, ct);
        if (ca is not null)
            return Result<AdminProfileDto>.Success(new AdminProfileDto(
                ca.Id, ca.Username, ca.FullName, ca.Email, ca.Phone, "ClientAdmin", ca.ClientId));

        var legacy = await db.AdminUsers.FirstOrDefaultAsync(a => a.Id == currentUser.UserId, ct);
        if (legacy is null) return Result<AdminProfileDto>.Failure("Account not found.");
        return Result<AdminProfileDto>.Success(new AdminProfileDto(
            legacy.Id, legacy.Username, legacy.FullName, null, null, "ClientAdmin", 1));
    }

    private async Task<Result<AdminProfileDto>> GetSuperAdmin(CancellationToken ct)
    {
        var sa = await db.SuperAdmins.FirstOrDefaultAsync(a => a.Id == currentUser.UserId, ct);
        if (sa is null) return Result<AdminProfileDto>.Failure("Account not found.");
        return Result<AdminProfileDto>.Success(new AdminProfileDto(
            sa.Id, sa.Username, sa.FullName, sa.Email, sa.Phone, "SuperAdmin", null));
    }
}

public class UpdateAdminProfileCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<UpdateAdminProfileCommand, Result<AdminProfileDto>>
{
    public async Task<Result<AdminProfileDto>> Handle(UpdateAdminProfileCommand command, CancellationToken ct)
    {
        if (currentUser.UserId is null || currentUser.Role is null)
            return Result<AdminProfileDto>.Failure("Not authenticated.");

        var req = command.Request;
        if (string.IsNullOrWhiteSpace(req.FullName))
            return Result<AdminProfileDto>.Failure("Full name is required.");

        return currentUser.Role switch
        {
            UserRole.ClientAdmin => await UpdateClientAdmin(req, ct),
            UserRole.SuperAdmin => await UpdateSuperAdmin(req, ct),
            _ => Result<AdminProfileDto>.Failure("Profile not available for this role.")
        };
    }

    private async Task<Result<AdminProfileDto>> UpdateClientAdmin(UpdateAdminProfileRequest req, CancellationToken ct)
    {
        var ca = await db.ClientAdmins.FirstOrDefaultAsync(a => a.Id == currentUser.UserId, ct);
        if (ca is not null)
        {
            if (!string.IsNullOrWhiteSpace(req.Email))
            {
                var emailTaken = await db.ClientAdmins.AnyAsync(
                    a => a.Email == req.Email && a.Id != ca.Id, ct);
                if (emailTaken) return Result<AdminProfileDto>.Failure("Email is already in use.");
            }

            ca.FullName = req.FullName.Trim();
            ca.Email = string.IsNullOrWhiteSpace(req.Email) ? null : req.Email.Trim();
            ca.Phone = string.IsNullOrWhiteSpace(req.Phone) ? null : req.Phone.Trim();
            await db.SaveChangesAsync(ct);
            return Result<AdminProfileDto>.Success(new AdminProfileDto(
                ca.Id, ca.Username, ca.FullName, ca.Email, ca.Phone, "ClientAdmin", ca.ClientId));
        }

        var legacy = await db.AdminUsers.FirstOrDefaultAsync(a => a.Id == currentUser.UserId, ct);
        if (legacy is null) return Result<AdminProfileDto>.Failure("Account not found.");
        legacy.FullName = req.FullName.Trim();
        await db.SaveChangesAsync(ct);
        return Result<AdminProfileDto>.Success(new AdminProfileDto(
            legacy.Id, legacy.Username, legacy.FullName, null, null, "ClientAdmin", 1));
    }

    private async Task<Result<AdminProfileDto>> UpdateSuperAdmin(UpdateAdminProfileRequest req, CancellationToken ct)
    {
        var sa = await db.SuperAdmins.FirstOrDefaultAsync(a => a.Id == currentUser.UserId, ct);
        if (sa is null) return Result<AdminProfileDto>.Failure("Account not found.");
        sa.FullName = req.FullName.Trim();
        if (!string.IsNullOrWhiteSpace(req.Email))
            sa.Email = req.Email.Trim();
        if (!string.IsNullOrWhiteSpace(req.Phone))
            sa.Phone = req.Phone.Trim();
        await db.SaveChangesAsync(ct);
        return Result<AdminProfileDto>.Success(new AdminProfileDto(
            sa.Id, sa.Username, sa.FullName, sa.Email, sa.Phone, "SuperAdmin", null));
    }
}
