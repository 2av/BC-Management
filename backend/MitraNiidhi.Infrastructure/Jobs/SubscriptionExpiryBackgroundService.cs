using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Notifications;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;

namespace MitraNiidhi.Infrastructure.Jobs;

/// <summary>
/// Periodically creates subscription-expiry notifications (replaces PHP cron).
/// </summary>
public class SubscriptionExpiryBackgroundService(
    IServiceScopeFactory scopeFactory,
    ILogger<SubscriptionExpiryBackgroundService> logger) : BackgroundService
{
    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        // Short delay so startup migration finishes first.
        await Task.Delay(TimeSpan.FromSeconds(15), stoppingToken);

        while (!stoppingToken.IsCancellationRequested)
        {
            try
            {
                using var scope = scopeFactory.CreateScope();
                var db = scope.ServiceProvider.GetRequiredService<IAppDbContext>();
                await NotificationWriter.EnsureSubscriptionExpiryAsync(db, stoppingToken);
                logger.LogInformation("Subscription expiry notification check completed.");
            }
            catch (Exception ex) when (ex is not OperationCanceledException)
            {
                logger.LogError(ex, "Subscription expiry job failed.");
            }

            await Task.Delay(TimeSpan.FromHours(6), stoppingToken);
        }
    }
}
