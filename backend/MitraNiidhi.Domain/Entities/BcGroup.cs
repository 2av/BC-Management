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
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    public Client? Client { get; set; }
    public ICollection<GroupMember> Members { get; set; } = new List<GroupMember>();
    public ICollection<MonthlyBid> MonthlyBids { get; set; } = new List<MonthlyBid>();
}
