<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

$db = DB::tryConn();
if ($db === null) {
    json_response(['ok' => false, 'error' => 'Database belum tersedia.'], 503);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $in = json_decode((string) file_get_contents('php://input'), true);
    $in = is_array($in) ? $in : [];
    $page = trim((string) ($in['page'] ?? 'app'));
    $page = mb_substr($page, 0, 255);

    $stmt = $db->prepare('INSERT INTO page_visits (page) VALUES (?)');
    $stmt->execute([$page]);
    json_response(['ok' => true]);
}

if ($method === 'GET') {
    $total = (int) $db->query('SELECT COUNT(*) FROM page_visits')->fetchColumn();
    $today = (int) $db->query('SELECT COUNT(*) FROM page_visits WHERE DATE(visited_at) = CURDATE()')->fetchColumn();
    $last  = $db->query('SELECT MAX(visited_at) FROM page_visits')->fetchColumn();
    json_response(['ok' => true, 'total' => $total, 'today' => $today, 'last' => $last]);
}

json_response(['ok' => false, 'error' => 'Method tidak didukung.'], 405);