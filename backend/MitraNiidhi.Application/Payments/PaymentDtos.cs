using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Payments;

public record PaymentItemDto(
    int Id,
    int GroupId,
    string GroupName,
    int MemberId,
    int? GroupMemberId,
    string MemberName,
    int MemberNumber,
    string? HandLabel,
    int MonthNumber,
    decimal PaymentAmount,
    decimal ExpectedAmount,
    string PaymentStatus,
    DateOnly? PaymentDate,
    string? TransactionId,
    string? WinnerName,
    decimal? BidAmount,
    decimal? GainPerMember);

public record GroupPaymentsOverviewDto(
    int GroupId,
    string GroupName,
    decimal MonthlyContribution,
    decimal TotalMonthlyCollection,
    int PendingCount,
    decimal PendingAmount,
    int PaidCount,
    decimal PaidAmount,
    IReadOnlyList<PaymentItemDto> Payments,
    IReadOnlyList<MonthPaymentDueDto> MonthDues);

public record UpdatePaymentRequest(
    string PaymentStatus,
    decimal? PaymentAmount,
    DateOnly? PaymentDate,
    string? PaymentMethod,
    string? TransactionId,
    string? Notes);

public record BulkMarkPaidRequest(int MonthNumber, IReadOnlyList<int>? PaymentIds, DateOnly? PaymentDate);

public record CreatePaymentRequest(
    int? GroupMemberId = null,
    IReadOnlyList<int>? GroupMemberIds = null,
    int MonthNumber = 0,
    decimal PaymentAmount = 0,
    string PaymentStatus = "paid",
    DateOnly? PaymentDate = null,
    string? PaymentMethod = null,
    string? TransactionId = null,
    string? Notes = null);

public record SetMonthPaymentAmountRequest(int MonthNumber, decimal? PaymentAmount);

public record MonthPaymentDueDto(int MonthNumber, decimal? PaymentDueAmount, decimal EffectiveAmount);

public record MemberPaymentItemDto(
    int Id,
    int GroupId,
    string GroupName,
    int? GroupMemberId,
    int? MemberNumber,
    string? HandLabel,
    int MonthNumber,
    decimal PaymentAmount,
    string PaymentStatus,
    DateOnly? PaymentDate,
    string? WinnerName);

public record MemberPaymentsDto(
    decimal TotalPending,
    decimal TotalPaid,
    IReadOnlyList<MemberPaymentItemDto> Payments);

public static class PaymentStatusMapper
{
    public static string ToApi(PaymentStatus status) => status switch
    {
        PaymentStatus.Pending => "pending",
        PaymentStatus.Paid => "paid",
        PaymentStatus.Failed => "failed",
        _ => status.ToString().ToLowerInvariant()
    };

    public static bool TryParse(string value, out PaymentStatus status)
    {
        status = value.Trim().ToLowerInvariant() switch
        {
            "pending" => PaymentStatus.Pending,
            "paid" => PaymentStatus.Paid,
            "failed" => PaymentStatus.Failed,
            _ => default
        };
        return value.Trim().ToLowerInvariant() is "pending" or "paid" or "failed";
    }
}
