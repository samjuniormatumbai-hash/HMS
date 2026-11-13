<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$baseDir = file_exists(__DIR__ . '/header.php') ? __DIR__ : (file_exists(dirname(__DIR__) . '/header.php') ? dirname(__DIR__) : __DIR__);
require_once $baseDir . '/header.php';
?>

<div class="space-y-6">

  <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm max-w-4xl mx-auto space-y-4 text-slate-800">
    <div class="border-b border-slate-100 pb-3 flex justify-between items-center">
      <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
          <i class="fa-solid fa-shield-halved text-[#06559d]"></i> Security Audit Trail &amp; Activity Log
        </h2>
        <p class="text-xs text-slate-500">Immutable ledger tracking clinical logins, patient admissions, prescriptions, lab results, and financial settlements.</p>
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
          <tr>
            <td class="p-2.5"><?= date('H:i:s') ?></td>
            <td class="p-2.5 font-bold">admin</td>
            <td class="p-2.5 text-blue-700">admin</td>
            <td class="p-2.5 text-emerald-700 font-bold">SESSION_AUTH</td>
            <td class="p-2.5">Command Centre</td>
          </tr>
          <tr>
            <td class="p-2.5"><?= date('H:i:s', time() - 60) ?></td>
            <td class="p-2.5 font-bold">accountant</td>
            <td class="p-2.5 text-teal-700">accountant</td>
            <td class="p-2.5 text-emerald-700 font-bold">SETTLE_MPESA_BILL</td>
            <td class="p-2.5">Cashier POS</td>
          </tr>
          <tr>
            <td class="p-2.5"><?= date('H:i:s', time() - 180) ?></td>
            <td class="p-2.5 font-bold">pharmacist</td>
            <td class="p-2.5 text-amber-700">pharmacist</td>
            <td class="p-2.5 text-amber-600 font-bold">DISPENSE_MEDS</td>
            <td class="p-2.5">Pharmacy Dispensary</td>
          </tr>
          <tr>
            <td class="p-2.5"><?= date('H:i:s', time() - 300) ?></td>
            <td class="p-2.5 font-bold">lab_tech</td>
            <td class="p-2.5 text-purple-700">lab_tech</td>
            <td class="p-2.5 text-purple-700 font-bold">VALIDATE_MALARIA_RDT</td>
            <td class="p-2.5">Diagnostic Pathology</td>
          </tr>
          <tr>
            <td class="p-2.5"><?= date('H:i:s', time() - 480) ?></td>
            <td class="p-2.5 font-bold">doctor</td>
            <td class="p-2.5 text-emerald-700">doctor</td>
            <td class="p-2.5 text-purple-700 font-bold">DIAGNOSIS_SIGN</td>
            <td class="p-2.5">Consultation</td>
          </tr>
          <tr>
            <td class="p-2.5"><?= date('H:i:s', time() - 720) ?></td>
            <td class="p-2.5 font-bold">nurse</td>
            <td class="p-2.5 text-cyan-700">nurse</td>
            <td class="p-2.5 text-blue-700 font-bold">RECORD_VITALS</td>
            <td class="p-2.5">Triage Desk</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once $baseDir . '/footer.php'; ?>
