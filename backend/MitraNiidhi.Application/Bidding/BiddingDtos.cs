using MitraNiidhi.Domain.Enums;

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
    int TotalBids);

public record BidItemDto(
    int Id,
    int MemberId,
    int? GroupMemberId,
    string MemberName,
    int MemberNumber,
    string? HandLabel,
    decimal BidAmount,
    string BidStatus,
    DateTime BidDate);

public record GroupBiddingOverviewDto(
    int GroupId,
    string GroupName,
    int TotalMembers,
    decimal MonthlyContribution,
    decimal TotalMonthlyCollection,
    IReadOnlyList<MonthBiddingDto> Months);

public record OpenBiddingRequest(int MonthNumber, DateOnly EndDate, decimal MinBidAmount, decimal MaxBidAmount);
public record PlaceBidRequest(int MonthNumber, decimal BidAmount, int? GroupMemberId = null);
public record ApproveWinnerRequest(int MonthNumber, int WinnerMemberId, decimal WinningBidAmount, int? WinnerGroupMemberId = null);
