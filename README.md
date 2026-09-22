# AISS UTS — Arsitektur Web Terindependen (WAF + Traefik + Nginx + PHP + Grafana + Keycloak + MariaDB)

Stack produksi-mini untuk UTS AISS dengan prinsip **Defense in Depth**:

```
Internet ──▶ MikroTik (VLAN 10 & 20 + VPN WireGuard)
                │  VLAN 10 (app)   : 10.10.10.0/24
                │  VLAN 20 (mgmt)  : 10.20.20.0/24 (hanya via VPN Admin 10.30.30.0/24)
                ▼
        SafeLine WAF ──▶ Traefik ──▶ container apps
   (10.10.10.10:80/443)   (routing per-host)
                              ├─ semogasukses.com      → Nginx (landing statis)
                              ├─ app.semogasukses.com   → Nginx → PHP-FPM → dashboard → MariaDB
                              ├─ monitor.semogasukses.com → Grafana (SSO Keycloak) → MariaDB
                              └─ sso.semogasukses.com   → Keycloak (login + RBAC)
```

## Layanan & Akses

| Layanan | Image | Bind | Akses |
|---|---|---|---|
| SafeLine Tengine | `chaitin/safeline-tengine` | `10.10.10.10:80/443` | Frontgate WAF |
| SafeLine Mgt UI | `chaitin/safeline-mgt` | `10.20.20.10:9443` | Management WAF (VLAN 20/VPN) |
| SafeLine Detector/API/PG/Redis | `chaitin/*`, `postgres`, `redis` | internal | Mesin deteksi |
| Traefik | `traefik:v2.10` | `10.20.20.10:80` | Router + dashboard (mgmt whitelist) |
| Nginx | `nginx:alpine` | internal | Landing `semogasukses.com` + proxy `app.semogasukses.com` |
| PHP-FPM | `php:8.4-fpm-alpine` | internal | Dashboard PHP |
| Grafana | `grafana/grafana` | internal | `monitor.semogasukses.com` |
| Keycloak | `quay.io/keycloak` | internal | `sso.semogasukses.com` |
| MariaDB | `mariadb:11` | internal | DB `website` |

## Struktur Proyek

```
uts/
├── docker-compose.yaml          # 12 service
├── .env                         # kredensial, SSO realm, bind IP (tidak di-commit)
├── docs/
│   ├── mikrotik.rsc             # skrip RouterOS: VLAN 10/20, WireGuard, firewall, DNAT, netplan Ubuntu
│   └── keycloak-setup.md        # panduan client "grafana", grup user/supervisor, klaim "groups"
├── nginx/
│   ├── html/index.html          # landing page statis
│   └── conf.d/{00-landing,10-app}.conf
├── php/Dockerfile               # php:8.4-fpm-alpine + pdo_mysql
├── www/                         # dashboard PHP (index, config, system_stats, api/, assets/)
├── mariadb/init/01-init.sql     # skema db website + seed (jalan sekali)
└── grafana/
    ├── provisioning/            # data source MariaDB + provider dashboard
    └── dashboards/monitoring.json
```

## Persiapan

1. **Salin & isi `.env`** — wajib ada: `GRAFANA_CLIENT_SECRET`, `KEYCLOAK_ADMIN`, `KEYCLOAK_ADMIN_PASSWORD`, `MARIADB_*`. Sesuaikan IP bind (`SAFELINE_WAN_IP`, dst.) dengan server.
2. **MikroTik**: ikuti `docs/mikrotik.rsc` (VLAN + WireGuard + firewall + DNAT). DNS publik mengarah ke WAN; gunakan /etc/hosts di PC untuk uji labor.
3. **Server Ubuntu**: pastikan VLAN tagging diterima (lihat contoh netplan di `docs/mikrotik.rsc`).
4. **Keycloak**: selesaikan `docs/keycloak-setup.md` (client `grafana` + grup `user`/`supervisor` + mapper klaim `groups`).
5. **SafeLine**: buat situs upstream menuju **`http://traefik:8080`** (internal network) — traefik entrypoint `web-app` menerima trafik dari WAF.

## Menjalankan

```bash
docker compose up -d --build
docker compose ps
```

> Docker posts bind hanya ke IP spesifik sesuai `.env`. Di lab tanpa IP tersebut, set ke `127.0.0.1` untuk uji lokal (jaringan mikro-segmentasi tidak berlaku dalam uji tersebut).

## RBAC Grafana (SSO)

`GF_AUTH_GENERIC_OAUTH_ROLE_ATTRIBUTE_PATH`:
```
contains(groups[*], 'supervisor') && 'Admin' || contains(groups[*], 'user') && 'Viewer'
```
- `supervisor` → **Admin** (semua akses)
- `user` → **Viewer** (read-only)

## Keamanan (Konteks AISS)

- **Segmentasi jaringan**: VLAN 10 (app) terisolasi dari VLAN 20 (mgmt). Admin hanya lewat WireGuard (`10.30.30.0/24`) — firewall MikroTik *drop* semua jalur lain.
- **WAF di depan**: SafeLine memblokir serangan sebelum Traefik.
- **Whitelist manajemen**: dashboard Traefik & `/admin` Keycloak hanya dari `MGMT_SOURCERANGE` (VLAN 20 + VPN) via entrypoint `web-mgmt`.
- **DB internal**: MariaDB/Postgres tidak ter-expose ke host.
- **RBAC SSO**: Grafana read-only untuk grup `user`, admin untuk `supervisor`.
- Header keamanan dasar nginx (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`).

## Verifikasi Cepat

```bash
# Landing & dashboard (lewat traefik dengan header Host)
curl -H "Host: semogasukses.com"    http://10.20.20.10/
curl -H "Host: app.semogasukses.com" http://10.20.20.10/ | head -20
curl -H "Host: app.semogasukses.com" http://10.20.20.10/api/stats.php

# DB seed
docker compose exec mariadb mariadb -uwebsite -p website -e "SELECT COUNT(*) FROM users;"

# Log
docker compose logs -f grafana keycloak php
```

## Troubleshooting

- **WAF gagal bind IP** → pastikan IP VLAN ada di interface Ubuntu.
- **Grafana tidak bisa login SSO** → cek `GRAFANA_CLIENT_SECRET` & follow `docs/keycloak-setup.md`.
- **Role selalu Viewer** → klaim `groups` tidak ter-inject; cek mapper (langkah 4 di docs).
- **Dashboard "DB: MENUNGGU"** → php menunggu healthcheck MariaDB; `docker compose logs mariadb`.
- **Init SQL tidak jalan** → seed hanya saat volume DB baru. Reset: `docker compose down -v`.
- **Keycloak redirect loop** → pastikan `KC_PROXY_HEADERS=xforwarded` dan `X-Forwarded-Proto` diteruskan SafeLine/traefik (HTTPS di depan).

## Roadmap (opsional, sesuai prompt)

- Tabel `products` / `solutions` di MariaDB yang ditampilkan di halaman web (CRUD via dashboard PHP).