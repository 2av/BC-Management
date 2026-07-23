namespace MitraNiidhi.Domain.Entities;

public class SubscriptionPayment
{
    public int Id { get; set; }
    public int ClientId { get; set; }
    public int SubscriptionId { get; set; }
    public decimal Amount { get; set; }
    public string Currency { get; set; } = "INR";
    public string? PaymentMethod { get; set; }
    public string? PaymentReference { get; set; }
    public string PaymentStatus { get; set; } = "pending";
    public string? PaymentGateway { get; set; }
    public string? GatewayTransactionId { get; set; }
    public DateTime? PaymentDate { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime? UpdatedAt { get; set; }

    public Client Client { get; set; } = null!;
    public ClientSubscription Subscription { get; set; } = null!;
}
