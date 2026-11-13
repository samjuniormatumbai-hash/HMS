<?php


if (file_exists(__DIR__ . '/db_connect.php')) {
    require_once __DIR__ . '/db_connect.php';
} elseif (file_exists(__DIR__ . '/config/db_connect.php')) {
    require_once __DIR__ . '/config/db_connect.php';
} elseif (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = null;
    if (class_exists('Database')) {
        try {
            $pdo = Database::getConnection();
        } catch (\Throwable $e) {
            $pdo = null;
        }
    }
    if (!$pdo) {
        foreach ([3306, 3307, 3308] as $port) {
            try {
                $pdo = new PDO(
                    "mysql:host=127.0.0.1;port={$port};charset=utf8mb4",
                    'root',
                    '',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_TIMEOUT => 2,
                    ]
                );
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `ihms_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `ihms_db`");
                break;
            } catch (\Throwable $e) {
                $pdo = null;
            }
        }
    }
}
