-- Inisialisasi tabel data untuk dashboard AISS UTS
-- Dijalankan otomatis HANYA saat volume database pertama kali dibuat.
-- (Reset: docker compose down -v)

USE website;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  role VARCHAR(50) NOT NULL DEFAULT 'user',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_visits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page VARCHAR(255) NOT NULL DEFAULT 'home',
  visited_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_visited_at (visited_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data contoh pengguna
INSERT INTO users (name, email, role) VALUES
  ('Andi Pratama',  'andi.pratama@example.com',  'admin'),
  ('Budi Santoso',  'budi.santoso@example.com',  'analyst'),
  ('Citra Dewi',    'citra.dewi@example.com',    'user'),
  ('Dewi Lestari',  'dewi.lestari@example.com',  'user'),
  ('Eko Nugroho',   'eko.nugroho@example.com',   'user');

-- Data kunjungan contoh (tersebar 6 jam terakhir agar grafik Grafana langsung terisi)
INSERT INTO page_visits (page, visited_at) VALUES
  ('home', NOW() - INTERVAL 6 HOUR),
  ('home', NOW() - INTERVAL 5 HOUR - INTERVAL 40 MINUTE),
  ('home', NOW() - INTERVAL 5 HOUR - INTERVAL 25 MINUTE),
  ('home', NOW() - INTERVAL 5 HOUR),
  ('home', NOW() - INTERVAL 4 HOUR - INTERVAL 30 MINUTE),
  ('home', NOW() - INTERVAL 4 HOUR),
  ('home', NOW() - INTERVAL 3 HOUR - INTERVAL 55 MINUTE),
  ('home', NOW() - INTERVAL 3 HOUR - INTERVAL 20 MINUTE),
  ('home', NOW() - INTERVAL 3 HOUR),
  ('home', NOW() - INTERVAL 2 HOUR - INTERVAL 45 MINUTE),
  ('home', NOW() - INTERVAL 2 HOUR - INTERVAL 15 MINUTE),
  ('home', NOW() - INTERVAL 2 HOUR),
  ('home', NOW() - INTERVAL 1 HOUR - INTERVAL 50 MINUTE),
  ('home', NOW() - INTERVAL 1 HOUR - INTERVAL 30 MINUTE),
  ('home', NOW() - INTERVAL 1 HOUR),
  ('home', NOW() - INTERVAL 30 MINUTE),
  ('home', NOW() - INTERVAL 10 MINUTE);