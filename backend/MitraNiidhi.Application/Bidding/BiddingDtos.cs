namespace MitraNiidhi.Application.Bidding;

public record MonthBiddingDto(
    int MonthNumber,
    string BiddingStatus,
    DateOnly? BiddingStartDate,
    DateOnly? BiddingEndDate,
    decimal MinimumBidAmount,
    decimal MaximumBidAmount,
    int? WinnerMemberId,
    int? WinnerGroupMemberId,
    string? WinnerMemberName,
    decimal? WinningBidAmount,
    int TotalBids,
    decimal? RandomAmount = null,
    decimal? BoliStartAmount = null,
    decimal? NextBoliAmount = null,
    decimal? CurrentBestBoliAmount = null,
    /// <summary>True when every active seat has Paid for this month (no pending left).</summary>
    bool PaymentDone = false);

public record BidItemDto(
    int Id,
    int MemberId,
    int? GroupMemberId,
    string MemberName,
    int MemberNumber,
    string? HandLabel,
    decimal BidAmount,
    string BidStatus,
    DateTime BidDate,
    decimal? BoliAmount = null);

public record GroupBiddingOverviewDto(
    int GroupId,
    string GroupName,
    int TotalMembers,
    decimal MonthlyContribution,
    decimal TotalMonthlyCollection,
    IReadOnlyList<MonthBiddingDto> Months,
    int? OrganiserMemberId = null,
    int? OrganiserGroupMemberId = null,
    string? OrganiserName = null,
    bool Month1Allocated = false,
    decimal BoliStepAmount = 1000);

/// <summary>Open a month for bidding. Min/max removed — amounts come from the group BC chart.</summary>
public record OpenBiddingRequest(int MonthNumber, DateOnly EndDate);

/// <summary>BoliAmount = amount the winner would receive (chart style). Stored internally as discount.</summary>
public record PlaceBidRequest(int MonthNumber, decimal BoliAmount, int? GroupMemberId = null);

public record ApproveWinnerRequest(
    int MonthNumber,
    int WinnerMemberId,
    decimal WinningBidAmount,
    int? WinnerGroupMemberId = null,
    decimal? PaymentAmount = null,
    /// <summary>If true (or WinningBidAmount is 0 with chart random), use chart random receive.</summary>
    bool UseRandomAmount = false,
    /// <summary>Winner receive amount (chart boli). Converted to discount when set.</summary>
    decimal? BoliAmount = null);
