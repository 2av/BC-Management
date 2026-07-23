using MitraNiidhi.Domain.Common;

namespace MitraNiidhi.Domain.Entities;

public class RandomPick : IClientScoped
{
    public int Id { get; set; }
    public int? ClientId { get; set; }
    public int GroupId { get; set; }
    public int MonthNumber { get; set; }
    public int SelectedMemberId { get; set; }
    public int? SelectedGroupMemberId { get; set; }
    public int? AdminOverrideMemberId { get; set; }
    public int? AdminOverrideGroupMemberId { get; set; }
    public int? AdminOverrideBy { get; set; }
    public DateTime? AdminOverrideAt { get; set; }
    public int? PickedBy { get; set; }
    public string PickedByType { get; set; } = "admin";
    public DateTime PickedAt { get; set; } = DateTime.UtcNow;
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    public BcGroup Group { get; set; } = null!;
    public Member SelectedMember { get; set; } = null!;
    public GroupMember? SelectedGroupMember { get; set; }
    public Member? AdminOverrideMember { get; set; }
    public GroupMember? AdminOverrideGroupMember { get; set; }

    public int EffectiveMemberId => AdminOverrideMemberId ?? SelectedMemberId;
    public int? EffectiveGroupMemberId => AdminOverrideGroupMemberId ?? SelectedGroupMemberId;
}
