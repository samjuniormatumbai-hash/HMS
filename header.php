<?php
/**
 * =====================================================================
 * AFYASMART INTEGRATED HOSPITAL MANAGEMENT SYSTEM (IHMS)
 * Module: Global Header & Navigation Template
 * File: header.php (Save to C:\xampp\htdocs\HMS\header.php)
 * Author: Sam Junior Matumbayi (Reg: DSE-02-0171/2025)
 * Institution: Zetech University - Department of Computer Science
 * =====================================================================
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Africa/Nairobi');

// 1. Database Connection Discovery
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
} catch (\Throwable $e) {
    $pdo = null;
    $isDbConnected = false;
}

// 2. Helper Functions
function safe_str($v, $d = '') { return (isset($v) && !is_array($v)) ? trim((string)$v) : $d; }
function safe_int($v, $d = 0) { return (isset($v) && is_numeric($v)) ? (int)$v : $d; }
function safe_float($v, $d = 0.0) { return (isset($v) && is_numeric($v)) ? (float)$v : $d; }

// Smart URL resolver: resolves between root files, modules/ files, and index.php?tab=
function ihms_url(string $file, string $tab, array $params = []): string {
    $currentInModules = (basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'modules');
    $query = !empty($params) ? '&' . http_build_query($params) : '';

    // 1. Direct file in current folder
    if (file_exists(__DIR__ . '/' . $file)) {
        return $file . (!empty($params) ? '?' . http_build_query($params) : '');
    }
    // 2. Subfolder modules/
    if (!$currentInModules && file_exists(__DIR__ . '/modules/' . $file)) {
        return 'modules/' . $file . (!empty($params) ? '?' . http_build_query($params) : '');
    }
    // 3. Parent directory if currently running inside modules/
    if ($currentInModules && file_exists(dirname(__DIR__) . '/' . $file)) {
        return '../' . $file . (!empty($params) ? '?' . http_build_query($params) : '');
    }
    // 4. Master index.php router fallback
    $prefix = $currentInModules ? '../' : '';
    return $prefix . 'index.php?tab=' . urlencode($tab) . $query;
}

// Base-aware redirect helper to prevent XAMPP Apache 404s
function headerRedirectWithBase(string $relativePath): void {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $cleanRel = ltrim($relativePath, '/');
    $url = ($scriptDir === '' ? '' : $scriptDir) . '/' . $cleanRel;
    if (!headers_sent()) {
        header("Location: " . $url);
    }
    echo "<!DOCTYPE html><html><head><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url, ENT_QUOTES) . "'><script>window.location.replace(" . json_encode($url) . ");</script></head><body style='font-family:sans-serif;padding:30px;background:#f0f7fd;color:#0c477d;'><h3>Connecting to Workstation...</h3><p><a href='" . htmlspecialchars($url, ENT_QUOTES) . "'>Click here if not redirected automatically</a></p></body></html>";
    exit;
}

// Resolve role workstation file with graceful fallback
function resolveRoleWorkstation(string $role): string {
    $roleMap = [
        'admin'      => ['file' => 'dashboard.php',    'tab' => 'dashboard'],
        'doctor'     => ['file' => 'consultation.php', 'tab' => 'consultation'],
        'nurse'      => ['file' => 'triage.php',       'tab' => 'triage'],
        'pharmacist' => ['file' => 'pharmacy.php',     'tab' => 'pharmacy'],
        'lab_tech'   => ['file' => 'lab.php',          'tab' => 'laboratory'],
        'accountant' => ['file' => 'billing.php',      'tab' => 'billing'],
    ];
    $target = $roleMap[$role] ?? ['file' => 'dashboard.php', 'tab' => 'dashboard'];
    return ihms_url($target['file'], $target['tab']);
}

// 3. Staff Role Switcher Handler
$systemRoles = [
    'admin'      => ['id' => 1, 'username' => 'admin',      'full_name' => 'Admin',          'role' => 'admin',      'department' => 'Hospital Administration',       'home' => 'dashboard.php'],
    'doctor'     => ['id' => 2, 'username' => 'doctor',     'full_name' => 'Doctor',         'role' => 'doctor',     'department' => 'Outpatient Clinical Services',  'home' => 'consultation.php'],
    'nurse'      => ['id' => 3, 'username' => 'nurse',      'full_name' => 'Nurse',          'role' => 'nurse',      'department' => 'Emergency & Triage Station',    'home' => 'triage.php'],
    'pharmacist' => ['id' => 4, 'username' => 'pharmacist', 'full_name' => 'Pharmacist',     'role' => 'pharmacist', 'department' => 'Main Hospital Pharmacy',        'home' => 'pharmacy.php'],
    'lab_tech'   => ['id' => 5, 'username' => 'lab_tech',   'full_name' => 'Lab Technician', 'role' => 'lab_tech',   'department' => 'Diagnostic Pathology Lab',      'home' => 'lab.php'],
    'accountant' => ['id' => 6, 'username' => 'accountant', 'full_name' => 'Accountant',     'role' => 'accountant', 'department' => 'Billing & Cashier Revenue Desk', 'home' => 'billing.php'],
];

if (isset($_GET['switch_role'])) {
    $currentRole = $_SESSION['user']['role'] ?? '';
    // SECURITY: Only Admin is permitted to see and switch other staff member roles!
    if ($currentRole !== 'admin') {
        $dest = resolveRoleWorkstation($currentRole ?: 'doctor');
        $sep = (strpos($dest, '?') !== false) ? '&' : '?';
        headerRedirectWithBase($dest . $sep . "msg=access_restricted_role_switch");
        exit;
    }
    $rawTarget = safe_str($_GET['switch_role']);
    $aliasMap = [
        'admin' => 'admin', 'administrator' => 'admin', 'superadmin' => 'admin',
        'doctor' => 'doctor', 'dr' => 'doctor', 'doc' => 'doctor', 'dr_kamau' => 'doctor', 'clinician' => 'doctor',
        'nurse' => 'nurse', 'triage' => 'nurse', 'nurse_faith' => 'nurse', 'sister' => 'nurse',
        'pharmacist' => 'pharmacist', 'pharm' => 'pharmacist', 'pharmacy' => 'pharmacist', 'pharm_otieno' => 'pharmacist',
        'lab_tech' => 'lab_tech', 'lab' => 'lab_tech', 'labtech' => 'lab_tech', 'laboratory' => 'lab_tech', 'lab_mutua' => 'lab_tech',
        'accountant' => 'accountant', 'finance' => 'accountant', 'billing' => 'accountant', 'cashier' => 'accountant', 'finance_njeri' => 'accountant',
    ];
    $target = $aliasMap[$rawTarget] ?? $rawTarget;
    if (isset($systemRoles[$target])) {
        $_SESSION['user'] = $systemRoles[$target];
        $_SESSION['logged_in'] = true;
        $dest = resolveRoleWorkstation($target);
        $sep = (strpos($dest, '?') !== false) ? '&' : '?';
        headerRedirectWithBase($dest . $sep . "msg=role_switched");
    }
}

// 4. Instant Logout Handler
if ((isset($_GET['action']) && $_GET['action'] === 'logout') || isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    headerRedirectWithBase("login.php?logout=1");
    exit;
}

// Require active authenticated session; never silently auto-login as admin when logged out
if (empty($_SESSION['user']) || empty($_SESSION['user']['role']) || empty($_SESSION['logged_in'])) {
    $loginUrl = file_exists(__DIR__ . '/login.php') ? 'login.php' : (file_exists(dirname(__DIR__) . '/login.php') ? '../login.php' : 'index.php?tab=login');
    headerRedirectWithBase($loginUrl);
    exit;
}
if (!isset($systemRoles[$_SESSION['user']['role']])) {
    $_SESSION['user']['role'] = 'admin';
}
$currentUser = $_SESSION['user'];

// Strict Role-Based Access Control (RBAC):
// Each staff member must only see his own role workstation (e.g. lab_tech -> lab.php, accountant -> billing.php).
// Only Admin is authorized to see and oversee the work of all staff members (all modules 1 to 10).
$roleAllowedPages = [
    'admin'      => ['dashboard.php', 'registration.php', 'triage.php', 'consultation.php', 'lab.php', 'pharmacy.php', 'billing.php', 'receipt.php', 'audit.php', 'settings.php'],
    'doctor'     => ['consultation.php'],
    'nurse'      => ['triage.php', 'registration.php'],
    'lab_tech'   => ['lab.php'],
    'pharmacist' => ['pharmacy.php'],
    'accountant' => ['billing.php', 'receipt.php'],
];

$roleHomePages = [
    'admin'      => 'dashboard.php',
    'doctor'     => 'consultation.php',
    'nurse'      => 'triage.php',
    'lab_tech'   => 'lab.php',
    'pharmacist' => 'pharmacy.php',
    'accountant' => 'billing.php',
];

$allowedPagesForUser = $roleAllowedPages[$currentUser['role']] ?? $roleAllowedPages['admin'];
$homePageForUser = $roleHomePages[$currentUser['role']] ?? 'dashboard.php';

// Security boundary check: Redirect non-admins if attempting to load an unpermitted page
$thisScript = basename($_SERVER['PHP_SELF'] ?? '');
if ($currentUser['role'] !== 'admin' && !empty($thisScript) && !in_array($thisScript, ['index.php', 'header.php', 'footer.php', 'login.php', 'logout.php', 'db_connect.php']) && !in_array($thisScript, $allowedPagesForUser)) {
    $dest = resolveRoleWorkstation($currentUser['role']);
    $sep = (strpos($dest, '?') !== false) ? '&' : '?';
    headerRedirectWithBase($dest . $sep . "msg=access_restricted_to_workstation");
    exit;
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

// Theme Handling
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = safe_str($_GET['theme'], 'default');
}
$isDarkBlue = (isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark_blue');

// Facility Settings State
if (!isset($_SESSION['facility_settings']) || empty($_SESSION['facility_settings']['hospital_name']) || strpos($_SESSION['facility_settings']['hospital_name'], 'CareFlow') !== false || !empty($_SESSION['facility_settings']['facility_level']) || (($_SESSION['facility_settings']['moh_code'] ?? '') === '14892')) {
    $_SESSION['facility_settings'] = [
        'hospital_name'    => 'AfyaSmart Integrated Health Centre',
        'moh_code'         => '',
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
        $returnPage = safe_str($_REQUEST['return_page'] ?? null, 'dashboard.php');
        header("Location: " . $returnPage . "?patient_id={$pid}&msg=stage_transitioned");
        exit;
    }
}

// Add Active Patient to Lifecycle Handler (Used by dashboard.php quick-add modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && safe_str($_POST['action'] ?? null) === 'add_lifecycle_patient') {
    $pName  = safe_str($_POST['full_name'] ?? null, 'New Active Patient');
    $phone  = safe_str($_POST['phone'] ?? null, '+254 7' . rand(10000000, 99999999));
    $gender = safe_str($_POST['gender'] ?? null, 'Male');
    $scheme = safe_str($_POST['payment_scheme'] ?? null, 'SHA');
    $stage  = safe_str($_POST['start_stage'] ?? null, 'Registered');
    $allowedStages = ['Registered', 'Triaged', 'In_Consultation', 'Lab_Pending', 'Pharmacy_Pending', 'Billing_Pending', 'Discharged'];
    if (!in_array($stage, $allowedStages)) { $stage = 'Registered'; }
    $newId  = count($_SESSION['patients']) + 30;
    $opd    = 'OPD-2025-' . str_pad($newId, 3, '0', STR_PAD_LEFT);

    array_unshift($_SESSION['patients'], [
        'patient_id' => $newId, 'opd_number' => $opd, 'full_name' => $pName,
        'gender' => $gender, 'phone' => $phone, 'payment_scheme' => $scheme, 'current_status' => $stage
    ]);

    $returnPage = safe_str($_POST['return_page'] ?? null, 'dashboard.php');
    header("Location: " . $returnPage . "?patient_id={$newId}&msg=lifecycle_patient_added");
    exit;
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

// Active Page Identification
$thisPage = basename($_SERVER['PHP_SELF']);
if ($thisPage === 'index.php') {
    $thisPage = 'dashboard.php';
}
$currentTab = safe_str($_GET['tab'] ?? '');
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
      <a href="<?= ihms_url('registration.php', 'registration') ?>" class="h-9 w-9 bg-white rounded-lg flex items-center justify-center shadow-xs hover:scale-105 transition">
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
              <a href="?switch_role=admin" class="flex items-center gap-2.5 px-3 py-2 rounded-xl transition <?= $currentUser['role'] === 'admin' ? 'bg-blue-50 text-[#06559d] font-bold' : 'hover:bg-slate-50 text-slate-700' ?>">
                <span class="w-7 h-7 rounded-lg bg-[#06559d] text-white flex items-center justify-center font-black text-[11px]">AD</span>
                <div><span class="font-bold">Admin</span><span class="text-[10px] text-slate-400 block">Hospital Administration</span></div>
              </a>
              <a href="?switch_role=doctor" class="flex items-center gap-2.5 px-3 py-2 rounded-xl transition <?= $currentUser['role'] === 'doctor' ? 'bg-blue-50 text-[#06559d] font-bold' : 'hover:bg-slate-50 text-slate-700' ?>">
                <span class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-black text-[11px]">DR</span>
                <div><span class="font-bold">Doctor</span><span class="text-[10px] text-slate-400 block">Outpatient Consultation</span></div>
              </a>
              <a href="?switch_role=nurse" class="flex items-center gap-2.5 px-3 py-2 rounded-xl transition <?= $currentUser['role'] === 'nurse' ? 'bg-blue-50 text-[#06559d] font-bold' : 'hover:bg-slate-50 text-slate-700' ?>">
                <span class="w-7 h-7 rounded-lg bg-cyan-600 text-white flex items-center justify-center font-black text-[11px]">RN</span>
                <div><span class="font-bold">Nurse</span><span class="text-[10px] text-slate-400 block">Triage Desk Station</span></div>
              </a>
              <a href="?switch_role=pharmacist" class="flex items-center gap-2.5 px-3 py-2 rounded-xl transition <?= $currentUser['role'] === 'pharmacist' ? 'bg-blue-50 text-[#06559d] font-bold' : 'hover:bg-slate-50 text-slate-700' ?>">
                <span class="w-7 h-7 rounded-lg bg-amber-600 text-white flex items-center justify-center font-black text-[11px]">PH</span>
                <div><span class="font-bold">Pharmacist</span><span class="text-[10px] text-slate-400 block">Dispensary &amp; Stock</span></div>
              </a>
              <a href="?switch_role=lab_tech" class="flex items-center gap-2.5 px-3 py-2 rounded-xl transition <?= $currentUser['role'] === 'lab_tech' ? 'bg-blue-50 text-[#06559d] font-bold' : 'hover:bg-slate-50 text-slate-700' ?>">
                <span class="w-7 h-7 rounded-lg bg-purple-600 text-white flex items-center justify-center font-black text-[11px]">LT</span>
                <div><span class="font-bold">Lab Tech</span><span class="text-[10px] text-slate-400 block">Diagnostic Laboratory</span></div>
              </a>
              <a href="?switch_role=accountant" class="flex items-center gap-2.5 px-3 py-2 rounded-xl transition <?= $currentUser['role'] === 'accountant' ? 'bg-blue-50 text-[#06559d] font-bold' : 'hover:bg-slate-50 text-slate-700' ?>">
                <span class="w-7 h-7 rounded-lg bg-teal-600 text-white flex items-center justify-center font-black text-[11px]">AC</span>
                <div><span class="font-bold">Accountant</span><span class="text-[10px] text-slate-400 block">Billing &amp; Revenue POS</span></div>
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
      <a href="<?= $thisPage ?>?theme=<?= $isDarkBlue ? 'default' : 'dark_blue' ?>&patient_id=<?= $activePatientId ?>" class="text-xs px-3 py-1.5 rounded-xl border <?= $isDarkBlue ? 'border-sky-400 bg-sky-950/70 text-sky-200' : 'border-blue-400/40 bg-blue-800/80 hover:bg-blue-800 text-white' ?> font-bold transition flex items-center gap-1.5 shadow-xs" title="Switch Theme Color">
        <i class="fa-solid <?= $isDarkBlue ? 'fa-sun text-amber-300' : 'fa-moon text-sky-200' ?>"></i>
        <span class="hidden md:inline"><?= $isDarkBlue ? 'Classic Blue' : 'Dark Blue' ?></span>
      </a>

      <!-- Sign Out -->
      <?php
        $logoutDest = file_exists(__DIR__ . '/logout.php') ? 'logout.php' : (file_exists(dirname(__DIR__) . '/logout.php') ? '../logout.php' : '?action=logout');
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

        <?php if ($currentUser['role'] === 'admin' || in_array('dashboard.php', $allowedPagesForUser)): ?>
          <!-- 1. Hospital Dashboard -->
          <a href="<?= ihms_url('dashboard.php', 'dashboard') ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= in_array($thisPage, ['dashboard.php', 'index.php']) && ($currentTab === 'dashboard' || empty($_GET['tab'])) ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= in_array($thisPage, ['dashboard.php', 'index.php']) && ($currentTab === 'dashboard' || empty($_GET['tab'])) ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">1</span>
            <i class="fa-solid fa-chart-pie text-sm w-4 text-center <?= in_array($thisPage, ['dashboard.php', 'index.php']) && ($currentTab === 'dashboard' || empty($_GET['tab'])) ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Hospital Dashboard</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('registration.php', $allowedPagesForUser)): ?>
          <!-- 2. Patient Registration -->
          <a href="<?= ihms_url('registration.php', 'registration', ['patient_id' => $activePatientId]) ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $thisPage === 'registration.php' || $currentTab === 'registration' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $thisPage === 'registration.php' || $currentTab === 'registration' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">2</span>
            <i class="fa-solid fa-user text-sm w-4 text-center <?= $thisPage === 'registration.php' || $currentTab === 'registration' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Patient Registration</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('triage.php', $allowedPagesForUser)): ?>
          <!-- 3. Triage Desk -->
          <a href="<?= ihms_url('triage.php', 'triage', ['patient_id' => $activePatientId]) ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $thisPage === 'triage.php' || $currentTab === 'triage' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $thisPage === 'triage.php' || $currentTab === 'triage' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">3</span>
            <i class="fa-solid fa-stethoscope text-sm w-4 text-center <?= $thisPage === 'triage.php' || $currentTab === 'triage' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Triage Desk</span>
            <span class="ml-auto text-[10px] px-2 py-0.5 rounded-full <?= $thisPage === 'triage.php' || $currentTab === 'triage' ? 'bg-white text-[#0260a8]' : 'bg-[#0260a8] text-white' ?> font-bold">
              <?= count($_SESSION['patients']) ?>
            </span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('consultation.php', $allowedPagesForUser)): ?>
          <!-- 4. Consultation Workstation -->
          <a href="<?= ihms_url('consultation.php', 'consultation', ['patient_id' => $activePatientId]) ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $thisPage === 'consultation.php' || $currentTab === 'consultation' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $thisPage === 'consultation.php' || $currentTab === 'consultation' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">4</span>
            <i class="fa-solid fa-file-lines text-sm w-4 text-center <?= $thisPage === 'consultation.php' || $currentTab === 'consultation' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Doctor Consultation</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('lab.php', $allowedPagesForUser)): ?>
          <!-- 5. Diagnostic Laboratory -->
          <a href="<?= ihms_url('lab.php', 'laboratory', ['patient_id' => $activePatientId]) ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $thisPage === 'lab.php' || $currentTab === 'laboratory' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $thisPage === 'lab.php' || $currentTab === 'laboratory' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">5</span>
            <i class="fa-solid fa-flask text-sm w-4 text-center <?= $thisPage === 'lab.php' || $currentTab === 'laboratory' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Laboratory Tests</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('pharmacy.php', $allowedPagesForUser)): ?>
          <!-- 6. Pharmacy Dispensary -->
          <a href="<?= ihms_url('pharmacy.php', 'pharmacy', ['patient_id' => $activePatientId]) ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $thisPage === 'pharmacy.php' || $currentTab === 'pharmacy' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $thisPage === 'pharmacy.php' || $currentTab === 'pharmacy' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">6</span>
            <i class="fa-solid fa-capsules text-sm w-4 text-center <?= $thisPage === 'pharmacy.php' || $currentTab === 'pharmacy' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Pharmacy &amp; Stock</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('billing.php', $allowedPagesForUser)): ?>
          <!-- 7. Billing Desk -->
          <a href="<?= ihms_url('billing.php', 'billing', ['patient_id' => $activePatientId]) ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $thisPage === 'billing.php' || $currentTab === 'billing' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $thisPage === 'billing.php' || $currentTab === 'billing' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">7</span>
            <i class="fa-solid fa-receipt text-sm w-4 text-center <?= $thisPage === 'billing.php' || $currentTab === 'billing' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Billing &amp; Revenue</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('receipt.php', $allowedPagesForUser)): ?>
          <!-- 8. Receipt Preview -->
          <a href="<?= ihms_url('receipt.php', 'receipt', ['patient_id' => $activePatientId]) ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $thisPage === 'receipt.php' || $currentTab === 'receipt' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $thisPage === 'receipt.php' || $currentTab === 'receipt' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">8</span>
            <i class="fa-solid fa-print text-sm w-4 text-center <?= $thisPage === 'receipt.php' || $currentTab === 'receipt' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Print Receipt</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('audit.php', $allowedPagesForUser)): ?>
          <!-- 9. Audit Logs -->
          <a href="<?= ihms_url('audit.php', 'audit') ?>"
             class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $thisPage === 'audit.php' || $currentTab === 'audit' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $thisPage === 'audit.php' || $currentTab === 'audit' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">9</span>
            <i class="fa-solid fa-shield-halved text-sm w-4 text-center <?= $thisPage === 'audit.php' || $currentTab === 'audit' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
            <span class="text-xs">Security Audit Trail</span>
          </a>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin' || in_array('settings.php', $allowedPagesForUser)): ?>
          <!-- 10. System Settings -->
          <a href="<?= ihms_url('settings.php', 'settings') ?>"
             class="flex items-center gap-3 px-4 py-3 transition <?= $thisPage === 'settings.php' || $currentTab === 'settings' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $thisPage === 'settings.php' || $currentTab === 'settings' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">10</span>
            <i class="fa-solid fa-gear text-sm w-4 text-center <?= $thisPage === 'settings.php' || $currentTab === 'settings' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
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

      <?php if (isset($_GET['msg']) && in_array($_GET['msg'], ['access_restricted_role_switch', 'access_restricted_to_workstation', 'role_restricted'])): ?>
        <div class="mb-4 p-3.5 rounded-xl font-medium text-xs flex flex-wrap items-center justify-between gap-2 <?= $isDarkBlue ? 'bg-amber-950/80 border-amber-700 text-amber-200' : 'bg-amber-100 text-amber-900 border border-amber-300' ?>">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-lock text-amber-600 text-sm shrink-0"></i>
            <span><strong>Department Workstation Boundary:</strong> As a <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $currentUser['role']))) ?></strong>, your authorized workstation is <strong><?= htmlspecialchars($currentUser['department'] ?? 'Clinical Station') ?></strong>. Only the Hospital Administrator has authorization to view and oversee the work of all staff members.</span>
          </div>
          <a href="<?= $logoutDest ?>" class="px-3 py-1.5 rounded-lg bg-[#06559d] hover:bg-[#05437d] text-white font-bold text-[11px] whitespace-nowrap transition shadow-2xs">
            Sign Out &amp; Login as Admin &rarr;
          </a>
        </div>
      <?php endif; ?>

      <!-- ACTIVE PATIENT CONTEXT STRIP WITH REAL-TIME TRANSITION -->
      <?php if ($thisPage !== 'settings.php' && $thisPage !== 'audit.php' && $thisPage !== 'dashboard.php'): ?>
        <?php
          $curStatus = $selectedPatient['current_status'] ?? 'Registered';
          $nextStageInfo = [
            'Registered'       => ['to' => 'Triaged',          'page' => 'triage.php',        'label' => 'Transition to Triage'],
            'Triaged'          => ['to' => 'In_Consultation',  'page' => 'consultation.php',  'label' => 'Transition to Doctor'],
            'In_Consultation'  => ['to' => 'Lab_Pending',      'page' => 'lab.php',           'label' => 'Transition to Lab'],
            'Lab_Pending'      => ['to' => 'Pharmacy_Pending', 'page' => 'pharmacy.php',      'label' => 'Transition to Pharmacy'],
            'Pharmacy_Pending' => ['to' => 'Billing_Pending',  'page' => 'billing.php',       'label' => 'Transition to Billing'],
            'Billing_Pending'  => ['to' => 'Discharged',       'page' => 'receipt.php',       'label' => 'Settle & Print Receipt'],
            'Discharged'       => ['to' => 'Registered',       'page' => 'registration.php',  'label' => 'Re-admit for Intake'],
          ];
          $nxt = $nextStageInfo[$curStatus] ?? $nextStageInfo['Registered'];
        ?>
        <div class="mb-4 <?= $isDarkBlue ? 'bg-[#0c2340] border-[#1b3b6f]' : 'bg-white/95 border-[#c7e0f6]' ?> border rounded-xl p-3 shadow-2xs flex flex-wrap items-center justify-between gap-3 text-xs <?= $thisPage === 'receipt.php' ? 'no-print' : '' ?>">
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
            <a href="<?= $thisPage ?>?action=transition_stage&patient_id=<?= $activePatientId ?>&to_stage=<?= urlencode($nxt['to']) ?>&return_page=<?= urlencode($nxt['page']) ?>" class="px-3.5 py-1.5 rounded-xl bg-[#06559d] hover:bg-[#00407a] text-white font-bold text-xs shadow-xs transition flex items-center gap-1.5 cursor-pointer">
              <span><?= $nxt['label'] ?></span>
              <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
          </div>
        </div>
      <?php endif; ?>
