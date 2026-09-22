<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/system_stats.php';

$db = DB::tryConn();
$dbOk = $db !== null;

// Catat kunjungan ke database (setiap muat ulang halaman = 1 kunjungan baru).
if ($dbOk) {
    try {
        $stmt = $db->prepare('INSERT INTO page_visits (page) VALUES (?)');
        $stmt->execute(['home']);
    } catch (Throwable $e) {
        // Abaikan; kartu DB akan tetap dirender dari nilai default 0.
    }
}

// Ringkasan data untuk kartu DB.
$vTotal = $vToday = $uCount = 0;
$lastVisit = null;
$hourlyRows = [];
if ($dbOk) {
    try {
        $vTotal     = (int) $db->query('SELECT COUNT(*) FROM page_visits')->fetchColumn();
        $vToday     = (int) $db->query('SELECT COUNT(*) FROM page_visits WHERE DATE(visited_at) = CURDATE()')->fetchColumn();
        $uCount     = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $lastVisit  = $db->query('SELECT MAX(visited_at) FROM page_visits')->fetchColumn();
        $hourlyRows = $db->query('SELECT HOUR(visited_at) AS h, COUNT(*) AS c FROM page_visits WHERE DATE(visited_at) = CURDATE() GROUP BY HOUR(visited_at) ORDER BY h')->fetchAll();
    } catch (Throwable $e) {
        // DB masih belum siap — UI akan mencoba ulang lewat API.
    }
}

// Bar chart kunjungan per jam (CSS murni, tanpa library).
$byHour = array_fill(0, 24, 0);
foreach ($hourlyRows as $r) {
    $byHour[(int) $r['h']] = (int) $r['c'];
}
$maxHour = max(1, max($byHour));
$barsHtml = '';
foreach ($byHour as $h => $c) {
    $pct = $c > 0 ? max(5, (int) round($c / $maxHour * 100)) : 0;
    $barsHtml .= '<div class="bar-col" title="' . h(sprintf('%02d:00', $h)) . ' — ' . $c . ' kunjungan">'
        . '<div class="bar-val" style="height:' . $pct . '%"></div>'
        . '<div class="bar-label">' . h(sprintf('%02d', $h)) . '</div></div>';
}

$s = system_stats();

$init = [
    'system' => $s,
    'db'     => ['ok' => $dbOk, 'vTotal' => $vTotal, 'vToday' => $vToday, 'uCount' => $uCount, 'lastVisit' => $lastVisit],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>🛡️ AISS Dashboard — UTS</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<header class="topbar">
  <div class="brand">🛡️ <strong>AISS Dashboard</strong> <span class="muted">UTS &middot; PHP &amp; MariaDB</span></div>
  <div class="topbar-right">
    <span class="badge <?= $dbOk ? 'badge-ok' : 'badge-err' ?>" id="dbBadge"><?= $dbOk ? 'DB: OK' : 'DB: MENUNGGU' ?></span>
    <span class="clock muted" id="clock">--:--:--</span>
  </div>
</header>

<main>
  <section class="grid cards-4">
    <div class="card">
      <h3>CPU Load <small>(1 / 5 / 15 m)</small></h3>
      <div class="value mono"><span id="load1"><?= h((string) $s['load']['1']) ?></span> / <span id="load5"><?= h((string) $s['load']['5']) ?></span> / <span id="load15"><?= h((string) $s['load']['15']) ?></span></div>
      <p class="muted small">Perspektif container/host</p>
    </div>
    <div class="card">
      <h3>Memori Terpakai</h3>
      <div class="value mono" id="memText"><?= h(format_bytes($s['mem']['used'])) ?></div>
      <div class="progress"><div class="progress-fill" id="memBar" style="width:<?= h((string) $s['mem']['percent']) ?>%"></div></div>
      <p class="muted small"><span id="memMeta"><?= h((string) $s['mem']['percent']) ?>% dari <?= h(format_bytes($s['mem']['total'])) ?></span></p>
    </div>
    <div class="card">
      <h3>Disk <small>/var/www/html</small></h3>
      <div class="value mono" id="diskText"><?= h(format_bytes($s['disk']['used'])) ?></div>
      <div class="progress"><div class="progress-fill disk" id="diskBar" style="width:<?= h((string) $s['disk']['percent']) ?>%"></div></div>
      <p class="muted small"><span id="diskMeta"><?= h((string) $s['disk']['percent']) ?>% terpakai</span></p>
    </div>
    <div class="card">
      <h3>Uptime &amp; Host</h3>
      <div class="value small-value"><?= h(format_uptime($s['uptime'])) ?></div>
      <p class="muted small">Host: <span id="hostName"><?= h($s['host']) ?></span><br>PHP <?= h($s['php']) ?></p>
    </div>
  </section>

  <section class="grid cards-3">
    <div class="card">
      <h3>Total Kunjungan</h3>
      <div class="value accent" id="vTotal"><?= h((string) $vTotal) ?></div>
      <p class="muted small">Terakhir: <span id="vLast"><?= h((string) ($lastVisit ?? '-')) ?></span></p>
    </div>
    <div class="card">
      <h3>Kunjungan Hari Ini</h3>
      <div class="value accent" id="vToday"><?= h((string) $vToday) ?></div>
      <p class="muted small">Tersimpan di tabel <code>page_visits</code></p>
    </div>
    <div class="card">
      <h3>Pengguna Terdaftar</h3>
      <div class="value accent" id="uCount"><?= h((string) $uCount) ?></div>
      <p class="muted small">Tabel <code>users</code> &middot; MariaDB</p>
    </div>
  </section>

  <section class="grid cards-2">
    <div class="card">
      <h3>Kunjungan per Jam <small>hari ini</small></h3>
      <div class="barchart" id="barChart"><?= $barsHtml ?></div>
    </div>
    <div class="card">
      <h3>Tambah Pengguna</h3>
      <form id="userForm" autocomplete="off">
        <label class="field"><span>Nama</span><input type="text" name="name" required maxlength="100" placeholder="Nama lengkap"></label>
        <label class="field"><span>Email</span><input type="email" name="email" required maxlength="150" placeholder="nama@example.com"></label>
        <label class="field"><span>Role</span>
          <select name="role">
            <option value="user">user</option>
            <option value="admin">admin</option>
            <option value="analyst">analyst</option>
          </select>
        </label>
        <button class="btn" type="submit">Simpan Pengguna</button>
        <p class="muted small" id="formMsg"></p>
      </form>
    </div>
  </section>

  <section class="card">
    <h3>Data Pengguna <small>dari MariaDB</small></h3>
    <?php if ($dbOk): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>ID</th><th>Nama</th><th>Email</th><th>Role</th><th>Dibuat</th><th class="ta-r">Aksi</th></tr></thead>
        <tbody id="userRows"></tbody>
      </table>
    </div>
    <p class="muted small" id="userMsg"></p>
    <?php else: ?>
    <p class="muted">Database belum siap. Data pengguna akan muncul otomatis saat koneksi tersedia.</p>
    <?php endif; ?>
  </section>
</main>

<footer class="muted">AISS — UTS &middot; Nginx + PHP <?= h($s['php']) ?> + MariaDB + Grafana &middot; Docker Compose</footer>

<script>window.PAGE_INIT = <?= json_encode($init, JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="/assets/app.js"></script>
</body>
</html>