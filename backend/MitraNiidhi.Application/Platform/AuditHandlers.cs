using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Entities;

namespace MitraNiidhi.Application.Platform;

public record AuditLogDto(
    int Id,
    int? ClientId,
    string? ClientName,
    string UserType,
    int UserId,
    string Action,
    string? TableName,
    int? RecordId,
    string? IpAddress,
    DateTime CreatedAt);

public record GetAuditLogsQuery(int Page = 1, int PageSize = 50) : IRequest<Result<IReadOnlyList<AuditLogDto>>>;

public record WriteAuditLogCommand(
    string Action,
    string? TableName = null,
    int? RecordId = null,
    int? ClientId = null,
    string? OldValues = null,
    string? NewValues = null,
    string? IpAddress = null,
    string? UserAgent = null) : IRequest<Result>;

public static class AuditWriter
{
    public static void Add(
        IAppDbContext db,
        ICurrentUser currentUser,
        string action,
        string? tableName = null,
        int? recordId = null,
        int? clientId = null,
        string? oldValues = null,
        string? newValues = null,
        string? ipAddress = null,
        string? userAgent = null)
    {
        var userType = currentUser.Role switch
        {
            Domain.Enums.UserRole.SuperAdmin => "super_admin",
            Domain.Enums.UserRole.ClientAdmin => "client_admin",
            Domain.Enums.UserRole.Member => "member",
            _ => "unknown"
        };

        db.AuditLogs.Add(new AuditLog
        {
            ClientId = clientId ?? currentUser.ClientId,
            UserType = userType,
            UserId = currentUser.UserId ?? 0,
            Action = action,
            TableName = tableName,
            RecordId = recordId,
            OldValues = oldValues,
            NewValues = newValues,
            IpAddress = ipAddress,
            UserAgent = userAgent,
            CreatedAt = DateTime.UtcNow
        });
    }
}

public class GetAuditLogsQueryHandler(IAppDbContext db)
    : IRequestHandler<GetAuditLogsQuery, Result<IReadOnlyList<AuditLogDto>>>
{
    public async Task<Result<IReadOnlyList<AuditLogDto>>> Handle(GetAuditLogsQuery request, CancellationToken ct)
    {
        var page = Math.Max(1, request.Page);
        var size = Math.Clamp(request.PageSize, 1, 200);

        var rows = await db.AuditLogs
            .OrderByDescending(a => a.CreatedAt)
            .Skip((page - 1) * size)
            .Take(size)
            .ToListAsync(ct);

        var clientIds = rows.Where(r => r.ClientId.HasValue).Select(r => r.ClientId!.Value).Distinct().ToList();
        var names = await db.Clients
            .Where(c => clientIds.Contains(c.Id))
            .ToDictionaryAsync(c => c.Id, c => c.ClientName, ct);

        var list = rows.Select(a => new AuditLogDto(
            a.Id,
            a.ClientId,
            a.ClientId is int cid && names.TryGetValue(cid, out var n) ? n : null,
            a.UserType,
            a.UserId,
            a.Action,
            a.TableName,
            a.RecordId,
            a.IpAddress,
            a.CreatedAt)).ToList();

        return Result<IReadOnlyList<AuditLogDto>>.Success(list);
    }
}

public class WriteAuditLogCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<WriteAuditLogCommand, Result>
{
    public async Task<Result> Handle(WriteAuditLogCommand command, CancellationToken ct)
    {
        AuditWriter.Add(
            db, currentUser, command.Action, command.TableName, command.RecordId,
            command.ClientId, command.OldValues, command.NewValues, command.IpAddress, command.UserAgent);
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}
