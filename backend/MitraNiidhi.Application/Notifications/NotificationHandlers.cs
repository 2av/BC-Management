using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Entities;

namespace MitraNiidhi.Application.Notifications;

public record NotificationDto(
    int Id,
    string Title,
    string Message,
    string Type,
    bool IsRead,
    DateTime? ReadAt,
    DateTime CreatedAt);

public record NotificationCountsDto(int Total, int Unread, int Warning, int Danger);

public record GetNotificationsQuery(string? Filter, int Page = 1, int PageSize = 10)
    : IRequest<Result<IReadOnlyList<NotificationDto>>>;

public record GetNotificationCountsQuery : IRequest<Result<NotificationCountsDto>>;
public record MarkNotificationReadCommand(int Id) : IRequest<Result>;
public record MarkAllNotificationsReadCommand : IRequest<Result>;
public record DeleteNotificationCommand(int Id) : IRequest<Result>;

public class GetNotificationsQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetNotificationsQuery, Result<IReadOnlyList<NotificationDto>>>
{
    public async Task<Result<IReadOnlyList<NotificationDto>>> Handle(GetNotificationsQuery request, CancellationToken ct)
    {
        if (currentUser.Role is Domain.Enums.UserRole.SuperAdmin or Domain.Enums.UserRole.ClientAdmin)
            await NotificationWriter.EnsureSubscriptionExpiryAsync(db, ct);

        var q = Scoped(db, currentUser);

        q = request.Filter switch
        {
            "unread" => q.Where(n => !n.IsRead),
            "read" => q.Where(n => n.IsRead),
            "warning" => q.Where(n => n.Type == "warning"),
            "danger" => q.Where(n => n.Type == "danger"),
            _ => q
        };

        var list = await q.OrderByDescending(n => n.CreatedAt)
            .Skip((request.Page - 1) * request.PageSize)
            .Take(request.PageSize)
            .ToListAsync(ct);

        return Result<IReadOnlyList<NotificationDto>>.Success(list.Select(Map).ToList());
    }

    internal static NotificationDto Map(Notification n) =>
        new(n.Id, n.Title, n.Message, n.Type, n.IsRead, n.ReadAt, n.CreatedAt);

    internal static IQueryable<Notification> Scoped(IAppDbContext db, ICurrentUser currentUser)
    {
        var userType = currentUser.Role == Domain.Enums.UserRole.Member ? "member" : "admin";
        var userId = currentUser.UserId;
        return db.Notifications.Where(n =>
            n.UserType == userType &&
            (n.UserId == null || n.UserId == userId));
    }
}

public class GetNotificationCountsQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetNotificationCountsQuery, Result<NotificationCountsDto>>
{
    public async Task<Result<NotificationCountsDto>> Handle(GetNotificationCountsQuery request, CancellationToken ct)
    {
        if (currentUser.Role is Domain.Enums.UserRole.SuperAdmin or Domain.Enums.UserRole.ClientAdmin)
            await NotificationWriter.EnsureSubscriptionExpiryAsync(db, ct);

        var q = GetNotificationsQueryHandler.Scoped(db, currentUser);
        return Result<NotificationCountsDto>.Success(new NotificationCountsDto(
            await q.CountAsync(ct),
            await q.CountAsync(n => !n.IsRead, ct),
            await q.CountAsync(n => n.Type == "warning", ct),
            await q.CountAsync(n => n.Type == "danger", ct)));
    }
}

public class MarkNotificationReadCommandHandler(IAppDbContext db)
    : IRequestHandler<MarkNotificationReadCommand, Result>
{
    public async Task<Result> Handle(MarkNotificationReadCommand command, CancellationToken ct)
    {
        var n = await db.Notifications.FirstOrDefaultAsync(x => x.Id == command.Id, ct);
        if (n is null) return Result.Failure("Notification not found.");
        n.IsRead = true;
        n.ReadAt = DateTime.UtcNow;
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}

public class MarkAllNotificationsReadCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<MarkAllNotificationsReadCommand, Result>
{
    public async Task<Result> Handle(MarkAllNotificationsReadCommand command, CancellationToken ct)
    {
        var unread = await GetNotificationsQueryHandler.Scoped(db, currentUser)
            .Where(n => !n.IsRead)
            .ToListAsync(ct);
        foreach (var n in unread)
        {
            n.IsRead = true;
            n.ReadAt = DateTime.UtcNow;
        }
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}

public class DeleteNotificationCommandHandler(IAppDbContext db)
    : IRequestHandler<DeleteNotificationCommand, Result>
{
    public async Task<Result> Handle(DeleteNotificationCommand command, CancellationToken ct)
    {
        var n = await db.Notifications.FirstOrDefaultAsync(x => x.Id == command.Id, ct);
        if (n is null) return Result.Failure("Notification not found.");
        db.Notifications.Remove(n);
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}
