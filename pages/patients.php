<?php
// pages/patients.php
?>
<div class="page-header">
    <h1 class="page-title">Patients</h1>
    <p class="page-subtitle">Manage and view all patient records</p>
</div>

<div class="welcome-card">
    <h3>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h3>
    <p>You are logged in as <?php echo htmlspecialchars($_SESSION['role']); ?></p>
</div>

<div class="todo-card">
    <div class="todo-icon">
        <span class="material-icons" style="font-size: 80px;">assignment</span>
    </div>
    <div class="todo-text">// TODO</div>
    <div class="todo-subtext">Patients module coming soon. This will display patient lists, admissions, and medical history.</div>
</div>