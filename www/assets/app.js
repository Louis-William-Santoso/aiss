'use strict';

/* ===== Helper ===== */
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

function fmtBytes(b) {
  b = Number(b) || 0;
  if (b >= 1073741824) return (b / 1073741824).toFixed(2) + ' GB';
  if (b >= 1048576)    return (b / 1048576).toFixed(2) + ' MB';
  if (b >= 1024)       return (b / 1024).toFixed(1) + ' KB';
  return b + ' B';
}

async function getJSON(url, options) {
  const res = await fetch(url, options);
  const data = await res.json().catch(() => ({}));
  if (!res.ok || data.ok === false) {
    throw new Error(data.error || ('HTTP ' + res.status));
  }
  return data;
}

/* ===== Statistik sistem (refresh tiap 3 detik) ===== */
async function refreshStats() {
  try {
    const s = await getJSON('/api/stats.php');
    $('load1').textContent = s.load['1'];
    $('load5').textContent = s.load['5'];
    $('load15').textContent = s.load['15'];

    $('memText').textContent = fmtBytes(s.mem.used);
    $('memBar').style.width = s.mem.percent + '%';
    $('memMeta').textContent = s.mem.percent + '% dari ' + fmtBytes(s.mem.total);

    $('diskText').textContent = fmtBytes(s.disk.used);
    $('diskBar').style.width = s.disk.percent + '%';
    $('diskMeta').textContent = s.disk.percent + '% terpakai';

    $('hostName').textContent = s.host;
  } catch (_) {
    // halaman hanya dibuka saat service php hidup; biarkan nilai sebelumnya.
  }
}

/* ===== Data DB (kunjungan + pengguna) ===== */
async function refreshDb(showError) {
  try {
    const v = await getJSON('/api/visits.php');
    $('vTotal').textContent = v.total;
    $('vToday').textContent = v.today;
    $('vLast').textContent = v.last || '-';

    const u = await getJSON('/api/users.php');
    $('uCount').textContent = u.data.length;
    renderUsers(u.data);

    badge(true);
    if (showError) $('userMsg').textContent = '';
    return true;
  } catch (e) {
    badge(false);
    if (showError) $('userMsg').textContent = 'Database belum siap — mencoba lagi otomatis…';
    return false;
  }
}

function badge(ok) {
  const el = $('dbBadge');
  el.textContent = ok ? 'DB: OK' : 'DB: MENUNGGU';
  el.className = 'badge ' + (ok ? 'badge-ok' : 'badge-err');
}

/* ===== Tabel pengguna ===== */
function renderUsers(rows) {
  const tbody = $('userRows');
  if (!tbody) return;
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="muted">Tidak ada data pengguna.</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map((u) =>
    '<tr>'
    + '<td>' + esc(u.id) + '</td>'
    + '<td>' + esc(u.name) + '</td>'
    + '<td>' + esc(u.email) + '</td>'
    + '<td>' + esc(u.role) + '</td>'
    + '<td>' + esc(u.created_at) + '</td>'
    + '<td class="ta-r"><button class="btn-danger" data-id="' + esc(u.id) + '" data-name="' + esc(u.name) + '">Hapus</button></td>'
    + '</tr>'
  ).join('');

  tbody.querySelectorAll('.btn-danger').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!confirm('Hapus pengguna "' + btn.dataset.name + '"?')) return;
      btn.disabled = true;
      try {
        await getJSON('/api/users.php', {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: Number(btn.dataset.id) }),
        });
        refreshDb(true);
      } catch (e) {
        $('userMsg').textContent = e.message;
        btn.disabled = false;
      }
    });
  });
}

/* ===== Form tambah pengguna ===== */
$('userForm').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const form = ev.currentTarget;
  const btn = form.querySelector('button[type="submit"]');
  const msg = $('formMsg');
  msg.textContent = '';
  btn.disabled = true;

  const body = {
    name: form.name.value.trim(),
    email: form.email.value.trim(),
    role: form.role.value,
  };

  try {
    await getJSON('/api/users.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    form.reset();
    msg.textContent = 'Pengguna berhasil disimpan ✔';
    msg.style.color = 'var(--green)';
    refreshDb(true);
  } catch (e) {
    msg.textContent = e.message;
    msg.style.color = 'var(--red)';
  } finally {
    btn.disabled = false;
  }
});

/* ===== Jam ===== */
function tickClock() {
  const now = new Date();
  const p = (n) => String(n).padStart(2, '0');
  $('clock').textContent = p(now.getHours()) + ':' + p(now.getMinutes()) + ':' + p(now.getSeconds());
}

/* ===== Inisialisasi ===== */
(function init() {
  const init = window.PAGE_INIT || {};
  const sys = init.system || {};

  // Isi nilai awal dari render server (anti-flicker), lalu polling.
  if (sys.load) {
    $('load1').textContent = sys.load['1'];
    $('load5').textContent = sys.load['5'];
    $('load15').textContent = sys.load['15'];
  }
  if (sys.mem) {
    $('memText').textContent = fmtBytes(sys.mem.used);
    $('memBar').style.width = sys.mem.percent + '%';
    $('memMeta').textContent = sys.mem.percent + '% dari ' + fmtBytes(sys.mem.total);
  }
  if (sys.disk) {
    $('diskText').textContent = fmtBytes(sys.disk.used);
    $('diskBar').style.width = sys.disk.percent + '%';
    $('diskMeta').textContent = sys.disk.percent + '% terpakai';
  }

  tickClock();
  setInterval(tickClock, 1000);
  setInterval(refreshStats, 3000);

  refreshStats();
  refreshDb(true)
    .then((ok) => {
      // Jika DB belum siap saat load, coba lagi tiap 5 detik.
      if (!ok) setInterval(() => refreshDb(false), 5000);
    });
})();