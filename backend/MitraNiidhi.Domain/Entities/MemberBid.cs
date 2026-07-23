using MitraNiidhi.Domain.Common;

namespace MitraNiidhi.Domain.Entities;

public class MemberBid : IClientScoped
{
    public int Id { get; set; }
    public int? ClientId { get; set; }
    public int GroupId { get; set; }
    public int MemberId { get; set; }
    public int? GroupMemberId { get; set; }
    public int MonthNumber { get; set; }
    public decimal BidAmount { get; set; }
    public string BidStatus { get; set; } = "pending";
    public DateTime BidDate { get; set; } = DateTime.UtcNow;
    public string? AdminNotes { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    public BcGroup Group { get; set; } = null!;
    public Member Member { get; set; } = null!;
    public GroupMember? GroupMember { get; set; }
}
