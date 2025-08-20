<?php
// Example page demonstrating header/footer usage
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

// Sample data for demonstration
$sampleData = [
    ['id' => 1, 'name' => 'John Doe', 'status' => 'active'],
    ['id' => 2, 'name' => 'Jane Smith', 'status' => 'inactive'],
    ['id' => 3, 'name' => 'Bob Johnson', 'status' => 'active'],
];

// Set page title for header
$page_title = 'Example Page';

// Include header
require_once 'includes/header.php';
?>

<!-- Page-specific CSS -->
<style>
    .example-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }
    
    .example-card:hover {
        transform: translateY(-5px);
    }
    
    .feature-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.9;
    }
    
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .status-active {
        background: #10b981;
        color: white;
    }
    
    .status-inactive {
        background: #6b7280;
        color: white;
    }
</style>

<!-- Page Content -->
<div class="row mb-4">
    <div class="col-12">
        <div class="example-card">
            <div class="text-center">
                <i class="fas fa-rocket feature-icon"></i>
                <h2>Welcome to the Example Page!</h2>
                <p class="lead">This page demonstrates how to use the new header and footer components.</p>
                <p>Notice how the navbar, styling, and responsive design are automatically included.</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Features Included</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Modern responsive navbar</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Language switcher</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Notifications dropdown</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Admin profile menu</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Bootstrap 5 + Font Awesome</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Custom CSS variables</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Mobile-first design</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-table me-2"></i>Sample Data</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sampleData as $item): ?>
                            <tr>
                                <td><?= $item['id'] ?></td>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $item['status'] ?>">
                                        <?= ucfirst($item['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-code me-2"></i>Usage Example</h5>
            </div>
            <div class="card-body">
                <pre class="bg-light p-3 rounded"><code>&lt;?php
// Set page title
$page_title = 'Your Page Title';

// Include header
require_once 'includes/header.php';
?&gt;

&lt;!-- Your page content here --&gt;
&lt;div class="row"&gt;
    &lt;div class="col-12"&gt;
        &lt;h1&gt;Your Content&lt;/h1&gt;
    &lt;/div&gt;
&lt;/div&gt;

&lt;?php require_once 'includes/footer.php'; ?&gt;</code></pre>
            </div>
        </div>
    </div>
</div>

<!-- Page-specific JavaScript -->
<script>
    // Example of page-specific JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Example page loaded successfully!');
        
        // Add some interactive behavior
        const cards = document.querySelectorAll('.card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.02)';
                this.style.transition = 'transform 0.3s ease';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
