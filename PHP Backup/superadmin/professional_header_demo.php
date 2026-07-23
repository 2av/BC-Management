<?php
// Demo page to showcase the new professional header

// Set page title
$page_title = 'Professional Header Demo';

// Include header
require_once 'includes/header.php';
?>

<!-- Demo Content -->
<div class="row">
    <div class="col-12">
        <div class="text-center mb-5">
            <h1 class="display-4 mb-3">
                <i class="fas fa-crown me-3" style="color: #ffd700;"></i>
                Professional Super Admin Header
            </h1>
            <p class="lead text-muted">
                Experience the new professional design with enhanced navigation and modern styling
            </p>
        </div>
    </div>
</div>

<!-- Features Showcase -->
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="fas fa-palette fa-3x" style="color: #1e3c72;"></i>
                </div>
                <h5 class="card-title">Professional Design</h5>
                <p class="card-text">
                    Modern blue gradient with gold accents, creating a sophisticated and trustworthy appearance
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="fas fa-mobile-alt fa-3x" style="color: #1e3c72;"></i>
                </div>
                <h5 class="card-title">Responsive Navigation</h5>
                <p class="card-text">
                    Fully responsive design that works perfectly on desktop, tablet, and mobile devices
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="fas fa-chart-line fa-3x" style="color: #1e3c72;"></i>
                </div>
                <h5 class="card-title">Real-time Stats</h5>
                <p class="card-text">
                    Live statistics display in the header showing active clients, pending payments, and current time
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Navigation Features -->
<div class="row mt-5">
    <div class="col-12">
        <h3 class="mb-4">
            <i class="fas fa-compass me-2" style="color: #1e3c72;"></i>
            Navigation Features
        </h3>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title">
                    <i class="fas fa-bars me-2" style="color: #ffd700;"></i>
                    Organized Menu Structure
                </h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Dashboard - Main overview</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Client Management - Comprehensive client tools</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Billing & Plans - Subscription management</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Payments - Financial tracking</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>System Tools - Administrative functions</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title">
                    <i class="fas fa-user-crown me-2" style="color: #ffd700;"></i>
                    User Experience Enhancements
                </h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Hover effects and smooth transitions</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Active state highlighting</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Professional dropdown menus</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Notification badges for alerts</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>User profile with avatar</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Color Scheme -->
<div class="row mt-5">
    <div class="col-12">
        <h3 class="mb-4">
            <i class="fas fa-swatchbook me-2" style="color: #1e3c72;"></i>
            Professional Color Scheme
        </h3>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="text-center">
            <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); border-radius: 50%; margin: 0 auto 1rem; border: 3px solid #ffd700;"></div>
            <h6>Primary Blue</h6>
            <small class="text-muted">Header Background</small>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="text-center">
            <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #ffd700, #ffed4e); border-radius: 50%; margin: 0 auto 1rem; border: 3px solid #1e3c72;"></div>
            <h6>Gold Accent</h6>
            <small class="text-muted">Highlights & Active States</small>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="text-center">
            <div style="width: 100px; height: 100px; background: rgba(255, 255, 255, 0.9); border-radius: 50%; margin: 0 auto 1rem; border: 3px solid #1e3c72;"></div>
            <h6>Clean White</h6>
            <small class="text-muted">Content Background</small>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="text-center">
            <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #ff6b6b, #ee5a52); border-radius: 50%; margin: 0 auto 1rem; border: 3px solid #ffd700;"></div>
            <h6>Alert Red</h6>
            <small class="text-muted">Notifications & Warnings</small>
        </div>
    </div>
</div>

<!-- Test Buttons -->
<div class="row mt-5">
    <div class="col-12">
        <h3 class="mb-4">
            <i class="fas fa-vial me-2" style="color: #1e3c72;"></i>
            Test Interactive Features
        </h3>
        <div class="d-flex flex-wrap gap-3">
            <button class="btn btn-primary" onclick="showNotification('Professional notification system working!', 'success')">
                <i class="fas fa-bell me-2"></i>Test Notification
            </button>
            <button class="btn btn-warning" onclick="showLoading(); setTimeout(hideLoading, 2000)">
                <i class="fas fa-spinner me-2"></i>Test Loading
            </button>
            <button class="btn btn-info" onclick="confirmAction('Test confirmation dialog?', function() { showNotification('Confirmed!', 'info'); })">
                <i class="fas fa-question me-2"></i>Test Confirmation
            </button>
            <button class="btn btn-success" onclick="window.location.href='super_admin_dashboard.php'">
                <i class="fas fa-home me-2"></i>Go to Dashboard
            </button>
        </div>
    </div>
</div>

<!-- Implementation Guide -->
<div class="row mt-5">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white;">
                <h5 class="mb-0">
                    <i class="fas fa-code me-2"></i>
                    Implementation Guide
                </h5>
            </div>
            <div class="card-body">
                <p class="mb-3">The new professional header is now active across all super admin pages. Here's what changed:</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>Visual Improvements:</h6>
                        <ul>
                            <li>Professional blue and gold color scheme</li>
                            <li>Enhanced typography and spacing</li>
                            <li>Improved hover effects and transitions</li>
                            <li>Better organized navigation structure</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Functional Enhancements:</h6>
                        <ul>
                            <li>Clearer menu categorization</li>
                            <li>Enhanced dropdown organization</li>
                            <li>Better mobile responsiveness</li>
                            <li>Improved accessibility features</li>
                        </ul>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> All existing functionality remains the same - only the visual design and organization have been improved for a more professional appearance.
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
