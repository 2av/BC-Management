using MitraNiidhi.Domain.Common;

namespace MitraNiidhi.Domain.Entities;

/// <summary>
/// Month-wise BC chart row: random receive (no bid) and boli start receive (first bid).
/// </summary>
public class GroupMonthChart : IClientScoped
{
    public int Id { get; set; }
    public int? ClientId { get; set; }
    public int GroupId { get; set; }
    public int MonthNumber { get; set; }
    /// <summary>Amount winner receives if nobody bids (spin / random).</summary>
    public decimal RandomAmount { get; set; }
    /// <summary>Starting amount winner receives when bidding starts. Null = no boli that month.</summary>
    public decimal? BoliStartAmount { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    public BcGroup Group { get; set; } = null!;
}
