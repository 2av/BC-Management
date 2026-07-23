namespace MitraNiidhi.Domain.Entities;

public class SubscriptionPlan
{
    public int Id { get; set; }
    public string PlanName { get; set; } = string.Empty;
    public int DurationMonths { get; set; }
    public decimal Price { get; set; }
    public string Currency { get; set; } = "INR";
    public string? Description { get; set; }
    public string? FeaturesJson { get; set; }
    public bool IsActive { get; set; } = true;
    public bool IsPromotional { get; set; }
    public decimal PromotionalDiscount { get; set; }
    public int? MaxGroups { get; set; }
    public int? MaxMembersPerGroup { get; set; }
    public int? CreatedBy { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime? UpdatedAt { get; set; }
}
