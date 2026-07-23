namespace MitraNiidhi.Domain.Entities;

public class GroupMember
{
    public int Id { get; set; }
    public int GroupId { get; set; }
    public int MemberId { get; set; }
    public int MemberNumber { get; set; }
    /// <summary>Optional label e.g. "Hand 1", "Hand 2".</summary>
    public string? HandLabel { get; set; }
    public string Status { get; set; } = "active";
    public DateTime JoinedDate { get; set; } = DateTime.UtcNow;

    public BcGroup Group { get; set; } = null!;
    public Member Member { get; set; } = null!;

    public string DisplayName =>
        string.IsNullOrWhiteSpace(HandLabel)
            ? Member.MemberName
            : $"{Member.MemberName} · {HandLabel}";
}
