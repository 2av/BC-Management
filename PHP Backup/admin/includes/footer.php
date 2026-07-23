    </div> <!-- End container -->

    <!-- Mobile Quick Actions Bar (fixed at bottom, visible only on mobile) -->
    <nav class="mobile-quick-bar">
        <a href="index.php" class="mobile-quick-item" title="Dashboard">
            <i class="fas fa-tachometer-alt"></i>
            <span>Home</span>
        </a>
        <a href="create_group_simple.php" class="mobile-quick-item" title="Create Group">
            <i class="fas fa-plus-circle"></i>
            <span>Create</span>
        </a>
        <a href="add_member.php" class="mobile-quick-item" title="Add Member">
            <i class="fas fa-user-plus"></i>
            <span>Member</span>
        </a>
        <a href="payment_status.php" class="mobile-quick-item" title="Payments">
            <i class="fas fa-credit-card"></i>
            <span>Payments</span>
        </a>
        <a href="manage_groups.php" class="mobile-quick-item" title="Groups">
            <i class="fas fa-layer-group"></i>
            <span>Groups</span>
        </a>
    </nav>
    <style>
        .mobile-quick-bar {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 0.5rem 0;
            z-index: 1050;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
            padding-bottom: max(0.5rem, env(safe-area-inset-bottom));
            justify-content: space-around;
            align-items: center;
        }
        @media (max-width: 767.98px) {
            .mobile-quick-bar { display: flex !important; }
            body { padding-bottom: 70px; }
        }
        .mobile-quick-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            padding: 0.4rem 0.6rem;
            font-size: 0.7rem;
            font-weight: 500;
            transition: all 0.2s;
            border-radius: 10px;
            min-width: 56px;
        }
        .mobile-quick-item i {
            font-size: 1.2rem;
            margin-bottom: 0.2rem;
        }
        .mobile-quick-item:hover, .mobile-quick-item:active {
            color: #fff;
            background: rgba(255,255,255,0.2);
        }
    </style>

    <!-- Footer -->
    <footer class="mt-5 py-4 bg-white border-top">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">
                        <i class="fas fa-copyright me-1"></i>
                        <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">
                        <i class="fas fa-code me-1"></i>
                        Version 2.0 | Built with <i class="fas fa-heart text-danger"></i>
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Additional JavaScript can be added here -->
    <script>
        // Check if Bootstrap is loaded and initialize dropdowns
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof bootstrap === 'undefined') {
                console.error('Bootstrap JavaScript is not loaded!');
            } else {
                console.log('Bootstrap loaded successfully');

                // Initialize all dropdowns manually if needed
                const dropdownElementList = document.querySelectorAll('.dropdown-toggle');
                const dropdownList = [...dropdownElementList].map(dropdownToggleEl => {
                    return new bootstrap.Dropdown(dropdownToggleEl);
                });

                console.log('Initialized', dropdownList.length, 'dropdowns');
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Add smooth scrolling to anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Add loading state to buttons on form submission
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                }
            });
        });
    </script>

</body>
</html>
