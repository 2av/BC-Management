namespace MitraNiidhi.Application.Groups;

public record GroupListItemDto(
    int Id,
    string GroupName,
    int TotalMembers,
    decimal MonthlyContribution,
    decimal TotalMonthlyCollection,
    DateOnly StartDate,
    string Status,
    int CompletedMonths,
    decimal PendingAmount);

public record DashboardStatsDto(
    int TotalGroups,
    int ActiveGroups,
    int CompletedGroups,
    int TotalMembers,
    decimal TotalCollected,
    decimal TotalDistributed,
    decimal CashInHand,
    decimal ThisMonthCollected,
    IReadOnlyList<GroupListItemDto> RecentGroups);

public record MonthlyBidDto(
    int MonthNumber,
    string? TakenByMemberName,
    int? TakenByMemberId,
    int? TakenByGroupMemberId,
    bool IsBid,
    decimal BidAmount,
    decimal NetPayable,
    decimal GainPerMember,
    DateOnly? PaymentDate);

public record MemberLedgerRowDto(
    int GroupMemberId,
    int MemberId,
    int MemberNumber,
    string MemberName,
    string? HandLabel,
    IReadOnlyDictionary<int, decimal?> PaymentsByMonth,
    decimal TotalPaid,
    decimal GivenAmount,
    decimal Profit);

public record GroupLedgerDto(
    int Id,
    string GroupName,
    int TotalMembers,
    decimal MonthlyContribution,
    decimal TotalMonthlyCollection,
    DateOnly StartDate,
    string Status,
    IReadOnlyList<MonthlyBidDto> MonthlyBids,
    IReadOnlyList<MemberLedgerRowDto> Members);
