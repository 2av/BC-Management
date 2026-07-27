namespace MitraNiidhi.Domain.Entities;

public class Member
{
    public int Id { get; set; }
    public string MemberName { get; set; } = string.Empty;
    public string? Username { get; set; }
    public string? PasswordHash { get; set; }
    public string? Phone { get; set; }
    public string? Email { get; set; }
    public string? Address { get; set; }
    public string Status { get; set; } = "active";
    public bool MustChangePassword { get; set; }
    public int? HasWonMonth { get; set; }
    public decimal WonAmount { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    public ICollection<GroupMember> GroupMemberships { get; set; } = new List<GroupMember>();
}
