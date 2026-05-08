// assets/scripts.js
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle Functionality
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggleSidebar');
    
    if (sidebar && toggleBtn) {
        // Load saved state from localStorage
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
        }
        
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }
    
    // Logout Modal Functionality
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutModal = document.getElementById('logoutModal');
    const cancelLogoutBtn = document.getElementById('cancelLogoutBtn');
    const confirmLogoutBtn = document.getElementById('confirmLogoutBtn');
    
    if (logoutBtn && logoutModal && cancelLogoutBtn && confirmLogoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            logoutModal.classList.add('active');
        });
        
        cancelLogoutBtn.addEventListener('click', () => {
            logoutModal.classList.remove('active');
        });
        
        logoutModal.addEventListener('click', (e) => {
            if (e.target === logoutModal) {
                logoutModal.classList.remove('active');
            }
        });
        
        confirmLogoutBtn.addEventListener('click', () => {
            window.location.href = 'logout.php';
        });
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && logoutModal.classList.contains('active')) {
                logoutModal.classList.remove('active');
            }
        });
    }
});