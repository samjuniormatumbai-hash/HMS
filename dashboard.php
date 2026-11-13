<?php

$baseDir = file_exists(__DIR__ . '/header.php') ? __DIR__ : (file_exists(dirname(__DIR__) . '/header.php') ? dirname(__DIR__) : __DIR__);
require_once $baseDir . '/header.php';

// Dynamic Real-Time Counters Calculated from Active Session State
$autoTotalPatients = count($_SESSION['patients'] ?? []);
$autoTriageQueue = count(array_filter($_SESSION['patients'] ?? [], fn($p) => in_array($p['current_status'] ?? '', ['Registered', 'Triaged'])));
$autoConsultations = count(array_filter($_SESSION['patients'] ?? [], fn($p) => in_array($p['current_status'] ?? '', ['In_Consultation', 'Lab_Pending', 'Pharmacy_Pending', 'Billing_Pending', 'Discharged'])));
$autoLabSpecimens = count(array_filter($_SESSION['patients'] ?? [], fn($p) => in_array($p['current_status'] ?? '', ['Lab_Pending', 'Pharmacy_Pending', 'Billing_Pending', 'Discharged'])));
$autoPharmacyStock = !empty($_SESSION['pharmacy_inventory']) ? array_sum(array_column($_SESSION['pharmacy_inventory'], 'stock_qty')) : 1165;
$autoDailyRevenue = !empty($_SESSION['revenue_ledger']) ? array_sum(array_column($_SESSION['revenue_ledger'], 'amount')) : 9750.00;

$stages = [
  'Registered'       => ['title' => 'Registration',   'step' => 1, 'page' => 'registration.php', 'icon' => 'fa-clipboard-list', 'color' => 'blue',    'next' => 'Triaged',          'next_page' => 'triage.php',        'next_lbl' => 'Send to Triage'],
  'Triaged'          => ['title' => 'Triage Desk',    'step' => 2, 'page' => 'triage.php',       'icon' => 'fa-stethoscope',    'color' => 'rose',    'next' => 'In_Consultation',  'next_page' => 'consultation.php',  'next_lbl' => 'Send to Doctor'],
  'In_Consultation'  => ['title' => 'Doctor Consult', 'step' => 3, 'page' => 'consultation.php', 'icon' => 'fa-user-doctor',   'color' => 'indigo',  'next' => 'Lab_Pending',      'next_page' => 'lab.php',           'next_lbl' => 'Send to Lab'],
  'Lab_Pending'      => ['title' => 'Laboratory',     'step' => 4, 'page' => 'lab.php',          'icon' => 'fa-flask',          'color' => 'purple',  'next' => 'Pharmacy_Pending', 'next_page' => 'pharmacy.php',      'next_lbl' => 'Send to Pharmacy'],
  'Pharmacy_Pending' => ['title' => 'Pharmacy',       'step' => 5, 'page' => 'pharmacy.php',     'icon' => 'fa-pills',          'color' => 'amber',   'next' => 'Billing_Pending',  'next_page' => 'billing.php',       'next_lbl' => 'Send to Billing'],
  'Billing_Pending'  => ['title' => 'Billing & POS',  'step' => 6, 'page' => 'billing.php',      'icon' => 'fa-receipt',        'color' => 'emerald', 'next' => 'Discharged',       'next_page' => 'receipt.php',       'next_lbl' => 'Settle & Receipt'],
];
?>

<div class="space-y-6 <?= $isDarkBlue ? 'bg-[#07162c] -m-4 sm:-m-6 p-4 sm:p-6 rounded-3xl border border-[#1b3b6f] text-slate-100 shadow-xl' : '' ?>">
  
  <!-- Executive Header -->
  <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]' : 'border-[#c7e0f6]' ?>">
    <div>
      <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold <?= $isDarkBlue ? 'bg-[#0a2f5a] text-sky-200 border border-sky-400/40' : 'bg-[#0260a8] text-white' ?>">
          <i class="fa-solid fa-crown mr-1"></i> Admin Command Centre
        </span>
        <?php if (!empty($_SESSION['facility_settings']['moh_code'])): ?>
          <span class="text-xs font-mono <?= $isDarkBlue ? 'text-emerald-300 bg-emerald-950/60 border border-emerald-800' : 'text-emerald-700 bg-emerald-50 border border-emerald-200' ?> px-2 py-0.5 rounded font-semibold">
          
          </span>
        <?php endif; ?>
      </div>
      <h2 class="text-xl sm:text-2xl font-bold <?= $isDarkBlue ? 'text-white' : 'text-[#0c477d]' ?> tracking-tight mt-1">
        Hospital Executive Operations
      </h2>
      <p class="text-xs <?= $isDarkBlue ? 'text-slate-300' : 'text-slate-600' ?>">
        Centralized administration interface providing immediate oversight and routing across all clinical, diagnostic, and financial modules.
      </p>
    </div>
    <div class="flex items-center gap-2">
      <a href="registration.php" class="bg-[#0069c8] hover:bg-[#0057a6] text-white font-bold px-4 py-2 rounded-xl text-xs shadow-md transition flex items-center gap-2">
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
        <a href="dashboard.php?switch_role=admin&return_page=dashboard.php" title="Switch to Admin" class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $currentUser['role'] === 'admin' ? 'bg-[#06559d] text-white shadow-xs' : ($isDarkBlue ? 'bg-[#07162c] text-slate-200 border border-[#1b3b6f]' : 'bg-slate-100 hover:bg-slate-200 text-slate-700') ?>">
          <i class="fa-solid fa-crown text-[10px]"></i><span>Admin</span>
        </a>
        <a href="dashboard.php?switch_role=doctor&return_page=dashboard.php" title="Switch to Doctor" class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $currentUser['role'] === 'doctor' ? 'bg-emerald-600 text-white shadow-xs' : ($isDarkBlue ? 'bg-[#07162c] text-slate-200 border border-[#1b3b6f]' : 'bg-slate-100 hover:bg-slate-200 text-slate-700') ?>">
          <i class="fa-solid fa-user-doctor text-[10px]"></i><span>Doctor</span>
        </a>
        <a href="dashboard.php?switch_role=nurse&return_page=dashboard.php" title="Switch to Nurse" class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $currentUser['role'] === 'nurse' ? 'bg-cyan-600 text-white shadow-xs' : ($isDarkBlue ? 'bg-[#07162c] text-slate-200 border border-[#1b3b6f]' : 'bg-slate-100 hover:bg-slate-200 text-slate-700') ?>">
          <i class="fa-solid fa-stethoscope text-[10px]"></i><span>Nurse</span>
        </a>
        <a href="dashboard.php?switch_role=pharmacist&return_page=dashboard.php" title="Switch to Pharmacist" class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $currentUser['role'] === 'pharmacist' ? 'bg-amber-600 text-white shadow-xs' : ($isDarkBlue ? 'bg-[#07162c] text-slate-200 border border-[#1b3b6f]' : 'bg-slate-100 hover:bg-slate-200 text-slate-700') ?>">
          <i class="fa-solid fa-prescription-bottle-medical text-[10px]"></i><span>Pharmacist</span>
        </a>
        <a href="dashboard.php?switch_role=lab_tech&return_page=dashboard.php" title="Switch to Lab Tech" class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $currentUser['role'] === 'lab_tech' ? 'bg-purple-600 text-white shadow-xs' : ($isDarkBlue ? 'bg-[#07162c] text-slate-200 border border-[#1b3b6f]' : 'bg-slate-100 hover:bg-slate-200 text-slate-700') ?>">
          <i class="fa-solid fa-vial-virus text-[10px]"></i><span>Lab Tech</span>
        </a>
        <a href="dashboard.php?switch_role=accountant&return_page=dashboard.php" title="Switch to Accountant" class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $currentUser['role'] === 'accountant' ? 'bg-teal-600 text-white shadow-xs' : ($isDarkBlue ? 'bg-[#07162c] text-slate-200 border border-[#1b3b6f]' : 'bg-slate-100 hover:bg-slate-200 text-slate-700') ?>">
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
  <div class="<?= $isDarkBlue ? 'bg-[#081b35] border-[#1b3b6f]' : 'bg-white border-[#c7e0f6]' ?> rounded-2xl p-5 border shadow-sm space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]' : 'border-slate-100' ?>">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#06559d] text-white uppercase tracking-wider font-mono flex items-center gap-1">
            <i class="fa-solid fa-arrows-split-up-and-left text-[9px]"></i> Normalized Clinical Workflow Pipeline
          </span>
          
        </div>
        <h3 class="text-base sm:text-lg font-bold <?= $isDarkBlue ? 'text-white' : 'text-slate-900' ?> mt-1">
          Active Outpatient Journey Across Core Hospital Departments
        </h3>
        
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
          <button type="button" onclick="fillPreset('Samuel Mwangi', 'Male', 'SHA', 'Registered')" class="px-2 py-1 bg-white border border-slate-300 hover:border-blue-500 rounded-lg text-[10px] font-semibold text-slate-700 cursor-pointer">Malaria Crisis (Registration)</button>
          <button type="button" onclick="fillPreset('Grace Wanjiru', 'Female', 'Cash', 'Triaged')" class="px-2 py-1 bg-white border border-slate-300 hover:border-blue-500 rounded-lg text-[10px] font-semibold text-slate-700 cursor-pointer">Hypertensive Urgency (Triage)</button>
          <button type="button" onclick="fillPreset('Brian Kiprono', 'Male', 'Private Insurance', 'In_Consultation')" class="px-2 py-1 bg-white border border-slate-300 hover:border-blue-500 rounded-lg text-[10px] font-semibold text-slate-700 cursor-pointer">Pediatric Bronchitis (Doctor)</button>
          <button type="button" onclick="fillPreset('Amina Hassan', 'Female', 'SHA', 'Lab_Pending')" class="px-2 py-1 bg-white border border-slate-300 hover:border-blue-500 rounded-lg text-[10px] font-semibold text-slate-700 cursor-pointer">Diabetes Review (Laboratory)</button>
          <button type="button" onclick="fillPreset('Kevin Otieno', 'Male', 'Cash', 'Pharmacy_Pending')" class="px-2 py-1 bg-white border border-slate-300 hover:border-blue-500 rounded-lg text-[10px] font-semibold text-slate-700 cursor-pointer">Tonsillitis (Pharmacy)</button>
          <button type="button" onclick="fillPreset('Matty Jeff', 'Female', 'SHA', 'Billing_Pending')" class="px-2 py-1 bg-white border border-slate-300 hover:border-blue-500 rounded-lg text-[10px] font-semibold text-slate-700 cursor-pointer">Clearance (Billing Desk)</button>
        </div>
      </div>

      <form method="POST" action="dashboard.php" class="grid grid-cols-1 sm:grid-cols-4 gap-3 pt-2">
        <input type="hidden" name="action" value="add_lifecycle_patient">
        <input type="hidden" name="return_page" value="dashboard.php">
        <div>
          <label class="block font-bold mb-1 <?= $isDarkBlue ? 'text-slate-300' : 'text-slate-700' ?>">Full Name *</label>
          <input type="text" id="preset_name" name="full_name" required placeholder="e.g. Samuel Mwangi" class="w-full px-2.5 py-1.5 bg-white text-slate-900 border border-slate-300 rounded-lg">
        </div>
        <div>
          <label class="block font-bold mb-1 <?= $isDarkBlue ? 'text-slate-300' : 'text-slate-700' ?>">Gender</label>
          <select id="preset_gender" name="gender" class="w-full px-2.5 py-1.5 bg-white text-slate-900 border border-slate-300 rounded-lg">
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>
        <div>
          <label class="block font-bold mb-1 <?= $isDarkBlue ? 'text-slate-300' : 'text-slate-700' ?>">Payment Scheme</label>
          <select id="preset_scheme" name="payment_scheme" class="w-full px-2.5 py-1.5 bg-white text-slate-900 border border-slate-300 rounded-lg">
            <option value="SHA">Social Health Authority (SHA)</option>
            <option value="Cash">Cash / M-Pesa</option>
            <option value="Private Insurance">Private Insurance</option>
          </select>
        </div>
        <div>
          <label class="block font-bold mb-1 <?= $isDarkBlue ? 'text-slate-300' : 'text-slate-700' ?>">Start Department</label>
          <select id="preset_stage" name="start_stage" class="w-full px-2.5 py-1.5 bg-white text-slate-900 border border-slate-300 rounded-lg font-bold">
            <option value="Registered">Registration</option>
            <option value="Triaged">Triage Desk</option>
            <option value="In_Consultation">Doctor Consult</option>
            <option value="Lab_Pending">Laboratory</option>
            <option value="Pharmacy_Pending">Pharmacy</option>
            <option value="Billing_Pending">Billing Desk</option>
            <option value="Discharged">Discharged</option>
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

    <!-- 6-DEPARTMENT NORMALIZED KANBAN PIPELINE (No Stage Numbering) -->
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
                  <?= $cfg['title'] ?>
                </div>
              </div>
            </div>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold <?= $isDarkBlue ? 'bg-slate-800 text-sky-300 border border-slate-700' : 'bg-white text-[#06559d] border border-slate-200' ?>">
              <?= count($colPatients) ?>
            </span>
          </div>

          <!-- Jump Link to Department Page -->
          <a href="<?= $cfg['page'] ?>" class="mt-1.5 text-[10px] font-bold text-blue-600 hover:text-blue-800 flex items-center justify-between py-1 px-1.5 rounded bg-blue-50/70 hover:bg-blue-100/70 transition">
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
                    <a href="dashboard.php?action=transition_stage&patient_id=<?= $pat['patient_id'] ?>&to_stage=<?= urlencode($cfg['next']) ?>&return_page=dashboard.php" class="w-full bg-[#06559d] hover:bg-[#00407a] text-white font-bold py-1 px-1.5 rounded text-[10px] transition text-center flex items-center justify-center gap-1 shadow-2xs">
                      <span><?= $cfg['next_lbl'] ?></span>
                      <i class="fa-solid fa-arrow-right text-[8px]"></i>
                    </a>

                    <div class="flex items-center justify-between text-[9px] text-blue-700 pt-0.5">
                      <a href="<?= $cfg['page'] ?>?patient_id=<?= $pat['patient_id'] ?>" class="hover:underline font-semibold flex items-center gap-0.5">
                        <i class="fa-solid fa-pen-to-square text-[8px]"></i> Workstation
                      </a>
                      <a href="dashboard.php?action=transition_stage&patient_id=<?= $pat['patient_id'] ?>&to_stage=Discharged&return_page=receipt.php" class="text-emerald-700 hover:underline font-semibold" title="Fast Clear &amp; Bill">
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
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-emerald-600 font-bold flex items-center gap-1">
          <i class="fa-solid fa-circle-check"></i> Discharged &amp; Cleared (<?= count($discharged) ?>):
        </span>
        <span class="<?= $isDarkBlue ? 'text-slate-300' : 'text-slate-600' ?> text-[11px]">
          <?php foreach ($discharged as $dp): ?>
            <a href="receipt.php?patient_id=<?= $dp['patient_id'] ?>" class="inline-block bg-emerald-50 text-emerald-800 border border-emerald-200 px-2 py-0.5 rounded mr-1 hover:bg-emerald-100 font-mono text-[10px]">
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

  <!-- Active Patients In Lifecycle Table -->
  <div class="<?= $isDarkBlue ? 'bg-[#0c2340] border-[#1b3b6f]' : 'bg-white border-[#c7e0f6]' ?> rounded-2xl p-5 border shadow-2xs space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b <?= $isDarkBlue ? 'border-[#1b3b6f]' : 'border-slate-100' ?> pb-3">
      <div>
        <h3 class="font-bold text-sm <?= $isDarkBlue ? 'text-white' : 'text-slate-900' ?> flex items-center gap-2">
          <i class="fa-solid fa-users text-[#06559d]"></i> Live Patient Flow Through Care Lifecycle
        </h3>
        <p class="text-xs <?= $isDarkBlue ? 'text-slate-400' : 'text-slate-500' ?>">Real-time queue tracking current clinic location and immediate departmental action routing.</p>
      </div>
      <a href="registration.php" class="px-3 py-1.5 bg-[#06559d] hover:bg-[#05437d] text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-xs">
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
                'Registered'       => ['badge' => 'bg-blue-100 text-blue-800',       'next_label' => 'Route to Triage',      'next_page' => 'triage.php',       'next_stage' => 'Triaged'],
                'Triaged'          => ['badge' => 'bg-rose-100 text-rose-800',       'next_label' => 'Route to Doctor',      'next_page' => 'consultation.php', 'next_stage' => 'In_Consultation'],
                'In_Consultation'  => ['badge' => 'bg-indigo-100 text-indigo-800',   'next_label' => 'Send to Lab',          'next_page' => 'lab.php',          'next_stage' => 'Lab_Pending'],
                'Lab_Pending'      => ['badge' => 'bg-purple-100 text-purple-800',   'next_label' => 'Send to Pharmacy',     'next_page' => 'pharmacy.php',     'next_stage' => 'Pharmacy_Pending'],
                'Pharmacy_Pending' => ['badge' => 'bg-amber-100 text-amber-800',     'next_label' => 'Route to Billing',     'next_page' => 'billing.php',      'next_stage' => 'Billing_Pending'],
                'Billing_Pending'  => ['badge' => 'bg-emerald-100 text-emerald-800', 'next_label' => 'Issue Receipt',       'next_page' => 'receipt.php',      'next_stage' => 'Discharged'],
                'Discharged'       => ['badge' => 'bg-slate-200 text-slate-800',     'next_label' => 'View Receipt',         'next_page' => 'receipt.php',      'next_stage' => 'Discharged'],
              ];
              $st = $statusStyles[$status] ?? $statusStyles['Registered'];
              $isSelected = ($p['patient_id'] === $activePatientId);
            ?>
            <tr class="<?= $isSelected ? ($isDarkBlue ? 'bg-blue-950/40' : 'bg-blue-50/70 font-semibold') : ($isDarkBlue ? 'hover:bg-[#123158]' : 'hover:bg-slate-50') ?> transition">
              <td class="p-2.5 font-mono text-[11px] <?= $isDarkBlue ? 'text-sky-300' : 'text-[#06559d]' ?>"><?= htmlspecialchars($p['opd_number']) ?></td>
              <td class="p-2.5 font-bold <?= $isDarkBlue ? 'text-white' : 'text-slate-900' ?>">
                <a href="<?= $st['next_page'] ?>?patient_id=<?= $p['patient_id'] ?>" class="hover:underline flex items-center gap-1.5">
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
                  <a href="<?= $st['next_page'] ?>?patient_id=<?= $p['patient_id'] ?>" class="px-2.5 py-1 rounded-lg bg-[#06559d] hover:bg-[#00407a] text-white text-[11px] font-bold transition">
                    <?= $st['next_label'] ?>
                  </a>
                  <?php if ($status !== 'Discharged'): ?>
                    <a href="dashboard.php?action=transition_stage&patient_id=<?= $p['patient_id'] ?>&to_stage=<?= urlencode($st['next_stage']) ?>&return_page=dashboard.php" title="Fast 1-click stage advance" class="px-2 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold transition">
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

<?php require_once $baseDir . '/footer.php'; ?>
