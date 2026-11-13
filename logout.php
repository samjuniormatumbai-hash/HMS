<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables
$_SESSION = [];

// Delete session cookie if configured
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Base-aware redirect to login.php or index.php?tab=login
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$loginTarget = file_exists(__DIR__ . '/login.php') ? 'login.php?logout=1' : 'index.php?tab=login&logout=1';
$url = ($scriptDir === '' ? '' : $scriptDir) . '/' . $loginTarget;

if (!headers_sent()) {
    header("Location: " . $url);
}
echo "<!DOCTYPE html><html><head><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url, ENT_QUOTES) . "'><script>window.location.replace(" . json_encode($url) . ");</script></head><body style='font-family:sans-serif;padding:30px;background:#f0f7fd;color:#0c477d;'><h3>Signing out of AfyaSmart...</h3><p><a href='" . htmlspecialchars($url, ENT_QUOTES) . "'>Click here if not redirected automatically</a></p></body></html>";
exit;
