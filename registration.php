<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Form Submission BEFORE header rendering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_patient') {
    $pName  = trim((string)($_POST['full_name'] ?? 'New Patient'));
    $phone  = trim((string)($_POST['phone'] ?? '+254 700 000 000'));
    $gender = trim((string)($_POST['gender'] ?? 'Male'));
    $scheme = trim((string)($_POST['payment_scheme'] ?? 'Cash'));
    $newId  = count($_SESSION['patients'] ?? []) + 25;
    $opd    = 'OPD-2025-' . str_pad((string)$newId, 3, '0', STR_PAD_LEFT);

    if (!isset($_SESSION['patients'])) {
        $_SESSION['patients'] = [];
    }

    array_unshift($_SESSION['patients'], [
        'patient_id'     => $newId,
        'opd_number'     => $opd,
        'full_name'      => $pName,
        'gender'         => $gender,
        'phone'          => $phone,
        'payment_scheme' => $scheme,
        'current_status' => 'Registered'
    ]);

    header("Location: triage.php?patient_id={$newId}&msg=patient_registered");
    exit;
}

$baseDir = file_exists(__DIR__ . '/header.php') ? __DIR__ : (file_exists(dirname(__DIR__) . '/header.php') ? dirname(__DIR__) : __DIR__);
require_once $baseDir . '/header.php';
?>

<div class="space-y-6">

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'patient_registered'): ?>
    <div class="p-3.5 rounded-xl font-medium text-xs flex items-center gap-2 <?= $isDarkBlue ? 'bg-emerald-950/80 border-emerald-700 text-emerald-200' : 'bg-emerald-100 text-emerald-900 border border-emerald-300' ?>">
      <i class="fa-solid fa-circle-check text-emerald-500"></i>
      <span>Patient registered successfully in Master Index. Routed to Triage Station.</span>
    </div>
  <?php endif; ?>

  <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm max-w-3xl mx-auto space-y-4 text-slate-800">
    <div class="border-b border-slate-100 pb-3 flex justify-between items-center">
      <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
          <i class="fa-solid fa-user-plus text-[#06559d]"></i> Patient Intake &amp; Registration Form
        </h2>
        <p class="text-xs text-slate-500">Capture national ID, demographics, contact, and payment schemes for OPD admission.</p>
      </div>
      <span class="px-2.5 py-1 bg-blue-100 text-[#06559d] rounded-full text-xs font-bold font-mono">Module 2</span>
    </div>

    <form method="POST" action="registration.php" class="space-y-4">
      <input type="hidden" name="action" value="register_patient">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Full Patient Name *</label>
          <input type="text" name="full_name" required placeholder="e.g. Dennis Kipchoge" class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Phone Number *</label>
          <input type="text" name="phone" required placeholder="+254 7XX XXX XXX" class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
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

      <div class="pt-2">
        <button type="submit" class="w-full py-2.5 bg-[#06559d] hover:bg-[#05437d] text-white font-bold rounded-xl text-xs shadow-md transition flex items-center justify-center gap-2 cursor-pointer">
          <i class="fa-solid fa-save"></i>
          <span>Save Patient &amp; Route to Triage Desk &rarr;</span>
        </button>
      </div>
    </form>
  </div>

  <!-- Intake Queue Table -->
  <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm max-w-3xl mx-auto space-y-3">
    <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2 border-b pb-2">
      <i class="fa-solid fa-list-ol text-[#06559d]"></i> Recently Registered Admissions
    </h3>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200 text-slate-600">
            <th class="p-2 font-bold">OPD #</th>
            <th class="p-2 font-bold">Patient Name</th>
            <th class="p-2 font-bold">Scheme</th>
            <th class="p-2 font-bold">Status</th>
            <th class="p-2 font-bold text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach (array_slice($_SESSION['patients'] ?? [], 0, 5) as $rp): ?>
            <tr>
              <td class="p-2 font-mono text-[#06559d]"><?= htmlspecialchars($rp['opd_number']) ?></td>
              <td class="p-2 font-bold text-slate-800"><?= htmlspecialchars($rp['full_name']) ?></td>
              <td class="p-2 text-slate-600"><?= htmlspecialchars($rp['payment_scheme']) ?></td>
              <td class="p-2"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800"><?= htmlspecialchars($rp['current_status']) ?></span></td>
              <td class="p-2 text-right">
                <a href="triage.php?patient_id=<?= $rp['patient_id'] ?>" class="px-2.5 py-1 bg-[#06559d] hover:bg-[#00407a] text-white rounded-lg font-bold text-[11px] transition">
                  Triage &rarr;
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once $baseDir . '/footer.php'; ?>
