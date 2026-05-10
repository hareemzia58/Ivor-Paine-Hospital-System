<?php
// pages/patients.php

// 1. Prevent session double-start notices
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Security Check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../php/login.php');
    exit();
}

/**
 * 3. DATABASE CONNECTION LOGIC
 */
$connectionPath = 'php/db_connect.php'; 

if (file_exists($connectionPath)) {
    require_once $connectionPath;
    if (!isset($conn) && function_exists('db')) {
        $conn = db(); 
    }
} else {
    $connectionPath = '../php/db_connect.php';
    if (file_exists($connectionPath)) {
        require_once $connectionPath;
        if (!isset($conn) && function_exists('db')) {
            $conn = db(); 
        }
    } else {
        die("Fatal Error: Could not find db_connect.php. Check your file paths.");
    }
}

if (!$conn) {
    die("Fatal Error: Database connection variable \$conn is not initialized.");
}

// 4. HANDLE SEARCH AND FILTERS
$search = isset($_GET['search']) ? $_GET['search'] : '';
$ward_filter = isset($_GET['ward']) ? $_GET['ward'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// 5. BUILD THE QUERY
$sql = "SELECT DISTINCT 
            p.p_id,
            p.fname,
            p.lname,
            p.dob,
            p.admission_date,
            p.discharge_date,
            p.bed_no,
            w.name AS ward_name
        FROM Patient p
        LEFT JOIN Ward w ON p.w_id = w.w_id
        WHERE 1=1";

$params = [];
if (!empty($search)) {
    $sql .= " AND (
        p.fname LIKE ? 
        OR p.lname LIKE ? 
        OR CAST(p.p_id AS VARCHAR) LIKE ? 
        OR ('P' + RIGHT('000' + CAST(p.p_id AS VARCHAR), 3)) LIKE ?
    )";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($ward_filter)) {
    $sql .= " AND w.name = ?";
    $params[] = $ward_filter;
}

if (!empty($status_filter)) {
    if ($status_filter == 'Active') {
        $sql .= " AND p.discharge_date IS NULL";
    } elseif ($status_filter == 'Discharged') {
        $sql .= " AND p.discharge_date IS NOT NULL";
    }
}

$sql .= " ORDER BY p.admission_date DESC";

// Execute main query
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Get distinct wards for filter dropdown
$ward_sql = "SELECT DISTINCT name FROM Ward ORDER BY name";
$ward_stmt = sqlsrv_query($conn, $ward_sql);
$wards = [];
if ($ward_stmt !== false) {
    while ($row = sqlsrv_fetch_array($ward_stmt, SQLSRV_FETCH_ASSOC)) {
        $wards[] = $row['name'];
    }
}

// Get stats
$active_sql = "SELECT COUNT(*) as count FROM Patient WHERE discharge_date IS NULL";
$active_stmt = sqlsrv_query($conn, $active_sql);
$active_count = sqlsrv_fetch_array($active_stmt, SQLSRV_FETCH_ASSOC)['count'];

$ward_count_sql = "SELECT COUNT(*) as count FROM Ward";
$ward_count_stmt = sqlsrv_query($conn, $ward_count_sql);
$ward_count = sqlsrv_fetch_array($ward_count_stmt, SQLSRV_FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patients - Ivor Paine Hospital</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f4f7fc;
        }
        
        .page-header {
            margin-bottom: 32px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1E3A5F;
            margin-bottom: 8px;
        }
        
        .page-subtitle {
            font-size: 1rem;
            color: #2D3748;
            opacity: 0.6;
        }
        
        .welcome-card {
            background: white;
            border-radius: 20px;
            padding: 28px 32px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 24px;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }
        
        .welcome-card h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1E3A5F;
            margin-bottom: 8px;
        }
        
        .welcome-card p {
            color: #718096;
        }
        
        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .search-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            position: relative;
        }
        
        .search-icon {
            position: absolute;
            left: 12px;
            color: #718096;
            pointer-events: none;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 12px 12px 44px;
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #2C527A;
            box-shadow: 0 0 0 3px rgba(44, 82, 122, 0.1);
        }
        
        .filter-controls {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .filter-select {
            flex: 1;
            padding: 10px 12px;
            border: 1.5px solid #E2E8F0;
            border-radius: 10px;
            background: white;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .reset-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #1E3A5F 0%, #2C527A 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-icon .material-icons {
            color: white;
        }
        
        .stat-info h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1E3A5F;
        }
        
        .stat-info p {
            color: #718096;
            font-size: 0.85rem;
        }
        
        .table-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #E2E8F0;
        }
        
        .table-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1E3A5F;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .patients-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .patients-table th {
            text-align: left;
            padding: 16px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #4A5568;
            background: #F8FAFC;
            border-bottom: 1px solid #E2E8F0;
        }
        
        .patients-table td {
            padding: 16px;
            font-size: 0.9rem;
            color: #2D3748;
            border-bottom: 1px solid #F0F2F5;
        }
        
        .patients-table tbody tr:hover {
            background: #F8FAFC;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active {
            background: #C6F6D5;
            color: #22543D;
        }
        
        .status-discharged {
            background: #FED7D7;
            color: #742A2A;
        }
        
        .view-record-btn {
            background: #1E3A5F;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        
        .view-record-btn:hover {
            background: #2C527A;
            transform: translateY(-1px);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 24px;
            max-width: 700px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: #1E3A5F;
            color: white;
            border-radius: 24px 24px 0 0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .modal-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: white;
            transition: opacity 0.2s;
        }
        
        .close-modal:hover {
            opacity: 0.8;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .loading-spinner {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        
        /* Patient Detail Styles */
        .patient-profile-header {
            background: linear-gradient(135deg, #1E3A5F 0%, #2C527A 100%);
            color: white;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
        }
        
        .patient-name-large {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 16px;
        }
        
        .patient-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .info-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            opacity: 0.8;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .section-card {
            background: #F8FAFC;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1E3A5F;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid #E2E8F0;
            padding-bottom: 8px;
        }
        
        .complaint-item {
            background: white;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #E2E8F0;
        }
        
        .complaint-item:hover {
            transform: translateX(4px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .complaint-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .complaint-title {
            font-weight: 700;
            color: #1E3A5F;
            font-size: 1rem;
        }
        
        .complaint-code {
            font-size: 0.7rem;
            color: #718096;
            background: #F1F5F9;
            padding: 2px 8px;
            border-radius: 12px;
        }
        
        .complaint-description {
            color: #4A5568;
            font-size: 0.85rem;
            margin: 8px 0;
        }
        
        .treatment-count {
            font-size: 0.7rem;
            color: #2C527A;
            margin-top: 8px;
        }
        
        .treatment-details {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #E2E8F0;
            display: none;
        }
        
        .treatment-details.show {
            display: block;
        }
        
        .treatment-item {
            background: #F8FAFC;
            padding: 12px;
            border-radius: 8px;
            margin-top: 8px;
        }
        
        .treatment-item strong {
            color: #1E3A5F;
        }
        
        .progress-note {
            background: white;
            border-left: 4px solid #2C527A;
            padding: 16px;
            margin-bottom: 12px;
            border-radius: 8px;
        }
        
        .progress-date {
            font-weight: 700;
            color: #1E3A5F;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }
        
        .progress-text {
            color: #4A5568;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 8px;
        }
        
        .progress-doctor {
            font-size: 0.75rem;
            color: #718096;
            font-style: italic;
        }
        
        .no-data {
            color: #718096;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>

<div class="page-header">
    <h1 class="page-title">Patients</h1>
    <p class="page-subtitle">Manage and view all patient records</p>
</div>

<div class="welcome-card">
    <h3>Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>!</h3>
    <p>You are logged in as <?php echo htmlspecialchars($_SESSION['role'] ?? 'Staff'); ?></p>
</div>

<div class="filter-section">
    <div class="search-bar">
        <span class="material-icons search-icon">search</span>
        <form method="GET" action="" id="searchForm" style="flex: 1;">
            <input type="text" 
                   name="search" 
                   id="searchInput"
                   placeholder="Search by patient name or ID..." 
                   value="<?php echo htmlspecialchars($search); ?>"
                   class="search-input">
            <input type="hidden" name="ward" id="wardHidden" value="<?php echo htmlspecialchars($ward_filter); ?>">
            <input type="hidden" name="status" id="statusHidden" value="<?php echo htmlspecialchars($status_filter); ?>">
        </form>
    </div>
    
    <div class="filter-controls">
        <select id="wardFilter" class="filter-select">
            <option value="">Filter by Ward - All</option>
            <?php foreach ($wards as $ward): ?>
                <option value="<?php echo htmlspecialchars($ward); ?>" 
                    <?php echo $ward_filter == $ward ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($ward); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <select id="statusFilter" class="filter-select">
            <option value="">Filter by Status - All</option>
            <option value="Active" <?php echo $status_filter == 'Active' ? 'selected' : ''; ?>>Active</option>
            <option value="Discharged" <?php echo $status_filter == 'Discharged' ? 'selected' : ''; ?>>Discharged</option>
        </select>
        
        <button id="resetFilters" class="reset-btn">
            <span class="material-icons">refresh</span>
            Reset
        </button>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">people</span></div>
        <div class="stat-info">
            <h3><?php echo $active_count; ?></h3>
            <p>Active Patients</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">local_hospital</span></div>
        <div class="stat-info">
            <h3><?php echo $ward_count; ?></h3>
            <p>Departments</p>
        </div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h3>Patient Records</h3>
    </div>
    <div class="table-responsive">
        <table class="patients-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>DOB</th>
                    <th>Ward</th>
                    <th>Bed</th>
                    <th>Admission</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $row_count = 0;
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
                    $row_count++;
                    $status = $row['discharge_date'] === null ? 'Active' : 'Discharged';
                    $status_class = $status === 'Active' ? 'status-active' : 'status-discharged';
                    $display_id = 'P' . str_pad($row['p_id'], 3, '0', STR_PAD_LEFT);
                    $dob = $row['dob'] instanceof DateTime ? $row['dob']->format('Y-m-d') : $row['dob'];
                    $admission = $row['admission_date'] instanceof DateTime ? $row['admission_date']->format('Y-m-d') : $row['admission_date'];
                ?>
                <tr>
                    <td><?php echo $display_id; ?></td>
                    <td><strong><?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?></strong></td>
                    <td><?php echo $dob; ?></td>
                    <td><?php echo htmlspecialchars($row['ward_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['bed_no'] ?? 'N/A'); ?></td>
                    <td><?php echo $admission; ?></td>
                    <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                    <td>
                        <button class="view-record-btn" onclick="viewRecord(<?php echo $row['p_id']; ?>)">
                            <span class="material-icons" style="font-size: 14px;">visibility</span>
                            View Record
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                
                <?php if ($row_count === 0): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 60px;">
                        <span class="material-icons" style="font-size: 48px; color: #CBD5E0;">search_off</span>
                        <p style="color: #718096; margin-top: 12px;">No patients found</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="patientModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Full Medical Record</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="loading-spinner">Loading patient record...</div>
        </div>
    </div>
</div>

<script>
// Apply filters function
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const ward = document.getElementById('wardFilter').value;
    const status = document.getElementById('statusFilter').value;
    const url = new URL(window.location.href);
    
    if (search) url.searchParams.set('search', search);
    else url.searchParams.delete('search');
    if (ward) url.searchParams.set('ward', ward);
    else url.searchParams.delete('ward');
    if (status) url.searchParams.set('status', status);
    else url.searchParams.delete('status');
    
    window.location.href = url.toString();
}

// Event listeners
if (document.getElementById('wardFilter')) {
    document.getElementById('wardFilter').addEventListener('change', applyFilters);
}
if (document.getElementById('statusFilter')) {
    document.getElementById('statusFilter').addEventListener('change', applyFilters);
}
if (document.getElementById('resetFilters')) {
    document.getElementById('resetFilters').addEventListener('click', () => {
        window.location.href = window.location.pathname;
    });
}

// Debounced search
let searchTimeout;
if (document.getElementById('searchInput')) {
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 500);
    });
}

// View patient record with full details
function viewRecord(patientId) {
    const modal = document.getElementById('patientModal');
    const modalBody = document.getElementById('modalBody');
    
    modal.classList.add('active');
    modalBody.innerHTML = '<div class="loading-spinner">Loading patient record...</div>';
    
    // Fix: Use correct path from /php/ directory
    const url = `../pages/get_patient_details.php?id=${patientId}`;
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayFullPatientRecord(data);
            } else {
                modalBody.innerHTML = `<div class="loading-spinner">Error: ${data.message}</div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = '<div class="loading-spinner">Error loading patient record. Please try again.</div>';
        });
}

function displayFullPatientRecord(data) {
    const patient = data.patient;
    const complaints = data.complaints || [];
    const treatments = data.treatments || [];
    const progress = data.progress || [];
    
    const patientIdFormatted = 'P' + String(patient.p_id).padStart(3, '0');
    const dob = formatDate(patient.dob);
    const admissionDate = formatDate(patient.admission_date);
    
    // Group treatments by complaint
    const treatmentsByComplaint = {};
    treatments.forEach(treatment => {
        const cCode = treatment.c_code;
        if (!treatmentsByComplaint[cCode]) {
            treatmentsByComplaint[cCode] = [];
        }
        treatmentsByComplaint[cCode].push(treatment);
    });
    
    // Build complaints HTML
    let complaintsHtml = '';
    if (complaints.length > 0) {
        complaints.forEach(complaint => {
            const complaintTreatments = treatmentsByComplaint[complaint.c_code] || [];
            const treatmentCount = complaintTreatments.length;
            
            complaintsHtml += `
                <div class="complaint-item" onclick="toggleTreatments(${complaint.c_code})">
                    <div class="complaint-header">
                        <span class="complaint-title">${escapeHtml(complaint.title)}</span>
                        <span class="complaint-code">CO${String(complaint.c_code).padStart(2, '0')}</span>
                    </div>
                    <div class="complaint-description">
                        ${escapeHtml(complaint.description || 'No description provided')}
                    </div>
                    <div class="treatment-count">
                        <span class="material-icons" style="font-size: 14px; vertical-align: middle;">medical_services</span>
                        ${treatmentCount} treatment${treatmentCount !== 1 ? 's' : ''}
                        <span class="expand-icon material-icons" style="font-size: 16px; margin-left: 8px;">expand_more</span>
                    </div>
                    <div id="treatments-${complaint.c_code}" class="treatment-details">
                        ${generateTreatmentsHtml(complaintTreatments)}
                    </div>
                </div>
            `;
        });
    } else {
        complaintsHtml = '<div class="no-data">No complaints recorded for this patient.</div>';
    }
    
    // Build progress HTML
    let progressHtml = '';
    if (progress && progress.length > 0) {
        progress.forEach(note => {
            progressHtml += `
                <div class="progress-note">
                    <div class="progress-date">📅 ${formatDate(note.date_grade)}</div>
                    <div class="progress-text">${escapeHtml(note.performance || 'No details provided')}</div>
                    <div class="progress-doctor">— ${escapeHtml(note.doctor_name || 'Unknown')}</div>
                </div>
            `;
        });
    } else {
        progressHtml = '<div class="no-data">No progress notes recorded.</div>';
    }
    
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = `
        <div class="patient-profile-header">
            <div class="patient-name-large">
                ${escapeHtml(patient.fname)} ${escapeHtml(patient.lname)}
            </div>
            <div class="patient-info-grid">
                <div class="info-item">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value">${patientIdFormatted}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Ward / Bed</span>
                    <span class="info-value">${escapeHtml(patient.ward_name || 'Not Assigned')} / ${escapeHtml(patient.bed_no || 'N/A')}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Consultant</span>
                    <span class="info-value">${escapeHtml(patient.consultant_name || 'Not Assigned')}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-value">${dob}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Admission Date</span>
                    <span class="info-value">${admissionDate}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="info-value">${patient.discharge_date ? 'Discharged on ' + formatDate(patient.discharge_date) : 'Active'}</span>
                </div>
            </div>
        </div>
        
        <div class="section-card">
            <div class="section-title">
                <span class="material-icons">healing</span>
                Complaints & Treatments
            </div>
            ${complaintsHtml}
        </div>
        
        <div class="section-card">
            <div class="section-title">
                <span class="material-icons">note_alt</span>
                Progress Notes
            </div>
            ${progressHtml}
        </div>
    `;
}

function generateTreatmentsHtml(treatments) {
    if (!treatments.length) return '';
    
    let html = '<div style="margin-top: 8px;"><strong>Treatments:</strong></div>';
    treatments.forEach(treatment => {
        html += `
            <div class="treatment-item">
                <strong>Treatment #${treatment.t_code}</strong><br>
                <small>Started: ${formatDate(treatment.startdate)}</small><br>
                <small>Ended: ${treatment.enddate ? formatDate(treatment.enddate) : 'Ongoing'}</small><br>
                <small>Doctor: ${escapeHtml(treatment.doctor_name || 'Unknown')}</small>
            </div>
        `;
    });
    return html;
}

function toggleTreatments(complaintCode) {
    const treatmentsDiv = document.getElementById(`treatments-${complaintCode}`);
    if (treatmentsDiv) {
        treatmentsDiv.classList.toggle('show');
        const complaintItem = treatmentsDiv.closest('.complaint-item');
        if (complaintItem) {
            complaintItem.classList.toggle('expanded');
        }
    }
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    if (dateString instanceof Date) {
        return dateString.toISOString().split('T')[0];
    }
    if (typeof dateString === 'string') {
        if (dateString.match(/^\d{4}-\d{2}-\d{2}/)) {
            return dateString;
        }
        const date = new Date(dateString);
        if (!isNaN(date.getTime())) {
            return date.toISOString().split('T')[0];
        }
    }
    return dateString;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function closeModal() {
    const modal = document.getElementById('patientModal');
    modal.classList.remove('active');
}

// Close modal when clicking outside
if (document.getElementById('patientModal')) {
    document.getElementById('patientModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('patientModal').classList.contains('active')) {
            closeModal();
        }
    });
}
</script>
</body>
</html>