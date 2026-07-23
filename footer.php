    <!-- Toast Notifications Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Site Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-top">
                <div class="footer-brand">
                    <h2>Step One</h2>
                    <p>A 4-day residential faith formation program for 9th-standard students and teachers, helping them encounter Jesus at a young age.</p>
                    <div class="social-links">
                        <a href="https://instagram.com/jyteenskerala" target="_blank" class="social-btn" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                        <a href="https://youtube.com/JesusYouthKeralaTeensMinistry
" target="_blank" class="social-btn" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
                    </div>
                </div>
                
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php#home">Home</a></li>
                        <li><a href="index.php#about">About the Program</a></li>
                        <li><a href="index.php#highlights">Highlights</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
                        <li><a href="index.php#intercession">Intercession </a></li>
                        <li><a href="index.php#media" onclick="if(window.switchMainMediaTab) { window.switchMainMediaTab('Posters'); }">Posters</a></li>
                        <li><a href="index.php#media" onclick="if(window.switchMainMediaTab) { window.switchMainMediaTab('Videos'); }">Videos</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4>Portals</h4>
                    <ul>
                        <li><a href="tr_portal.php">Teachers Portal</a></li>
                        <li><a href="register.php">Participant Registration</a></li>
                        <li><a href="admin_login.php">Administrator Login</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> Jesus Youth Kerala Teens Ministry. All Rights Reserved.</p>
                <p>Designed with <i class="fa-solid fa-heart" style="color: var(--gold);"></i> for Step One.</p>
            </div>
        </div>
    </footer>

    <!-- JS Scripts -->
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('mainNavbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Mobile Nav Menu Toggle
        const navToggleBtn = document.getElementById('navToggleBtn');
        const navLinksMenu = document.getElementById('navLinksMenu');

        if (navToggleBtn && navLinksMenu) {
            navToggleBtn.addEventListener('click', function() {
                navLinksMenu.classList.toggle('active');
                const icon = navToggleBtn.querySelector('i');
                if (navLinksMenu.classList.contains('active')) {
                    icon.className = 'fa-solid fa-xmark';
                } else {
                    icon.className = 'fa-solid fa-bars';
                }
            });
        }

        // Toggle dropdown on mobile
        window.toggleMobileDropdown = function(e) {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                const dropdownMenu = e.currentTarget.nextElementSibling;
                dropdownMenu.classList.toggle('active');
            }
        };

        // Global loading functions
        function showLoading(text = 'Processing...') {
            const overlay = document.getElementById('globalLoadingOverlay');
            const overlayText = document.getElementById('loadingText');
            if (overlay) {
                overlayText.textContent = text;
                overlay.style.display = 'flex';
            }
        }

        function hideLoading() {
            const overlay = document.getElementById('globalLoadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }

        // Toast Notification System
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            let iconClass = 'fa-circle-info';
            if (type === 'success') iconClass = 'fa-circle-check';
            if (type === 'error') iconClass = 'fa-circle-exclamation';

            toast.innerHTML = `
                <i class="fa-solid ${iconClass}"></i>
                <div class="toast-message">${message}</div>
            `;

            container.appendChild(toast);

            // Trigger reflow to apply transition
            toast.offsetHeight;
            toast.classList.add('show');

            // Remove toast after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 500);
            }, 5000);
        }

        // Auto display session flash messages
        <?php
        if (isset($_SESSION['flash_success'])) {
            echo "showToast(" . json_encode($_SESSION['flash_success']) . ", 'success');";
            unset($_SESSION['flash_success']);
        }
        if (isset($_SESSION['flash_error'])) {
            echo "showToast(" . json_encode($_SESSION['flash_error']) . ", 'error');";
            unset($_SESSION['flash_error']);
        }
        if (isset($_SESSION['flash_info'])) {
            echo "showToast(" . json_encode($_SESSION['flash_info']) . ", 'info');";
            unset($_SESSION['flash_info']);
        }
        ?>
    </script>
</body>
</html>
