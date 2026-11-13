<?php

header('Content-Type: text/html; charset=utf-8');

$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$scriptPath = __DIR__;
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$serverPort = $_SERVER['SERVER_PORT'] ?? '80';
$serverHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$serverHost}{$scriptName}";
$dirName = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

// Expected core files
$files = [
    'login.php' => 'Staff Authentication Portal (Image UI)',
    'triage.php' => 'Primary Clinical Mainfile (Intake & Workstation)',
    'index.php' => 'Entry Gateway & Route Bridge',
    'consultation.php' => 'Doctor Consultation Suite',
    'pharmacy.php' => 'Pharmacy & Dispensing Module',
    'lab.php' => 'Laboratory Diagnostics Module',
    'billing.php' => 'Billing & Cashier Module',
    'registration.php' => 'Patient Intake & Registration Module',
    '.htaccess' => 'Apache DirectoryIndex & URL Rewriting',
    'ihms_database.sql' => 'MySQL Database Schema & Default Accounts',
];

// Check MySQL
$mysqlStatus = 'Offline';
$mysqlColor = '#e11d48';
$detectedPort = null;
$ports = [3306, 3307, 3308];
foreach ($ports as $port) {
    try {
        $pdo = new PDO("mysql:host=127.0.0.1;port={$port};charset=utf8mb4", 'root', '', [
            PDO::ATTR_TIMEOUT => 1,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $mysqlStatus = "Online (Port {$port})";
        $mysqlColor = '#16a34a';
        $detectedPort = $port;
        break;
    } catch (\Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IHMS XAMPP Diagnostic & 404 Resolver</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f0f7fd; margin: 0; padding: 24px; color: #1e293b; }
        .card { max-width: 860px; margin: 0 auto; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid #c7e0f6; overflow: hidden; }
        .header { background: linear-gradient(135deg, #0c477d 0%, #0260a8 100%); color: white; padding: 28px 32px; }
        .header h1 { margin: 0 0 6px 0; font-size: 22px; font-weight: 800; letter-spacing: -0.3px; }
        .header p { margin: 0; opacity: 0.9; font-size: 13px; }
        .content { padding: 28px 32px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin: 20px 0; }
        .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; }
        .info-box h4 { margin: 0 0 8px 0; font-size: 13px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-box p { margin: 0; font-family: monospace; font-size: 14px; font-weight: 600; color: #0c477d; word-break: break-all; }
        .file-list { margin: 24px 0; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
        .file-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #f1f5f9; }
        .file-row:last-child { border-bottom: none; }
        .file-row:nth-child(even) { background: #f8fafc; }
        .btn { display: inline-flex; align-items: center; gap: 8px; background: #0260a8; color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 700; transition: background 0.2s; }
        .btn:hover { background: #0c477d; }
        .btn-outline { background: white; border: 2px solid #0260a8; color: #0260a8; }
        .btn-outline:hover { background: #f0f7fd; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .alert { background: #eff6ff; border-left: 4px solid #0260a8; padding: 14px 18px; border-radius: 0 8px 8px 0; margin-bottom: 20px; font-size: 13px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>XAMPP Apache Diagnostic & Route Resolver</h1>
            <p>Integrated Hospital Management System (IHMS) • Sam Junior Matumbayi (Reg: DSE-02-0171/2025)</p>
        </div>
        <div class="content">
            <div class="alert">
                <strong>Why did you see "404 Not Found"?</strong><br>
                Apache shows 404 when the browser requests a URL that does not match the actual folder name in <code>C:\xampp\htdocs\</code>.
                Below is the verified working path to launch your system immediately.
            </div>

            <div class="grid">
                <div class="info-box">
                    <h4>Current File Location on Disk</h4>
                    <p><?= htmlspecialchars($scriptPath) ?></p>
                </div>
                <div class="info-box">
                    <h4>Apache Document Root</h4>
                    <p><?= htmlspecialchars($docRoot) ?></p>
                </div>
                <div class="info-box">
                    <h4>Active URL In Browser</h4>
                    <p><?= htmlspecialchars($currentUrl) ?></p>
                </div>
                <div class="info-box">
                    <h4>MySQL Database Engine</h4>
                    <p style="color: <?= $mysqlColor ?>;"><?= $mysqlStatus ?></p>
                </div>
            </div>

            <h3 style="font-size: 16px; margin: 24px 0 12px; color: #0c477d;">Click to Open Application Modules</h3>
            <div class="actions" style="margin-top: 0; padding-top: 0; border-top: none;">
                <a href="<?= htmlspecialchars(($dirName ? $dirName : '') . '/login.php') ?>" class="btn">
                    Launch Login Page (image_185d23c7.png) &rarr;
                </a>
                <a href="<?= htmlspecialchars(($dirName ? $dirName : '') . '/triage.php') ?>" class="btn btn-outline">
                    Launch Mainfile (triage.php) &rarr;
                </a>
                <a href="<?= htmlspecialchars(($dirName ? $dirName : '') . '/index.php') ?>" class="btn btn-outline">
                    Launch Gateway (index.php) &rarr;
                </a>
            </div>

            <h3 style="font-size: 16px; margin: 30px 0 12px; color: #0c477d;">File Integrity Check in Current Directory</h3>
            <div class="file-list">
                <?php foreach ($files as $fileName => $desc): ?>
                    <?php $exists = file_exists(__DIR__ . '/' . $fileName); ?>
                    <div class="file-row">
                        <div>
                            <strong style="font-family: monospace; font-size: 14px; color: #0c477d;"><?= $fileName ?></strong>
                            <div style="font-size: 12px; color: #64748b;"><?= $desc ?></div>
                        </div>
                        <div>
                            <?php if ($exists): ?>
                                <span class="badge badge-success">OK (Found)</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Missing / Not in folder</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 20px; margin-top: 24px;">
                <h4 style="margin: 0 0 10px; color: #0f172a; font-size: 14px;">How to avoid 404 errors in XAMPP:</h4>
                <ol style="margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8; color: #334155;">
                    <li>Ensure all files are placed directly in: <code>C:\xampp\htdocs\HMS\</code> (not inside another subfolder like <code>C:\xampp\htdocs\HMS\HMS\</code>).</li>
                    <li>In your browser address bar, open: <strong><a href="http://localhost/HMS/login.php" style="color: #0260a8;">http://localhost/HMS/login.php</a></strong> or <strong><a href="http://localhost/HMS/triage.php" style="color: #0260a8;">http://localhost/HMS/triage.php</a></strong>.</li>
                    <li>Check that Windows hasn't saved files as <code>.php.txt</code> by unchecking <em>"Hide extensions for known file types"</em> in Windows File Explorer.</li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>
