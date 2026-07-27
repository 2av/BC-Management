using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Domain.Entities;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Infrastructure.Persistence;

public class AppDbContext : DbContext, IAppDbContext
{
    private readonly ICurrentUser _currentUser;

    public AppDbContext(DbContextOptions<AppDbContext> options, ICurrentUser currentUser)
        : base(options)
    {
        _currentUser = currentUser;
    }

    public DbSet<Client> Clients => Set<Client>();
    public DbSet<SuperAdmin> SuperAdmins => Set<SuperAdmin>();
    public DbSet<AdminUser> AdminUsers => Set<AdminUser>();
    public DbSet<ClientAdmin> ClientAdmins => Set<ClientAdmin>();
    public DbSet<Member> Members => Set<Member>();
    public DbSet<BcGroup> BcGroups => Set<BcGroup>();
    public DbSet<GroupMember> GroupMembers => Set<GroupMember>();
    public DbSet<MonthlyBid> MonthlyBids => Set<MonthlyBid>();
    public DbSet<MemberPayment> MemberPayments => Set<MemberPayment>();
    public DbSet<MemberSummary> MemberSummaries => Set<MemberSummary>();
    public DbSet<MonthBiddingStatus> MonthBiddingStatuses => Set<MonthBiddingStatus>();
    public DbSet<MemberBid> MemberBids => Set<MemberBid>();
    public DbSet<GroupMonthChart> GroupMonthCharts => Set<GroupMonthChart>();
    public DbSet<RandomPick> RandomPicks => Set<RandomPick>();
    public DbSet<SubscriptionPlan> SubscriptionPlans => Set<SubscriptionPlan>();
    public DbSet<ClientSubscription> ClientSubscriptions => Set<ClientSubscription>();
    public DbSet<SubscriptionPayment> SubscriptionPayments => Set<SubscriptionPayment>();
    public DbSet<PaymentConfig> PaymentConfigs => Set<PaymentConfig>();
    public DbSet<SystemSetting> SystemSettings => Set<SystemSetting>();
    public DbSet<Notification> Notifications => Set<Notification>();
    public DbSet<MemberPushToken> MemberPushTokens => Set<MemberPushToken>();
    public DbSet<AuditLog> AuditLogs => Set<AuditLog>();

    private static string ToDbEnum(Enum value)
    {
        var name = value.ToString().ToLowerInvariant();
        return name == "notstarted" ? "not_started" : name;
    }

    private static T FromDbEnum<T>(string value) where T : struct, Enum
        => Enum.Parse<T>(value.Replace("_", ""), ignoreCase: true);

    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        modelBuilder.Entity<Client>(e =>
        {
            e.ToTable("clients");
            e.HasKey(x => x.Id);
            e.Property(x => x.CompanyName).HasMaxLength(200);
            e.Property(x => x.SubscriptionStatus).HasMaxLength(20);
        });

        modelBuilder.Entity<SuperAdmin>(e =>
        {
            e.ToTable("super_admins");
            e.HasKey(x => x.Id);
            e.HasIndex(x => x.Username).IsUnique();
            e.Property(x => x.Username).HasMaxLength(50).IsRequired();
            e.Property(x => x.PasswordHash).HasColumnName("password").HasMaxLength(255).IsRequired();
        });

        modelBuilder.Entity<AdminUser>(e =>
        {
            e.ToTable("admin_users");
            e.HasKey(x => x.Id);
            e.HasIndex(x => x.Username).IsUnique();
            e.Property(x => x.PasswordHash).HasColumnName("password").HasMaxLength(255).IsRequired();
        });

        modelBuilder.Entity<ClientAdmin>(e =>
        {
            e.ToTable("client_admins");
            e.HasKey(x => x.Id);
            e.HasIndex(x => x.Username).IsUnique();
            e.Property(x => x.PasswordHash).HasColumnName("password").HasMaxLength(255).IsRequired();
            e.HasOne(x => x.Client).WithMany(c => c.Admins).HasForeignKey(x => x.ClientId);
        });

        modelBuilder.Entity<Member>(e =>
        {
            e.ToTable("members");
            e.HasKey(x => x.Id);
            e.HasIndex(x => x.Username).IsUnique();
            e.Property(x => x.MemberName).HasMaxLength(100).IsRequired();
            e.Property(x => x.PasswordHash).HasColumnName("password");
            e.Property(x => x.MustChangePassword).HasColumnName("must_change_password");
            e.Property(x => x.WonAmount).HasPrecision(10, 2);
        });

        modelBuilder.Entity<BcGroup>(e =>
        {
            e.ToTable("bc_groups");
            e.HasKey(x => x.Id);
            e.Property(x => x.GroupName).HasMaxLength(100).IsRequired();
            e.Property(x => x.MonthlyContribution).HasPrecision(10, 2);
            e.Property(x => x.TotalMonthlyCollection).HasPrecision(10, 2);
            e.Property(x => x.Status).HasConversion(
                v => ToDbEnum(v),
                v => FromDbEnum<GroupStatus>(v));
            e.HasOne(x => x.Client).WithMany(c => c.Groups).HasForeignKey(x => x.ClientId);
            e.HasOne(x => x.OrganiserMember).WithMany().HasForeignKey(x => x.OrganiserMemberId);
            e.HasOne(x => x.OrganiserGroupMember).WithMany().HasForeignKey(x => x.OrganiserGroupMemberId);
            e.Property(x => x.BoliStepAmount).HasPrecision(10, 2);
            e.HasQueryFilter(g => _currentUser.IsSuperAdmin
                || !_currentUser.ClientId.HasValue
                || g.ClientId == _currentUser.ClientId);
        });

        modelBuilder.Entity<GroupMonthChart>(e =>
        {
            e.ToTable("group_month_charts");
            e.HasKey(x => x.Id);
            e.HasIndex(x => new { x.GroupId, x.MonthNumber }).IsUnique();
            e.Property(x => x.RandomAmount).HasPrecision(10, 2);
            e.Property(x => x.BoliStartAmount).HasPrecision(10, 2);
            e.HasOne(x => x.Group).WithMany(g => g.MonthCharts).HasForeignKey(x => x.GroupId);
            e.HasQueryFilter(m => _currentUser.IsSuperAdmin
                || !_currentUser.ClientId.HasValue
                || m.ClientId == _currentUser.ClientId);
        });

        modelBuilder.Entity<GroupMember>(e =>
        {
            e.ToTable("group_members");
            e.HasKey(x => x.Id);
            e.Ignore(x => x.DisplayName);
            e.Property(x => x.HandLabel).HasMaxLength(50);
            // Multi-hand: same member may have multiple seats; only member_number is unique per group.
            e.HasIndex(x => new { x.GroupId, x.MemberId });
            e.HasIndex(x => new { x.GroupId, x.MemberNumber }).IsUnique();
            e.HasOne(x => x.Group).WithMany(g => g.Members).HasForeignKey(x => x.GroupId);
            e.HasOne(x => x.Member).WithMany(m => m.GroupMemberships).HasForeignKey(x => x.MemberId);
        });

        modelBuilder.Entity<MonthlyBid>(e =>
        {
            e.ToTable("monthly_bids");
            e.HasKey(x => x.Id);
            e.HasIndex(x => new { x.GroupId, x.MonthNumber }).IsUnique();
            e.Property(x => x.BidAmount).HasPrecision(10, 2);
            e.Property(x => x.NetPayable).HasPrecision(10, 2);
            e.Property(x => x.GainPerMember).HasPrecision(10, 2);
            e.Property(x => x.IsBid).HasConversion(
                v => v ? "Yes" : "No",
                v => string.Equals(v, "Yes", StringComparison.OrdinalIgnoreCase));
            e.HasOne(x => x.Group).WithMany(g => g.MonthlyBids).HasForeignKey(x => x.GroupId);
            e.HasOne(x => x.TakenByMember).WithMany().HasForeignKey(x => x.TakenByMemberId);
            e.HasOne(x => x.TakenByGroupMember).WithMany().HasForeignKey(x => x.TakenByGroupMemberId);
            e.HasQueryFilter(m => _currentUser.IsSuperAdmin
                || !_currentUser.ClientId.HasValue
                || m.ClientId == _currentUser.ClientId);
        });

        modelBuilder.Entity<MemberPayment>(e =>
        {
            e.ToTable("member_payments");
            e.HasKey(x => x.Id);
            e.HasIndex(x => new { x.GroupId, x.GroupMemberId, x.MonthNumber }).IsUnique();
            e.Property(x => x.PaymentAmount).HasPrecision(10, 2);
            e.Property(x => x.PaymentStatus).HasConversion(
                v => ToDbEnum(v),
                v => FromDbEnum<PaymentStatus>(v));
            e.HasOne(x => x.Group).WithMany().HasForeignKey(x => x.GroupId);
            e.HasOne(x => x.Member).WithMany().HasForeignKey(x => x.MemberId);
            e.HasOne(x => x.GroupMember).WithMany().HasForeignKey(x => x.GroupMemberId);
            e.HasQueryFilter(m => _currentUser.IsSuperAdmin
                || !_currentUser.ClientId.HasValue
                || m.ClientId == _currentUser.ClientId);
        });

        modelBuilder.Entity<MemberSummary>(e =>
        {
            e.ToTable("member_summary");
            e.HasKey(x => x.Id);
            e.HasIndex(x => new { x.GroupId, x.GroupMemberId }).IsUnique();
            e.Property(x => x.TotalPaid).HasPrecision(10, 2);
            e.Property(x => x.GivenAmount).HasPrecision(10, 2);
            e.Property(x => x.Profit).HasPrecision(10, 2);
            e.HasOne(x => x.Group).WithMany().HasForeignKey(x => x.GroupId);
            e.HasOne(x => x.Member).WithMany().HasForeignKey(x => x.MemberId);
            e.HasOne(x => x.GroupMember).WithMany().HasForeignKey(x => x.GroupMemberId);
            e.HasQueryFilter(m => _currentUser.IsSuperAdmin
                || !_currentUser.ClientId.HasValue
                || m.ClientId == _currentUser.ClientId);
        });

        modelBuilder.Entity<MonthBiddingStatus>(e =>
        {
            e.ToTable("month_bidding_status");
            e.HasKey(x => x.Id);
            e.HasIndex(x => new { x.GroupId, x.MonthNumber }).IsUnique();
            e.Property(x => x.BiddingStatus).HasConversion(
                v => ToDbEnum(v),
                v => FromDbEnum<BiddingStatus>(v));
            e.Property(x => x.MinimumBidAmount).HasPrecision(10, 2);
            e.Property(x => x.MaximumBidAmount).HasPrecision(10, 2);
            e.Property(x => x.WinningBidAmount).HasPrecision(10, 2);
            e.Property(x => x.PaymentDueAmount).HasPrecision(10, 2);
            e.HasOne(x => x.Group).WithMany().HasForeignKey(x => x.GroupId);
            e.HasOne(x => x.WinnerMember).WithMany().HasForeignKey(x => x.WinnerMemberId);
            e.HasOne(x => x.WinnerGroupMember).WithMany().HasForeignKey(x => x.WinnerGroupMemberId);
            e.HasQueryFilter(m => _currentUser.IsSuperAdmin
                || !_currentUser.ClientId.HasValue
                || m.ClientId == _currentUser.ClientId);
        });

        modelBuilder.Entity<MemberBid>(e =>
        {
            e.ToTable("member_bids");
            e.HasKey(x => x.Id);
            e.Property(x => x.BidAmount).HasPrecision(10, 2);
            e.HasOne(x => x.Group).WithMany().HasForeignKey(x => x.GroupId);
            e.HasOne(x => x.Member).WithMany().HasForeignKey(x => x.MemberId);
            e.HasOne(x => x.GroupMember).WithMany().HasForeignKey(x => x.GroupMemberId);
            e.HasQueryFilter(m => _currentUser.IsSuperAdmin
                || !_currentUser.ClientId.HasValue
                || m.ClientId == _currentUser.ClientId);
        });

        modelBuilder.Entity<RandomPick>(e =>
        {
            e.ToTable("random_picks");
            e.HasKey(x => x.Id);
            e.Ignore(x => x.ClientId); // legacy table may not have client_id
            e.Ignore(x => x.EffectiveMemberId);
            e.Ignore(x => x.EffectiveGroupMemberId);
            e.HasIndex(x => new { x.GroupId, x.MonthNumber }).IsUnique();
            e.HasOne(x => x.Group).WithMany().HasForeignKey(x => x.GroupId);
            e.HasOne(x => x.SelectedMember).WithMany().HasForeignKey(x => x.SelectedMemberId);
            e.HasOne(x => x.SelectedGroupMember).WithMany().HasForeignKey(x => x.SelectedGroupMemberId);
            e.HasOne(x => x.AdminOverrideMember).WithMany().HasForeignKey(x => x.AdminOverrideMemberId);
            e.HasOne(x => x.AdminOverrideGroupMember).WithMany().HasForeignKey(x => x.AdminOverrideGroupMemberId);
        });

        modelBuilder.Entity<SubscriptionPlan>(e =>
        {
            e.ToTable("subscription_plans");
            e.HasKey(x => x.Id);
            e.Property(x => x.PlanName).HasMaxLength(100).IsRequired();
            e.Property(x => x.Price).HasPrecision(10, 2);
            e.Property(x => x.PromotionalDiscount).HasPrecision(5, 2);
            e.Property(x => x.FeaturesJson).HasColumnName("features");
        });

        modelBuilder.Entity<ClientSubscription>(e =>
        {
            e.ToTable("client_subscriptions");
            e.HasKey(x => x.Id);
            e.Property(x => x.PlanSnapshotJson).HasColumnName("plan_snapshot").IsRequired();
            e.Property(x => x.PaymentAmount).HasPrecision(10, 2);
            e.HasOne(x => x.Client).WithMany().HasForeignKey(x => x.ClientId);
            e.HasOne(x => x.Plan).WithMany().HasForeignKey(x => x.PlanId);
        });

        modelBuilder.Entity<SubscriptionPayment>(e =>
        {
            e.ToTable("subscription_payments");
            e.HasKey(x => x.Id);
            e.Property(x => x.Amount).HasPrecision(10, 2);
            e.HasOne(x => x.Client).WithMany().HasForeignKey(x => x.ClientId);
            e.HasOne(x => x.Subscription).WithMany().HasForeignKey(x => x.SubscriptionId);
        });

        modelBuilder.Entity<PaymentConfig>(e =>
        {
            e.ToTable("payment_config");
            e.HasKey(x => x.Id);
            e.HasIndex(x => new { x.ClientId, x.ConfigKey }).IsUnique();
            e.Property(x => x.ConfigKey).HasMaxLength(50).IsRequired();
        });

        modelBuilder.Entity<SystemSetting>(e =>
        {
            e.ToTable("system_settings");
            e.HasKey(x => x.Id);
            e.HasIndex(x => x.SettingKey).IsUnique();
            e.Property(x => x.SettingKey).HasMaxLength(100).IsRequired();
            e.Property(x => x.SettingType).HasMaxLength(20);
            e.Property(x => x.Category).HasMaxLength(50);
        });

        modelBuilder.Entity<Notification>(e =>
        {
            e.ToTable("notifications");
            e.HasKey(x => x.Id);
            e.Property(x => x.UserType).HasMaxLength(20).IsRequired();
            e.Property(x => x.Title).HasMaxLength(255).IsRequired();
            e.Property(x => x.Type).HasMaxLength(20);
        });

        modelBuilder.Entity<MemberPushToken>(e =>
        {
            e.ToTable("member_push_tokens");
            e.HasKey(x => x.Id);
            e.HasIndex(x => x.Token).IsUnique();
            e.HasIndex(x => x.MemberId);
            e.Property(x => x.Token).HasMaxLength(512).IsRequired();
            e.Property(x => x.Platform).HasMaxLength(20).IsRequired();
        });

        modelBuilder.Entity<AuditLog>(e =>
        {
            e.ToTable("audit_log");
            e.HasKey(x => x.Id);
            e.Property(x => x.UserType).HasMaxLength(30).IsRequired();
            e.Property(x => x.Action).HasMaxLength(100).IsRequired();
            e.Property(x => x.TableName).HasMaxLength(100);
            e.Property(x => x.IpAddress).HasMaxLength(45);
        });
    }
}
