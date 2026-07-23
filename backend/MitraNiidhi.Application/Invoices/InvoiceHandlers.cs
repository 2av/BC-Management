using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Invoices;

public record InvoiceLineDto(
    int MonthNumber,
    decimal ExpectedAmount,
    decimal PaidAmount,
    string Status,
    DateOnly? PaymentDate);

public record InvoiceDto(
    string InvoiceNumber,
    DateOnly InvoiceDate,
    string GroupName,
    int TotalMembers,
    decimal MonthlyContribution,
    decimal TotalMonthlyCollection,
    string MemberName,
    int MemberNumber,
    string? Phone,
    string? Email,
    IReadOnlyList<InvoiceLineDto> Lines,
    decimal TotalPaid,
    decimal GivenAmount,
    decimal Profit);

public record GetInvoiceQuery(int GroupId, int MemberId) : IRequest<Result<InvoiceDto>>;

public class GetInvoiceQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetInvoiceQuery, Result<InvoiceDto>>
{
    public async Task<Result<InvoiceDto>> Handle(GetInvoiceQuery request, CancellationToken ct)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == request.GroupId, ct);
        if (group is null) return Result<InvoiceDto>.Failure("Group not found.");

        var member = await db.Members.FirstOrDefaultAsync(m => m.Id == request.MemberId, ct);
        if (member is null) return Result<InvoiceDto>.Failure("Member not found.");

        if (currentUser.Role == UserRole.Member)
        {
            var isMember = await db.GroupMembers.AnyAsync(
                gm => gm.GroupId == request.GroupId && gm.MemberId == currentUser.UserId && gm.Status == "active", ct);
            if (!isMember) return Result<InvoiceDto>.Failure("Access denied.");
        }

        var gm = await db.GroupMembers.FirstOrDefaultAsync(
            x => x.GroupId == request.GroupId && x.MemberId == request.MemberId, ct);
        var memberNumber = gm?.MemberNumber ?? 0;

        var bids = await db.MonthlyBids
            .Where(b => b.GroupId == request.GroupId)
            .ToDictionaryAsync(b => b.MonthNumber, ct);

        var payments = await db.MemberPayments
            .Where(p => p.GroupId == request.GroupId && p.MemberId == request.MemberId)
            .ToDictionaryAsync(p => p.MonthNumber, ct);

        var summary = await db.MemberSummaries
            .FirstOrDefaultAsync(s => s.GroupId == request.GroupId && s.MemberId == request.MemberId, ct);

        var lines = new List<InvoiceLineDto>();
        for (var m = 1; m <= group.TotalMembers; m++)
        {
            bids.TryGetValue(m, out var bid);
            payments.TryGetValue(m, out var payment);
            var expected = bid?.GainPerMember ?? group.MonthlyContribution;
            var status = payment?.PaymentStatus switch
            {
                PaymentStatus.Paid => "paid",
                PaymentStatus.Pending => "pending",
                _ => bid is not null ? "unpaid" : "not_ready"
            };
            lines.Add(new InvoiceLineDto(
                m, expected, payment?.PaymentAmount ?? 0, status, payment?.PaymentDate));
        }

        var invoiceNumber = $"INV-{request.GroupId}-{request.MemberId}-{DateTime.UtcNow:yyyyMMdd}";
        return Result<InvoiceDto>.Success(new InvoiceDto(
            invoiceNumber,
            DateOnly.FromDateTime(DateTime.UtcNow),
            group.GroupName,
            group.TotalMembers,
            group.MonthlyContribution,
            group.TotalMonthlyCollection,
            member.MemberName,
            memberNumber,
            member.Phone,
            member.Email,
            lines,
            summary?.TotalPaid ?? lines.Where(l => l.Status == "paid").Sum(l => l.PaidAmount),
            summary?.GivenAmount ?? 0,
            summary?.Profit ?? 0));
    }
}
