<?php
// pages/multi_complaint_patients.php

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

// 4. GET MULTI-COMPLAINT PATIENTS (2 or more complaints)
$sql = "SELECT 
            p.p_id,
            p.fname,
            p.lname,
            COUNT(DISTINCT c.c_code) as complaint_count,
            COUNT(DISTINCT t.t_code) as treatment_count,
            w.name AS ward_name,
            CASE WHEN p.discharge_date IS NULL THEN 'Active' ELSE 'Discharged' END as status,
            p.admission_date,
            p.discharge_date
        FROM Patient p
        LEFT JOIN Ward w ON p.w_id = w.w_id
        LEFT JOIN Complaint c ON p.p_id = c.p_id
        LEFT JOIN Treatment t ON p.p_id = t.p_id
        WHERE c.c_code IS NOT NULL
        GROUP BY p.p_id, p.fname, p.lname, w.name, p.discharge_date, p.admission_date
        HAVING COUNT(DISTINCT c.c_code) >= 2
        ORDER BY complaint_count DESC, treatment_count DESC";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Get total count of multi-complaint patients
$total_count_sql = "SELECT COUNT(*) as count FROM (
    SELECT p.p_id
    FROM Patient p
    LEFT JOIN Complaint c ON p.p_id = c.p_id
    WHERE c.c_code IS NOT NULL
    GROUP BY p.p_id
    HAVING COUNT(DISTINCT c.c_code) >= 2
) as multi_complaint_patients";
$total_count_stmt = sqlsrv_query($conn, $total_count_sql);
$total_count = sqlsrv_fetch_array($total_count_stmt, SQLSRV_FETCH_ASSOC)['count'];

// Get patients grouped by complaint count for chart
$distribution_sql = "SELECT 
    complaint_count,
    COUNT(*) as patient_count
FROM (
    SELECT 
        p.p_id,
        COUNT(DISTINCT c.c_code) as complaint_count
    FROM Patient p
    LEFT JOIN Complaint c ON p.p_id = c.p_id
    WHERE c.c_code IS NOT NULL
    GROUP BY p.p_id
    HAVING COUNT(DISTINCT c.c_code) >= 2
) as complaint_counts
GROUP BY complaint_count
ORDER BY complaint_count";
$distribution_stmt = sqlsrv_query($conn, $distribution_sql);
$distribution = [];
if ($distribution_stmt !== false) {
    while ($row = sqlsrv_fetch_array($distribution_stmt, SQLSRV_FETCH_ASSOC)) {
        $distribution[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Multi-Complaint Patients - Ivor Paine Hospital</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f4f7fc;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #1E3A5F 0%, #2C527A 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-icon .material-icons {
            color: white;
            font-size: 28px;
        }
        
        .stat-info h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1E3A5F;
        }
        
        .stat-info p {
            color: #718096;
            font-size: 0.85rem;
            margin-top: 4px;
        }
        
        /* Distribution Chart */
        .distribution-section {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1E3A5F;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid #E2E8F0;
            padding-bottom: 12px;
        }
        
        .chart-container {
            display: flex;
            gap: 20px;
            align-items: flex-end;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .chart-bar {
            flex: 1;
            min-width: 80px;
            text-align: center;
        }
        
        .bar {
            background: linear-gradient(180deg, #1E3A5F 0%, #2C527A 100%);
            width: 100%;
            border-radius: 8px 8px 0 0;
            transition: height 0.3s ease;
        }
        
        .bar-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1E3A5F;
            margin-bottom: 8px;
        }
        
        .bar-label {
            margin-top: 12px;
            font-size: 0.8rem;
            color: #718096;
        }
        
        /* Table Styles */
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
            background: #F8FAFC;
            border-bottom: 2px solid #E2E8F0;
        }
        
        .table-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1E3A5F;
        }
        
        .badge {
            background: #1E3A5F;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
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
            cursor: pointer;
        }
        
        .complaint-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #FEF3C7;
            color: #92400E;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .treatment-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #DBEAFE;
            color: #1E40AF;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
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
        
        .ward-name {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .ward-name .material-icons {
            font-size: 14px;
            color: #718096;
        }
        
        .no-data {
            text-align: center;
            padding: 60px;
            color: #718096;
        }
        
        .no-data .material-icons {
            font-size: 48px;
            color: #CBD5E0;
            margin-bottom: 12px;
        }
        
        /* Modal Styles (reuse from patients.php) */
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .chart-bar {
                width: 100%;
            }
            
            .bar {
                height: 40px !important;
                width: 100% !important;
                border-radius: 8px;
            }
        }
    </style>
</head>
<body>

<!-- Stats Overview -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">warning</span></div>
        <div class="stat-info">
            <h3><?php echo $total_count; ?></h3>
            <p>Multi-Complaint Patients</p>
            <small style="color: #F59E0B;">⚠️ 2+ complaints each</small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">local_hospital</span></div>
        <div class="stat-info">
            <h3><?php 
                $unique_wards_sql = "SELECT COUNT(DISTINCT w.w_id) as count 
                                    FROM Patient p 
                                    LEFT JOIN Ward w ON p.w_id = w.w_id
                                    LEFT JOIN Complaint c ON p.p_id = c.p_id
                                    WHERE c.c_code IS NOT NULL
                                    GROUP BY p.p_id
                                    HAVING COUNT(DISTINCT c.c_code) >= 2";
                $unique_wards_stmt = sqlsrv_query($conn, $unique_wards_sql);
                $ward_count = $unique_wards_stmt ? sqlsrv_num_rows($unique_wards_stmt) : 0;
                echo $ward_count;
            ?></h3>
            <p>Departments Involved</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">trending_up</span></div>
        <div class="stat-info">
            <h3><?php 
                $avg_sql = "SELECT AVG(complaint_count) as avg_count FROM (
                    SELECT COUNT(DISTINCT c.c_code) as complaint_count
                    FROM Patient p
                    LEFT JOIN Complaint c ON p.p_id = c.p_id
                    WHERE c.c_code IS NOT NULL
                    GROUP BY p.p_id
                    HAVING COUNT(DISTINCT c.c_code) >= 2
                ) as multi_complaint";
                $avg_stmt = sqlsrv_query($conn, $avg_sql);
                $avg_row = sqlsrv_fetch_array($avg_stmt, SQLSRV_FETCH_ASSOC);
                echo round($avg_row['avg_count'], 1);
            ?></h3>
            <p>Avg Complaints per Patient</p>
        </div>
    </div>
</div>

<!-- Distribution Chart -->
<?php if (count($distribution) > 0): ?>
<div class="distribution-section">
    <div class="section-title">
        <span class="material-icons">bar_chart</span>
        Complaint Distribution
    </div>
    <div class="chart-container">
        <?php 
        $max_count = max(array_column($distribution, 'patient_count'));
        foreach ($distribution as $item): 
            $height = ($item['patient_count'] / $max_count) * 200;
        ?>
            <div class="chart-bar">
                <div class="bar-value"><?php echo $item['patient_count']; ?></div>
                <div class="bar" style="height: <?php echo $height; ?>px; min-height: 30px;"></div>
                <div class="bar-label"><?php echo $item['complaint_count']; ?> Complaint(s)</div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Multi-Complaint Patients Table -->
<div class="table-container">
    <div class="table-header">
        <h3>
            <span class="material-icons" style="vertical-align: middle;">warning</span>
            Patients with Multiple Complaints
        </h3>
        <span class="badge"><?php echo $total_count; ?> Patients</span>
    </div>
    <div class="table-responsive">
        <table class="patients-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Complaints</th>
                    <th>Treatments</th>
                    <th>Ward</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $row_count = 0;
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
                    $row_count++;
                    $display_id = 'P' . str_pad($row['p_id'], 3, '0', STR_PAD_LEFT);
                ?>
                <tr onclick="viewRecord(<?php echo $row['p_id']; ?>)" style="cursor: pointer;">
                    <td>
                        <strong><?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?></strong>
                        <br>
                        <small style="color: #718096;">ID: <?php echo $display_id; ?></small>
                    </td>
                    <td>
                        <span class="complaint-badge">
                            <span class="material-icons" style="font-size: 14px;">healing</span>
                            <?php echo $row['complaint_count']; ?> complaint<?php echo $row['complaint_count'] != 1 ? 's' : ''; ?>
                        </span>
                    </td>
                    <td>
                        <span class="treatment-badge">
                            <span class="material-icons" style="font-size: 14px;">medical_services</span>
                            <?php echo $row['treatment_count']; ?> treatment<?php echo $row['treatment_count'] != 1 ? 's' : ''; ?>
                        </span>
                    </td>
                    <td>
                        <span class="ward-name">
                            <span class="material-icons">domain</span>
                            <?php echo htmlspecialchars($row['ward_name'] ?? 'N/A'); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $row['status'] === 'Active' ? 'status-active' : 'status-discharged'; ?>">
                            <?php echo $row['status']; ?>
                        </span>
                    </td>
                    <td onclick="event.stopPropagation();">
                        <button class="view-record-btn" onclick="viewRecord(<?php echo $row['p_id']; ?>)">
                            <span class="material-icons" style="font-size: 14px;">visibility</span>
                            View Record
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                
                <?php if ($row_count === 0): ?>
                <tr>
                    <td colspan="6" class="no-data">
                        <span class="material-icons">check_circle</span>
                        <p>No patients with multiple complaints found</p>
                        <small>Patients need at least 2 complaints to appear here</small>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Patient Modal -->
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
// View patient record with full details
function viewRecord(patientId) {
    const modal = document.getElementById('patientModal');
    const modalBody = document.getElementById('modalBody');
    
    modal.classList.add('active');
    modalBody.innerHTML = '<div class="loading-spinner">Loading patient record...</div>';
    
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
        <style>
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
        </style>
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
                All Complaints (${complaints.length})
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
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('patientModal').classList.contains('active')) {
            closeModal();
        }
    });
}
</script>

</body>
</html>