<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Form Submission BEFORE header rendering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $_SESSION['facility_settings']['hospital_name']    = trim((string)($_POST['hospital_name'] ?? $_SESSION['facility_settings']['hospital_name']));
    $_SESSION['facility_settings']['moh_code']         = trim((string)($_POST['moh_code'] ?? $_SESSION['facility_settings']['moh_code']));
    $_SESSION['facility_settings']['consultation_fee'] = (float)($_POST['consultation_fee'] ?? 500.00);
    $_SESSION['facility_settings']['triage_high_bp']   = (int)($_POST['triage_high_bp'] ?? 140);

    header("Location: settings.php?msg=settings_saved");
    exit;
}

$baseDir = file_exists(__DIR__ . '/header.php') ? __DIR__ : (file_exists(dirname(__DIR__) . '/header.php') ? dirname(__DIR__) : __DIR__);
require_once $baseDir . '/header.php';
?>

<div class="space-y-6">

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'settings_saved'): ?>
    <div class="p-3.5 rounded-xl font-medium text-xs flex items-center gap-2 <?= $isDarkBlue ? 'bg-emerald-950/80 border-emerald-700 text-emerald-200' : 'bg-emerald-100 text-emerald-900 border border-emerald-300' ?>">
      <i class="fa-solid fa-circle-check text-emerald-500"></i>
      <span>Facility parameters updated successfully. New consultation fees and thresholds active.</span>
    </div>
  <?php endif; ?>

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
      <form method="POST" action="settings.php" class="space-y-4 text-xs">
        <input type="hidden" name="action" value="save_settings">

        <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2 border-b pb-1.5">
          <i class="fa-solid fa-hospital text-blue-600"></i> Facility Identity &amp; Clinical Thresholds
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Facility Name</label>
            <input type="text" name="hospital_name" value="<?= htmlspecialchars($_SESSION['facility_settings']['hospital_name']) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">MOH Registration Code</label>
            <input type="text" name="moh_code" value="<?= htmlspecialchars($_SESSION['facility_settings']['moh_code']) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">General Consultation Fee (KES)</label>
            <input type="number" step="10" name="consultation_fee" value="<?= htmlspecialchars($_SESSION['facility_settings']['consultation_fee']) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">High BP Warning Threshold (mmHg)</label>
            <input type="number" name="triage_high_bp" value="<?= htmlspecialchars($_SESSION['facility_settings']['triage_high_bp']) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-blue-500">
          </div>
        </div>

        <div class="pt-1">
          <button type="submit" class="w-full py-2.5 bg-[#06559d] hover:bg-[#05437d] text-white font-bold rounded-xl text-xs shadow-md transition flex items-center justify-center gap-2 cursor-pointer">
            <i class="fa-solid fa-save"></i>
            <span>Save Facility Configuration &rarr;</span>
          </button>
        </div>
      </form>

      <!-- ALL STAFF ROLES DIRECTORY (Strictly Admin View) -->
      <?php
        $allStaffMembers = $systemRoles;
        if (!empty($_SESSION['registered_users']) && is_array($_SESSION['registered_users'])) {
            foreach ($_SESSION['registered_users'] as $regK => $regV) {
                if (!isset($allStaffMembers[$regK])) {
                    $allStaffMembers[$regK] = $regV;
                }
            }
        }
      ?>
      <div class="pt-4 border-t border-slate-100 space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
              <i class="fa-solid fa-users-gear text-purple-600"></i> Authorized Hospital Staff Members &amp; Roles
            </h3>
            <p class="text-xs text-slate-500">Each staff member has their designated role. Only Admin can view all member roles.</p>
          </div>
          <span class="text-[10px] bg-purple-100 text-purple-900 font-bold px-2 py-0.5 rounded-full">
            <?= count($allStaffMembers) ?> Registered Members
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
              <?php foreach ($allStaffMembers as $key => $roleItem):
                $itemRole = $roleItem['role'] ?? $key;
                $itemHome = $roleItem['home'] ?? ($systemRoles[$itemRole]['home'] ?? 'dashboard.php');
              ?>
                <tr class="hover:bg-slate-50/80 transition">
                  <td class="p-2.5 font-bold text-slate-900 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg <?= $itemRole === 'admin' ? 'bg-[#06559d]' : ($itemRole === 'doctor' ? 'bg-emerald-600' : ($itemRole === 'nurse' ? 'bg-cyan-600' : ($itemRole === 'pharmacist' ? 'bg-amber-600' : ($itemRole === 'lab_tech' ? 'bg-purple-600' : 'bg-teal-600')))) ?> text-white text-[10px] font-black flex items-center justify-center shrink-0">
                      <?= strtoupper(substr($roleItem['username'] ?? $key, 0, 2)) ?>
                    </span>
                    <span><?= htmlspecialchars($roleItem['full_name'] ?? ucfirst($key)) ?></span>
                  </td>
                  <td class="p-2.5 font-mono text-slate-600"><?= htmlspecialchars($roleItem['username'] ?? $key) ?></td>
                  <td class="p-2.5">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $itemRole === 'admin' ? 'bg-blue-100 text-blue-900 font-mono' : 'bg-slate-100 text-slate-800' ?>">
                      <?= htmlspecialchars($itemRole) ?>
                    </span>
                  </td>
                  <td class="p-2.5 text-slate-600"><?= htmlspecialchars($roleItem['department'] ?? 'Hospital Administration') ?></td>
                  <td class="p-2.5 text-right font-mono text-[11px] text-blue-700">
                    <a href="<?= htmlspecialchars($itemHome) ?>" class="hover:underline font-bold"><?= htmlspecialchars($itemHome) ?> &rarr;</a>
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

</div>

<?php require_once $baseDir . '/footer.php'; ?>
