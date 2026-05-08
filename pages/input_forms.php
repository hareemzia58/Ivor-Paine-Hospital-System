<?php
// pages/input_forms.php
require_once '../php/db_connect.php';

$conn = db();

// Get data for dropdowns
$wards = [];
$wards_result = sqlsrv_query($conn, "SELECT w_id, name FROM Ward ORDER BY name");
while ($row = sqlsrv_fetch_array($wards_result, SQLSRV_FETCH_ASSOC)) {
    $wards[] = $row;
}

$doctors = [];
$doctors_result = sqlsrv_query($conn, "SELECT d.d_id, s.fname, s.lname FROM Doctor d JOIN Staff s ON d.d_id = s.st_id ORDER BY s.fname");
while ($row = sqlsrv_fetch_array($doctors_result, SQLSRV_FETCH_ASSOC)) {
    $doctors[] = $row;
}

$consultants = [];
$consultants_result = sqlsrv_query($conn, "SELECT c.c_id, s.fname, s.lname FROM Consultant c JOIN Staff s ON c.c_id = s.st_id ORDER BY s.fname");
while ($row = sqlsrv_fetch_array($consultants_result, SQLSRV_FETCH_ASSOC)) {
    $consultants[] = $row;
}

$complaints = [];
$complaints_result = sqlsrv_query($conn, "SELECT c_code, title, description FROM Complaint ORDER BY title");
while ($row = sqlsrv_fetch_array($complaints_result, SQLSRV_FETCH_ASSOC)) {
    $complaints[] = $row;
}

$treatments = [];
$treatments_result = sqlsrv_query($conn, "SELECT t_code FROM Treatment ORDER BY t_code");
while ($row = sqlsrv_fetch_array($treatments_result, SQLSRV_FETCH_ASSOC)) {
    $treatments[] = $row;
}

// Handle form submissions
$success_message = '';
$error_message = '';

// Patient Record Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_patient'])) {
    $fname = $_POST['fname'] ?? '';
    $lname = $_POST['lname'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $admission_date = $_POST['admission_date'] ?? '';
    $ward_id = $_POST['ward_id'] ?? '';
    $bed_no = $_POST['bed_no'] ?? '';
    $telno = $_POST['telno'] ?? '';
    $address = $_POST['address'] ?? '';
    
    // Debug: Check if values are being received
    error_log("Attempting to insert patient: fname=$fname, lname=$lname, dob=$dob, ward_id=$ward_id");
    
    $sql = "INSERT INTO Patient (fname, lname, dob, admission_date, w_id, bed_no, telno, address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $params = [$fname, $lname, $dob, $admission_date, $ward_id, $bed_no, $telno, $address];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        // Get detailed error
        $errors = sqlsrv_errors();
        $error_message = "Error saving patient record: ";
        if ($errors) {
            foreach ($errors as $error) {
                $error_message .= $error['message'] . " ";
                error_log("SQL Error: " . print_r($error, true));
            }
        } else {
            $error_message .= "Unknown error occurred.";
        }
    } else {
        $success_message = "Patient record saved successfully!";
        // Clear form after success? Optional
    }
}

// Ward Record Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ward'])) {
    $ward_name = $_POST['ward_name'] ?? '';
    $specialty = $_POST['specialty'] ?? '';
    $day_sister = $_POST['day_sister'] ?? '';
    $night_sister = $_POST['night_sister'] ?? '';
    $staff_nurses = $_POST['staff_nurses'] ?? '';
    $non_reg_nurses = $_POST['non_reg_nurses'] ?? '';
    
    // First get or create specialty
    $sp_id = null;
    $sp_sql = "SELECT sp_id FROM Speciality WHERE speciality = ?";
    $sp_stmt = sqlsrv_query($conn, $sp_sql, [$specialty]);
    if ($sp_row = sqlsrv_fetch_array($sp_stmt, SQLSRV_FETCH_ASSOC)) {
        $sp_id = $sp_row['sp_id'];
    }
    
    if ($sp_id) {
        $sql = "INSERT INTO Ward (name, sp_id, day_sister, night_sister) VALUES (?, ?, ?, ?)";
        $params = [$ward_name, $sp_id, $day_sister ?: null, $night_sister ?: null];
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            $success_message = "Ward record saved successfully!";
        } else {
            $error_message = "Error saving ward record.";
        }
    } else {
        $error_message = "Invalid specialty selected.";
    }
}

// Staff Record Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_staff'])) {
    $fname = $_POST['fname'] ?? '';
    $lname = $_POST['lname'] ?? '';
    $email = $_POST['email'] ?? '';
    $telno = $_POST['telno'] ?? '';
    $address = $_POST['address'] ?? '';
    $position = $_POST['position'] ?? '';
    $staff_type = $_POST['staff_type'] ?? '';
    $team_id = $_POST['team_id'] ?? null;
    $specialty_id = $_POST['specialty_id'] ?? null;
    
    // Insert into Staff table
    $sql = "INSERT INTO Staff (fname, lname, email, telno, address) VALUES (?, ?, ?, ?, ?)";
    $params = [$fname, $lname, $email, $telno, $address];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt) {
        // Get the new staff ID
        $st_id_sql = "SELECT SCOPE_IDENTITY() as st_id";
        $id_stmt = sqlsrv_query($conn, $st_id_sql);
        $id_row = sqlsrv_fetch_array($id_stmt, SQLSRV_FETCH_ASSOC);
        $st_id = $id_row['st_id'];
        
        // Insert into specific role table
        if ($staff_type === 'doctor') {
            $doc_sql = "INSERT INTO Doctor (d_id, position, t_id) VALUES (?, ?, ?)";
            $doc_params = [$st_id, $position, $team_id ?: null];
            sqlsrv_query($conn, $doc_sql, $doc_params);
        } elseif ($staff_type === 'consultant') {
            $cons_sql = "INSERT INTO Consultant (c_id, sp_id) VALUES (?, ?)";
            $cons_params = [$st_id, $specialty_id];
            sqlsrv_query($conn, $cons_sql, $cons_params);
        } elseif ($staff_type === 'nurse') {
            $nurse_sql = "INSERT INTO Nurse (n_id, reg_id, w_id) VALUES (?, ?, ?)";
            $nurse_params = [$st_id, $st_id, null];
            sqlsrv_query($conn, $nurse_sql, $nurse_params);
        }
        
        // Create login credentials
        $pass = 'pass' . $st_id . '@IPH';
        $login_sql = "INSERT INTO UserLogin (st_id, password_hash, is_active) VALUES (?, CONVERT(VARCHAR(255), HASHBYTES('MD5', ?), 2), 1)";
        sqlsrv_query($conn, $login_sql, [$st_id, $pass]);
        
        $success_message = "Staff record saved successfully! Default password: " . $pass;
    } else {
        $error_message = "Error saving staff record.";
    }
}

$active_form = isset($_GET['form']) ? $_GET['form'] : 'patient';
?>
<!-- PAGE HEADER -->
<div class="page-header">
    <h1 class="page-title">Input Forms</h1>
    <p class="page-subtitle">Add and update patient, ward, and staff records in the hospital system</p>
</div>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Forms | IPMH</title>
    <style>
        /* Input Forms Page Styles */
        .form-nav {
            background: white;
            border-radius: 60px;
            padding: 6px;
            margin-bottom: 32px;
            display: inline-flex;
            gap: 8px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }
        
        .form-nav-item {
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
        
        .form-nav-item .material-icons {
            font-size: 20px;
        }
        
        .form-nav-item:hover {
            background: rgba(30, 58, 95, 0.08);
            color: #1E3A5F;
        }
        
        .form-nav-item.active {
            background: linear-gradient(135deg, #1E3A5F 0%, #2C527A 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(30, 58, 95, 0.25);
        }
        
        .form-card {
            background: white;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            max-width: 900px;
            margin: 0 auto;
        }
        
        .form-card h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1E3A5F;
            margin-bottom: 8px;
        }
        
        .form-card .form-subtitle {
            color: #718096;
            font-size: 0.85rem;
            margin-bottom: 28px;
            padding-bottom: 16px;
            border-bottom: 2px solid #E2E8F0;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        .form-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #4A5568;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }
        
        .form-group label .required {
            color: #DC2626;
            margin-left: 4px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 14px;
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            background: #F8FAFC;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2C527A;
            box-shadow: 0 0 0 3px rgba(44, 82, 122, 0.1);
            background: white;
        }
        
        .form-group input::placeholder {
            color: #A0AEC0;
        }
        
        .help-text {
            font-size: 0.7rem;
            color: #A0AEC0;
            margin-top: 6px;
        }
        
        .auto-id {
            background: #EDF2F7;
            color: #4A5568;
            font-weight: 500;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2C527A;
            margin: 24px 0 20px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #E2E8F0;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #E2E8F0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1E3A5F 0%, #2C527A 100%);
            color: white;
            border: none;
            padding: 12px 32px;
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
            padding: 12px 32px;
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
        
        .success-alert {
            background: #C6F6D5;
            color: #22543D;
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border-left: 4px solid #38A169;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-alert {
            background: #FED7D7;
            color: #742A2A;
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border-left: 4px solid #E53E3E;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-group.full-width {
                grid-column: span 1;
            }
            .form-nav-item span:not(.material-icons) {
                display: none;
            }
            .form-nav-item {
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body>

<div style="margin-bottom: 24px;">
    <div class="form-nav">
        <a href="?page=input_forms&form=patient" class="form-nav-item <?php echo $active_form == 'patient' ? 'active' : ''; ?>">
            <span class="material-icons">assignment_ind</span>
            <span>Patient Record</span>
        </a>
        <a href="?page=input_forms&form=ward" class="form-nav-item <?php echo $active_form == 'ward' ? 'active' : ''; ?>">
            <span class="material-icons">apartment</span>
            <span>Ward Record</span>
        </a>
        <a href="?page=input_forms&form=staff" class="form-nav-item <?php echo $active_form == 'staff' ? 'active' : ''; ?>">
            <span class="material-icons">badge</span>
            <span>Staff Record</span>
        </a>
    </div>
</div>

<?php if ($success_message): ?>
    <div class="success-alert">
        <span class="material-icons" style="font-size: 20px;">check_circle</span>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="error-alert">
        <span class="material-icons" style="font-size: 20px;">error</span>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<?php if ($active_form == 'patient'): ?>
    <!-- Patient Record Form -->
    <div class="form-card">
        <h2>Patient Record Form</h2>
        <p class="form-subtitle">Add a new patient to the hospital system</p>
        
        <form method="POST" action="?page=input_forms&form=patient" id="patientForm">
            <div class="section-title">Patient Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Patient No <span class="required">*</span></label>
                    <input type="text" class="auto-id" value="AUTO-GENERATED" disabled>
                    <div class="help-text">Auto-generated by system</div>
                </div>
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="fname" placeholder="Enter first name" required>
                </div>
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="lname" placeholder="Enter last name" required>
                </div>
                <div class="form-group">
                    <label>Date of Birth <span class="required">*</span></label>
                    <input type="date" name="dob" required>
                </div>
                <div class="form-group">
                    <label>Admission Date <span class="required">*</span></label>
                    <input type="date" name="admission_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Ward <span class="required">*</span></label>
                    <select name="ward_id" required>
                        <option value="">Select Ward</option>
                        <?php foreach ($wards as $ward): ?>
                            <option value="<?php echo $ward['w_id']; ?>"><?php echo htmlspecialchars($ward['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Bed No</label>
                    <input type="text" name="bed_no" placeholder="e.g., 101">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="telno" placeholder="e.g., 03001234567">
                </div>
                <div class="form-group full-width">
                    <label>Address</label>
                    <textarea name="address" rows="2" placeholder="Enter patient address"></textarea>
                </div>
            </div>
            
            <div class="section-title">Medical Complaint & Treatment</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Complaint</label>
                    <select name="complaint_code" id="complaintSelect">
                        <option value="">Select Complaint</option>
                        <?php foreach ($complaints as $complaint): ?>
                            <option value="<?php echo $complaint['c_code']; ?>" data-desc="<?php echo htmlspecialchars($complaint['description']); ?>">
                                <?php echo htmlspecialchars($complaint['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Complaint Description</label>
                    <textarea name="complaint_desc" id="complaintDesc" rows="2" placeholder="Description will appear here" readonly style="background: #F8FAFC;"></textarea>
                </div>
                <div class="form-group">
                    <label>Treatment</label>
                    <select name="treatment_code">
                        <option value="">Select Treatment</option>
                        <?php foreach ($treatments as $treatment): ?>
                            <option value="<?php echo $treatment['t_code']; ?>">Treatment #<?php echo $treatment['t_code']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Doctor <span class="required">*</span></label>
                    <select name="doctor_id" required>
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['d_id']; ?>">Dr. <?php echo htmlspecialchars($doctor['fname'] . ' ' . $doctor['lname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date Treatment Started</label>
                    <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Date Treatment Ended</label>
                    <input type="date" name="end_date">
                    <div class="help-text">Leave empty if ongoing</div>
                </div>
            </div>
            
            <div class="section-title">Primary Doctor Assignment</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Primary Doctor</label>
                    <select name="primary_doctor_id">
                        <option value="">Select Primary Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['d_id']; ?>">Dr. <?php echo htmlspecialchars($doctor['fname'] . ' ' . $doctor['lname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="help-text">Doctor primarily responsible for this patient</div>
                </div>
                <div class="form-group">
                    <label>Consultant</label>
                    <select name="consultant_id">
                        <option value="">Select Consultant</option>
                        <?php foreach ($consultants as $consultant): ?>
                            <option value="<?php echo $consultant['c_id']; ?>">Dr. <?php echo htmlspecialchars($consultant['fname'] . ' ' . $consultant['lname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="help-text">For performance tracking</div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="reset" class="btn-secondary">Clear Form</button>
                <button type="submit" name="save_patient" class="btn-primary">Save Patient Record</button>
            </div>
        </form>
    </div>
    
    <script>
        document.getElementById('complaintSelect')?.addEventListener('change', function() {
            var descField = document.getElementById('complaintDesc');
            var selectedOption = this.options[this.selectedIndex];
            var desc = selectedOption.getAttribute('data-desc');
            descField.value = desc || '';
        });
    </script>

<?php elseif ($active_form == 'ward'): ?>
    <!-- Ward Record Form -->
    <div class="form-card">
        <h2>Ward Record Form</h2>
        <p class="form-subtitle">Add a new ward to the hospital</p>
        
        <form method="POST" action="?page=input_forms&form=ward">
            <div class="section-title">Ward Information</div>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Ward Name <span class="required">*</span></label>
                    <input type="text" name="ward_name" placeholder="e.g., Cardiac Ward C" required>
                </div>
                <div class="form-group">
                    <label>Specialty <span class="required">*</span></label>
                    <select name="specialty" required>
                        <option value="">Select Specialty</option>
                        <option value="Cardiology">Cardiology</option>
                        <option value="Neurology">Neurology</option>
                        <option value="Orthopedics">Orthopedics</option>
                        <option value="Pediatrics">Pediatrics</option>
                        <option value="General Surgery">General Surgery</option>
                        <option value="Oncology">Oncology</option>
                        <option value="Pulmonology">Pulmonology</option>
                        <option value="Gastroenterology">Gastroenterology</option>
                        <option value="Dermatology">Dermatology</option>
                        <option value="Psychiatry">Psychiatry</option>
                        <option value="Obstetrics & Gynecology">Obstetrics & Gynecology</option>
                        <option value="Urology">Urology</option>
                        <option value="Nephrology">Nephrology</option>
                        <option value="Endocrinology">Endocrinology</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Day Sister (Staff ID)</label>
                    <input type="number" name="day_sister" placeholder="Enter Staff ID">
                    <div class="help-text">Staff ID of the day sister</div>
                </div>
                <div class="form-group">
                    <label>Night Sister (Staff ID)</label>
                    <input type="number" name="night_sister" placeholder="Enter Staff ID">
                </div>
                <div class="form-group">
                    <label>Staff Nurses (Count)</label>
                    <input type="number" name="staff_nurses" placeholder="Number of staff nurses">
                </div>
                <div class="form-group">
                    <label>Non-registered Nurses (Count)</label>
                    <input type="number" name="non_reg_nurses" placeholder="Number of non-registered nurses">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="reset" class="btn-secondary">Clear Form</button>
                <button type="submit" name="save_ward" class="btn-primary">Save Ward Record</button>
            </div>
        </form>
    </div>

<?php elseif ($active_form == 'staff'): ?>
    <!-- Staff Record Form -->
    <div class="form-card">
        <h2>Staff Record Form</h2>
        <p class="form-subtitle">Add a new staff member to the hospital (Doctor, Consultant, or Nurse)</p>
        
        <form method="POST" action="?page=input_forms&form=staff">
            <div class="section-title">Personal Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="fname" placeholder="Enter first name" required>
                </div>
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="lname" placeholder="Enter last name" required>
                </div>
                <div class="form-group full-width">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="email" placeholder="email@hospital.pk" required>
                </div>
                <div class="form-group">
                    <label>Phone Number <span class="required">*</span></label>
                    <input type="tel" name="telno" placeholder="e.g., 03001234567" required>
                </div>
                <div class="form-group full-width">
                    <label>Address <span class="required">*</span></label>
                    <textarea name="address" rows="2" placeholder="Enter address" required></textarea>
                </div>
            </div>
            
            <div class="section-title">Professional Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Staff Type <span class="required">*</span></label>
                    <select name="staff_type" id="staffType" required>
                        <option value="">Select Type</option>
                        <option value="doctor">Doctor</option>
                        <option value="consultant">Consultant</option>
                        <option value="nurse">Nurse</option>
                    </select>
                </div>
                <div class="form-group" id="positionField" style="display: none;">
                    <label>Position <span class="required">*</span></label>
                    <input type="text" name="position" placeholder="e.g., Cardiologist, Neurologist">
                </div>
                <div class="form-group" id="teamField" style="display: none;">
                    <label>Team ID</label>
                    <input type="number" name="team_id" placeholder="Team number (1-8)">
                </div>
                <div class="form-group" id="specialtyField" style="display: none;">
                    <label>Specialty ID</label>
                    <input type="number" name="specialty_id" placeholder="Specialty number (1-14)">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="reset" class="btn-secondary">Clear Form</button>
                <button type="submit" name="save_staff" class="btn-primary">Save Staff Record</button>
            </div>
        </form>
    </div>
    
    <script>
        const staffType = document.getElementById('staffType');
        const positionField = document.getElementById('positionField');
        const teamField = document.getElementById('teamField');
        const specialtyField = document.getElementById('specialtyField');
        
        staffType.addEventListener('change', function() {
            positionField.style.display = 'none';
            teamField.style.display = 'none';
            specialtyField.style.display = 'none';
            
            if (this.value === 'doctor') {
                positionField.style.display = 'block';
                teamField.style.display = 'block';
            } else if (this.value === 'consultant') {
                specialtyField.style.display = 'block';
            } else if (this.value === 'nurse') {
                // Nurses have no additional required fields
            }
        });
    </script>
<?php endif; ?>

</body>
</html>