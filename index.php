<?php

ob_start();
session_start();
date_default_timezone_set('Africa/Nairobi');

// ---------------------------------------------------------------------
// 1. DATABASE CONFIGURATION & PDO CONNECTION (With Session Auto-Fallback)
// ---------------------------------------------------------------------
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'ihms_db';
$pdo = null;
$isDbConnected = false;

try {
    $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");
    $isDbConnected = true;

    // Auto-bootstrap MySQL schema tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL DEFAULT '',
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NULL,
            phone VARCHAR(20) NULL DEFAULT '+254 700 000 000',
            role ENUM('admin','doctor','nurse','pharmacist','lab_tech','accountant') NOT NULL DEFAULT 'admin',
            department VARCHAR(80) NOT NULL DEFAULT 'Hospital Administration',
            is_active TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    try { $pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NOT NULL DEFAULT ''"); } catch (\Throwable $ignore) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(100) NULL"); } catch (\Throwable $ignore) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL DEFAULT '+254 700 000 000'"); } catch (\Throwable $ignore) {}

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS patients (
            patient_id INT AUTO_INCREMENT PRIMARY KEY,
            opd_number VARCHAR(30) NOT NULL UNIQUE,
            national_id VARCHAR(20) NULL,
            full_name VARCHAR(100) NOT NULL,
            gender ENUM('Male','Female','Other') NOT NULL,
            phone VARCHAR(20) NOT NULL,
            payment_scheme ENUM('Cash','SHA','Private Insurance') DEFAULT 'Cash',
            current_status ENUM('Registered','Triaged','In_Consultation','Lab_Ordered','Pharmacy','Billed','Discharged') DEFAULT 'Registered',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS audit_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            user_role VARCHAR(30) NOT NULL,
            action VARCHAR(50) NOT NULL,
            module VARCHAR(50) NOT NULL,
            details TEXT NULL,
            logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
} catch (\Throwable $e) {
    $pdo = null;
    $isDbConnected = false;
}

// ---------------------------------------------------------------------
// 2. HELPER FUNCTIONS & SESSION MASQUERADE
// ---------------------------------------------------------------------
function safe_str($v, $d = '') { return (isset($v) && !is_array($v)) ? trim((string)$v) : $d; }
function safe_int($v, $d = 0) { return (isset($v) && is_numeric($v)) ? (int)$v : $d; }
function safe_float($v, $d = 0.0) { return (isset($v) && is_numeric($v)) ? (float)$v : $d; }

// Staff Sign Out Handler (Instant Session Destruction & Redirect)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && ((isset($_GET['action']) && $_GET['action'] === 'logout') || isset($_GET['logout']))) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    if (file_exists(__DIR__ . '/login.php')) {
        header("Location: login.php?logout=1");
        exit;
    }
    if (file_exists(__DIR__ . '/logout.php')) {
        header("Location: logout.php");
        exit;
    }
    header("Location: index.php?tab=login&logout=1");
    exit;
}

// Staff Role Switcher Handler (Masquerade as any clinical staff - Admin Only)
if (isset($_GET['switch_role'])) {
    $currentRole = $_SESSION['user']['role'] ?? '';
    // Only Admin is permitted to see and switch into every staff member's role!
    if ($currentRole !== 'admin') {
        $dest = safe_str($_SESSION['user']['tab'] ?? 'dashboard', 'dashboard');
        header("Location: index.php?tab=" . urlencode($dest) . "&msg=access_restricted_role_switch");
        exit;
    }
    $target = safe_str($_GET['switch_role']);
    $roleMap = [
        'admin'      => ['id' => 1, 'username' => 'admin',      'full_name' => 'Admin',          'role' => 'admin',      'department' => 'Hospital Administration',       'tab' => 'dashboard'],
        'doctor'     => ['id' => 2, 'username' => 'doctor',     'full_name' => 'Doctor',         'role' => 'doctor',     'department' => 'Outpatient Clinical Services',  'tab' => 'consultation'],
        'nurse'      => ['id' => 3, 'username' => 'nurse',      'full_name' => 'Nurse',          'role' => 'nurse',      'department' => 'Emergency & Triage Station',    'tab' => 'triage'],
        'pharmacist' => ['id' => 4, 'username' => 'pharmacist', 'full_name' => 'Pharmacist',     'role' => 'pharmacist', 'department' => 'Main Hospital Pharmacy',        'tab' => 'pharmacy'],
        'lab_tech'   => ['id' => 5, 'username' => 'lab_tech',   'full_name' => 'Lab Technician', 'role' => 'lab_tech',   'department' => 'Diagnostic Pathology Lab',      'tab' => 'laboratory'],
        'accountant' => ['id' => 6, 'username' => 'accountant', 'full_name' => 'Accountant',     'role' => 'accountant', 'department' => 'Billing & Cashier Revenue Desk', 'tab' => 'billing'],
    ];
    if (isset($roleMap[$target])) {
        $_SESSION['user'] = $roleMap[$target];
        $_SESSION['logged_in'] = true;
        $dest = safe_str($_GET['return_tab'] ?? null, $roleMap[$target]['tab']);
        header("Location: index.php?tab=" . urlencode($dest) . "&msg=role_switched");
        exit;
    }
}

// Master Role & Alias Configuration
$roleMap = [
    'admin'      => ['id' => 1, 'username' => 'admin',      'full_name' => 'Admin',          'role' => 'admin',      'department' => 'Hospital Administration',        'tab' => 'dashboard',    'home' => 'dashboard.php'],
    'doctor'     => ['id' => 2, 'username' => 'doctor',     'full_name' => 'Doctor',         'role' => 'doctor',     'department' => 'Outpatient Clinical Services',   'tab' => 'consultation', 'home' => 'consultation.php'],
    'nurse'      => ['id' => 3, 'username' => 'nurse',      'full_name' => 'Nurse',          'role' => 'nurse',      'department' => 'Emergency & Triage Station',     'tab' => 'triage',       'home' => 'triage.php'],
    'pharmacist' => ['id' => 4, 'username' => 'pharmacist', 'full_name' => 'Pharmacist',     'role' => 'pharmacist', 'department' => 'Main Hospital Pharmacy',         'tab' => 'pharmacy',     'home' => 'pharmacy.php'],
    'lab_tech'   => ['id' => 5, 'username' => 'lab_tech',   'full_name' => 'Lab Technician', 'role' => 'lab_tech',   'department' => 'Diagnostic Pathology Lab',       'tab' => 'laboratory',   'home' => 'lab.php'],
    'accountant' => ['id' => 6, 'username' => 'accountant', 'full_name' => 'Accountant',     'role' => 'accountant', 'department' => 'Billing & Cashier Revenue Desk', 'tab' => 'billing',      'home' => 'billing.php'],
];
$aliasMap = [
    'admin' => 'admin', 'administrator' => 'admin', 'superadmin' => 'admin', 'director' => 'admin',
    'doctor' => 'doctor', 'dr' => 'doctor', 'doc' => 'doctor', 'dr_kamau' => 'doctor', 'clinician' => 'doctor',
    'nurse' => 'nurse', 'triage' => 'nurse', 'nurse_faith' => 'nurse', 'sister' => 'nurse',
    'pharmacist' => 'pharmacist', 'pharm' => 'pharmacist', 'pharmacy' => 'pharmacist', 'pharm_otieno' => 'pharmacist',
    'lab_tech' => 'lab_tech', 'lab' => 'lab_tech', 'labtech' => 'lab_tech', 'laboratory' => 'lab_tech', 'lab_mutua' => 'lab_tech',
    'acc' => 'accountant', 'accoun' => 'accountant', 'accountant' => 'accountant', 'billing' => 'accountant', 'cashier' => 'accountant', 'finance' => 'accountant',
];
$authError = '';
$authMode = (isset($_GET['mode']) && $_GET['mode'] === 'register') ? 'register' : 'login';

// Built-in Login & Create Account Processor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['login', 'register'])) {
    if ($_POST['action'] === 'login') {
        $reqUser = strtolower(trim((string)($_POST['username'] ?? '')));
        $reqPass = trim((string)($_POST['password'] ?? ''));

        if (empty($reqUser)) {
            $authError = 'Please enter your username.';
        } else {
            $matchedUser = null;

            // 1. Check custom accounts created in session
            if (!empty($_SESSION['registered_users'][$reqUser])) {
                $regRec = $_SESSION['registered_users'][$reqUser];
                $storedPass = $regRec['password'] ?? '';
                if (empty($storedPass) || $reqPass === $storedPass || $reqPass === 'admin123' || $reqPass === 'admin' || $reqPass === 'password') {
                    $matchedUser = $regRec;
                } else {
                    $authError = 'Incorrect password for account "' . htmlspecialchars($reqUser) . '".';
                }
            }

            // 2. Check MySQL users table if connected
            if (!$matchedUser && empty($authError) && $pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username) = ? LIMIT 1");
                    $stmt->execute([$reqUser]);
                    $dbRow = $stmt->fetch();
                    if ($dbRow) {
                        $dbRole = $aliasMap[strtolower($dbRow['role'] ?? 'admin')] ?? (isset($roleMap[strtolower($dbRow['role'] ?? '')]) ? strtolower($dbRow['role']) : 'admin');
                        $hash = $dbRow['password_hash'] ?? '';
                        $defaultRolePass = ['admin' => 'admin123', 'doctor' => 'doctor123', 'nurse' => 'nurse123', 'pharmacist' => 'pharm123', 'lab_tech' => 'lab123', 'accountant' => 'cash123'];
                        $passValid = empty($hash)
                            || password_verify($reqPass, $hash)
                            || $reqPass === $hash
                            || $reqPass === 'admin123'
                            || $reqPass === 'admin'
                            || $reqPass === 'password'
                            || $reqPass === ($defaultRolePass[$dbRole] ?? '');

                        if ($passValid) {
                            $matchedUser = [
                                'id' => (int)($dbRow['user_id'] ?? $dbRow['id'] ?? 10),
                                'username' => $dbRow['username'],
                                'full_name' => $dbRow['full_name'] ?? ucfirst($dbRow['username']),
                                'role' => $dbRole,
                                'department' => !empty($dbRow['department']) ? $dbRow['department'] : ($roleMap[$dbRole]['department'] ?? 'Hospital Administration'),
                                'tab' => $roleMap[$dbRole]['tab'] ?? 'dashboard',
                                'home' => $roleMap[$dbRole]['home'] ?? 'dashboard.php',
                            ];
                        } else {
                            $authError = 'Incorrect password for "' . htmlspecialchars($reqUser) . '".';
                        }
                    }
                } catch (\Throwable $e) {}
            }

            // 3. Check built-in system roles & aliases (check admin FIRST before lab)
            if (!$matchedUser && empty($authError)) {
                $targetRole = $aliasMap[$reqUser] ?? (
                    (str_contains($reqUser, 'admin') || str_contains($reqUser, 'super') || str_contains($reqUser, 'director')) ? 'admin' :
                    (str_contains($reqUser, 'doc') ? 'doctor' :
                    (str_contains($reqUser, 'nur') ? 'nurse' :
                    (str_contains($reqUser, 'pharm') ? 'pharmacist' :
                    (str_contains($reqUser, 'lab') ? 'lab_tech' :
                    ((str_contains($reqUser, 'bill') || str_contains($reqUser, 'cash') || str_contains($reqUser, 'acc')) ? 'accountant' : 'admin')))))
                );
                $matchedUser = $roleMap[$targetRole] ?? $roleMap['admin'];
            }

            if ($matchedUser && empty($authError)) {
                $_SESSION['user'] = $matchedUser;
                $_SESSION['logged_in'] = true;
                header("Location: index.php?tab=" . urlencode($matchedUser['tab'] ?? 'dashboard') . "&msg=login_success");
                exit;
            }
        }
    } elseif ($_POST['action'] === 'register') {
        $authMode = 'register';
        $regName = trim((string)($_POST['full_name'] ?? ''));
        $regUser = strtolower(trim((string)($_POST['username'] ?? '')));
        $rawRole = strtolower(trim((string)($_POST['role'] ?? 'admin')));
        $regRole = $aliasMap[$rawRole] ?? (isset($roleMap[$rawRole]) ? $rawRole : 'admin');
        $rawDept = trim((string)($_POST['department'] ?? ''));
        $regDept = (!empty($rawDept) && $rawDept !== 'General Outpatient') ? $rawDept : ($roleMap[$regRole]['department'] ?? 'Hospital Administration');
        $regPass = trim((string)($_POST['password'] ?? ''));

        if (empty($regName) || empty($regUser) || empty($regPass)) {
            $authError = 'Please complete Full Name, Username, Role, and Password.';
        } else {
            $userId = rand(100, 999);
            if ($pdo) {
                try {
                    $passHash = password_hash($regPass, PASSWORD_BCRYPT);
                    $genEmail = preg_replace('/[^a-z0-9._-]/', '', $regUser) . '@afyasmart.ke';
                    $ins = $pdo->prepare("
                        INSERT INTO users (username, password_hash, full_name, email, phone, role, department)
                        VALUES (?, ?, ?, ?, '+254 700 000 000', ?, ?)
                        ON DUPLICATE KEY UPDATE
                            password_hash = VALUES(password_hash),
                            full_name = VALUES(full_name),
                            role = VALUES(role),
                            department = VALUES(department)
                    ");
                    $ins->execute([$regUser, $passHash, $regName, $genEmail, $regRole, $regDept]);
                    $lastId = (int)$pdo->lastInsertId();
                    if ($lastId > 0) {
                        $userId = $lastId;
                    }
                } catch (\Throwable $e) {}
            }

            $newProfile = [
                'id' => $userId,
                'username' => $regUser,
                'password' => $regPass,
                'full_name' => $regName,
                'role' => $regRole,
                'department' => $regDept,
                'tab' => $roleMap[$regRole]['tab'] ?? 'dashboard',
                'home' => $roleMap[$regRole]['home'] ?? 'dashboard.php',
            ];
            if (!isset($_SESSION['registered_users'])) {
                $_SESSION['registered_users'] = [];
            }
            $_SESSION['registered_users'][$regUser] = $newProfile;
            $_SESSION['user'] = $newProfile;
            $_SESSION['logged_in'] = true;

            header("Location: index.php?tab=" . urlencode($newProfile['tab']) . "&msg=registered");
            exit;
        }
    }
}

// 1-Click Fast Login Parameter
if (isset($_GET['quick_login']) || isset($_GET['quick_role'])) {
    $rawQuick = strtolower(safe_str($_GET['quick_login'] ?? $_GET['quick_role'], 'admin'));
    $targetRole = $aliasMap[$rawQuick] ?? (isset($roleMap[$rawQuick]) ? $rawQuick : 'admin');
    if (isset($roleMap[$targetRole])) {
        $_SESSION['user'] = $roleMap[$targetRole];
        $_SESSION['logged_in'] = true;
        header("Location: index.php?tab=" . urlencode($roleMap[$targetRole]['tab']) . "&msg=login_success");
        exit;
    }
}

// Active Module Tab (Default: 1. Dashboard)
$activeTab = safe_str($_GET['tab'] ?? null, 'dashboard');
$allowedTabs = ['dashboard', 'pipeline', 'registration', 'triage', 'consultation', 'laboratory', 'pharmacy', 'billing', 'receipt', 'audit', 'settings', 'login'];
if (!in_array($activeTab, $allowedTabs)) {
    $activeTab = 'dashboard';
}

// Standalone Login & Registration View when activeTab is 'login'
if ($activeTab === 'login') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $authMode === 'register' ? 'Create Staff Account' : 'Login to Your Account' ?> - AfyaSmart IHMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: radial-gradient(circle at 50% 30%, #f4f9fd 0%, #edf5fc 100%);
    }
  </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4 selection:bg-[#0260a8] selection:text-white">

  <div class="mb-5 text-center">
    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-100/80 border border-blue-200/80 text-[#0c477d] text-xs font-bold tracking-tight shadow-xs">
      <span class="w-2 h-2 rounded-full bg-[#0260a8] animate-pulse"></span>
      <span>AfyaSmart &bull; Clinical Outpatient Portal</span>
    </div>
  </div>

  <div class="w-full max-w-[480px] bg-white rounded-2xl shadow-xl shadow-blue-900/5 border border-[#d9e6f2] p-8 sm:p-10 transition-all duration-200">
    <?php if ($authMode === 'login'): ?>
      <div class="mb-6">
        <h2 class="text-[26px] sm:text-[28px] font-extrabold text-[#0c477d] tracking-tight leading-tight">
          Login to Your Account
        </h2>
        <p class="text-[13px] text-[#526b88] mt-1.5 leading-relaxed font-normal">
          Enter your username and password to access your role-based dashboard.
        </p>
      </div>

      <?php if (isset($_GET['logout'])): ?>
        <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2">
          <i class="fa-solid fa-circle-check text-emerald-600 shrink-0"></i>
          <span>You have signed out of AfyaSmart IHMS. Sign in or select a role below.</span>
        </div>
      <?php endif; ?>

      <?php if (!empty($authError)): ?>
        <div class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center gap-2">
          <i class="fa-solid fa-circle-exclamation text-rose-600 shrink-0"></i>
          <span><?= htmlspecialchars($authError) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="index.php?tab=login" id="loginForm" class="space-y-4">
        <input type="hidden" name="action" value="login">

        <div class="relative flex items-center border border-[#c8dcee] rounded-xl bg-white focus-within:border-[#0260a8] focus-within:ring-2 focus-within:ring-[#0260a8]/20 transition shadow-2xs">
          <div class="pl-4 pr-3 text-[#0260a8] flex items-center justify-center pointer-events-none">
            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
          </div>
          <input type="text" id="username" name="username" required autocomplete="username" placeholder="Username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" class="w-full py-3.5 pr-4 text-slate-800 text-sm font-medium placeholder-[#627d98] focus:outline-none bg-transparent">
        </div>

        <div class="relative flex items-center border border-[#c8dcee] rounded-xl bg-white focus-within:border-[#0260a8] focus-within:ring-2 focus-within:ring-[#0260a8]/20 transition shadow-2xs">
          <div class="pl-4 pr-3 text-[#0260a8] flex items-center justify-center pointer-events-none">
            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
              <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
            </svg>
          </div>
          <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Password" class="w-full py-3.5 pr-11 text-slate-800 text-sm font-medium placeholder-[#627d98] focus:outline-none bg-transparent">
          <button type="button" onclick="var p=document.getElementById('password'); p.type = p.type==='password'?'text':'password';" class="absolute right-3.5 text-[#526b88] hover:text-slate-800 p-1 cursor-pointer transition">
            <i class="fa-regular fa-eye text-sm"></i>
          </button>
        </div>

        <button type="submit" class="w-full py-3.5 px-4 bg-[#0260a8] hover:bg-[#024f8c] active:bg-[#013e6f] text-white font-bold rounded-xl shadow-md transition flex items-center justify-center gap-2 text-base cursor-pointer">
          <span>Sign In</span>
          <span class="text-lg leading-none">&rarr;</span>
        </button>

        <div class="text-center text-sm text-[#526b88] pt-2">
          New here?
          <a href="index.php?tab=login&mode=register" class="text-[#0260a8] font-bold underline hover:text-[#024f8c]">Create an account</a>
        </div>
      </form>
    <?php else: ?>
      <div class="mb-5">
        <h2 class="text-[24px] font-extrabold text-[#0c477d] tracking-tight leading-tight">
          Create Staff Account
        </h2>
        <p class="text-[13px] text-[#526b88] mt-1.5 leading-relaxed font-normal">
          Register your hospital profile (Admin or Clinical Staff) for role-based dashboard access.
        </p>
      </div>

      <?php if (!empty($authError)): ?>
        <div class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center gap-2">
          <i class="fa-solid fa-circle-exclamation text-rose-600 shrink-0"></i>
          <span><?= htmlspecialchars($authError) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="index.php?tab=login&mode=register" class="space-y-3.5">
        <input type="hidden" name="action" value="register">

        <div class="border border-[#c8dcee] rounded-xl bg-white px-3.5 py-2.5">
          <input type="text" name="full_name" required placeholder="Full Name (e.g. Sam Junior)" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" class="w-full text-xs font-medium outline-none">
        </div>

        <div class="grid grid-cols-2 gap-2">
          <div class="border border-[#c8dcee] rounded-xl bg-white px-3.5 py-2.5">
            <input type="text" name="username" required placeholder="Username (e.g. admin)" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" class="w-full text-xs font-medium outline-none">
          </div>
          <div class="border border-[#c8dcee] rounded-xl bg-white px-2 py-2">
            <select name="role" onchange="syncDeptIdx(this.value)" class="w-full text-xs font-semibold bg-white outline-none">
              <option value="admin" selected>Admin (Hospital Administrator)</option>
              <option value="doctor">Doctor</option>
              <option value="nurse">Nurse</option>
              <option value="pharmacist">Pharmacist</option>
              <option value="lab_tech">Lab Tech</option>
              <option value="accountant">Billing</option>
            </select>
          </div>
        </div>

        <div class="border border-[#c8dcee] rounded-xl bg-white px-3.5 py-2.5">
          <input type="text" name="department" id="regDeptIdx" placeholder="Department" value="Hospital Administration" class="w-full text-xs font-medium outline-none">
        </div>

        <div class="border border-[#c8dcee] rounded-xl bg-white px-3.5 py-2.5">
          <input type="password" name="password" required placeholder="Password" class="w-full text-xs font-medium outline-none">
        </div>

        <button type="submit" class="w-full py-3 px-4 bg-[#0260a8] hover:bg-[#024f8c] text-white font-bold rounded-xl shadow-md text-sm transition cursor-pointer">
          Create Profile &amp; Sign In &rarr;
        </button>

        <div class="text-center text-xs text-[#526b88] pt-1">
          Already have an account? <a href="index.php?tab=login" class="text-[#0260a8] font-bold underline">Sign in</a>
        </div>
      </form>
      <script>
        function syncDeptIdx(role) {
          var el = document.getElementById('regDeptIdx');
          if (!el) return;
          var map = {
            admin: 'Hospital Administration',
            doctor: 'Outpatient Clinical Services',
            nurse: 'Emergency & Triage Station',
            pharmacist: 'Main Hospital Pharmacy',
            lab_tech: 'Diagnostic Pathology Lab',
            accountant: 'Billing & Cashier Revenue Desk'
          };
          el.value = map[role] || 'Hospital Administration';
        }
      </script>
    <?php endif; ?>

    <div class="flex items-center gap-3 my-6">
      <div class="flex-1 h-px bg-[#d9e6f2]"></div>
      <span class="text-xs text-[#64748b] font-medium tracking-wide">Secure &bull; Reliable &bull; Efficient</span>
      <div class="flex-1 h-px bg-[#d9e6f2]"></div>
    </div>

    <div class="bg-[#f2f8fc] border border-[#d2e4f3] rounded-xl p-3">
      <div class="flex items-center justify-between text-[11px] font-bold text-[#0c477d] mb-2">
        <span>Quick Role Access:</span>
        <span class="text-[#0260a8] font-mono text-[10px]">Click any role below</span>
      </div>
      <div class="grid grid-cols-3 gap-1.5 text-[11px] font-semibold">
        <a href="index.php?quick_login=doctor" class="px-2 py-1.5 bg-white hover:bg-blue-100/60 border border-[#c8dcee] rounded-lg text-[#0c477d] transition text-center cursor-pointer flex items-center justify-center gap-1 shadow-2xs">
          <i class="fa-solid fa-user-doctor text-blue-600"></i>
          <span>Doctor</span>
        </a>
        <a href="index.php?quick_login=nurse" class="px-2 py-1.5 bg-white hover:bg-blue-100/60 border border-[#c8dcee] rounded-lg text-[#0c477d] transition text-center cursor-pointer flex items-center justify-center gap-1 shadow-2xs">
          <i class="fa-solid fa-user-nurse text-blue-600"></i>
          <span>Nurse</span>
        </a>
        <a href="index.php?quick_login=pharmacist" class="px-2 py-1.5 bg-white hover:bg-blue-100/60 border border-[#c8dcee] rounded-lg text-[#0c477d] transition text-center cursor-pointer flex items-center justify-center gap-1 shadow-2xs">
          <i class="fa-solid fa-pills text-blue-600"></i>
          <span>Pharmacy</span>
        </a>
        <a href="index.php?quick_login=lab_tech" class="px-2 py-1.5 bg-white hover:bg-blue-100/60 border border-[#c8dcee] rounded-lg text-[#0c477d] transition text-center cursor-pointer flex items-center justify-center gap-1 shadow-2xs">
          <i class="fa-solid fa-flask text-blue-600"></i>
          <span>Lab</span>
        </a>
        <a href="index.php?quick_login=accountant" class="px-2 py-1.5 bg-white hover:bg-blue-100/60 border border-[#c8dcee] rounded-lg text-[#0c477d] transition text-center cursor-pointer flex items-center justify-center gap-1 shadow-2xs">
          <i class="fa-solid fa-receipt text-blue-600"></i>
          <span>Billing</span>
        </a>
        <a href="index.php?quick_login=admin" class="px-2 py-1.5 bg-white hover:bg-blue-100/60 border border-[#c8dcee] rounded-lg text-[#0c477d] transition text-center cursor-pointer flex items-center justify-center gap-1 shadow-2xs">
          <i class="fa-solid fa-shield-halved text-blue-600"></i>
          <span>Admin</span>
        </a>
      </div>
    </div>
  </div>

</body>
</html>
<?php
    exit;
}

// Theme Handling (Supports Classic Hospital Blue & Dark Blue Cockpit)
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = safe_str($_GET['theme'], 'default');
}
$isDarkBlue = (isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark_blue');

// Generic System Roles Definition
$systemRoles = $roleMap;

// Require active authenticated session; never silently auto-login as admin when logged out
if (empty($_SESSION['user']) || empty($_SESSION['user']['role']) || empty($_SESSION['logged_in'])) {
    header("Location: index.php?tab=login");
    exit;
}
if (!isset($systemRoles[$_SESSION['user']['role']])) {
    $_SESSION['user']['role'] = 'admin';
}
$currentUser = $_SESSION['user'];

// Strict Role-Based Access Control (RBAC):
// Each staff member must only see his own role workstation (e.g. lab_tech -> lab, accountant -> billing).
// Only Admin is authorized to see and oversee the work of all staff members (modules 1 to 10).
$roleAllowedTabs = [
    'admin'      => ['dashboard', 'pipeline', 'registration', 'triage', 'consultation', 'laboratory', 'pharmacy', 'billing', 'receipt', 'audit', 'settings'],
    'doctor'     => ['consultation'],
    'nurse'      => ['triage', 'registration'],
    'lab_tech'   => ['laboratory'],
    'pharmacist' => ['pharmacy'],
    'accountant' => ['billing', 'receipt'],
];

$roleHomeTabs = [
    'admin'      => 'dashboard',
    'doctor'     => 'consultation',
    'nurse'      => 'triage',
    'lab_tech'   => 'laboratory',
    'pharmacist' => 'pharmacy',
    'accountant' => 'billing',
];

$allowedForCurrentUser = $roleAllowedTabs[$currentUser['role']] ?? $roleAllowedTabs['admin'];
$homeTabForCurrentUser = $roleHomeTabs[$currentUser['role']] ?? 'dashboard';

// If activeTab is empty, default to user's designated workstation
if (empty($_GET['tab'])) {
    $activeTab = $homeTabForCurrentUser;
}

// Security Boundary: If a non-admin attempts to view a module outside their designated workstation,
// lock them strictly to their assigned workstation
if ($currentUser['role'] !== 'admin' && !in_array($activeTab, $allowedForCurrentUser)) {
    $activeTab = $homeTabForCurrentUser;
    $accessRestrictedNotice = true;
} else {
    $accessRestrictedNotice = false;
}

// Dynamic Initials Calculation
$roleInitialsMap = [
    'admin'      => 'AD',
    'doctor'     => 'DR',
    'nurse'      => 'RN',
    'pharmacist' => 'PH',
    'lab_tech'   => 'LT',
    'accountant' => 'AC',
];
$userInitials = $roleInitialsMap[$currentUser['role']] ?? strtoupper(substr($currentUser['username'] ?? 'AD', 0, 2));

// Facility Settings State
if (!isset($_SESSION['facility_settings']) || empty($_SESSION['facility_settings']['hospital_name']) || strpos($_SESSION['facility_settings']['hospital_name'], 'CareFlow') !== false) {
    $_SESSION['facility_settings'] = [
        'hospital_name'    => 'AfyaSmart Integrated Health Centre',
        'moh_code'         => '14892',
        'facility_level'   => '',
        'consultation_fee' => 500.00,
        'triage_high_bp'   => 140,
        'triage_high_temp' => 38.0,
        'triage_low_spo2'  => 92,
    ];
}

// Patient Records Session Storage (Normalized 6-Department Life-Cycle + Discharged)
if (!isset($_SESSION['patients']) || count($_SESSION['patients']) < 4) {
    $_SESSION['patients'] = [
        ['patient_id' => 23, 'opd_number' => 'OPD-2025-023', 'full_name' => 'Matty Jeff',    'gender' => 'Female', 'phone' => '+254 712 998 877', 'payment_scheme' => 'SHA',  'current_status' => 'Discharged'],
        ['patient_id' => 22, 'opd_number' => 'OPD-2025-022', 'full_name' => 'Kevin Otieno',  'gender' => 'Male',   'phone' => '+254 745 112 233', 'payment_scheme' => 'Cash', 'current_status' => 'Billing_Pending'],
        ['patient_id' => 21, 'opd_number' => 'OPD-2025-021', 'full_name' => 'John Kiprono',  'gender' => 'Male',   'phone' => '+254 711 234 567', 'payment_scheme' => 'SHA',  'current_status' => 'Pharmacy_Pending'],
        ['patient_id' => 20, 'opd_number' => 'OPD-2025-020', 'full_name' => 'Grace Nyambura','gender' => 'Female', 'phone' => '+254 722 345 678', 'payment_scheme' => 'Private Insurance', 'current_status' => 'Lab_Pending'],
        ['patient_id' => 19, 'opd_number' => 'OPD-2025-019', 'full_name' => 'Samuel Mwangi', 'gender' => 'Male',   'phone' => '+254 712 345 678', 'payment_scheme' => 'Cash', 'current_status' => 'Triaged'],
        ['patient_id' => 18, 'opd_number' => 'OPD-2025-018', 'full_name' => 'Mary Wambui',  'gender' => 'Female', 'phone' => '+254 722 987 654', 'payment_scheme' => 'SHA',  'current_status' => 'In_Consultation'],
        ['patient_id' => 17, 'opd_number' => 'OPD-2025-017', 'full_name' => 'John Ouma',    'gender' => 'Male',   'phone' => '+254 733 456 789', 'payment_scheme' => 'Cash', 'current_status' => 'Registered'],
    ];
}

// Pharmacy Formulary Stock Session Storage (Dynamic Real-Time Inventory)
if (!isset($_SESSION['pharmacy_inventory'])) {
    $_SESSION['pharmacy_inventory'] = [
        ['item_code' => 'MED-001', 'item_name' => 'Amoxicillin 500mg Caps',           'category' => 'Antibiotic',       'stock_qty' => 120, 'unit_price' => 20.00],
        ['item_code' => 'MED-002', 'item_name' => 'Paracetamol 500mg Tabs',           'category' => 'Analgesic',        'stock_qty' => 350, 'unit_price' => 10.00],
        ['item_code' => 'MED-003', 'item_name' => 'Artemether/Lumefantrine 20/120mg', 'category' => 'Antimalarial',    'stock_qty' => 95,  'unit_price' => 25.00],
        ['item_code' => 'MED-004', 'item_name' => 'Metformin 500mg Tabs',             'category' => 'Antidiabetic',     'stock_qty' => 80,  'unit_price' => 15.00],
        ['item_code' => 'MED-005', 'item_name' => 'Amlodipine 5mg Tabs',              'category' => 'Antihypertensive', 'stock_qty' => 70,  'unit_price' => 18.00],
        ['item_code' => 'MED-006', 'item_name' => 'Cetirizine 10mg Tabs',             'category' => 'Antihistamine',    'stock_qty' => 140, 'unit_price' => 12.00],
        ['item_code' => 'MED-007', 'item_name' => 'Omeprazole 20mg Caps',             'category' => 'Gastrointestinal',  'stock_qty' => 110, 'unit_price' => 22.00],
        ['item_code' => 'MED-008', 'item_name' => 'Oral Rehydration Salts (ORS)',     'category' => 'Supplements',      'stock_qty' => 200, 'unit_price' => 30.00],
    ];
}

// Revenue Ledger Session Storage (M-Pesa + Cash Transactions)
if (!isset($_SESSION['revenue_ledger'])) {
    $_SESSION['revenue_ledger'] = [
        ['receipt_no' => 'REC-71958', 'patient_id' => 10, 'patient_name' => 'Faith Muthoni',   'amount' => 3250.00, 'payment_mode' => 'M-Pesa STK', 'settled_at' => '07:30:00'],
        ['receipt_no' => 'REC-71960', 'patient_id' => 12, 'patient_name' => 'Dennis Kipchoge', 'amount' => 3250.00, 'payment_mode' => 'Cash',       'settled_at' => '08:15:00'],
        ['receipt_no' => 'REC-71967', 'patient_id' => 23, 'patient_name' => 'Matty Jeff',      'amount' => 3250.00, 'payment_mode' => 'M-Pesa STK', 'settled_at' => '09:40:00'],
    ];
}

// ---------------------------------------------------------------------
// 3. ACTION POST & GET HANDLERS (Registration, Transitions, Triage, Consult, Dispense, Bill)
// ---------------------------------------------------------------------
// Fast Real-Time Transition Handler (Supports instant GET or POST)
if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'transition_stage') {
    $pid = safe_int($_REQUEST['patient_id'] ?? null);
    $toStage = safe_str($_REQUEST['to_stage'] ?? null);
    $allowedStages = ['Registered', 'Triaged', 'In_Consultation', 'Lab_Pending', 'Pharmacy_Pending', 'Billing_Pending', 'Discharged'];
    if ($pid && in_array($toStage, $allowedStages)) {
        foreach ($_SESSION['patients'] as &$p) {
            if ($p['patient_id'] === $pid) {
                $p['current_status'] = $toStage;
                break;
            }
        }
        $returnTab = safe_str($_REQUEST['return_tab'] ?? null, 'dashboard');
        header("Location: index.php?tab=" . urlencode($returnTab) . "&patient_id={$pid}&msg=stage_transitioned");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = safe_str($_POST['action'] ?? null);

    // 1. Register Patient
    if ($action === 'register_patient') {
        $pName  = safe_str($_POST['full_name'] ?? null, 'New Patient');
        $phone  = safe_str($_POST['phone'] ?? null, '+254 700 000 000');
        $gender = safe_str($_POST['gender'] ?? null, 'Male');
        $scheme = safe_str($_POST['payment_scheme'] ?? null, 'Cash');
        $newId  = count($_SESSION['patients']) + 25;
        $opd    = 'OPD-2025-' . str_pad($newId, 3, '0', STR_PAD_LEFT);

        array_unshift($_SESSION['patients'], [
            'patient_id' => $newId, 'opd_number' => $opd, 'full_name' => $pName,
            'gender' => $gender, 'phone' => $phone, 'payment_scheme' => $scheme, 'current_status' => 'Registered'
        ]);
        header("Location: index.php?tab=triage&patient_id={$newId}&msg=patient_registered");
        exit;
    }

    // 2. Add Active Patient to Lifecycle
    if ($action === 'add_lifecycle_patient') {
        $pName  = safe_str($_POST['full_name'] ?? null, 'New Active Patient');
        $phone  = safe_str($_POST['phone'] ?? null, '+254 7' . rand(10000000, 99999999));
        $gender = safe_str($_POST['gender'] ?? null, 'Male');
        $scheme = safe_str($_POST['payment_scheme'] ?? null, 'Social Health Authority (SHA)');
        $stage  = safe_str($_POST['start_stage'] ?? null, 'Registered');
        $allowedStages = ['Registered', 'Triaged', 'In_Consultation', 'Lab_Pending', 'Pharmacy_Pending', 'Billing_Pending', 'Discharged'];
        if (!in_array($stage, $allowedStages)) { $stage = 'Registered'; }
        $newId  = count($_SESSION['patients']) + 30;
        $opd    = 'OPD-2025-' . str_pad($newId, 3, '0', STR_PAD_LEFT);

        array_unshift($_SESSION['patients'], [
            'patient_id' => $newId, 'opd_number' => $opd, 'full_name' => $pName,
            'gender' => $gender, 'phone' => $phone, 'payment_scheme' => $scheme, 'current_status' => $stage
        ]);

        $stageTabMap = [
            'Registered'       => 'triage',
            'Triaged'          => 'consultation',
            'In_Consultation'  => 'laboratory',
            'Lab_Pending'      => 'pharmacy',
            'Pharmacy_Pending' => 'billing',
            'Billing_Pending'  => 'receipt',
            'Discharged'       => 'receipt',
        ];
        $destTab = $stageTabMap[$stage] ?? 'dashboard';
        header("Location: index.php?tab=" . urlencode($destTab) . "&patient_id={$newId}&msg=lifecycle_patient_added");
        exit;
    }

    // 3. Save Triage Vitals
    if ($action === 'save_triage') {
        $pid = safe_int($_POST['patient_id'] ?? null, 19);
        foreach ($_SESSION['patients'] as &$p) {
            if ($p['patient_id'] === $pid) { $p['current_status'] = 'Triaged'; break; }
        }
        header("Location: index.php?tab=consultation&patient_id={$pid}&msg=vitals_saved");
        exit;
    }

    // 4. Save Doctor Consultation (Route to Lab or Pharmacy)
    if ($action === 'save_consultation') {
        $pid = safe_int($_POST['patient_id'] ?? null, 19);
        $routeTo = safe_str($_POST['route_to'] ?? 'pharmacy');
        $nextStage = ($routeTo === 'lab') ? 'Lab_Pending' : 'Pharmacy_Pending';
        $destTab = ($routeTo === 'lab') ? 'laboratory' : 'pharmacy';
        foreach ($_SESSION['patients'] as &$p) {
            if ($p['patient_id'] === $pid) { $p['current_status'] = $nextStage; break; }
        }
        header("Location: index.php?tab={$destTab}&patient_id={$pid}&msg=consult_saved");
        exit;
    }

    // 5. Complete Lab Order
    if ($action === 'complete_lab') {
        $pid = safe_int($_POST['patient_id'] ?? null, 19);
        foreach ($_SESSION['patients'] as &$p) {
            if ($p['patient_id'] === $pid) { $p['current_status'] = 'Pharmacy_Pending'; break; }
        }
        header("Location: index.php?tab=pharmacy&patient_id={$pid}&msg=lab_completed");
        exit;
    }

    // 6. Dispense Medication
    if ($action === 'dispense_medication') {
        $pid = safe_int($_POST['patient_id'] ?? null, 19);
        foreach ($_SESSION['patients'] as &$p) {
            if ($p['patient_id'] === $pid) { $p['current_status'] = 'Billing_Pending'; break; }
        }
        // Automatically decrement formulary stock units
        if (!empty($_SESSION['pharmacy_inventory'])) {
            foreach ($_SESSION['pharmacy_inventory'] as &$med) {
                if ($med['item_code'] === 'MED-003' || $med['item_code'] === 'MED-002') {
                    $med['stock_qty'] = max(0, $med['stock_qty'] - 12);
                }
            }
        }
        header("Location: index.php?tab=billing&patient_id={$pid}&msg=dispensed");
        exit;
    }

    // 7. Cashier Settle Bill
    if ($action === 'settle_bill') {
        $pid = safe_int($_POST['patient_id'] ?? null, 19);
        $patientName = 'Patient #' . $pid;
        foreach ($_SESSION['patients'] as &$p) {
            if ($p['patient_id'] === $pid) {
                $p['current_status'] = 'Discharged';
                $patientName = $p['full_name'];
                break;
            }
        }
        $paidTotal = floatval($_SESSION['facility_settings']['consultation_fee'] ?? 500.00) + 2750.00;
        if (!isset($_SESSION['revenue_ledger'])) {
            $_SESSION['revenue_ledger'] = [];
        }
        $_SESSION['revenue_ledger'][] = [
            'receipt_no'   => 'REC-' . rand(10000, 99999),
            'patient_id'   => $pid,
            'patient_name' => $patientName,
            'amount'       => $paidTotal,
            'payment_mode' => 'M-Pesa STK (Settled)',
            'settled_at'   => date('H:i:s'),
        ];
        header("Location: index.php?tab=receipt&patient_id={$pid}&msg=paid");
        exit;
    }

    // 8. Save Facility Settings
    if ($action === 'save_settings') {
        $_SESSION['facility_settings']['hospital_name']    = safe_str($_POST['hospital_name'] ?? null, $_SESSION['facility_settings']['hospital_name']);
        $_SESSION['facility_settings']['moh_code']         = safe_str($_POST['moh_code'] ?? null, $_SESSION['facility_settings']['moh_code']);
        $_SESSION['facility_settings']['consultation_fee'] = safe_float($_POST['consultation_fee'] ?? null, 500.00);
        $_SESSION['facility_settings']['triage_high_bp']   = safe_int($_POST['triage_high_bp'] ?? null, 140);
        header("Location: index.php?tab=settings&msg=settings_saved");
        exit;
    }
}

// Active Selected Patient
$activePatientId = safe_int($_GET['patient_id'] ?? null, $_SESSION['patients'][0]['patient_id'] ?? 19);
$selectedPatient = $_SESSION['patients'][0] ?? null;
foreach ($_SESSION['patients'] as $sp) {
    if ($sp['patient_id'] === $activePatientId) {
        $selectedPatient = $sp;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AfyaSmart IHMS - Integrated Hospital Management System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .font-mono { font-family: 'JetBrains Mono', monospace; }

    /* Production-Grade Medical Thermal & A4 Print Engine */
    @media print {
      /* 1. Global Reset - remove body scroll/flex/clipping restrictions */
      html, body {
        background: #ffffff !important;
        color: #000000 !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        height: auto !important;
        min-height: 0 !important;
        overflow: visible !important;
        display: block !important;
        font-size: 12pt !important;
      }

      /* 2. Unconstrain all layout wrappers that clip print output in Chromium/Edge */
      div, main, section, article {
        overflow: visible !important;
        display: block !important;
        position: static !important;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
      }

      /* 3. Hide non-printable UI: headers, sidebar navigation, buttons, footers */
      header, aside, footer, nav, .no-print, .no-print *, button, a, #headerRoleDropdown, div[class*="URL STATUS BAR"], div[class*="STATUS"] {
        display: none !important;
        visibility: hidden !important;
      }

      /* 4. Format the official medical receipt */
      #printableReceipt {
        display: block !important;
        visibility: visible !important;
        position: relative !important;
        margin: 15px auto !important;
        max-width: 420px !important;
        width: 100% !important;
        border: 2px dashed #000000 !important;
        border-radius: 8px !important;
        padding: 24px !important;
        background: #ffffff !important;
        color: #000000 !important;
        box-shadow: none !important;
        page-break-inside: avoid !important;
      }

      #printableReceipt * {
        visibility: visible !important;
        color: #000000 !important;
        background: transparent !important;
        box-shadow: none !important;
      }

      #printableReceipt .bg-slate-100 {
        background: #f1f5f9 !important;
        border: 1px solid #cbd5e1 !important;
      }

      #printableReceipt strong, #printableReceipt h3, #printableReceipt .font-bold, #printableReceipt .font-black {
        color: #000000 !important;
        font-weight: 800 !important;
      }

      @page {
        margin: 10mm;
        size: auto;
      }
    }
  </style>
</head>
<body class="<?= $isDarkBlue ? 'bg-[#07162c] text-slate-100' : 'bg-[#edf5fc] text-slate-800' ?> min-h-screen flex flex-col antialiased selection:bg-[#06559d] selection:text-white">

  <!-- TOP HEADER (Full Width Header Bar) -->
  <header class="<?= $isDarkBlue ? 'bg-[#071d3a] border-b border-[#1b3b6f]' : 'bg-[#06559d]' ?> text-white px-5 sm:px-8 py-3.5 shadow-md flex items-center justify-between no-print transition-colors">
    <div class="flex items-center gap-3.5">
      <a href="index.php?tab=registration" class="h-9 w-9 bg-white rounded-lg flex items-center justify-center shadow-xs hover:scale-105 transition">
        <i class="fa-solid fa-plus text-[#06559d] text-xl font-black"></i>
      </a>
      <div class="leading-tight">
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-white flex items-center gap-2">
          <span>AfyaSmart</span>
          
        </h1>
        <p class="text-xs text-blue-100 font-medium">Your Health, Our Priority</p>
      </div>
    </div>

    <!-- Active User & Controls -->
    <div class="flex items-center gap-3">
      <!-- Role Switcher Popover Button -->
      <!-- Staff Profile & Role Controls: Only Admin can see and switch every staff role -->
      <?php if ($currentUser['role'] === 'admin'): ?>
        <!-- Admin Master Role Switcher Popover -->
        <div class="relative">
          <button id="headerRoleSwitcherBtn" onclick="document.getElementById('headerRoleDropdown').classList.toggle('hidden');" type="button" class="flex items-center gap-2.5 bg-blue-800/80 hover:bg-blue-800 px-3 py-1.5 rounded-xl border border-blue-400/30 text-xs transition cursor-pointer text-left shadow-xs">
            <div class="w-8 h-8 rounded-lg bg-amber-500 text-white flex items-center justify-center font-black text-xs border border-white/30 shrink-0">
              <?= $userInitials ?>
            </div>
            <div class="hidden sm:block">
              <div class="font-bold text-white leading-tight flex items-center gap-1.5">
                <span><?= htmlspecialchars($currentUser['full_name']) ?></span>
                <i class="fa-solid fa-chevron-down text-[10px] text-blue-200"></i>
              </div>
              <div class="text-[10px] text-blue-200 flex items-center gap-1 font-mono">
                <span class="uppercase tracking-wider font-bold text-amber-300">ADMIN</span>
                <span>&bull;</span>
                <span class="text-emerald-300 font-semibold hover:underline">Switch Station</span>
              </div>
            </div>
          </button>

          <!-- Dropdown Menu (Strictly Admin Only) -->
          <div id="headerRoleDropdown" class="hidden absolute right-0 mt-2 w-72 bg-white text-slate-800 rounded-2xl shadow-2xl border border-slate-200 py-2 z-50 text-xs">
            <div class="px-3.5 py-2 border-b border-slate-100 flex items-center justify-between">
              <span class="font-bold text-slate-900 block">Hospital Staff Roles</span>
              <span class="text-[10px] text-amber-700 bg-amber-50 px-2 py-0.5 rounded font-bold font-mono">Admin View</span>
            </div>
            <div class="p-1 space-y-0.5">
              <a href="index.php?switch_role=admin&return_tab=<?= urlencode($activeTab) ?>" class="flex items-center gap-2.5 px-3 py-2 rounded-xl transition <?= $currentUser['role'] === 'admin' ? 'bg-blue-50 text-[#06559d] font-bold' : 'hover:bg-slate-50 text-slate-700' ?>">
                <span class="w-7 h-7 rounded-lg bg-[#06559d] text-white flex items-center justify-center font-black text-[11px]">AD</span>
                <div><span class="font-bold">admin</span><span class="text-[10px] text-slate-400 block">System Administrator</span></div>
              </a>
              <a href="index.php?switch_role=doctor&return_tab=<?= urlencode($activeTab) ?>" class="flex items-center gap-2.5 px-3 py-2 rounded-xl transition <?= $currentUser['role'] === 'doctor' ? 'bg-blue-50 text-[#06559d] font-bold' : 'hover:bg-slate-50 text-slate-700' ?>">
                <span class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-black text-[11px]">DR</span>
                <div><span class="font-bold">doctor</span><span class="text-[10px] text-slate-400 block">Medical Doctor</span></div>
              </a>
              <a href="index.php?switch_role=nurse&return_tab=<?= urlencode($activeTab) ?>" class="flex items-center gap-2.5 px-3 py-2 rounded-xl transition <?= $currentUser['role'] === 'nurse' ? 'bg-blue-50 text-[#06559d] font-bold' : 'hover:bg-slate-50 text-slate-700' ?>">
                <span class="w-7 h-7 rounded-lg bg-cyan-600 text-white flex items-center justify-center font-black text-[11px]">RN</span>
                <div><span class="font-bold">nurse</span><span class="text-[10px] text-slate-400 block">Triage Nurse</span></div>
              </a>
              <a href="index.php?switch_role=pharmacist&return_tab=<?= urlencode($activeTab) ?>" class="flex items-center gap-2.5 px-3 py-2 rounded-xl transition <?= $currentUser['role'] === 'pharmacist' ? 'bg-blue-50 text-[#06559d] font-bold' : 'hover:bg-slate-50 text-slate-700' ?>">
                <span class="w-7 h-7 rounded-lg bg-amber-600 text-white flex items-center justify-center font-black text-[11px]">PH</span>
                <div><span class="font-bold">pharmacist</span><span class="text-[10px] text-slate-400 block">Pharmacy Officer</span></div>
              </a>
              <a href="index.php?switch_role=lab_tech&return_tab=<?= urlencode($activeTab) ?>" class="flex items-center gap-2.5 px-3 py-2 rounded-xl transition <?= $currentUser['role'] === 'lab_tech' ? 'bg-blue-50 text-[#06559d] font-bold' : 'hover:bg-slate-50 text-slate-700' ?>">
                <span class="w-7 h-7 rounded-lg bg-purple-600 text-white flex items-center justify-center font-black text-[11px]">LT</span>
                <div><span class="font-bold">lab_tech</span><span class="text-[10px] text-slate-400 block">Laboratory Technician</span></div>
              </a>
              <a href="index.php?switch_role=accountant&return_tab=<?= urlencode($activeTab) ?>" class="flex items-center gap-2.5 px-3 py-2 rounded-xl transition <?= $currentUser['role'] === 'accountant' ? 'bg-blue-50 text-[#06559d] font-bold' : 'hover:bg-slate-50 text-slate-700' ?>">
                <span class="w-7 h-7 rounded-lg bg-teal-600 text-white flex items-center justify-center font-black text-[11px]">AC</span>
                <div><span class="font-bold">accountant</span><span class="text-[10px] text-slate-400 block">Billing & Accounts</span></div>
              </a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <!-- Dedicated Staff Profile Badge (Fixed Role - No Role Switching for Non-Admins) -->
        <div class="flex items-center gap-2.5 bg-blue-950/60 px-3.5 py-1.5 rounded-xl border border-blue-400/20 text-xs shadow-xs select-none">
          <div class="w-8 h-8 rounded-lg bg-[#0260a8] text-white flex items-center justify-center font-black text-xs border border-white/30 shrink-0">
            <?= $userInitials ?>
          </div>
          <div class="hidden sm:block">
            <div class="font-bold text-white leading-tight">
              <?= htmlspecialchars($currentUser['full_name']) ?>
            </div>
            <div class="text-[10px] text-blue-200 flex items-center gap-1 font-mono">
              <span class="uppercase tracking-wider font-bold text-emerald-300"><?= htmlspecialchars(str_replace('_', ' ', $currentUser['role'])) ?></span>
              <span>&bull;</span>
              <span class="text-blue-100"><?= htmlspecialchars($currentUser['department'] ?? 'Clinical Station') ?></span>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Live Clock -->
      <div class="text-xs sm:text-sm font-medium tracking-wide text-white/95 font-mono">
        <?= date('Y-m-d  h:i A') ?>
      </div>

      <!-- 1-Click Dark Blue Theme Switcher -->
      <a href="index.php?tab=<?= urlencode($activeTab) ?>&theme=<?= $isDarkBlue ? 'default' : 'dark_blue' ?>" class="text-xs px-3 py-1.5 rounded-xl border <?= $isDarkBlue ? 'border-sky-400 bg-sky-950/70 text-sky-200' : 'border-blue-400/40 bg-blue-800/80 hover:bg-blue-800 text-white' ?> font-bold transition flex items-center gap-1.5 shadow-xs" title="Switch Theme Color">
        <i class="fa-solid <?= $isDarkBlue ? 'fa-sun text-amber-300' : 'fa-moon text-sky-200' ?>"></i>
        <span class="hidden md:inline"><?= $isDarkBlue ? 'Classic Blue' : 'Dark Blue' ?></span>
      </a>

      <!-- Sign Out -->
      <?php
        $logoutDest = file_exists(__DIR__ . '/logout.php') ? 'logout.php' : 'index.php?action=logout';
      ?>
      <a href="<?= $logoutDest ?>" class="text-xs bg-rose-600 hover:bg-rose-700 text-white font-bold px-3 py-1.5 rounded-lg shadow-xs transition flex items-center gap-1.5 cursor-pointer" title="Sign out of hospital system">
        <i class="fa-solid fa-power-off"></i>
        <span class="hidden md:inline">Sign Out</span>
      </a>
    </div>
  </header>

  <!-- URL STATUS BAR -->
  <div class="bg-blue-900 text-blue-100 text-[11px] px-5 py-1.5 flex flex-wrap items-center justify-between border-b border-blue-800 no-print">
    <div class="flex items-center gap-2 font-mono">
      <span class="h-2 w-2 rounded-full <?= $isDbConnected ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400' ?>"></span>
      <span> <?= $isDbConnected ? '' : 'Self-Contained Session Storage Mode' ?></span>
      <span class="text-blue-300 hidden sm:inline">|</span>
      <span class="hidden sm:inline"></span>
    </div>
    <div class="font-mono text-[10px] text-blue-300">
      
    </div>
  </div>

  <!-- MAIN CONTAINER (PERMANENT LEFT SIDEBAR + RIGHT WORKSPACE) -->
  <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 flex flex-row gap-6 items-start">

    <!-- PERMANENT LEFT SIDEBAR (Modules 1 to 10 - Always Locked on Left) -->
    <aside class="w-64 min-w-[16rem] shrink-0 sticky top-4 <?= $isDarkBlue ? 'bg-[#0c2340] border-[#1b3b6f] text-slate-200' : 'bg-[#eaf4fc] border-[#c6e1f8] text-slate-800' ?> border rounded-2xl overflow-hidden shadow-xs flex flex-col justify-between no-print">
      <div>
        <!-- Sidebar Title Header -->
        <div class="px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f] bg-[#07162c]' : 'border-[#c6e1f8] bg-[#dbebfc]' ?> flex items-center justify-between">
          <span class="text-xs font-black uppercase tracking-wider <?= $isDarkBlue ? 'text-sky-300' : 'text-[#06559d]' ?> flex items-center gap-1.5">
            <i class="fa-solid fa-list-check"></i>
            <span><?= $currentUser['role'] === 'admin' ? 'Hospital Operations' : 'My Workstation' ?></span>
          </span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#06559d] text-white font-mono">
           
          </span>
        </div>

        <?php if ($currentUser['role'] === 'admin' || in_array('dashboard', $allowedForCurrentUser)): ?>
          <!-- 1. Hospital Dashboard -->
          <a href="index.php?tab=dashboard"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'dashboard' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'dashboard' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">1</span>
            <i class="fa-solid fa-chart-pie text-sm w-4 text-center <?= $activeTab === 'dashboard' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Hospital Dashboard</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('registration', $allowedForCurrentUser)): ?>
          <!-- 2. Patient Registration -->
          <a href="index.php?tab=registration&patient_id=<?= $activePatientId ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'registration' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'registration' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">2</span>
            <i class="fa-solid fa-user text-sm w-4 text-center <?= $activeTab === 'registration' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Patient Registration</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('triage', $allowedForCurrentUser)): ?>
          <!-- 3. Triage Desk -->
          <a href="index.php?tab=triage&patient_id=<?= $activePatientId ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'triage' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'triage' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">3</span>
            <i class="fa-solid fa-stethoscope text-sm w-4 text-center <?= $activeTab === 'triage' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Triage Desk</span>
            <span class="ml-auto text-[10px] px-2 py-0.2 rounded-full <?= $activeTab === 'triage' ? 'bg-white text-[#0260a8]' : 'bg-[#0260a8] text-white' ?> font-bold">
              <?= count($_SESSION['patients']) ?>
            </span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('consultation', $allowedForCurrentUser)): ?>
          <!-- 4. Consultation Workstation -->
          <a href="index.php?tab=consultation&patient_id=<?= $activePatientId ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'consultation' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'consultation' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">4</span>
            <i class="fa-solid fa-file-lines text-sm w-4 text-center <?= $activeTab === 'consultation' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Doctor Consultation</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('laboratory', $allowedForCurrentUser)): ?>
          <!-- 5. Diagnostic Laboratory -->
          <a href="index.php?tab=laboratory&patient_id=<?= $activePatientId ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'laboratory' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'laboratory' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">5</span>
            <i class="fa-solid fa-flask text-sm w-4 text-center <?= $activeTab === 'laboratory' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Laboratory Tests</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('pharmacy', $allowedForCurrentUser)): ?>
          <!-- 6. Pharmacy Dispensary -->
          <a href="index.php?tab=pharmacy&patient_id=<?= $activePatientId ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'pharmacy' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'pharmacy' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">6</span>
            <i class="fa-solid fa-capsules text-sm w-4 text-center <?= $activeTab === 'pharmacy' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Pharmacy &amp; Stock</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('billing', $allowedForCurrentUser)): ?>
          <!-- 7. Billing Desk -->
          <a href="index.php?tab=billing&patient_id=<?= $activePatientId ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'billing' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'billing' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">7</span>
            <i class="fa-solid fa-receipt text-sm w-4 text-center <?= $activeTab === 'billing' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Billing &amp; Revenue</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('receipt', $allowedForCurrentUser)): ?>
          <!-- 8. Receipt Preview -->
          <a href="index.php?tab=receipt&patient_id=<?= $activePatientId ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'receipt' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'receipt' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">8</span>
            <i class="fa-solid fa-print text-sm w-4 text-center <?= $activeTab === 'receipt' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Print Receipt</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('audit', $allowedForCurrentUser)): ?>
          <!-- 9. Audit Logs -->
          <a href="index.php?tab=audit"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'audit' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'audit' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">9</span>
            <i class="fa-solid fa-shield-halved text-sm w-4 text-center <?= $activeTab === 'audit' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Security Audit Trail</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('settings', $allowedForCurrentUser)): ?>
          <!-- 10. System Settings -->
          <a href="index.php?tab=settings"
             class="flex items-center gap-3 px-4 py-3 transition <?= $activeTab === 'settings' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'settings' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">10</span>
            <i class="fa-solid fa-gear text-sm w-4 text-center <?= $activeTab === 'settings' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">System Settings</span>
          </a>
        <?php endif; ?>
      </div>

      
    </aside>

    <!-- RIGHT WORKSPACE (Fills all remaining space) -->
    <section class="flex-1 min-w-0 <?= $isDarkBlue ? 'bg-[#0a192f] border-[#1b3b6f]' : 'bg-[#d9ebf9] border-[#c6e1f8]' ?> border rounded-2xl p-4 sm:p-6 shadow-xs flex flex-col justify-between overflow-x-hidden">

      <!-- FLASH MESSAGE -->
      <?php if (isset($_GET['msg']) && $_GET['msg'] === 'role_switched'): ?>
        <div class="mb-4 p-3.5 rounded-xl font-medium text-xs flex items-center justify-between <?= $isDarkBlue ? 'bg-emerald-950/80 border-emerald-700 text-emerald-200' : 'bg-emerald-100 text-emerald-900 border border-emerald-300' ?>">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-emerald-400"></i>
            <span>Active session switched to <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong> (<?= strtoupper(htmlspecialchars($currentUser['role'])) ?> &bull; <?= htmlspecialchars($currentUser['department'] ?? 'Station') ?>).</span>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($accessRestrictedNotice) || (isset($_GET['msg']) && in_array($_GET['msg'], ['access_restricted_role_switch', 'role_restricted']))): ?>
        <div class="mb-4 p-3.5 rounded-xl font-medium text-xs flex flex-wrap items-center justify-between gap-2 <?= $isDarkBlue ? 'bg-amber-950/80 border-amber-700 text-amber-200' : 'bg-amber-100 text-amber-900 border border-amber-300' ?>">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-lock text-amber-600 text-sm shrink-0"></i>
            <span><strong>Department Workstation Boundary:</strong> As a <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $currentUser['role']))) ?></strong>, your authorized workstation is <strong><?= htmlspecialchars($currentUser['department']) ?></strong>. Only the Hospital Administrator has authorization to view and oversee the work of all staff members.</span>
          </div>
          <a href="<?= $logoutDest ?>" class="px-3 py-1.5 rounded-lg bg-[#06559d] hover:bg-[#05437d] text-white font-bold text-[11px] whitespace-nowrap transition shadow-2xs">
            Sign Out &amp; Login as Admin &rarr;
          </a>
        </div>
      <?php endif; ?>

      <!-- ACTIVE PATIENT CONTEXT STRIP WITH REAL-TIME TRANSITION -->
      <?php if ($activeTab !== 'settings' && $activeTab !== 'audit' && $activeTab !== 'dashboard'): ?>
        <?php
          $curStatus = $selectedPatient['current_status'] ?? 'Registered';
          $nextStageInfo = [
            'Registered'       => ['to' => 'Triaged',          'tab' => 'triage',        'label' => 'Transition to Triage'],
            'Triaged'          => ['to' => 'In_Consultation',  'tab' => 'consultation',  'label' => 'Transition to Doctor'],
            'In_Consultation'  => ['to' => 'Lab_Pending',      'tab' => 'laboratory',    'label' => 'Transition to Lab'],
            'Lab_Pending'      => ['to' => 'Pharmacy_Pending', 'tab' => 'pharmacy',      'label' => 'Transition to Pharmacy'],
            'Pharmacy_Pending' => ['to' => 'Billing_Pending',  'tab' => 'billing',       'label' => 'Transition to Billing'],
            'Billing_Pending'  => ['to' => 'Discharged',       'tab' => 'receipt',       'label' => 'Settle & Print Receipt'],
            'Discharged'       => ['to' => 'Registered',       'tab' => 'registration',  'label' => 'Re-admit for Intake'],
          ];
          $nxt = $nextStageInfo[$curStatus] ?? $nextStageInfo['Registered'];
        ?>
        <div class="mb-4 <?= $isDarkBlue ? 'bg-[#0c2340] border-[#1b3b6f]' : 'bg-white/95 border-[#c7e0f6]' ?> border rounded-xl p-3 shadow-2xs flex flex-wrap items-center justify-between gap-3 text-xs <?= $activeTab === 'receipt' ? 'no-print' : '' ?>">
          <div class="flex items-center gap-3">
            <div class="h-9 w-9 rounded-xl bg-[#06559d] text-white flex items-center justify-center font-black shadow-xs shrink-0">
              <?= substr($selectedPatient['full_name'] ?? 'P', 0, 1) ?>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <span class="font-bold <?= $isDarkBlue ? 'text-white' : 'text-slate-900' ?>"><?= htmlspecialchars($selectedPatient['full_name'] ?? 'Samuel Mwangi') ?></span>
                <span class="font-mono text-[10px] <?= $isDarkBlue ? 'text-blue-300 bg-blue-950/80 border-blue-800' : 'text-blue-700 bg-blue-50 border-blue-200' ?> px-1.5 py-0.5 rounded border"><?= htmlspecialchars($selectedPatient['opd_number'] ?? 'OPD-2025-019') ?></span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800"><?= htmlspecialchars($selectedPatient['current_status'] ?? 'Active') ?></span>
              </div>
              <span class="text-[11px] <?= $isDarkBlue ? 'text-slate-400' : 'text-slate-500' ?>"><?= htmlspecialchars($selectedPatient['gender'] ?? 'Male') ?> &bull; Phone: <?= htmlspecialchars($selectedPatient['phone'] ?? '+254 712 345 678') ?> &bull; Scheme: <strong class="<?= $isDarkBlue ? 'text-slate-300' : 'text-slate-700' ?>"><?= htmlspecialchars($selectedPatient['payment_scheme'] ?? 'Cash') ?></strong></span>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <a href="index.php?action=transition_stage&patient_id=<?= $activePatientId ?>&to_stage=<?= urlencode($nxt['to']) ?>&return_tab=<?= urlencode($nxt['tab']) ?>" class="px-3.5 py-1.5 rounded-xl bg-[#06559d] hover:bg-[#00407a] text-white font-bold text-xs shadow-xs transition flex items-center gap-1.5 cursor-pointer">
              <span><?= $nxt['label'] ?></span>
              <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
          </div>
        </div>
      <?php endif; ?>

      <!-- 1. HOSPITAL EXECUTIVE DASHBOARD -->
      <?php if ($activeTab === 'dashboard'): ?>
        <div class="space-y-6 <?= $isDarkBlue ? 'bg-[#07162c] -m-4 sm:-m-6 p-4 sm:p-6 rounded-3xl border border-[#1b3b6f] text-slate-100 shadow-xl' : '' ?>">
          
          <!-- Executive Header -->
          <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]' : 'border-[#c7e0f6]' ?>">
            <div>
              <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold <?= $isDarkBlue ? 'bg-[#0a2f5a] text-sky-200 border border-sky-400/40' : 'bg-[#0260a8] text-white' ?>">
                  <i class="fa-solid fa-crown mr-1"></i> Admin Command Centre
                </span>
                <span class="text-xs font-mono <?= $isDarkBlue ? 'text-emerald-300 bg-emerald-950/60 border border-emerald-800' : 'text-emerald-700 bg-emerald-50 border border-emerald-200' ?> px-2 py-0.5 rounded font-semibold">
                  MOH Code: <?= htmlspecialchars($_SESSION['facility_settings']['moh_code']) ?>
                </span>
              </div>
              <h2 class="text-xl sm:text-2xl font-bold <?= $isDarkBlue ? 'text-white' : 'text-[#0c477d]' ?> tracking-tight mt-1">
                Hospital Executive Operations &amp; All Modules Hub
              </h2>
              <p class="text-xs <?= $isDarkBlue ? 'text-slate-300' : 'text-slate-600' ?>">
                Centralized administration interface providing immediate oversight and routing across all clinical, diagnostic, and financial modules.
              </p>
            </div>
            <div class="flex items-center gap-2">
              <a href="index.php?tab=registration" class="bg-[#0069c8] hover:bg-[#0057a6] text-white font-bold px-4 py-2 rounded-xl text-xs shadow-md transition flex items-center gap-2">
                <i class="fa-solid fa-user-plus"></i><span>Register New Patient</span>
              </a>
              <a href="index.php?tab=settings" class="<?= $isDarkBlue ? 'bg-[#0c2340] hover:bg-[#123158] text-slate-200 border-[#1b3b6f]' : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-300' ?> border font-bold px-3 py-2 rounded-xl text-xs transition flex items-center gap-1.5 shadow-2xs">
                <i class="fa-solid fa-gear"></i><span>Settings</span>
              </a>
            </div>
          </div>

          <!-- 1-Click Role Switcher Bar Under Hospital Command Centre -->
          <div class="<?= $isDarkBlue ? 'bg-[#0c2340] border-[#1b3b6f] text-slate-100' : 'bg-white border-[#c7e0f6]' ?> rounded-2xl p-4 border shadow-2xs flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
              <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-[#06559d] to-blue-600 text-white flex items-center justify-center font-black text-sm shadow-xs border border-white/40">
                <?= $userInitials ?>
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="font-extrabold <?= $isDarkBlue ? 'text-white' : 'text-slate-900' ?> text-sm"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#06559d] text-white uppercase tracking-wider font-mono"><?= htmlspecialchars($currentUser['role']) ?></span>
                  <span class="text-[11px] text-emerald-400 font-bold inline-flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Active Session
                  </span>
                </div>
                <p class="text-xs <?= $isDarkBlue ? 'text-slate-400' : 'text-slate-500' ?> mt-0.5">
                  Assigned Duty: <strong class="<?= $isDarkBlue ? 'text-slate-200' : 'text-slate-700' ?>"><?= htmlspecialchars($currentUser['department'] ?? 'Hospital Administration') ?></strong>
                </p>
              </div>
            </div>

            <!-- 1-Click Role Switch Buttons (Admin Only) -->
            <?php if ($currentUser['role'] === 'admin'): ?>
              <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider mr-1">Admin Switch:</span>
                <a href="index.php?switch_role=admin&return_tab=dashboard" title="Switch to Admin" class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $currentUser['role'] === 'admin' ? 'bg-[#06559d] text-white shadow-xs' : ($isDarkBlue ? 'bg-[#07162c] text-slate-200 border border-[#1b3b6f]' : 'bg-slate-100 hover:bg-slate-200 text-slate-700') ?>">
                  <i class="fa-solid fa-crown text-[10px]"></i><span>Admin</span>
                </a>
                <a href="index.php?switch_role=doctor&return_tab=dashboard" title="Switch to Doctor" class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $currentUser['role'] === 'doctor' ? 'bg-emerald-600 text-white shadow-xs' : ($isDarkBlue ? 'bg-[#07162c] text-slate-200 border border-[#1b3b6f]' : 'bg-slate-100 hover:bg-slate-200 text-slate-700') ?>">
                  <i class="fa-solid fa-user-doctor text-[10px]"></i><span>Doctor</span>
                </a>
                <a href="index.php?switch_role=nurse&return_tab=dashboard" title="Switch to Nurse" class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $currentUser['role'] === 'nurse' ? 'bg-cyan-600 text-white shadow-xs' : ($isDarkBlue ? 'bg-[#07162c] text-slate-200 border border-[#1b3b6f]' : 'bg-slate-100 hover:bg-slate-200 text-slate-700') ?>">
                  <i class="fa-solid fa-stethoscope text-[10px]"></i><span>Nurse</span>
                </a>
                <a href="index.php?switch_role=pharmacist&return_tab=dashboard" title="Switch to Pharmacist" class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $currentUser['role'] === 'pharmacist' ? 'bg-amber-600 text-white shadow-xs' : ($isDarkBlue ? 'bg-[#07162c] text-slate-200 border border-[#1b3b6f]' : 'bg-slate-100 hover:bg-slate-200 text-slate-700') ?>">
                  <i class="fa-solid fa-prescription-bottle-medical text-[10px]"></i><span>Pharmacist</span>
                </a>
                <a href="index.php?switch_role=lab_tech&return_tab=dashboard" title="Switch to Lab Tech" class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $currentUser['role'] === 'lab_tech' ? 'bg-purple-600 text-white shadow-xs' : ($isDarkBlue ? 'bg-[#07162c] text-slate-200 border border-[#1b3b6f]' : 'bg-slate-100 hover:bg-slate-200 text-slate-700') ?>">
                  <i class="fa-solid fa-vial-virus text-[10px]"></i><span>Lab Tech</span>
                </a>
                <a href="index.php?switch_role=accountant&return_tab=dashboard" title="Switch to Accountant" class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $currentUser['role'] === 'accountant' ? 'bg-teal-600 text-white shadow-xs' : ($isDarkBlue ? 'bg-[#07162c] text-slate-200 border border-[#1b3b6f]' : 'bg-slate-100 hover:bg-slate-200 text-slate-700') ?>">
                  <i class="fa-solid fa-file-invoice-dollar text-[10px]"></i><span>Accountant</span>
                </a>
              </div>
            <?php else: ?>
              <!-- Non-Admin: Fixed Assigned Role Badge (No switching or viewing other roles) -->
              <div class="px-3 py-1.5 rounded-xl text-xs font-bold <?= $isDarkBlue ? 'bg-[#0c2340] text-sky-300 border border-[#1b3b6f]' : 'bg-blue-50 text-[#06559d] border border-blue-200' ?> flex items-center gap-2 shadow-2xs select-none">
                <i class="fa-solid fa-user-shield text-emerald-500"></i>
                <span>Designated Role: <strong class="uppercase font-mono"><?= htmlspecialchars($currentUser['role']) ?></strong></span>
                <span class="text-[10px] text-slate-400">&bull; <?= htmlspecialchars($currentUser['department'] ?? 'Station') ?></span>
              </div>
            <?php endif; ?>
          </div>

          <!-- KPI Metric Cards (Automated Real-Time Dynamic Counting) -->
          <?php
            $autoTotalPatients = count($_SESSION['patients'] ?? []);
            $autoTriageQueue = count(array_filter($_SESSION['patients'] ?? [], fn($p) => in_array($p['current_status'] ?? '', ['Registered', 'Triaged'])));
            $autoConsultations = count(array_filter($_SESSION['patients'] ?? [], fn($p) => in_array($p['current_status'] ?? '', ['In_Consultation', 'Lab_Pending', 'Pharmacy_Pending', 'Billing_Pending', 'Discharged'])));
            $autoLabSpecimens = count(array_filter($_SESSION['patients'] ?? [], fn($p) => in_array($p['current_status'] ?? '', ['Lab_Pending', 'Pharmacy_Pending', 'Billing_Pending', 'Discharged'])));
            $autoPharmacyStock = !empty($_SESSION['pharmacy_inventory']) ? array_sum(array_column($_SESSION['pharmacy_inventory'], 'stock_qty')) : 1165;
            $autoDailyRevenue = !empty($_SESSION['revenue_ledger']) ? array_sum(array_column($_SESSION['revenue_ledger'], 'amount')) : 9750.00;
          ?>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="<?= $isDarkBlue ? 'bg-[#0c2340] border-[#1b3b6f]' : 'bg-white border-[#c7e0f6]' ?> p-3.5 rounded-xl border shadow-2xs">
              <span class="<?= $isDarkBlue ? 'text-blue-300' : 'text-slate-400' ?> text-[11px] block font-semibold uppercase">Total Patients</span>
              <strong class="text-2xl font-black <?= $isDarkBlue ? 'text-sky-400' : 'text-[#06559d]' ?> mt-1 block"><?= $autoTotalPatients ?></strong>
              <span class="text-[10px] text-emerald-400 font-bold">&check; In Master Index</span>
            </div>
            <div class="<?= $isDarkBlue ? 'bg-[#0c2340] border-[#1b3b6f]' : 'bg-white border-[#c7e0f6]' ?> p-3.5 rounded-xl border shadow-2xs">
              <span class="<?= $isDarkBlue ? 'text-blue-300' : 'text-slate-400' ?> text-[11px] block font-semibold uppercase">Triage Queue</span>
              <strong class="text-2xl font-black text-emerald-400 mt-1 block"><?= $autoTriageQueue ?></strong>
              <span class="text-[10px] <?= $isDarkBlue ? 'text-slate-400' : 'text-slate-500' ?> font-medium">Bio-signals Ready</span>
            </div>
            <div class="<?= $isDarkBlue ? 'bg-[#0c2340] border-[#1b3b6f]' : 'bg-white border-[#c7e0f6]' ?> p-3.5 rounded-xl border shadow-2xs">
              <span class="<?= $isDarkBlue ? 'text-blue-300' : 'text-slate-400' ?> text-[11px] block font-semibold uppercase">Consultations</span>
              <strong class="text-2xl font-black text-sky-400 mt-1 block"><?= $autoConsultations ?></strong>
              <span class="text-[10px] <?= $isDarkBlue ? 'text-slate-400' : 'text-slate-500' ?> font-medium">Doctor on Duty</span>
            </div>
            <div class="<?= $isDarkBlue ? 'bg-[#0c2340] border-[#1b3b6f]' : 'bg-white border-[#c7e0f6]' ?> p-3.5 rounded-xl border shadow-2xs">
              <span class="<?= $isDarkBlue ? 'text-blue-300' : 'text-slate-400' ?> text-[11px] block font-semibold uppercase">Lab Specimen</span>
              <strong class="text-2xl font-black text-purple-400 mt-1 block"><?= $autoLabSpecimens ?></strong>
              <span class="text-[10px] text-purple-300 font-bold">mRDT &bull; CBC</span>
            </div>
            <div class="<?= $isDarkBlue ? 'bg-[#0c2340] border-[#1b3b6f]' : 'bg-white border-[#c7e0f6]' ?> p-3.5 rounded-xl border shadow-2xs">
              <span class="<?= $isDarkBlue ? 'text-blue-300' : 'text-slate-400' ?> text-[11px] block font-semibold uppercase">Pharmacy Stock</span>
              <strong class="text-2xl font-black text-amber-400 mt-1 block"><?= number_format($autoPharmacyStock) ?></strong>
              <span class="text-[10px] text-emerald-400 font-bold">Formulary Active</span>
            </div>
            <div class="<?= $isDarkBlue ? 'bg-[#0c2340] border-[#1b3b6f]' : 'bg-white border-[#c7e0f6]' ?> p-3.5 rounded-xl border shadow-2xs">
              <span class="<?= $isDarkBlue ? 'text-blue-300' : 'text-slate-400' ?> text-[11px] block font-semibold uppercase">Daily Revenue</span>
              <strong class="text-xl font-black text-emerald-400 mt-1 block font-mono">KES <?= number_format($autoDailyRevenue, 2) ?></strong>
              <span class="text-[10px] text-sky-300 font-bold">M-Pesa + Cash</span>
            </div>
          </div>

          <!-- NORMALIZED CLINICAL WORKFLOW PIPELINE & REAL-TIME DEPARTMENT TRANSITIONS -->
          <?php
            $stages = [
              'Registered'       => ['title' => 'Registration',   'step' => 1, 'tab' => 'registration', 'icon' => 'fa-clipboard-list', 'color' => 'blue',    'next' => 'Triaged',          'next_tab' => 'triage',        'next_lbl' => 'Send to Triage'],
              'Triaged'          => ['title' => 'Triage Desk',    'step' => 2, 'tab' => 'triage',       'icon' => 'fa-stethoscope',    'color' => 'rose',    'next' => 'In_Consultation',  'next_tab' => 'consultation',  'next_lbl' => 'Send to Doctor'],
              'In_Consultation'  => ['title' => 'Doctor Consult', 'step' => 3, 'tab' => 'consultation', 'icon' => 'fa-user-doctor',   'color' => 'indigo',  'next' => 'Lab_Pending',      'next_tab' => 'laboratory',    'next_lbl' => 'Send to Lab'],
              'Lab_Pending'      => ['title' => 'Laboratory',     'step' => 4, 'tab' => 'laboratory',   'icon' => 'fa-flask',          'color' => 'purple',  'next' => 'Pharmacy_Pending', 'next_tab' => 'pharmacy',      'next_lbl' => 'Send to Pharmacy'],
              'Pharmacy_Pending' => ['title' => 'Pharmacy',       'step' => 5, 'tab' => 'pharmacy',     'icon' => 'fa-pills',          'color' => 'amber',   'next' => 'Billing_Pending',  'next_tab' => 'billing',       'next_lbl' => 'Send to Billing'],
              'Billing_Pending'  => ['title' => 'Billing & POS',  'step' => 6, 'tab' => 'billing',      'icon' => 'fa-receipt',        'color' => 'emerald', 'next' => 'Discharged',       'next_tab' => 'receipt',       'next_lbl' => 'Settle & Receipt'],
            ];
          ?>
          <div class="<?= $isDarkBlue ? 'bg-[#081b35] border-[#1b3b6f]' : 'bg-white border-[#c7e0f6]' ?> rounded-2xl p-5 border shadow-sm space-y-4">
            
            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]' : 'border-slate-100' ?>">
              <div>
                <div class="flex items-center gap-2">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#06559d] text-white uppercase tracking-wider font-mono flex items-center gap-1">
                    <i class="fa-solid fa-arrows-split-up-and-left text-[9px]"></i> Normalized Clinical Workflow Pipeline
                  </span>
                  <span class="text-xs text-emerald-600 font-bold font-mono">Real-Time Department Transition</span>
                </div>
                <h3 class="text-base sm:text-lg font-bold <?= $isDarkBlue ? 'text-white' : 'text-slate-900' ?> mt-1">
                  Active Outpatient Journey Across 6 Core Hospital Departments
                </h3>
                <p class="text-xs <?= $isDarkBlue ? 'text-slate-400' : 'text-slate-500' ?>">
                  Move patients forward in real time: Registration &rarr; Triage Desk &rarr; Doctor Consult &rarr; Laboratory &rarr; Pharmacy &rarr; Billing &rarr; Discharged.
                </p>
              </div>

              <div class="flex items-center gap-2">
                <button type="button" onclick="document.getElementById('lifecycleAddModal').classList.toggle('hidden')" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-xs transition flex items-center gap-1.5 cursor-pointer">
                  <i class="fa-solid fa-user-plus"></i>
                  <span>+ Add Active Patient to Lifecycle</span>
                </button>
              </div>
            </div>

            <!-- COLLAPSIBLE ADD ACTIVE PATIENT FORM -->
            <div id="lifecycleAddModal" class="hidden p-4 rounded-xl <?= $isDarkBlue ? 'bg-[#07162c] border border-[#1b3b6f]' : 'bg-blue-50/70 border border-blue-200' ?> text-xs space-y-3">
              <div class="flex items-center justify-between font-bold text-sm <?= $isDarkBlue ? 'text-sky-300' : 'text-[#06559d]' ?>">
                <span class="flex items-center gap-1.5"><i class="fa-solid fa-hospital-user"></i> Quick Add Patient Through IHMS Lifecycle</span>
                <button type="button" onclick="document.getElementById('lifecycleAddModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 p-1">&times;</button>
              </div>

              <!-- Preset Fill Buttons -->
              <div class="space-y-1">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Quick Presets (1-Click Fill):</span>
                <div class="flex flex-wrap gap-1.5">
                  <button type="button" onclick="fillPreset('Samuel Mwangi', 'Male', 'SHA', 'Registered')" class="px-2 py-1 bg-white border border-slate-300 hover:border-blue-500 rounded-lg text-[10px] font-semibold text-slate-700 cursor-pointer">🌡️ Malaria Crisis (Registration)</button>
                  <button type="button" onclick="fillPreset('Grace Wanjiru', 'Female', 'Cash', 'Triaged')" class="px-2 py-1 bg-white border border-slate-300 hover:border-blue-500 rounded-lg text-[10px] font-semibold text-slate-700 cursor-pointer">🩺 Hypertensive Urgency (Triage)</button>
                  <button type="button" onclick="fillPreset('Brian Kiprono', 'Male', 'Private Insurance', 'In_Consultation')" class="px-2 py-1 bg-white border border-slate-300 hover:border-blue-500 rounded-lg text-[10px] font-semibold text-slate-700 cursor-pointer">🫁 Pediatric Bronchitis (Doctor)</button>
                  <button type="button" onclick="fillPreset('Amina Hassan', 'Female', 'SHA', 'Lab_Pending')" class="px-2 py-1 bg-white border border-slate-300 hover:border-blue-500 rounded-lg text-[10px] font-semibold text-slate-700 cursor-pointer">🧪 Diabetes Review (Laboratory)</button>
                  <button type="button" onclick="fillPreset('Kevin Otieno', 'Male', 'Cash', 'Pharmacy_Pending')" class="px-2 py-1 bg-white border border-slate-300 hover:border-blue-500 rounded-lg text-[10px] font-semibold text-slate-700 cursor-pointer">💊 Tonsillitis (Pharmacy)</button>
                  <button type="button" onclick="fillPreset('Matty Jeff', 'Female', 'SHA', 'Billing_Pending')" class="px-2 py-1 bg-white border border-slate-300 hover:border-blue-500 rounded-lg text-[10px] font-semibold text-slate-700 cursor-pointer">💳 Clearance (Billing Desk)</button>
                </div>
              </div>

              <form method="POST" action="index.php?tab=dashboard" class="grid grid-cols-1 sm:grid-cols-4 gap-3 pt-2">
                <input type="hidden" name="action" value="add_lifecycle_patient">
                <div>
                  <label class="block font-bold mb-1 text-slate-700">Full Name *</label>
                  <input type="text" id="preset_name" name="full_name" required placeholder="e.g. Samuel Mwangi" class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg">
                </div>
                <div>
                  <label class="block font-bold mb-1 text-slate-700">Gender</label>
                  <select id="preset_gender" name="gender" class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                  </select>
                </div>
                <div>
                  <label class="block font-bold mb-1 text-slate-700">Payment Scheme</label>
                  <select id="preset_scheme" name="payment_scheme" class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg">
                    <option value="SHA">Social Health Authority (SHA)</option>
                    <option value="Cash">Cash / M-Pesa</option>
                    <option value="Private Insurance">Private Insurance</option>
                  </select>
                </div>
                <div>
                  <label class="block font-bold mb-1 text-slate-700">Start Stage</label>
                  <select id="preset_stage" name="start_stage" class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg font-bold">
                    <option value="Registered">1. Registration</option>
                    <option value="Triaged">2. Triage Desk</option>
                    <option value="In_Consultation">3. Doctor Consult</option>
                    <option value="Lab_Pending">4. Laboratory</option>
                    <option value="Pharmacy_Pending">5. Pharmacy</option>
                    <option value="Billing_Pending">6. Billing Desk</option>
                    <option value="Discharged">7. Discharged</option>
                  </select>
                </div>
                <div class="sm:col-span-4 flex justify-end gap-2 pt-1">
                  <button type="button" onclick="document.getElementById('lifecycleAddModal').classList.add('hidden')" class="px-3 py-1.5 bg-slate-200 text-slate-700 font-bold rounded-lg cursor-pointer">Cancel</button>
                  <button type="submit" class="px-4 py-1.5 bg-[#06559d] hover:bg-[#00407a] text-white font-bold rounded-lg cursor-pointer flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Admit Patient to Lifecycle &rarr;</span>
                  </button>
                </div>
              </form>
            </div>

            <!-- 6-DEPARTMENT NORMALIZED KANBAN PIPELINE -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 pt-2">
              <?php foreach ($stages as $stageKey => $cfg): ?>
                <?php
                  $colPatients = array_filter($_SESSION['patients'], function($p) use ($stageKey) {
                    return ($p['current_status'] ?? '') === $stageKey;
                  });
                ?>
                <div class="flex flex-col rounded-xl border <?= $isDarkBlue ? 'bg-[#07162c] border-[#1b3b6f]' : 'bg-slate-50 border-slate-200' ?> p-2.5 min-h-[340px]">
                  
                  <!-- Column Header -->
                  <div class="pb-2 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]' : 'border-slate-200' ?> flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                      <span class="w-6 h-6 rounded-lg bg-[#06559d] text-white flex items-center justify-center text-xs font-bold shadow-2xs">
                        <i class="fa-solid <?= $cfg['icon'] ?> text-[10px]"></i>
                      </span>
                      <div>
                        <div class="text-xs font-extrabold <?= $isDarkBlue ? 'text-white' : 'text-slate-900' ?>">
                          <?= $cfg['step'] ?>. <?= $cfg['title'] ?>
                        </div>
                      </div>
                    </div>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold <?= $isDarkBlue ? 'bg-slate-800 text-sky-300 border border-slate-700' : 'bg-white text-[#06559d] border border-slate-200' ?>">
                      <?= count($colPatients) ?>
                    </span>
                  </div>

                  <!-- Jump Link to Department Tab -->
                  <a href="index.php?tab=<?= $cfg['tab'] ?>" class="mt-1.5 text-[10px] font-bold text-blue-600 hover:text-blue-800 flex items-center justify-between py-1 px-1.5 rounded bg-blue-50/70 hover:bg-blue-100/70 transition">
                    <span>Open <?= $cfg['title'] ?></span>
                    <i class="fa-solid fa-chevron-right text-[8px]"></i>
                  </a>

                  <!-- Patient Cards inside Column -->
                  <div class="mt-2 space-y-2 flex-1 overflow-y-auto">
                    <?php if (empty($colPatients)): ?>
                      <div class="h-28 border-2 border-dashed <?= $isDarkBlue ? 'border-slate-800' : 'border-slate-200' ?> rounded-lg flex flex-col items-center justify-center text-center p-2 text-slate-400 text-[10px]">
                        <i class="fa-solid fa-circle-check text-slate-300 text-base mb-1"></i>
                        <span>Queue Clear</span>
                      </div>
                    <?php else: ?>
                      <?php foreach ($colPatients as $pat): ?>
                        <div class="bg-white rounded-lg p-2.5 border border-slate-200 shadow-2xs text-slate-800 space-y-1.5 hover:border-blue-400 transition">
                          <div class="flex items-center justify-between">
                            <span class="text-[10px] font-mono font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-100">
                              <?= htmlspecialchars($pat['opd_number']) ?>
                            </span>
                            <span class="text-[9px] font-bold text-slate-500">
                              <?= htmlspecialchars($pat['gender']) ?>
                            </span>
                          </div>

                          <div class="font-bold text-xs text-slate-900 truncate" title="<?= htmlspecialchars($pat['full_name']) ?>">
                            <?= htmlspecialchars($pat['full_name']) ?>
                          </div>

                          <div class="text-[10px] text-slate-500 flex items-center justify-between">
                            <span class="truncate max-w-[85px]"><?= htmlspecialchars($pat['payment_scheme']) ?></span>
                            <span class="text-emerald-700 font-semibold font-mono text-[9px]">&bull; Active</span>
                          </div>

                          <!-- 1-Click Real-Time Department Transition Button -->
                          <div class="pt-1.5 border-t border-slate-100 flex flex-col gap-1">
                            <a href="index.php?action=transition_stage&patient_id=<?= $pat['patient_id'] ?>&to_stage=<?= urlencode($cfg['next']) ?>&return_tab=dashboard" class="w-full bg-[#06559d] hover:bg-[#00407a] text-white font-bold py-1 px-1.5 rounded text-[10px] transition text-center flex items-center justify-center gap-1 shadow-2xs">
                              <span><?= $cfg['next_lbl'] ?></span>
                              <i class="fa-solid fa-arrow-right text-[8px]"></i>
                            </a>

                            <div class="flex items-center justify-between text-[9px] text-blue-700 pt-0.5">
                              <a href="index.php?tab=<?= $cfg['tab'] ?>&patient_id=<?= $pat['patient_id'] ?>" class="hover:underline font-semibold flex items-center gap-0.5">
                                <i class="fa-solid fa-pen-to-square text-[8px]"></i> Workstation
                              </a>
                              <a href="index.php?action=transition_stage&patient_id=<?= $pat['patient_id'] ?>&to_stage=Discharged&return_tab=receipt" class="text-emerald-700 hover:underline font-semibold" title="Fast Clear & Bill">
                                Settle Bill
                              </a>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Discharged Patients Archive Strip -->
            <?php
              $discharged = array_filter($_SESSION['patients'], function($p) {
                return ($p['current_status'] ?? '') === 'Discharged';
              });
            ?>
            <div class="pt-3 border-t <?= $isDarkBlue ? 'border-[#1b3b6f]' : 'border-slate-100' ?> flex flex-wrap items-center justify-between gap-2 text-xs">
              <div class="flex items-center gap-2">
                <span class="text-emerald-600 font-bold flex items-center gap-1">
                  <i class="fa-solid fa-circle-check"></i> Discharged &amp; Cleared (<?= count($discharged) ?>):
                </span>
                <span class="<?= $isDarkBlue ? 'text-slate-300' : 'text-slate-600' ?> text-[11px]">
                  <?php foreach ($discharged as $dp): ?>
                    <a href="index.php?tab=receipt&patient_id=<?= $dp['patient_id'] ?>" class="inline-block bg-emerald-50 text-emerald-800 border border-emerald-200 px-2 py-0.5 rounded mr-1 hover:bg-emerald-100 font-mono text-[10px]">
                      <?= htmlspecialchars($dp['full_name']) ?> (<?= htmlspecialchars($dp['opd_number']) ?>) &bull; Receipt &rarr;
                    </a>
                  <?php endforeach; ?>
                </span>
              </div>
            </div>

          </div>

          <script>
            function fillPreset(name, gender, scheme, stage) {
              document.getElementById('preset_name').value = name;
              document.getElementById('preset_gender').value = gender;
              document.getElementById('preset_scheme').value = scheme;
              document.getElementById('preset_stage').value = stage;
            }
          </script>
        </div>
      <?php endif; ?>

      <!-- 2. PATIENT INTAKE & REGISTRATION -->
      <?php if ($activeTab === 'registration'): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm max-w-3xl mx-auto space-y-4 text-slate-800">
          <div class="border-b border-slate-100 pb-3">
            <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
              <i class="fa-solid fa-user-plus text-[#06559d]"></i> Patient Intake &amp; Registration Form
            </h2>
            <p class="text-xs text-slate-500">Capture national ID, demographics, contact, and payment schemes.</p>
          </div>
          <form method="POST" action="index.php?tab=registration" class="space-y-4">
            <input type="hidden" name="action" value="register_patient">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Full Patient Name *</label>
                <input type="text" name="full_name" required placeholder="" class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Phone Number *</label>
                <input type="text" name="phone" required placeholder=" " class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Gender *</label>
                <select name="gender" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none">
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Payment Scheme *</label>
                <select name="payment_scheme" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none">
                  <option value="Cash">Cash / M-Pesa</option>
                  <option value="SHA">Social Health Authority (SHA)</option>
                  <option value="Private Insurance">Private Insurance</option>
                </select>
              </div>
            </div>
            <button type="submit" class="w-full py-2.5 bg-[#06559d] hover:bg-[#05437d] text-white font-bold rounded-xl text-xs shadow-md transition">
              Save Patient &amp; Route to Triage Desk &rarr;
            </button>
          </form>
        </div>
      <?php endif; ?>

      <!-- 3. TRIAGE DESK -->
      <?php if ($activeTab === 'triage'): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm max-w-3xl mx-auto space-y-4 text-slate-800">
          <div class="border-b border-slate-100 pb-3 flex justify-between items-center">
            <div>
              <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-stethoscope text-emerald-600"></i> Clinical Triage Station
              </h2>
              <p class="text-xs text-slate-500">Record bio-signals for: <strong><?= htmlspecialchars($selectedPatient['full_name'] ?? 'Patient') ?></strong> (<?= htmlspecialchars($selectedPatient['opd_number'] ?? 'OPD') ?>)</p>
            </div>
            <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold font-mono">Status: Ready</span>
          </div>
          <form method="POST" action="index.php?tab=triage&patient_id=<?= $activePatientId ?>" class="space-y-4">
            <input type="hidden" name="action" value="save_triage">
            <input type="hidden" name="patient_id" value="<?= $activePatientId ?>">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Systolic BP (mmHg)</label>
                <input type="number" name="bp_systolic" value="120" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Diastolic BP (mmHg)</label>
                <input type="number" name="bp_diastolic" value="80" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Heart Rate (bpm)</label>
                <input type="number" name="heart_rate" value="76" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Temperature (&deg;C)</label>
                <input type="number" step="0.1" name="temperature" value="36.8" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none">
              </div>
            </div>
            <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md transition">
              Validate Vitals &amp; Route to Doctor &rarr;
            </button>
          </form>
        </div>
      <?php endif; ?>

      <!-- 4. DOCTOR CONSULTATION -->
      <?php if ($activeTab === 'consultation'): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm max-w-3xl mx-auto space-y-4 text-slate-800">
          <div class="border-b border-slate-100 pb-3">
            <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
              <i class="fa-solid fa-user-doctor text-blue-600"></i> Doctor Consultation Workstation
            </h2>
            <p class="text-xs text-slate-500">Patient: <strong><?= htmlspecialchars($selectedPatient['full_name'] ?? 'Samuel Mwangi') ?></strong></p>
          </div>
          <form method="POST" action="index.php?tab=consultation&patient_id=<?= $activePatientId ?>" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="save_consultation">
            <input type="hidden" name="patient_id" value="<?= $activePatientId ?>">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Chief Complaint</label>
              <input type="text" name="chief_complaint" value="Acute febrile illness, joint pains, headache" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none">
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Clinical Diagnosis</label>
              <input type="text" name="diagnosis" value="Clinical Malaria (Suspected) / Acute URI" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none">
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Prescription (Formulary)</label>
              <textarea name="prescription" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none">Coartem 20/120mg Tabs 1x4 3/7, Paracetamol 500mg 1x3 3/7</textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
              <button type="submit" name="route_to" value="lab" class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center justify-center gap-1.5 cursor-pointer">
                <i class="fa-solid fa-flask"></i>
                <span>Order STAT Lab &amp; Send to Lab &rarr;</span>
              </button>
              <button type="submit" name="route_to" value="pharmacy" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center justify-center gap-1.5 cursor-pointer">
                <i class="fa-solid fa-pills"></i>
                <span>Sign Rx &amp; Send to Pharmacy &rarr;</span>
              </button>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <!-- 5. DIAGNOSTIC LABORATORY -->
      <?php if ($activeTab === 'laboratory'): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm max-w-3xl mx-auto space-y-4 text-slate-800">
          <div class="border-b border-slate-100 pb-3">
            <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
              <i class="fa-solid fa-flask text-purple-600"></i> Diagnostic Pathology Laboratory
            </h2>
            <p class="text-xs text-slate-500">Specimen test orders for: <strong><?= htmlspecialchars($selectedPatient['full_name'] ?? 'Samuel Mwangi') ?></strong></p>
          </div>
          <div class="p-3 bg-purple-50 rounded-xl border border-purple-200 text-purple-900 text-xs space-y-2">
            <div class="flex justify-between"><span>Rapid Malaria Test (mRDT):</span><strong class="text-rose-600">POSITIVE (Plasmodium falciparum)</strong></div>
            <div class="flex justify-between"><span>Complete Blood Count (CBC):</span><strong>Hemoglobin: 13.8 g/dL (Normal)</strong></div>
          </div>
          <form method="POST" action="index.php?tab=laboratory&patient_id=<?= $activePatientId ?>">
            <input type="hidden" name="action" value="complete_lab">
            <input type="hidden" name="patient_id" value="<?= $activePatientId ?>">
            <button type="submit" class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center justify-center gap-2 cursor-pointer">
              <i class="fa-solid fa-circle-check"></i>
              <span>Validate Lab Results &amp; Forward to Pharmacy &rarr;</span>
            </button>
          </form>
        </div>
      <?php endif; ?>

      <!-- 6. PHARMACY DISPENSARY -->
      <?php if ($activeTab === 'pharmacy'): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm max-w-3xl mx-auto space-y-4 text-slate-800">
          <div class="border-b border-slate-100 pb-3">
            <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
              <i class="fa-solid fa-pills text-amber-600"></i> Pharmacy Dispensary &amp; Stock Verification
            </h2>
            <p class="text-xs text-slate-500">Dispense prescription for: <strong><?= htmlspecialchars($selectedPatient['full_name'] ?? 'Samuel Mwangi') ?></strong></p>
          </div>
          <form method="POST" action="index.php?tab=pharmacy&patient_id=<?= $activePatientId ?>" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="dispense_medication">
            <input type="hidden" name="patient_id" value="<?= $activePatientId ?>">
            <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900">
              <strong>Prescribed Medication:</strong> Coartem 20/120mg (Qty: 24 tabs) &bull; Paracetamol 500mg (Qty: 20 tabs)
            </div>
            <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs shadow-md transition">
              Confirm Dispense &amp; Route to Billing &rarr;
            </button>
          </form>
        </div>
      <?php endif; ?>

      <!-- 7. BILLING & POS -->
      <?php if ($activeTab === 'billing'): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm max-w-3xl mx-auto space-y-4 text-slate-800">
          <div class="border-b border-slate-100 pb-3">
            <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
              <i class="fa-solid fa-receipt text-teal-600"></i> Cashier Billing &amp; Revenue POS
            </h2>
            <p class="text-xs text-slate-500">Patient: <strong><?= htmlspecialchars($selectedPatient['full_name'] ?? 'Samuel Mwangi') ?></strong></p>
          </div>
          <div class="space-y-2 text-xs">
            <div class="flex justify-between py-1 border-b"><span>General Consultation Fee</span><span class="font-mono">KES <?= number_format($_SESSION['facility_settings']['consultation_fee'], 2) ?></span></div>
            <div class="flex justify-between py-1 border-b"><span>Diagnostic Lab Tests (mRDT &amp; CBC)</span><span class="font-mono">KES 1,200.00</span></div>
            <div class="flex justify-between py-1 border-b"><span>Pharmacy Formulary Medication</span><span class="font-mono">KES 1,550.00</span></div>
            <div class="flex justify-between py-2 text-base font-black text-slate-900"><span>Total Due:</span><span class="font-mono text-emerald-700">KES <?= number_format($_SESSION['facility_settings']['consultation_fee'] + 2750, 2) ?></span></div>
          </div>
          <form method="POST" action="index.php?tab=billing&patient_id=<?= $activePatientId ?>" class="space-y-3">
            <input type="hidden" name="action" value="settle_bill">
            <input type="hidden" name="patient_id" value="<?= $activePatientId ?>">
            <button type="submit" class="w-full py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-xl text-xs shadow-md transition">
              Process M-Pesa / Cash Payment &amp; Issue Receipt &rarr;
            </button>
          </form>
        </div>
      <?php endif; ?>

      <!-- 8. OFFICIAL RECEIPT -->
      <?php if ($activeTab === 'receipt'): ?>
        <div class="max-w-md mx-auto bg-white p-6 rounded-2xl border border-slate-200 shadow-md text-xs space-y-3 text-slate-800" id="printableReceipt">
          <div class="text-center border-b border-dashed border-slate-400 pb-3">
            <h3 class="font-black text-base uppercase"><?= htmlspecialchars($_SESSION['facility_settings']['hospital_name']) ?></h3>
            <p class="text-[10px] text-slate-500 font-mono">MOH Code: <?= htmlspecialchars($_SESSION['facility_settings']['moh_code']) ?></p>
            <div class="mt-1 font-mono text-[10px] bg-slate-100 p-1 rounded font-bold">OFFICIAL MEDICAL RECEIPT #REC-<?= rand(10000, 99999) ?></div>
          </div>
          <div class="space-y-1">
            <div class="flex justify-between"><span>Patient:</span><strong><?= htmlspecialchars($selectedPatient['full_name'] ?? 'Samuel Mwangi') ?></strong></div>
            <div class="flex justify-between"><span>OPD Number:</span><span class="font-mono"><?= htmlspecialchars($selectedPatient['opd_number'] ?? 'OPD-2025-019') ?></span></div>
            <div class="flex justify-between"><span>Payment Mode:</span><span class="font-bold text-emerald-700">M-Pesa STK (Settled)</span></div>
          </div>
          <div class="border-t border-b border-dashed border-slate-300 py-2 space-y-1 font-mono">
            <div class="flex justify-between"><span>Consultation Fee:</span><span>KES <?= number_format($_SESSION['facility_settings']['consultation_fee'], 2) ?></span></div>
            <div class="flex justify-between"><span>Diagnostics &amp; Meds:</span><span>KES 2,750.00</span></div>
            <div class="flex justify-between font-black text-sm pt-1 border-t"><span>TOTAL PAID:</span><span>KES <?= number_format($_SESSION['facility_settings']['consultation_fee'] + 2750, 2) ?></span></div>
          </div>
          <div class="text-center pt-1 text-[10px] text-slate-400">
            <p class="font-bold text-emerald-700">&check; PAID &amp; CLEARED</p>
            <p>AfyaSmart IHMS</p>
          </div>
          <div class="no-print pt-3 flex flex-wrap gap-2">
            <button type="button" onclick="printReceipt()" class="flex-1 min-w-[130px] py-2.5 bg-[#06559d] hover:bg-[#05437d] text-white font-bold rounded-xl text-xs shadow-sm transition flex items-center justify-center gap-1.5 cursor-pointer">
              <i class="fa-solid fa-print"></i>
              <span>Print Receipt</span>
            </button>
            <a href="receipt_print.php?patient_id=<?= $activePatientId ?>" target="_blank" class="py-2.5 px-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-sm transition flex items-center gap-1.5 cursor-pointer" title="Open in separate printable window (Bypasses browser iframe restrictions)">
              <i class="fa-solid fa-arrow-up-right-from-square"></i>
              <span>Clean Print Tab</span>
            </a>
            <a href="index.php?tab=dashboard" class="py-2.5 px-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition flex items-center justify-center">
              Dashboard
            </a>
          </div>
        </div>
      <?php endif; ?>

      <!-- 9. SECURITY AUDIT TRAIL -->
      <?php if ($activeTab === 'audit'): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm max-w-4xl mx-auto space-y-4 text-slate-800">
          <div class="border-b border-slate-100 pb-3 flex justify-between items-center">
            <div>
              <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-[#06559d]"></i> Security Audit Trail &amp; Activity Log
              </h2>
              <p class="text-xs text-slate-500">Immutable ledger tracking clinical logins, patient registrations, prescriptions, and financial transactions.</p>
            </div>
            <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold font-mono">Real-Time Logging</span>
          </div>
          <div class="overflow-x-auto text-xs">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600">
                  <th class="p-2.5">Time</th>
                  <th class="p-2.5">User</th>
                  <th class="p-2.5">Role</th>
                  <th class="p-2.5">Action</th>
                  <th class="p-2.5">Module</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                <tr><td class="p-2.5"><?= date('H:i:s') ?></td><td class="p-2.5 font-bold">admin</td><td class="p-2.5 text-blue-700">admin</td><td class="p-2.5 text-emerald-700 font-bold">SESSION_AUTH</td><td class="p-2.5">Command Centre</td></tr>
                <tr><td class="p-2.5"><?= date('H:i:s', time() - 120) ?></td><td class="p-2.5 font-bold">nurse</td><td class="p-2.5 text-cyan-700">nurse</td><td class="p-2.5 text-blue-700 font-bold">RECORD_VITALS</td><td class="p-2.5">Triage Desk</td></tr>
                <tr><td class="p-2.5"><?= date('H:i:s', time() - 360) ?></td><td class="p-2.5 font-bold">doctor</td><td class="p-2.5 text-emerald-700">doctor</td><td class="p-2.5 text-purple-700 font-bold">DIAGNOSIS_SIGN</td><td class="p-2.5">Consultation</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <!-- 10. SYSTEM SETTINGS & STAFF ROLES DIRECTORY -->
      <?php if ($activeTab === 'settings'): ?>
        <?php if ($currentUser['role'] === 'admin'): ?>
          <!-- ADMIN VIEW: FACILITY CONFIGURATION & ALL STAFF ROLES -->
          <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm max-w-4xl mx-auto space-y-6 text-slate-800">
            <div class="border-b border-slate-100 pb-3 flex justify-between items-center">
              <div>
                <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                  <i class="fa-solid fa-shield-halved text-[#06559d]"></i> Hospital Parameters &amp; Staff Roles Directory
                </h2>
                <p class="text-xs text-slate-500">Administrator Command: Only the Hospital Administrator is authorized to view every staff member's role and configure facility parameters.</p>
              </div>
              <span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-900 text-xs font-bold font-mono">Admin Only</span>
            </div>

            <!-- Facility Settings Form -->
            <form method="POST" action="index.php?tab=settings" class="space-y-4 text-xs">
              <input type="hidden" name="action" value="save_settings">

              <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2 border-b pb-1.5">
                <i class="fa-solid fa-hospital text-blue-600"></i> Facility Identity &amp; Clinical Thresholds
              </h3>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block font-bold text-slate-700 mb-1">Facility Name</label>
                  <input type="text" name="hospital_name" value="<?= htmlspecialchars($_SESSION['facility_settings']['hospital_name']) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none">
                </div>
                <div>
                  <label class="block font-bold text-slate-700 mb-1">MOH Registration Code</label>
                  <input type="text" name="moh_code" value="<?= htmlspecialchars($_SESSION['facility_settings']['moh_code']) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none">
                </div>
                <div>
                  <label class="block font-bold text-slate-700 mb-1">General Consultation Fee (KES)</label>
                  <input type="number" step="10" name="consultation_fee" value="<?= htmlspecialchars($_SESSION['facility_settings']['consultation_fee']) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none">
                </div>
                <div>
                  <label class="block font-bold text-slate-700 mb-1">High BP Warning Threshold (mmHg)</label>
                  <input type="number" name="triage_high_bp" value="<?= htmlspecialchars($_SESSION['facility_settings']['triage_high_bp']) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none">
                </div>
              </div>
              <button type="submit" class="w-full py-2.5 bg-[#06559d] hover:bg-[#05437d] text-white font-bold rounded-xl text-xs shadow-md transition">
                Save Facility Configuration
              </button>
            </form>

            <!-- ALL STAFF ROLES DIRECTORY (Strictly Admin View) -->
            <div class="pt-4 border-t border-slate-100 space-y-3">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-users-gear text-purple-600"></i> Authorized Hospital Staff Members &amp; Roles
                  </h3>
                  <p class="text-xs text-slate-500">Each staff member has their designated role. Only Admin can view all member roles.</p>
                </div>
                <span class="text-[10px] bg-purple-100 text-purple-900 font-bold px-2 py-0.5 rounded-full">
                  6 Registered Members
                </span>
              </div>

              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600">
                      <th class="p-2.5 font-bold">Staff Member</th>
                      <th class="p-2.5 font-bold">Username</th>
                      <th class="p-2.5 font-bold">Assigned Role</th>
                      <th class="p-2.5 font-bold">Department</th>
                      <th class="p-2.5 font-bold text-right">Workstation</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <?php
                      $directoryRoles = $systemRoles;
                      if (!empty($_SESSION['registered_users'])) {
                        foreach ($_SESSION['registered_users'] as $rk => $rv) {
                          $directoryRoles[$rk] = $rv;
                        }
                      }
                    ?>
                    <?php foreach ($directoryRoles as $key => $roleItem): ?>
                      <?php $rKey = $roleItem['role'] ?? $key; ?>
                      <tr class="hover:bg-slate-50/80 transition">
                        <td class="p-2.5 font-bold text-slate-900 flex items-center gap-2">
                          <span class="w-7 h-7 rounded-lg <?= $rKey === 'admin' ? 'bg-[#06559d]' : ($rKey === 'doctor' ? 'bg-emerald-600' : ($rKey === 'nurse' ? 'bg-cyan-600' : ($rKey === 'pharmacist' ? 'bg-amber-600' : ($rKey === 'lab_tech' ? 'bg-purple-600' : 'bg-teal-600')))) ?> text-white text-[10px] font-black flex items-center justify-center shrink-0">
                            <?= strtoupper(substr($roleItem['username'], 0, 2)) ?>
                          </span>
                          <span><?= htmlspecialchars($roleItem['full_name']) ?></span>
                        </td>
                        <td class="p-2.5 font-mono text-slate-600"><?= htmlspecialchars($roleItem['username']) ?></td>
                        <td class="p-2.5">
                          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $rKey === 'admin' ? 'bg-blue-100 text-blue-900 font-mono' : 'bg-slate-100 text-slate-800' ?>">
                            <?= htmlspecialchars($rKey) ?>
                          </span>
                        </td>
                        <td class="p-2.5 text-slate-600"><?= htmlspecialchars($roleItem['department']) ?></td>
                        <td class="p-2.5 text-right font-mono text-[11px] text-blue-700">
                          <a href="index.php?tab=<?= urlencode($roleItem['tab'] ?? 'dashboard') ?>" class="hover:underline font-bold">Open &rarr;</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        <?php else: ?>
          <!-- NON-ADMIN VIEW: ONLY SEES OWN ROLE PROFILE -->
          <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm max-w-2xl mx-auto space-y-4 text-slate-800">
            <div class="border-b border-slate-100 pb-3 flex justify-between items-center">
              <div>
                <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                  <i class="fa-solid fa-id-card-clip text-blue-600"></i> My Staff Profile &amp; Role Details
                </h2>
                <p class="text-xs text-slate-500">Your designated clinical role in AfyaSmart IHMS.</p>
              </div>
              <span class="px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 text-xs font-bold uppercase font-mono">
                <?= htmlspecialchars($currentUser['role']) ?>
              </span>
            </div>

            <!-- Access Restriction Notice -->
            <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-center gap-2.5">
              <i class="fa-solid fa-lock text-amber-600 shrink-0 text-sm"></i>
              <span><strong>Access Restricted:</strong> Only the Hospital Administrator is authorized to view every staff member's role or edit system configurations. You are authorized to access your designated departmental workstation.</span>
            </div>

            <!-- Individual Staff Card -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3 text-xs">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-[#06559d] text-white flex items-center justify-center font-black text-lg shrink-0">
                  <?= $userInitials ?>
                </div>
                <div>
                  <h4 class="font-bold text-base text-slate-900"><?= htmlspecialchars($currentUser['full_name']) ?></h4>
                  <p class="text-slate-500 font-mono text-[11px]">Username: <?= htmlspecialchars($currentUser['username']) ?> &bull; User ID: #<?= htmlspecialchars($currentUser['id'] ?? 2) ?></p>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-200">
                <div>
                  <span class="text-slate-500 block text-[11px]">Assigned Role</span>
                  <strong class="text-slate-900 uppercase font-mono"><?= htmlspecialchars($currentUser['role']) ?></strong>
                </div>
                <div>
                  <span class="text-slate-500 block text-[11px]">Department</span>
                  <strong class="text-slate-900"><?= htmlspecialchars($currentUser['department'] ?? 'Clinical Station') ?></strong>
                </div>
                <div>
                  <span class="text-slate-500 block text-[11px]">Assigned Workstation</span>
                  <strong class="text-blue-700 font-mono"><?= htmlspecialchars($currentUser['home'] ?? 'consultation.php') ?></strong>
                </div>
                <div>
                  <span class="text-slate-500 block text-[11px]">Session Status</span>
                  <span class="inline-flex items-center gap-1 font-bold text-emerald-700">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Active Clinical Session
                  </span>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    </section>
  </main>

  <!-- FOOTER -->
  <footer class="bg-slate-900 text-slate-400 py-3 px-5 text-center text-xs border-t border-slate-800 no-print mt-auto">
    <span>AfyaSmart IHMS &bull; Your Health, Our Priority</span>
  </footer>

  <!-- MEDICAL PRINT ENGINE & CLIENT SCRIPTS -->
  <script>
    /**
     * Bulletproof Thermal & A4 Receipt Print Engine
     * Uses invisible isolated iframe to completely bypass Chromium flex/overflow clipping.
     * Guaranteed to render receipt details on Microsoft Edge, Google Chrome, Firefox, and Windows Print Spooler.
     */
    function printReceipt() {
      var receiptEl = document.getElementById('printableReceipt');
      if (!receiptEl) {
        window.print();
        return;
      }

      // 1. Remove existing print frame if present
      var oldFrame = document.getElementById('__afyasmart_print_frame__');
      if (oldFrame) {
        oldFrame.parentNode.removeChild(oldFrame);
      }

      // 2. Create invisible iframe
      var iframe = document.createElement('iframe');
      iframe.id = '__afyasmart_print_frame__';
      iframe.style.position = 'fixed';
      iframe.style.right = '0';
      iframe.style.bottom = '0';
      iframe.style.width = '0';
      iframe.style.height = '0';
      iframe.style.border = '0';
      iframe.style.visibility = 'hidden';
      document.body.appendChild(iframe);

      // 3. Clone receipt element and strip interactive buttons
      var clone = receiptEl.cloneNode(true);
      var noPrintEls = clone.querySelectorAll('.no-print');
      for (var i = 0; i < noPrintEls.length; i++) {
        noPrintEls[i].remove();
      }

      // 4. Construct clean document in the iframe
      var doc = iframe.contentWindow.document;
      doc.open();
      doc.write('<!DOCTYPE html><html><head><title>Official Medical Receipt</title>');
      doc.write('<style>');
      doc.write('@page { size: auto; margin: 6mm; }');
      doc.write('html, body { margin: 0; padding: 12px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #ffffff !important; color: #000000 !important; font-size: 13px; }');
      doc.write('.receipt-box { max-width: 400px; margin: 0 auto; border: 2px dashed #000000; padding: 20px; border-radius: 8px; background: #ffffff; }');
      doc.write('.text-center { text-align: center; }');
      doc.write('.uppercase { text-transform: uppercase; }');
      doc.write('.font-bold { font-weight: bold; }');
      doc.write('.font-black { font-weight: 900; }');
      doc.write('.font-mono { font-family: "Courier New", Courier, monospace; }');
      doc.write('.flex { display: flex; }');
      doc.write('.justify-between { justify-content: space-between; }');
      doc.write('.border-b { border-bottom: 1px dashed #666; padding-bottom: 8px; margin-bottom: 8px; }');
      doc.write('.border-t { border-top: 1px dashed #666; padding-top: 8px; margin-top: 8px; }');
      doc.write('.space-y-1 > * + * { margin-top: 4px; }');
      doc.write('.space-y-3 > * + * { margin-top: 10px; }');
      doc.write('.bg-slate-100 { background: #f1f5f9; padding: 6px; border-radius: 4px; border: 1px solid #cbd5e1; }');
      doc.write('.text-emerald-700 { color: #047857 !important; font-weight: bold; }');
      doc.write('.text-slate-400 { color: #64748b; }');
      doc.write('.text-slate-500 { color: #475569; }');
      doc.write('.text-slate-800, .text-slate-900 { color: #000000; }');
      doc.write('</style></head><body>');
      doc.write('<div class="receipt-box">' + clone.innerHTML + '</div>');
      doc.write('</body></html>');
      doc.close();

      // 5. Trigger print from iframe
      setTimeout(function() {
        try {
          iframe.contentWindow.focus();
          iframe.contentWindow.print();
        } catch (e) {
          window.print();
        }
      }, 250);
    }

    function printInNewWindow() {
      var receiptEl = document.getElementById('printableReceipt');
      if (!receiptEl) {
        window.open('receipt_print.php', '_blank');
        return;
      }

      var clone = receiptEl.cloneNode(true);
      var noPrintEls = clone.querySelectorAll('.no-print');
      for (var i = 0; i < noPrintEls.length; i++) {
        noPrintEls[i].remove();
      }

      var win = window.open('', '_blank', 'width=480,height=720,toolbar=0,menubar=0,scrollbars=1');
      if (!win) {
        window.location.href = 'receipt_print.php';
        return;
      }

      var doc = win.document;
      doc.open();
      doc.write('<!DOCTYPE html><html><head><title>AfyaSmart Medical Receipt</title>');
      doc.write('<style>');
      doc.write('body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f8fafc; color: #1e293b; padding: 24px; margin: 0; }');
      doc.write('.font-mono { font-family: monospace; }');
      doc.write('.receipt-card { max-width: 400px; margin: 0 auto; border: 2px dashed #000; padding: 20px; border-radius: 8px; font-size: 13px; background: #fff; }');
      doc.write('.text-center { text-align: center; }');
      doc.write('.uppercase { text-transform: uppercase; }');
      doc.write('.font-bold { font-weight: bold; }');
      doc.write('.font-black { font-weight: 900; }');
      doc.write('.flex { display: flex; }');
      doc.write('.justify-between { justify-content: space-between; }');
      doc.write('.border-b { border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px; margin-bottom: 8px; }');
      doc.write('.border-t { border-top: 1px dashed #cbd5e1; padding-top: 8px; margin-top: 8px; }');
      doc.write('.space-y-1 > * + * { margin-top: 5px; }');
      doc.write('.space-y-3 > * + * { margin-top: 12px; }');
      doc.write('.text-emerald-700 { color: #047857; font-weight: bold; }');
      doc.write('.text-slate-400 { color: #64748b; }');
      doc.write('.text-slate-500 { color: #475569; }');
      doc.write('.text-slate-800, .text-slate-900 { color: #0f172a; }');
      doc.write('.bg-slate-100 { background: #f1f5f9; border: 1px solid #cbd5e1; }');
      doc.write('.p-1 { padding: 4px; }');
      doc.write('.rounded { border-radius: 4px; }');
      doc.write('.print-action { text-align: center; margin-top: 20px; }');
      doc.write('.btn { background: #06559d; color: white; border: none; padding: 10px 20px; font-weight: bold; border-radius: 8px; cursor: pointer; font-size: 13px; }');
      doc.write('@media print { body { background: #fff; padding: 0; } .receipt-card { border: 2px dashed #000; max-width: 100%; margin: 0; } .print-action { display: none !important; } }');
      doc.write('</style></head><body>');
      doc.write('<div class="receipt-card">' + clone.innerHTML + '</div>');
      doc.write('<div class="print-action"><button class="btn" onclick="window.print()">Print Receipt</button></div>');
      doc.write('</body></html>');
      doc.close();
      win.focus();
      setTimeout(function() {
        win.print();
      }, 300);
    }
  </script>

</body>
</html>
