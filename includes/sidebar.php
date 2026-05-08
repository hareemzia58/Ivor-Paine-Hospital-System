<?php
// includes/sidebar.php
// Make sure $current_page is set before including this file
?>
<aside class="sidebar" id="sidebar">
    <button class="toggle-btn" id="toggleSidebar">
        <span class="material-icons">chevron_left</span>
    </button>
    
    <div class="sidebar-header">
        <div class="logo">
            <img src="../assets/visualAssets/logo-blue.png" alt="IPMH Logo" style="width: 45px; height: 45px; object-fit: contain;">
            <div class="logo-text">
                <h2>IVOR PAINE</h2>
                <p>Memorial Hospital</p>
            </div>
        </div>
    </div>
    
    <div class="nav-menu">
        <a href="?page=patients" class="nav-item <?php echo ($current_page == 'patients') ? 'active' : ''; ?>">
            <span class="material-icons">people</span>
            <span>Patients</span>
        </a>
        <a href="?page=clinical_records" class="nav-item <?php echo ($current_page == 'clinical_records') ? 'active' : ''; ?>">
            <span class="material-icons">medical_services</span>
            <span>Clinical Records</span>
        </a>
        <a href="?page=staff_wards" class="nav-item <?php echo ($current_page == 'staff_wards') ? 'active' : ''; ?>">
            <span class="material-icons">badge</span>
            <span>Staff and Wards</span>
        </a>
        <a href="?page=input_forms" class="nav-item <?php echo ($current_page == 'input_forms') ? 'active' : ''; ?>">
            <span class="material-icons">edit_note</span>
            <span>Input Forms</span>
        </a>
    </div>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <span class="material-icons" style="font-size: 20px;">person</span>
            </div>
            <div>
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff Member'); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Staff'); ?></div>
            </div>
        </div>
        <a href="#" class="logout-btn" id="logoutBtn">
            <span class="material-icons">logout</span>
            <span>Logout</span>
        </a>
        <div style="margin-top: 20px; font-size: 0.65rem; text-align: center; color: #A0AEC0;">
            © 2026 IPMH
        </div>
    </div>
</aside>