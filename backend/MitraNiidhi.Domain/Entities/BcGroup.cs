using MitraNiidhi.Domain.Common;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Domain.Entities;

public class BcGroup : IClientScoped
{
    public int Id { get; set; }
    public int? ClientId { get; set; }
    public string GroupName { get; set; } = string.Empty;
    public int TotalMembers { get; set; }
    public decimal MonthlyContribution { get; set; }
    public decimal TotalMonthlyCollection { get; set; }
    public DateOnly StartDate { get; set; }
    public GroupStatus Status { get; set; } = GroupStatus.Active;
    /// <summary>Member who receives Month 1 pot (no bid). Optional until set.</summary>
    public int? OrganiserMemberId { get; set; }
    /// <summary>Specific seat when organiser has multiple hands.</summary>
    public int? OrganiserGroupMemberId { get; set; }
    /// <summary>Each next boli (receive amount) must be this much lower than the current best.</summary>
    public decimal BoliStepAmount { get; set; } = 1000;
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    public Client? Client { get; set; }
    public Member? OrganiserMember { get; set; }
    public GroupMember? OrganiserGroupMember { get; set; }
    public ICollection<GroupMember> Members { get; set; } = new List<GroupMember>();
    public ICollection<MonthlyBid> MonthlyBids { get; set; } = new List<MonthlyBid>();
    public ICollection<GroupMonthChart> MonthCharts { get; set; } = new List<GroupMonthChart>();
}
