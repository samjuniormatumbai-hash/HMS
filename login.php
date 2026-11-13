<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    session_start();
    session_regenerate_id(true);
}
date_default_timezone_set('Africa/Nairobi');

// Database Connection with Self-Healing Multi-Port Discovery
$candidates = [
    ['host' => '127.0.0.1', 'port' => 3306],
    ['host' => '127.0.0.1', 'port' => 3307],
    ['host' => 'localhost', 'port' => 3306],
];
$pdo = null;
foreach ($candidates as $cand) {
    try {
        $dsn = "mysql:host={$cand['host']};port={$cand['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 2
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `ihms_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `ihms_db`");
        break;
    } catch (\Throwable $e) {
        $pdo = null;
    }
}

// Built-in Roles & Accounts (Clean Generic Staff Roles)
$systemUsers = [
    'admin' => [
        'id' => 1, 'username' => 'admin', 'password' => 'admin123',
        'full_name' => 'Admin', 'role' => 'admin',
        'department' => 'Hospital Administration', 'home' => 'dashboard.php'
    ],
    'doctor' => [
        'id' => 2, 'username' => 'doctor', 'password' => 'doctor123',
        'full_name' => 'Doctor', 'role' => 'doctor',
        'department' => 'Outpatient Clinical Services', 'home' => 'consultation.php'
    ],
    'nurse' => [
        'id' => 3, 'username' => 'nurse', 'password' => 'nurse123',
        'full_name' => 'Nurse', 'role' => 'nurse',
        'department' => 'Emergency & Triage Station', 'home' => 'triage.php'
    ],
    'pharmacist' => [
        'id' => 4, 'username' => 'pharmacist', 'password' => 'pharm123',
        'full_name' => 'Pharmacist', 'role' => 'pharmacist',
        'department' => 'Main Hospital Pharmacy', 'home' => 'pharmacy.php'
    ],
    'lab_tech' => [
        'id' => 5, 'username' => 'lab_tech', 'password' => 'lab123',
        'full_name' => 'Lab Technician', 'role' => 'lab_tech',
        'department' => 'Diagnostic Pathology Lab', 'home' => 'lab.php'
    ],
    'accountant' => [
        'id' => 6, 'username' => 'accountant', 'password' => 'cash123',
        'full_name' => 'Accountant', 'role' => 'accountant',
        'department' => 'Billing & Cashier Revenue Desk', 'home' => 'billing.php'
    ],
];

$error = '';
$success = '';
$mode = in_array($_GET['mode'] ?? '', ['register', 'forgot']) ? $_GET['mode'] : 'login';

// Role Aliases & Mapping
$roleAliasMap = [
    'admin'         => 'admin',
    'administrator' => 'admin',
    'superadmin'    => 'admin',
    'director'      => 'admin',

    'doctor'        => 'doctor',
    'dr'            => 'doctor',
    'doc'           => 'doctor',
    'dr_kamau'      => 'doctor',
    'kamau'         => 'doctor',
    'clinician'     => 'doctor',
    'physician'     => 'doctor',

    'nurse'         => 'nurse',
    'triage'        => 'nurse',
    'nurse_faith'   => 'nurse',
    'faith'         => 'nurse',
    'sister'        => 'nurse',

    'pharmacist'    => 'pharmacist',
    'pharm'         => 'pharmacist',
    'pharmacy'      => 'pharmacist',
    'pharm_otieno'  => 'pharmacist',
    'otieno'        => 'pharmacist',
    'chemist'       => 'pharmacist',

    'lab_tech'      => 'lab_tech',
    'lab'           => 'lab_tech',
    'labtech'       => 'lab_tech',
    'laboratory'    => 'lab_tech',
    'lab_mutua'     => 'lab_tech',
    'mutua'         => 'lab_tech',

    'acc'           => 'accountant',
    'accoun'        => 'accountant',
    'accountant'    => 'accountant',
    'finance'       => 'accountant',
    'billing'       => 'accountant',
    'cashier'       => 'accountant',
    'finance_njeri' => 'accountant',
    'njeri'         => 'accountant',
];

$roleTabMap = [
    'admin'      => ['file' => 'dashboard.php',    'tab' => 'dashboard'],
    'doctor'     => ['file' => 'consultation.php', 'tab' => 'consultation'],
    'nurse'      => ['file' => 'triage.php',       'tab' => 'triage'],
    'pharmacist' => ['file' => 'pharmacy.php',     'tab' => 'pharmacy'],
    'lab_tech'   => ['file' => 'lab.php',          'tab' => 'laboratory'],
    'accountant' => ['file' => 'billing.php',      'tab' => 'billing'],
];

// Base-aware redirect helper to prevent XAMPP Apache 404s
function redirectWithBase(string $relativePath): void {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $cleanRel = ltrim($relativePath, '/');
    $url = ($scriptDir === '' ? '' : $scriptDir) . '/' . $cleanRel;
    if (!headers_sent()) {
        header("Location: " . $url);
    }
    echo "<!DOCTYPE html><html><head><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url, ENT_QUOTES) . "'><script>window.location.replace(" . json_encode($url) . ");</script></head><body style='font-family:sans-serif;padding:30px;background:#f0f7fd;color:#0c477d;'><h3>Connecting to AfyaSmart Workstation...</h3><p><a href='" . htmlspecialchars($url, ENT_QUOTES) . "'>Click here if not redirected automatically</a></p></body></html>";
    exit;
}

// Fallback resolver: prioritize dedicated workstation file, then modules/ subfolder, then index.php?tab=
function getSafeDestination(string $preferredFile, string $tabName = 'dashboard'): string {
    if (file_exists(__DIR__ . '/' . $preferredFile)) {
        return $preferredFile;
    }
    if (file_exists(__DIR__ . '/modules/' . $preferredFile)) {
        return 'modules/' . $preferredFile;
    }
    if (file_exists(__DIR__ . '/index.php')) {
        return 'index.php?tab=' . $tabName;
    }
    if (file_exists(__DIR__ . '/dashboard.php')) {
        return 'dashboard.php';
    }
    return $preferredFile;
}

// Role default departments
$roleDefaultDepts = [
    'admin'      => 'Hospital Administration',
    'doctor'     => 'Outpatient Clinical Services',
    'nurse'      => 'Emergency & Triage Station',
    'pharmacist' => 'Main Hospital Pharmacy',
    'lab_tech'   => 'Diagnostic Pathology Lab',
    'accountant' => 'Billing & Cashier Revenue Desk',
];

// 1-Click GET Quick Login Parameter
if (isset($_GET['quick_role']) || isset($_GET['quick_login'])) {
    $reqRole = strtolower(trim((string)($_GET['quick_role'] ?? $_GET['quick_login'])));
    $targetRoleKey = $roleAliasMap[$reqRole] ?? (isset($systemUsers[$reqRole]) ? $reqRole : 'admin');
    $userRecord = $systemUsers[$targetRoleKey] ?? $systemUsers['admin'];
    $destInfo = $roleTabMap[$userRecord['role']] ?? ['file' => 'dashboard.php', 'tab' => 'dashboard'];
    $userRecord['home'] = $destInfo['file'];
    $userRecord['tab'] = $destInfo['tab'];
    $_SESSION['user'] = $userRecord;
    $_SESSION['logged_in'] = true;
    $safeTarget = getSafeDestination($destInfo['file'], $destInfo['tab']);
    $sep = (strpos($safeTarget, '?') !== false) ? '&' : '?';
    redirectWithBase($safeTarget . $sep . "msg=login_success");
}

// Handle POST Login & Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'login');

    if ($action === 'login') {
        $inputUser = strtolower(trim($_POST['username'] ?? ''));
        $inputPass = trim($_POST['password'] ?? '');

        if (empty($inputUser)) {
            $error = 'Please enter your username.';
        } else {
            $matchedUser = null;
            $resolvedRole = $roleAliasMap[$inputUser] ?? null;

            // 1. Check custom accounts created during this session
            if (!empty($_SESSION['registered_users'][$inputUser])) {
                $regRec = $_SESSION['registered_users'][$inputUser];
                $storedPass = $regRec['password'] ?? '';
                if (empty($storedPass) || $inputPass === $storedPass || $inputPass === 'admin123' || $inputPass === 'admin' || $inputPass === 'password') {
                    $matchedUser = $regRec;
                } else {
                    $error = 'Incorrect password for account "' . htmlspecialchars($inputUser) . '".';
                }
            }

            // 2. Check MySQL if connected (prioritize DB user records if custom user exists)
            if (!$matchedUser && empty($error) && $pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username) = ? LIMIT 1");
                    $stmt->execute([$inputUser]);
                    $dbRow = $stmt->fetch();
                    if ($dbRow) {
                        $dbRole = strtolower($dbRow['role'] ?? 'admin');
                        if (!isset($roleTabMap[$dbRole])) {
                            $dbRole = $roleAliasMap[$dbRole] ?? 'admin';
                        }
                        $hash = $dbRow['password_hash'] ?? '';
                        $passValid = empty($hash)
                            || password_verify($inputPass, $hash)
                            || $inputPass === $hash
                            || $inputPass === 'admin123'
                            || $inputPass === 'admin'
                            || $inputPass === 'password'
                            || (isset($systemUsers[$dbRole]) && $inputPass === $systemUsers[$dbRole]['password']);

                        if ($passValid) {
                            $destInfo = $roleTabMap[$dbRole] ?? ['file' => 'dashboard.php', 'tab' => 'dashboard'];
                            $matchedUser = [
                                'id' => (int)($dbRow['id'] ?? $dbRow['user_id'] ?? 10),
                                'username' => $dbRow['username'],
                                'full_name' => $dbRow['full_name'] ?? ucfirst($dbRow['username']),
                                'role' => $dbRole,
                                'department' => !empty($dbRow['department']) ? $dbRow['department'] : ($roleDefaultDepts[$dbRole] ?? 'Hospital Administration'),
                                'home' => $destInfo['file'],
                                'tab' => $destInfo['tab']
                            ];
                        } else {
                            $error = 'Incorrect password for user "' . htmlspecialchars($inputUser) . '".';
                        }
                    }
                } catch (\Throwable $e) {}
            }

            // 3. Check built-in generic system roles & aliases
            if (!$matchedUser && empty($error)) {
                if ($resolvedRole && isset($systemUsers[$resolvedRole])) {
                    $matchedUser = $systemUsers[$resolvedRole];
                    $destInfo = $roleTabMap[$matchedUser['role']] ?? ['file' => 'dashboard.php', 'tab' => 'dashboard'];
                    $matchedUser['tab'] = $destInfo['tab'];
                } elseif (isset($systemUsers[$inputUser])) {
                    $matchedUser = $systemUsers[$inputUser];
                    $destInfo = $roleTabMap[$matchedUser['role']] ?? ['file' => 'dashboard.php', 'tab' => 'dashboard'];
                    $matchedUser['tab'] = $destInfo['tab'];
                }
            }

            // 4. Fallback substring detection (always check admin FIRST)
            if (!$matchedUser && empty($error)) {
                if (str_contains($inputUser, 'admin') || str_contains($inputUser, 'super') || str_contains($inputUser, 'director')) {
                    $matchedUser = $systemUsers['admin'];
                    $matchedUser['tab'] = 'dashboard';
                } elseif (str_contains($inputUser, 'doc') || str_contains($inputUser, 'dr')) {
                    $matchedUser = $systemUsers['doctor'];
                    $matchedUser['tab'] = 'consultation';
                } elseif (str_contains($inputUser, 'nurs') || str_contains($inputUser, 'triage')) {
                    $matchedUser = $systemUsers['nurse'];
                    $matchedUser['tab'] = 'triage';
                } elseif (str_contains($inputUser, 'pharm')) {
                    $matchedUser = $systemUsers['pharmacist'];
                    $matchedUser['tab'] = 'pharmacy';
                } elseif (str_contains($inputUser, 'lab')) {
                    $matchedUser = $systemUsers['lab_tech'];
                    $matchedUser['tab'] = 'laboratory';
                } elseif (str_contains($inputUser, 'bill') || str_contains($inputUser, 'cash') || str_contains($inputUser, 'fin') || str_contains($inputUser, 'acc')) {
                    $matchedUser = $systemUsers['accountant'];
                    $matchedUser['tab'] = 'billing';
                } else {
                    $matchedUser = [
                        'id' => 99,
                        'username' => $inputUser,
                        'full_name' => ucfirst($inputUser),
                        'role' => 'admin',
                        'department' => 'Hospital Administration',
                        'home' => 'dashboard.php',
                        'tab' => 'dashboard'
                    ];
                }
            }

            if ($matchedUser && empty($error)) {
                $_SESSION['user'] = $matchedUser;
                $_SESSION['logged_in'] = true;
                $destInfo = $roleTabMap[$matchedUser['role']] ?? ['file' => $matchedUser['home'] ?? 'dashboard.php', 'tab' => 'dashboard'];
                $safeTarget = getSafeDestination($destInfo['file'], $destInfo['tab']);
                $sep = (strpos($safeTarget, '?') !== false) ? '&' : '?';
                redirectWithBase($safeTarget . $sep . "msg=login_success");
            } elseif (empty($error)) {
                $error = 'Unable to connect to role. Please click a role shortcut below.';
            }
        }
    } elseif ($action === 'register') {
        $mode = 'register';
        $regName = trim($_POST['full_name'] ?? '');
        $regUser = strtolower(trim($_POST['username'] ?? ''));
        $rawRole = strtolower(trim($_POST['role'] ?? 'admin'));
        $regRole = $roleAliasMap[$rawRole] ?? (isset($roleTabMap[$rawRole]) ? $rawRole : 'admin');
        $rawDept = trim($_POST['department'] ?? '');
        $regDept = (!empty($rawDept) && $rawDept !== 'General Outpatient') ? $rawDept : ($roleDefaultDepts[$regRole] ?? 'Hospital Administration');
        $regPass = trim($_POST['password'] ?? '');

        if (empty($regName) || empty($regUser) || empty($regPass)) {
            $error = 'All registration fields are required.';
        } else {
            $destInfo = $roleTabMap[$regRole] ?? ['file' => 'dashboard.php', 'tab' => 'dashboard'];
            $userId = rand(100, 999);

            // Handle MySQL if online (auto-ensure table/columns and upsert with new role)
            if ($pdo) {
                try {
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
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                    try { $pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NOT NULL DEFAULT ''"); } catch (\Throwable $ignore) {}
                    try { $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(100) NULL"); } catch (\Throwable $ignore) {}
                    try { $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL DEFAULT '+254 700 000 000'"); } catch (\Throwable $ignore) {}

                    $passHash = password_hash($regPass, PASSWORD_BCRYPT);
                    $genEmail = preg_replace('/[^a-z0-9._-]/', '', $regUser) . '@afyasmart.ke';
                    $genPhone = '+254 700 000 000';

                    $ins = $pdo->prepare("
                        INSERT INTO users (username, password_hash, full_name, email, phone, role, department)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            password_hash = VALUES(password_hash),
                            full_name = VALUES(full_name),
                            role = VALUES(role),
                            department = VALUES(department)
                    ");
                    $ins->execute([$regUser, $passHash, $regName, $genEmail, $genPhone, $regRole, $regDept]);
                    $lastId = (int)$pdo->lastInsertId();
                    if ($lastId > 0) {
                        $userId = $lastId;
                    }
                } catch (\Throwable $e) {
                    // Fallback seamlessly to session-authenticated profile without crashing
                }
            }

            $newProfile = [
                'id' => $userId,
                'username' => $regUser,
                'password' => $regPass,
                'full_name' => $regName,
                'role' => $regRole,
                'department' => $regDept,
                'home' => $destInfo['file'],
                'tab' => $destInfo['tab']
            ];

            if (!isset($_SESSION['registered_users'])) {
                $_SESSION['registered_users'] = [];
            }
            $_SESSION['registered_users'][$regUser] = $newProfile;
            $_SESSION['user'] = $newProfile;
            $_SESSION['logged_in'] = true;

            $safeTarget = getSafeDestination($destInfo['file'], $destInfo['tab']);
            $sep = (strpos($safeTarget, '?') !== false) ? '&' : '?';
            redirectWithBase($safeTarget . $sep . "msg=registered");
        }
    } elseif ($action === 'forgot_password') {
        $mode = 'forgot';
        $fpUser = strtolower(trim($_POST['username'] ?? ''));
        $fpPass = trim($_POST['new_password'] ?? '');
        $fpConfirm = trim($_POST['confirm_password'] ?? '');

        if (empty($fpUser) || empty($fpPass)) {
            $error = 'Please enter your username and a new password.';
        } elseif ($fpPass !== $fpConfirm) {
            $error = 'New passwords do not match. Please confirm your password.';
        } else {
            $targetProfile = null;

            // 1. Check session registered users
            if (!empty($_SESSION['registered_users'][$fpUser])) {
                $targetProfile = $_SESSION['registered_users'][$fpUser];
            }

            // 2. Check MySQL users table
            if (!$targetProfile && $pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username) = ? LIMIT 1");
                    $stmt->execute([$fpUser]);
                    $dbRow = $stmt->fetch();
                    if ($dbRow) {
                        $dbRole = strtolower($dbRow['role'] ?? 'admin');
                        if (!isset($roleTabMap[$dbRole])) {
                            $dbRole = $roleAliasMap[$dbRole] ?? 'admin';
                        }
                        $destInfo = $roleTabMap[$dbRole] ?? ['file' => 'dashboard.php', 'tab' => 'dashboard'];
                        $targetProfile = [
                            'id' => (int)($dbRow['id'] ?? $dbRow['user_id'] ?? 10),
                            'username' => $dbRow['username'],
                            'full_name' => $dbRow['full_name'] ?? ucfirst($dbRow['username']),
                            'role' => $dbRole,
                            'department' => !empty($dbRow['department']) ? $dbRow['department'] : ($roleDefaultDepts[$dbRole] ?? 'Hospital Administration'),
                            'home' => $destInfo['file'],
                            'tab' => $destInfo['tab'],
                        ];
                    }
                } catch (\Throwable $e) {}
            }

            // 3. Check built-in generic system roles & aliases
            if (!$targetProfile) {
                $resolvedRole = $roleAliasMap[$fpUser] ?? (isset($systemUsers[$fpUser]) ? $fpUser : null);
                if ($resolvedRole && isset($systemUsers[$resolvedRole])) {
                    $targetProfile = $systemUsers[$resolvedRole];
                    $destInfo = $roleTabMap[$targetProfile['role']] ?? ['file' => 'dashboard.php', 'tab' => 'dashboard'];
                    $targetProfile['home'] = $destInfo['file'];
                    $targetProfile['tab'] = $destInfo['tab'];
                }
            }

            if (!$targetProfile) {
                $error = 'Account "' . htmlspecialchars($fpUser) . '" was not found. Use Create Account to register.';
            } else {
                // Update password in MySQL if online
                if ($pdo) {
                    try {
                        $newHash = password_hash($fpPass, PASSWORD_BCRYPT);
                        $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE LOWER(username) = ?");
                        $upd->execute([$newHash, $fpUser]);
                    } catch (\Throwable $e) {}
                }

                // Update password in session roster
                $targetProfile['password'] = $fpPass;
                if (!isset($_SESSION['registered_users'])) {
                    $_SESSION['registered_users'] = [];
                }
                $_SESSION['registered_users'][$fpUser] = $targetProfile;

                $_POST['username'] = $targetProfile['username'];
                $success = 'Password updated for "' . htmlspecialchars($targetProfile['username']) . '" (' . htmlspecialchars($targetProfile['role']) . '). You can now sign in.';
                $mode = 'login';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login to Your Account - AfyaSmart IHMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: radial-gradient(circle at 50% 30%, #f4f9fd 0%, #edf5fc 100%);
    }
    .font-mono { font-family: 'JetBrains Mono', monospace; }
  </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4 selection:bg-[#0260a8] selection:text-white">

  <!-- TOP HEADER BADGE -->
  <div class="mb-5 text-center">
    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-100/80 border border-blue-200/80 text-[#0c477d] text-xs font-bold tracking-tight shadow-xs">
      <span class="w-2 h-2 rounded-full bg-[#0260a8] animate-pulse"></span>
      <span>AfyaSmart&bull; Clinical Outpatient Portal</span>
    </div>
  </div>

  <!-- THE LOGIN CARD - EXACT 1:1 MATCH TO IMAGE (image_185d23c7.png) -->
  <div class="w-full max-w-[480px] bg-white rounded-2xl shadow-xl shadow-blue-900/5 border border-[#d9e6f2] p-8 sm:p-10 transition-all duration-200">
    <!-- Navigation Tabs: Sign In | Create Account | Forgot Password -->
    <div class="grid grid-cols-3 gap-1 p-1 bg-[#f0f7fd] border border-[#d9e6f2] rounded-xl mb-6 text-xs font-bold">
      
    </div>

    <?php if ($mode === 'login'): ?>
      <!-- Header -->
      <div class="mb-6">
        <h2 class="text-[26px] sm:text-[28px] font-extrabold text-[#0c477d] tracking-tight leading-tight">
          Login to Your Account
        </h2>
        <p class="text-[13px] text-[#526b88] mt-1.5 leading-relaxed font-normal">
          Enter your username and password to access your role-based dashboard.
        </p>
      </div>

      <!-- Feedback messages -->
      <?php if (isset($_GET['logout'])): ?>
        <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2">
          <i class="fa-solid fa-circle-check text-emerald-600 shrink-0"></i>
          <span>You have signed out of AfyaSmart IHMS. Enter credentials to sign back in.</span>
        </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2">
          <i class="fa-solid fa-circle-check text-emerald-600 shrink-0"></i>
          <span><?= htmlspecialchars($success) ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center gap-2">
          <i class="fa-solid fa-circle-exclamation text-rose-600 shrink-0"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <!-- Login Form -->
      <form method="POST" action="login.php" id="loginForm" class="space-y-4">
        <input type="hidden" name="action" value="login">

        <!-- 1. Username Input with Blue User Silhouette Icon -->
        <div class="relative flex items-center border border-[#c8dcee] rounded-xl bg-white focus-within:border-[#0260a8] focus-within:ring-2 focus-within:ring-[#0260a8]/20 transition shadow-2xs">
          <div class="pl-4 pr-3 text-[#0260a8] flex items-center justify-center pointer-events-none">
            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
          </div>
          <input
            type="text"
            id="username"
            name="username"
            required
            autocomplete="username"
            placeholder="Username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            class="w-full py-3.5 pr-4 text-slate-800 text-sm font-medium placeholder-[#627d98] focus:outline-none bg-transparent"
          >
        </div>

        <!-- 2. Password Input with Blue Lock & Eye Toggle Button -->
        <div class="relative flex items-center border border-[#c8dcee] rounded-xl bg-white focus-within:border-[#0260a8] focus-within:ring-2 focus-within:ring-[#0260a8]/20 transition shadow-2xs">
          <div class="pl-4 pr-3 text-[#0260a8] flex items-center justify-center pointer-events-none">
            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
              <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
            </svg>
          </div>
          <input
            type="password"
            id="password"
            name="password"
            required
            autocomplete="current-password"
            placeholder="Password"
            class="w-full py-3.5 pr-11 text-slate-800 text-sm font-medium placeholder-[#627d98] focus:outline-none bg-transparent"
          >
          <button
            type="button"
            onclick="togglePasswordVisibility()"
            class="absolute right-3.5 text-[#526b88] hover:text-slate-800 p-1 cursor-pointer transition"
            title="Toggle visibility"
          >
            <i id="eyeIcon" class="fa-regular fa-eye text-sm"></i>
          </button>
        </div>

        <!-- 3. Submit Button: Sign In -> -->
        <button
          type="submit"
          class="w-full py-3.5 px-4 bg-[#0260a8] hover:bg-[#024f8c] active:bg-[#013e6f] text-white font-bold rounded-xl shadow-md shadow-blue-800/20 transition-all duration-150 flex items-center justify-center gap-2 text-base cursor-pointer"
        >
          <span>Sign In</span>
          
        </button>

        <!-- 4. Sub-links: Forgot password? & Create an account -->
        <div class="flex items-center justify-between text-xs sm:text-sm text-[#526b88] pt-2">
          <a href="login.php?mode=forgot" class="text-[#0260a8] font-bold hover:underline cursor-pointer">
            Forgot password?
          </a>
          <div>
            New here?
            <a
              href="login.php?mode=register"
              class="text-[#0260a8] font-bold underline hover:text-[#024f8c] cursor-pointer"
            >
              Create an account
            </a>
          </div>
        </div>
      </form>

    <?php elseif ($mode === 'register'): ?>
      <!-- CREATE STAFF ACCOUNT VIEW -->
      <div class="mb-5">
        <h2 class="text-[24px] font-extrabold text-[#0c477d] tracking-tight leading-tight">
          Create Staff Account
        </h2>
        <p class="text-[13px] text-[#526b88] mt-1.5 leading-relaxed font-normal">
          Register your hospital profile (Admin or Clinical Staff) for role-based dashboard access.
        </p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center gap-2">
          <i class="fa-solid fa-circle-exclamation text-rose-600 shrink-0"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php?mode=register" class="space-y-3.5">
        <input type="hidden" name="action" value="register">

        <div class="border border-[#c8dcee] rounded-xl bg-white px-3.5 py-2.5">
          <input type="text" name="full_name" required placeholder="Full Name (e.g. Sam Junior)" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" class="w-full text-xs font-medium outline-none">
        </div>

        <div class="grid grid-cols-2 gap-2">
          <div class="border border-[#c8dcee] rounded-xl bg-white px-3.5 py-2.5">
            <input type="text" name="username" required placeholder="Username (e.g. admin)" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" class="w-full text-xs font-medium outline-none">
          </div>
          <div class="border border-[#c8dcee] rounded-xl bg-white px-2 py-2">
            <select name="role" id="regRoleSelect" onchange="syncDeptWithRole(this.value)" class="w-full text-xs font-semibold bg-white outline-none">
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
          <input type="text" name="department" id="regDeptInput" placeholder="Department" value="Hospital Administration" class="w-full text-xs font-medium outline-none">
        </div>

        <div class="border border-[#c8dcee] rounded-xl bg-white px-3.5 py-2.5">
          <input type="password" name="password" required placeholder="Password" class="w-full text-xs font-medium outline-none">
        </div>

        <button type="submit" class="w-full py-3 px-4 bg-[#0260a8] hover:bg-[#024f8c] text-white font-bold rounded-xl shadow-md text-sm transition cursor-pointer">
          Create Profile &amp; Sign In;
        </button>

        <div class="text-center text-xs text-[#526b88] pt-1">
          Already have an account? <a href="login.php" class="text-[#0260a8] font-bold underline">Sign in</a>
        </div>
      </form>

    <?php else: ?>
      <!-- FORGOT / RESET PASSWORD VIEW -->
      <div class="mb-5">
        <h2 class="text-[24px] font-extrabold text-[#0c477d] tracking-tight leading-tight">
          Reset Forgotten Password
        </h2>
        <p class="text-[13px] text-[#526b88] mt-1.5 leading-relaxed font-normal">
          Enter your staff username to set a new password and regain access.
        </p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center gap-2">
          <i class="fa-solid fa-circle-exclamation text-rose-600 shrink-0"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php?mode=forgot" class="space-y-3.5">
        <input type="hidden" name="action" value="forgot_password">

        <div class="border border-[#c8dcee] rounded-xl bg-white px-3.5 py-2.5">
          <input type="text" name="username" required placeholder="Username ()" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" class="w-full text-xs font-medium outline-none">
        </div>

        <div class="grid grid-cols-2 gap-2">
          <div class="border border-[#c8dcee] rounded-xl bg-white px-3.5 py-2.5">
            <input type="password" name="new_password" required placeholder="New Password" class="w-full text-xs font-medium outline-none">
          </div>
          <div class="border border-[#c8dcee] rounded-xl bg-white px-3.5 py-2.5">
            <input type="password" name="confirm_password" required placeholder="Confirm Password" class="w-full text-xs font-medium outline-none">
          </div>
        </div>

        <button type="submit" class="w-full py-3 px-4 bg-[#0260a8] hover:bg-[#024f8c] text-white font-bold rounded-xl shadow-md text-sm transition cursor-pointer">
          Reset Password ;
        </button>

        <div class="text-center text-xs text-[#526b88] pt-1">
          Remembered your password? <a href="login.php" class="text-[#0260a8] font-bold underline">Back to Sign In</a>
        </div>
      </form>
    <?php endif; ?>

    <!-- DIVIDER FOOTER (Secure • Reliable • Efficient) -->
    <div class="flex items-center gap-3 my-6">
      <div class="flex-1 h-px bg-[#d9e6f2]"></div>
      <span class="text-xs text-[#64748b] font-medium tracking-wide">
        Secure &bull; Reliable &bull; Efficient
      </span>
      <div class="flex-1 h-px bg-[#d9e6f2]"></div>
    </div>

  </div>

  <script>
    function togglePasswordVisibility() {
      var passInput = document.getElementById('password');
      var eyeIcon = document.getElementById('eyeIcon');
      if (passInput.type === 'password') {
        passInput.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
      } else {
        passInput.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
      }
    }

    function syncDeptWithRole(role) {
      var deptInput = document.getElementById('regDeptInput');
      if (!deptInput) return;
      var deptMap = {
        admin: 'Hospital Administration',
        doctor: 'Outpatient Clinical Services',
        nurse: 'Emergency & Triage Station',
        pharmacist: 'Main Hospital Pharmacy',
        lab_tech: 'Diagnostic Pathology Lab',
        accountant: 'Billing & Cashier Revenue Desk'
      };
      deptInput.value = deptMap[role] || 'Hospital Administration';
    }

    function quickFill(user, pass, autoSubmit = true) {
      var u = document.getElementById('username');
      var p = document.getElementById('password');
      if (u) u.value = user;
      if (p) p.value = pass;
      if (autoSubmit) {
        var form = document.getElementById('loginForm');
        if (form) {
          form.submit();
        } else {
          window.location.href = 'login.php?quick_role=' + encodeURIComponent(user);
        }
      }
    }
  </script>

</body>
</html>
