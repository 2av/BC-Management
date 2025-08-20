# Admin Header and Footer Components

This directory contains reusable header and footer components for all admin pages.

## Files

- `header.php` - Complete header with navbar, CSS, and HTML structure
- `footer.php` - Footer with scripts and closing HTML tags

## Usage

### Basic Usage

To use the header and footer in any admin page:

```php
<?php
// Your PHP logic here
$groups = getAllGroups();
// ... other logic

// Set page title (optional)
$page_title = 'Your Page Title';

// Include header
require_once 'includes/header.php';
?>

<!-- Your page-specific CSS (optional) -->
<style>
    .custom-class {
        /* Your custom styles */
    }
</style>

<!-- Your page content here -->
<div class="row">
    <div class="col-12">
        <h1>Your Page Content</h1>
        <!-- ... -->
    </div>
</div>

<!-- Your page-specific JavaScript (optional) -->
<script>
    // Your custom JavaScript
</script>

<?php require_once 'includes/footer.php'; ?>
```

### Example Implementation

Here's how `index.php` was updated to use the new header:

**Before:**
```php
<?php
// PHP logic
?>
<!DOCTYPE html>
<html>
<head>
    <!-- All CSS and meta tags -->
</head>
<body>
    <nav class="navbar">
        <!-- Complete navbar structure -->
    </nav>
    <div class="container">
        <!-- Page content -->
    </div>
    <script src="bootstrap.js"></script>
</body>
</html>
```

**After:**
```php
<?php
// PHP logic
$page_title = 'Admin Dashboard';
require_once 'includes/header.php';
?>

<style>
    /* Page-specific styles only */
</style>

<!-- Page content starts here -->
<!-- The container, navbar, and basic structure are already included -->

<script>
    /* Page-specific JavaScript only */
</script>

<?php require_once 'includes/footer.php'; ?>
```

## Features Included in Header

### CSS Framework
- Bootstrap 5.3.0
- Font Awesome 6.4.0
- Chart.js
- Custom modern design system with CSS variables

### Navbar Features
- Responsive design
- Modern gradient styling
- Dropdown menus for Members, Groups, Reports
- Notifications dropdown
- Language switcher
- Admin profile dropdown
- Active page highlighting

### Responsive Design
- Mobile-first approach
- Collapsible navigation
- Optimized for all screen sizes

### Language Support
- Multi-language support
- Cookie-based language persistence
- Flag icons for each language

## Customization

### Page Title
Set the `$page_title` variable before including the header:
```php
$page_title = 'Custom Page Title';
require_once 'includes/header.php';
```

### Active Navigation
The header automatically detects the current page and highlights the appropriate navigation item using the `isActiveNav()` function.

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
Add page-specific scripts before including the footer:
```php
<script>
    // Your JavaScript
</script>
<?php require_once 'includes/footer.php'; ?>
```

## Benefits

1. **Consistency** - All admin pages have the same look and feel
2. **Maintainability** - Update navbar/styling in one place
3. **Performance** - Optimized CSS and JavaScript loading
4. **Responsive** - Mobile-friendly design out of the box
5. **Accessibility** - Proper ARIA labels and semantic HTML
6. **Modern Design** - Beautiful gradients and animations

## Migration Guide

To migrate existing admin pages:

1. Remove the `<!DOCTYPE html>` through `<nav>` sections
2. Remove the closing `</nav>` through `</html>` sections
3. Add the header include at the top
4. Add the footer include at the bottom
5. Move page-specific CSS to a `<style>` block after the header
6. Set `$page_title` if needed

## Dependencies

The header includes all necessary dependencies:
- Bootstrap 5.3.0 CSS/JS
- Font Awesome 6.4.0
- Chart.js
- Custom CSS variables and modern design system

No additional includes are needed for basic functionality.
