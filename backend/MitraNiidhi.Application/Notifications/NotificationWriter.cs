using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Domain.Entities;

namespace MitraNiidhi.Application.Notifications;

public static class NotificationWriter
{
    public static void Add(
        IAppDbContext db,
        string userType,
        int? userId,
        string title,
        string message,
        string type = "info")
    {
        db.Notifications.Add(new Notification
        {
            UserType = userType,
            UserId = userId,
            Title = title,
            Message = message,
            Type = type,
            CreatedAt = DateTime.UtcNow
        });
    }

    public static async Task EnsureSubscriptionExpiryAsync(IAppDbContext db, CancellationToken ct)
    {
        var soon = DateOnly.FromDateTime(DateTime.UtcNow.AddDays(30));
        var today = DateOnly.FromDateTime(DateTime.UtcNow);
        var clients = await db.Clients
            .Where(c => c.Status == "active" && c.SubscriptionEndDate != null && c.SubscriptionEndDate <= soon)
            .ToListAsync(ct);

        foreach (var client in clients)
        {
            var end = client.SubscriptionEndDate!.Value;
            var days = end.DayNumber - today.DayNumber;
            var window = days <= 7 ? "7d" : "30d";
            var title = days < 0
                ? $"Subscription expired — {client.ClientName}"
                : $"Subscription expiring — {client.ClientName}";
            var key = $"sub-expiry:{client.Id}:{window}:{end:yyyy-MM-dd}";

            var exists = await db.Notifications.AnyAsync(
                n => n.UserType == "admin" && n.UserId == null && n.Message.Contains(key), ct);
            if (exists) continue;

            var type = days < 0 || days <= 7 ? "danger" : "warning";
            var message = days < 0
                ? $"{client.ClientName} subscription ended on {end:dd MMM yyyy}. [{key}]"
                : $"{client.ClientName} subscription ends on {end:dd MMM yyyy} ({days} day(s) left). [{key}]";

            Add(db, "admin", null, title, message, type);

            var admins = await db.ClientAdmins
                .Where(a => a.ClientId == client.Id && a.Status == "active")
                .Select(a => a.Id)
                .ToListAsync(ct);
            foreach (var adminId in admins)
            {
                var adminKey = $"{key}:ca{adminId}";
                var adminExists = await db.Notifications.AnyAsync(
                    n => n.UserType == "admin" && n.UserId == adminId && n.Message.Contains(key), ct);
                if (adminExists) continue;
                Add(db, "admin", adminId, title,
                    days < 0
                        ? $"Your organisation subscription ended on {end:dd MMM yyyy}. [{adminKey}]"
                        : $"Your organisation subscription ends on {end:dd MMM yyyy}. [{adminKey}]",
                    type);
            }
        }

        await db.SaveChangesAsync(ct);
    }
}
