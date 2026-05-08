<?php
session_start();
require_once 'db_connect.php';

$error = '';
$shake = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $conn = db();

        $sql = "
            SELECT s.st_id, s.fname, s.lname, s.email,
                   CASE
                       WHEN EXISTS (SELECT 1 FROM Consultant c WHERE c.c_id = s.st_id) THEN 'Consultant'
                       WHEN EXISTS (SELECT 1 FROM Doctor    d WHERE d.d_id = s.st_id) THEN 'Doctor'
                       WHEN EXISTS (SELECT 1 FROM Nurse     n WHERE n.n_id = s.st_id) THEN 'Nurse'
                       ELSE 'Staff'
                   END AS staff_type
            FROM Staff s
            INNER JOIN UserLogin ul ON s.st_id = ul.st_id
            WHERE s.email = ?
              AND ul.password_hash = CONVERT(VARCHAR(255), HASHBYTES('MD5', ?), 2)
              AND ul.is_active = 1
        ";

        $params = [$email, $password];
        $stmt   = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $error = 'Query error. Please try again.';
        } else {
            $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

            if ($user) {
                $_SESSION['user_id']   = $user['st_id'];
                $_SESSION['full_name'] = $user['fname'] . ' ' . $user['lname'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['role']      = $user['staff_type'];
                sqlsrv_free_stmt($stmt);
                sqlsrv_close($conn);
                header('Location: main.php');
                exit;
            } else {
                $error = 'Incorrect email or password.';
                $shake = true;
            }

            sqlsrv_free_stmt($stmt);
        }

        sqlsrv_close($conn);
    } else {
        $error = 'Please enter both email and password.';
        $shake = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>IPMH — Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../assets/styles.css"/>
</head>
<body>
<div class="page">

    <!-- LEFT PANEL -->
    <div class="left">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
        <div class="ring ring-1"></div>
        <div class="ring ring-2"></div>
        <div class="ring ring-3"></div>
        <div class="dot-1"></div>
        <div class="dot-2"></div>

        <div class="left-content">
            <h1 class="hello">HELLO&nbsp;<span>!</span></h1>
            <p class="tagline">Please enter your details<br>to continue</p>
        </div>

        <!-- wave divider SVG -->
        <div class="wave-divider">
            <svg viewBox="0 0 80 800" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M80,0 C40,100 60,200 40,300 C20,400 60,500 40,600 C20,700 50,750 80,800 L80,0 Z"
                      fill="#F4F7FB"/>
            </svg>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right">
        <div class="ring-r1"></div>
        <div class="ring-r2"></div>
        <div class="dot-r1"></div>

        <div class="form-box">
            <!-- Brand -->
            <div class="brand">
                <img src="../assets/visualAssets/logo-blue.png" alt="IPMH Logo"/>
            </div>

            <h2 class="form-title">Welcome back</h2>
            <p class="form-sub">Sign in to your account</p>

            <form method="POST" action="" id="loginForm" novalidate>
                <!-- Email -->
                <div class="field">
                    <label for="email">Email Address</label>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="2"/>
                                <path d="M2 7l10 7 10-7"/>
                            </svg>
                        </span>
                        <input type="email" id="email" name="email"
                               placeholder="you@hospital.pk"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               autocomplete="email"
                               class="<?= ($error) ? 'error-field' : '' ?>"/>
                    </div>
                </div>

                <!-- Password -->
                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap <?= $shake ? 'shake' : '' ?>" id="pwWrap">
                        <span class="input-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <input type="password" id="password" name="password"
                               placeholder="••••••••"
                               autocomplete="current-password"
                               class="<?= $shake ? 'error-field' : '' ?>"/>
                        <button type="button" class="eye-toggle" id="eyeBtn" aria-label="Toggle password">
                            <svg id="eyeIcon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <?php if ($error): ?>
                    <div class="error-msg">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-label">Log In</span>
                    <div class="spinner"></div>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Eye toggle
const eyeBtn  = document.getElementById('eyeBtn');
const pwInput = document.getElementById('password');
const eyeIcon = document.getElementById('eyeIcon');

const eyeOpen   = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
const eyeClosed = '<line x1="1" y1="1" x2="23" y2="23"/><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>';

if (eyeBtn) {
    eyeBtn.addEventListener('click', () => {
        const hidden = pwInput.type === 'password';
        pwInput.type = hidden ? 'text' : 'password';
        eyeIcon.innerHTML = hidden ? eyeClosed : eyeOpen;
    });
}

// Show spinner on submit
document.getElementById('loginForm').addEventListener('submit', function () {
    const btn = document.getElementById('loginBtn');
    btn.classList.add('loading');
});
</script>
</body>
</html>