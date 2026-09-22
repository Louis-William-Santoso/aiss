<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Ambil katalog produk/solusi dari MariaDB (data dummy di init SQL).
$db = DB::tryConn();
$products = [];
if ($db !== null) {
    try {
        $products = $db->query(
            'SELECT name, category, description, price FROM products WHERE is_active = 1 ORDER BY id'
        )->fetchAll();
    } catch (Throwable $e) {
        $products = [];
    }
}

function format_price(mixed $price): string
{
    if ($price === null || $price === '') {
        return 'Hubungi kami';
    }
    return 'Rp ' . number_format((float) $price, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>🛡️ SemogaSukses.com — Produk & Solusi</title>
<style>
  :root {
    --bg:#0b1220; --panel:#111a2e; --border:#1f2b45; --text:#e6edf7;
    --muted:#8fa3c4; --accent:#3fa9f5; --green:#43d9a0; --amber:#f5b93f; --red:#f87171;
  }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { background:var(--bg); color:var(--text); font-family:"Segoe UI",system-ui,sans-serif; min-height:100vh; padding-bottom:24px; }
  .wrap { max-width:1080px; margin:0 auto; padding:32px 24px; }
  .nav { display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:16px; flex-wrap:wrap; gap:10px; }
  .logo { font-size:19px; font-weight:700; }
  .logo span { color:var(--accent); }
  .nav a { color:var(--muted); text-decoration:none; font-size:14px; margin-left:18px; }
  .nav a:hover { color:var(--text); }

  .hero { padding:56px 0 36px; text-align:center; }
  .hero h1 { font-size:clamp(28px, 5vw, 46px); line-height:1.15; }
  .hero h1 em { color:var(--accent); font-style:normal; }
  .hero p { color:var(--muted); max-width:640px; margin:16px auto 0; font-size:16px; line-height:1.6; }
  .hero .links { margin-top:24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap; }
  .btn { display:inline-block; border-radius:10px; padding:11px 20px; font-size:14px; font-weight:600; text-decoration:none; border:1px solid var(--border); color:var(--text); background:var(--panel); transition:.2s; }
  .btn.primary { background:linear-gradient(90deg,#0284c7,#0ea5e9); border:none; }
  .btn:hover { transform:translateY(-2px); }

  .section-title { margin:40px 0 16px; display:flex; align-items:baseline; gap:10px; }
  .section-title h2 { font-size:20px; }
  .section-title span { color:var(--muted); font-size:13px; }

  .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
  .card { background:var(--panel); border:1px solid var(--border); border-radius:14px; padding:20px; display:flex; flex-direction:column; gap:10px; transition:.2s; }
  .card:hover { transform:translateY(-3px); border-color:var(--accent); }
  .card .row-top { display:flex; justify-content:space-between; align-items:center; gap:8px; }
  .card h3 { font-size:16px; }
  .badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:999px; letter-spacing:.4px; }
  .badge.product  { color:var(--accent); border:1px solid var(--accent); background:rgba(63,169,245,.1); }
  .badge.solution { color:var(--green); border:1px solid var(--green); background:rgba(67,217,160,.1); }
  .card p { color:var(--muted); font-size:13.5px; line-height:1.55; flex:1; }
  .card .price { font-weight:700; color:var(--text); font-size:15px; }
  .card .price.ask { color:var(--amber); }

  .empty { color:var(--muted); text-align:center; padding:32px; border:1px dashed var(--border); border-radius:14px; }
  footer { text-align:center; color:var(--muted); font-size:12.5px; margin-top:52px; border-top:1px solid var(--border); padding-top:18px; }
  .badge-db { font-size:12px; padding:4px 10px; border-radius:999px; border:1px solid var(--border); color:var(--muted); }
  .badge-db.ok { color:var(--green); border-color:var(--green); }
</style>
</head>
<body>
<div class="wrap">
  <nav class="nav">
    <div class="logo">🛡️ Semoga<span>Sukses</span>.com</div>
    <div>
      <span class="badge-db <?= $db !== null ? 'ok' : '' ?>"><?= $db !== null ? 'DB: OK' : 'DB: MENUNGGU' ?></span>
      <a href="/">Beranda</a>
      <a href="/dashboard">Dashboard</a>
      <a href="https://monitor.semogasukses.com">Monitoring</a>
      <a href="https://sso.semogasukses.com">SSO</a>
    </div>
  </nav>

  <section class="hero">
    <h1>Keamanan Digital yang <em>Terjaga</em> &amp; Terpantau.</h1>
    <p>Landing point arsitektur AISS UTS: SafeLine WAF di depan, Traefik sebagai router, Keycloak untuk SSO, dan Grafana untuk monitoring — semua data katalog di bawah disimpan dan ditampilkan langsung dari MariaDB.</p>
    <div class="links">
      <a class="btn primary" href="/dashboard">Buka Dashboard &rarr;</a>
      <a class="btn" href="https://monitor.semogasukses.com">Grafana Monitoring</a>
    </div>
  </section>

  <div class="section-title">
    <h2>Katalog Produk &amp; Solusi</h2>
    <span>sumber data: tabel <code>products</code> pada MariaDB</span>
  </div>

  <?php if (empty($products)): ?>
    <div class="empty">Belum ada data produk. Pastikan tabel <code>products</code> sudah ter-seed (lihat README → bagian "Inisialisasi DB").</div>
  <?php else: ?>
  <section class="grid">
    <?php foreach ($products as $p): ?>
    <div class="card">
      <div class="row-top">
        <h3><?= h($p['name']) ?></h3>
        <span class="badge <?= $p['category'] === 'solution' ? 'solution' : 'product' ?>"><?= h($p['category']) ?></span>
      </div>
      <p><?= h($p['description']) ?></p>
      <div class="price <?= $p['price'] === null || $p['price'] === '' ? 'ask' : '' ?>"><?= h(format_price($p['price'])) ?></div>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <footer>AISS — Ujian Tengah Semester &middot; Nginx + PHP + MariaDB + Grafana + Keycloak + Traefik + SafeLine &middot; Docker Compose</footer>
</div>
</body>
</html>