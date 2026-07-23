using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Domain.Entities;

namespace MitraNiidhi.Application.Common.Interfaces;

public interface IAppDbContext
{
    DbSet<Client> Clients { get; }
    DbSet<SuperAdmin> SuperAdmins { get; }
    DbSet<AdminUser> AdminUsers { get; }
    DbSet<ClientAdmin> ClientAdmins { get; }
    DbSet<Member> Members { get; }
    DbSet<BcGroup> BcGroups { get; }
    DbSet<GroupMember> GroupMembers { get; }
    DbSet<MonthlyBid> MonthlyBids { get; }
    DbSet<MemberPayment> MemberPayments { get; }
    DbSet<MemberSummary> MemberSummaries { get; }
    DbSet<MonthBiddingStatus> MonthBiddingStatuses { get; }
    DbSet<MemberBid> MemberBids { get; }
    DbSet<RandomPick> RandomPicks { get; }
    DbSet<SubscriptionPlan> SubscriptionPlans { get; }
    DbSet<ClientSubscription> ClientSubscriptions { get; }
    DbSet<SubscriptionPayment> SubscriptionPayments { get; }
    DbSet<PaymentConfig> PaymentConfigs { get; }
    DbSet<SystemSetting> SystemSettings { get; }
    DbSet<Notification> Notifications { get; }
    DbSet<AuditLog> AuditLogs { get; }

    Task<int> SaveChangesAsync(CancellationToken cancellationToken = default);
}
