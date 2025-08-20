<?php
// Example page showing how to use the new header system

// Your page logic here
$exampleData = [
    'total_users' => 150,
    'active_sessions' => 45,
    'revenue_today' => 25000
];

// Set page title
$page_title = 'Example Page';

// Include header
require_once 'includes/header.php';
?>

<!-- Page-specific styles (optional) -->
<style>
    .example-card {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
    
    .example-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    }
    
    .feature-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }
</style>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="fas fa-star me-3" style="background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
            Example Page
        </h2>
        <p class="text-muted mb-0">This demonstrates how easy it is to create new pages with the header system</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="showNotification('Button clicked!', 'success')">
            <i class="fas fa-plus me-2"></i>Test Notification
        </button>
    </div>
</div>

<!-- Example Content -->
<div class="row">
    <div class="col-md-4">
        <div class="example-card text-center">
            <div class="feature-icon mx-auto">
                <i class="fas fa-users"></i>
            </div>
            <h4><?= number_format($exampleData['total_users']) ?></h4>
            <p class="text-muted mb-0">Total Users</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="example-card text-center">
            <div class="feature-icon mx-auto">
                <i class="fas fa-chart-line"></i>
            </div>
            <h4><?= number_format($exampleData['active_sessions']) ?></h4>
            <p class="text-muted mb-0">Active Sessions</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="example-card text-center">
            <div class="feature-icon mx-auto">
                <i class="fas fa-rupee-sign"></i>
            </div>
            <h4><?= formatCurrency($exampleData['revenue_today']) ?></h4>
            <p class="text-muted mb-0">Revenue Today</p>
        </div>
    </div>
</div>

<!-- Features Demo -->
<div class="row mt-4">
    <div class="col-12">
        <div class="example-card">
            <h4 class="mb-3">
                <i class="fas fa-magic me-2" style="color: #667eea;"></i>
                Header System Features
            </h4>
            <div class="row">
                <div class="col-md-6">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Interactive Navigation:</strong> Hover effects and active states
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Real-time Stats:</strong> Live client and payment counts
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Notification System:</strong> Toast notifications for user feedback
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Loading Animations:</strong> Smooth page transitions
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Responsive Design:</strong> Works on all devices
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Easy Integration:</strong> Just include header and footer
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Consistent Styling:</strong> Unified look across all pages
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Enhanced UX:</strong> Auto-dismiss alerts and form helpers
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Demo Buttons -->
<div class="row mt-4">
    <div class="col-12">
        <div class="example-card">
            <h4 class="mb-3">
                <i class="fas fa-play me-2" style="color: #667eea;"></i>
                Try These Features
            </h4>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-success" onclick="showNotification('Success message!', 'success')">
                    <i class="fas fa-check me-2"></i>Success Notification
                </button>
                <button class="btn btn-warning" onclick="showNotification('Warning message!', 'warning')">
                    <i class="fas fa-exclamation-triangle me-2"></i>Warning Notification
                </button>
                <button class="btn btn-danger" onclick="showNotification('Error message!', 'error')">
                    <i class="fas fa-times me-2"></i>Error Notification
                </button>
                <button class="btn btn-info" onclick="showNotification('Info message!', 'info')">
                    <i class="fas fa-info me-2"></i>Info Notification
                </button>
                <button class="btn btn-secondary" onclick="showLoading(); setTimeout(hideLoading, 2000)">
                    <i class="fas fa-spinner me-2"></i>Test Loading
                </button>
                <button class="btn btn-primary" onclick="confirmAction('Are you sure?', function() { showNotification('Confirmed!', 'success'); })">
                    <i class="fas fa-question me-2"></i>Confirmation Dialog
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Usage Example -->
<div class="row mt-4">
    <div class="col-12">
        <div class="example-card">
            <h4 class="mb-3">
                <i class="fas fa-code me-2" style="color: #667eea;"></i>
                How to Use This Header System
            </h4>
            <pre class="bg-light p-3 rounded"><code>&lt;?php
// Your page logic here
$data = getData();

// Set page title
$page_title = 'Your Page Title';

// Include header
require_once 'includes/header.php';
?&gt;

&lt;!-- Your page content here --&gt;
&lt;div class="row"&gt;
    &lt;div class="col-12"&gt;
        &lt;h2&gt;Your Content&lt;/h2&gt;
    &lt;/div&gt;
&lt;/div&gt;

&lt;?php
// Optional: Add page-specific JavaScript
$additional_js = '
&lt;script&gt;
    // Your custom JavaScript
&lt;/script&gt;
';

// Include footer
require_once 'includes/footer.php';
?&gt;</code></pre>
        </div>
    </div>
</div>

<?php
// Page-specific JavaScript
$additional_js = '
<script>
    // Example page specific JavaScript
    document.addEventListener("DOMContentLoaded", function() {
        console.log("Example page loaded successfully!");
        
        // Animate cards on load
        const cards = document.querySelectorAll(".example-card");
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = "0";
                card.style.transform = "translateY(20px)";
                card.style.transition = "all 0.5s ease";
                
                setTimeout(() => {
                    card.style.opacity = "1";
                    card.style.transform = "translateY(0)";
                }, 100);
            }, index * 100);
        });
    });
</script>
';

// Include footer
require_once 'includes/footer.php';
?>
