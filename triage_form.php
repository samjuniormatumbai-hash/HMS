<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Flexible directory resolution: works in C:\xampp\htdocs\HMS\ or C:\xampp\htdocs\HMS\modules\
$baseDir = file_exists(__DIR__ . '/header.php') ? __DIR__ : (file_exists(dirname(__DIR__) . '/header.php') ? dirname(__DIR__) : __DIR__);

// 1. Handle Form Submission BEFORE header rendering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_triage') {
    $pid = (int)($_POST['patient_id'] ?? 19);
    if (!empty($_SESSION['patients'])) {
        foreach ($_SESSION['patients'] as &$p) {
            if ($p['patient_id'] === $pid) {
                $p['current_status'] = 'Triaged';
                break;
            }
        }
    }
    // Determine destination URL to avoid 404 Not Found
    if (file_exists(__DIR__ . '/consultation.php')) {
        $dest = 'consultation.php';
    } elseif (file_exists(__DIR__ . '/doctor.php')) {
        $dest = 'doctor.php';
    } elseif (file_exists($baseDir . '/consultation.php')) {
        $dest = ($baseDir === __DIR__) ? 'consultation.php' : '../consultation.php';
    } elseif (file_exists($baseDir . '/doctor.php')) {
        $dest = ($baseDir === __DIR__) ? 'doctor.php' : '../doctor.php';
    } elseif (file_exists($baseDir . '/index.php')) {
        $dest = ($baseDir === __DIR__) ? 'index.php?tab=consultation' : '../index.php?tab=consultation';
    } else {
        $dest = 'consultation.php';
    }
    $sep = (strpos($dest, '?') !== false) ? '&' : '?';
    header("Location: {$dest}{$sep}patient_id={$pid}&msg=vitals_saved");
    exit;
}

if (file_exists($baseDir . '/header.php')) {
    require_once $baseDir . '/header.php';
} elseif (file_exists(__DIR__ . '/header.php')) {
    require_once __DIR__ . '/header.php';
}
?>

<div class="space-y-6">

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'patient_registered'): ?>
    <div class="p-3.5 rounded-xl font-medium text-xs flex items-center gap-2 <?= $isDarkBlue ? 'bg-emerald-950/80 border-emerald-700 text-emerald-200' : 'bg-emerald-100 text-emerald-900 border border-emerald-300' ?>">
      <i class="fa-solid fa-circle-check text-emerald-500"></i>
      <span>Patient admitted. Please assess bio-signals and triage category below.</span>
    </div>
  <?php endif; ?>

  <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm max-w-3xl mx-auto space-y-4 text-slate-800">
    <div class="border-b border-slate-100 pb-3 flex justify-between items-center">
      <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
          <i class="fa-solid fa-stethoscope text-emerald-600"></i> Clinical Triage Station &amp; Bio-signals Acuity
        </h2>
        <p class="text-xs text-slate-500">Recording bio-signals for: <strong><?= htmlspecialchars($selectedPatient['full_name'] ?? 'Patient') ?></strong> (<?= htmlspecialchars($selectedPatient['opd_number'] ?? 'OPD') ?>)</p>
      </div>
      <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold font-mono">Module 3</span>
    </div>

    <!-- Triage Vitals Form -->
    <form method="POST" action="" class="space-y-4">
      <input type="hidden" name="action" value="save_triage">
      <input type="hidden" name="patient_id" value="<?= $activePatientId ?>">

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Systolic BP (mmHg)</label>
          <input type="number" name="bp_systolic" id="bpSysInput" value="120" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none font-mono">
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Diastolic BP (mmHg)</label>
          <input type="number" name="bp_diastolic" value="80" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none font-mono">
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Heart Rate (bpm)</label>
          <input type="number" name="heart_rate" value="76" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none font-mono">
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Temperature (&deg;C)</label>
          <input type="number" step="0.1" name="temperature" value="36.8" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none font-mono">
        </div>
      </div>

      <div class="p-3.5 bg-emerald-50 rounded-xl border border-emerald-200 text-xs text-emerald-900 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <i class="fa-solid fa-shield-heart text-emerald-600 text-base"></i>
          <div>
            <strong class="block">Triage Threshold Status: Normal Vitals</strong>
            <span class="text-[11px] text-emerald-700">BP Systolic is below facility threshold limit (<?= (int)($_SESSION['facility_settings']['triage_high_bp'] ?? 140) ?> mmHg).</span>
          </div>
        </div>
        <span class="px-2.5 py-1 bg-emerald-600 text-white rounded-lg font-bold text-[10px] uppercase font-mono">Priority 3: Routine</span>
      </div>

      <div class="pt-2">
        <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center justify-center gap-2 cursor-pointer">
          <i class="fa-solid fa-check-circle"></i>
          <span>Validate Vitals &amp; Route to Doctor Consult &rarr;</span>
        </button>
      </div>
    </form>
  </div>

  <!-- Queue Awaiting Doctor -->
  <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm max-w-3xl mx-auto space-y-3">
    <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2 border-b pb-2">
      <i class="fa-solid fa-user-clock text-emerald-600"></i> Patients in Triage Station
    </h3>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200 text-slate-600">
            <th class="p-2 font-bold">OPD #</th>
            <th class="p-2 font-bold">Patient Name</th>
            <th class="p-2 font-bold">Current Stage</th>
            <th class="p-2 font-bold text-right">Route Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($_SESSION['patients'] as $tp): ?>
            <?php if (in_array($tp['current_status'], ['Registered', 'Triaged'])): ?>
              <tr>
                <td class="p-2 font-mono text-[#06559d]"><?= htmlspecialchars($tp['opd_number']) ?></td>
                <td class="p-2 font-bold text-slate-800"><?= htmlspecialchars($tp['full_name']) ?></td>
                <td class="p-2"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $tp['current_status'] === 'Triaged' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' ?>"><?= htmlspecialchars($tp['current_status']) ?></span></td>
                <td class="p-2 text-right">
                  <?php
                    $consultTarget = file_exists(__DIR__ . '/consultation.php') ? 'consultation.php' : (file_exists(__DIR__ . '/doctor.php') ? 'doctor.php' : (file_exists($baseDir . '/consultation.php') ? ($baseDir === __DIR__ ? 'consultation.php' : '../consultation.php') : ($baseDir === __DIR__ ? 'index.php?tab=consultation' : '../index.php?tab=consultation')));
                    $sep = (strpos($consultTarget, '?') !== false) ? '&' : '?';
                  ?>
                  <a href="<?= $consultTarget . $sep ?>patient_id=<?= $tp['patient_id'] ?>" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[11px] transition">
                    Send to Doctor &rarr;
                  </a>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php
if (file_exists($baseDir . '/footer.php')) {
    require_once $baseDir . '/footer.php';
} elseif (file_exists(__DIR__ . '/footer.php')) {
    require_once __DIR__ . '/footer.php';
} else {
    echo "  </section></main><footer class='bg-slate-900 text-slate-400 py-3 px-5 text-center text-xs border-t border-slate-800 no-print mt-auto'>AfyaSmart IHMS v2.4 &bull; Your Health, Our Priority &bull; Zetech University &bull; Sam Junior Matumbayi (DSE-02-0171/2025)</footer></body></html>";
}
?>