<?php
// pages/staff_wards.php
// Comprehensive Staff and Wards Management Module


/** */

require_once '../php/db_connect.php';
$conn = db();

// Initialize variables
$error = '';
$success = '';
$action = $_GET['action'] ?? '';
$tab = $_GET['tab'] ?? 'staff';

// Handle form submissions for editing/adding
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_staff') {
        // Add new staff member
        $fname = trim($_POST['fname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telno = trim($_POST['telno'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        if ($fname && $lname && $email && $telno && $address) {
            $sql = "INSERT INTO Staff (fname, lname, email, telno, address) VALUES (?, ?, ?, ?, ?)";
            $params = [$fname, $lname, $email, $telno, $address];
            $result = sqlsrv_query($conn, $sql, $params);
            
            if ($result) {
                $success = 'Staff member added successfully!';
            } else {
                $error = 'Error adding staff member. Email might already exist.';
            }
        } else {
            $error = 'Please fill in all required fields.';
        }
    }
}

// Fetch staff data with roles
$staffQuery = "
    SELECT 
        s.st_id,
        s.fname,
        s.lname,
        s.email,
        s.telno,
        s.address,
        s.created_date,
        CASE
            WHEN EXISTS (SELECT 1 FROM Consultant c WHERE c.c_id = s.st_id) THEN 'Consultant'
            WHEN EXISTS (SELECT 1 FROM Doctor d WHERE d.d_id = s.st_id) THEN 'Doctor'
            WHEN EXISTS (SELECT 1 FROM Nurse n WHERE n.n_id = s.st_id) THEN 'Nurse'
            ELSE 'Staff'
        END AS staff_type
    FROM Staff s
    ORDER BY s.created_date ASC
";
$staffResult = sqlsrv_query($conn, $staffQuery);
$staff = [];
if ($staffResult) {
    while ($row = sqlsrv_fetch_array($staffResult, SQLSRV_FETCH_ASSOC)) {
        $staff[] = $row;
    }
}

// Fetch wards with detailed information
$wardsQuery = "
    SELECT 
        w.w_id,
        w.name,
        sp.speciality,
        CAST(sp.description AS NVARCHAR(MAX)) AS description,
        ISNULL(CONCAT(s1.fname, ' ', s1.lname), 'Unassigned') AS day_sister_name,
        ISNULL(CONCAT(s2.fname, ' ', s2.lname), 'Unassigned') AS night_sister_name,
        w.day_sister,
        w.night_sister,
        ISNULL(COUNT(DISTINCT CASE WHEN p.discharge_date IS NULL THEN p.p_id END), 0) AS current_patients,
        ISNULL(COUNT(DISTINCT n.n_id), 0) AS nurse_count,
        w.created_date,
        sp.sp_id
    FROM Ward w
    LEFT JOIN Speciality sp ON w.sp_id = sp.sp_id
    LEFT JOIN Staff s1 ON w.day_sister = s1.st_id
    LEFT JOIN Staff s2 ON w.night_sister = s2.st_id
    LEFT JOIN Patient p ON w.w_id = p.w_id
    LEFT JOIN Nurse n ON w.w_id = n.w_id
    GROUP BY w.w_id, w.name, sp.speciality,
             CAST(sp.description AS NVARCHAR(MAX)),
             s1.fname, s1.lname, s2.fname, s2.lname,
             w.created_date, sp.sp_id, w.day_sister, w.night_sister
    ORDER BY w.w_id
";
$wardsResult = sqlsrv_query($conn, $wardsQuery);
$wards = [];
if ($wardsResult) {
    while ($row = sqlsrv_fetch_array($wardsResult, SQLSRV_FETCH_ASSOC)) {
        $wards[] = $row;
    }
}


// Fetch doctors with specialization
$doctorsQuery = "
    SELECT 
        d.d_id,
        s.fname,
        s.lname,
        s.email,
        s.telno,
        d.position,
        t.team_name,
        t.t_id,
        ISNULL(sp.speciality, 'N/A') AS speciality,
        COUNT(DISTINCT tr.t_code) AS patient_count
    FROM Doctor d
    INNER JOIN Staff s ON d.d_id = s.st_id
    LEFT JOIN Team t ON d.t_id = t.t_id
    LEFT JOIN Consultant c ON d.d_id = c.c_id
    LEFT JOIN Speciality sp ON c.sp_id = sp.sp_id
    LEFT JOIN Treatment tr ON d.d_id = tr.d_id
    GROUP BY d.d_id, s.fname, s.lname, s.email, s.telno, d.position, t.team_name, t.t_id, sp.speciality
    ORDER BY d.d_id
";
$doctorsResult = sqlsrv_query($conn, $doctorsQuery);
$doctors = [];
if ($doctorsResult) {
    while ($row = sqlsrv_fetch_array($doctorsResult, SQLSRV_FETCH_ASSOC)) {
        $doctors[] = $row;
    }
}

// Fetch nurses with ward assignments
$nursesQuery = "
    SELECT 
        n.n_id,
        s.fname,
        s.lname,
        s.email,
        s.telno,
        w.name AS ward_name,
        nwa.shift,
        nwa.from_date,
        nwa.to_date,
        COUNT(DISTINCT p.p_id) AS patient_count
    FROM Nurse n
    INNER JOIN Staff s ON n.n_id = s.st_id
    LEFT JOIN Ward w ON n.w_id = w.w_id
    LEFT JOIN Nurse_Ward_Assignment nwa ON n.n_id = nwa.n_id AND nwa.to_date IS NULL
    LEFT JOIN Patient p ON w.w_id = p.w_id AND p.discharge_date IS NULL
    GROUP BY n.n_id, s.fname, s.lname, s.email, s.telno, w.name, nwa.shift, nwa.from_date, nwa.to_date
    ORDER BY n.n_id
";
$nursesResult = sqlsrv_query($conn, $nursesQuery);
$nurses = [];
if ($nursesResult) {
    while ($row = sqlsrv_fetch_array($nursesResult, SQLSRV_FETCH_ASSOC)) {
        $nurses[] = $row;
    }
}

// Fetch teams
$teamsQuery = "
    SELECT 
        t.t_id,
        t.team_name,
        CONCAT(s.fname, ' ', s.lname) AS team_lead_name,
        COUNT(DISTINCT d.d_id) AS doctor_count
    FROM Team t
    LEFT JOIN Doctor tl ON t.team_lead = tl.d_id
    LEFT JOIN Staff s ON tl.d_id = s.st_id
    LEFT JOIN Doctor d ON t.t_id = d.t_id
    GROUP BY t.t_id, t.team_name, s.fname, s.lname
    ORDER BY t.t_id
";
$teamsResult = sqlsrv_query($conn, $teamsQuery);
$teams = [];
if ($teamsResult) {
    while ($row = sqlsrv_fetch_array($teamsResult, SQLSRV_FETCH_ASSOC)) {
        $teams[] = $row;
    }
}
$doctorsByTeam = [];
$teamDoctorsQ = "
    SELECT d.d_id, d.t_id, d.position,
           s.fname, s.lname, s.email, s.telno,
           ISNULL(sp.speciality, 'N/A') AS speciality,
           CASE WHEN c.c_id IS NOT NULL THEN 1 ELSE 0 END AS is_consultant
    FROM Doctor d
    INNER JOIN Staff s ON d.d_id = s.st_id
    LEFT JOIN Consultant c ON d.d_id = c.c_id
    LEFT JOIN Speciality sp ON c.sp_id = sp.sp_id
    WHERE d.t_id IS NOT NULL
    ORDER BY d.t_id, s.lname
";
$teamDoctorsR = sqlsrv_query($conn, $teamDoctorsQ);
if ($teamDoctorsR) {
    while ($row = sqlsrv_fetch_array($teamDoctorsR, SQLSRV_FETCH_ASSOC)) {
        $doctorsByTeam[$row['t_id']][] = $row;
    }
}
sqlsrv_close($conn);
?>

<div class="page-header">
    <h1 class="page-title">Staff and Wards Management</h1>
    <p class="page-subtitle">Manage hospital staff, doctors, nurses, wards, care units, and team assignments</p>
</div>

<?php if ($error): ?>
<div class="alert alert-danger" role="alert">
    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success" role="alert">
    <strong>Success!</strong> <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<!-- Navigation Tabs -->
<div class="nav-tabs-container">
    <nav class="nav-tabs" role="tablist">
        <button class="nav-tab-button <?= ($tab === 'staff' || $tab === '') ? 'active' : '' ?>" 
                data-tab="staff" role="tab">
            <span class="material-icons">people</span>
            All Staff
        </button>
        <button class="nav-tab-button <?= ($tab === 'doctors') ? 'active' : '' ?>" 
                data-tab="doctors" role="tab">
            <span class="material-icons">local_hospital</span>
            Doctors
        </button>
        <button class="nav-tab-button <?= ($tab === 'nurses') ? 'active' : '' ?>" 
                data-tab="nurses" role="tab">
            <span class="material-icons">health_and_safety</span>
            Nurses
        </button>
        <button class="nav-tab-button <?= ($tab === 'wards') ? 'active' : '' ?>" 
                data-tab="wards" role="tab">
            <span class="material-icons">apartment</span>
            Wards
        </button>
        <button class="nav-tab-button <?= ($tab === 'teams') ? 'active' : '' ?>" 
                data-tab="teams" role="tab">
            <span class="material-icons">groups</span>
            Teams
        </button>
    </nav>
</div>

<!-- ===== TAB 1: ALL STAFF ===== -->
<div id="staff-tab" class="tab-content <?= ($tab === 'staff' || $tab === '') ? 'active' : '' ?>">
    <div class="section-header">
        <h2>All Staff Members</h2>
        <div class="section-actions">
            <input type="text" id="staffSearch" class="search-input" placeholder="Search staff...">
            <button class="btn btn-primary" data-toggle="modal" data-target="#addStaffModal">
                <span class="material-icons">add</span> Add Staff
            </button>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="data-table" id="staffTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Role</th>
                    <th>Date Added</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff as $member): ?>
                <tr class="staff-row">
                    <td><?= $member['st_id'] ?></td>
                    <td class="name-cell">
                        <strong><?= htmlspecialchars($member['fname'] . ' ' . $member['lname']) ?></strong>
                    </td>
                    <td><?= htmlspecialchars($member['email']) ?></td>
                    <td><?= htmlspecialchars($member['telno']) ?></td>
                    <td><?= htmlspecialchars($member['address']) ?></td>
                    <td>
                        <span class="badge badge-<?= strtolower($member['staff_type']) ?>">
                            <?= htmlspecialchars($member['staff_type']) ?>
                        </span>
                    </td>
                    <td><?= date('M d, Y', strtotime($member['created_date']->format('Y-m-d H:i:s'))) ?></td>
                    <td>
                        <button class="btn btn-small btn-info" title="View Details">
                            <span class="material-icons">visibility</span>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($staff)): ?>
        <div class="empty-state">
            <p>No staff members found</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== TAB 2: DOCTORS ===== -->
<div id="doctors-tab" class="tab-content <?= ($tab === 'doctors') ? 'active' : '' ?>">
    <div class="section-header">
        <h2>Doctors Directory</h2>
        <div class="section-actions">
            <input type="text" id="doctorSearch" class="search-input" placeholder="Search doctors...">
        </div>
    </div>

    <div class="cards-grid">
        <?php foreach ($doctors as $doctor): ?>
        <div class="staff-card" id="doctor-<?= $doctor['d_id'] ?>">
            <div class="card-header">
                <h3><?= htmlspecialchars($doctor['fname'] . ' ' . $doctor['lname']) ?></h3>
                <span class="badge badge-doctor"><?= htmlspecialchars($doctor['position']) ?></span>
            </div>
            <div class="card-body">
                <div class="info-group">
                    <label>Specialization</label>
                    <p><?= htmlspecialchars($doctor['speciality']) ?></p>
                </div>
                <div class="info-group">
                    <label>Team</label>
                    <p><?= htmlspecialchars($doctor['team_name'] ?? 'Unassigned') ?></p>
                </div>
                <div class="info-group">
                    <label>Contact</label>
                    <p><?= htmlspecialchars($doctor['email']) ?></p>
                    <p><?= htmlspecialchars($doctor['telno']) ?></p>
                </div>
                <div class="info-group">
                    <label>Current Patients</label>
                    <p class="stat-number"><?= $doctor['patient_count'] ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($doctors)): ?>
    <div class="empty-state">
        <p>No doctors found</p>
    </div>
    <?php endif; ?>
</div>

<!-- ===== TAB 3: NURSES ===== -->
<div id="nurses-tab" class="tab-content <?= ($tab === 'nurses') ? 'active' : '' ?>">
    <div class="section-header">
        <h2>Nursing Staff</h2>
        <div class="section-actions">
            <input type="text" id="nurseSearch" class="search-input" placeholder="Search nurses...">
        </div>
    </div>

    <div class="table-wrapper">
        <table class="data-table" id="nursesTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Assigned Ward</th>
                    <th>Shift</th>
                    <th>Active From</th>
                    <th>Patients Assigned</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($nurses as $nurse): ?>
                <tr class="nurse-row">
                    <td><?= $nurse['n_id'] ?></td>
                    <td class="name-cell">
                        <strong><?= htmlspecialchars($nurse['fname'] . ' ' . $nurse['lname']) ?></strong>
                    </td>
                    <td><?= htmlspecialchars($nurse['email']) ?></td>
                    <td><?= htmlspecialchars($nurse['telno']) ?></td>
                    <td><?= htmlspecialchars($nurse['ward_name'] ?? 'Unassigned') ?></td>
                    <td>
                        <?php if ($nurse['shift']): ?>
                        <span class="badge badge-info"><?= htmlspecialchars($nurse['shift']) ?></span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($nurse['from_date']): ?>
                        <?= date('M d, Y', strtotime($nurse['from_date']->format('Y-m-d'))) ?>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="stat-badge"><?= $nurse['patient_count'] ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($nurses)): ?>
        <div class="empty-state">
            <p>No nurses found</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== TAB 4: WARDS ===== -->
<div id="wards-tab" class="tab-content <?= ($tab === 'wards') ? 'active' : '' ?>">
    <div class="section-header">
        <h2>Hospital Wards</h2>
        <div class="section-actions">
            <input type="text" id="wardSearch" class="search-input" placeholder="Search wards...">
        </div>
    </div>

    <div class="wards-grid">
        <?php foreach ($wards as $ward): ?>
        <div class="ward-card" id="ward-<?= $ward['w_id'] ?>">
            <div class="card-header-ward">
                <h3><?= htmlspecialchars($ward['name']) ?></h3>
                <span class="badge badge-primary"><?= htmlspecialchars($ward['speciality']) ?></span>
            </div>
            <div class="card-body">
                <div class="info-group">
                    <label>Specialization</label>
                    <p><?= htmlspecialchars($ward['description']) ?></p>
                </div>
                <div class="info-row">
                    <div class="info-group">
                        <label>Day Sister</label>
                        <p><?= htmlspecialchars($ward['day_sister_name'] ?? 'Unassigned') ?></p>
                    </div>
                    <div class="info-group">
                        <label>Night Sister</label>
                        <p><?= htmlspecialchars($ward['night_sister_name'] ?? 'Unassigned') ?></p>
                    </div>
                </div>
                <div class="info-row">
                    <div class="stat-box">
                        <span class="stat-label">Current Patients</span>
                        <span class="stat-number"><?= $ward['current_patients'] ?></span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label">Nurses</span>
                        <span class="stat-number"><?= $ward['nurse_count'] ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($wards)): ?>
    <div class="empty-state">
        <p>No wards found</p>
    </div>
    <?php endif; ?>
    
</div>

<!-- ===== TAB 5: TEAMS ===== -->
<!-- ===== TAB 5: TEAMS ===== -->
<div id="teams-tab" class="tab-content <?= ($tab === 'teams') ? 'active' : '' ?>">
    <div class="section-header">
        <h2>Medical Teams</h2>
    </div>

    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Team ID</th>
                    <th>Team Name</th>
                    <th>Team Lead</th>
                    <th>Doctors</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teams as $team): ?>
                <tr class="team-row">
                    <td><?= $team['t_id'] ?></td>
                    <td><strong><?= htmlspecialchars($team['team_name']) ?></strong></td>
                    <td><?= htmlspecialchars($team['team_lead_name'] ?? 'Unassigned') ?></td>
                    <td><span class="stat-badge"><?= $team['doctor_count'] ?></span></td>
                    <td>
                        <button class="btn btn-small btn-info" 
                                onclick="openTeamModal(<?= (int)$team['t_id'] ?>)"
                                title="View team members">
                            <span class="material-icons">groups</span> View Team
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($teams)): ?>
        <div class="empty-state"><p>No teams found</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== MODAL: ADD STAFF ===== -->
<div class="modal" id="addStaffModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Staff Member</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_staff">
                    
                    <div class="form-group">
                        <label for="fname">First Name *</label>
                        <input type="text" id="fname" name="fname" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lname">Last Name *</label>
                        <input type="text" id="lname" name="lname" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telno">Phone Number *</label>
                        <input type="tel" id="telno" name="telno" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address *</label>
                        <textarea id="address" name="address" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Staff Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Staff and Wards Page Styles */
.page-header {
    margin-bottom: 2rem;
}

.nav-tabs-container {
    margin-bottom: 2rem;
    border-bottom: 1px solid #e5e7eb;
}

.nav-tabs {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
}

.nav-tab-button {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    background: none;
    color: #6b7280;
    font-weight: 500;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.nav-tab-button:hover {
    color: #1f2937;
    border-bottom-color: #d1d5db;
}

.nav-tab-button.active {
    color: #2563eb;
    border-bottom-color: #2563eb;
}

.nav-tab-button .material-icons {
    font-size: 20px;
}

.tab-content {
    display: none;
    animation: fadeIn 0.3s ease;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-header h2 {
    margin: 0;
    font-size: 1.5rem;
}

.section-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.search-input {
    padding: 0.5rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}

.table-wrapper {
    background: white;
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead {
    background-color: #f3f4f6;
    border-bottom: 1px solid #e5e7eb;
}

.data-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
    text-transform: uppercase;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
}

.data-table tbody tr:hover {
    background-color: #f9fafb;
}

.name-cell {
    font-weight: 500;
    color: #1f2937;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-doctor {
    background-color: #dbeafe;
    color: #0c4a6e;
}

.badge-consultant {
    background-color: #fce7f3;
    color: #831843;
}

.badge-nurse {
    background-color: #d1fae5;
    color: #065f46;
}

.badge-staff {
    background-color: #f3e8ff;
    color: #5b21b6;
}

.badge-primary {
    background-color: #dbeafe;
    color: #0c4a6e;
}

.badge-info {
    background-color: #d1fae5;
    color: #065f46;
}

.stat-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    background-color: #e0e7ff;
    color: #4f46e5;
    border-radius: 0.375rem;
    font-weight: 600;
    font-size: 0.875rem;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.staff-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.staff-card:hover {
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    font-size: 1.125rem;
}

.card-body {
    padding: 1.5rem;
}

.info-group {
    margin-bottom: 1rem;
}

.info-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    margin-bottom: 0.25rem;
}

.info-group p {
    margin: 0;
    color: #1f2937;
}

.info-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.stat-box {
    background-color: #f3f4f6;
    padding: 1rem;
    border-radius: 0.375rem;
    text-align: center;
}

.stat-label {
    display: block;
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}

.stat-number {
    display: block;
    font-size: 1.875rem;
    font-weight: 700;
    color: #2563eb;
}

.ward-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.ward-card:hover {
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.card-header-ward {
    background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    color: white;
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header-ward h3 {
    margin: 0;
    font-size: 1.125rem;
}

.wards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary {
    background-color: #2563eb;
    color: white;
}

.btn-primary:hover {
    background-color: #1d4ed8;
}

.btn-small {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.btn-info {
    background-color: #e0e7ff;
    color: #4f46e5;
}

.btn-info:hover {
    background-color: #c7d2fe;
}

.btn .material-icons {
    font-size: 18px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.show {
    display: flex;
}

.modal-dialog {
    background: white;
    border-radius: 0.5rem;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-content {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
}

.modal-body {
    padding: 1.5rem;
    flex: 1;
    overflow-y: auto;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
}

.form-control {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}

.form-control:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.alert {
    padding: 1rem;
    border-radius: 0.375rem;
    margin-bottom: 1.5rem;
}

.alert-danger {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert-success {
    background-color: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.text-muted {
    color: #6b7280;
}

@media (max-width: 768px) {
    .cards-grid, .wards-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .section-actions {
        width: 100%;
        flex-direction: column;
    }
    
    .search-input {
        width: 100%;
    }
    
    .nav-tabs {
        padding: 0.5rem 0;
    }
    
    .nav-tab-button {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    
    .data-table {
        font-size: 0.875rem;
    }
    
    .data-table th, .data-table td {
        padding: 0.75rem 0.5rem;
    }
}
</style>

<script>
// Tab switching functionality
document.querySelectorAll('.nav-tab-button').forEach(button => {
    button.addEventListener('click', function() {
        const tabName = this.getAttribute('data-tab');
        
        // Remove active class from all buttons
        document.querySelectorAll('.nav-tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Add active class to clicked button
        this.classList.add('active');
        
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show selected tab
        const selectedTab = document.getElementById(tabName + '-tab');
        if (selectedTab) {
            selectedTab.classList.add('active');
        }
        
        // Update URL without reloading
        window.history.replaceState({}, '', '?tab=' + tabName);
    });
});

// Modal functionality
document.querySelectorAll('[data-toggle="modal"]').forEach(trigger => {
    trigger.addEventListener('click', function() {
        const modalId = this.getAttribute('data-target');
        document.querySelector(modalId).classList.add('show');
    });
});

document.querySelectorAll('[data-dismiss="modal"]').forEach(button => {
    button.addEventListener('click', function() {
        this.closest('.modal').classList.remove('show');
    });
});

// Close modal when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
    });
});

// Search functionality
function setupSearch(inputId, tableId, rowClass) {
    const searchInput = document.getElementById(inputId);
    if (!searchInput) return;
    
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('.' + rowClass);
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
}

setupSearch('staffSearch', 'staffTable', 'staff-row');
setupSearch('doctorSearch', 'doctors-tab', 'staff-card');
setupSearch('nurseSearch', 'nursesTable', 'nurse-row');
setupSearch('wardSearch', 'wards-tab', 'ward-card');
</script>
