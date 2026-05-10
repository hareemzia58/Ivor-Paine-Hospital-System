<?php
// pages/clinical_records.php - Working logic with beautiful UI

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../php/login.php');
    exit();
}

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
        die("Fatal Error: Could not find db_connect.php");
    }
}

if (!$conn) {
    die("Fatal Error: Database connection failed");
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'complaint_view';

// Get all complaints for dropdowns
$complaints_sql = "SELECT c_code, title, description FROM Complaint ORDER BY title";
$complaints_stmt = sqlsrv_query($conn, $complaints_sql);
$complaints_list = [];
if ($complaints_stmt) {
    while ($row = sqlsrv_fetch_array($complaints_stmt, SQLSRV_FETCH_ASSOC)) {
        $complaints_list[] = $row;
    }
}

// Get all doctors for performance search
$doctors_sql = "SELECT d.d_id, s.fname, s.lname, d.position 
                FROM Doctor d 
                JOIN Staff s ON d.d_id = s.st_id 
                ORDER BY s.lname";
$doctors_stmt = sqlsrv_query($conn, $doctors_sql);
$doctors_list = [];
if ($doctors_stmt) {
    while ($row = sqlsrv_fetch_array($doctors_stmt, SQLSRV_FETCH_ASSOC)) {
        $doctors_list[] = $row;
    }
}

// Handle GET parameters
$selected_complaint = isset($_GET['complaint_id']) ? intval($_GET['complaint_id']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$doctor_search = isset($_GET['doctor_search']) ? intval($_GET['doctor_search']) : 0;

// ============================================
// QUERY #6: For a selected complaint, show:
// - The complaint details
// - All patients who have this complaint
// - Treatments given to those patients (for that specific complaint)
// - The doctor who treated them and their experience
// ============================================
$complaint_data = null;
$complaint_patients = [];

if ($selected_complaint > 0 && $active_tab == 'complaint_view') {
    // First, get the complaint details
    $complaint_detail_sql = "SELECT c_code, title, description FROM Complaint WHERE c_code = ?";
    $detail_stmt = sqlsrv_query($conn, $complaint_detail_sql, [$selected_complaint]);
    if ($detail_stmt) {
        $complaint_data = sqlsrv_fetch_array($detail_stmt, SQLSRV_FETCH_ASSOC);
    }
    
    // Now get all patients with this complaint, their treatments, and doctor experience
    $query6_sql = "
        SELECT 
            c.c_code,
            c.title AS complaint_title,
            c.description AS complaint_desc,
            p.p_id,
            p.fname AS patient_fname,
            p.lname AS patient_lname,
            t.t_code,
            t.startdate,
            t.enddate,
            d.d_id,
            s.fname AS doctor_fname,
            s.lname AS doctor_lname,
            d.position AS doctor_current_position,
            STUFF((
                SELECT ' | ' + pe.establishment + ' (' + pe.position + ', ' + 
                    CONVERT(VARCHAR(10), pe.from_date, 120) + ' to ' + 
                    ISNULL(CONVERT(VARCHAR(10), pe.to_date, 120), 'Present') + ')'
                FROM PrevExperience pe
                WHERE pe.d_id = d.d_id
                FOR XML PATH(''), TYPE
            ).value('.', 'NVARCHAR(MAX)'), 1, 3, '') AS experience_history
        FROM Complaint c
        INNER JOIN PatientRecord pr ON c.c_code = pr.c_code
        INNER JOIN Patient p ON pr.p_id = p.p_id
        INNER JOIN Doctor d ON pr.d_id = d.d_id
        INNER JOIN Staff s ON d.d_id = s.st_id
        LEFT JOIN Treatment t ON t.p_id = p.p_id AND t.d_id = d.d_id
        WHERE c.c_code = ?
        ORDER BY p.p_id, t.startdate DESC
    ";
    
    $query6_stmt = sqlsrv_query($conn, $query6_sql, [$selected_complaint]);
    if ($query6_stmt) {
        while ($row = sqlsrv_fetch_array($query6_stmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['startdate'] instanceof DateTime) {
                $row['startdate'] = $row['startdate']->format('Y-m-d');
            }
            if ($row['enddate'] instanceof DateTime) {
                $row['enddate'] = $row['enddate']->format('Y-m-d');
            }
            $complaint_patients[] = $row;
        }
    }
}

// ============================================
// QUERY #11: Treatments for a complaint between two dates
// ============================================
$date_range_treatments = [];
if ($selected_complaint > 0 && $date_from && $date_to && $active_tab == 'date_range') {
    $query11_sql = "
        SELECT 
            t.t_code,
            t.startdate,
            t.enddate,
            p.p_id,
            CONCAT(p.fname, ' ', p.lname) AS patient_name,
            CONCAT(s.fname, ' ', s.lname) AS doctor_name,
            d.position AS doctor_position,
            c.title AS complaint_title,
            DATEDIFF(DAY, t.startdate, ISNULL(t.enddate, GETDATE())) AS treatment_duration
        FROM Complaint c
        INNER JOIN PatientRecord pr ON c.c_code = pr.c_code
        INNER JOIN Patient p ON pr.p_id = p.p_id
        INNER JOIN Doctor d ON pr.d_id = d.d_id
        INNER JOIN Staff s ON d.d_id = s.st_id
        LEFT JOIN Treatment t ON t.p_id = p.p_id AND t.d_id = d.d_id
        WHERE c.c_code = ? 
            AND (t.startdate >= CAST(? AS DATE) OR t.startdate IS NULL)
            AND (t.startdate <= CAST(? AS DATE) OR t.startdate IS NULL)
        ORDER BY t.startdate
    ";
    $query11_stmt = sqlsrv_query($conn, $query11_sql, [$selected_complaint, $date_from, $date_to]);
    if ($query11_stmt) {
        while ($row = sqlsrv_fetch_array($query11_stmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['startdate'] instanceof DateTime) {
                $row['startdate'] = $row['startdate']->format('Y-m-d');
            }
            if ($row['enddate'] instanceof DateTime) {
                $row['enddate'] = $row['enddate']->format('Y-m-d');
            }
            $date_range_treatments[] = $row;
        }
    }
}

// ============================================
// QUERY #9: Performance history for a particular doctor
// ============================================
$doctor_performance = null;
if ($doctor_search > 0 && $active_tab == 'performance') {
    // Doctor's basic info
    $doctor_info_sql = "
        SELECT 
            d.d_id,
            CONCAT(s.fname, ' ', s.lname) AS doctor_name,
            d.position AS current_position
        FROM Doctor d
        JOIN Staff s ON d.d_id = s.st_id
        WHERE d.d_id = ?
    ";
    $info_stmt = sqlsrv_query($conn, $doctor_info_sql, [$doctor_search]);
    $doctor_info = sqlsrv_fetch_array($info_stmt, SQLSRV_FETCH_ASSOC);
    
    // Doctor's experience at each position
    $doctor_experience_sql = "
        SELECT 
            establishment,
            position AS previous_position,
            from_date,
            to_date,
            DATEDIFF(YEAR, from_date, ISNULL(to_date, GETDATE())) AS years_in_position
        FROM PrevExperience
        WHERE d_id = ?
        ORDER BY from_date DESC
    ";
    $exp_stmt = sqlsrv_query($conn, $doctor_experience_sql, [$doctor_search]);
    $doctor_experience = [];
    if ($exp_stmt) {
        while ($row = sqlsrv_fetch_array($exp_stmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['from_date'] instanceof DateTime) {
                $row['from_date'] = $row['from_date']->format('Y-m-d');
            }
            if ($row['to_date'] instanceof DateTime) {
                $row['to_date'] = $row['to_date']->format('Y-m-d');
            }
            $doctor_experience[] = $row;
        }
    }
    
    // Doctor's patients and treatment outcomes - join through PatientRecord
    $doctor_patients_sql = "
        SELECT DISTINCT
            p.p_id,
            CONCAT(p.fname, ' ', p.lname) AS patient_name,
            c.title AS complaint_title,
            c.c_code,
            t.t_code,
            t.startdate,
            t.enddate,
            CASE 
                WHEN t.enddate IS NOT NULL AND t.enddate <= GETDATE() THEN 'Completed'
                WHEN t.enddate IS NULL AND t.startdate <= GETDATE() THEN 'Ongoing'
                WHEN t.startdate IS NULL THEN 'No Treatment'
                ELSE 'Scheduled'
            END AS treatment_status
        FROM Doctor d
        INNER JOIN PatientRecord pr ON d.d_id = pr.d_id
        INNER JOIN Patient p ON pr.p_id = p.p_id
        INNER JOIN Complaint c ON pr.c_code = c.c_code
        LEFT JOIN Treatment t ON t.p_id = p.p_id AND t.d_id = d.d_id
        WHERE d.d_id = ?
        ORDER BY p.p_id, t.startdate DESC
    ";
    $patients_stmt = sqlsrv_query($conn, $doctor_patients_sql, [$doctor_search]);
    $doctor_patients = [];
    if ($patients_stmt) {
        while ($row = sqlsrv_fetch_array($patients_stmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['startdate'] instanceof DateTime) {
                $row['startdate'] = $row['startdate']->format('Y-m-d');
            }
            if ($row['enddate'] instanceof DateTime) {
                $row['enddate'] = $row['enddate']->format('Y-m-d');
            }
            $doctor_patients[] = $row;
        }
    }
    
    $total_patients = count($doctor_patients);
    $completed_treatments = count(array_filter($doctor_patients, function($p) { return $p['treatment_status'] == 'Completed'; }));
    $ongoing_treatments = count(array_filter($doctor_patients, function($p) { return $p['treatment_status'] == 'Ongoing'; }));
    
    $doctor_performance = [
        'info' => $doctor_info,
        'experience' => $doctor_experience,
        'patients' => $doctor_patients,
        'total_patients' => $total_patients,
        'completed_treatments' => $completed_treatments,
        'ongoing_treatments' => $ongoing_treatments
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinical Records | IPMH</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        /* Clinical Records Page Styles */
        .clinical-records-container {
            max-width: 1400px;
            margin: 0 auto;
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
        
        .clinical-tabs {
            background: white;
            border-radius: 60px;
            padding: 6px;
            margin-bottom: 32px;
            display: inline-flex;
            gap: 8px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }
        
        .clinical-tab {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 28px;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 500;
            color: #4A5568;
            text-decoration: none;
            transition: all 0.3s ease;
            background: transparent;
            border: none;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
        }
        
        .clinical-tab .material-icons {
            font-size: 20px;
        }
        
        .clinical-tab:hover {
            background: rgba(30, 58, 95, 0.08);
            color: #1E3A5F;
        }
        
        .clinical-tab.active {
            background: linear-gradient(135deg, #1E3A5F 0%, #2C527A 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(30, 58, 95, 0.25);
        }
        
        .clinical-subtabs {
            background: #F1F5F9;
            border-radius: 40px;
            padding: 4px;
            display: inline-flex;
            gap: 4px;
            margin-bottom: 28px;
        }
        
        .clinical-subtab {
            padding: 8px 24px;
            border-radius: 36px;
            font-size: 0.85rem;
            font-weight: 500;
            color: #4A5568;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        
        .clinical-subtab:hover {
            background: rgba(30, 58, 95, 0.1);
        }
        
        .clinical-subtab.active {
            background: linear-gradient(135deg, #1E3A5F 0%, #2C527A 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(30, 58, 95, 0.25);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #4A5568;
            letter-spacing: 0.3px;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 12px 16px;
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            background: white;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #2C527A;
            box-shadow: 0 0 0 3px rgba(44, 82, 122, 0.1);
        }
        
        .date-input-group {
            position: relative;
        }
        
        .date-input-group .material-icons {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #A0AEC0;
            font-size: 18px;
            pointer-events: none;
        }
        
        .date-input-group input {
            padding-left: 42px;
            width: 100%;
        }
        
        .results-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin-top: 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }
        
        .treatment-item,
        .performance-item {
            padding: 20px;
            border: 1px solid #E8ECF0;
            border-radius: 16px;
            margin-bottom: 16px;
            transition: all 0.2s;
            background: white;
        }
        
        .treatment-item:hover,
        .performance-item:hover {
            border-color: #2C527A;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .doctor-card {
            background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 28px;
            border: 1px solid #E2E8F0;
        }
        
        .doctor-avatar {
            width: 64px;
            height: 64px;
            border-radius: 32px;
            background: linear-gradient(135deg, #1E3A5F 0%, #2C527A 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: 600;
        }
        
        .grade-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .grade-Excellent { background: #C6F6D5; color: #22543D; }
        .grade-Good { background: #BEE3F8; color: #2C5282; }
        .grade-Satisfactory { background: #FEEBC8; color: #7B341E; }
        .grade-Needs-Improvement { background: #FED7D7; color: #742A2A; }
        
        .status-completed {
            background: #C6F6D5;
            color: #22543D;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-ongoing {
            background: #FEF3C7;
            color: #92400E;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-no-treatment {
            background: #E2E8F0;
            color: #4A5568;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1E3A5F 0%, #2C527A 100%);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Inter', sans-serif;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(30, 58, 95, 0.25);
        }
        
        .btn-secondary {
            background: #F1F5F9;
            color: #4A5568;
            border: 1px solid #E2E8F0;
            padding: 10px 24px;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        
        .btn-secondary:hover {
            background: #E2E8F0;
        }
        
        .search-doctor-input {
            position: relative;
            margin-bottom: 24px;
        }
        
        .search-doctor-input input {
            width: 100%;
            padding: 14px 20px;
            border: 1.5px solid #E2E8F0;
            border-radius: 50px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            padding-left: 48px;
        }
        
        .search-doctor-input .material-icons {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #A0AEC0;
        }
        
        .doctor-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            margin-top: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            z-index: 100;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .doctor-suggestion-item {
            padding: 12px 20px;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid #F1F5F9;
        }
        
        .doctor-suggestion-item:hover {
            background: #F8FAFC;
        }
        
        .doctor-suggestion-name {
            font-weight: 600;
            color: #1A2B3C;
            margin-bottom: 4px;
        }
        
        .doctor-suggestion-specialty {
            font-size: 0.75rem;
            color: #6B7E92;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #F8FAFC;
            border-radius: 20px;
        }
        
        .empty-state .material-icons {
            font-size: 64px;
            color: #A0AEC0;
            margin-bottom: 16px;
        }
        
        .complaint-preview {
            background: #F8FAFC;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        
        .complaint-preview h4 {
            color: #1E3A5F;
            margin-bottom: 8px;
        }
        
        .complaint-preview p {
            color: #4A5568;
            font-size: 0.85rem;
        }
        
        .experience-text {
            background: #F8FAFC;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            line-height: 1.5;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #F8FAFC 0%, #FFFFFF 100%);
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1E3A5F;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #718096;
            margin-top: 4px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            text-align: left;
            padding: 12px 16px;
            background: #F8FAFC;
            font-size: 0.75rem;
            font-weight: 600;
            color: #4A5568;
            border-bottom: 1px solid #E2E8F0;
        }
        
        .data-table td {
            padding: 12px 16px;
            font-size: 0.85rem;
            color: #2D3748;
            border-bottom: 1px solid #F0F2F5;
        }
        
        .data-table tr:hover td {
            background: #F8FAFC;
        }
        
        .experience-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #E2E8F0;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .experience-establishment {
            font-weight: 600;
            color: #1E3A5F;
        }
        
        .experience-date {
            font-size: 0.7rem;
            color: #718096;
        }
        
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            .clinical-tab span:last-child {
                display: none;
            }
            .clinical-tab {
                padding: 12px 20px;
            }
        }
    </style>
</head>
<body>

<div class="clinical-records-container">
    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1 class="page-title">Clinical Records</h1>
        <p class="page-subtitle">View complaints, treatments, doctor experiences, and performance history</p>
    </div>
    
    <!-- Main Tabs -->
    <div class="clinical-tabs">
        <a href="?page=clinical_records&tab=complaint_view" class="clinical-tab <?php echo $active_tab == 'complaint_view' ? 'active' : ''; ?>">
            <span class="material-icons">medical_services</span>
            <span>Complaints & Treatments</span>
        </a>
        <a href="?page=clinical_records&tab=performance" class="clinical-tab <?php echo $active_tab == 'performance' ? 'active' : ''; ?>">
            <span class="material-icons">trending_up</span>
            <span>Performance & History</span>
        </a>
    </div>
    
    <!-- ============================================ -->
    <!-- COMPLAINTS & TREATMENTS TAB -->
    <!-- ============================================ -->
    <?php if ($active_tab == 'complaint_view'): ?>
    <div>
        <div class="results-card">
            <!-- Sub Tabs -->
            <div class="clinical-subtabs">
                <a href="?page=clinical_records&tab=complaint_view&subtab=complaint-view" class="clinical-subtab active">Complaint View</a>
                <a href="?page=clinical_records&tab=date_range" class="clinical-subtab">Date Range Filter</a>
            </div>
            
            <!-- Complaint View Subtab -->
            <div id="complaintView">
                <div class="filter-group" style="margin-bottom: 24px;">
                    <label>Select Complaint</label>
                    <form method="GET" action="">
                        <input type="hidden" name="page" value="clinical_records">
                        <input type="hidden" name="tab" value="complaint_view">
                        <select name="complaint_id" onchange="this.form.submit()" style="width: 100%;">
                            <option value=""> Select a Complaint </option>
                            <?php foreach ($complaints_list as $complaint): ?>
                                <option value="<?php echo $complaint['c_code']; ?>" 
                                    <?php echo $selected_complaint == $complaint['c_code'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($complaint['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                
                <?php if ($selected_complaint > 0 && $complaint_data): ?>
                    <div class="complaint-preview">
                        <h4><?php echo htmlspecialchars($complaint_data['title']); ?></h4>
                        <p><?php echo htmlspecialchars($complaint_data['description'] ?? 'No description available'); ?></p>
                    </div>
                    
                    <?php if (!empty($complaint_patients)): ?>
                        <h3 style="margin-bottom: 16px; color: #1E3A5F;">
                            <span class="material-icons" style="vertical-align: middle;">people</span>
                            Patients with this Complaint & Their Treatments
                        </h3>
                        
                        <?php 
                        // Group by patient
                        $patients_grouped = [];
                        foreach ($complaint_patients as $item) {
                            $patient_key = $item['p_id'];
                            if (!isset($patients_grouped[$patient_key])) {
                                $patients_grouped[$patient_key] = [
                                    'patient_name' => $item['patient_fname'] . ' ' . $item['patient_lname'],
                                    'doctors' => []
                                ];
                            }
                            $doctor_key = $item['d_id'];
                            if (!isset($patients_grouped[$patient_key]['doctors'][$doctor_key])) {
                                $patients_grouped[$patient_key]['doctors'][$doctor_key] = [
                                    'doctor_name' => $item['doctor_fname'] . ' ' . $item['doctor_lname'],
                                    'current_position' => $item['doctor_current_position'],
                                    'experience_history' => $item['experience_history'],
                                    'treatments' => []
                                ];
                            }
                            if ($item['t_code']) {
                                $patients_grouped[$patient_key]['doctors'][$doctor_key]['treatments'][] = $item;
                            }
                        }
                        ?>
                        
                        <?php foreach ($patients_grouped as $patient): ?>
                            <div class="treatment-item">
                                <div style="font-weight: 600; color: #1E3A5F; margin-bottom: 12px; font-size: 1rem;">
                                     Patient: <?php echo htmlspecialchars($patient['patient_name']); ?>
                                </div>
                                <?php foreach ($patient['doctors'] as $doctor): ?>
                                    <div style="margin: 16px 0; padding: 12px; background: #F8FAFC; border-radius: 12px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                                            <strong style="color: #1E3A5F;">Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?></strong>
                                            <span class="grade-badge grade-Good"><?php echo htmlspecialchars($doctor['current_position']); ?></span>
                                        </div>
                                        
                                        <div style="margin-bottom: 12px;">
                                            <strong>Experience History:</strong>
                                            <div class="experience-text" style="margin-top: 8px;">
                                                <?php echo htmlspecialchars($doctor['experience_history'] ?: 'No previous experience records found.'); ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($doctor['treatments'])): ?>
                                            <table class="data-table">
                                                <thead><tr><th>Treatment Code</th><th>Start Date</th><th>End Date</th></tr></thead>
                                                <tbody>
                                                    <?php foreach ($doctor['treatments'] as $treatment): ?>
                                                        <tr>
                                                            <td>#<?php echo $treatment['t_code']; ?></td>
                                                            <td><?php echo $treatment['startdate'] ?? 'N/A'; ?></td>
                                                            <td><?php echo $treatment['enddate'] ?? 'Ongoing'; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <p style="color: #718096; font-size: 0.85rem;">No treatment records for this doctor with this complaint.</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        
                    <?php else: ?>
                        <div class="empty-state">
                            <span class="material-icons">search_off</span>
                            <p>No patients or treatments found for this complaint.</p>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($selected_complaint > 0): ?>
                    <div class="empty-state">
                        <span class="material-icons">error_outline</span>
                        <p>Complaint not found.</p>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="material-icons">healing</span>
                        <p>Select a complaint from the dropdown above to view associated patients, treatments, and doctor experience.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ============================================ -->
    <!-- DATE RANGE FILTER TAB -->
    <!-- ============================================ -->
    <?php if ($active_tab == 'date_range'): ?>
    <div>
        <div class="results-card">
            <div class="clinical-subtabs">
                <a href="?page=clinical_records&tab=complaint_view" class="clinical-subtab">Complaint View</a>
                <a href="?page=clinical_records&tab=date_range" class="clinical-subtab active">Date Range Filter</a>
            </div>
            
            <div>
                <form method="GET" action="">
                    <input type="hidden" name="page" value="clinical_records">
                    <input type="hidden" name="tab" value="date_range">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label>Complaint Type</label>
                            <select name="complaint_id" required>
                                <option value=""> Select Complaint </option>
                                <?php foreach ($complaints_list as $complaint): ?>
                                    <option value="<?php echo $complaint['c_code']; ?>" <?php echo $selected_complaint == $complaint['c_code'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($complaint['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Start Date</label>
                            <div class="date-input-group">
                                <span class="material-icons">event</span>
                                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" required>
                            </div>
                        </div>
                        <div class="filter-group">
                            <label>End Date</label>
                            <div class="date-input-group">
                                <span class="material-icons">event</span>
                                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-bottom: 24px;">
                        <button type="submit" class="btn-primary">Apply Filters</button>
                        <a href="?page=clinical_records&tab=date_range" class="btn-secondary">Clear Filters</a>
                    </div>
                </form>
                
                <?php if ($selected_complaint > 0 && $date_from && $date_to): ?>
                    <?php 
                    $filtered_treatments = array_filter($date_range_treatments, function($t) {
                        return $t['t_code'] !== null;
                    });
                    ?>
                    <?php if (!empty($filtered_treatments)): ?>
                        <div class="stats-grid">
                            <div class="stat-card"><div class="stat-number"><?php echo count($filtered_treatments); ?></div><div class="stat-label">Treatments Found</div></div>
                            <div class="stat-card"><div class="stat-number"><?php echo count(array_unique(array_column($filtered_treatments, 'p_id'))); ?></div><div class="stat-label">Patients</div></div>
                            <div class="stat-card"><div class="stat-number"><?php echo count(array_unique(array_column($filtered_treatments, 'doctor_name'))); ?></div><div class="stat-label">Doctors</div></div>
                        </div>
                        
                        <?php foreach ($filtered_treatments as $treatment): ?>
                            <div class="treatment-item">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                                    <div>
                                        <div style="font-weight: 600; color: #1E3A5F; margin-bottom: 8px;">
                                            Treatment #<?php echo htmlspecialchars($treatment['t_code']); ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: #6B7E92;">
                                            Patient: <?php echo htmlspecialchars($treatment['patient_name']); ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: #6B7E92;">
                                            Doctor: Dr. <?php echo htmlspecialchars($treatment['doctor_name']); ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: #6B7E92;">
                                            Date: <?php echo $treatment['startdate']; ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div class="grade-badge grade-Good" style="margin-bottom: 8px;">
                                            <?php echo htmlspecialchars($treatment['complaint_title']); ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #A0AEC0;">
                                            <?php echo $treatment['enddate'] ? 'Completed: ' . $treatment['enddate'] : 'Ongoing'; ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #A0AEC0;">
                                            Duration: <?php echo $treatment['treatment_duration']; ?> days
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <span class="material-icons">event_busy</span>
                            <p>No treatments found for the selected complaint between <?php echo $date_from; ?> and <?php echo $date_to; ?>.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="material-icons">date_range</span>
                        <p>Select a complaint and date range to see treatments applied during that period.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ============================================ -->
    <!-- PERFORMANCE & HISTORY TAB -->
    <!-- ============================================ -->
    <?php if ($active_tab == 'performance'): ?>
    <div>
        <div class="results-card">
            <div class="search-doctor-input">
                <span class="material-icons">search</span>
                <form method="GET" action="" id="doctorSearchForm">
                    <input type="hidden" name="page" value="clinical_records">
                    <input type="hidden" name="tab" value="performance">
                    <select name="doctor_search" onchange="this.form.submit()" style="width: 100%; padding: 14px 20px; padding-left: 48px; border: 1.5px solid #E2E8F0; border-radius: 50px; font-size: 0.9rem;">
                        <option value=""> Select a Doctor </option>
                        <?php foreach ($doctors_list as $doctor): ?>
                            <option value="<?php echo $doctor['d_id']; ?>" <?php echo $doctor_search == $doctor['d_id'] ? 'selected' : ''; ?>>
                                Dr. <?php echo htmlspecialchars($doctor['fname'] . ' ' . $doctor['lname']); ?> (<?php echo htmlspecialchars($doctor['position']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            
            <?php if ($doctor_search > 0 && $doctor_performance && $doctor_performance['info']): ?>
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-number"><?php echo $doctor_performance['total_patients']; ?></div><div class="stat-label">Total Patients</div></div>
                    <div class="stat-card"><div class="stat-number"><?php echo $doctor_performance['completed_treatments']; ?></div><div class="stat-label">Completed Treatments</div></div>
                    <div class="stat-card"><div class="stat-number"><?php echo $doctor_performance['ongoing_treatments']; ?></div><div class="stat-label">Ongoing Treatments</div></div>
                </div>
                
                <!-- Doctor Info Card -->
                <div class="doctor-card">
                    <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                        <div class="doctor-avatar"><?php echo substr(htmlspecialchars($doctor_performance['info']['doctor_name']), 0, 1); ?></div>
                        <div>
                            <h2 style="color: #1A2B3C; margin-bottom: 8px;">Dr. <?php echo htmlspecialchars($doctor_performance['info']['doctor_name']); ?></h2>
                            <p style="color: #6B7E92; margin-bottom: 4px;"><?php echo htmlspecialchars($doctor_performance['info']['current_position']); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Experience History Section -->
                <div class="treatment-item">
                    <h3 style="color: #1E3A5F; margin-bottom: 20px;"> Employment History</h3>
                    <?php if (!empty($doctor_performance['experience'])): ?>
                        <?php foreach ($doctor_performance['experience'] as $exp): ?>
                            <div class="experience-item">
                                <div>
                                    <div class="experience-establishment"><?php echo htmlspecialchars($exp['establishment']); ?></div>
                                    <div class="experience-position"><?php echo htmlspecialchars($exp['previous_position']); ?></div>
                                </div>
                                <div class="experience-date">
                                    <?php echo $exp['from_date']; ?> → <?php echo $exp['to_date'] ?? 'Present'; ?>
                                    <?php if ($exp['years_in_position']): ?><br>(<?php echo $exp['years_in_position']; ?> years)<?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #718096;">No previous employment history recorded.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Patient Records Section -->
                <div class="treatment-item">
                    <h3 style="color: #1E3A5F; margin-bottom: 20px;"> Patient Records</h3>
                    <?php if (!empty($doctor_performance['patients'])): ?>
                        <table class="data-table">
                            <thead>
                                <tr><th>Patient</th><th>Complaint</th><th>Treatment #</th><th>Start Date</th><th>End Date</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doctor_performance['patients'] as $patient): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($patient['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($patient['complaint_title']); ?></td>
                                        <td><?php echo $patient['t_code'] ? '#'.$patient['t_code'] : 'No treatment'; ?></td>
                                        <td><?php echo $patient['startdate'] ?? '-'; ?></td>
                                        <td><?php echo $patient['enddate'] ?? ($patient['startdate'] ? 'Ongoing' : '-'); ?></td>
                                        <td><span class="status-<?php echo strtolower(str_replace(' ', '', $patient['treatment_status'])); ?>"><?php echo $patient['treatment_status']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 40px;">
                            <span class="material-icons">folder_open</span>
                            <p>No patient records found for this doctor.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($doctor_search > 0): ?>
                <div class="empty-state">
                    <span class="material-icons">error_outline</span>
                    <p>No performance data found for the selected doctor.</p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="material-icons">person_search</span>
                    <p>Select a doctor from the dropdown above to view their performance history and employment experience.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
