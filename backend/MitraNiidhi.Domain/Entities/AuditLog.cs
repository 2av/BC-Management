namespace MitraNiidhi.Domain.Entities;

public class AuditLog
{
    public int Id { get; set; }
    public int? ClientId { get; set; }
    public string UserType { get; set; } = string.Empty;
    public int UserId { get; set; }
    public string Action { get; set; } = string.Empty;
    public string? TableName { get; set; }
    public int? RecordId { get; set; }
    public string? OldValues { get; set; }
    public string? NewValues { get; set; }
    public string? IpAddress { get; set; }
    public string? UserAgent { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
}
