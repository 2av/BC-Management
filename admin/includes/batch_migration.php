<?php
/**
 * Batch Migration Script for Admin Pages
 * 
 * This script automatically converts admin pages to use the new header/footer system
 */

require_once '../../config/config.php';
require_once '../../common/middleware.php';
checkRole('admin');

function migrateAdminPage($filename) {
    $filepath = dirname(__FILE__) . '/../' . $filename;
    
    if (!file_exists($filepath)) {
        return ['success' => false, 'message' => "File not found: $filename"];
    }
    
    $content = file_get_contents($filepath);
    $originalContent = $content;
    
    // Check if already migrated
    if (strpos($content, 'includes/header.php') !== false) {
        return ['success' => false, 'message' => "Already migrated: $filename"];
    }
    
    // Check if has old structure
    if (strpos($content, '<!DOCTYPE html') === false) {
        return ['success' => false, 'message' => "No HTML structure found: $filename"];
    }
    
    // Extract page title from existing title tag
    $pageTitle = 'Admin Page';
    if (preg_match('/<title>(.*?) - .*?<\/title>/', $content, $matches)) {
        $pageTitle = trim($matches[1]);
    } elseif (preg_match('/<title>(.*?)<\/title>/', $content, $matches)) {
        $pageTitle = trim($matches[1]);
    }
    
    // Find the end of PHP logic (before HTML starts)
    $phpEndPos = strrpos(substr($content, 0, strpos($content, '<!DOCTYPE')), '?>');
    if ($phpEndPos === false) {
        return ['success' => false, 'message' => "Could not find PHP end marker: $filename"];
    }
    
    // Extract PHP logic part
    $phpPart = substr($content, 0, $phpEndPos);
    
    // Find where the main content starts (after navbar)
    $navEndPos = strpos($content, '</nav>');
    if ($navEndPos === false) {
        return ['success' => false, 'message' => "Could not find navbar end: $filename"];
    }
    
    // Find the container start after navbar
    $containerStartPos = strpos($content, '<div class="container', $navEndPos);
    if ($containerStartPos === false) {
        $containerStartPos = strpos($content, '<div class="container-fluid', $navEndPos);
    }
    
    if ($containerStartPos === false) {
        return ['success' => false, 'message' => "Could not find container start: $filename"];
    }
    
    // Extract page-specific CSS
    $cssContent = '';
    if (preg_match('/<style>(.*?)<\/style>/s', $content, $matches)) {
        $cssContent = trim($matches[1]);
    }
    
    // Find where the footer starts
    $footerStartPos = strrpos($content, '</script>');
    if ($footerStartPos === false) {
        $footerStartPos = strrpos($content, '</body>');
    }
    
    if ($footerStartPos === false) {
        return ['success' => false, 'message' => "Could not find footer start: $filename"];
    }
    
    // Extract main content (from container to before footer)
    $mainContent = substr($content, $containerStartPos, $footerStartPos - $containerStartPos);
    
    // Extract page-specific JavaScript
    $jsContent = '';
    if (preg_match_all('/<script(?![^>]*src=)[^>]*>(.*?)<\/script>/s', $content, $matches)) {
        $jsContent = implode("\n", $matches[1]);
    }
    
    // Build new content
    $newContent = $phpPart . "\n\n";
    $newContent .= "// Set page title for the header\n";
    $newContent .= "\$page_title = '" . addslashes($pageTitle) . "';\n\n";
    $newContent .= "// Include the new header\n";
    $newContent .= "require_once 'includes/header.php';\n";
    $newContent .= "?>\n\n";
    
    if (!empty($cssContent)) {
        $newContent .= "<!-- Page-specific CSS -->\n";
        $newContent .= "<style>\n" . $cssContent . "\n</style>\n\n";
    }
    
    $newContent .= "<!-- Page content starts here -->\n";
    $newContent .= $mainContent;
    
    if (!empty($jsContent)) {
        $newContent .= "\n<script>\n" . $jsContent . "\n</script>\n\n";
    }
    
    $newContent .= "<?php require_once 'includes/footer.php'; ?>\n";
    
    // Backup original file
    $backupPath = $filepath . '.backup.' . date('Y-m-d-H-i-s');
    file_put_contents($backupPath, $originalContent);
    
    // Write new content
    if (file_put_contents($filepath, $newContent)) {
        return [
            'success' => true, 
            'message' => "Successfully migrated: $filename",
            'backup' => basename($backupPath)
        ];
    } else {
        return ['success' => false, 'message' => "Failed to write file: $filename"];
    }
}

// List of pages to migrate
$pagesToMigrate = [
    'bulk_import.php',
    'change_password.php',
    'create_group.php',
    'create_group_simple.php',
    'edit_group.php',
    'edit_member.php',
    'manage_members.php',
    'payment_status.php',
    'view_group.php'
];

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate'])) {
    $selectedPages = $_POST['pages'] ?? [];
    
    foreach ($selectedPages as $page) {
        if (in_array($page, $pagesToMigrate)) {
            $results[$page] = migrateAdminPage($page);
        }
    }
}

// Set page title for this helper page
$page_title = 'Batch Migration Tool';
require_once 'header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-magic me-2"></i>Batch Migration Tool</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This tool will automatically modify admin pages. 
                    Backups will be created automatically.
                </div>
                
                <?php if (!empty($results)): ?>
                    <div class="alert alert-info">
                        <h6>Migration Results:</h6>
                        <ul class="mb-0">
                            <?php foreach ($results as $page => $result): ?>
                                <li class="<?= $result['success'] ? 'text-success' : 'text-danger' ?>">
                                    <strong><?= htmlspecialchars($page) ?>:</strong> 
                                    <?= htmlspecialchars($result['message']) ?>
                                    <?php if (isset($result['backup'])): ?>
                                        <small class="text-muted">(Backup: <?= htmlspecialchars($result['backup']) ?>)</small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <h6>Select pages to migrate:</h6>
                    <div class="row">
                        <?php foreach ($pagesToMigrate as $page): ?>
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="pages[]" 
                                           value="<?= htmlspecialchars($page) ?>" id="page_<?= htmlspecialchars($page) ?>">
                                    <label class="form-check-label" for="page_<?= htmlspecialchars($page) ?>">
                                        <?= htmlspecialchars($page) ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" name="migrate" class="btn btn-primary">
                            <i class="fas fa-magic me-2"></i>Migrate Selected Pages
                        </button>
                        <a href="migration_helper.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-chart-bar me-2"></i>View Migration Status
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
