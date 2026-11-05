<?php

ob_start();
session_start();
date_default_timezone_set('Africa/Nairobi');

// ---------------------------------------------------------------------
//  DATABASE CONFIGURATION & PDO CONNECTION (With Session Auto-Fallback)
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
            full_name VARCHAR(100) NOT NULL,
            role ENUM('admin','doctor','nurse','pharmacist','lab_tech','accountant') NOT NULL,
            department VARCHAR(80) NOT NULL
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS patients (
            patient_id INT AUTO_INCREMENT PRIMARY KEY,
            opd_number VARCHAR(30) NOT NULL UNIQUE,
            national_id VARCHAR(20) NULL,
            full_name VARCHAR(100) NOT NULL,
            gender ENUM('Male','Female','Other') NOT NULL,
            phone VARCHAR(20) NOT NULL,
            payment_scheme ENUM('Cash','SHA','Private Insurance') DEFAULT 'Cash',
            current_status ENUM('Registered','Triaged','In_Consultation','Lab_Pending','Pharmacy_Pending','Billing_Pending','Discharged') DEFAULT 'Registered',
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
// HELPER FUNCTIONS & SESSION ROLE SWITCHER
// ---------------------------------------------------------------------
function safe_str($v, $d = '') { return (isset($v) && !is_array($v)) ? trim((string)$v) : $d; }
function safe_int($v, $d = 0) { return (isset($v) && is_numeric($v)) ? (int)$v : $d; }
function safe_float($v, $d = 0.0) { return (isset($v) && is_numeric($v)) ? (float)$v : $d; }

// Staff Role Switcher Handler (Masquerade as any clinical staff)
if (isset($_GET['switch_role'])) {
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
        $dest = safe_str($_GET['return_tab'] ?? null, $roleMap[$target]['tab']);
        header("Location: index.php?tab=" . urlencode($dest) . "&msg=role_switched");
        exit;
    }
}

// Active Module Tab (Default: 1. Dashboard)
$activeTab = safe_str($_GET['tab'] ?? null, 'dashboard');
$allowedTabs = ['dashboard', 'pipeline', 'registration', 'triage', 'consultation', 'laboratory', 'pharmacy', 'billing', 'receipt', 'audit', 'settings'];
if (!in_array($activeTab, $allowedTabs)) {
    $activeTab = 'dashboard';
}

// Theme Handling (Supports Classic Hospital Blue & Dark Blue Cockpit)
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = safe_str($_GET['theme'], 'default');
}
$isDarkBlue = (isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark_blue');

// Generic System Roles Definition
$systemRoles = [
    'admin'      => ['id' => 1, 'username' => 'admin',      'full_name' => 'Admin',          'role' => 'admin',      'department' => 'Hospital Administration'],
    'doctor'     => ['id' => 2, 'username' => 'doctor',     'full_name' => 'Doctor',         'role' => 'doctor',     'department' => 'Outpatient Clinical Services'],
    'nurse'      => ['id' => 3, 'username' => 'nurse',      'full_name' => 'Nurse',          'role' => 'nurse',      'department' => 'Emergency & Triage Station'],
    'pharmacist' => ['id' => 4, 'username' => 'pharmacist', 'full_name' => 'Pharmacist',     'role' => 'pharmacist', 'department' => 'Main Hospital Pharmacy'],
    'lab_tech'   => ['id' => 5, 'username' => 'lab_tech',   'full_name' => 'Lab Technician', 'role' => 'lab_tech',   'department' => 'Diagnostic Pathology Lab'],
    'accountant' => ['id' => 6, 'username' => 'accountant', 'full_name' => 'Accountant',     'role' => 'accountant', 'department' => 'Billing & Cashier Revenue Desk'],
];

// Clean session if user is not set or has legacy names
if (empty($_SESSION['user']) || empty($_SESSION['user']['role']) || !isset($systemRoles[$_SESSION['user']['role']]) || strpos($_SESSION['user']['full_name'] ?? '', 'Omondi') !== false || strpos($_SESSION['user']['full_name'] ?? '', 'Kamau') !== false || strpos($_SESSION['user']['full_name'] ?? '', 'Wanjiku') !== false) {
    $roleKey = $_SESSION['user']['role'] ?? 'admin';
    $_SESSION['user'] = $systemRoles[$roleKey] ?? $systemRoles['admin'];
}
$currentUser = $_SESSION['user'];

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
        'moh_code'         => '2004',
        'facility_level'   => 'Level 4 Hospital / Sub-County Referral',
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
//  ACTION POST & GET HANDLERS (Registration, Transitions, Triage, Consult, Dispense, Bill)
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

    // Register Patient
    if ($action === 'register_patient') {
        $pName  = safe_str($_POST['full_name'] ?? null, 'New Patient');
        $phone  = safe_str($_POST['phone'] ?? null, '+254 700 000 000');
        $gender = safe_str($_POST['gender'] ?? null, 'Male');
        $scheme = safe_str($_POST['payment_scheme'] ?? null, 'Cash');
        $newId  = count($_SESSION['patients']) + 25;
        $opd    = 'OPD-2025-' . str_pad($newId, 3, '0', STR_PAD_LEFT);

        array_unshift($_SESSION['patients'], [
            'patient_id'     => $newId,
            'opd_number'     => $opd,
            'full_name'      => $pName,
            'gender'         => $gender,
            'phone'          => $phone,
            'payment_scheme' => $scheme,
            'current_status' => 'Registered'
        ]);
        header("Location: index.php?tab=triage&patient_id={$newId}&msg=patient_registered");
        exit;
    }

    //  Save Triage Vitals
    if ($action === 'save_triage') {
        $pid = safe_int($_POST['patient_id'] ?? null, 19);
        foreach ($_SESSION['patients'] as &$p) {
            if ($p['patient_id'] === $pid) {
                $p['current_status'] = 'Triaged';
                break;
            }
        }
        header("Location: index.php?tab=consultation&patient_id={$pid}&msg=vitals_saved");
        exit;
    }

    //  Save Doctor Consultation
    if ($action === 'save_consultation') {
        $pid = safe_int($_POST['patient_id'] ?? null, 19);
        $routeTo = safe_str($_POST['route_to'] ?? null, 'pharmacy');
        $nextStatus = ($routeTo === 'lab') ? 'Lab_Pending' : 'Pharmacy_Pending';
        $destTab = ($routeTo === 'lab') ? 'laboratory' : 'pharmacy';

        foreach ($_SESSION['patients'] as &$p) {
            if ($p['patient_id'] === $pid) {
                $p['current_status'] = $nextStatus;
                break;
            }
        }
        header("Location: index.php?tab={$destTab}&patient_id={$pid}&msg=consult_saved");
        exit;
    }

    //  Complete Lab Test
    if ($action === 'complete_lab') {
        $pid = safe_int($_POST['patient_id'] ?? null, 19);
        foreach ($_SESSION['patients'] as &$p) {
            if ($p['patient_id'] === $pid) {
                $p['current_status'] = 'Pharmacy_Pending';
                break;
            }
        }
        header("Location: index.php?tab=pharmacy&patient_id={$pid}&msg=lab_completed");
        exit;
    }

    //  Dispense Medication (Decrements stock inventory)
    if ($action === 'dispense_medication') {
        $pid = safe_int($_POST['patient_id'] ?? null, 19);
        foreach ($_SESSION['patients'] as &$p) {
            if ($p['patient_id'] === $pid) {
                $p['current_status'] = 'Billing_Pending';
                break;
            }
        }
        if (!empty($_SESSION['pharmacy_inventory'])) {
            foreach ($_SESSION['pharmacy_inventory'] as &$med) {
                if ($med['stock_qty'] > 5) {
                    $med['stock_qty'] -= 2;
                }
            }
        }
        header("Location: index.php?tab=billing&patient_id={$pid}&msg=dispensed");
        exit;
    }

    //  Cashier Settle Bill (Records payment in ledger)
    if ($action === 'settle_bill') {
        $pid = safe_int($_POST['patient_id'] ?? null, 19);
        $patName = 'Walk-in Patient';
        foreach ($_SESSION['patients'] as &$p) {
            if ($p['patient_id'] === $pid) {
                $p['current_status'] = 'Discharged';
                $patName = $p['full_name'];
                break;
            }
        }
        $amountPaid = ($_SESSION['facility_settings']['consultation_fee'] ?? 500.00) + 2750.00;
        $_SESSION['revenue_ledger'][] = [
            'receipt_no'   => 'REC-' . rand(71970, 79999),
            'patient_id'   => $pid,
            'patient_name' => $patName,
            'amount'       => $amountPaid,
            'payment_mode' => 'M-Pesa STK',
            'settled_at'   => date('H:i:s')
        ];
        header("Location: index.php?tab=receipt&patient_id={$pid}&msg=paid");
        exit;
    }

    //  Reset Patient Workflow (Re-admit for Intake)
    if ($action === 'reset_patient') {
        $pid = safe_int($_POST['patient_id'] ?? null, 19);
        foreach ($_SESSION['patients'] as &$p) {
            if ($p['patient_id'] === $pid) {
                $p['current_status'] = 'Registered';
                break;
            }
        }
        header("Location: index.php?tab=triage&patient_id={$pid}&msg=patient_readmitted");
        exit;
    }

    //  Save Facility Settings
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

    /* Production-Grade Medical Print Engine */
    @media print {
      /* 1. Global Reset - remove body scroll/flex restrictions */
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
      }

      /* 2. Unconstrain main and section wrappers */
      main, section {
        overflow: visible !important;
        display: block !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
      }

      /* 3. Hide all page chrome and layout structures by default */
      body * {
        visibility: hidden !important;
      }

      /* 4. Make ONLY the official printable receipt visible */
      #printableReceipt, #printableReceipt * {
        visibility: visible !important;
      }

      /* 5. Position receipt at exact top center of printed page */
      #printableReceipt {
        position: absolute !important;
        left: 0 !important;
        right: 0 !important;
        top: 0 !important;
        margin: 0 auto !important;
        max-width: 420px !important;
        width: 100% !important;
        border: 1px dashed #000000 !important;
        border-radius: 8px !important;
        padding: 20px !important;
        background: #ffffff !important;
        color: #000000 !important;
        box-shadow: none !important;
        display: block !important;
        page-break-inside: avoid !important;
      }

      #printableReceipt * {
        color: #000000 !important;
        background-color: transparent !important;
        box-shadow: none !important;
      }

      /* 6. Suppress interactive action buttons, headers, sidebars, and nav */
      header, aside, footer, .no-print, .no-print *, button, a, #headerRoleDropdown, div[class*="URL STATUS BAR"], nav {
        display: none !important;
        visibility: hidden !important;
      }

      @page {
        margin: 8mm;
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
      <div class="relative">
        <button id="headerRoleSwitcherBtn" onclick="document.getElementById('headerRoleDropdown').classList.toggle('hidden');" type="button" class="flex items-center gap-2.5 bg-blue-800/80 hover:bg-blue-800 px-3 py-1.5 rounded-xl border border-blue-400/30 text-xs transition cursor-pointer text-left shadow-xs">
          <div class="w-8 h-8 rounded-lg bg-white/20 text-white flex items-center justify-center font-black text-xs border border-white/30 shrink-0">
            <?= $userInitials ?>
          </div>
          <div class="hidden sm:block">
            <div class="font-bold text-white leading-tight flex items-center gap-1.5">
              <span><?= htmlspecialchars($currentUser['full_name']) ?></span>
              <i class="fa-solid fa-chevron-down text-[10px] text-blue-200"></i>
            </div>
            <div class="text-[10px] text-blue-200 flex items-center gap-1 font-mono">
              <span class="uppercase tracking-wider font-bold text-white"><?= htmlspecialchars($currentUser['role']) ?></span>
              <span>&bull;</span>
              <span class="text-emerald-300 font-semibold hover:underline">Switch Role</span>
            </div>
          </div>
        </button>

        <!-- Dropdown Menu -->
        <div id="headerRoleDropdown" class="hidden absolute right-0 mt-2 w-72 bg-white text-slate-800 rounded-2xl shadow-2xl border border-slate-200 py-2 z-50 text-xs">
          <div class="px-3.5 py-2 border-b border-slate-100 flex items-center justify-between">
            <span class="font-bold text-slate-900 block">Switch Clinical Role</span>
            <span class="text-[10px] text-slate-500">1-click switch</span>
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
      <a href="index.php?action=logout" onclick="return confirm('Sign out of Staff Session?');" class="text-xs bg-rose-600 hover:bg-rose-700 text-white font-bold px-3 py-1.5 rounded-lg shadow-xs transition flex items-center gap-1.5">
        <i class="fa-solid fa-power-off"></i>
        <span class="hidden md:inline">Sign Out</span>
      </a>
    </div>
  </header>

  <!-- URL STATUS BAR -->
  <div class="bg-blue-900 text-blue-100 text-[11px] px-5 py-1.5 flex flex-wrap items-center justify-between border-b border-blue-800 no-print">
    <div class="flex items-center gap-2 font-mono">
      <span class="h-2 w-2 rounded-full <?= $isDbConnected ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400' ?>"></span>
      <span> <?= $isDbConnected ? : 'Self-Contained Session Storage Mode' ?></span>
      <span class="text-blue-300 hidden sm:inline">|</span>
      <span class="hidden sm:inline"> </span>
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
            <span>Hospital Operations</span>
          </span>
        
        </div>

        <!-- 1. Hospital Dashboard -->
        <a href="index.php?tab=dashboard"
           class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'dashboard' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
          <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'dashboard' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">1</span>
          <i class="fa-solid fa-chart-pie text-sm w-4 text-center <?= $activeTab === 'dashboard' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
          <span class="text-xs">Hospital Dashboard</span>
        </a>

        <!-- 2. Patient Registration -->
        <a href="index.php?tab=registration&patient_id=<?= $activePatientId ?>"
           class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'registration' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
          <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'registration' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">2</span>
          <i class="fa-solid fa-user text-sm w-4 text-center <?= $activeTab === 'registration' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
          <span class="text-xs">Patient Registration</span>
        </a>

        <!-- 3. Triage Desk -->
        <a href="index.php?tab=triage&patient_id=<?= $activePatientId ?>"
           class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'triage' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
          <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'triage' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">3</span>
          <i class="fa-solid fa-stethoscope text-sm w-4 text-center <?= $activeTab === 'triage' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
          <span class="text-xs">Triage Desk</span>
          <span class="ml-auto text-[10px] px-2 py-0.5 rounded-full <?= $activeTab === 'triage' ? 'bg-white text-[#0260a8]' : 'bg-[#0260a8] text-white' ?> font-bold">
            <?= count($_SESSION['patients']) ?>
          </span>
        </a>

        <!-- 4. Consultation Workstation -->
        <a href="index.php?tab=consultation&patient_id=<?= $activePatientId ?>"
           class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'consultation' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
          <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'consultation' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">4</span>
          <i class="fa-solid fa-file-lines text-sm w-4 text-center <?= $activeTab === 'consultation' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
          <span class="text-xs">Doctor Consultation</span>
        </a>

        <!-- 5. Diagnostic Laboratory -->
        <a href="index.php?tab=laboratory&patient_id=<?= $activePatientId ?>"
           class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'laboratory' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
          <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'laboratory' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">5</span>
          <i class="fa-solid fa-flask text-sm w-4 text-center <?= $activeTab === 'laboratory' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
          <span class="text-xs">Laboratory Tests</span>
        </a>

        <!-- 6. Pharmacy Dispensary -->
        <a href="index.php?tab=pharmacy&patient_id=<?= $activePatientId ?>"
           class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'pharmacy' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
          <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'pharmacy' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">6</span>
          <i class="fa-solid fa-capsules text-sm w-4 text-center <?= $activeTab === 'pharmacy' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
          <span class="text-xs">Pharmacy &amp; Stock</span>
        </a>

        <!-- 7. Billing Desk -->
        <a href="index.php?tab=billing&patient_id=<?= $activePatientId ?>"
           class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'billing' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
          <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'billing' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">7</span>
          <i class="fa-solid fa-receipt text-sm w-4 text-center <?= $activeTab === 'billing' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
          <span class="text-xs">Billing &amp; Revenue</span>
        </a>

        <!-- 8. Receipt Preview -->
        <a href="index.php?tab=receipt&patient_id=<?= $activePatientId ?>"
           class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'receipt' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
          <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'receipt' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">8</span>
          <i class="fa-solid fa-print text-sm w-4 text-center <?= $activeTab === 'receipt' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
          <span class="text-xs">Print Receipt</span>
        </a>

        <!-- 9. Audit Logs -->
        <a href="index.php?tab=audit"
           class="flex items-center gap-3 px-4 py-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]/50' : 'border-[#c6e1f8]/50' ?> transition <?= $activeTab === 'audit' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
          <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'audit' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">9</span>
          <i class="fa-solid fa-shield-halved text-sm w-4 text-center <?= $activeTab === 'audit' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
          <span class="text-xs">Security Audit Trail</span>
        </a>

        <!-- 10. System Settings -->
        <a href="index.php?tab=settings"
           class="flex items-center gap-3 px-4 py-3 transition <?= $activeTab === 'settings' ? 'bg-[#0260a8] text-white font-bold shadow-xs' : ($isDarkBlue ? 'text-slate-200 hover:bg-[#123158]' : 'text-[#0f3e6f] hover:bg-[#d9ecfa] font-medium') ?>">
          <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold <?= $activeTab === 'settings' ? 'bg-white text-[#0260a8]' : ($isDarkBlue ? 'bg-slate-800 text-sky-300' : 'bg-blue-100 text-[#0260a8]') ?>">10</span>
          <i class="fa-solid fa-gear text-sm w-4 text-center <?= $activeTab === 'settings' ? 'text-white' : 'text-[#0260a8]' ?>"></i>
          <span class="text-xs">System Settings</span>
        </a>
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

            <!-- 1-Click Role Switch Buttons -->
            <div class="flex flex-wrap items-center gap-1.5">
              <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider mr-1">Switch Role:</span>
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
         
          <div class="bg-gradient-to-r <?= $isDarkBlue ? 'from-[#061e3e] via-[#0b2b52] to-[#04152d] border border-[#1b3b6f]' : 'from-[#0260a8] to-[#0c477d]' ?> text-white p-5 rounded-2xl shadow-sm space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 pb-3">
              <div>
                <span class="font-bold uppercase tracking-wider text-blue-100 flex items-center gap-2 text-xs">
                  <i class="fa-solid fa-arrows-split-up-and-left text-sky-300"></i> Normalized Clinical Workflow Pipeline
                </span>
                <p class="text-[11px] text-blue-200 mt-0.5">Real-time multi-department patient movement across all 6 stages of care delivery.</p>
              </div>
              <span class="text-[10px] font-mono bg-white/10 px-2.5 py-1 rounded-full text-blue-100 font-bold border border-white/15">
                Active Queue: <?= count($_SESSION['patients']) ?> Patients
              </span>
            </div>

            <!-- Pipeline 6-Step Visual Bar -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 pt-1">
              <?php foreach ($stages as $stageKey => $meta): ?>
                <?php
                  $countInStage = 0;
                  foreach ($_SESSION['patients'] as $p) {
                    if (($p['current_status'] ?? '') === $stageKey) {
                      $countInStage++;
                    }
                  }
                  $isStageActive = ($selectedPatient && ($selectedPatient['current_status'] ?? '') === $stageKey);
                ?>
                <a href="index.php?tab=<?= $meta['tab'] ?>&patient_id=<?= $activePatientId ?>" class="p-3 rounded-xl transition border text-left flex flex-col justify-between group <?= $isStageActive ? 'bg-white text-slate-900 border-white shadow-lg ring-2 ring-emerald-400' : 'bg-white/10 hover:bg-white/20 text-white border-white/15' ?>">
                  <div class="flex items-center justify-between">
                   
                  </div>
                  <div class="mt-2">
                    <i class="fa-solid <?= $meta['icon'] ?> text-sm mb-1 block <?= $isStageActive ? 'text-[#06559d]' : 'text-sky-300' ?>"></i>
                    <strong class="text-xs block leading-tight <?= $isStageActive ? 'text-slate-900' : 'text-white' ?>"><?= $meta['title'] ?></strong>
                    <span class="text-[10px] <?= $isStageActive ? 'text-slate-500' : 'text-blue-200' ?> mt-0.5 block"><?= $stageKey ?></span>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Active Patients In Lifecycle Table -->
          <div class="<?= $isDarkBlue ? 'bg-[#0c2340] border-[#1b3b6f]' : 'bg-white border-[#c7e0f6]' ?> rounded-2xl p-5 border shadow-2xs space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]' : 'border-slate-100' ?> pb-3">
              <div>
                <h3 class="font-bold text-sm <?= $isDarkBlue ? 'text-white' : 'text-slate-900' ?> flex items-center gap-2">
                  <i class="fa-solid fa-users text-[#06559d]"></i> Live Patient Flow Through Care Lifecycle
                </h3>
                <p class="text-xs <?= $isDarkBlue ? 'text-slate-400' : 'text-slate-500' ?>">Real-time queue tracking current clinic location and immediate departmental action routing.</p>
              </div>
              <a href="index.php?tab=registration" class="px-3 py-1.5 bg-[#06559d] hover:bg-[#05437d] text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-xs">
                <i class="fa-solid fa-plus text-[10px]"></i><span>Admit Patient</span>
              </a>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="<?= $isDarkBlue ? 'bg-[#07162c] text-slate-300' : 'bg-slate-50 text-slate-600' ?> border-b <?= $isDarkBlue ? 'border-[#1b3b6f]' : 'border-slate-200' ?>">
                    <th class="p-2.5 font-bold">OPD #</th>
                    <th class="p-2.5 font-bold">Patient Name</th>
                    <th class="p-2.5 font-bold">Scheme</th>
                    <th class="p-2.5 font-bold">Current Department</th>
                    <th class="p-2.5 font-bold">Status Badge</th>
                    <th class="p-2.5 font-bold text-right">Direct Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y <?= $isDarkBlue ? 'divide-[#1b3b6f] text-slate-200' : 'divide-slate-100 text-slate-700' ?>">
                  <?php foreach ($_SESSION['patients'] as $p): ?>
                    <?php
                      $status = $p['current_status'] ?? 'Registered';
                      $statusStyles = [
                        'Registered'       => ['badge' => 'bg-blue-100 text-blue-800',       'next_label' => 'Route to Triage',      'next_tab' => 'triage',       'next_stage' => 'Triaged'],
                        'Triaged'          => ['badge' => 'bg-rose-100 text-rose-800',       'next_label' => 'Route to Doctor',      'next_tab' => 'consultation', 'next_stage' => 'In_Consultation'],
                        'In_Consultation'  => ['badge' => 'bg-indigo-100 text-indigo-800',   'next_label' => 'Send to Lab',          'next_tab' => 'laboratory',   'next_stage' => 'Lab_Pending'],
                        'Lab_Pending'      => ['badge' => 'bg-purple-100 text-purple-800',   'next_label' => 'Send to Pharmacy',     'next_tab' => 'pharmacy',     'next_stage' => 'Pharmacy_Pending'],
                        'Pharmacy_Pending' => ['badge' => 'bg-amber-100 text-amber-800',     'next_label' => 'Route to Billing',     'next_tab' => 'billing',      'next_stage' => 'Billing_Pending'],
                        'Billing_Pending'  => ['badge' => 'bg-emerald-100 text-emerald-800', 'next_label' => 'Issue Receipt',       'next_tab' => 'receipt',      'next_stage' => 'Discharged'],
                        'Discharged'       => ['badge' => 'bg-slate-200 text-slate-800',     'next_label' => 'View Receipt',         'next_tab' => 'receipt',      'next_stage' => 'Discharged'],
                      ];
                      $st = $statusStyles[$status] ?? $statusStyles['Registered'];
                      $isSelected = ($p['patient_id'] === $activePatientId);
                    ?>
                    <tr class="<?= $isSelected ? ($isDarkBlue ? 'bg-blue-950/40' : 'bg-blue-50/70 font-semibold') : ($isDarkBlue ? 'hover:bg-[#123158]' : 'hover:bg-slate-50') ?> transition">
                      <td class="p-2.5 font-mono text-[11px] <?= $isDarkBlue ? 'text-sky-300' : 'text-[#06559d]' ?>"><?= htmlspecialchars($p['opd_number']) ?></td>
                      <td class="p-2.5 font-bold <?= $isDarkBlue ? 'text-white' : 'text-slate-900' ?>">
                        <a href="index.php?tab=<?= $st['next_tab'] ?>&patient_id=<?= $p['patient_id'] ?>" class="hover:underline flex items-center gap-1.5">
                          <span><?= htmlspecialchars($p['full_name']) ?></span>
                          <?php if ($isSelected): ?>
                            <span class="text-[9px] px-1.5 py-0.2 bg-[#06559d] text-white rounded">Active</span>
                          <?php endif; ?>
                        </a>
                      </td>
                      <td class="p-2.5 text-[11px]"><?= htmlspecialchars($p['payment_scheme']) ?></td>
                      <td class="p-2.5">
                        <span class="capitalize"><?= str_replace('_', ' ', $status) ?></span>
                      </td>
                      <td class="p-2.5">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $st['badge'] ?>">
                          <?= htmlspecialchars($status) ?>
                        </span>
                      </td>
                      <td class="p-2.5 text-right">
                        <div class="inline-flex items-center gap-1">
                          <a href="index.php?tab=<?= $st['next_tab'] ?>&patient_id=<?= $p['patient_id'] ?>" class="px-2.5 py-1 rounded-lg bg-[#06559d] hover:bg-[#00407a] text-white text-[11px] font-bold transition">
                            <?= $st['next_label'] ?>
                          </a>
                          <?php if ($status !== 'Discharged'): ?>
                            <a href="index.php?action=transition_stage&patient_id=<?= $p['patient_id'] ?>&to_stage=<?= urlencode($st['next_stage']) ?>&return_tab=dashboard" title="Fast 1-click stage advance" class="px-2 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold transition">
                              &rarr;
                            </a>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

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
                <input type="text" name="full_name" required placeholder="  " class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Phone Number *</label>
                <input type="text" name="phone" required placeholder="  " class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Gender *</label>
                <select name="gender" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none">
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
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
            <p class="text-[10px] text-slate-500">MOH Code: <?= htmlspecialchars($_SESSION['facility_settings']['moh_code']) ?> &bull; <?= htmlspecialchars($_SESSION['facility_settings']['facility_level']) ?></p>
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
            <button type="button" onclick="printInNewWindow()" class="py-2.5 px-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-sm transition flex items-center gap-1.5 cursor-pointer" title="Open in separate printable window (Bypasses browser iframe restrictions)">
              <i class="fa-solid fa-arrow-up-right-from-square"></i>
              <span>Clean Print Tab</span>
            </button>
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

      <!-- 10. SYSTEM SETTINGS -->
      <?php if ($activeTab === 'settings'): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm max-w-3xl mx-auto space-y-4 text-slate-800">
          <div class="border-b border-slate-100 pb-3 flex justify-between items-center">
            <div>
              <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-gear text-[#06559d]"></i> Hospital Parameters &amp; Facility Settings
              </h2>
              <p class="text-xs text-slate-500">Configure institutional metadata, triage warning limits, and consultation fees.</p>
            </div>
            <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-bold">Admin Only</span>
          </div>
          <form method="POST" action="index.php?tab=settings" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="save_settings">
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
        </div>
      <?php endif; ?>

    </section>
  </main>

  <!-- FOOTER -->
  <footer class="bg-slate-900 text-slate-400 py-3 px-5 text-center text-xs border-t border-slate-800 no-print mt-auto">
    <span>AfyaSmart IHMS &bull; Your Health, Our Priority</span>
  </footer>

  <!-- MEDICAL PRINT ENGINE & CLIENT SCRIPTS -->
  <script>
    function printReceipt() {
      window.focus();
      window.print();
    }

    function printInNewWindow() {
      var receiptEl = document.getElementById('printableReceipt');
      if (!receiptEl) {
        window.focus();
        window.print();
        return;
      }

      var clone = receiptEl.cloneNode(true);
      var noPrintEls = clone.querySelectorAll('.no-print');
      for (var i = 0; i < noPrintEls.length; i++) {
        noPrintEls[i].remove();
      }

      var win = window.open('', '_blank', 'width=460,height=680');
      if (!win) {
        window.focus();
        window.print();
        return;
      }

      var doc = win.document;
      doc.open();
      doc.write('<!DOCTYPE html><html><head><title>AfyaSmart Medical Receipt</title>');
      doc.write('<style>');
      doc.write('body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #fff; color: #1e293b; padding: 24px; margin: 0; }');
      doc.write('.font-mono { font-family: monospace; }');
      doc.write('.receipt-card { max-width: 380px; margin: 0 auto; border: 1px dashed #64748b; padding: 20px; border-radius: 8px; font-size: 12px; }');
      doc.write('.text-center { text-align: center; }');
      doc.write('.uppercase { text-transform: uppercase; }');
      doc.write('.font-bold { font-weight: bold; }');
      doc.write('.font-black { font-weight: 900; }');
      doc.write('.flex { display: flex; }');
      doc.write('.justify-between { justify-content: space-between; }');
      doc.write('.border-b { border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px; margin-bottom: 8px; }');
      doc.write('.border-t { border-top: 1px dashed #cbd5e1; padding-top: 8px; margin-top: 8px; }');
      doc.write('.space-y-1 > * + * { margin-top: 4px; }');
      doc.write('.space-y-3 > * + * { margin-top: 12px; }');
      doc.write('.text-emerald-700 { color: #047857; }');
      doc.write('.text-slate-400 { color: #94a3b8; }');
      doc.write('.text-slate-500 { color: #64748b; }');
      doc.write('.text-slate-800 { color: #1e293b; }');
      doc.write('.bg-slate-100 { background: #f1f5f9; }');
      doc.write('.p-1 { padding: 4px; }');
      doc.write('.rounded { border-radius: 4px; }');
      doc.write('@media print { body { padding: 0; } .receipt-card { border: none; max-width: 100%; padding: 0; } }');
      doc.write('</style></head><body>');
      doc.write('<div class="receipt-card">' + clone.innerHTML + '</div>');
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