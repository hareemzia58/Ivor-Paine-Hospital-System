<?php
// pages/get_patient_details.php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login']);
    exit();
}

// Database connection - adjust path as needed
$connectionPath = '../php/db_connect.php';

if (!file_exists($connectionPath)) {
    $connectionPath = 'php/db_connect.php';
}

if (file_exists($connectionPath)) {
    require_once $connectionPath;
    if (!isset($conn) && function_exists('db')) {
        $conn = db();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit();
}

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($patient_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit();
}

// Get patient details with consultant info
$patient_sql = "SELECT 
                    p.p_id,
                    p.fname,
                    p.lname,
                    p.dob,
                    p.admission_date,
                    p.discharge_date,
                    p.telno,
                    p.address,
                    p.bed_no,
                    w.name AS ward_name,
                    sp.speciality
                FROM Patient p
                LEFT JOIN Ward w ON p.w_id = w.w_id
                LEFT JOIN Speciality sp ON w.sp_id = sp.sp_id
                WHERE p.p_id = ?";

$patient_stmt = sqlsrv_query($conn, $patient_sql, array($patient_id));
if ($patient_stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . print_r(sqlsrv_errors(), true)]);
    exit();
}

$patient = sqlsrv_fetch_array($patient_stmt, SQLSRV_FETCH_ASSOC);
if (!$patient) {
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit();
}

// Convert DateTime objects to strings
foreach ($patient as $key => $value) {
    if ($value instanceof DateTime) {
        $patient[$key] = $value->format('Y-m-d');
    }
}

// Get consultant name for this patient (based on ward speciality)
$consultant_sql = "SELECT TOP 1 CONCAT(s.fname, ' ', s.lname) AS consultant_name
                   FROM Consultant c
                   INNER JOIN Staff s ON c.c_id = s.st_id
                   INNER JOIN Speciality sp ON c.sp_id = sp.sp_id
                   INNER JOIN Ward w ON w.sp_id = sp.sp_id
                   INNER JOIN Patient p ON p.w_id = w.w_id
                   WHERE p.p_id = ?";
$consultant_stmt = sqlsrv_query($conn, $consultant_sql, array($patient_id));
if ($consultant_stmt) {
    $consultant = sqlsrv_fetch_array($consultant_stmt, SQLSRV_FETCH_ASSOC);
    if ($consultant && isset($consultant['consultant_name'])) {
        $patient['consultant_name'] = $consultant['consultant_name'];
    } else {
        $patient['consultant_name'] = 'Not Assigned';
    }
} else {
    $patient['consultant_name'] = 'Not Assigned';
}

// Get complaints
$complaint_sql = "SELECT 
                    c_code,
                    title,
                    description,
                    created_date
                FROM Complaint
                WHERE p_id = ?
                ORDER BY created_date DESC";

$complaint_stmt = sqlsrv_query($conn, $complaint_sql, array($patient_id));
$complaints = [];
if ($complaint_stmt !== false) {
    while ($row = sqlsrv_fetch_array($complaint_stmt, SQLSRV_FETCH_ASSOC)) {
        if ($row['created_date'] instanceof DateTime) {
            $row['created_date'] = $row['created_date']->format('Y-m-d H:i:s');
        }
        $complaints[] = $row;
    }
}

// Get treatments with doctor names
$treatment_sql = "SELECT 
                    t.t_code,
                    t.startdate,
                    t.enddate,
                    t.p_id,
                    CONCAT(s.fname, ' ', s.lname) AS doctor_name
                FROM Treatment t
                INNER JOIN Doctor d ON t.d_id = d.d_id
                INNER JOIN Staff s ON d.d_id = s.st_id
                WHERE t.p_id = ?
                ORDER BY t.startdate DESC";

$treatment_stmt = sqlsrv_query($conn, $treatment_sql, array($patient_id));
$treatments = [];
if ($treatment_stmt !== false) {
    while ($row = sqlsrv_fetch_array($treatment_stmt, SQLSRV_FETCH_ASSOC)) {
        if ($row['startdate'] instanceof DateTime) {
            $row['startdate'] = $row['startdate']->format('Y-m-d');
        }
        if ($row['enddate'] instanceof DateTime) {
            $row['enddate'] = $row['enddate']->format('Y-m-d');
        }
        
        // Try to link treatment to a complaint through PatientRecord table
        $link_sql = "SELECT TOP 1 c_code FROM PatientRecord WHERE p_id = ? AND d_id IN (SELECT d_id FROM Treatment WHERE t_code = ?)";
        $link_stmt = sqlsrv_query($conn, $link_sql, array($patient_id, $row['t_code']));
        if ($link_stmt !== false) {
            $link = sqlsrv_fetch_array($link_stmt, SQLSRV_FETCH_ASSOC);
            if ($link && isset($link['c_code'])) {
                $row['c_code'] = $link['c_code'];
            } elseif (!empty($complaints)) {
                // If no link found, associate with first complaint
                $row['c_code'] = $complaints[0]['c_code'];
            }
        } elseif (!empty($complaints)) {
            $row['c_code'] = $complaints[0]['c_code'];
        }
        
        $treatments[] = $row;
    }
}

// Get progress notes
$progress_sql = "SELECT 
                    p.date_grade,
                    p.performance,
                    CONCAT(s.fname, ' ', s.lname) AS doctor_name
                FROM Progress p
                INNER JOIN Consultant c ON p.c_id = c.c_id
                INNER JOIN Staff s ON c.c_id = s.st_id
                WHERE p.p_id = ?
                ORDER BY p.date_grade DESC";

$progress_stmt = sqlsrv_query($conn, $progress_sql, array($patient_id));
$progress = [];
if ($progress_stmt !== false) {
    while ($row = sqlsrv_fetch_array($progress_stmt, SQLSRV_FETCH_ASSOC)) {
        if ($row['date_grade'] instanceof DateTime) {
            $row['date_grade'] = $row['date_grade']->format('Y-m-d');
        }
        $progress[] = $row;
    }
}

// Return the data
echo json_encode([
    'success' => true,
    'patient' => $patient,
    'complaints' => $complaints,
    'treatments' => $treatments,
    'progress' => $progress
]);
?>