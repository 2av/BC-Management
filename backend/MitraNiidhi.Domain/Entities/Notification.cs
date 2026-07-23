namespace MitraNiidhi.Domain.Entities;

public class Notification
{
    public int Id { get; set; }
    public string UserType { get; set; } = "admin";
    public int? UserId { get; set; }
    public string Title { get; set; } = string.Empty;
    public string Message { get; set; } = string.Empty;
    public string Type { get; set; } = "info";
    public bool IsRead { get; set; }
    public DateTime? ReadAt { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
}
