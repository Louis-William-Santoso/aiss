# AISS UTS — Arsitektur Web Terindependen (WAF + Nginx + PHP + Grafana + Keycloak + MariaDB)

Stack produksi-mini untuk UTS AISS dengan mengimplementasikan prinsip keamanan **Defense in Depth** secara komprehensif dari tingkat jaringan hingga aplikasi.

## Topologi & Arsitektur

Arsitektur sistem dibangun di atas Ubuntu Server dengan menggunakan jaringan MikroTik sebagai gerbang utama.

```text
Internet ──▶ MikroTik Router (Firewall, Port Forwarding, VPN L2TP/IPSEC)
                │   VLAN 10 (APP)   : 10.10.10.0/24 (Akses Publik via Port 80 & 443)
                │   VLAN 20 (MGMT)  : 10.20.20.0/24 (Manajemen Terisolasi)
                │   VPN Admin       : 10.30.30.0/24 (Satu-satunya jalur ke VLAN 20)
                ▼
        Ubuntu Server (Host Firewall dengan nftables)
                ▼
        Docker Bridge: aiss-net (172.30.0.0/24)
                │
        SafeLine WAF Stack ──▶ Application Stack (Nginx, PHP, Grafana, Keycloak, MariaDB)
```

## Lapisan Keamanan (Defense in Depth)

Sistem ini menggunakan strategi keamanan berlapis yang terdiri dari:

### 1. Perimeter & Network Security (MikroTik)
* Firewall MikroTik memblokir seluruh trafik dari publik yang menuju ke VLAN Management.
* Akses manajemen jaringan hanya dapat dilakukan oleh user terdaftar menggunakan VPN L2TP/IPSEC.
* Terdapat *rule* pendeteksi *Port Scanner* yang memasukkan alamat IP penyerang ke dalam *blacklist* secara otomatis.
* Mitigasi serangan *ICMP Flood* dilakukan menggunakan *rate limiting* dengan batas 5 paket per detik dan *burst* 5 paket.
* Servis bawaan MikroTik yang tidak diperlukan (seperti telnet, ftp, www, ssh, dan api) dinonaktifkan untuk meminimalkan celah keamanan.

### 2. Host Security (Ubuntu nftables)
* Konfigurasi nftables membatasi pengiriman *ICMP echo-request* maksimal 18 paket per menit dengan *burst* 5 paket.
* Akses ke *port* manajemen Keycloak (8080) dan Safeline (9445) dibatasi secara ketat hanya untuk *traffic* yang berasal dari IP VPN (10.30.30.0/24).
* Protokol SSH (*port* 22) pada Ubuntu server hanya dapat diakses melalui jaringan VPN.

### 3. Web Application Firewall (SafeLine)
* SafeLine bertindak sebagai lapisan deteksi serangan yang terintegrasi dengan AI untuk menganalisis dan memblokir muatan berbahaya, seperti *SQL Injection*.
* WAF difungsikan sebagai *reverse proxy* yang memaksa pengalihan seluruh *traffic* HTTP (*port* 80) menuju HTTPS (*port* 443) yang lebih aman.
* Keamanan komunikasi data dijamin dengan penggunaan *Root CA* kustom beserta sertifikat server terkait.

### 4. Application Security (NGINX)
* Konfigurasi NGINX dilengkapi dengan pengaturan *header* keamanan meliputi `X-Content-Type-Options nosniff`, `X-Frame-Options SAMEORIGIN`, serta `Referrer-Policy strict-origin-when-cross-origin`.
* NGINX diatur agar menolak seluruh permintaan akses menuju *file* tersembunyi.

### 5. Identity & Database Security (Keycloak & MariaDB)
* MariaDB tidak menyimpan kata sandi pengguna dalam teks biasa, melainkan menggunakan metode *hashing* SHA256 ditambah *Salt*.
* Keycloak mengamankan sistem autentikasi SSO dengan mewajibkan pengaturan kata sandi OTP (One-Time Password) melalui aplikasi autentikator.
* Fitur deteksi *Brute Force* aktif di Keycloak yang akan mengunci akun secara sementara apabila pengguna gagal *login* sebanyak 5 kali.
* Sistem otorisasi SSO menerapkan *Role-Based Access Control* (RBAC), yaitu penetapan peran 'supervisor' dengan akses *read* dan *write*, serta peran 'user' dengan hak *read-only*.

## Layanan & Akses Container

Terdapat total 12 *container* yang terbagi menjadi grup layanan *microservice* WAF dan grup layanan *web/app*.

| Layanan | Keterangan | Bind Akses |
|---|---|---|
| **safeline-tengine** | *Reverse proxy service* (Frontgate WAF). | `10.10.10.10:80/443` |
| **safeline-mgt** | *Management dashboard* dari Safeline. | `10.20.20.10:9445` (Akses VPN) |
| **safeline-pg** | *Postgres service* untuk database sistem WAF. | Internal |
| **safeline-detector** | Modul analisis Safeline untuk *threat detection*. | Internal |
| **safeline-luigi** | Modul untuk *background log processing* dan manajemen data. | Internal |
| **safeline-fvm** | *Service* untuk *version management* dan dukungan *runtime*. | Internal |
| **safeline-chaos** | Menangani fitur pelindung, *captcha*, dan *waiting room*. | Internal |
| **nginx** | Menghosting *landing page* atau halaman utama situs. | Internal (Di belakang proksi) |
| **php** | Menyediakan dukungan skrip backend untuk NGINX. | Internal |
| **mariadb** | Database *backend* pendukung dasbor aplikasi utama. | Internal |
| **grafana** | Visualisasi dan *monitoring* sistem internal aplikasi. | `monitor.semogasukses.com` |
| **keycloak** | Mengatur *Single Sign-On* dan manajemen otentikasi sentral. | Dasbor Manajemen: `10.20.20.10:8080` |

## Struktur Proyek

```text
uts/
├── docker-compose.yaml     # Orkestrasi jaringan (mendefinisikan 12 service Safeline dan Apps).
├── .env                    # Kredensial, penentuan nama realm (uts_aiss), dan IP bind
├── docs/
│   ├── mikrotik.rsc        # Konfigurasi pembentukan VLAN, L2TP/IPSEC, pembatasan port firewall, dan mitigasi ICMP
│   └── keycloak-setup.md   # Setup client "grafana", perincian grup user, dan mapper klaim
├── nginx/
│   └── conf.d/00-site.conf # Header keamanan dan filter regex blokir direktori file tersembunyi
├── www/                    # Direktori kode sumber situs web
├── mariadb/
│   └── init/01-init.sql    # Simulasi penyemaian (seeding) database menggunakan SHA256+Salt
└── grafana/                # Datasource provision dan tautan masuk Keycloak
```

## Persiapan & Pengujian Sistem

1. **Konfigurasi MikroTik & VPN**: Aktifkan skrip di `docs/mikrotik.rsc` untuk melakukan *setup* antarmuka, *Pool DHCP*, dan aturan pemfilteran berbasis peran. Sambungkan profil VPN (contoh: user louis) via L2TP/IPSEC pada perangkat lokal Anda.
2. **Peluncuran Container**: Eksekusi perintah pembentukan arsitektur kontainer *docker* di terminal server pusat:
   ```bash
   docker compose up -d
   ```
3. **Pemberdayaan Safeline**: Atur ulang sandi administratif WAF menggunakan baris kode: `docker exec safeline-mgt resetadmin`. Di dalam dasbor Safeline, ikat kode sandi OTP, tambahkan sertifikat server dan *Root CA*, lalu berlakukan *reverse proxy* lintas gerbang HTTP (*Port* 80) ke HTTPS (*Port* 443).
4. **Pemberdayaan Role Keycloak**: Buka *dashboard* manajemen Keycloak di port `8080` via jalur VPN. Setelah meresmikan *realm* `uts_aiss` yang memuat fitur deteksi *Brute Force*, buatlah dan petakan identitas akun baru ke ranah 'supervisor' dan 'user' dan hubungkan bersama kewajiban autentikator pihak ketiga.
