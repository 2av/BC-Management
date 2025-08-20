    </div> <!-- End Main Content Container -->

    <!-- Footer -->
    <footer class="mt-5 py-4" style="background: var(--member-gradient); color: white;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">
                        <i class="fas fa-user-friends me-2"></i>
                        <strong><?= APP_NAME ?></strong> - Member Portal
                    </p>
                    <small class="opacity-75">Manage your BC groups and payments</small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="opacity-75">
                        © <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Common Member Scripts -->
    <script>
        // Group switching functionality
        function switchGroup(groupId) {
            if (groupId && groupId !== '<?= $current_group_id ?>') {
                // Create a form to switch group
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'dashboard.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'switch_group';
                input.value = groupId;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Add loading states to buttons
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                form.addEventListener('submit', function() {
                    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        const originalText = submitBtn.innerHTML || submitBtn.value;
                        if (submitBtn.tagName === 'BUTTON') {
                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                        } else {
                            submitBtn.value = 'Processing...';
                        }
                        submitBtn.disabled = true;
                        
                        // Re-enable after 10 seconds as fallback
                        setTimeout(function() {
                            if (submitBtn.tagName === 'BUTTON') {
                                submitBtn.innerHTML = originalText;
                            } else {
                                submitBtn.value = originalText;
                            }
                            submitBtn.disabled = false;
                        }, 10000);
                    }
                });
            });
        });

        // Smooth scrolling for anchor links
        document.addEventListener('DOMContentLoaded', function() {
            const anchorLinks = document.querySelectorAll('a[href^="#"]');
            anchorLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    const targetId = this.getAttribute('href').substring(1);
                    const targetElement = document.getElementById(targetId);
                    if (targetElement) {
                        e.preventDefault();
                        targetElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });

        // Add hover effects to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card, .stats-card, .action-card');
            cards.forEach(function(card) {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.transition = 'all 0.3s ease';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });

        // Confirmation dialogs for important actions
        document.addEventListener('DOMContentLoaded', function() {
            const dangerButtons = document.querySelectorAll('.btn-danger, .btn-outline-danger, [data-confirm]');
            dangerButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    const message = this.getAttribute('data-confirm') || 'Are you sure you want to perform this action?';
                    if (!confirm(message)) {
                        e.preventDefault();
                        return false;
                    }
                });
            });
        });

        // Format currency inputs
        document.addEventListener('DOMContentLoaded', function() {
            const currencyInputs = document.querySelectorAll('input[data-currency]');
            currencyInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    let value = this.value.replace(/[^\d]/g, '');
                    if (value) {
                        value = parseInt(value).toLocaleString('en-IN');
                        this.value = '₹' + value;
                    }
                });
            });
        });

        // Mobile menu enhancements
        document.addEventListener('DOMContentLoaded', function() {
            const navbarToggler = document.querySelector('.navbar-toggler');
            const navbarCollapse = document.querySelector('.navbar-collapse');
            
            if (navbarToggler && navbarCollapse) {
                // Close mobile menu when clicking outside
                document.addEventListener('click', function(e) {
                    if (!navbarCollapse.contains(e.target) && !navbarToggler.contains(e.target)) {
                        const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                        if (bsCollapse && navbarCollapse.classList.contains('show')) {
                            bsCollapse.hide();
                        }
                    }
                });
                
                // Close mobile menu when clicking on nav links
                const navLinks = navbarCollapse.querySelectorAll('.nav-link');
                navLinks.forEach(function(link) {
                    link.addEventListener('click', function() {
                        const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                        if (bsCollapse && navbarCollapse.classList.contains('show')) {
                            bsCollapse.hide();
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
