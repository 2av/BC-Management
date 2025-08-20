# Super Admin Header and Footer Components

This directory contains reusable header and footer components for all super admin pages.

## Files

- `header.php` - Complete interactive header with navbar, CSS, and HTML structure
- `footer.php` - Footer with enhanced JavaScript functionality and closing HTML tags

## Features

### Interactive Header
- **Modern Design**: Glassmorphism effect with gradients and animations
- **Responsive Navigation**: Mobile-friendly with collapsible menu
- **Active State Detection**: Automatically highlights current page
- **Real-time Stats**: Shows active clients and pending payments in header
- **Notification Badges**: Visual indicators for pending items
- **User Profile**: Avatar and dropdown with user info
- **Loading Animations**: Smooth transitions between pages

### Enhanced Footer
- **Auto-dismiss Alerts**: Alerts automatically close after 5 seconds
- **Form Enhancements**: Loading states and auto-save functionality
- **Utility Functions**: Currency formatting, date formatting, AJAX helpers
- **Keyboard Shortcuts**: Built-in shortcuts for better UX
- **Notification System**: Toast-style notifications

## Usage

### Basic Usage

To use the header and footer in any super admin page:

```php
<?php
// Your PHP logic here
$clients = getAllClients();
// ... other logic

// Set page title (optional)
$page_title = 'Client Management';

// Include header
require_once 'includes/header.php';
?>

<!-- Your page content here -->
<div class="row">
    <div class="col-12">
        <h2>Your Page Content</h2>
        <!-- ... -->
    </div>
</div>

<?php
// Optional: Add page-specific JavaScript
$additional_js = '
<script>
    // Your custom JavaScript
    console.log("Page-specific JS loaded");
</script>
';

// Include footer
require_once 'includes/footer.php';
?>
```

### Page Title

Set the `$page_title` variable before including the header:
```php
$page_title = 'Custom Page Title';
require_once 'includes/header.php';
```

### Active Navigation

The header automatically detects the current page and highlights the appropriate navigation item using the `isActiveSuperAdminNav()` function.

### Additional CSS

Add page-specific styles after including the header:
```php
require_once 'includes/header.php';
?>
<style>
    .my-custom-class {
        /* Your styles */
    }
</style>
```

### Additional JavaScript

Add page-specific scripts by setting the `$additional_js` variable before including the footer:
```php
$additional_js = '
<script>
    // Your JavaScript
    function myCustomFunction() {
        showNotification("Custom function called!", "success");
    }
</script>
';
require_once 'includes/footer.php';
?>
```

## Available JavaScript Functions

### Utility Functions
- `formatCurrency(amount, currency)` - Format currency values
- `formatDate(dateString)` - Format dates
- `formatDateTime(dateString)` - Format date and time
- `showLoading()` / `hideLoading()` - Show/hide loading overlay

### Notification System
```javascript
showNotification(message, type, duration);
// Types: 'success', 'error', 'warning', 'info'
// Duration: milliseconds (default: 5000)
```

### AJAX Helper
```javascript
makeAjaxRequest(url, method, data)
    .then(response => {
        // Handle response
    });
```

### Confirmation Dialogs
```javascript
confirmAction('Are you sure?', function() {
    // Callback function
});
```

## Navigation Structure

The header includes the following navigation items:
- Dashboard
- Clients
- Subscriptions (dropdown)
  - Subscription Plans
  - Client Subscriptions
- Payments (with notification badge)
- System (dropdown)
  - Analytics
  - Reports
  - Settings
  - Notifications

## Customization

### Adding New Navigation Items

Edit `header.php` and add new navigation items in the navbar section:

```php
<li class="nav-item">
    <a class="nav-link-super <?= isActiveSuperAdminNav('your_page.php') ?>" href="your_page.php">
        <i class="fas fa-your-icon me-2"></i>Your Page
    </a>
</li>
```

### Modifying Styles

The header uses CSS custom properties for easy theming:
```css
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    /* ... other gradients */
}
```

### Header Stats

The header automatically displays:
- Active clients count
- Pending payments count (with warning styling)

To add more stats, modify the header stats section in `header.php`.

## Benefits

1. **Consistency**: All pages use the same header/footer structure
2. **Maintainability**: Changes to navigation affect all pages
3. **Enhanced UX**: Loading animations, notifications, and smooth interactions
4. **Responsive**: Works on all device sizes
5. **Accessibility**: Proper ARIA labels and keyboard navigation
6. **Performance**: Optimized CSS and JavaScript

## Migration from Old Headers

To migrate existing pages:

1. Remove existing `<!DOCTYPE html>`, `<head>`, and navigation code
2. Add `require_once 'includes/header.php';` at the top
3. Remove closing `</body>` and `</html>` tags
4. Add `require_once 'includes/footer.php';` at the bottom
5. Wrap your content in appropriate Bootstrap classes if needed

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Dependencies

- Bootstrap 5.3.0
- Font Awesome 6.4.0
- Chart.js (for future dashboard enhancements)
