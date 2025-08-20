# BC Management System - Reorganized Structure

## Overview
This document describes the new organized folder structure for the BC Management System, which has been reorganized for better maintainability, security, and scalability.

## New Folder Structure

```
BC-Management/
├── index.php                 # Main entry point (redirects to auth/landing.php)
├── config.php                # Main configuration loader
│
├── /admin/                   # Admin-related files
│   ├── index.php            # Admin dashboard (formerly admin_dashboard.php)
│   ├── members.php           # Member management (formerly admin_members.php)
│   ├── add_member.php        # Add new member (formerly admin_add_member.php)
│   ├── edit_member.php       # Edit member (formerly admin_edit_member.php)
│   ├── create_group.php      # Create BC group
│   ├── create_group_simple.php # Simple group creation
│   ├── manage_groups.php     # Group management
│   ├── view_group.php        # View group details
│   ├── bidding.php           # Bidding management
│   ├── payment_status.php    # Payment status
│   ├── payment_config.php    # QR code configuration
│   ├── bulk_import.php       # Bulk member import
│   ├── change_password.php   # Admin password change
│   └── ...                   # Other admin files
│
├── /member/                  # Member-related files
│   ├── dashboard.php         # Member dashboard (formerly client_dashboard.php)
│   ├── bidding.php           # Member bidding (formerly member_bidding.php)
│   ├── payment.php           # Member payment (formerly member_payment.php)
│   ├── group_view.php        # Group view (formerly member_group_view.php)
│   ├── edit_profile.php      # Edit profile (formerly member_edit_profile.php)
│   ├── change_password.php   # Member password change
│   └── member_dashboard_old.php # Backup of old dashboard
│
├── /superadmin/              # SuperAdmin-related files
│   ├── dashboard.php         # SuperAdmin dashboard
│   ├── clients.php           # Client management
│   ├── add_client.php        # Add new client
│   ├── payments.php          # Payment management
│   ├── subscriptions.php     # Subscription management
│   ├── subscription_plans.php # Subscription plans
│   └── navbar.php            # SuperAdmin navigation
│
├── /auth/                    # Authentication files
│   ├── landing.php           # Main landing page with login options
│   ├── login.php             # Admin login (formerly in root)
│   ├── member_login.php      # Member login (formerly in root)
│   ├── super_admin_login.php # SuperAdmin login (formerly in root)
│   └── logout.php            # Logout handler (formerly in root)
│
├── /config/                  # Configuration files
│   ├── config.php            # Application constants (formerly config/config.php)
│   ├── db_config.php         # Database configuration (formerly in root)
│   ├── main_config.php       # Legacy main config (backup)
│   ├── mt_config.php         # Multi-tenant config (formerly in root)
│   ├── multi_tenant_config.php # Multi-tenant config (formerly in root)
│   └── simple_mt_config.php  # Simple MT config (formerly in root)
│
├── /common/                  # Shared/common files
│   ├── functions.php         # Utility functions
│   ├── auth.php              # Authentication functions
│   ├── middleware.php        # Role-based access control
│   ├── qr_utils.php          # QR code utilities (formerly in root)
│   ├── subscription_functions.php # Subscription functions (formerly in root)
│   └── /languages/           # Language files (formerly in root)
│       ├── config.php        # Language configuration
│       ├── en.php            # English translations
│       └── hi.php            # Hindi translations
│
├── /assets/                  # Static assets
│   ├── /css/                 # Stylesheets
│   │   └── modern-design.css # Main stylesheet
│   ├── /js/                  # JavaScript files
│   └── /images/              # Images
│       └── QRCode.jpeg       # QR code image (formerly in root)
│
├── /uploads/                 # User uploads
│   ├── /qr_codes/            # QR code uploads
│   ├── /member_photos/       # Member photos
│   └── /documents/           # Document uploads
│
└── [Root Files]              # Remaining files (SQL, test files, etc.)
    ├── database.sql          # Database structure
    ├── setup.php             # Database setup
    ├── test_*.php            # Test files
    ├── fix_*.php             # Fix scripts
    ├── *.sql                 # SQL migration files
    └── documentation files   # README, guides, etc.
```

## Key Changes

### 1. Role-Based Organization
- **Admin files**: All admin-related functionality moved to `/admin/`
- **Member files**: All member-related functionality moved to `/member/`
- **SuperAdmin files**: All superadmin functionality moved to `/superadmin/`

### 2. Authentication Centralization
- All login/logout files moved to `/auth/`
- Centralized authentication handling
- Consistent redirect patterns

### 3. Configuration Management
- All config files moved to `/config/`
- Main `config.php` serves as entry point
- Modular configuration loading

### 4. Shared Resources
- Common functions in `/common/`
- Middleware for role-based access control
- Language files organized under `/common/languages/`

### 5. Asset Organization
- CSS, JS, and images in `/assets/`
- Organized upload directories in `/uploads/`

## File Naming Convention

Files have been renamed to remove role prefixes since they're now in role-specific folders:

- `admin_dashboard.php` → `admin/index.php`
- `member_login.php` → `auth/member_login.php`
- `super_admin_clients.php` → `superadmin/clients.php`

## Include Path Updates

All files now use relative paths from their new locations:

```php
// Old way (from root)
require_once 'config.php';

// New way (from role folders)
require_once '../config.php';
require_once '../common/middleware.php';
```

## Middleware Usage

New role-based access control:

```php
// Old way
requireAdminLogin();

// New way
require_once '../common/middleware.php';
checkRole('admin');
```

Available roles:
- `'admin'` - Admin users
- `'member'` - Member users  
- `'superadmin'` - SuperAdmin users
- `'any'` - Any logged-in user

## Benefits

1. **Better Organization**: Clear separation of concerns
2. **Enhanced Security**: Role-based access control
3. **Easier Maintenance**: Logical file grouping
4. **Scalability**: Modular structure for future growth
5. **Cleaner URLs**: Semantic URL structure

## Migration Notes

- All existing functionality preserved
- Database structure unchanged
- Session handling maintained
- Multi-tenant support retained
- Language switching functionality preserved

## Next Steps

1. Update any hardcoded links in remaining files
2. Test all user flows thoroughly
3. Update documentation and deployment scripts
4. Consider implementing URL rewriting for cleaner URLs
