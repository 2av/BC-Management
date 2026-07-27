namespace MitraNiidhi.Application.Common.Interfaces;

public interface IPushNotificationService
{
    Task SendToMemberAsync(
        int memberId,
        string title,
        string body,
        string? type = null,
        CancellationToken cancellationToken = default);

    Task SendToMembersAsync(
        IEnumerable<int> memberIds,
        string title,
        string body,
        string? type = null,
        CancellationToken cancellationToken = default);
}
