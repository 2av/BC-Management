using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Infrastructure.Persistence;

namespace MitraNiidhi.Infrastructure.Persistence;

public class SchemaMigrationService(AppDbContext db) : ISchemaMigrationService
{
    private sealed record ExpectedColumn(string Name, string Definition);
    private sealed record ExpectedTable(string Name, string CreateSql, ExpectedColumn[] Columns);

    private static readonly ExpectedTable[] ExpectedTables =
    [
        new("subscription_plans", """
            CREATE TABLE IF NOT EXISTS subscription_plans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                plan_name VARCHAR(100) NOT NULL,
                duration_months INT NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                currency VARCHAR(10) DEFAULT 'INR',
                description TEXT,
                features JSON,
                is_active TINYINT(1) DEFAULT 1,
                is_promotional TINYINT(1) DEFAULT 0,
                promotional_discount DECIMAL(5,2) DEFAULT 0.00,
                max_groups INT NULL,
                max_members_per_group INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NULL,
                INDEX idx_active (is_active),
                INDEX idx_promotional (is_promotional),
                INDEX idx_duration (duration_months)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """,
            [
                new("plan_name", "VARCHAR(100) NOT NULL"),
                new("duration_months", "INT NOT NULL"),
                new("price", "DECIMAL(10,2) NOT NULL"),
                new("currency", "VARCHAR(10) DEFAULT 'INR'"),
                new("description", "TEXT NULL"),
                new("features", "JSON NULL"),
                new("is_active", "TINYINT(1) DEFAULT 1"),
                new("is_promotional", "TINYINT(1) DEFAULT 0"),
                new("promotional_discount", "DECIMAL(5,2) DEFAULT 0.00"),
                new("max_groups", "INT NULL"),
                new("max_members_per_group", "INT NULL"),
                new("created_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"),
                new("updated_at", "TIMESTAMP NULL DEFAULT NULL"),
                new("created_by", "INT NULL")
            ]),
        new("client_subscriptions", """
            CREATE TABLE IF NOT EXISTS client_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                plan_id INT NOT NULL,
                plan_snapshot JSON NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                status VARCHAR(20) DEFAULT 'active',
                payment_amount DECIMAL(10,2) NOT NULL,
                payment_method VARCHAR(50) NULL,
                payment_reference VARCHAR(100) NULL,
                payment_date TIMESTAMP NULL,
                auto_renewal TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_client_status (client_id, status),
                INDEX idx_end_date (end_date),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """,
            [
                new("client_id", "INT NOT NULL"),
                new("plan_id", "INT NOT NULL"),
                new("plan_snapshot", "JSON NOT NULL"),
                new("start_date", "DATE NOT NULL"),
                new("end_date", "DATE NOT NULL"),
                new("status", "VARCHAR(20) DEFAULT 'active'"),
                new("payment_amount", "DECIMAL(10,2) NOT NULL"),
                new("payment_method", "VARCHAR(50) NULL"),
                new("payment_reference", "VARCHAR(100) NULL"),
                new("payment_date", "TIMESTAMP NULL"),
                new("auto_renewal", "TINYINT(1) DEFAULT 0"),
                new("created_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"),
                new("updated_at", "TIMESTAMP NULL DEFAULT NULL")
            ]),
        new("subscription_payments", """
            CREATE TABLE IF NOT EXISTS subscription_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                subscription_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(10) DEFAULT 'INR',
                payment_method VARCHAR(50) NULL,
                payment_reference VARCHAR(100) NULL,
                payment_status VARCHAR(20) DEFAULT 'pending',
                payment_gateway VARCHAR(50) NULL,
                gateway_transaction_id VARCHAR(100) NULL,
                payment_date TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_client_status (client_id, payment_status),
                INDEX idx_reference (payment_reference)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """,
            [
                new("client_id", "INT NOT NULL"),
                new("subscription_id", "INT NOT NULL"),
                new("amount", "DECIMAL(10,2) NOT NULL"),
                new("currency", "VARCHAR(10) DEFAULT 'INR'"),
                new("payment_method", "VARCHAR(50) NULL"),
                new("payment_reference", "VARCHAR(100) NULL"),
                new("payment_status", "VARCHAR(20) DEFAULT 'pending'"),
                new("payment_gateway", "VARCHAR(50) NULL"),
                new("gateway_transaction_id", "VARCHAR(100) NULL"),
                new("payment_date", "TIMESTAMP NULL"),
                new("created_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"),
                new("updated_at", "TIMESTAMP NULL DEFAULT NULL")
            ]),
        new("subscription_notifications", """
            CREATE TABLE IF NOT EXISTS subscription_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                subscription_id INT NOT NULL,
                notification_type VARCHAR(50) NOT NULL,
                notification_date DATE NOT NULL,
                days_before_expiry INT NULL,
                message TEXT NULL,
                is_sent TINYINT(1) DEFAULT 0,
                sent_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_client_date (client_id, notification_date),
                INDEX idx_sent (is_sent)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """,
            [
                new("client_id", "INT NOT NULL"),
                new("subscription_id", "INT NOT NULL"),
                new("notification_type", "VARCHAR(50) NOT NULL"),
                new("notification_date", "DATE NOT NULL"),
                new("days_before_expiry", "INT NULL"),
                new("message", "TEXT NULL"),
                new("is_sent", "TINYINT(1) DEFAULT 0"),
                new("sent_at", "TIMESTAMP NULL"),
                new("created_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP")
            ]),
        new("payment_config", """
            CREATE TABLE IF NOT EXISTS payment_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL DEFAULT 1,
                config_key VARCHAR(50) NOT NULL,
                config_value TEXT NULL,
                description TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_config_per_client (client_id, config_key),
                INDEX idx_payment_config_client (client_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """,
            [
                new("client_id", "INT NOT NULL DEFAULT 1"),
                new("config_key", "VARCHAR(50) NOT NULL"),
                new("config_value", "TEXT NULL"),
                new("description", "TEXT NULL"),
                new("created_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"),
                new("updated_at", "TIMESTAMP NULL DEFAULT NULL")
            ]),
        new("system_settings", """
            CREATE TABLE IF NOT EXISTS system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT NULL,
                setting_type VARCHAR(20) DEFAULT 'text',
                description TEXT NULL,
                category VARCHAR(50) DEFAULT 'general',
                is_public TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_setting_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """,
            [
                new("setting_key", "VARCHAR(100) NOT NULL"),
                new("setting_value", "TEXT NULL"),
                new("setting_type", "VARCHAR(20) DEFAULT 'text'"),
                new("description", "TEXT NULL"),
                new("category", "VARCHAR(50) DEFAULT 'general'"),
                new("is_public", "TINYINT(1) DEFAULT 0"),
                new("created_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"),
                new("updated_at", "TIMESTAMP NULL DEFAULT NULL")
            ]),
        new("notifications", """
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_type VARCHAR(20) NOT NULL,
                user_id INT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(20) DEFAULT 'info',
                is_read TINYINT(1) DEFAULT 0,
                read_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_type_read (user_type, is_read),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """,
            [
                new("user_type", "VARCHAR(20) NOT NULL"),
                new("user_id", "INT NULL"),
                new("title", "VARCHAR(255) NOT NULL"),
                new("message", "TEXT NOT NULL"),
                new("type", "VARCHAR(20) DEFAULT 'info'"),
                new("is_read", "TINYINT(1) DEFAULT 0"),
                new("read_at", "TIMESTAMP NULL"),
                new("created_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP")
            ]),
        new("random_picks", """
            CREATE TABLE IF NOT EXISTS random_picks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_id INT NOT NULL,
                month_number INT NOT NULL,
                selected_member_id INT NOT NULL,
                admin_override_member_id INT NULL,
                admin_override_by INT NULL,
                picked_by INT NULL,
                picked_by_type VARCHAR(20) NOT NULL,
                picked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_random_pick_per_month (group_id, month_number),
                INDEX idx_group_month (group_id, month_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """,
            [
                new("group_id", "INT NOT NULL"),
                new("month_number", "INT NOT NULL"),
                new("selected_member_id", "INT NOT NULL"),
                new("admin_override_member_id", "INT NULL"),
                new("admin_override_by", "INT NULL"),
                new("picked_by", "INT NULL"),
                new("picked_by_type", "VARCHAR(20) NOT NULL"),
                new("picked_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"),
                new("created_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"),
                new("updated_at", "TIMESTAMP NULL DEFAULT NULL")
            ]),
        new("clients", """
            CREATE TABLE IF NOT EXISTS clients (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_name VARCHAR(200) NOT NULL,
                company_name VARCHAR(200) NULL,
                contact_person VARCHAR(200) NOT NULL,
                email VARCHAR(200) NOT NULL,
                phone VARCHAR(50) NOT NULL,
                address TEXT NULL,
                city VARCHAR(100) NULL,
                state VARCHAR(100) NULL,
                country VARCHAR(100) NULL,
                pincode VARCHAR(20) NULL,
                status VARCHAR(20) DEFAULT 'active',
                subscription_plan VARCHAR(50) DEFAULT 'basic',
                subscription_status VARCHAR(20) DEFAULT 'trial',
                subscription_end_date DATE NULL,
                current_subscription_id INT NULL,
                max_groups INT NULL,
                max_members_per_group INT NULL,
                created_by INT NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """,
            [
                new("client_name", "VARCHAR(200) NOT NULL"),
                new("company_name", "VARCHAR(200) NULL"),
                new("contact_person", "VARCHAR(200) NOT NULL"),
                new("email", "VARCHAR(200) NOT NULL"),
                new("phone", "VARCHAR(50) NOT NULL"),
                new("address", "TEXT NULL"),
                new("city", "VARCHAR(100) NULL"),
                new("state", "VARCHAR(100) NULL"),
                new("country", "VARCHAR(100) NULL"),
                new("pincode", "VARCHAR(20) NULL"),
                new("status", "VARCHAR(20) DEFAULT 'active'"),
                new("subscription_plan", "VARCHAR(50) DEFAULT 'basic'"),
                new("subscription_status", "VARCHAR(20) DEFAULT 'trial'"),
                new("subscription_end_date", "DATE NULL"),
                new("current_subscription_id", "INT NULL"),
                new("max_groups", "INT NULL"),
                new("max_members_per_group", "INT NULL"),
                new("created_by", "INT NOT NULL DEFAULT 1"),
                new("created_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"),
                new("updated_at", "TIMESTAMP NULL DEFAULT NULL")
            ]),
        new("super_admins", """
            CREATE TABLE IF NOT EXISTS super_admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(200) NOT NULL,
                email VARCHAR(200) NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """,
            [
                new("username", "VARCHAR(50) NOT NULL"),
                new("password", "VARCHAR(255) NOT NULL"),
                new("full_name", "VARCHAR(200) NOT NULL"),
                new("email", "VARCHAR(200) NULL"),
                new("status", "VARCHAR(20) DEFAULT 'active'"),
                new("created_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP")
            ]),
        new("client_admins", """
            CREATE TABLE IF NOT EXISTS client_admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                username VARCHAR(50) NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(200) NOT NULL,
                email VARCHAR(200) NULL,
                phone VARCHAR(50) NULL,
                status VARCHAR(20) DEFAULT 'active',
                role VARCHAR(50) DEFAULT 'admin',
                last_login TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_username (username),
                INDEX idx_client (client_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """,
            [
                new("client_id", "INT NOT NULL"),
                new("username", "VARCHAR(50) NOT NULL"),
                new("password", "VARCHAR(255) NOT NULL"),
                new("full_name", "VARCHAR(200) NOT NULL"),
                new("email", "VARCHAR(200) NULL"),
                new("phone", "VARCHAR(50) NULL"),
                new("status", "VARCHAR(20) DEFAULT 'active'"),
                new("role", "VARCHAR(50) DEFAULT 'admin'"),
                new("last_login", "TIMESTAMP NULL"),
                new("created_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP")
            ]),
        new("audit_log", """
            CREATE TABLE IF NOT EXISTS audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NULL,
                user_type VARCHAR(30) NOT NULL,
                user_id INT NOT NULL,
                action VARCHAR(100) NOT NULL,
                table_name VARCHAR(100) NULL,
                record_id INT NULL,
                old_values TEXT NULL,
                new_values TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_created (created_at),
                INDEX idx_client (client_id),
                INDEX idx_user (user_type, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """,
            [
                new("client_id", "INT NULL"),
                new("user_type", "VARCHAR(30) NOT NULL"),
                new("user_id", "INT NOT NULL"),
                new("action", "VARCHAR(100) NOT NULL"),
                new("table_name", "VARCHAR(100) NULL"),
                new("record_id", "INT NULL"),
                new("old_values", "TEXT NULL"),
                new("new_values", "TEXT NULL"),
                new("ip_address", "VARCHAR(45) NULL"),
                new("user_agent", "TEXT NULL"),
                new("created_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP")
            ])
    ];

    // Extra column patches for existing core tables that may predate multi-tenant.
    private static readonly (string Table, ExpectedColumn Column)[] ExtraColumns =
    [
        ("bc_groups", new("client_id", "INT NULL")),
        ("monthly_bids", new("client_id", "INT NULL")),
        ("member_payments", new("client_id", "INT NULL")),
        ("member_summary", new("client_id", "INT NULL")),
        ("member_bids", new("client_id", "INT NULL")),
        ("month_bidding_status", new("client_id", "INT NULL")),
        ("random_picks", new("admin_override_member_id", "INT NULL")),
        ("random_picks", new("admin_override_by", "INT NULL")),
        ("system_settings", new("setting_type", "VARCHAR(20) DEFAULT 'text'")),
        ("system_settings", new("category", "VARCHAR(50) DEFAULT 'general'")),
        ("system_settings", new("is_public", "TINYINT(1) DEFAULT 0")),
        ("payment_config", new("client_id", "INT NOT NULL DEFAULT 1")),
        ("clients", new("subscription_status", "VARCHAR(20) DEFAULT 'trial'")),
        ("clients", new("subscription_end_date", "DATE NULL")),
        ("clients", new("current_subscription_id", "INT NULL")),
        ("group_members", new("hand_label", "VARCHAR(50) NULL")),
        ("monthly_bids", new("taken_by_group_member_id", "INT NULL")),
        ("month_bidding_status", new("winner_group_member_id", "INT NULL")),
        ("member_bids", new("group_member_id", "INT NULL")),
        ("member_payments", new("group_member_id", "INT NULL")),
        ("member_summary", new("group_member_id", "INT NULL")),
        ("random_picks", new("selected_group_member_id", "INT NULL")),
        ("random_picks", new("admin_override_group_member_id", "INT NULL"))
    ];

    public async Task<SchemaCheckResultDto> CheckAsync(CancellationToken cancellationToken = default)
    {
        var issues = new List<SchemaIssueDto>();
        var notes = new List<string>();

        foreach (var table in ExpectedTables)
        {
            if (!await TableExistsAsync(table.Name, cancellationToken))
            {
                issues.Add(new SchemaIssueDto("table", table.Name, $"Missing table `{table.Name}`.", true));
                continue;
            }

            foreach (var col in table.Columns)
            {
                if (!await ColumnExistsAsync(table.Name, col.Name, cancellationToken))
                    issues.Add(new SchemaIssueDto("column", $"{table.Name}.{col.Name}", $"Missing column `{col.Name}` on `{table.Name}`.", true));
            }
        }

        foreach (var (table, column) in ExtraColumns)
        {
            if (!await TableExistsAsync(table, cancellationToken)) continue;
            if (!await ColumnExistsAsync(table, column.Name, cancellationToken))
                issues.Add(new SchemaIssueDto("column", $"{table}.{column.Name}", $"Missing column `{column.Name}` on `{table}`.", true));
        }

        if (await TableExistsAsync("subscription_plans", cancellationToken))
        {
            var planCount = await ScalarLongAsync("SELECT COUNT(*) FROM subscription_plans", cancellationToken);
            if (planCount == 0)
            {
                issues.Add(new SchemaIssueDto("seed", "subscription_plans", "No subscription plans found — defaults can be seeded.", true));
                notes.Add("subscription_plans is empty — migrate will seed default plans.");
            }
        }

        if (await TableExistsAsync("payment_config", cancellationToken))
        {
            var cfgCount = await ScalarLongAsync("SELECT COUNT(*) FROM payment_config", cancellationToken);
            if (cfgCount == 0)
            {
                issues.Add(new SchemaIssueDto("seed", "payment_config", "No payment config keys found — defaults can be seeded.", true));
                notes.Add("payment_config is empty — migrate will seed default UPI/QR keys.");
            }
        }

        if (await TableExistsAsync("system_settings", cancellationToken))
        {
            var settingsCount = await ScalarLongAsync("SELECT COUNT(*) FROM system_settings", cancellationToken);
            if (settingsCount == 0)
            {
                issues.Add(new SchemaIssueDto("seed", "system_settings", "No system settings found — defaults can be seeded.", true));
                notes.Add("system_settings is empty — migrate will seed defaults.");
            }
        }

        return new SchemaCheckResultDto(issues.Count == 0, issues, notes);
    }

    public async Task<SchemaMigrateResultDto> MigrateAsync(CancellationToken cancellationToken = default)
    {
        var applied = new List<string>();
        var skipped = new List<string>();
        var errors = new List<string>();

        foreach (var table in ExpectedTables)
        {
            try
            {
                if (!await TableExistsAsync(table.Name, cancellationToken))
                {
                    await ExecuteAsync(table.CreateSql, cancellationToken);
                    applied.Add($"Created table `{table.Name}`");
                }
                else
                {
                    skipped.Add($"Table `{table.Name}` already exists");
                }

                foreach (var col in table.Columns)
                {
                    if (await ColumnExistsAsync(table.Name, col.Name, cancellationToken))
                    {
                        skipped.Add($"Column `{table.Name}.{col.Name}` already exists");
                        continue;
                    }

                    await ExecuteAsync($"ALTER TABLE `{table.Name}` ADD COLUMN `{col.Name}` {col.Definition}", cancellationToken);
                    applied.Add($"Added column `{table.Name}.{col.Name}`");
                }
            }
            catch (Exception ex)
            {
                errors.Add($"{table.Name}: {ex.Message}");
            }
        }

        foreach (var (table, column) in ExtraColumns)
        {
            try
            {
                if (!await TableExistsAsync(table, cancellationToken)) continue;
                if (await ColumnExistsAsync(table, column.Name, cancellationToken))
                {
                    skipped.Add($"Column `{table}.{column.Name}` already exists");
                    continue;
                }

                await ExecuteAsync($"ALTER TABLE `{table}` ADD COLUMN `{column.Name}` {column.Definition}", cancellationToken);
                applied.Add($"Added column `{table}.{column.Name}`");
            }
            catch (Exception ex)
            {
                errors.Add($"{table}.{column.Name}: {ex.Message}");
            }
        }

        try
        {
            await ApplyMultiHandSeatMigrationAsync(applied, skipped, cancellationToken);
        }
        catch (Exception ex)
        {
            errors.Add($"Multi-hand seats: {ex.Message}");
        }

        try
        {
            await SeedDefaultsAsync(applied, cancellationToken);
        }
        catch (Exception ex)
        {
            errors.Add($"Seed: {ex.Message}");
        }

        return new SchemaMigrateResultDto(errors.Count == 0, applied.Count, applied, skipped, errors);
    }

    private async Task ApplyMultiHandSeatMigrationAsync(
        List<string> applied, List<string> skipped, CancellationToken ct)
    {
        if (!await TableExistsAsync("group_members", ct)) return;

        // Drop unique (group_id, member_id) if present so one login can hold multiple seats.
        var idxRows = await db.Database.SqlQueryRaw<string>("""
            SELECT INDEX_NAME AS Value
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'group_members'
              AND NON_UNIQUE = 0
              AND INDEX_NAME <> 'PRIMARY'
              AND COLUMN_NAME IN ('group_id', 'member_id')
            GROUP BY INDEX_NAME
            HAVING COUNT(DISTINCT COLUMN_NAME) = 2
               AND SUM(COLUMN_NAME IN ('group_id', 'member_id')) = 2
            """).ToListAsync(ct);

        foreach (var idx in idxRows.Distinct())
        {
            await ExecuteAsync($"ALTER TABLE `group_members` DROP INDEX `{idx}`", ct);
            applied.Add($"Dropped unique index `{idx}` on group_members(group_id, member_id)");
        }

        if (idxRows.Count == 0)
            skipped.Add("group_members unique(group_id, member_id) already absent");

        // Backfill group_member_id from (group_id, member_id) where still null.
        async Task Backfill(string table, string seatCol, string memberCol = "member_id")
        {
            if (!await TableExistsAsync(table, ct)) return;
            if (!await ColumnExistsAsync(table, seatCol, ct)) return;
            var sql = $"""
                UPDATE `{table}` t
                INNER JOIN group_members gm
                  ON gm.group_id = t.group_id AND gm.member_id = t.`{memberCol}`
                SET t.`{seatCol}` = gm.id
                WHERE t.`{seatCol}` IS NULL
                """;
            // Prefer matching active seat with lowest member_number when duplicates appear later.
            await ExecuteAsync(sql, ct);
            applied.Add($"Backfilled `{table}.{seatCol}` from group_members");
        }

        await Backfill("monthly_bids", "taken_by_group_member_id", "taken_by_member_id");
        await Backfill("month_bidding_status", "winner_group_member_id", "winner_member_id");
        await Backfill("member_bids", "group_member_id");
        await Backfill("member_payments", "group_member_id");
        await Backfill("member_summary", "group_member_id");
        await Backfill("random_picks", "selected_group_member_id", "selected_member_id");

        if (await TableExistsAsync("random_picks", ct)
            && await ColumnExistsAsync("random_picks", "admin_override_group_member_id", ct)
            && await ColumnExistsAsync("random_picks", "admin_override_member_id", ct))
        {
            await ExecuteAsync("""
                UPDATE random_picks t
                INNER JOIN group_members gm
                  ON gm.group_id = t.group_id AND gm.member_id = t.admin_override_member_id
                SET t.admin_override_group_member_id = gm.id
                WHERE t.admin_override_group_member_id IS NULL
                  AND t.admin_override_member_id IS NOT NULL
                """, ct);
            applied.Add("Backfilled random_picks.admin_override_group_member_id");
        }

        // Ensure default hand labels for seats missing them when member has multiple seats.
        await ExecuteAsync("""
            UPDATE group_members gm
            INNER JOIN (
              SELECT group_id, member_id, COUNT(*) AS c
              FROM group_members
              WHERE status = 'active'
              GROUP BY group_id, member_id
              HAVING c > 1
            ) multi ON multi.group_id = gm.group_id AND multi.member_id = gm.member_id
            SET gm.hand_label = CONCAT('Hand ', gm.member_number)
            WHERE gm.hand_label IS NULL OR gm.hand_label = ''
            """, ct);

        // Payments / summaries: uniqueness moves from member_id → group_member_id (seat).
        await RemapUniqueIndexAsync(
            "member_payments",
            oldColumns: ["group_id", "member_id", "month_number"],
            newIndexName: "uq_member_payments_seat_month",
            newColumnsSql: "`group_id`, `group_member_id`, `month_number`",
            applied, skipped, ct);

        await RemapUniqueIndexAsync(
            "member_summary",
            oldColumns: ["group_id", "member_id"],
            newIndexName: "uq_member_summary_seat",
            newColumnsSql: "`group_id`, `group_member_id`",
            applied, skipped, ct);
    }

    private async Task RemapUniqueIndexAsync(
        string table,
        string[] oldColumns,
        string newIndexName,
        string newColumnsSql,
        List<string> applied,
        List<string> skipped,
        CancellationToken ct)
    {
        if (!await TableExistsAsync(table, ct)) return;

        var colList = string.Join(", ", oldColumns.Select(c => $"'{c}'"));
        var idxRows = await db.Database.SqlQueryRaw<string>($"""
            SELECT INDEX_NAME AS Value
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{table}'
              AND NON_UNIQUE = 0
              AND INDEX_NAME <> 'PRIMARY'
              AND COLUMN_NAME IN ({colList})
            GROUP BY INDEX_NAME
            HAVING COUNT(DISTINCT COLUMN_NAME) = {oldColumns.Length}
               AND SUM(COLUMN_NAME IN ({colList})) = {oldColumns.Length}
            """).ToListAsync(ct);

        // FK on group_id often relies on the leftmost prefix of the unique index.
        // Add a plain group_id index first so MySQL allows dropping the unique key.
        if (oldColumns.Contains("group_id", StringComparer.OrdinalIgnoreCase) && idxRows.Count > 0)
        {
            var helper = $"idx_{table}_group_id_fk";
            var existsHelper = await db.Database.SqlQueryRaw<string>($"""
                SELECT INDEX_NAME AS Value
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = '{table}'
                  AND INDEX_NAME = '{helper}'
                LIMIT 1
                """).ToListAsync(ct);
            if (existsHelper.Count == 0)
            {
                await ExecuteAsync($"ALTER TABLE `{table}` ADD INDEX `{helper}` (`group_id`)", ct);
                applied.Add($"Added helper index `{helper}` on {table}(group_id)");
            }
        }

        foreach (var idx in idxRows.Distinct())
        {
            if (string.Equals(idx, newIndexName, StringComparison.OrdinalIgnoreCase))
                continue;
            await ExecuteAsync($"ALTER TABLE `{table}` DROP INDEX `{idx}`", ct);
            applied.Add($"Dropped unique index `{idx}` on {table}");
        }

        var hasNew = await db.Database.SqlQueryRaw<string>($"""
            SELECT INDEX_NAME AS Value
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{table}'
              AND INDEX_NAME = '{newIndexName}'
            LIMIT 1
            """).ToListAsync(ct);

        if (hasNew.Count == 0)
        {
            await ExecuteAsync(
                $"ALTER TABLE `{table}` ADD UNIQUE INDEX `{newIndexName}` ({newColumnsSql})", ct);
            applied.Add($"Added unique index `{newIndexName}` on {table}");
        }
        else
        {
            skipped.Add($"{table}.{newIndexName} already exists");
        }
    }

    private async Task SeedDefaultsAsync(List<string> applied, CancellationToken ct)
    {
        if (await TableExistsAsync("subscription_plans", ct)
            && await ScalarLongAsync("SELECT COUNT(*) FROM subscription_plans", ct) == 0)
        {
            await ExecuteAsync("""
                INSERT INTO subscription_plans (plan_name, duration_months, price, description, features, max_groups, max_members_per_group, created_by, is_promotional)
                VALUES
                ('1 Month Plan', 1, 100.00, 'Perfect for trying out Mitra Niidhi', JSON_ARRAY('Basic group management','Member management','Payment tracking'), 5, 20, 1, 0),
                ('3 Months Plan', 3, 280.00, 'Great for small communities', JSON_ARRAY('All basic features','Advanced reporting','Priority support'), 15, 30, 1, 0),
                ('6 Months Plan', 6, 550.00, 'Ideal for multiple groups', JSON_ARRAY('All standard features','API access','Phone support'), 30, 50, 1, 0),
                ('1 Year Plan', 12, 1000.00, 'Best annual value', JSON_ARRAY('All premium features','Dedicated support'), 100, 100, 1, 0),
                ('Free Trial', 0, 0.00, '7-day free trial', JSON_ARRAY('All basic features','Limited to 2 groups'), 2, 10, 1, 1)
                """, ct);
            applied.Add("Seeded default subscription plans");
        }

        if (await TableExistsAsync("payment_config", ct)
            && await ScalarLongAsync("SELECT COUNT(*) FROM payment_config", ct) == 0)
        {
            await ExecuteAsync("""
                INSERT INTO payment_config (client_id, config_key, config_value, description) VALUES
                (1, 'upi_id', '', 'UPI ID for receiving payments'),
                (1, 'bank_account_name', 'BC Group Admin', 'Bank account holder / payee name'),
                (1, 'payment_note', 'BC Group Monthly Payment', 'Default payment note'),
                (1, 'qr_enabled', '1', 'Enable QR payments (1=enabled, 0=disabled)')
                """, ct);
            applied.Add("Seeded default payment_config keys");
        }

        if (await TableExistsAsync("system_settings", ct)
            && await ScalarLongAsync("SELECT COUNT(*) FROM system_settings", ct) == 0)
        {
            await ExecuteAsync("""
                INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_public) VALUES
                ('app_name', 'Mitra Niidhi', 'text', 'Application name', 'general', 1),
                ('app_version', '2.1.0', 'text', 'Application version', 'general', 1),
                ('default_currency', 'INR', 'text', 'Default currency', 'general', 1),
                ('currency_symbol', '₹', 'text', 'Currency symbol', 'general', 1),
                ('max_group_members', '20', 'number', 'Max members per group', 'groups', 0),
                ('min_group_members', '5', 'number', 'Min members per group', 'groups', 0),
                ('enable_qr_payments', '1', 'boolean', 'Enable QR payments', 'payments', 0),
                ('maintenance_mode', '0', 'boolean', 'Maintenance mode', 'system', 0)
                """, ct);
            applied.Add("Seeded default system_settings");
        }

        if (await TableExistsAsync("clients", ct)
            && await ScalarLongAsync("SELECT COUNT(*) FROM clients", ct) == 0)
        {
            await ExecuteAsync("""
                INSERT INTO clients (client_name, company_name, contact_person, email, phone, created_by, status, subscription_status)
                VALUES ('Default Client', 'Default Company', 'Admin User', 'admin@defaultclient.com', '9999999999', 1, 'active', 'trial')
                """, ct);
            applied.Add("Seeded default client");
        }
    }

    private async Task<bool> TableExistsAsync(string table, CancellationToken ct)
    {
        var count = await ScalarLongAsync(
            """
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = {0}
            """,
            ct,
            table);
        return count > 0;
    }

    private async Task<bool> ColumnExistsAsync(string table, string column, CancellationToken ct)
    {
        var count = await ScalarLongAsync(
            """
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = {0} AND column_name = {1}
            """,
            ct,
            table,
            column);
        return count > 0;
    }

    private async Task<long> ScalarLongAsync(string sql, CancellationToken ct, params object[] args)
    {
        await using var command = db.Database.GetDbConnection().CreateCommand();
        if (command.Connection!.State != System.Data.ConnectionState.Open)
            await command.Connection.OpenAsync(ct);

        // EF FormattableString style — use parameterized query via FromSql is awkward for scalar;
        // build with MySqlParameter-like placeholders manually.
        var formatted = sql;
        for (var i = 0; i < args.Length; i++)
        {
            var pName = $"@p{i}";
            formatted = formatted.Replace($"{{{i}}}", pName);
            var p = command.CreateParameter();
            p.ParameterName = pName;
            p.Value = args[i];
            command.Parameters.Add(p);
        }

        command.CommandText = formatted;
        var result = await command.ExecuteScalarAsync(ct);
        return Convert.ToInt64(result ?? 0L);
    }

    private async Task ExecuteAsync(string sql, CancellationToken ct)
    {
        await db.Database.ExecuteSqlRawAsync(sql, ct);
    }
}
