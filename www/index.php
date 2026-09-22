<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

// Catat kunjungan halaman (untuk analytics di Grafana).
$db = DB::tryConn();
if ($db !== null) {
    try {
        $stmt = $db->prepare('INSERT INTO page_visits (page) VALUES (?)');
        $stmt->execute([$_SERVER['REQUEST_URI'] ?? '/']);
    } catch (Throwable $e) {
        // DB belum siap — diabaikan.
    }
}

// Routing front-controller:
//   "/"          → landing page (katalog produk dari MariaDB)
//   "/dashboard" → dashboard aplikasi (statistik sistem + CRUD)
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/');
$path = $path === '' ? '/' : $path;

if ($path === '/dashboard') {
    require __DIR__ . '/views/dashboard.php';
} else {
    require __DIR__ . '/views/landing.php';
}