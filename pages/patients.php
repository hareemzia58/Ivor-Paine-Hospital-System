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

// 5. BUILD THE QUERY for table view
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

// 5.5 GET ENHANCED PATIENT DATA FOR CARDS VIEW (Complaint & Treatment counts)
$enhanced_sql = "SELECT 
    p.p_id,
    p.fname,
    p.lname,
    w.name AS ward_name,
    COUNT(DISTINCT c.c_code) as complaint_count,
    COUNT(DISTINCT t.t_code) as treatment_count,
    CASE WHEN p.discharge_date IS NULL THEN 'Active' ELSE 'Discharged' END as status
FROM Patient p
LEFT JOIN Ward w ON p.w_id = w.w_id
LEFT JOIN Complaint c ON p.p_id = c.p_id
LEFT JOIN Treatment t ON p.p_id = t.p_id
WHERE 1=1";

$enhanced_params = [];
if (!empty($search)) {
    $enhanced_sql .= " AND (p.fname LIKE ? OR p.lname LIKE ? OR CAST(p.p_id AS VARCHAR) LIKE ?)";
    $search_param = "%$search%";
    $enhanced_params = [$search_param, $search_param, $search_param];
}
if (!empty($ward_filter)) {
    $enhanced_sql .= " AND w.name = ?";
    $enhanced_params[] = $ward_filter;
}
if (!empty($status_filter)) {
    if ($status_filter == 'Active') {
        $enhanced_sql .= " AND p.discharge_date IS NULL";
    } elseif ($status_filter == 'Discharged') {
        $enhanced_sql .= " AND p.discharge_date IS NOT NULL";
    }
}

$enhanced_sql .= " GROUP BY p.p_id, p.fname, p.lname, w.name, p.discharge_date
                   ORDER BY complaint_count DESC, treatment_count DESC";

$enhanced_stmt = sqlsrv_query($conn, $enhanced_sql, $enhanced_params);
$dashboard_patients = [];
if ($enhanced_stmt !== false) {
    while ($row = sqlsrv_fetch_array($enhanced_stmt, SQLSRV_FETCH_ASSOC)) {
        foreach ($row as $key => $value) {
            if ($value instanceof DateTime) {
                $row[$key] = $value->format('Y-m-d');
            }
        }
        $dashboard_patients[] = $row;
    }
}

// 6. GET MULTI-COMPLAINT PATIENTS (2 or more complaints)
$multi_sql = "SELECT 
            p.p_id,
            p.fname,
            p.lname,
            COUNT(DISTINCT c.c_code) as complaint_count,
            COUNT(DISTINCT t.t_code) as treatment_count,
            w.name AS ward_name,
            CASE WHEN p.discharge_date IS NULL THEN 'Active' ELSE 'Discharged' END as status
        FROM Patient p
        LEFT JOIN Ward w ON p.w_id = w.w_id
        LEFT JOIN Complaint c ON p.p_id = c.p_id
        LEFT JOIN Treatment t ON p.p_id = t.p_id
        WHERE c.c_code IS NOT NULL
        GROUP BY p.p_id, p.fname, p.lname, w.name, p.discharge_date
        HAVING COUNT(DISTINCT c.c_code) >= 2
        ORDER BY complaint_count DESC, treatment_count DESC";

$multi_stmt = sqlsrv_query($conn, $multi_sql);
$multi_patients = [];
if ($multi_stmt !== false) {
    while ($row = sqlsrv_fetch_array($multi_stmt, SQLSRV_FETCH_ASSOC)) {
        $multi_patients[] = $row;
    }
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

$multi_count = count($multi_patients);
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
        
        /* Tabs Styles */
        .tabs-container {
            background: white;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .tabs {
            display: flex;
            background: #F8FAFC;
            border-bottom: 1px solid #E2E8F0;
            gap: 0;
        }
        
        .tab-btn {
            flex: 1;
            padding: 16px 24px;
            background: transparent;
            border: none;
            font-size: 0.95rem;
            font-weight: 600;
            color: #4A5568;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: 'Inter', sans-serif;
            position: relative;
        }
        
        .tab-btn .material-icons {
            font-size: 20px;
        }
        
        .tab-btn:hover {
            background: #F1F5F9;
            color: #1E3A5F;
        }
        
        .tab-btn.active {
            color: #1E3A5F;
            background: white;
        }
        
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 3px;
            background: #1E3A5F;
        }
        
        .tab-content {
            display: none;
            padding: 24px;
            background: white;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .filter-section {
            background: #F8FAFC;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
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
            border: 1px solid #E2E8F0;
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
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .view-toggle {
            display: flex;
            gap: 8px;
            background: #F1F5F9;
            padding: 4px;
            border-radius: 12px;
        }
        
        .toggle-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            font-weight: 500;
            color: #4A5568;
            transition: all 0.2s;
        }
        
        .toggle-btn.active {
            background: white;
            color: #1E3A5F;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .patient-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid #E2E8F0;
        }
        
        .patient-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, #1E3A5F 0%, #2C527A 100%);
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .card-header h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .patient-id {
            font-size: 0.7rem;
            opacity: 0.8;
            font-family: monospace;
        }
        
        .ward-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .stats-row {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .stat-pill {
            flex: 1;
            text-align: center;
            padding: 12px;
            background: #F8FAFC;
            border-radius: 12px;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1E3A5F;
            display: block;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: #718096;
            margin-top: 4px;
            display: block;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .status-active-card {
            background: #C6F6D5;
            color: #22543D;
        }
        
        .status-discharged-card {
            background: #FED7D7;
            color: #742A2A;
        }
        
        .view-details-btn {
            width: 100%;
            padding: 10px;
            background: #F1F5F9;
            border: none;
            border-radius: 10px;
            color: #1E3A5F;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .view-details-btn:hover {
            background: #E2E8F0;
            transform: translateY(-1px);
        }
        
        .multi-complaint {
            border-left: 4px solid #F59E0B;
        }
        
        .table-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #E2E8F0;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #E2E8F0;
            background: #F8FAFC;
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
            padding: 60px;
        }
        
        .no-data .material-icons {
            font-size: 48px;
            color: #CBD5E0;
            margin-bottom: 12px;
        }
        
        @media (max-width: 768px) {
            .cards-container {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .view-toggle {
                justify-content: center;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab-btn.active::after {
                display: none;
            }
            
            .tab-btn.active {
                background: #1E3A5F;
                color: white;
            }
        }

                /* Treatment specific styles */
        .treatments-list {
            margin-bottom: 16px;
        }
        
        .treatment-item-card {
            background: #F8FAFC;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 8px;
            border-left: 3px solid #2C527A;
        }
        
        .treatment-code {
            font-weight: 700;
            color: #1E3A5F;
            font-size: 0.75rem;
            margin-bottom: 4px;
        }
        
        .treatment-dates {
            font-size: 0.65rem;
            color: #718096;
        }
        
        .treatment-doctor {
            font-size: 0.65rem;
            color: #2C527A;
            margin-top: 4px;
        }
        
        .toggle-btn-treatment {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            font-weight: 500;
            color: #4A5568;
            transition: all 0.2s;
        }
        
        .toggle-btn-treatment.active {
            background: white;
            color: #1E3A5F;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .toggle-btn-treatment:hover {
            background: #F1F5F9;
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

<!-- Tabs Navigation -->
<div class="tabs-container">
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('all-patients')">
            <span class="material-icons">people</span>
            All Patients
        </button>
        <button class="tab-btn" onclick="switchTab('multi-complaint')">
            <span class="material-icons">warning</span>
            Multi-Complaint Patients
            <?php if ($multi_count > 0): ?>
                <span style="background: #F59E0B; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; margin-left: 4px;"><?php echo $multi_count; ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn" onclick="switchTab('by-treatment')">
            <span class="material-icons">medical_services</span>
            Patients by Treatment
        </button>
    </div>
    
    <!-- Tab 1: All Patients -->
    <div id="tab-all-patients" class="tab-content active">
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
            
            <div class="stat-card">
                <div class="stat-icon"><span class="material-icons">warning</span></div>
                <div class="stat-info">
                    <h3><?php echo $multi_count; ?></h3>
                    <p>Multi-Complaint Patients</p>
                </div>
            </div>
        </div>

        <div class="dashboard-header">
            <h3>Patient Management</h3>
            <div class="view-toggle">
                <button class="toggle-btn active" onclick="toggleView('table')">
                    <span class="material-icons">table_view</span> Table View
                </button>
                <button class="toggle-btn" onclick="toggleView('cards')">
                    <span class="material-icons">dashboard</span> Card View
                </button>
            </div>
        </div>

        <!-- Cards View -->
        <div id="cardsView" class="cards-container" style="display: none;">
            <?php if (count($dashboard_patients) > 0): ?>
                <?php foreach ($dashboard_patients as $patient): 
                    $display_id = 'P' . str_pad($patient['p_id'], 3, '0', STR_PAD_LEFT);
                    $hasMultipleComplaints = ($patient['complaint_count'] ?? 0) >= 2;
                    $multiClass = $hasMultipleComplaints ? 'multi-complaint' : '';
                ?>
                    <div class="patient-card <?php echo $multiClass; ?>" onclick="viewRecord(<?php echo $patient['p_id']; ?>)">
                        <div class="card-header">
                            <h4><?php echo htmlspecialchars($patient['fname'] . ' ' . $patient['lname']); ?></h4>
                            <div class="patient-id">ID: <?php echo $display_id; ?></div>
                            <div class="ward-badge"><?php echo htmlspecialchars($patient['ward_name'] ?? 'No Ward'); ?></div>
                        </div>
                        <div class="card-body">
                            <div class="stats-row">
                                <div class="stat-pill">
                                    <span class="stat-number"><?php echo $patient['complaint_count'] ?? 0; ?></span>
                                    <span class="stat-label">Complaints</span>
                                </div>
                                <div class="stat-pill">
                                    <span class="stat-number"><?php echo $patient['treatment_count'] ?? 0; ?></span>
                                    <span class="stat-label">Treatments</span>
                                </div>
                            </div>
                            <div class="status-indicator <?php echo $patient['status'] === 'Active' ? 'status-active-card' : 'status-discharged-card'; ?>">
                                <span class="material-icons" style="font-size: 14px;"><?php echo $patient['status'] === 'Active' ? 'fiber_manual_record' : 'check_circle'; ?></span>
                                <?php echo $patient['status']; ?>
                            </div>
                            <button class="view-details-btn" onclick="event.stopPropagation(); viewRecord(<?php echo $patient['p_id']; ?>)">
                                <span class="material-icons">visibility</span>
                                View Full Record
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">No patients found matching your criteria</div>
            <?php endif; ?>
        </div>

        <!-- Table View -->
        <div id="tableView" class="table-container">
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
                        <tr onclick="viewRecord(<?php echo $row['p_id']; ?>)" style="cursor: pointer;">
                            <td><?php echo $display_id; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?></strong></td>
                            <td><?php echo $dob; ?></td>
                            <td><?php echo htmlspecialchars($row['ward_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['bed_no'] ?? 'N/A'); ?></td>
                            <td><?php echo $admission; ?></td>
                            <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status; ?></span></td>
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
                            <td colspan="8" class="no-data">
                                <span class="material-icons">search_off</span>
                                <p>No patients found</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Tab 2: Multi-Complaint Patients -->
    <div id="tab-multi-complaint" class="tab-content">
        <?php if (count($multi_patients) > 0): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><span class="material-icons">warning</span></div>
                    <div class="stat-info">
                        <h3><?php echo count($multi_patients); ?></h3>
                        <p>Multi-Complaint Patients</p>
                        <small style="color: #F59E0B;">⚠️ 2+ complaints each</small>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><span class="material-icons">local_hospital</span></div>
                    <div class="stat-info">
                        <h3><?php echo count(array_unique(array_column($multi_patients, 'ward_name'))); ?></h3>
                        <p>Departments Involved</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><span class="material-icons">trending_up</span></div>
                    <div class="stat-info">
                        <h3><?php echo round(array_sum(array_column($multi_patients, 'complaint_count')) / count($multi_patients), 1); ?></h3>
                        <p>Avg Complaints per Patient</p>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <div class="table-header">
                    <h3>
                        <span class="material-icons" style="vertical-align: middle;">warning</span>
                        Patients with Multiple Complaints
                    </h3>
                    <span class="badge"><?php echo count($multi_patients); ?> Patients</span>
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
                            <?php foreach ($multi_patients as $patient): 
                                $display_id = 'P' . str_pad($patient['p_id'], 3, '0', STR_PAD_LEFT);
                            ?>
                            <tr onclick="viewRecord(<?php echo $patient['p_id']; ?>)" style="cursor: pointer;">
                                <td>
                                    <strong><?php echo htmlspecialchars($patient['fname'] . ' ' . $patient['lname']); ?></strong>
                                    <br>
                                    <small style="color: #718096;">ID: <?php echo $display_id; ?></small>
                                </td>
                                <td>
                                    <span class="complaint-badge">
                                        <span class="material-icons" style="font-size: 14px;">healing</span>
                                        <?php echo $patient['complaint_count']; ?> complaint<?php echo $patient['complaint_count'] != 1 ? 's' : ''; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="treatment-badge">
                                        <span class="material-icons" style="font-size: 14px;">medical_services</span>
                                        <?php echo $patient['treatment_count']; ?> treatment<?php echo $patient['treatment_count'] != 1 ? 's' : ''; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="ward-name">
                                        <span class="material-icons" style="font-size: 14px;">domain</span>
                                        <?php echo htmlspecialchars($patient['ward_name'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $patient['status'] === 'Active' ? 'status-active' : 'status-discharged'; ?>">
                                        <?php echo $patient['status']; ?>
                                    </span>
                                </td>
                                <td onclick="event.stopPropagation();">
                                    <button class="view-record-btn" onclick="viewRecord(<?php echo $patient['p_id']; ?>)">
                                        <span class="material-icons" style="font-size: 14px;">visibility</span>
                                        View Record
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="no-data">
                <span class="material-icons">check_circle</span>
                <p>No patients with multiple complaints found</p>
                <small>Patients need at least 2 complaints to appear here</small>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Tab 3: Patients by Treatment -->
    <div id="tab-by-treatment" class="tab-content">
        <?php
        // QUERY TO GET PATIENTS WITH TREATMENTS (without complaint link since it doesn't exist)
        $treatment_filter_sql = "SELECT 
                    p.p_id,
                    p.fname,
                    p.lname,
                    p.dob,
                    p.admission_date,
                    p.discharge_date,
                    p.bed_no,
                    w.name AS ward_name,
                    CASE WHEN p.discharge_date IS NULL THEN 'Active' ELSE 'Discharged' END as status,
                    (SELECT COUNT(*) FROM Complaint c WHERE c.p_id = p.p_id) as complaint_count,
                    (SELECT COUNT(*) FROM Treatment t WHERE t.p_id = p.p_id) as treatment_count
                FROM Patient p
                LEFT JOIN Ward w ON p.w_id = w.w_id
                WHERE EXISTS (SELECT 1 FROM Treatment t2 WHERE t2.p_id = p.p_id)
                ORDER BY treatment_count DESC, complaint_count DESC";
        
        $treatment_filter_stmt = sqlsrv_query($conn, $treatment_filter_sql);
        $treatment_filter_patients = [];
        
        if ($treatment_filter_stmt !== false) {
            while ($row = sqlsrv_fetch_array($treatment_filter_stmt, SQLSRV_FETCH_ASSOC)) {
                // Format dates
                if ($row['dob'] instanceof DateTime) $row['dob'] = $row['dob']->format('Y-m-d');
                if ($row['admission_date'] instanceof DateTime) $row['admission_date'] = $row['admission_date']->format('Y-m-d');
                if ($row['discharge_date'] instanceof DateTime) $row['discharge_date'] = $row['discharge_date']->format('Y-m-d');
                $treatment_filter_patients[] = $row;
            }
        }
        
        // Get unique treatment counts for stats
        $unique_treatments_sql = "SELECT COUNT(*) as count FROM Treatment";
        $unique_treatments_stmt = sqlsrv_query($conn, $unique_treatments_sql);
        $total_unique_treatments = $unique_treatments_stmt ? sqlsrv_fetch_array($unique_treatments_stmt, SQLSRV_FETCH_ASSOC)['count'] : 0;
        
        $total_treatment_patients = count($treatment_filter_patients);
        
        // Get treatments grouped by patient for detailed view (without complaint link)
        $detailed_treatment_sql = "SELECT 
                    t.t_code,
                    t.startdate,
                    t.enddate,
                    t.p_id,
                    CONCAT(s.fname, ' ', s.lname) AS doctor_name
                FROM Treatment t
                INNER JOIN Doctor d ON t.d_id = d.d_id
                INNER JOIN Staff s ON d.d_id = s.st_id
                ORDER BY t.p_id, t.t_code";
        
        $detailed_treatment_stmt = sqlsrv_query($conn, $detailed_treatment_sql);
        $patient_treatments = [];
        
        if ($detailed_treatment_stmt !== false) {
            while ($row = sqlsrv_fetch_array($detailed_treatment_stmt, SQLSRV_FETCH_ASSOC)) {
                if ($row['startdate'] instanceof DateTime) $row['startdate'] = $row['startdate']->format('Y-m-d');
                if ($row['enddate'] instanceof DateTime) $row['enddate'] = $row['enddate']->format('Y-m-d');
                
                $p_id = $row['p_id'];
                if (!isset($patient_treatments[$p_id])) {
                    $patient_treatments[$p_id] = [];
                }
                $patient_treatments[$p_id][] = $row;
            }
        }
        ?>
        
        <?php if ($total_treatment_patients > 0): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><span class="material-icons">medical_services</span></div>
                    <div class="stat-info">
                        <h3><?php echo $total_unique_treatments; ?></h3>
                        <p>Total Treatments</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><span class="material-icons">people</span></div>
                    <div class="stat-info">
                        <h3><?php echo $total_treatment_patients; ?></h3>
                        <p>Patients with Treatments</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><span class="material-icons">trending_up</span></div>
                    <div class="stat-info">
                        <h3><?php echo $total_unique_treatments > 0 && $total_treatment_patients > 0 ? round($total_unique_treatments / $total_treatment_patients, 1) : 0; ?></h3>
                        <p>Avg Treatments/Patient</p>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-header">
                <h3>Patients by Treatment</h3>
                <div class="view-toggle">
                    <button class="toggle-btn-treatment active" onclick="toggleTreatmentView('table')">
                        <span class="material-icons">table_view</span> Table View
                    </button>
                    <button class="toggle-btn-treatment" onclick="toggleTreatmentView('cards')">
                        <span class="material-icons">dashboard</span> Card View
                    </button>
                </div>
            </div>
            
            <!-- Cards View for Treatments -->
            <div id="treatmentCardsView" class="cards-container" style="display: none;">
                <?php foreach ($treatment_filter_patients as $patient): 
                    $display_id = 'P' . str_pad($patient['p_id'], 3, '0', STR_PAD_LEFT);
                    $patient_tx = $patient_treatments[$patient['p_id']] ?? [];
                ?>
                    <div class="patient-card" onclick="viewRecord(<?php echo $patient['p_id']; ?>)">
                        <div class="card-header">
                            <h4><?php echo htmlspecialchars($patient['fname'] . ' ' . $patient['lname']); ?></h4>
                            <div class="patient-id">ID: <?php echo $display_id; ?></div>
                            <div class="ward-badge"><?php echo htmlspecialchars($patient['ward_name'] ?? 'No Ward'); ?></div>
                        </div>
                        <div class="card-body">
                            <div class="stats-row">
                                <div class="stat-pill">
                                    <span class="stat-number"><?php echo $patient['complaint_count'] ?? 0; ?></span>
                                    <span class="stat-label">Complaints</span>
                                </div>
                                <div class="stat-pill">
                                    <span class="stat-number"><?php echo $patient['treatment_count'] ?? 0; ?></span>
                                    <span class="stat-label">Treatments</span>
                                </div>
                            </div>
                            <?php if (count($patient_tx) > 0): ?>
                                <div class="treatments-list">
                                    <?php foreach ($patient_tx as $treatment): ?>
                                        <div class="treatment-item-card">
                                            <div class="treatment-code">
                                                Treatment #<?php echo $treatment['t_code']; ?>
                                            </div>
                                            <div class="treatment-dates">
                                                Started: <?php echo $treatment['startdate'] ?? 'N/A'; ?>
                                                <?php if ($treatment['enddate']): ?>
                                                    | Ended: <?php echo $treatment['enddate']; ?>
                                                <?php else: ?>
                                                    | <span style="color: #2C527A;">Ongoing</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="treatment-doctor">
                                                Dr. <?php echo htmlspecialchars($treatment['doctor_name']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="status-indicator <?php echo $patient['status'] === 'Active' ? 'status-active-card' : 'status-discharged-card'; ?>">
                                <span class="material-icons" style="font-size: 14px;"><?php echo $patient['status'] === 'Active' ? 'fiber_manual_record' : 'check_circle'; ?></span>
                                <?php echo $patient['status']; ?>
                            </div>
                            <button class="view-details-btn" onclick="event.stopPropagation(); viewRecord(<?php echo $patient['p_id']; ?>)">
                                <span class="material-icons">visibility</span>
                                View Full Record
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Table View for Treatments -->
            <div id="treatmentTableView" class="table-container">
                <div class="table-header">
                    <h3>Patient Treatment Records</h3>
                </div>
                <div class="table-responsive">
                    <table class="patients-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Ward</th>
                                <th>Treatments</th>
                                <th>Complaints</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($treatment_filter_patients as $patient): 
                                $display_id = 'P' . str_pad($patient['p_id'], 3, '0', STR_PAD_LEFT);
                                $patient_tx = $patient_treatments[$patient['p_id']] ?? [];
                                $treatment_codes = array_column($patient_tx, 't_code');
                            ?>
                                <tr onclick="viewRecord(<?php echo $patient['p_id']; ?>)" style="cursor: pointer;">
                                    <td><?php echo $display_id; ?></td>
                                    <td><strong><?php echo htmlspecialchars($patient['fname'] . ' ' . $patient['lname']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($patient['ward_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (count($treatment_codes) > 0): ?>
                                            <span class="treatment-badge">
                                                <span class="material-icons" style="font-size: 14px;">medical_services</span>
                                                <?php echo implode(', ', $treatment_codes); ?>
                                            </span>
                                        <?php else: ?>
                                            No treatments
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="complaint-badge">
                                            <span class="material-icons" style="font-size: 14px;">healing</span>
                                            <?php echo $patient['complaint_count'] ?? 0; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $patient['status'] === 'Active' ? 'status-active' : 'status-discharged'; ?>">
                                            <?php echo $patient['status']; ?>
                                        </span>
                                    </td>
                                    <td onclick="event.stopPropagation();">
                                        <button class="view-record-btn" onclick="viewRecord(<?php echo $patient['p_id']; ?>)">
                                            <span class="material-icons" style="font-size: 14px;">visibility</span>
                                            View Record
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="no-data">
                <span class="material-icons">info</span>
                <p>No treatment records found</p>
                <small>Patients need to have treatments assigned to appear here</small>
            </div>
        <?php endif; ?>
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
// Tab switching function
function switchTab(tabName, event) {
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    } else {
        const btns = document.querySelectorAll('.tab-btn');
        if (tabName === 'all-patients') btns[0].classList.add('active');
        else if (tabName === 'multi-complaint') btns[1].classList.add('active');
        else if (tabName === 'by-treatment') btns[2].classList.add('active');
    }
    
    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(`tab-${tabName}`).classList.add('active');
    
    // Save to localStorage
    localStorage.setItem('activeTab', tabName);
}

// View Toggle Function
function toggleView(view) {
    const tableView = document.getElementById('tableView');
    const cardsView = document.getElementById('cardsView');
    const toggleBtns = document.querySelectorAll('.toggle-btn');
    
    if (view === 'table') {
        tableView.style.display = 'block';
        cardsView.style.display = 'none';
        toggleBtns[0].classList.add('active');
        toggleBtns[1].classList.remove('active');
        localStorage.setItem('patientView', 'table');
    } else {
        tableView.style.display = 'none';
        cardsView.style.display = 'grid';
        toggleBtns[0].classList.remove('active');
        toggleBtns[1].classList.add('active');
        localStorage.setItem('patientView', 'cards');
    }
}

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
        const url = new URL(window.location.href);
        url.search = '';
        window.location.href = url.toString();
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

// Load saved preferences on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('patientView');
    if (savedView === 'cards') {
        toggleView('cards');
    }
    
    const savedTab = localStorage.getItem('activeTab');
    if (savedTab && savedTab !== 'all-patients') {
        switchTab(savedTab);
    }
});

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
    
    const treatmentsByComplaint = {};
    treatments.forEach(treatment => {
        const cCode = treatment.c_code;
        if (!treatmentsByComplaint[cCode]) {
            treatmentsByComplaint[cCode] = [];
        }
        treatmentsByComplaint[cCode].push(treatment);
    });
    
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

// Toggle view for Treatment tab
function toggleTreatmentView(view) {
    const tableView = document.getElementById('treatmentTableView');
    const cardsView = document.getElementById('treatmentCardsView');
    const toggleBtns = document.querySelectorAll('.toggle-btn-treatment');
    
    if (view === 'table') {
        tableView.style.display = 'block';
        cardsView.style.display = 'none';
        toggleBtns[0].classList.add('active');
        toggleBtns[1].classList.remove('active');
        localStorage.setItem('treatmentView', 'table');
    } else {
        tableView.style.display = 'none';
        cardsView.style.display = 'grid';
        toggleBtns[0].classList.remove('active');
        toggleBtns[1].classList.add('active');
        localStorage.setItem('treatmentView', 'cards');
    }
}

// Load saved treatment view preference
const savedTreatmentView = localStorage.getItem('treatmentView');
if (savedTreatmentView === 'cards') {
    toggleTreatmentView('cards');
}

</script>
</body>
</html>