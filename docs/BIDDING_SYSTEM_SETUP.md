# Bidding System Setup Guide

## Overview
The new bidding system allows multiple members to place bids for each month, and admins can manage the bidding process and approve winners.

## Database Setup

### Step 1: Run the Migration
Execute the following SQL file to add the new bidding tables:

```sql
-- Run this in your MySQL database
SOURCE bidding_system_migration.sql;
```

Or manually execute the contents of `bidding_system_migration.sql` in your database.

### Step 2: Verify Tables
After running the migration, you should have these new tables:
- `member_bids` - Stores individual bids from members
- `month_bidding_status` - Tracks bidding periods and winners
- Updated `members` table with `has_won_month` and `won_amount` columns

## Features Added

### For Members:
1. **Bidding Portal** (`member_bidding.php`)
   - View available months for bidding
   - Place bids for open months
   - View current bids and rankings
   - See bidding status and rules

2. **Dashboard Integration**
   - New "Place Bids" button in member dashboard
   - Shows bidding status and won months

### For Admins:
1. **Bidding Management** (`admin_bidding.php`)
   - Open/close bidding for specific months
   - View all bids for each month
   - Select and approve winners
   - Manage bidding timeline

2. **Winner Selection**
   - Interactive winner selection interface
   - Automatic calculation of net amounts
   - Integration with existing monthly_bids table

## How It Works

### Member Workflow:
1. Member logs in and goes to "Place Bids"
2. Sees available months with open bidding
3. Places bid amount (must be less than total collection)
4. Can view all current bids and rankings
5. Gets notified when admin approves winner

### Admin Workflow:
1. Admin opens bidding for a specific month
2. Sets bidding end date
3. Members place their bids
4. Admin closes bidding when ready
5. Admin reviews all bids and selects winner
6. System automatically updates all related tables

### Bidding Rules:
- Members who have already won cannot bid again
- Lowest bid typically wins
- Winner receives: Total Collection - Bid Amount
- Other members pay: Bid Amount ÷ (Total Members - 1)
- Admin has final say in winner selection

## File Structure

### New Files:
- `member_bidding.php` - Member bidding interface
- `get_month_bids.php` - AJAX endpoint for member bid viewing
- `admin_bidding.php` - Admin bidding management
- `admin_get_winner_selection.php` - AJAX winner selection
- `admin_get_month_bids.php` - AJAX admin bid viewing
- `bidding_system_migration.sql` - Database migration

### Modified Files:
- `config.php` - Added bidding helper functions
- `member_dashboard.php` - Added "Place Bids" button
- `view_group.php` - Added "Manage Bidding" button

## Security Features

### Access Control:
- Members can only bid in their own group
- Members cannot bid if they've already won
- Admins can only manage groups they have access to
- All inputs are validated and sanitized

### Data Integrity:
- Foreign key constraints ensure data consistency
- Transaction-based winner approval
- Automatic status updates across related tables

## Integration with Existing System

The bidding system is fully integrated with the existing BC management system:

- **Backward Compatibility**: Existing monthly_bids table is automatically updated
- **Member Summary**: Profit calculations include bidding wins
- **Payment Tracking**: Winner amounts are tracked in member payments
- **Group Status**: Bidding status affects group completion tracking

## Customization Options

### Bidding Rules:
You can modify the bidding rules by editing the validation in:
- `member_bidding.php` (member-side validation)
- `admin_get_winner_selection.php` (admin-side selection)

### UI Customization:
- All interfaces use Bootstrap 5 with custom CSS
- Icons use Font Awesome 6
- Color schemes can be modified in the CSS sections

### Notification System:
Currently uses session messages. Can be extended to include:
- Email notifications to members
- SMS alerts for bidding deadlines
- Real-time updates using WebSockets

## Troubleshooting

### Common Issues:

1. **Migration Fails**
   - Check database permissions
   - Ensure foreign key constraints are enabled
   - Verify existing table structure

2. **Members Can't Place Bids**
   - Check if bidding is open for the month
   - Verify member hasn't already won
   - Ensure member is in correct group

3. **Admin Can't Approve Winner**
   - Check if bidding is closed
   - Verify bids exist for the month
   - Ensure database transaction permissions

### Database Cleanup:
If you need to reset the bidding system:

```sql
-- WARNING: This will delete all bidding data
DELETE FROM member_bids;
DELETE FROM month_bidding_status;
UPDATE members SET has_won_month = NULL, won_amount = 0;
```

## Future Enhancements

Potential improvements that can be added:
- Automatic bidding deadlines
- Email/SMS notifications
- Bid history and analytics
- Mobile app integration
- Real-time bidding updates
- Auction-style bidding
- Group chat for bidding discussions
