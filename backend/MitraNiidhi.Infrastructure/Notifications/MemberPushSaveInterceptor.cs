using System.Collections.Concurrent;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Diagnostics;
using Microsoft.Extensions.DependencyInjection;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Domain.Entities;

namespace MitraNiidhi.Infrastructure.Notifications;

/// <summary>
/// After member in-app notifications are saved, send Expo push to their registered devices.
/// </summary>
public sealed class MemberPushSaveInterceptor(IServiceScopeFactory scopeFactory) : SaveChangesInterceptor
{
    private readonly ConcurrentDictionary<DbContextId, List<PendingPush>> _pending = new();

    private sealed record PendingPush(int MemberId, string Title, string Message, string Type);

    public override InterceptionResult<int> SavingChanges(DbContextEventData eventData, InterceptionResult<int> result)
    {
        Capture(eventData.Context);
        return result;
    }

    public override ValueTask<InterceptionResult<int>> SavingChangesAsync(
        DbContextEventData eventData,
        InterceptionResult<int> result,
        CancellationToken cancellationToken = default)
    {
        Capture(eventData.Context);
        return new ValueTask<InterceptionResult<int>>(result);
    }

    public override int SavedChanges(SaveChangesCompletedEventData eventData, int result)
    {
        // Fire-and-forget sync path; prefer async SaveChanges in app code.
        _ = FlushAsync(eventData.Context, CancellationToken.None);
        return result;
    }

    public override async ValueTask<int> SavedChangesAsync(
        SaveChangesCompletedEventData eventData,
        int result,
        CancellationToken cancellationToken = default)
    {
        await FlushAsync(eventData.Context, cancellationToken);
        return result;
    }

    private void Capture(DbContext? context)
    {
        if (context is null) return;

        var list = context.ChangeTracker.Entries<Notification>()
            .Where(e => e.State == EntityState.Added
                        && e.Entity.UserId is > 0
                        && string.Equals(e.Entity.UserType, "member", StringComparison.OrdinalIgnoreCase))
            .Select(e => new PendingPush(
                e.Entity.UserId!.Value,
                e.Entity.Title,
                e.Entity.Message,
                string.IsNullOrWhiteSpace(e.Entity.Type) ? "info" : e.Entity.Type!))
            .ToList();

        if (list.Count == 0) return;
        _pending[context.ContextId] = list;
    }

    private async Task FlushAsync(DbContext? context, CancellationToken cancellationToken)
    {
        if (context is null) return;
        if (!_pending.TryRemove(context.ContextId, out var list) || list.Count == 0) return;

        try
        {
            await using var scope = scopeFactory.CreateAsyncScope();
            var push = scope.ServiceProvider.GetRequiredService<IPushNotificationService>();
            foreach (var item in list)
            {
                await push.SendToMemberAsync(item.MemberId, item.Title, item.Message, item.Type, cancellationToken);
            }
        }
        catch
        {
            // Never fail the DB transaction path because push failed.
        }
    }
}
