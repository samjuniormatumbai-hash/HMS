<?php

function getIHMSConnection(): ?PDO {
    $candidates = [
        ['host' => '127.0.0.1', 'port' => 3306],
        ['host' => '127.0.0.1', 'port' => 3307],
        ['host' => '127.0.0.1', 'port' => 3308],
        ['host' => 'localhost', 'port' => 3306],
        ['host' => 'localhost', 'port' => 3307],
    ];

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    foreach ($candidates as $c) {
        $h = $c['host'];
        $p = $c['port'];
        try {
            $dsn = "mysql:host={$h};port={$p};dbname=ihms_db;charset=utf8mb4";
            return new PDO($dsn, 'root', '', $options);
        } catch (PDOException $e) {
            continue;
        }
    }
    return null;
}