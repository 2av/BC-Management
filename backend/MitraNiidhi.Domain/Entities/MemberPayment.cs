using MitraNiidhi.Domain.Common;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Domain.Entities;

public class MemberPayment : IClientScoped
{
    public int Id { get; set; }
    public int? ClientId { get; set; }
    public int GroupId { get; set; }
    public int MemberId { get; set; }
    public int? GroupMemberId { get; set; }
    public int MonthNumber { get; set; }
    public decimal PaymentAmount { get; set; }
    public PaymentStatus PaymentStatus { get; set; } = PaymentStatus.Pending;
    public DateOnly? PaymentDate { get; set; }
    public string PaymentMethod { get; set; } = "upi";
    public string? TransactionId { get; set; }
    public string? Notes { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    public BcGroup Group { get; set; } = null!;
    public Member Member { get; set; } = null!;
    public GroupMember? GroupMember { get; set; }
}
