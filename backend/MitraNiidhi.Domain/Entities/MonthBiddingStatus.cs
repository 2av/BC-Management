using MitraNiidhi.Domain.Common;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Domain.Entities;

public class MonthBiddingStatus : IClientScoped
{
    public int Id { get; set; }
    public int? ClientId { get; set; }
    public int GroupId { get; set; }
    public int MonthNumber { get; set; }
    public BiddingStatus BiddingStatus { get; set; } = BiddingStatus.NotStarted;
    public DateOnly? BiddingStartDate { get; set; }
    public DateOnly? BiddingEndDate { get; set; }
    public decimal MinimumBidAmount { get; set; }
    public decimal MaximumBidAmount { get; set; }
    public int? WinnerMemberId { get; set; }
    public int? WinnerGroupMemberId { get; set; }
    public decimal? WinningBidAmount { get; set; }
    public int? AdminApprovedBy { get; set; }
    public DateTime? AdminApprovedAt { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    public BcGroup Group { get; set; } = null!;
    public Member? WinnerMember { get; set; }
    public GroupMember? WinnerGroupMember { get; set; }
}
