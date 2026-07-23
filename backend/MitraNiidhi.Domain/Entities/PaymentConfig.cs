namespace MitraNiidhi.Domain.Entities;

public class PaymentConfig
{
    public int Id { get; set; }
    public int ClientId { get; set; }
    public string ConfigKey { get; set; } = string.Empty;
    public string? ConfigValue { get; set; }
    public string? Description { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime? UpdatedAt { get; set; }
}
