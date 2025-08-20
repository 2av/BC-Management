# Mitra Niidhi Samooh - Community Fund Management System

A clean, minimal PHP-based system for managing Niidhi (Community Fund) groups that exactly matches Excel spreadsheet format. Mitra Niidhi Samooh helps communities organize and manage their collective savings and bidding committees efficiently.

## Features

- **Multiple BC Groups**: Manage multiple BC groups from one dashboard
- **Excel-like Interface**: Deposit/Bid Details and Transaction Details tables exactly like your spreadsheet
- **Monthly Bidding**: Track monthly bids, winners, and payments
- **Member Management**: Add members and track their payments and profits
- **Member Login Portal**: Individual members can login to view their status and group information
- **Real-time Calculations**: Automatic calculation of net payable, gain per member, and profits
- **Dual Access Levels**: Admin access for management, member access for viewing
- **Simple & Clean**: Minimal, focused interface without unnecessary complexity

## Quick Setup

1. **Upload Files**: Upload all files to your web server (XAMPP, WAMP, etc.)

2. **Run Setup**: Visit `setup.php` in your browser and configure database connection

3. **Login**: Use the default credentials:
   - **Admin Login**: `admin` / `admin123`
   - **Member Login**: `akhilesh` / `member123` (sample member)

4. **Create Groups**: Start creating your BC groups and adding members

## Access Levels

### Admin Access (`login.php`)
- Create and manage BC groups
- Add monthly bids and payments
- View complete group data
- Manage member login credentials
- Full system administration

### Member Access (`member_login.php`)
- View personal payment history and status
- See group overview (read-only)
- Track profit/loss calculations
- Monitor group progress
- Highlighted personal data in group view

## File Structure

```
BC-Management/
├── index.php                 # Main entry point (redirects to auth/landing.php)
├── config.php                # Main configuration loader
├── README.md                 # This file
├── test_reorganization.php   # Test script for new structure
├── db_config.sample.php      # Sample database configuration
│
├── /admin/                   # Admin-related files (32 files)
│   ├── index.php            # Admin dashboard
│   ├── members.php           # Member management
│   ├── create_group.php      # Create BC group
│   └── ...                   # Other admin files
│
├── /member/                  # Member-related files (7 files)
│   ├── dashboard.php         # Member dashboard
│   ├── bidding.php           # Member bidding
│   └── ...                   # Other member files
│
├── /superadmin/              # SuperAdmin files (7 files)
│   ├── dashboard.php         # SuperAdmin dashboard
│   ├── clients.php           # Client management
│   └── ...                   # Other superadmin files
│
├── /auth/                    # Authentication files (5 files)
│   ├── landing.php           # Main landing page
│   ├── login.php             # Admin login
│   ├── member_login.php      # Member login
│   └── ...                   # Other auth files
│
├── /config/                  # Configuration files (6 files)
│   ├── db_config.php         # Database configuration
│   ├── config.php            # Application constants
│   └── ...                   # Other config files
│
├── /common/                  # Shared/common files (7 files)
│   ├── functions.php         # Utility functions
│   ├── auth.php              # Authentication functions
│   ├── middleware.php        # Role-based access control
│   └── /languages/           # Language files
│
├── /assets/                  # Static assets
│   ├── /css/                 # Stylesheets
│   ├── /js/                  # JavaScript files
│   └── /images/              # Images
│
├── /uploads/                 # User uploads
│   ├── /qr_codes/            # QR code uploads
│   ├── /member_photos/       # Member photos
│   └── /documents/           # Document uploads
│
├── /sql/                     # SQL files (15 files)
│   ├── database.sql          # Main database structure
│   ├── complete_database.sql # Complete database with data
│   └── ...                   # Migration and fix scripts
│
├── /tests/                   # Test and utility files (50+ files)
│   ├── setup.php             # Database setup
│   ├── test_*.php            # Test files
│   ├── fix_*.php             # Fix scripts
│   └── ...                   # Debug and utility files
│
└── /docs/                    # Documentation (4 files)
    ├── REORGANIZATION_GUIDE.md # Detailed reorganization guide
    ├── BIDDING_SYSTEM_SETUP.md # Bidding system setup
    └── ...                   # Other documentation
```

## How Niidhi Groups Work

1. **Create Group**: Set group name, number of members, and monthly contribution
2. **Add Members**: Enter all member names
3. **Monthly Bidding**: Each month, members can bid to receive the collection early
4. **Winner Selection**: Lowest bidder wins and receives net amount (total - bid)
5. **Payment Tracking**: All members pay reduced amount based on the bid
6. **Profit Calculation**: System automatically calculates profits for each member

## Sample Data

The system comes with a sample Niidhi group "Family BC Group" with 9 members and ₹2000 monthly contribution, showing 4 months of completed transactions.

## Requirements

- PHP 7.4+ with PDO MySQL extension
- MySQL 5.7+ or MariaDB 10.2+
- Web server (Apache/Nginx)

## Support

This is a simple, focused system designed to match your exact Excel spreadsheet format. The interface is clean and minimal, perfect for managing multiple BC groups efficiently.

## Screenshots

The system displays:
- **Basic Info**: Total members, monthly contribution, total collection
- **Deposit/Bid Details**: Month-wise bidding information (exactly like Excel)
- **Transaction Details**: Member-wise payment tracking with totals and profits
- **Summary Cards**: Quick overview of group status

All tables use the same color scheme and layout as your Excel spreadsheet for familiar usage.
