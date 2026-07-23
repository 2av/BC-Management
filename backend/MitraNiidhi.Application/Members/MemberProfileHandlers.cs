using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Application.Settings;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Members;

public record MemberProfileDto(
    int Id,
    string MemberName,
    string? Username,
    string? Phone,
    string? Email,
    string? Address,
    string Status,
    DateTime CreatedAt);

public record UpdateMemberProfileRequest(
    string MemberName,
    string? Phone,
    string? Email,
    string? Address);

public record PaymentMonthOptionDto(
    int GroupId,
    string GroupName,
    int MonthNumber,
    decimal Amount,
    string Status,
    DateOnly? PaymentDate);

public record MemberPaymentDetailDto(
    int GroupId,
    string GroupName,
    int MonthNumber,
    decimal Amount,
    string MemberName,
    string? WinnerName,
    string PaymentStatus,
    bool QrEnabled,
    string? UpiId,
    string? QrImageUrl,
    string? UpiUrl);

public record GetMemberProfileQuery : IRequest<Result<MemberProfileDto>>;
public record UpdateMemberProfileCommand(UpdateMemberProfileRequest Request) : IRequest<Result<MemberProfileDto>>;
public record GetPaymentOptionsQuery : IRequest<Result<IReadOnlyList<PaymentMonthOptionDto>>>;
public record GetMemberPaymentDetailQuery(int GroupId, int MonthNumber) : IRequest<Result<MemberPaymentDetailDto>>;

public class GetMemberProfileQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetMemberProfileQuery, Result<MemberProfileDto>>
{
    public async Task<Result<MemberProfileDto>> Handle(GetMemberProfileQuery request, CancellationToken ct)
    {
        if (currentUser.UserId is null) return Result<MemberProfileDto>.Failure("Not authenticated.");
        var m = await db.Members.FirstOrDefaultAsync(x => x.Id == currentUser.UserId, ct);
        if (m is null) return Result<MemberProfileDto>.Failure("Member not found.");
        return Result<MemberProfileDto>.Success(new MemberProfileDto(
            m.Id, m.MemberName, m.Username, m.Phone, m.Email, m.Address, m.Status, m.CreatedAt));
    }
}

public class UpdateMemberProfileCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<UpdateMemberProfileCommand, Result<MemberProfileDto>>
{
    public async Task<Result<MemberProfileDto>> Handle(UpdateMemberProfileCommand command, CancellationToken ct)
    {
        if (currentUser.UserId is null) return Result<MemberProfileDto>.Failure("Not authenticated.");
        var m = await db.Members.FirstOrDefaultAsync(x => x.Id == currentUser.UserId, ct);
        if (m is null) return Result<MemberProfileDto>.Failure("Member not found.");

        var req = command.Request;
        if (string.IsNullOrWhiteSpace(req.MemberName))
            return Result<MemberProfileDto>.Failure("Name is required.");

        var oldName = m.MemberName;
        m.MemberName = req.MemberName.Trim();
        m.Phone = req.Phone?.Trim();
        m.Email = req.Email?.Trim();
        m.Address = req.Address?.Trim();

        // Sync name across all member rows with same legacy name
        if (!string.Equals(oldName, m.MemberName, StringComparison.Ordinal))
        {
            var sameName = await db.Members.Where(x => x.MemberName == oldName && x.Id != m.Id).ToListAsync(ct);
            foreach (var other in sameName) other.MemberName = m.MemberName;
        }

        await db.SaveChangesAsync(ct);
        return Result<MemberProfileDto>.Success(new MemberProfileDto(
            m.Id, m.MemberName, m.Username, m.Phone, m.Email, m.Address, m.Status, m.CreatedAt));
    }
}

public class GetPaymentOptionsQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetPaymentOptionsQuery, Result<IReadOnlyList<PaymentMonthOptionDto>>>
{
    public async Task<Result<IReadOnlyList<PaymentMonthOptionDto>>> Handle(GetPaymentOptionsQuery request, CancellationToken ct)
    {
        if (currentUser.UserId is null)
            return Result<IReadOnlyList<PaymentMonthOptionDto>>.Failure("Not authenticated.");

        var memberId = currentUser.UserId.Value;
        var memberships = await db.GroupMembers
            .Where(gm => gm.MemberId == memberId && gm.Status == "active")
            .Include(gm => gm.Group)
            .ToListAsync(ct);

        var options = new List<PaymentMonthOptionDto>();
        foreach (var gm in memberships)
        {
            var group = gm.Group;
            var bids = await db.MonthlyBids.Where(b => b.GroupId == group.Id).ToListAsync(ct);
            var payments = await db.MemberPayments
                .Where(p => p.GroupId == group.Id && p.MemberId == memberId)
                .ToListAsync(ct);

            for (var month = 1; month <= group.TotalMembers; month++)
            {
                var bid = bids.FirstOrDefault(b => b.MonthNumber == month);
                var payment = payments.FirstOrDefault(p => p.MonthNumber == month);
                string status;
                decimal amount;
                DateOnly? paidDate = null;

                if (payment?.PaymentStatus == PaymentStatus.Paid)
                {
                    status = "paid";
                    amount = payment.PaymentAmount;
                    paidDate = payment.PaymentDate;
                }
                else if (bid is not null)
                {
                    status = "pending";
                    amount = bid.GainPerMember;
                }
                else
                {
                    status = "not_ready";
                    amount = group.MonthlyContribution;
                }

                options.Add(new PaymentMonthOptionDto(group.Id, group.GroupName, month, amount, status, paidDate));
            }
        }

        return Result<IReadOnlyList<PaymentMonthOptionDto>>.Success(options);
    }
}

public record MemberPaymentMethodsDto(
    bool QrEnabled,
    string UpiId,
    string PayeeName,
    string PaymentNote,
    string? QrImageUrl,
    string? UpiUrl);

public record GetMemberPaymentMethodsQuery : IRequest<Result<MemberPaymentMethodsDto>>;

public class GetMemberPaymentMethodsQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetMemberPaymentMethodsQuery, Result<MemberPaymentMethodsDto>>
{
    public async Task<Result<MemberPaymentMethodsDto>> Handle(GetMemberPaymentMethodsQuery request, CancellationToken ct)
    {
        if (currentUser.UserId is null)
            return Result<MemberPaymentMethodsDto>.Failure("Not authenticated.");

        var resolvedClientId = currentUser.ClientId
            ?? await db.GroupMembers
                .Where(gm => gm.MemberId == currentUser.UserId && gm.Status == "active")
                .Join(db.BcGroups, gm => gm.GroupId, g => g.Id, (gm, g) => g.ClientId)
                .FirstOrDefaultAsync(ct)
            ?? 1;

        var map = await GetPaymentConfigQueryHandler.GetConfigMap(db, resolvedClientId, ct);
        // Fallback: legacy payment_config rows may exist without matching client scope.
        if (string.IsNullOrWhiteSpace(map.GetValueOrDefault("upi_id")))
            map = await GetPaymentConfigQueryHandler.GetConfigMap(db, 1, ct);

        var qrEnabled = map.GetValueOrDefault("qr_enabled", "0") == "1";
        var upiId = map.GetValueOrDefault("upi_id", "");
        var payee = map.GetValueOrDefault("bank_account_name", "BC Admin");
        var note = map.GetValueOrDefault("payment_note", "BC Payment");
        string? upiUrl = null;
        string? qrImageUrl = null;
        if (qrEnabled && !string.IsNullOrWhiteSpace(upiId))
        {
            upiUrl = $"upi://pay?pa={Uri.EscapeDataString(upiId)}&pn={Uri.EscapeDataString(payee)}&cu=INR&tn={Uri.EscapeDataString(note)}";
            qrImageUrl = $"https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={Uri.EscapeDataString(upiUrl)}";
        }

        return Result<MemberPaymentMethodsDto>.Success(new MemberPaymentMethodsDto(
            qrEnabled && !string.IsNullOrWhiteSpace(upiId),
            upiId,
            payee,
            note,
            qrImageUrl,
            upiUrl));
    }
}

public class GetMemberPaymentDetailQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetMemberPaymentDetailQuery, Result<MemberPaymentDetailDto>>
{
    public async Task<Result<MemberPaymentDetailDto>> Handle(GetMemberPaymentDetailQuery request, CancellationToken ct)
    {
        if (currentUser.UserId is null) return Result<MemberPaymentDetailDto>.Failure("Not authenticated.");
        var memberId = currentUser.UserId.Value;

        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == request.GroupId, ct);
        if (group is null) return Result<MemberPaymentDetailDto>.Failure("Group not found.");

        var isMember = await db.GroupMembers.AnyAsync(
            gm => gm.GroupId == request.GroupId && gm.MemberId == memberId && gm.Status == "active", ct);
        if (!isMember) return Result<MemberPaymentDetailDto>.Failure("Access denied.");

        var member = await db.Members.FirstAsync(m => m.Id == memberId, ct);
        var bid = await db.MonthlyBids
            .Include(b => b.TakenByMember)
            .FirstOrDefaultAsync(b => b.GroupId == request.GroupId && b.MonthNumber == request.MonthNumber, ct);
        var payment = await db.MemberPayments.FirstOrDefaultAsync(
            p => p.GroupId == request.GroupId && p.MemberId == memberId && p.MonthNumber == request.MonthNumber, ct);

        var amount = bid?.GainPerMember ?? group.MonthlyContribution;
        var status = payment?.PaymentStatus.ToString().ToLowerInvariant() ?? "pending";

        var clientId = group.ClientId ?? currentUser.ClientId ?? 1;
        var configMap = await GetPaymentConfigQueryHandler.GetConfigMap(db, clientId, ct);
        if (string.IsNullOrWhiteSpace(configMap.GetValueOrDefault("upi_id")))
            configMap = await GetPaymentConfigQueryHandler.GetConfigMap(db, 1, ct);

        var unpaid = payment?.PaymentStatus != PaymentStatus.Paid;
        var upiId = configMap.GetValueOrDefault("upi_id", "");
        var qrEnabled = configMap.GetValueOrDefault("qr_enabled", "0") == "1"
                        && unpaid
                        && !string.IsNullOrWhiteSpace(upiId);
        string? upiUrl = null;
        string? qrImageUrl = null;
        if (configMap.GetValueOrDefault("qr_enabled", "0") == "1" && !string.IsNullOrWhiteSpace(upiId))
        {
            var payee = configMap.GetValueOrDefault("bank_account_name", "BC Admin");
            var note = $"{configMap.GetValueOrDefault("payment_note", "BC Payment")} - {group.GroupName} M{request.MonthNumber}";
            upiUrl = unpaid
                ? $"upi://pay?pa={Uri.EscapeDataString(upiId)}&pn={Uri.EscapeDataString(payee)}&am={amount:0.##}&cu=INR&tn={Uri.EscapeDataString(note)}"
                : $"upi://pay?pa={Uri.EscapeDataString(upiId)}&pn={Uri.EscapeDataString(payee)}&cu=INR&tn={Uri.EscapeDataString(note)}";
            qrImageUrl = $"https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={Uri.EscapeDataString(upiUrl)}";
        }

        return Result<MemberPaymentDetailDto>.Success(new MemberPaymentDetailDto(
            group.Id, group.GroupName, request.MonthNumber, amount, member.MemberName,
            bid?.TakenByMember?.MemberName, status, qrEnabled || (unpaid && !string.IsNullOrWhiteSpace(qrImageUrl)),
            string.IsNullOrWhiteSpace(upiId) ? null : upiId, qrImageUrl, upiUrl));
    }
}
