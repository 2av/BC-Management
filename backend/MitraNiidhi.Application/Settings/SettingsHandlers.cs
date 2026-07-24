using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Application.Payments;
using MitraNiidhi.Domain.Entities;

namespace MitraNiidhi.Application.Settings;

public record PaymentConfigDto(
    string UpiId,
    string BankAccountName,
    string PaymentNote,
    bool QrEnabled);

public record UpdatePaymentConfigRequest(
    string UpiId,
    string BankAccountName,
    string PaymentNote,
    bool QrEnabled);

public record PaymentQrDto(
    string UpiUrl,
    string QrImageUrl,
    decimal Amount,
    string PayeeName,
    string UpiId,
    string Note);

public record SystemSettingDto(
    string Key,
    string Value,
    string Type,
    string Category,
    string? Description);

public record SystemSettingsResponseDto(
    IReadOnlyDictionary<string, IReadOnlyList<SystemSettingDto>> SettingsByCategory,
    SystemStatsDto Stats);

public record SystemStatsDto(
    int TotalGroups,
    int TotalMembers,
    decimal TotalCollected,
    int TotalPayments,
    int TotalAdmins);

public record UpdateSystemSettingsRequest(Dictionary<string, string> Settings);

public record GetPaymentConfigQuery : IRequest<Result<PaymentConfigDto>>;
public record UpdatePaymentConfigCommand(UpdatePaymentConfigRequest Request) : IRequest<Result>;
public record GetPaymentQrQuery(int GroupId, int MonthNumber) : IRequest<Result<PaymentQrDto>>;
public record GetSystemSettingsQuery : IRequest<Result<SystemSettingsResponseDto>>;
public record UpdateSystemSettingsCommand(UpdateSystemSettingsRequest Request) : IRequest<Result>;

public static class SettingsDefaults
{
    internal static readonly (string Key, string Default, string Desc)[] PaymentKeys =
    [
        ("upi_id", "", "UPI ID for receiving payments"),
        ("bank_account_name", "BC Group Admin", "Payee name in UPI"),
        ("payment_note", "BC Group Monthly Payment", "Default payment note"),
        ("qr_enabled", "1", "Enable QR payments")
    ];

    internal static readonly (string Key, string Value, string Type, string Desc, string Cat)[] DefaultSettings =
    [
        ("app_name", "Mitra Niidhi", "text", "Application name", "general"),
        ("app_version", "2.1.0", "text", "Application version", "general"),
        ("default_currency", "INR", "text", "Default currency", "general"),
        ("currency_symbol", "₹", "text", "Currency symbol", "general"),
        ("max_group_members", "20", "number", "Max members per group", "groups"),
        ("min_group_members", "5", "number", "Min members per group", "groups"),
        ("default_group_duration", "18", "number", "Default group duration (months)", "groups"),
        ("enable_sms_notifications", "1", "boolean", "Enable SMS notifications", "notifications"),
        ("enable_email_notifications", "1", "boolean", "Enable email notifications", "notifications"),
        ("payment_reminder_days", "3", "number", "Payment reminder days before due", "payments"),
        ("late_payment_penalty", "50", "number", "Late payment penalty", "payments"),
        ("enable_qr_payments", "1", "boolean", "Enable QR code payments", "payments"),
        ("backup_frequency", "daily", "text", "Backup frequency", "system"),
        ("maintenance_mode", "0", "boolean", "Maintenance mode", "system"),
        ("session_timeout", "3600", "number", "Session timeout (seconds)", "security"),
        ("max_login_attempts", "5", "number", "Max login attempts", "security"),
        ("password_min_length", "6", "number", "Min password length", "security")
    ];
}

public class GetPaymentConfigQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetPaymentConfigQuery, Result<PaymentConfigDto>>
{
    public async Task<Result<PaymentConfigDto>> Handle(GetPaymentConfigQuery request, CancellationToken ct)
    {
        var clientId = currentUser.ClientId ?? 1;
        var map = await GetConfigMap(db, clientId, ct);
        return Result<PaymentConfigDto>.Success(ToDto(map));
    }

    internal static async Task<Dictionary<string, string>> GetConfigMap(IAppDbContext db, int clientId, CancellationToken ct)
    {
        var rows = await db.PaymentConfigs.Where(c => c.ClientId == clientId).ToListAsync(ct);
        if (rows.Count == 0)
            rows = await db.PaymentConfigs.Where(c => c.ClientId == 1 || c.ClientId == 0).ToListAsync(ct);
        if (rows.Count == 0)
            rows = await db.PaymentConfigs.Take(20).ToListAsync(ct);

        var map = SettingsDefaults.PaymentKeys.ToDictionary(k => k.Key, k => k.Default);
        foreach (var row in rows) map[row.ConfigKey] = row.ConfigValue ?? "";
        return map;
    }

    internal static PaymentConfigDto ToDto(Dictionary<string, string> map) => new(
        map.GetValueOrDefault("upi_id", ""),
        map.GetValueOrDefault("bank_account_name", ""),
        map.GetValueOrDefault("payment_note", ""),
        map.GetValueOrDefault("qr_enabled", "0") == "1");
}

public class UpdatePaymentConfigCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<UpdatePaymentConfigCommand, Result>
{
    public async Task<Result> Handle(UpdatePaymentConfigCommand command, CancellationToken ct)
    {
        var clientId = currentUser.ClientId ?? 1;
        var req = command.Request;
        var values = new Dictionary<string, string>
        {
            ["upi_id"] = req.UpiId.Trim(),
            ["bank_account_name"] = req.BankAccountName.Trim(),
            ["payment_note"] = req.PaymentNote.Trim(),
            ["qr_enabled"] = req.QrEnabled ? "1" : "0"
        };

        foreach (var (key, value) in values)
        {
            var row = await db.PaymentConfigs.FirstOrDefaultAsync(c => c.ClientId == clientId && c.ConfigKey == key, ct);
            if (row is null)
            {
                db.PaymentConfigs.Add(new PaymentConfig
                {
                    ClientId = clientId,
                    ConfigKey = key,
                    ConfigValue = value,
                    Description = SettingsDefaults.PaymentKeys.First(k => k.Key == key).Desc
                });
            }
            else
            {
                row.ConfigValue = value;
                row.UpdatedAt = DateTime.UtcNow;
            }
        }
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}

public class GetPaymentQrQueryHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<GetPaymentQrQuery, Result<PaymentQrDto>>
{
    public async Task<Result<PaymentQrDto>> Handle(GetPaymentQrQuery request, CancellationToken ct)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == request.GroupId, ct);
        if (group is null) return Result<PaymentQrDto>.Failure("Group not found.");

        var clientId = group.ClientId ?? currentUser.ClientId ?? 1;
        var map = await GetPaymentConfigQueryHandler.GetConfigMap(db, clientId, ct);
        if (map.GetValueOrDefault("qr_enabled", "0") != "1")
            return Result<PaymentQrDto>.Failure("QR payments are disabled.");

        var bid = await db.MonthlyBids.FirstOrDefaultAsync(b => b.GroupId == request.GroupId && b.MonthNumber == request.MonthNumber, ct);
        var due = await db.MonthBiddingStatuses
            .Where(s => s.GroupId == request.GroupId && s.MonthNumber == request.MonthNumber)
            .Select(s => s.PaymentDueAmount)
            .FirstOrDefaultAsync(ct);
        var amount = UpiPaymentHelper.ResolveDueAmount(group.MonthlyContribution, due, bid?.GainPerMember);
        var upiId = map.GetValueOrDefault("upi_id", "");
        var payee = UpiPaymentHelper.BrandPayee;
        var note = UpiPaymentHelper.PaymentNote(group.GroupName, request.MonthNumber);
        var (upiUrl, qrUrl) = UpiPaymentHelper.BuildUrls(upiId, payee, note, amount);

        return Result<PaymentQrDto>.Success(new PaymentQrDto(upiUrl, qrUrl, amount, payee, upiId, note));
    }
}

public class GetSystemSettingsQueryHandler(IAppDbContext db)
    : IRequestHandler<GetSystemSettingsQuery, Result<SystemSettingsResponseDto>>
{
    public async Task<Result<SystemSettingsResponseDto>> Handle(GetSystemSettingsQuery request, CancellationToken ct)
    {
        if (!await db.SystemSettings.AnyAsync(ct))
            await SeedDefaults(db, ct);

        var settings = await db.SystemSettings.OrderBy(s => s.Category).ThenBy(s => s.SettingKey).ToListAsync(ct);
        var grouped = settings
            .GroupBy(s => s.Category)
            .ToDictionary(
                g => g.Key,
                g => (IReadOnlyList<SystemSettingDto>)g.Select(s =>
                    new SystemSettingDto(s.SettingKey, s.SettingValue ?? "", s.SettingType, s.Category, s.Description)).ToList());

        var stats = new SystemStatsDto(
            await db.BcGroups.CountAsync(ct),
            await db.Members.CountAsync(m => m.Status == "active", ct),
            await db.MemberPayments.Where(p => p.PaymentStatus == Domain.Enums.PaymentStatus.Paid)
                .SumAsync(p => (decimal?)p.PaymentAmount, ct) ?? 0,
            await db.MemberPayments.CountAsync(p => p.PaymentStatus == Domain.Enums.PaymentStatus.Paid, ct),
            await db.ClientAdmins.CountAsync(ct) + await db.AdminUsers.CountAsync(ct));

        return Result<SystemSettingsResponseDto>.Success(new SystemSettingsResponseDto(grouped, stats));
    }

    private static async Task SeedDefaults(IAppDbContext db, CancellationToken ct)
    {
        foreach (var d in SettingsDefaults.DefaultSettings)
        {
            db.SystemSettings.Add(new SystemSetting
            {
                SettingKey = d.Key,
                SettingValue = d.Value,
                SettingType = d.Type,
                Description = d.Desc,
                Category = d.Cat
            });
        }
        await db.SaveChangesAsync(ct);
    }
}

public class UpdateSystemSettingsCommandHandler(IAppDbContext db)
    : IRequestHandler<UpdateSystemSettingsCommand, Result>
{
    public async Task<Result> Handle(UpdateSystemSettingsCommand command, CancellationToken ct)
    {
        var keys = await db.SystemSettings.ToListAsync(ct);
        foreach (var setting in keys)
        {
            if (command.Request.Settings.TryGetValue(setting.SettingKey, out var value))
                setting.SettingValue = value;
            else if (setting.SettingType == "boolean")
                setting.SettingValue = "0";
            setting.UpdatedAt = DateTime.UtcNow;
        }
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}
