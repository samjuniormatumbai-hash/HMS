<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Africa/Nairobi');

// Helper function
function safe_str($v, $d = '') { return (isset($v) && !is_array($v)) ? trim((string)$v) : $d; }
function safe_int($v, $d = 0) { return (isset($v) && is_numeric($v)) ? (int)$v : $d; }

// Active Selected Patient
$pid = safe_int($_GET['patient_id'] ?? null, 23);
$patient = null;
if (isset($_SESSION['patients']) && is_array($_SESSION['patients'])) {
    foreach ($_SESSION['patients'] as $p) {
        if ($p['patient_id'] === $pid) {
            $patient = $p;
            break;
        }
    }
}
if (!$patient) {
    $patient = [
        'patient_id'     => $pid,
        'opd_number'     => 'OPD-2025-023',
        'full_name'      => 'Matty Jeff',
        'gender'         => 'Female',
        'phone'          => '+254 712 998 877',
        'payment_scheme' => 'SHA',
        'current_status' => 'Discharged'
    ];
}

$facility = $_SESSION['facility_settings'] ?? [
    'hospital_name'    => 'AfyaSmart Integrated Health Centre',
    'moh_code'         => '14892',
    'facility_level'   => '',
    'consultation_fee' => 500.00
];

$consultationFee = (float)($facility['consultation_fee'] ?? 500.00);
$diagnosticFee = 1200.00;
$pharmacyFee = 1550.00;
$totalPaid = $consultationFee + 2750.00;
$receiptNo = 'REC-' . (70000 + $pid * 85 + 7);
$issueDate = date('d M Y - H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Official Medical Receipt #<?= $receiptNo ?> - AfyaSmart IHMS</title>
  <style>
    /* Clean Universal Print Styles - Guaranteed Crisp Rendering in Edge & Chrome */
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background-color: #f1f5f9;
      color: #0f172a;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 30px 15px;
      min-height: 100vh;
    }
    .print-actions {
      display: flex;
      gap: 12px;
      margin-bottom: 20px;
    }
    .btn {
      padding: 10px 20px;
      font-size: 13px;
      font-weight: 700;
      border-radius: 8px;
      cursor: pointer;
      border: none;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: background 0.15s;
    }
    .btn-primary {
      background-color: #06559d;
      color: #ffffff;
    }
    .btn-primary:hover {
      background-color: #05437d;
    }
    .btn-secondary {
      background-color: #e2e8f0;
      color: #334155;
    }
    .btn-secondary:hover {
      background-color: #cbd5e1;
    }

    /* Thermal 80mm & A4 Optimized Card */
    .receipt-container {
      width: 100%;
      max-width: 420px;
      background-color: #ffffff;
      border: 2px dashed #000000;
      border-radius: 10px;
      padding: 24px;
      font-size: 13px;
      line-height: 1.45;
      box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }
    .text-center { text-align: center; }
    .uppercase { text-transform: uppercase; }
    .font-mono { font-family: "Courier New", Courier, monospace; }
    .font-bold { font-weight: 700; }
    .font-black { font-weight: 900; }
    .header-section {
      border-bottom: 1.5px dashed #000000;
      padding-bottom: 12px;
      margin-bottom: 14px;
      text-align: center;
    }
    .hospital-title {
      font-size: 16px;
      font-weight: 900;
      color: #000000;
      letter-spacing: 0.5px;
    }
    .sub-title {
      font-size: 10px;
      color: #475569;
      margin-top: 2px;
    }
    .receipt-badge {
      display: inline-block;
      margin-top: 8px;
      background: #f1f5f9;
      border: 1px solid #cbd5e1;
      padding: 4px 10px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 700;
    }
    .row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 6px;
    }
    .divider {
      border-top: 1.5px dashed #000000;
      margin: 12px 0 10px 0;
    }
    .total-row {
      border-top: 1.5px solid #000000;
      padding-top: 6px;
      margin-top: 6px;
      font-size: 15px;
      font-weight: 900;
    }
    .status-stamp {
      text-align: center;
      margin-top: 14px;
      padding-top: 8px;
      border-top: 1px dashed #cbd5e1;
    }
    .stamp-text {
      color: #047857;
      font-weight: 900;
      font-size: 14px;
      letter-spacing: 1px;
    }
    .footer-note {
      font-size: 9.5px;
      color: #64748b;
      margin-top: 6px;
      line-height: 1.3;
    }

    /* Print Driver Override */
    @media print {
      body {
        background: #ffffff !important;
        padding: 0 !important;
        margin: 0 !important;
        color: #000000 !important;
      }
      .print-actions {
        display: none !important;
      }
      .receipt-container {
        max-width: 100% !important;
        width: 100% !important;
        margin: 0 auto !important;
        border: 2px dashed #000000 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        page-break-inside: avoid !important;
      }
      @page {
        margin: 5mm;
        size: auto;
      }
    }
  </style>
</head>
<body>

  <!-- Screen Action Bar (Hidden on Print) -->
  <div class="print-actions">
    <button class="btn btn-primary" onclick="window.print()">
      &#128424; Print Receipt Now
    </button>
    <a href="receipt.php?patient_id=<?= $pid ?>" class="btn btn-secondary">
      &larr; Back to System
    </a>
    <a href="dashboard.php" class="btn btn-secondary">
      Dashboard
    </a>
  </div>

  <!-- Thermal & A4 Official Receipt Content -->
  <div class="receipt-container" id="receiptCard">
    
    <div class="header-section">
      <div class="hospital-title uppercase"><?= htmlspecialchars($facility['hospital_name']) ?></div>
      <div class="sub-title">MOH Code: <?= htmlspecialchars($facility['moh_code']) ?></div>
      <div class="receipt-badge font-mono">OFFICIAL MEDICAL RECEIPT #<?= $receiptNo ?></div>
    </div>

    <div class="row">
      <span>Patient:</span>
      <strong class="font-bold"><?= htmlspecialchars($patient['full_name']) ?></strong>
    </div>
    <div class="row">
      <span>OPD Number:</span>
      <span class="font-mono font-bold"><?= htmlspecialchars($patient['opd_number']) ?></span>
    </div>
    <div class="row">
      <span>Payment Scheme:</span>
      <span class="font-bold"><?= htmlspecialchars($patient['payment_scheme']) ?></span>
    </div>
    <div class="row">
      <span>Payment Mode:</span>
      <strong class="font-mono" style="color: #047857;">M-Pesa STK (Settled)</strong>
    </div>
    <div class="row">
      <span>Date &amp; Time:</span>
      <span class="font-mono"><?= $issueDate ?></span>
    </div>

    <div class="divider"></div>

    <div class="row font-mono">
      <span>General Outpatient Consultation:</span>
      <span>KES <?= number_format($consultationFee, 2) ?></span>
    </div>
    <div class="row font-mono">
      <span>Diagnostic Lab Tests (mRDT &amp; CBC):</span>
      <span>KES <?= number_format($diagnosticFee, 2) ?></span>
    </div>
    <div class="row font-mono">
      <span>Pharmacy Formulary Medications:</span>
      <span>KES <?= number_format($pharmacyFee, 2) ?></span>
    </div>

    <div class="row total-row font-mono">
      <span>TOTAL PAID:</span>
      <span>KES <?= number_format($totalPaid, 2) ?></span>
    </div>

    <div class="status-stamp">
      <div class="stamp-text font-mono">&check; PAID &amp; CLEARED</div>
      <div class="footer-note">
        AfyaSmart Integrated Hospital Management System (IHMS)<br>
        Zetech University &bull; Sam Junior Matumbayi (DSE-02-0171/2025)<br>
        <em>Valid Official Computer Generated Tax Invoice &amp; Clearance Slip</em>
      </div>
    </div>

  </div>

  <script>
    // Automatically trigger print dialog on page load
    window.addEventListener('load', function() {
      // Small timeout allows fonts and CSS to settle before spooling
      setTimeout(function() {
        window.print();
      }, 350);
    });
  </script>

</body>
</html>
