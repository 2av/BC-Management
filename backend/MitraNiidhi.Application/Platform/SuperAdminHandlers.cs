using System.Text.Json;
using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Application.Notifications;
using MitraNiidhi.Domain.Entities;

namespace MitraNiidhi.Application.Platform;

// ── DTOs ──────────────────────────────────────────────────────────────────────

public record SuperAdminDashboardDto(
    int ClientCount,
    int ActiveClients,
    int GroupCount,
    int MemberCount,
    decimal MonthlyRevenue,
    int ExpiringSoon);

public record ClientListItemDto(
    int Id,
    string ClientName,
    string? CompanyName,
    string ContactPerson,
    string Email,
    string Phone,
    string Status,
    string? SubscriptionStatus,
    DateOnly? SubscriptionEndDate,
    int GroupCount,
    int MemberCount);

public record ClientDetailDto(
    int Id,
    string ClientName,
    string? CompanyName,
    string ContactPerson,
    string Email,
    string Phone,
    string? Address,
    string? City,
    string? State,
    string? Country,
    string? Pincode,
    string Status,
    string? SubscriptionStatus,
    DateOnly? SubscriptionEndDate,
    int? MaxGroups,
    int? MaxMembersPerGroup,
    int GroupCount,
    int MemberCount,
    IReadOnlyList<ClientSubscriptionDto> Subscriptions);

public record ClientSubscriptionDto(
    int Id,
    int PlanId,
    string PlanName,
    DateOnly StartDate,
    DateOnly EndDate,
    string Status,
    decimal PaymentAmount);

public record CreateClientRequest(
    string ClientName,
    string? CompanyName,
    string ContactPerson,
    string Email,
    string Phone,
    string? Address,
    string? City,
    string? State,
    string? Country,
    string? Pincode,
    int? MaxGroups,
    int? MaxMembersPerGroup,
    string? AdminUsername,
    string? AdminPassword);

public record UpdateClientRequest(
    string ClientName,
    string? CompanyName,
    string ContactPerson,
    string Email,
    string Phone,
    string? Address,
    string? City,
    string? State,
    string? Country,
    string? Pincode,
    int? MaxGroups,
    int? MaxMembersPerGroup,
    string Status);

public record PlanDto(
    int Id,
    string PlanName,
    int DurationMonths,
    decimal Price,
    string Currency,
    string? Description,
    IReadOnlyList<string> Features,
    bool IsActive,
    bool IsPromotional,
    decimal PromotionalDiscount,
    int? MaxGroups,
    int? MaxMembersPerGroup);

public record SavePlanRequest(
    string PlanName,
    int DurationMonths,
    decimal Price,
    string? Description,
    IReadOnlyList<string>? Features,
    bool IsActive,
    bool IsPromotional,
    decimal PromotionalDiscount,
    int? MaxGroups,
    int? MaxMembersPerGroup);

public record AssignSubscriptionRequest(
    int ClientId,
    int PlanId,
    decimal PaymentAmount,
    string? PaymentMethod,
    string? PaymentReference);

public record ExtendSubscriptionRequest(int Months);

public record SubscriptionPaymentDto(
    int Id,
    int ClientId,
    string ClientName,
    int SubscriptionId,
    decimal Amount,
    string Currency,
    string? PaymentMethod,
    string? PaymentReference,
    string PaymentStatus,
    DateTime? PaymentDate,
    DateTime CreatedAt);

// ── Queries ───────────────────────────────────────────────────────────────────

public record GetSuperAdminDashboardQuery : IRequest<Result<SuperAdminDashboardDto>>;
public record GetClientsQuery : IRequest<Result<IReadOnlyList<ClientListItemDto>>>;
public record GetClientDetailQuery(int Id) : IRequest<Result<ClientDetailDto>>;
public record GetPlansQuery : IRequest<Result<IReadOnlyList<PlanDto>>>;
public record GetSubscriptionsQuery(int? ClientId) : IRequest<Result<IReadOnlyList<ClientSubscriptionDto>>>;
public record GetSubscriptionPaymentsQuery(string? Status, int? ClientId) : IRequest<Result<IReadOnlyList<SubscriptionPaymentDto>>>;

// ── Commands ──────────────────────────────────────────────────────────────────

public record CreateClientCommand(CreateClientRequest Request) : IRequest<Result<ClientListItemDto>>;
public record UpdateClientCommand(int Id, UpdateClientRequest Request) : IRequest<Result>;
public record ToggleClientStatusCommand(int Id) : IRequest<Result<string>>;
public record SavePlanCommand(int? Id, SavePlanRequest Request) : IRequest<Result<PlanDto>>;
public record DeletePlanCommand(int Id) : IRequest<Result>;
public record AssignSubscriptionCommand(AssignSubscriptionRequest Request) : IRequest<Result<ClientSubscriptionDto>>;
public record ExtendSubscriptionCommand(int SubscriptionId, ExtendSubscriptionRequest Request) : IRequest<Result>;
public record CancelSubscriptionCommand(int SubscriptionId) : IRequest<Result>;
public record MarkSubscriptionPaymentCommand(int PaymentId) : IRequest<Result>;

// ── Handlers ──────────────────────────────────────────────────────────────────

public class GetSuperAdminDashboardQueryHandler(IAppDbContext db)
    : IRequestHandler<GetSuperAdminDashboardQuery, Result<SuperAdminDashboardDto>>
{
    public async Task<Result<SuperAdminDashboardDto>> Handle(GetSuperAdminDashboardQuery request, CancellationToken ct)
    {
        var clients = await db.Clients.ToListAsync(ct);
        var active = clients.Count(c => c.Status == "active");
        var groupCount = await db.BcGroups.CountAsync(ct);
        var memberCount = await db.Members.CountAsync(m => m.Status == "active", ct);
        var monthStart = new DateTime(DateTime.UtcNow.Year, DateTime.UtcNow.Month, 1);
        var revenue = await db.SubscriptionPayments
            .Where(p => p.PaymentStatus == "completed" && p.PaymentDate >= monthStart)
            .SumAsync(p => (decimal?)p.Amount, ct) ?? 0;
        var soon = DateOnly.FromDateTime(DateTime.UtcNow.AddDays(30));
        var expiring = clients.Count(c =>
            c.SubscriptionEndDate.HasValue && c.SubscriptionEndDate <= soon && c.Status == "active");

        await NotificationWriter.EnsureSubscriptionExpiryAsync(db, ct);

        return Result<SuperAdminDashboardDto>.Success(new SuperAdminDashboardDto(
            clients.Count, active, groupCount, memberCount, revenue, expiring));
    }
}

public class GetClientsQueryHandler(IAppDbContext db)
    : IRequestHandler<GetClientsQuery, Result<IReadOnlyList<ClientListItemDto>>>
{
    public async Task<Result<IReadOnlyList<ClientListItemDto>>> Handle(GetClientsQuery request, CancellationToken ct)
    {
        var clients = await db.Clients.OrderByDescending(c => c.CreatedAt).ToListAsync(ct);
        var groupCounts = await db.BcGroups.GroupBy(g => g.ClientId).Select(g => new { ClientId = g.Key, Count = g.Count() }).ToListAsync(ct);
        var memberCounts = await db.GroupMembers
            .Where(gm => gm.Status == "active")
            .Join(db.BcGroups, gm => gm.GroupId, g => g.Id, (gm, g) => new { g.ClientId })
            .GroupBy(x => x.ClientId)
            .Select(g => new { ClientId = g.Key, Count = g.Count() })
            .ToListAsync(ct);

        var list = clients.Select(c => new ClientListItemDto(
            c.Id, c.ClientName, c.CompanyName, c.ContactPerson, c.Email, c.Phone,
            c.Status, c.SubscriptionStatus, c.SubscriptionEndDate,
            groupCounts.FirstOrDefault(x => x.ClientId == c.Id)?.Count ?? 0,
            memberCounts.FirstOrDefault(x => x.ClientId == c.Id)?.Count ?? 0)).ToList();

        return Result<IReadOnlyList<ClientListItemDto>>.Success(list);
    }
}

public class GetClientDetailQueryHandler(IAppDbContext db)
    : IRequestHandler<GetClientDetailQuery, Result<ClientDetailDto>>
{
    public async Task<Result<ClientDetailDto>> Handle(GetClientDetailQuery request, CancellationToken ct)
    {
        var c = await db.Clients.FirstOrDefaultAsync(x => x.Id == request.Id, ct);
        if (c is null) return Result<ClientDetailDto>.Failure("Client not found.");

        var subs = await db.ClientSubscriptions
            .Include(s => s.Plan)
            .Where(s => s.ClientId == request.Id)
            .OrderByDescending(s => s.CreatedAt)
            .Take(10)
            .ToListAsync(ct);

        var groupCount = await db.BcGroups.CountAsync(g => g.ClientId == request.Id, ct);
        var memberCount = await db.GroupMembers
            .Where(gm => gm.Status == "active" && db.BcGroups.Any(g => g.Id == gm.GroupId && g.ClientId == request.Id))
            .CountAsync(ct);

        return Result<ClientDetailDto>.Success(new ClientDetailDto(
            c.Id, c.ClientName, c.CompanyName, c.ContactPerson, c.Email, c.Phone,
            c.Address, c.City, c.State, c.Country, c.Pincode, c.Status,
            c.SubscriptionStatus, c.SubscriptionEndDate, c.MaxGroups, c.MaxMembersPerGroup,
            groupCount, memberCount,
            subs.Select(s => new ClientSubscriptionDto(
                s.Id, s.PlanId, s.Plan.PlanName, s.StartDate, s.EndDate, s.Status, s.PaymentAmount)).ToList()));
    }
}

public class GetPlansQueryHandler(IAppDbContext db)
    : IRequestHandler<GetPlansQuery, Result<IReadOnlyList<PlanDto>>>
{
    public async Task<Result<IReadOnlyList<PlanDto>>> Handle(GetPlansQuery request, CancellationToken ct)
    {
        var plans = await db.SubscriptionPlans.OrderBy(p => p.DurationMonths).ToListAsync(ct);
        return Result<IReadOnlyList<PlanDto>>.Success(plans.Select(MapPlan).ToList());
    }

    internal static PlanDto MapPlan(SubscriptionPlan p) => new(
        p.Id, p.PlanName, p.DurationMonths, p.Price, p.Currency, p.Description,
        ParseFeatures(p.FeaturesJson), p.IsActive, p.IsPromotional, p.PromotionalDiscount,
        p.MaxGroups, p.MaxMembersPerGroup);

    internal static IReadOnlyList<string> ParseFeatures(string? json)
    {
        if (string.IsNullOrWhiteSpace(json)) return [];
        try { return JsonSerializer.Deserialize<List<string>>(json) ?? []; }
        catch { return []; }
    }
}

public class GetSubscriptionsQueryHandler(IAppDbContext db)
    : IRequestHandler<GetSubscriptionsQuery, Result<IReadOnlyList<ClientSubscriptionDto>>>
{
    public async Task<Result<IReadOnlyList<ClientSubscriptionDto>>> Handle(GetSubscriptionsQuery request, CancellationToken ct)
    {
        var q = db.ClientSubscriptions.Include(s => s.Plan).AsQueryable();
        if (request.ClientId.HasValue) q = q.Where(s => s.ClientId == request.ClientId);
        var subs = await q.OrderByDescending(s => s.CreatedAt).Take(50).ToListAsync(ct);
        return Result<IReadOnlyList<ClientSubscriptionDto>>.Success(subs.Select(s =>
            new ClientSubscriptionDto(s.Id, s.PlanId, s.Plan.PlanName, s.StartDate, s.EndDate, s.Status, s.PaymentAmount)).ToList());
    }
}

public class GetSubscriptionPaymentsQueryHandler(IAppDbContext db)
    : IRequestHandler<GetSubscriptionPaymentsQuery, Result<IReadOnlyList<SubscriptionPaymentDto>>>
{
    public async Task<Result<IReadOnlyList<SubscriptionPaymentDto>>> Handle(GetSubscriptionPaymentsQuery request, CancellationToken ct)
    {
        var q = db.SubscriptionPayments.Include(p => p.Client).AsQueryable();
        if (!string.IsNullOrWhiteSpace(request.Status)) q = q.Where(p => p.PaymentStatus == request.Status);
        if (request.ClientId.HasValue) q = q.Where(p => p.ClientId == request.ClientId);
        var list = await q.OrderByDescending(p => p.CreatedAt).Take(100).ToListAsync(ct);
        return Result<IReadOnlyList<SubscriptionPaymentDto>>.Success(list.Select(p =>
            new SubscriptionPaymentDto(p.Id, p.ClientId, p.Client.ClientName, p.SubscriptionId, p.Amount,
                p.Currency, p.PaymentMethod, p.PaymentReference, p.PaymentStatus, p.PaymentDate, p.CreatedAt)).ToList());
    }
}

public class CreateClientCommandHandler(IAppDbContext db, ICurrentUser currentUser, IPasswordHasher hasher)
    : IRequestHandler<CreateClientCommand, Result<ClientListItemDto>>
{
    public async Task<Result<ClientListItemDto>> Handle(CreateClientCommand command, CancellationToken ct)
    {
        var req = command.Request;
        if (string.IsNullOrWhiteSpace(req.ClientName) || string.IsNullOrWhiteSpace(req.Email))
            return Result<ClientListItemDto>.Failure("Client name and email are required.");

        var client = new Client
        {
            ClientName = req.ClientName.Trim(),
            CompanyName = req.CompanyName?.Trim(),
            ContactPerson = req.ContactPerson.Trim(),
            Email = req.Email.Trim(),
            Phone = req.Phone.Trim(),
            Address = req.Address,
            City = req.City,
            State = req.State,
            Country = req.Country,
            Pincode = req.Pincode,
            MaxGroups = req.MaxGroups ?? 10,
            MaxMembersPerGroup = req.MaxMembersPerGroup ?? 50,
            SubscriptionStatus = "trial",
            CreatedBy = currentUser.UserId ?? 1,
            Status = "active"
        };
        db.Clients.Add(client);
        await db.SaveChangesAsync(ct);

        if (!string.IsNullOrWhiteSpace(req.AdminUsername) && !string.IsNullOrWhiteSpace(req.AdminPassword))
        {
            db.ClientAdmins.Add(new ClientAdmin
            {
                ClientId = client.Id,
                Username = req.AdminUsername.Trim(),
                PasswordHash = hasher.Hash(req.AdminPassword),
                FullName = req.ContactPerson.Trim(),
                Email = req.Email.Trim()
            });
            await db.SaveChangesAsync(ct);
        }

        return Result<ClientListItemDto>.Success(new ClientListItemDto(
            client.Id, client.ClientName, client.CompanyName, client.ContactPerson,
            client.Email, client.Phone, client.Status, client.SubscriptionStatus,
            client.SubscriptionEndDate, 0, 0));
    }
}

public class UpdateClientCommandHandler(IAppDbContext db)
    : IRequestHandler<UpdateClientCommand, Result>
{
    public async Task<Result> Handle(UpdateClientCommand command, CancellationToken ct)
    {
        var c = await db.Clients.FirstOrDefaultAsync(x => x.Id == command.Id, ct);
        if (c is null) return Result.Failure("Client not found.");
        var req = command.Request;
        c.ClientName = req.ClientName.Trim();
        c.CompanyName = req.CompanyName?.Trim();
        c.ContactPerson = req.ContactPerson.Trim();
        c.Email = req.Email.Trim();
        c.Phone = req.Phone.Trim();
        c.Address = req.Address;
        c.City = req.City;
        c.State = req.State;
        c.Country = req.Country;
        c.Pincode = req.Pincode;
        c.MaxGroups = req.MaxGroups;
        c.MaxMembersPerGroup = req.MaxMembersPerGroup;
        c.Status = req.Status;
        c.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}

public class ToggleClientStatusCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<ToggleClientStatusCommand, Result<string>>
{
    public async Task<Result<string>> Handle(ToggleClientStatusCommand command, CancellationToken ct)
    {
        var c = await db.Clients.FirstOrDefaultAsync(x => x.Id == command.Id, ct);
        if (c is null) return Result<string>.Failure("Client not found.");
        var old = c.Status;
        c.Status = c.Status == "active" ? "inactive" : "active";
        c.UpdatedAt = DateTime.UtcNow;
        AuditWriter.Add(
            db, currentUser, $"client_{c.Status}",
            tableName: "clients", recordId: c.Id, clientId: c.Id,
            oldValues: old, newValues: c.Status);
        await db.SaveChangesAsync(ct);
        return Result<string>.Success(c.Status);
    }
}

public class SavePlanCommandHandler(IAppDbContext db, ICurrentUser currentUser)
    : IRequestHandler<SavePlanCommand, Result<PlanDto>>
{
    public async Task<Result<PlanDto>> Handle(SavePlanCommand command, CancellationToken ct)
    {
        var req = command.Request;
        SubscriptionPlan plan;
        if (command.Id.HasValue)
        {
            var existing = await db.SubscriptionPlans.FirstOrDefaultAsync(p => p.Id == command.Id, ct);
            if (existing is null) return Result<PlanDto>.Failure("Plan not found.");
            plan = existing;
        }
        else
        {
            plan = new SubscriptionPlan { CreatedBy = currentUser.UserId };
            db.SubscriptionPlans.Add(plan);
        }

        plan.PlanName = req.PlanName.Trim();
        plan.DurationMonths = req.DurationMonths;
        plan.Price = req.Price;
        plan.Description = req.Description;
        plan.FeaturesJson = JsonSerializer.Serialize(req.Features ?? []);
        plan.IsActive = req.IsActive;
        plan.IsPromotional = req.IsPromotional;
        plan.PromotionalDiscount = req.PromotionalDiscount;
        plan.MaxGroups = req.MaxGroups;
        plan.MaxMembersPerGroup = req.MaxMembersPerGroup;
        plan.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(ct);
        return Result<PlanDto>.Success(GetPlansQueryHandler.MapPlan(plan));
    }
}

public class DeletePlanCommandHandler(IAppDbContext db)
    : IRequestHandler<DeletePlanCommand, Result>
{
    public async Task<Result> Handle(DeletePlanCommand command, CancellationToken ct)
    {
        var plan = await db.SubscriptionPlans.FirstOrDefaultAsync(p => p.Id == command.Id, ct);
        if (plan is null) return Result.Failure("Plan not found.");
        plan.IsActive = false;
        plan.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}

public class AssignSubscriptionCommandHandler(IAppDbContext db)
    : IRequestHandler<AssignSubscriptionCommand, Result<ClientSubscriptionDto>>
{
    public async Task<Result<ClientSubscriptionDto>> Handle(AssignSubscriptionCommand command, CancellationToken ct)
    {
        var req = command.Request;
        var client = await db.Clients.FirstOrDefaultAsync(c => c.Id == req.ClientId, ct);
        var plan = await db.SubscriptionPlans.FirstOrDefaultAsync(p => p.Id == req.PlanId && p.IsActive, ct);
        if (client is null || plan is null) return Result<ClientSubscriptionDto>.Failure("Client or plan not found.");

        var start = DateOnly.FromDateTime(DateTime.UtcNow);
        var months = plan.DurationMonths > 0 ? plan.DurationMonths : 1;
        var end = start.AddMonths(months);
        var snapshot = JsonSerializer.Serialize(GetPlansQueryHandler.MapPlan(plan));

        var sub = new ClientSubscription
        {
            ClientId = req.ClientId,
            PlanId = req.PlanId,
            PlanSnapshotJson = snapshot,
            StartDate = start,
            EndDate = end,
            Status = "active",
            PaymentAmount = req.PaymentAmount,
            PaymentMethod = req.PaymentMethod,
            PaymentReference = req.PaymentReference,
            PaymentDate = DateTime.UtcNow
        };
        db.ClientSubscriptions.Add(sub);
        await db.SaveChangesAsync(ct);

        db.SubscriptionPayments.Add(new SubscriptionPayment
        {
            ClientId = req.ClientId,
            SubscriptionId = sub.Id,
            Amount = req.PaymentAmount,
            PaymentMethod = req.PaymentMethod,
            PaymentReference = req.PaymentReference,
            PaymentStatus = "completed",
            PaymentDate = DateTime.UtcNow
        });

        client.SubscriptionStatus = "active";
        client.SubscriptionEndDate = end;
        client.CurrentSubscriptionId = sub.Id;
        client.SubscriptionPlan = plan.PlanName;
        client.MaxGroups = plan.MaxGroups ?? client.MaxGroups;
        client.MaxMembersPerGroup = plan.MaxMembersPerGroup ?? client.MaxMembersPerGroup;
        client.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(ct);

        NotificationWriter.Add(
            db, "admin", null,
            "Subscription assigned",
            $"{client.ClientName} → {plan.PlanName} until {end:dd MMM yyyy}.",
            "success");
        foreach (var adminId in await db.ClientAdmins.Where(a => a.ClientId == client.Id && a.Status == "active").Select(a => a.Id).ToListAsync(ct))
        {
            NotificationWriter.Add(
                db, "admin", adminId,
                "Subscription activated",
                $"Plan {plan.PlanName} is active until {end:dd MMM yyyy}.",
                "success");
        }
        await db.SaveChangesAsync(ct);

        return Result<ClientSubscriptionDto>.Success(
            new ClientSubscriptionDto(sub.Id, plan.Id, plan.PlanName, start, end, sub.Status, sub.PaymentAmount));
    }
}

public class ExtendSubscriptionCommandHandler(IAppDbContext db)
    : IRequestHandler<ExtendSubscriptionCommand, Result>
{
    public async Task<Result> Handle(ExtendSubscriptionCommand command, CancellationToken ct)
    {
        var sub = await db.ClientSubscriptions.Include(s => s.Client).FirstOrDefaultAsync(s => s.Id == command.SubscriptionId, ct);
        if (sub is null) return Result.Failure("Subscription not found.");
        sub.EndDate = sub.EndDate.AddMonths(command.Request.Months);
        sub.Status = "active";
        sub.UpdatedAt = DateTime.UtcNow;
        sub.Client.SubscriptionEndDate = sub.EndDate;
        sub.Client.SubscriptionStatus = "active";
        sub.Client.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(ct);

        NotificationWriter.Add(
            db, "admin", null,
            "Subscription extended",
            $"{sub.Client.ClientName} extended by {command.Request.Months} month(s) to {sub.EndDate:dd MMM yyyy}.",
            "info");
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}

public class CancelSubscriptionCommandHandler(IAppDbContext db)
    : IRequestHandler<CancelSubscriptionCommand, Result>
{
    public async Task<Result> Handle(CancelSubscriptionCommand command, CancellationToken ct)
    {
        var sub = await db.ClientSubscriptions.Include(s => s.Client).FirstOrDefaultAsync(s => s.Id == command.SubscriptionId, ct);
        if (sub is null) return Result.Failure("Subscription not found.");
        sub.Status = "cancelled";
        sub.UpdatedAt = DateTime.UtcNow;
        sub.Client.SubscriptionStatus = "cancelled";
        sub.Client.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(ct);

        NotificationWriter.Add(
            db, "admin", null,
            "Subscription cancelled",
            $"{sub.Client.ClientName} subscription was cancelled.",
            "danger");
        foreach (var adminId in await db.ClientAdmins.Where(a => a.ClientId == sub.ClientId && a.Status == "active").Select(a => a.Id).ToListAsync(ct))
        {
            NotificationWriter.Add(
                db, "admin", adminId,
                "Subscription cancelled",
                "Your organisation subscription was cancelled. Contact support.",
                "danger");
        }
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}

public class MarkSubscriptionPaymentCommandHandler(IAppDbContext db)
    : IRequestHandler<MarkSubscriptionPaymentCommand, Result>
{
    public async Task<Result> Handle(MarkSubscriptionPaymentCommand command, CancellationToken ct)
    {
        var p = await db.SubscriptionPayments.FirstOrDefaultAsync(x => x.Id == command.PaymentId, ct);
        if (p is null) return Result.Failure("Payment not found.");
        p.PaymentStatus = "completed";
        p.PaymentDate = DateTime.UtcNow;
        p.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(ct);
        return Result.Success();
    }
}
