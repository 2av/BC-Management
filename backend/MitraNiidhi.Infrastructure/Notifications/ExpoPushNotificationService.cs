using System.Net.Http.Json;
using System.Text.Json.Serialization;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging;
using MitraNiidhi.Application.Common.Interfaces;

namespace MitraNiidhi.Infrastructure.Notifications;

public class ExpoPushNotificationService(
    IAppDbContext db,
    IHttpClientFactory httpClientFactory,
    ILogger<ExpoPushNotificationService> logger) : IPushNotificationService
{
    private const string ExpoPushUrl = "https://exp.host/--/api/v2/push/send";

    public Task SendToMemberAsync(
        int memberId,
        string title,
        string body,
        string? type = null,
        CancellationToken cancellationToken = default)
        => SendToMembersAsync([memberId], title, body, type, cancellationToken);

    public async Task SendToMembersAsync(
        IEnumerable<int> memberIds,
        string title,
        string body,
        string? type = null,
        CancellationToken cancellationToken = default)
    {
        var ids = memberIds.Distinct().ToList();
        if (ids.Count == 0) return;

        var tokens = await db.MemberPushTokens
            .Where(t => ids.Contains(t.MemberId))
            .Select(t => t.Token)
            .Distinct()
            .ToListAsync(cancellationToken);

        if (tokens.Count == 0) return;

        var messages = tokens.Select(token => new ExpoPushMessage(
            token,
            title,
            body,
            new Dictionary<string, string>
            {
                ["type"] = type ?? "info",
                ["screen"] = "Alerts",
            },
            "default",
            "high")).ToList();

        try
        {
            var client = httpClientFactory.CreateClient("ExpoPush");
            // Expo accepts batches up to ~100
            foreach (var batch in messages.Chunk(100))
            {
                using var response = await client.PostAsJsonAsync(ExpoPushUrl, batch, cancellationToken);
                if (!response.IsSuccessStatusCode)
                {
                    var err = await response.Content.ReadAsStringAsync(cancellationToken);
                    logger.LogWarning("Expo push failed ({Status}): {Body}", (int)response.StatusCode, err);
                }
            }
        }
        catch (Exception ex)
        {
            logger.LogWarning(ex, "Expo push send failed");
        }
    }

    private sealed record ExpoPushMessage(
        [property: JsonPropertyName("to")] string To,
        [property: JsonPropertyName("title")] string Title,
        [property: JsonPropertyName("body")] string Body,
        [property: JsonPropertyName("data")] Dictionary<string, string> Data,
        [property: JsonPropertyName("sound")] string Sound,
        [property: JsonPropertyName("priority")] string Priority,
        [property: JsonPropertyName("channelId")] string ChannelId = "default");
}
