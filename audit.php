<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$logs = $pdo->query("SELECT * FROM audit_logs ORDER BY log_id DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Security Audit Trail - IHMS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="top-header">
    <div class="brand">
      <h1>IHMS HOSPITAL SYSTEM</h1>
      <small>System Security & Compliance Audit Trail</small>
    </div>
    <div class="user-badge">
      <span>Admin: <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong></span>
      <a href="logout.php" class="btn-exit">Exit</a>
    </div>
  </header>

  <nav class="dept-nav">
    <a href="index.php">Dashboard</a>
    <a href="triage.php"> Triage Desk</a>
    <a href="consultation.php"> Doctor Workstation</a>
    <a href="lab.php">Laboratory Desk</a>
    <a href="pharmacy.php"> Pharmacy & Inventory</a>
    <a href="billing.php"> Invoicing & Billing</a>
    <a href="patients.php">EHR Patients</a>
    <a href="audit.php" class="active">Audit Logs</a>
  </nav>

  <main class="main-wrapper">
    <div class="wireframe-box">
      <div class="wireframe-title-bar">
        SECURITY AUDIT TRAIL (KENYA DATA PROTECTION ACT 2019)
      </div>

      <div class="inner-card">
        <h3>System Transaction & Activity Log</h3>
        <table class="data-table">
          <thead>
            <tr>
              <th>Log ID</th>
              <th>User Role</th>
              <th>Action Type</th>
              <th>Module</th>
              <th>Activity Details</th>
              <th>IP Address</th>
              <th>Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td>#<?= $l['log_id'] ?></td>
                <td><strong><?= htmlspecialchars($l['user_role'] ?? 'system') ?></strong></td>
                <td><span style="color: #38bdf8; font-weight: bold;"><?= htmlspecialchars($l['action']) ?></span></td>
                <td><?= htmlspecialchars($l['module']) ?></td>
                <td style="max-width: 300px;"><?= htmlspecialchars($l['details']) ?></td>
                <td><?= htmlspecialchars($l['ip_address'] ?? '127.0.0.1') ?></td>
                <td style="color: #94a3b8; font-size: 0.75rem;"><?= htmlspecialchars($l['logged_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</body>
</html>