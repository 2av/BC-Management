# BC Management System - Multi-Tenant Architecture

## Overview

The BC Management System has been upgraded to support multi-tenant architecture, allowing a single platform to serve multiple clients (companies) with complete data isolation and independent management.

## Architecture

### Hierarchy Structure
```
Super Admin (Platform Owner)
    ├── Client 1 (Company A)
    │   ├── Client Admin
    │   ├── Groups
    │   └── Members
    ├── Client 2 (Company B)
    │   ├── Client Admin
    │   ├── Groups
    │   └── Members
    └── Client N...
```

## User Roles

### 1. Super Admin
- **Purpose**: Platform owner and system administrator
- **Access**: Complete platform control
- **Capabilities**:
  - Create and manage client accounts
  - View platform-wide statistics
  - System configuration and settings
  - Audit log access
  - Client subscription management

### 2. Client Admin
- **Purpose**: Company administrator
- **Access**: Limited to their organization's data
- **Capabilities**:
  - Manage BC groups within their organization
  - Add/edit members
  - Configure payment settings
  - View reports and analytics for their data only
  - All existing admin functionality (scoped to their client)

### 3. Members
- **Purpose**: BC group participants
- **Access**: Limited to their group data
- **Capabilities**:
  - View group information
  - Make payments
  - Place bids
  - View personal payment history
  - All existing member functionality

## Database Changes

### New Tables
1. **super_admins** - Platform administrators
2. **clients** - Client organizations
3. **client_admins** - Client administrators
4. **audit_log** - System audit trail
5. **system_settings** - Platform-wide settings

### Modified Tables
All existing tables now include `client_id` for data isolation:
- `bc_groups`
- `monthly_bids`
- `member_payments`
- `member_summary`
- `member_bids`
- `month_bidding_status`
- `payment_config`

## Installation & Migration

### Prerequisites
- Existing BC Management System
- MySQL/MariaDB database
- PHP 7.4 or higher
- Web server (Apache/Nginx)

### Migration Steps

1. **Backup Your Database**
   ```bash
   mysqldump -u username -p database_name > backup.sql
   ```

2. **Run Migration**
   - Via Web Interface: Visit `run_multi_tenant_migration.php`
   - Via Command Line: `php run_multi_tenant_migration.php`

3. **Verify Migration**
   - Check that new tables are created
   - Verify existing data is migrated to default client
   - Test login functionality

### Default Accounts Created

After migration, these accounts are available:

- **Super Admin**
  - Username: `superadmin`
  - Password: `superadmin123`
  - Access: `super_admin_login.php`

- **Default Client**
  - Your existing admin account becomes a client admin
  - All existing data is migrated to "Default Client"
  - Access: `client_login.php`

## Access Points

### Login URLs
- **Super Admin**: `/super_admin_login.php`
- **Client Admin**: `/client_login.php`
- **Members**: `/member_login.php`
- **Landing Page**: `/landing.php`

### Dashboard URLs
- **Super Admin**: `/super_admin_dashboard.php`
- **Client Admin**: `/client_dashboard.php`
- **Members**: `/member_dashboard.php`

## Features

### Super Admin Features
- Client management (create, edit, suspend, delete)
- Platform statistics and analytics
- System settings configuration
- Audit log monitoring
- Subscription plan management

### Client Features
- Complete BC management for their organization
- Member management
- Group creation and management
- Payment configuration
- Reports and analytics
- All existing admin features (scoped to client)

### Data Isolation
- Complete separation between clients
- No cross-client data access
- Secure multi-tenancy
- Independent configurations per client

## Security Features

### Authentication
- Separate login systems for each user type
- Password hashing with PHP's password_hash()
- Session-based authentication
- Role-based access control

### Data Protection
- Client-based data filtering
- SQL injection protection
- XSS protection
- CSRF protection (where implemented)

### Audit Trail
- All major actions logged
- User activity tracking
- IP address and user agent logging
- Comprehensive audit reports

## Configuration

### Client Limits
Each client can have configurable limits:
- Maximum number of groups
- Maximum members per group
- Subscription plan features
- Custom payment configurations

### System Settings
Platform-wide settings managed by Super Admin:
- Application name and branding
- Default client limits
- Maintenance mode
- Maximum clients allowed

## API Compatibility

The existing API endpoints continue to work with automatic client context:
- All queries are automatically filtered by client_id
- Existing functionality preserved
- Backward compatibility maintained

## Troubleshooting

### Common Issues

1. **Migration Fails**
   - Check database permissions
   - Verify MySQL version compatibility
   - Review error logs

2. **Login Issues**
   - Clear browser cache and cookies
   - Check user status (active/inactive)
   - Verify client status

3. **Data Not Visible**
   - Confirm user is in correct client context
   - Check client_id assignments
   - Verify data migration completed

### Support

For technical support:
1. Check the audit log for error details
2. Review database integrity
3. Verify file permissions
4. Check PHP error logs

## Backup & Recovery

### Regular Backups
- Database: Daily automated backups recommended
- Files: Include uploaded files and configurations
- Settings: Export system settings regularly

### Recovery Process
1. Restore database from backup
2. Restore file system
3. Verify data integrity
4. Test all login systems

## Future Enhancements

Planned features for future versions:
- API key management for clients
- Advanced reporting and analytics
- White-label customization
- Automated billing integration
- Mobile app support
- Advanced audit features

## Version Information

- **Version**: 2.0.0
- **Release Date**: Current
- **Compatibility**: Upgrades from v1.x
- **PHP Requirements**: 7.4+
- **Database**: MySQL 5.7+ / MariaDB 10.2+
