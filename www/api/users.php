<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

$db = DB::tryConn();
if ($db === null) {
    json_response(['ok' => false, 'error' => 'Database belum tersedia.'], 503);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $db->query('SELECT id, name, email, role, created_at FROM users ORDER BY id DESC LIMIT 100')->fetchAll();
    json_response(['ok' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    $in = json_decode((string) file_get_contents('php://input'), true);
    $in = is_array($in) ? $in : [];
    $name  = trim((string) ($in['name'] ?? ''));
    $email = trim((string) ($in['email'] ?? ''));
    $role  = trim((string) ($in['role'] ?? 'user'));

    if ($name === '' || $email === '') {
        json_response(['ok' => false, 'error' => 'Nama dan email wajib diisi.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['ok' => false, 'error' => 'Format email tidak valid.'], 422);
    }

    $stmt = $db->prepare('INSERT INTO users (name, email, role) VALUES (?, ?, ?)');
    try {
        $stmt->execute([$name, $email, $role]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate entry')) {
            json_response(['ok' => false, 'error' => 'Email sudah terdaftar.'], 409);
        }
        json_response(['ok' => false, 'error' => 'Gagal menyimpan data.'], 500);
    }
    json_response(['ok' => true, 'id' => (int) $db->lastInsertId()]);
}

if ($method === 'DELETE') {
    $in = json_decode((string) file_get_contents('php://input'), true);
    $in = is_array($in) ? $in : [];
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'ID tidak valid.'], 422);
    }
    $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    json_response(['ok' => true, 'deleted' => $stmt->rowCount() > 0]);
}

json_response(['ok' => false, 'error' => 'Method tidak didukung.'], 405);