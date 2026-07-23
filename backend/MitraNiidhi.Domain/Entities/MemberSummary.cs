using MitraNiidhi.Domain.Common;

namespace MitraNiidhi.Domain.Entities;

public class MemberSummary : IClientScoped
{
    public int Id { get; set; }
    public int? ClientId { get; set; }
    public int GroupId { get; set; }
    public int MemberId { get; set; }
    public int? GroupMemberId { get; set; }
    public decimal TotalPaid { get; set; }
    public decimal GivenAmount { get; set; }
    public decimal Profit { get; set; }

    public BcGroup Group { get; set; } = null!;
    public Member Member { get; set; } = null!;
    public GroupMember? GroupMember { get; set; }
}
