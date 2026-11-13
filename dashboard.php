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

  <!-- NORMALIZED CLINICAL WORKFLOW PIPELINE -->
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
        <a href="<?= $meta['page'] ?>?patient_id=<?= $activePatientId ?>" class="p-3 rounded-xl transition border text-left flex flex-col justify-between group <?= $isStageActive ? 'bg-white text-slate-900 border-white shadow-lg ring-2 ring-emerald-400' : 'bg-white/10 hover:bg-white/20 text-white border-white/15' ?>">
          <div class="flex items-center justify-between">
            <i class="fa-solid <?= $meta['icon'] ?> text-sm <?= $isStageActive ? 'text-[#06559d]' : 'text-sky-300' ?>"></i>
            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full <?= $countInStage > 0 ? ($isStageActive ? 'bg-emerald-100 text-emerald-800' : 'bg-emerald-400 text-slate-900') : ($isStageActive ? 'bg-slate-100 text-slate-500' : 'bg-white/10 text-blue-200') ?>">
              <?= $countInStage ?>
            </span>
          </div>
          <div class="mt-2">
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
