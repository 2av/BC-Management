namespace MitraNiidhi.Domain.Entities;

public class Client
{
    public int Id { get; set; }
    public string ClientName { get; set; } = string.Empty;
    public string? CompanyName { get; set; }
    public string ContactPerson { get; set; } = string.Empty;
    public string Email { get; set; } = string.Empty;
    public string Phone { get; set; } = string.Empty;
    public string? Address { get; set; }
    public string? City { get; set; }
    public string? State { get; set; }
    public string? Country { get; set; }
    public string? Pincode { get; set; }
    public string Status { get; set; } = "active";
    public string SubscriptionPlan { get; set; } = "basic";
    public string? SubscriptionStatus { get; set; }
    public DateOnly? SubscriptionEndDate { get; set; }
    public int? CurrentSubscriptionId { get; set; }
    public int? MaxGroups { get; set; }
    public int? MaxMembersPerGroup { get; set; }
    public int CreatedBy { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime? UpdatedAt { get; set; }

    public ICollection<ClientAdmin> Admins { get; set; } = new List<ClientAdmin>();
    public ICollection<BcGroup> Groups { get; set; } = new List<BcGroup>();
}
