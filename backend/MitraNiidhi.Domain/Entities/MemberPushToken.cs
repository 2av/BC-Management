namespace MitraNiidhi.Domain.Entities;

public class MemberPushToken
{
    public int Id { get; set; }
    public int MemberId { get; set; }
    public string Token { get; set; } = string.Empty;
    public string Platform { get; set; } = "unknown";
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;
}
