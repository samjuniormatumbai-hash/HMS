<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Resilient Multi-Port Database Singleton Fallback
if (!class_exists('Database')) {
    class Database {
        private static ?PDO $instance = null;
        public static function getConnection(): PDO {
            if (self::$instance !== null) return self::$instance;
            
            $candidates = [
                ['host' => '127.0.0.1', 'port' => 3306],
                ['host' => '127.0.0.1', 'port' => 3307],
                ['host' => '127.0.0.1', 'port' => 3308],
                ['host' => 'localhost', 'port' => 3306],
                ['host' => 'localhost', 'port' => 3307],
            ];

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            $lastEx = null;
            foreach ($candidates as $cand) {
                $h = $cand['host'];
                $p = $cand['port'];
                try {
                    $dsn = "mysql:host={$h};port={$p};dbname=ihms_db;charset=utf8mb4";
                    self::$instance = new PDO($dsn, 'root', '', $options);
                    break;
                } catch (PDOException $e) {
                    $lastEx = $e;
                    $msg = $e->getMessage();
                    if ($e->getCode() == 1049 || strpos($msg, '1049') !== false || strpos($msg, 'Unknown database') !== false) {
                        try {
                            $raw = new PDO("mysql:host={$h};port={$p};charset=utf8mb4", 'root', '', $options);
                            $raw->exec('CREATE DATABASE IF NOT EXISTS ihms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                            self::$instance = new PDO($dsn, 'root', '', $options);
                            break;
                        } catch (\Throwable $ex) {
                            $lastEx = $ex;
                        }
                    }
                }
            }

            if (self::$instance === null) {
                throw ($lastEx ?? new PDOException("SQLSTATE[HY000] [2002] MySQL service is offline. Please start MySQL in XAMPP."));
            }

            self::ensureSchema(self::$instance);
            return self::$instance;
        }

        private static function ensureSchema(PDO $pdo): void {
            static $ensured = false;
            if ($ensured) return;
            $ensured = true;
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS users (
                        user_id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(50) NOT NULL UNIQUE,
                        password_hash VARCHAR(255) NOT NULL,
                        full_name VARCHAR(100) NOT NULL,
                        email VARCHAR(100) NULL,
                        phone VARCHAR(25) NULL,
                        role ENUM('admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech', 'accountant', 'records_officer', 'patient') NOT NULL DEFAULT 'doctor',
                        department VARCHAR(80) NOT NULL DEFAULT 'General Outpatient',
                        is_active TINYINT(1) DEFAULT 1,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");

                $cnt = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                if ($cnt === 0) {
                    $seeds = [
                        ['admin', password_hash('admin123', PASSWORD_BCRYPT), 'Dr. Harrison Omondi', 'admin@hospital.org', '+254711000001', 'admin', 'Hospital Administration'],
                        ['dr_kamau', password_hash('doc123', PASSWORD_BCRYPT), 'Dr. Mercy Kamau, MD', 'kamau@hospital.org', '+254711000003', 'doctor', 'Clinical Consultations'],
                        ['nurse_faith', password_hash('nurse123', PASSWORD_BCRYPT), 'Faith Wanjiku, BSN', 'faith@hospital.org', '+254711000002', 'nurse', 'Emergency & Triage Station'],
                        ['pharm_otieno', password_hash('pharm123', PASSWORD_BCRYPT), 'Kevin Otieno, BPharm', 'otieno@hospital.org', '+254711000004', 'pharmacist', 'Main Hospital Pharmacy'],
                        ['lab_jane', password_hash('lab123', PASSWORD_BCRYPT), 'Jane Muthoni, MLS', 'muthoni@hospital.org', '+254711000005', 'lab_tech', 'Diagnostic Pathology Laboratory'],
                        ['acc_peter', password_hash('acc123', PASSWORD_BCRYPT), 'Peter Mwangi, CPA', 'mwangi@hospital.org', '+254711000006', 'accountant', 'Billing & Revenue Desk'],
                    ];
                    $st = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, email, phone, role, department) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    foreach ($seeds as $s) {
                        $st->execute($s);
                    }
                }
            } catch (\Throwable $e) {}
        }
    }
}

$flashError = '';
$flashSuccess = '';
$activeAuthTab = $_GET['mode'] ?? 'login';

// Process Staff Sign-In Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'staff_login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $flashError = 'Please provide both username and password.';
    } else {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user'] = [
                    'id' => (int)$user['user_id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role'],
                    'department' => $user['department'],
                    'email' => $user['email'],
                ];
                header("Location: index.php?tab=dashboard");
                exit;
            } else {
                $flashError = 'Invalid credentials or inactive staff account.';
            }
        } catch (\Throwable $e) {
            // Mock Fallback Login when MySQL is offline
            $mockCredentials = [
                'admin' => ['pass' => 'admin123', 'name' => 'Dr. Harrison Omondi', 'role' => 'admin', 'dept' => 'Hospital Administration'],
                'dr_kamau' => ['pass' => 'doc123', 'name' => 'Dr. Mercy Kamau, MD', 'role' => 'doctor', 'dept' => 'Clinical Consultations'],
                'nurse_faith' => ['pass' => 'nurse123', 'name' => 'Faith Wanjiku, BSN', 'role' => 'nurse', 'dept' => 'Emergency & Triage Station'],
                'pharm_otieno' => ['pass' => 'pharm123', 'name' => 'Kevin Otieno, BPharm', 'role' => 'pharmacist', 'dept' => 'Main Hospital Pharmacy'],
                'lab_jane' => ['pass' => 'lab123', 'name' => 'Jane Muthoni, MLS', 'role' => 'lab_tech', 'dept' => 'Diagnostic Pathology Laboratory'],
                'acc_peter' => ['pass' => 'acc123', 'name' => 'Peter Mwangi, CPA', 'role' => 'accountant', 'dept' => 'Billing & Revenue Desk'],
            ];

            if (isset($mockCredentials[$username]) && $mockCredentials[$username]['pass'] === $password) {
                $_SESSION['user'] = [
                    'id' => 1,
                    'username' => $username,
                    'full_name' => $mockCredentials[$username]['name'],
                    'role' => $mockCredentials[$username]['role'],
                    'department' => $mockCredentials[$username]['dept'],
                    'email' => "{$username}@hospital.org"
                ];
                header("Location: index.php?tab=dashboard");
                exit;
            } else {
                $flashError = 'Authentication failed. Please verify your credentials.';
            }
        }
    }
}

// Process New Staff Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'staff_register') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'doctor';
    $department = trim($_POST['department'] ?? 'General Clinical');
    $password = $_POST['password'] ?? '';

    if (empty($fullName) || empty($username) || empty($password)) {
        $flashError = 'Full name, username, and password are required.';
        $activeAuthTab = 'register';
    } else {
        try {
            $pdo = Database::getConnection();
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, email, phone, role, department) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hash, $fullName, $email, $phone, $role, $department]);
            $flashSuccess = "Staff account created for {$fullName}. You may now sign in.";
            $activeAuthTab = 'login';
        } catch (\Throwable $e) {
            $flashError = "Registration error: " . $e->getMessage();
            $activeAuthTab = 'register';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AfyaSmart IHMS - Staff Authentication &amp; Access Portal</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .font-mono { font-family: 'JetBrains Mono', monospace; }
  </style>
</head>
<body class="bg-[#edf5fc] min-h-screen text-slate-800 flex flex-col antialiased selection:bg-[#06559d] selection:text-white">

  <!-- TOP BRANDING BAR -->
  <header class="bg-[#0c477d] text-white px-6 py-3.5 border-b border-[#08355e] shadow-xs flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="h-9 w-9 rounded-xl bg-white text-[#0c477d] flex items-center justify-center font-black shadow-xs">
        <i class="fa-solid fa-hospital text-lg"></i>
      </div>
      <div>
        <h1 class="text-base font-bold tracking-tight">AfyaSmart IHMS</h1>
        <p class="text-[10px] text-blue-200">Your Health, Our Priority</p>
      </div>
    </div>
    
  </header>

  <!-- AUTHENTICATION CARD -->
  <main class="flex-1 max-w-md w-full mx-auto p-4 sm:p-6 flex flex-col justify-center">
    <div class="bg-white rounded-2xl border border-[#c7e0f6] shadow-sm p-6 space-y-5">
      <div class="text-center space-y-1">
        <h2 class="text-xl font-bold text-[#0c477d]">Hospital Staff Portal</h2>
        <p class="text-xs text-slate-500">Sign in with authorized clinical or administrative credentials</p>
      </div>

      <!-- Mode Selector -->
      <div class="grid grid-cols-2 p-1 bg-slate-100 rounded-xl text-xs font-bold text-center">
        <a href="login.php?mode=login" class="py-2 rounded-lg transition <?= $activeAuthTab === 'login' ? 'bg-[#0069c8] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">
          Sign In
        </a>
        <a href="login.php?mode=register" class="py-2 rounded-lg transition <?= $activeAuthTab === 'register' ? 'bg-[#0069c8] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">
          Register Account
        </a>
      </div>

      <!-- Alerts -->
      <?php if (!empty($flashError)): ?>
        <div class="p-3 bg-rose-50 border border-rose-200 text-rose-800 text-xs rounded-xl flex items-center gap-2">
          <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
          <span><?= htmlspecialchars($flashError) ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($flashSuccess)): ?>
        <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center gap-2">
          <i class="fa-solid fa-circle-check text-emerald-600"></i>
          <span><?= htmlspecialchars($flashSuccess) ?></span>
        </div>
      <?php endif; ?>

      <!-- 1. SIGN IN FORM -->
      <?php if ($activeAuthTab === 'login'): ?>
        <form action="login.php?mode=login" method="POST" class="space-y-4 text-xs">
          <input type="hidden" name="action" value="staff_login">

          <div>
            <label class="block font-bold text-slate-700 mb-1">Username / Staff ID:</label>
            <input type="text" name="username" required placeholder=" " class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[#0069c8] font-medium" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Password:</label>
            <div class="relative">
              <input type="password" id="loginPass" name="password" required placeholder="Enter password" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 pr-10 outline-none focus:border-[#0069c8]" />
              <button type="button" onclick="togglePass('loginPass', 'eyeIcon1')" class="absolute inset-y-0 right-0 px-3 text-slate-500 hover:text-slate-800">
                <i id="eyeIcon1" class="fa-solid fa-eye"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="w-full py-2.5 bg-[#0069c8] hover:bg-[#0057a6] text-white font-bold rounded-xl transition text-xs shadow-md flex items-center justify-center gap-2">
            <i class="fa-solid fa-right-to-bracket"></i>
            <span>Authenticate &amp; Open Workspace</span>
          </button>
        </form>

        

      <!--  REGISTER STAFF FORM -->
      <?php else: ?>
        <form action="login.php?mode=register" method="POST" class="space-y-3 text-xs">
          <input type="hidden" name="action" value="staff_register">

          <div>
            <label class="block font-bold text-slate-700 mb-1">Full Name &amp; Title:</label>
            <input type="text" name="full_name" required placeholder=" " class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[#0069c8]" />
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Username:</label>
              <input type="text" name="username" required placeholder=" " class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[#0069c8]" />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Role:</label>
              <select name="role" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[#0069c8]">
                <option value="doctor">Doctor</option>
                <option value="nurse">Nurse</option>
                <option value="pharmacist">Pharmacist</option>
                <option value="lab_tech">Lab Technician</option>
                <option value="accountant">Accountant / Cashier</option>
                <option value="admin">Administrator</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Email:</label>
              <input type="email" name="email" placeholder=" " class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[#0069c8]" />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Phone:</label>
              <input type="text" name="phone" placeholder=" " class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[#0069c8]" />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Password:</label>
            <div class="relative">
              <input type="password" id="regPass" name="password" required placeholder="Create secure password" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 pr-10 outline-none focus:border-[#0069c8]" />
              <button type="button" onclick="togglePass('regPass', 'eyeIcon2')" class="absolute inset-y-0 right-0 px-3 text-slate-500 hover:text-slate-800">
                <i id="eyeIcon2" class="fa-solid fa-eye"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition text-xs shadow-md flex items-center justify-center gap-2 mt-2">
            <i class="fa-solid fa-user-plus"></i>
            <span>Register Account</span>
          </button>
        </form>
      <?php endif; ?>
    </div>
  </main>

  <script>
    function togglePass(inputId, iconId) {
      const el = document.getElementById(inputId);
      const icon = document.getElementById(iconId);
      if (el.type === 'password') {
        el.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        el.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }
  </script>
</body>
</html>