<?php
// main.php - Main controller file
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get the requested page, default to 'patients'
$current_page = isset($_GET['page']) ? $_GET['page'] : 'patients';

// Define allowed pages for security
$allowed_pages = ['patients', 'clinical_records', 'staff_wards', 'input_forms'];

// Validate page parameter
if (!in_array($current_page, $allowed_pages)) {
    $current_page = 'patients';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPMH | Ivor Paine Memorial Hospital</title>
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/styles.css">
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
    <?php
    // Include the selected page from the pages folder (one level up)
    $page_file = "../pages/{$current_page}.php";
    if (file_exists($page_file)) {
        include $page_file;
    } else {
        include '../pages/patients.php'; // fallback
    }
    ?>
</main>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="logout-modal">
    <div class="logout-modal-content">
        <div class="logout-modal-header">
            <span class="material-icons" style="font-size: 48px; color: #1E3A5F;">logout</span>
            <h3>Confirm Logout</h3>
            <p>Are you sure you want to log out?</p>
        </div>
        <div class="logout-modal-actions">
            <button class="modal-cancel-btn" id="cancelLogoutBtn">Cancel</button>
            <button class="modal-confirm-btn" id="confirmLogoutBtn">Yes, Logout</button>
        </div>
    </div>
</div>

<script src="../assets/scripts.js"></script>

<script>
    // Sidebar Toggle Functionality (if not in scripts.js)
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggleSidebar');
    
    // Load saved state from localStorage
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
    }
    
    if (toggleBtn) {
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
</script>
</body>
</html>