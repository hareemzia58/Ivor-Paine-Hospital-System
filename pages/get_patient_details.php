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

// Convert DateTime objects to strings for patient data
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

// ============================================
// QUERY #3: Patients with their complaints, treatments, and treatment dates
// ============================================
$complaint_treatment_sql = "SELECT 
                                c.c_code,
                                c.title AS complaint_title,
                                c.description AS complaint_description,
                                c.created_date AS complaint_date,
                                t.t_code,
                                t.startdate AS treatment_start_date,
                                t.enddate AS treatment_end_date,
                                CONCAT(s.fname, ' ', s.lname) AS doctor_name,
                                DATEDIFF(DAY, t.startdate, COALESCE(t.enddate, GETDATE())) AS treatment_duration_days
                            FROM Patient p
                            LEFT JOIN Complaint c ON p.p_id = c.p_id
                            LEFT JOIN Treatment t ON p.p_id = t.p_id
                            LEFT JOIN Doctor d ON t.d_id = d.d_id
                            LEFT JOIN Staff s ON d.d_id = s.st_id
                            WHERE p.p_id = ?
                            ORDER BY c.created_date DESC, t.startdate DESC";

$complaint_treatment_stmt = sqlsrv_query($conn, $complaint_treatment_sql, array($patient_id));
$complaint_treatments = [];

if ($complaint_treatment_stmt === false) {
    error_log("Query #3 Error: " . print_r(sqlsrv_errors(), true));
} else {
    while ($row = sqlsrv_fetch_array($complaint_treatment_stmt, SQLSRV_FETCH_ASSOC)) {
        // Format dates
        if ($row['complaint_date'] instanceof DateTime) {
            $row['complaint_date'] = $row['complaint_date']->format('Y-m-d H:i:s');
        }
        if ($row['treatment_start_date'] instanceof DateTime) {
            $row['treatment_start_date'] = $row['treatment_start_date']->format('Y-m-d');
        }
        if ($row['treatment_end_date'] instanceof DateTime) {
            $row['treatment_end_date'] = $row['treatment_end_date']->format('Y-m-d');
        }
        
        // Set default values for NULLs
        $row['complaint_title'] = $row['complaint_title'] ?? 'No complaint recorded';
        $row['complaint_description'] = $row['complaint_description'] ?? null;
        $row['doctor_name'] = $row['doctor_name'] ?? 'Not assigned';
        $row['treatment_duration_days'] = $row['treatment_duration_days'] ?? 0;
        
        $complaint_treatments[] = $row;
    }
}

// Get complaints (separate for backward compatibility)
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
        // Add treatment count for each complaint
        $row['treatment_count'] = 0;
        foreach ($complaint_treatments as $ct) {
            if ($ct['c_code'] == $row['c_code'] && $ct['t_code'] !== null) {
                $row['treatment_count']++;
            }
        }
        $complaints[] = $row;
    }
}

// Get treatments with doctor names (separate for backward compatibility)
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

// Return the data with Query #3 included
echo json_encode([
    'success' => true,
    'patient' => $patient,
    'complaints' => $complaints,
    'treatments' => $treatments,
    'complaint_treatments' => $complaint_treatments,  // ← QUERY #3 RESULT
    'progress' => $progress,
    'query_3_summary' => [
        'total_records' => count($complaint_treatments),
        'has_treatment_data' => count(array_filter($complaint_treatments, function($item) {
            return !is_null($item['t_code']);
        })) > 0
    ]
]);
?>