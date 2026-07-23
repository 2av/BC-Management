using MitraNiidhi.Domain.Common;

namespace MitraNiidhi.Domain.Entities;

public class MonthlyBid : IClientScoped
{
    public int Id { get; set; }
    public int? ClientId { get; set; }
    public int GroupId { get; set; }
    public int MonthNumber { get; set; }
    public int? TakenByMemberId { get; set; }
    public int? TakenByGroupMemberId { get; set; }
    public bool IsBid { get; set; }
    public decimal BidAmount { get; set; }
    public decimal NetPayable { get; set; }
    public decimal GainPerMember { get; set; }
    public DateOnly? PaymentDate { get; set; }

    public BcGroup Group { get; set; } = null!;
    public Member? TakenByMember { get; set; }
    public GroupMember? TakenByGroupMember { get; set; }
}
