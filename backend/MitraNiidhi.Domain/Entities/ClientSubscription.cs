namespace MitraNiidhi.Domain.Entities;

public class ClientSubscription
{
    public int Id { get; set; }
    public int ClientId { get; set; }
    public int PlanId { get; set; }
    public string PlanSnapshotJson { get; set; } = "{}";
    public DateOnly StartDate { get; set; }
    public DateOnly EndDate { get; set; }
    public string Status { get; set; } = "active";
    public decimal PaymentAmount { get; set; }
    public string? PaymentMethod { get; set; }
    public string? PaymentReference { get; set; }
    public DateTime? PaymentDate { get; set; }
    public bool AutoRenewal { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime? UpdatedAt { get; set; }

    public Client Client { get; set; } = null!;
    public SubscriptionPlan Plan { get; set; } = null!;
}
