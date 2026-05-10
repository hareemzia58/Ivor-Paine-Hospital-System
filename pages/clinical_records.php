<?php
// pages/clinical_records.php
require_once '../php/db_connect.php';

$conn = db();

// Get data for dropdowns
$complaints = [];
$complaints_result = sqlsrv_query($conn, "SELECT c_code, title, description FROM Complaint ORDER BY title");
if ($complaints_result) {
    while ($row = sqlsrv_fetch_array($complaints_result, SQLSRV_FETCH_ASSOC)) {
        $complaints[] = $row;
    }
}

// Get doctors for search
$doctors = [];
$doctors_result = sqlsrv_query($conn, "
    SELECT 
        d.d_id,
        s.fname,
        s.lname,
        d.position,
        t.team_name,
        sp.speciality
    FROM Doctor d
    JOIN Staff s ON d.d_id = s.st_id
    LEFT JOIN Team t ON d.t_id = t.t_id
    LEFT JOIN Consultant c ON d.d_id = c.c_id
    LEFT JOIN Speciality sp ON c.sp_id = sp.sp_id
    ORDER BY s.fname
");
if ($doctors_result) {
    while ($row = sqlsrv_fetch_array($doctors_result, SQLSRV_FETCH_ASSOC)) {
        $doctors[] = $row;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'get_treatments_by_complaint') {
        $complaint_code = $_POST['complaint_code'] ?? '';
        
        if ($complaint_code) {
            // Query #6: A list of complaints, treatments given for that complaint and experience history 
            // of the doctor giving that particular treatment
            $sql = "
                SELECT DISTINCT 
                    t.t_code,
                    t.startdate,
                    t.enddate,
                    s.fname + ' ' + s.lname as doctor_name,
                    d.position,
                    p.fname + ' ' + p.lname as patient_name,
                    c.title as complaint_title
                FROM Treatment t
                JOIN Complaint c ON t.p_id = c.p_id AND c.c_code = ?
                JOIN Doctor d ON t.d_id = d.d_id
                JOIN Staff s ON d.d_id = s.st_id
                JOIN Patient p ON t.p_id = p.p_id
                WHERE c.c_code = ?
                ORDER BY t.startdate DESC
            ";
            $params = [$complaint_code, $complaint_code];
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            $treatments = [];
            if ($stmt) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $treatments[] = $row;
                }
            }
            
            // Get doctors experience for this complaint from PrevExperience table
            $exp_sql = "
                SELECT DISTINCT 
                    s.fname + ' ' + s.lname as doctor_name,
                    d.position,
                    sp.speciality,
                    pe.establishment,
                    pe.position as prev_position,
                    pe.from_date,
                    pe.to_date
                FROM Treatment t
                JOIN Complaint c ON t.p_id = c.p_id AND c.c_code = ?
                JOIN Doctor d ON t.d_id = d.d_id
                JOIN Staff s ON d.d_id = s.st_id
                LEFT JOIN Consultant cons ON d.d_id = cons.c_id
                LEFT JOIN Speciality sp ON cons.sp_id = sp.sp_id
                LEFT JOIN PrevExperience pe ON d.d_id = pe.d_id
                WHERE c.c_code = ?
                ORDER BY s.fname, pe.from_date DESC
            ";
            $exp_stmt = sqlsrv_query($conn, $exp_sql, $params);
            $doctors_list = [];
            if ($exp_stmt) {
                while ($row = sqlsrv_fetch_array($exp_stmt, SQLSRV_FETCH_ASSOC)) {
                    $doctors_list[] = $row;
                }
            }
            
            echo json_encode(['success' => true, 'treatments' => $treatments, 'doctors' => $doctors_list]);
            exit;
        }
    }
    
    if ($action === 'get_treatments_by_date_range') {
        $complaint_code = $_POST['complaint_code'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        
        // Query #11: A list of treatments that have been given for a particular complaint 
        // between two given dates ordered by treatment
        $sql = "
            SELECT 
                t.t_code,
                t.startdate as treatment_start_date,
                t.enddate as treatment_end_date,
                s.fname + ' ' + s.lname as doctor_name,
                p.fname + ' ' + p.lname as patient_name,
                c.title as complaint_title,
                c.description as complaint_description
            FROM Treatment t
            JOIN Complaint c ON t.p_id = c.p_id
            JOIN Doctor d ON t.d_id = d.d_id
            JOIN Staff s ON d.d_id = s.st_id
            JOIN Patient p ON t.p_id = p.p_id
            WHERE 1=1
        ";
        $params = [];
        
        if ($complaint_code) {
            $sql .= " AND c.c_code = ?";
            $params[] = $complaint_code;
        }
        
        if ($start_date) {
            $sql .= " AND CAST(t.startdate AS DATE) >= ?";
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $sql .= " AND CAST(t.startdate AS DATE) <= ?";
            $params[] = $end_date;
        }
        
        $sql .= " ORDER BY t.t_code, t.startdate DESC";
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        $treatments = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $treatments[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'treatments' => $treatments]);
        exit;
    }
    
    if ($action === 'get_doctor_performance') {
        $doctor_id = $_POST['doctor_id'] ?? '';
        
        if ($doctor_id) {
            // Query #9: A performance history for a particular doctor using Progress table
            $perf_sql = "
                SELECT 
                    pr.date_grade as review_date,
                    pr.performance as performance_grade,
                    pr.performance as comments,
                    s.fname + ' ' + s.lname as patient_name
                FROM Progress pr
                JOIN Patient p ON pr.p_id = p.p_id
                JOIN Staff s ON p.p_id = s.st_id
                WHERE pr.c_id = ?
                ORDER BY pr.date_grade DESC
            ";
            $perf_stmt = sqlsrv_query($conn, $perf_sql, [$doctor_id]);
            $performance = [];
            if ($perf_stmt) {
                while ($row = sqlsrv_fetch_array($perf_stmt, SQLSRV_FETCH_ASSOC)) {
                    $performance[] = $row;
                }
            }
            
            // Get experience history from PrevExperience table
            $exp_sql = "
                SELECT 
                    from_date,
                    to_date,
                    position,
                    establishment
                FROM PrevExperience
                WHERE d_id = ?
                ORDER BY from_date DESC
            ";
            $exp_stmt = sqlsrv_query($conn, $exp_sql, [$doctor_id]);
            $experience = [];
            if ($exp_stmt) {
                while ($row = sqlsrv_fetch_array($exp_stmt, SQLSRV_FETCH_ASSOC)) {
                    $experience[] = $row;
                }
            }
            
            echo json_encode(['success' => true, 'performance' => $performance, 'experience' => $experience]);
            exit;
        }
    }
    
    if ($action === 'get_patient_details') {
        $patient_id = $_POST['patient_id'] ?? '';
        
        if ($patient_id) {
            // Query #10: Full medical details for a particular patient
            // Query #3: A list of patients and their complaints, treatments and dates of treatment
            
            // Get patient basic info
            $patient_sql = "
                SELECT 
                    p.p_id,
                    p.fname,
                    p.lname,
                    p.dob,
                    p.admission_date,
                    p.discharge_date,
                    p.telno,
                    p.address,
                    p.bed_no,
                    w.name as ward_name
                FROM Patient p
                LEFT JOIN Ward w ON p.w_id = w.w_id
                WHERE p.p_id = ?
            ";
            $patient_stmt = sqlsrv_query($conn, $patient_sql, [$patient_id]);
            $patient = $patient_stmt ? sqlsrv_fetch_array($patient_stmt, SQLSRV_FETCH_ASSOC) : null;
            
            // Get complaints and treatments
            $complaint_sql = "
                SELECT 
                    c.title as complaint_title,
                    c.description as complaint_description,
                    t.t_code,
                    t.startdate as treatment_start_date,
                    t.enddate as treatment_end_date,
                    s.fname + ' ' + s.lname as doctor_name,
                    d.position as doctor_position
                FROM Complaint c
                LEFT JOIN Treatment t ON c.p_id = t.p_id
                LEFT JOIN Doctor d ON t.d_id = d.d_id
                LEFT JOIN Staff s ON d.d_id = s.st_id
                WHERE c.p_id = ?
                ORDER BY t.startdate DESC
            ";
            $complaint_stmt = sqlsrv_query($conn, $complaint_sql, [$patient_id]);
            $complaints_patient = [];
            if ($complaint_stmt) {
                while ($row = sqlsrv_fetch_array($complaint_stmt, SQLSRV_FETCH_ASSOC)) {
                    $complaints_patient[] = $row;
                }
            }
            
            echo json_encode(['success' => true, 'patient' => $patient, 'complaints' => $complaints_patient]);
            exit;
        }
    }
    
    if ($action === 'search_patients') {
        $search = $_POST['search'] ?? '';
        $sql = "
            SELECT TOP 10 
                p.p_id,
                p.fname + ' ' + p.lname as patient_name,
                p.admission_date,
                w.name as ward_name
            FROM Patient p
            LEFT JOIN Ward w ON p.w_id = w.w_id
            WHERE p.fname + ' ' + p.lname LIKE ?
            ORDER BY p.admission_date DESC
        ";
        $params = ["%$search%"];
        $stmt = sqlsrv_query($conn, $sql, $params);
        $patients = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $patients[] = $row;
            }
        }
        echo json_encode(['success' => true, 'patients' => $patients]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Initial data for performance tab - get doctor list for dropdown
$doctor_list = [];
foreach ($doctors as $doc) {
    $doctor_list[] = [
        'id' => $doc['d_id'],
        'name' => 'Dr. ' . $doc['fname'] . ' ' . $doc['lname'],
        'specialty' => $doc['speciality'] ?? 'General',
        'position' => $doc['position'] ?? 'Doctor'
    ];
}
?>

<style>
    /* Clinical Records Page Styles */
    .clinical-records-container {
        max-width: 1400px;
        margin: 0 auto;
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
        background: white;
        color: #1E3A5F;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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
    
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid #E2E8F0;
        border-top-color: #1E3A5F;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
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
    
    @media (max-width: 768px) {
        .clinical-tab span:last-child {
            display: none;
        }
        .clinical-tab {
            padding: 12px 20px;
        }
        .filter-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="clinical-records-container">
    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1 class="page-title">Clinical Records</h1>
        <p class="page-subtitle">View and manage patient complaints, treatments, and medical history</p>
    </div>
    
    <!-- Main Tabs -->
    <div class="clinical-tabs">
        <button class="clinical-tab active" data-tab="complaints">
            <span class="material-icons">medical_services</span>
            <span>Complaints & Treatments</span>
        </button>
        <button class="clinical-tab" data-tab="performance">
            <span class="material-icons">trending_up</span>
            <span>Performance & History</span>
        </button>
    </div>
    
    <!-- Complaints & Treatments Tab Content -->
    <div id="complaintsTab" class="tab-content">
        <div class="results-card">
            <!-- Sub Tabs -->
            <div class="clinical-subtabs">
                <button class="clinical-subtab active" data-subtab="complaint-view">
                    Complaint View
                </button>
                <button class="clinical-subtab" data-subtab="date-range">
                    Date Range Filter
                </button>
            </div>
            
            <!-- Complaint View Subtab -->
            <div id="complaintView" class="subtab-content">
                <div class="filter-group" style="margin-bottom: 24px;">
                    <label>Select Complaint</label>
                    <select id="complaintSelect">
                        <option value="">-- Select a Complaint --</option>
                        <?php foreach ($complaints as $complaint): ?>
                            <option value="<?php echo htmlspecialchars($complaint['c_code']); ?>">
                                <?php echo htmlspecialchars($complaint['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="complaintResults" style="display: none;">
                    <div class="filter-grid">
                        <div>
                            <h3 style="color: #1E3A5F; margin-bottom: 16px;">Treatments</h3>
                            <div id="treatmentsList"></div>
                        </div>
                        <div>
                            <h3 style="color: #1E3A5F; margin-bottom: 16px;">Doctor Experience</h3>
                            <div id="doctorsExperienceList"></div>
                        </div>
                    </div>
                </div>
                
                <div id="complaintEmpty" class="empty-state" style="display: none;">
                    <span class="material-icons">medical_information</span>
                    <h3>Select a complaint to view treatments</h3>
                    <p>Choose a complaint from the dropdown above to see associated treatments and doctor experience</p>
                </div>
            </div>
            
            <!-- Date Range Filter Subtab -->
            <div id="dateRangeView" class="subtab-content" style="display: none;">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Complaint Type</label>
                        <select id="filterComplaintSelect">
                            <option value="">All Complaints</option>
                            <?php foreach ($complaints as $complaint): ?>
                                <option value="<?php echo htmlspecialchars($complaint['c_code']); ?>">
                                    <?php echo htmlspecialchars($complaint['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Start Date</label>
                        <div class="date-input-group">
                            <span class="material-icons">event</span>
                            <input type="date" id="startDate">
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>End Date</label>
                        <div class="date-input-group">
                            <span class="material-icons">event</span>
                            <input type="date" id="endDate">
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-bottom: 24px;">
                    <button id="applyFilterBtn" class="btn-primary">Apply Filters</button>
                    <button id="clearFiltersBtn" class="btn-secondary">Clear Filters</button>
                </div>
                
                <div id="dateRangeResults">
                    <div id="treatmentsByDateList"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Performance & History Tab Content -->
    <div id="performanceTab" class="tab-content" style="display: none;">
        <div class="results-card">
            <div class="search-doctor-input">
                <span class="material-icons">search</span>
                <input type="text" id="doctorSearchInput" placeholder="Search doctor by name...">
                <div id="doctorSuggestions" class="doctor-suggestions" style="display: none;"></div>
            </div>
            
            <div id="doctorPerformanceResults" style="display: none;">
                <div id="doctorInfo" class="doctor-card"></div>
                <div id="performanceHistory"></div>
                <div id="experienceHistory" style="margin-top: 32px;"></div>
            </div>
            
            <div id="performanceEmpty" class="empty-state">
                <span class="material-icons">search</span>
                <h3>Search for a doctor</h3>
                <p>Enter a doctor's name above to view their performance history and experience</p>
            </div>
        </div>
    </div>
</div>

<script>
// Tab switching
document.querySelectorAll('.clinical-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const tabId = tab.dataset.tab;
        
        // Update active state
        document.querySelectorAll('.clinical-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        // Show/hide content
        document.getElementById('complaintsTab').style.display = tabId === 'complaints' ? 'block' : 'none';
        document.getElementById('performanceTab').style.display = tabId === 'performance' ? 'block' : 'none';
    });
});

// Subtab switching
document.querySelectorAll('.clinical-subtab').forEach(subtab => {
    subtab.addEventListener('click', () => {
        const subtabId = subtab.dataset.subtab;
        
        document.querySelectorAll('.clinical-subtab').forEach(st => st.classList.remove('active'));
        subtab.classList.add('active');
        
        document.getElementById('complaintView').style.display = subtabId === 'complaint-view' ? 'block' : 'none';
        document.getElementById('dateRangeView').style.display = subtabId === 'date-range' ? 'block' : 'none';
    });
});

// Complaint View - Load treatments when complaint is selected
const complaintSelect = document.getElementById('complaintSelect');
const complaintResults = document.getElementById('complaintResults');
const complaintEmpty = document.getElementById('complaintEmpty');
const treatmentsList = document.getElementById('treatmentsList');
const doctorsExperienceList = document.getElementById('doctorsExperienceList');

complaintSelect.addEventListener('change', async () => {
    const complaintCode = complaintSelect.value;
    
    if (!complaintCode) {
        complaintResults.style.display = 'none';
        complaintEmpty.style.display = 'block';
        return;
    }
    
    // Show loading state
    treatmentsList.innerHTML = '<div class="loading-spinner" style="margin: 20px auto; display: block;"></div>';
    doctorsExperienceList.innerHTML = '<div class="loading-spinner" style="margin: 20px auto; display: block;"></div>';
    complaintResults.style.display = 'block';
    complaintEmpty.style.display = 'none';
    
    try {
        const formData = new FormData();
        formData.append('action', 'get_treatments_by_complaint');
        formData.append('complaint_code', complaintCode);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Render treatments
            if (data.treatments && data.treatments.length > 0) {
                treatmentsList.innerHTML = data.treatments.map(t => `
                    <div class="treatment-item">
                        <div style="font-weight: 600; color: #1E3A5F; margin-bottom: 8px;">
                            Treatment #${escapeHtml(t.t_code)}
                        </div>
                        <div style="font-size: 0.85rem; color: #6B7E92;">
                            Patient: ${escapeHtml(t.patient_name || 'Unknown')}
                        </div>
                        <div style="font-size: 0.85rem; color: #6B7E92;">
                            Doctor: ${escapeHtml(t.doctor_name || 'Unknown')}
                        </div>
                        <div style="font-size: 0.75rem; color: #A0AEC0; margin-top: 8px;">
                            Started: ${t.startdate ? new Date(t.startdate).toLocaleDateString() : 'N/A'} | 
                            Ended: ${t.enddate ? new Date(t.enddate).toLocaleDateString() : 'Ongoing'}
                        </div>
                    </div>
                `).join('');
            } else {
                treatmentsList.innerHTML = '<div class="empty-state" style="padding: 40px;"><p>No treatments found for this complaint</p></div>';
            }
            
            // Render doctors experience
            if (data.doctors && data.doctors.length > 0) {
                // Group doctors to show unique ones with their experience
                const uniqueDoctors = {};
                data.doctors.forEach(d => {
                    if (!uniqueDoctors[d.doctor_name]) {
                        uniqueDoctors[d.doctor_name] = {
                            doctor_name: d.doctor_name,
                            position: d.position,
                            speciality: d.speciality,
                            experiences: []
                        };
                    }
                    if (d.establishment) {
                        uniqueDoctors[d.doctor_name].experiences.push({
                            establishment: d.establishment,
                            position: d.prev_position,
                            from_date: d.from_date,
                            to_date: d.to_date
                        });
                    }
                });
                
                doctorsExperienceList.innerHTML = Object.values(uniqueDoctors).map(d => `
                    <div class="treatment-item">
                        <div style="font-weight: 600; color: #1E3A5F; margin-bottom: 8px;">
                            ${escapeHtml(d.doctor_name)}
                        </div>
                        <div style="font-size: 0.85rem; color: #6B7E92;">
                            ${escapeHtml(d.position || 'Doctor')}
                        </div>
                        <div style="font-size: 0.85rem; color: #6B7E92; margin-bottom: 12px;">
                            Specialty: ${escapeHtml(d.speciality || 'General')}
                        </div>
                        ${d.experiences.length > 0 ? `
                            <div style="font-size: 0.75rem; color: #A0AEC0;">
                                <strong>Experience:</strong>
                                ${d.experiences.map(exp => `
                                    <div>• ${exp.position || 'Position'} at ${exp.establishment} 
                                    (${exp.from_date ? new Date(exp.from_date).getFullYear() : 'N/A'} - ${exp.to_date ? new Date(exp.to_date).getFullYear() : 'Present'})</div>
                                `).join('')}
                            </div>
                        ` : '<div style="font-size: 0.75rem; color: #A0AEC0;">No experience records available</div>'}
                    </div>
                `).join('');
            } else {
                doctorsExperienceList.innerHTML = '<div class="empty-state" style="padding: 40px;"><p>No doctors found for this complaint</p></div>';
            }
        }
    } catch (error) {
        console.error('Error:', error);
        treatmentsList.innerHTML = '<div class="empty-state"><p>Error loading treatments</p></div>';
    }
});

// Date Range Filter functionality
const applyFilterBtn = document.getElementById('applyFilterBtn');
const clearFiltersBtn = document.getElementById('clearFiltersBtn');
const filterComplaintSelect = document.getElementById('filterComplaintSelect');
const startDate = document.getElementById('startDate');
const endDate = document.getElementById('endDate');
const treatmentsByDateList = document.getElementById('treatmentsByDateList');

async function loadTreatmentsByDateRange() {
    const complaintCode = filterComplaintSelect.value;
    const start = startDate.value;
    const end = endDate.value;
    
    treatmentsByDateList.innerHTML = '<div class="loading-spinner" style="margin: 40px auto; display: block;"></div>';
    
    try {
        const formData = new FormData();
        formData.append('action', 'get_treatments_by_date_range');
        formData.append('complaint_code', complaintCode);
        formData.append('start_date', start);
        formData.append('end_date', end);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.treatments) {
            if (data.treatments.length > 0) {
                treatmentsByDateList.innerHTML = data.treatments.map(t => `
                    <div class="treatment-item">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <div style="font-weight: 600; color: #1E3A5F; margin-bottom: 8px;">
                                    Treatment #${escapeHtml(t.t_code)}
                                </div>
                                <div style="font-size: 0.85rem; color: #6B7E92;">
                                    Patient: ${escapeHtml(t.patient_name)}
                                </div>
                                <div style="font-size: 0.85rem; color: #6B7E92;">
                                    Doctor: ${escapeHtml(t.doctor_name)}
                                </div>
                                <div style="font-size: 0.85rem; color: #6B7E92;">
                                    Date: ${t.treatment_start_date ? new Date(t.treatment_start_date).toLocaleDateString() : 'N/A'}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div class="grade-badge grade-Good" style="margin-bottom: 8px;">
                                    ${escapeHtml(t.complaint_title)}
                                </div>
                                <div style="font-size: 0.75rem; color: #A0AEC0;">
                                    ${t.treatment_end_date ? 'Completed: ' + new Date(t.treatment_end_date).toLocaleDateString() : 'Ongoing'}
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else {
                treatmentsByDateList.innerHTML = '<div class="empty-state"><span class="material-icons">search</span><h3>No treatments found</h3><p>Try adjusting your filter criteria</p></div>';
            }
        }
    } catch (error) {
        console.error('Error:', error);
        treatmentsByDateList.innerHTML = '<div class="empty-state"><p>Error loading treatments</p></div>';
    }
}

applyFilterBtn.addEventListener('click', loadTreatmentsByDateRange);
clearFiltersBtn.addEventListener('click', () => {
    filterComplaintSelect.value = '';
    startDate.value = '';
    endDate.value = '';
    loadTreatmentsByDateRange();
});

// Performance & History functionality
const doctorSearchInput = document.getElementById('doctorSearchInput');
const doctorSuggestions = document.getElementById('doctorSuggestions');
const doctorPerformanceResults = document.getElementById('doctorPerformanceResults');
const performanceEmpty = document.getElementById('performanceEmpty');
const doctorInfo = document.getElementById('doctorInfo');
const performanceHistory = document.getElementById('performanceHistory');
const experienceHistory = document.getElementById('experienceHistory');

let doctorsList = <?php echo json_encode($doctor_list); ?>;

doctorSearchInput.addEventListener('input', () => {
    const searchTerm = doctorSearchInput.value.toLowerCase();
    
    if (searchTerm.length < 2) {
        doctorSuggestions.style.display = 'none';
        return;
    }
    
    const filtered = doctorsList.filter(doc => 
        doc.name.toLowerCase().includes(searchTerm) || 
        doc.specialty.toLowerCase().includes(searchTerm)
    );
    
    if (filtered.length > 0) {
        doctorSuggestions.innerHTML = filtered.map(doc => `
            <div class="doctor-suggestion-item" data-doctor-id="${doc.id}" data-doctor-name="${doc.name}" data-doctor-specialty="${doc.specialty}" data-doctor-position="${doc.position}">
                <div class="doctor-suggestion-name">${escapeHtml(doc.name)}</div>
                <div class="doctor-suggestion-specialty">${escapeHtml(doc.specialty)} • ${escapeHtml(doc.position)}</div>
            </div>
        `).join('');
        doctorSuggestions.style.display = 'block';
        
        // Add click handlers
        document.querySelectorAll('.doctor-suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                const doctorId = item.dataset.doctorId;
                const doctorName = item.dataset.doctorName;
                const doctorSpecialty = item.dataset.doctorSpecialty;
                const doctorPosition = item.dataset.doctorPosition;
                
                doctorSearchInput.value = doctorName;
                doctorSuggestions.style.display = 'none';
                loadDoctorPerformance(doctorId, doctorName, doctorSpecialty, doctorPosition);
            });
        });
    } else {
        doctorSuggestions.style.display = 'none';
    }
});

// Close suggestions when clicking outside
document.addEventListener('click', (e) => {
    if (!doctorSearchInput.contains(e.target) && !doctorSuggestions.contains(e.target)) {
        doctorSuggestions.style.display = 'none';
    }
});

async function loadDoctorPerformance(doctorId, doctorName, doctorSpecialty, doctorPosition) {
    doctorPerformanceResults.style.display = 'block';
    performanceEmpty.style.display = 'none';
    
    // Show loading
    performanceHistory.innerHTML = '<div class="loading-spinner" style="margin: 40px auto; display: block;"></div>';
    experienceHistory.innerHTML = '<div class="loading-spinner" style="margin: 40px auto; display: block;"></div>';
    
    // Display doctor info
    doctorInfo.innerHTML = `
        <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
            <div class="doctor-avatar">${doctorName.charAt(0)}</div>
            <div>
                <h2 style="color: #1A2B3C; margin-bottom: 8px;">${escapeHtml(doctorName)}</h2>
                <p style="color: #6B7E92; margin-bottom: 4px;">${escapeHtml(doctorPosition)}</p>
                <p style="color: #6B7E92;">${escapeHtml(doctorSpecialty)}</p>
            </div>
        </div>
    `;
    
    try {
        const formData = new FormData();
        formData.append('action', 'get_doctor_performance');
        formData.append('doctor_id', doctorId);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Render performance history
            if (data.performance && data.performance.length > 0) {
                performanceHistory.innerHTML = `
                    <h3 style="color: #1E3A5F; margin-bottom: 20px;">Patient Progress Notes</h3>
                    ${data.performance.map(p => `
                        <div class="performance-item">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 12px;">
                                <span class="grade-badge grade-Good">Patient: ${escapeHtml(p.patient_name || 'N/A')}</span>
                                <span style="font-size: 0.8rem; color: #A0AEC0;">${p.review_date ? new Date(p.review_date).toLocaleDateString() : 'Date unknown'}</span>
                            </div>
                            <p style="color: #4A5568; margin-bottom: 12px;">${escapeHtml(p.performance_grade || 'No notes')}</p>
                        </div>
                    `).join('')}
                `;
            } else {
                performanceHistory.innerHTML = '<div class="empty-state" style="padding: 40px;"><p>No progress notes found for this doctor</p></div>';
            }
            
            // Render experience history from PrevExperience
            if (data.experience && data.experience.length > 0) {
                experienceHistory.innerHTML = `
                    <h3 style="color: #1E3A5F; margin-bottom: 20px;">Previous Experience</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #F8FAFC; border-bottom: 2px solid #E2E8F0;">
                                    <th style="padding: 12px; text-align: left; color: #4A5568;">From</th>
                                    <th style="padding: 12px; text-align: left; color: #4A5568;">To</th>
                                    <th style="padding: 12px; text-align: left; color: #4A5568;">Position</th>
                                    <th style="padding: 12px; text-align: left; color: #4A5568;">Establishment</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.experience.map(exp => `
                                    <tr style="border-bottom: 1px solid #E2E8F0;">
                                        <td style="padding: 12px; color: #6B7E92;">${exp.from_date ? new Date(exp.from_date).toLocaleDateString() : 'N/A'}</td>
                                        <td style="padding: 12px; color: #6B7E92;">${exp.to_date ? new Date(exp.to_date).toLocaleDateString() : 'Present'}</td>
                                        <td style="padding: 12px; color: #1A2B3C;">${escapeHtml(exp.position || 'N/A')}</td>
                                        <td style="padding: 12px; color: #6B7E92;">${escapeHtml(exp.establishment || 'N/A')}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                experienceHistory.innerHTML = '<div class="empty-state" style="padding: 40px;"><p>No experience history found</p></div>';
            }
        }
    } catch (error) {
        console.error('Error:', error);
        performanceHistory.innerHTML = '<div class="empty-state"><p>Error loading performance data</p></div>';
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Show initial empty state for complaint view
complaintEmpty.style.display = 'block';

// Initially load empty date range view
treatmentsByDateList.innerHTML = '<div class="empty-state"><span class="material-icons">filter_alt</span><h3>Apply filters to view treatments</h3><p>Select complaint and date range above</p></div>';
</script>
