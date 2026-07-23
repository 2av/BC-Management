using System.Globalization;
using System.Text;
using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Reports;

public record ReportOverviewDto(
    int TotalGroups,
    int TotalMembers,
    decimal TotalCollected,
    decimal TotalPending,
    IReadOnlyList<GroupReportRowDto> Groups);

public record GroupReportRowDto(
    int GroupId,
    string GroupName,
    int TotalMembers,
    decimal MonthlyContribution,
    decimal TotalCollected,
    int PaidCount,
    int PendingCount,
    decimal PendingAmount,
    int BidCount,
    decimal TotalDistributed);

public record ReportPaymentRowDto(
    string GroupName,
    string MemberName,
    int MemberNumber,
    int MonthNumber,
    decimal PaymentAmount,
    string PaymentStatus,
    DateOnly? PaymentDate,
    string? WinnerName,
    decimal? BidAmount,
    decimal? GainPerMember);

public record ReportBidRowDto(
    string GroupName,
    int MonthNumber,
    string? WinnerName,
    decimal BidAmount,
    decimal NetPayable,
    decimal GainPerMember,
    DateOnly? PaymentDate);

public record GetReportOverviewQuery : IRequest<Result<ReportOverviewDto>>;
public record GetPaymentsReportQuery(int? GroupId, DateOnly? From, DateOnly? To)
    : IRequest<Result<IReadOnlyList<ReportPaymentRowDto>>>;
public record GetBidsReportQuery(int? GroupId) : IRequest<Result<IReadOnlyList<ReportBidRowDto>>>;
public record ExportCsvQuery(string ExportType, int? GroupId, DateOnly? From, DateOnly? To)
    : IRequest<Result<(string FileName, string Csv)>>;

public class GetReportOverviewQueryHandler(IAppDbContext db)
    : IRequestHandler<GetReportOverviewQuery, Result<ReportOverviewDto>>
{
    public async Task<Result<ReportOverviewDto>> Handle(GetReportOverviewQuery request, CancellationToken cancellationToken)
    {
        var groups = await db.BcGroups.OrderBy(g => g.GroupName).ToListAsync(cancellationToken);
        var payments = await db.MemberPayments.ToListAsync(cancellationToken);
        var bids = await db.MonthlyBids.ToListAsync(cancellationToken);
        var memberCounts = await db.GroupMembers
            .Where(gm => gm.Status == "active")
            .GroupBy(gm => gm.GroupId)
            .Select(g => new { GroupId = g.Key, Count = g.Count() })
            .ToDictionaryAsync(x => x.GroupId, x => x.Count, cancellationToken);

        var rows = groups.Select(g =>
        {
            var gp = payments.Where(p => p.GroupId == g.Id).ToList();
            var gb = bids.Where(b => b.GroupId == g.Id).ToList();
            return new GroupReportRowDto(
                g.Id,
                g.GroupName,
                g.TotalMembers,
                g.MonthlyContribution,
                gp.Where(p => p.PaymentStatus == PaymentStatus.Paid).Sum(p => p.PaymentAmount),
                gp.Count(p => p.PaymentStatus == PaymentStatus.Paid),
                gp.Count(p => p.PaymentStatus == PaymentStatus.Pending),
                gp.Where(p => p.PaymentStatus == PaymentStatus.Pending).Sum(p => p.PaymentAmount),
                gb.Count,
                gb.Sum(b => b.NetPayable));
        }).ToList();

        return Result<ReportOverviewDto>.Success(new ReportOverviewDto(
            groups.Count,
            memberCounts.Values.Sum(),
            rows.Sum(r => r.TotalCollected),
            rows.Sum(r => r.PendingAmount),
            rows));
    }
}

public class GetPaymentsReportQueryHandler(IAppDbContext db)
    : IRequestHandler<GetPaymentsReportQuery, Result<IReadOnlyList<ReportPaymentRowDto>>>
{
    public async Task<Result<IReadOnlyList<ReportPaymentRowDto>>> Handle(
        GetPaymentsReportQuery request,
        CancellationToken cancellationToken)
    {
        var query = db.MemberPayments.Include(p => p.Member).Include(p => p.Group).AsQueryable();
        if (request.GroupId is int gid) query = query.Where(p => p.GroupId == gid);
        if (request.From is DateOnly from) query = query.Where(p => p.PaymentDate == null || p.PaymentDate >= from);
        if (request.To is DateOnly to) query = query.Where(p => p.PaymentDate == null || p.PaymentDate <= to);

        var payments = await query.OrderBy(p => p.Group.GroupName).ThenBy(p => p.MonthNumber).ToListAsync(cancellationToken);
        var bids = await db.MonthlyBids.Include(b => b.TakenByMember).ToListAsync(cancellationToken);
        var bidMap = bids.ToDictionary(b => (b.GroupId, b.MonthNumber));
        var numbers = await db.GroupMembers.ToDictionaryAsync(
            gm => (gm.GroupId, gm.MemberId), gm => gm.MemberNumber, cancellationToken);

        var rows = payments.Select(p =>
        {
            bidMap.TryGetValue((p.GroupId, p.MonthNumber), out var bid);
            numbers.TryGetValue((p.GroupId, p.MemberId), out var num);
            return new ReportPaymentRowDto(
                p.Group.GroupName,
                p.Member.MemberName,
                num,
                p.MonthNumber,
                p.PaymentAmount,
                p.PaymentStatus.ToString().ToLowerInvariant(),
                p.PaymentDate,
                bid?.TakenByMember?.MemberName,
                bid?.BidAmount,
                bid?.GainPerMember);
        }).ToList();

        return Result<IReadOnlyList<ReportPaymentRowDto>>.Success(rows);
    }
}

public class GetBidsReportQueryHandler(IAppDbContext db)
    : IRequestHandler<GetBidsReportQuery, Result<IReadOnlyList<ReportBidRowDto>>>
{
    public async Task<Result<IReadOnlyList<ReportBidRowDto>>> Handle(
        GetBidsReportQuery request,
        CancellationToken cancellationToken)
    {
        var query = db.MonthlyBids.Include(b => b.Group).Include(b => b.TakenByMember).AsQueryable();
        if (request.GroupId is int gid) query = query.Where(b => b.GroupId == gid);

        var rows = await query
            .OrderBy(b => b.Group.GroupName)
            .ThenBy(b => b.MonthNumber)
            .Select(b => new ReportBidRowDto(
                b.Group.GroupName,
                b.MonthNumber,
                b.TakenByMember != null ? b.TakenByMember.MemberName : null,
                b.BidAmount,
                b.NetPayable,
                b.GainPerMember,
                b.PaymentDate))
            .ToListAsync(cancellationToken);

        return Result<IReadOnlyList<ReportBidRowDto>>.Success(rows);
    }
}

public class ExportCsvQueryHandler(IMediator mediator)
    : IRequestHandler<ExportCsvQuery, Result<(string FileName, string Csv)>>
{
    public async Task<Result<(string FileName, string Csv)>> Handle(ExportCsvQuery request, CancellationToken cancellationToken)
    {
        return request.ExportType.ToLowerInvariant() switch
        {
            "payments" => await ExportPayments(request, cancellationToken),
            "bids" => await ExportBids(request, cancellationToken),
            "groups" => await ExportGroups(cancellationToken),
            "members" => await ExportMembers(request, cancellationToken),
            _ => Result<(string, string)>.Failure("Unknown export type. Use members, payments, groups, or bids.")
        };
    }

    private async Task<Result<(string, string)>> ExportPayments(ExportCsvQuery request, CancellationToken ct)
    {
        var data = await mediator.Send(new GetPaymentsReportQuery(request.GroupId, request.From, request.To), ct);
        if (!data.Succeeded) return Result<(string, string)>.Failure(data.Error!);
        var sb = new StringBuilder();
        sb.AppendLine("group_name,member_name,member_number,month_number,payment_amount,payment_status,payment_date,winner_name,bid_amount,gain_per_member");
        foreach (var r in data.Data!)
        {
            sb.AppendLine(string.Join(',',
                Csv(r.GroupName), Csv(r.MemberName), r.MemberNumber, r.MonthNumber,
                r.PaymentAmount.ToString(CultureInfo.InvariantCulture), Csv(r.PaymentStatus),
                r.PaymentDate?.ToString("yyyy-MM-dd") ?? "", Csv(r.WinnerName),
                r.BidAmount?.ToString(CultureInfo.InvariantCulture) ?? "",
                r.GainPerMember?.ToString(CultureInfo.InvariantCulture) ?? ""));
        }
        return Result<(string, string)>.Success(("payments_export.csv", sb.ToString()));
    }

    private async Task<Result<(string, string)>> ExportBids(ExportCsvQuery request, CancellationToken ct)
    {
        var data = await mediator.Send(new GetBidsReportQuery(request.GroupId), ct);
        if (!data.Succeeded) return Result<(string, string)>.Failure(data.Error!);
        var sb = new StringBuilder();
        sb.AppendLine("group_name,month_number,winner_name,bid_amount,net_payable,gain_per_member,payment_date");
        foreach (var r in data.Data!)
        {
            sb.AppendLine(string.Join(',',
                Csv(r.GroupName), r.MonthNumber, Csv(r.WinnerName),
                r.BidAmount.ToString(CultureInfo.InvariantCulture),
                r.NetPayable.ToString(CultureInfo.InvariantCulture),
                r.GainPerMember.ToString(CultureInfo.InvariantCulture),
                r.PaymentDate?.ToString("yyyy-MM-dd") ?? ""));
        }
        return Result<(string, string)>.Success(("bids_export.csv", sb.ToString()));
    }

    private async Task<Result<(string, string)>> ExportGroups(CancellationToken ct)
    {
        var data = await mediator.Send(new GetReportOverviewQuery(), ct);
        if (!data.Succeeded) return Result<(string, string)>.Failure(data.Error!);
        var sb = new StringBuilder();
        sb.AppendLine("group_name,total_members,monthly_contribution,total_collected,paid_count,pending_count,pending_amount,bid_count,total_distributed");
        foreach (var r in data.Data!.Groups)
        {
            sb.AppendLine(string.Join(',',
                Csv(r.GroupName), r.TotalMembers,
                r.MonthlyContribution.ToString(CultureInfo.InvariantCulture),
                r.TotalCollected.ToString(CultureInfo.InvariantCulture),
                r.PaidCount, r.PendingCount,
                r.PendingAmount.ToString(CultureInfo.InvariantCulture),
                r.BidCount,
                r.TotalDistributed.ToString(CultureInfo.InvariantCulture)));
        }
        return Result<(string, string)>.Success(("groups_export.csv", sb.ToString()));
    }

    private async Task<Result<(string, string)>> ExportMembers(ExportCsvQuery request, CancellationToken ct)
    {
        // Reuse list members via DB through overview groups — use Members query indirectly not available; simple inline via GetMembersQuery
        var members = await mediator.Send(new Members.GetMembersQuery(null, null), ct);
        if (!members.Succeeded) return Result<(string, string)>.Failure(members.Error!);
        var filtered = members.Data!;
        if (request.GroupId is int gid)
            filtered = filtered.Where(m => m.Groups.Any(g => g.GroupId == gid)).ToList();

        var sb = new StringBuilder();
        sb.AppendLine("member_name,username,phone,email,status,groups");
        foreach (var m in filtered)
        {
            var groups = string.Join(';', m.Groups.Select(g => $"{g.GroupName}#{g.MemberNumber}"));
            sb.AppendLine(string.Join(',',
                Csv(m.MemberName), Csv(m.Username), Csv(m.Phone), Csv(m.Email), Csv(m.Status), Csv(groups)));
        }
        return Result<(string, string)>.Success(("members_export.csv", sb.ToString()));
    }

    private static string Csv(string? value)
    {
        value ??= "";
        if (value.Contains(',') || value.Contains('"') || value.Contains('\n'))
            return $"\"{value.Replace("\"", "\"\"")}\"";
        return value;
    }
}
