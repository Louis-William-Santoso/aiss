# Setup Keycloak untuk SSO Grafana (RBAC: user / supervisor)

Dokumen ini menyiapkan SSO sehingga:

- Grup **`supervisor`** → **Admin** di Grafana (bisa mengubah apa pun).
- Grup **`user`** → **Viewer** di Grafana (read-only).

Pemetaan sudah dideklarasikan di `docker-compose.yaml`:

```yaml
GF_AUTH_GENERIC_OAUTH_ROLE_ATTRIBUTE_PATH=contains(groups[*], 'supervisor') && 'Admin' || contains(groups[*], 'user') && 'Viewer'
```

## 0. Prasyarat

Keycloak sudah jalan: `docker compose up -d keycloak`. Akses `https://sso.semogasukses.com` (via VPN jika hanya web-mgmt; atau via SafeLine publik) dan login sebagai `admin` dengan password dari `.env` (`KEYCLOAK_ADMIN_PASSWORD`).

> Default URL di compose memakai realm **`master`** (variabel `SSO_REALM=master` di `.env`).
> Bila ingin memakai realm terpisah (mis. `AppRealm`), buat realm baru di Keycloak lalu ubah `SSO_REALM=AppRealm` di `.env` dan restart Grafana.

## 1. Buat Client "grafana"

1. **Clients → Create client**.
2. Client ID: `grafana`
3. Client type: **confidential** → Next.
4. Pada **Capability config** centang:
   - *Standard flow* (Authorization Code)
5. Simpan. Lalu pada tab **Settings** set:
   - **Root URL**: `https://monitor.semogasukses.com`
   - **Valid redirect URIs**: `https://monitor.semogasukses.com/*` dan `http://localhost:3000/*` (untuk uji lokal)
   - **Valid post logout redirect URIs**: `https://monitor.semogasukses.com/*`
   - **Web origins**: `https://monitor.semogasukses.com`
6. Buka tab **Credentials** → salin **Client secret**.
7. Isi ke `.env`: `GRAFANA_CLIENT_SECRET=<secret>` lalu `docker compose up -d grafana`.

## 2. Buat Groups

1. **Groups → Create group**: `user`
2. **Groups → Create group**: `supervisor`

## 3. Buat User & Masukkan ke Grup

1. **Users → Add user** (username, email; mis. `andi` dan `budi`).
2. Set password sementara: tab **Credentials** → *Set password* (matikan "Temporary" jika mau permanen).
3. Masuk ke profil user → **Join groups** → pilih `supervisor` untuk admin / `user` untuk read-only.

## 4. Masukkan Klaim "groups" ke Token

Grafana membaca grup dari klaim JWT bernama `groups`. Tanpa ini, `contains(groups[*], ...)` di atas tidak akan pernah true.

1. **Client scopes →** pilih scopes bawaan **`profile`** (atau buat yang baru).
2. Tab **Mappers → Add mapper → By configuration** → pilih **Group Membership**.
3. Isi:
   - **Name**: `groups`
   - **Mapper type**: `Group Membership`
   - **Token Claim Name**: `groups`
   - Centang **Add to ID token** dan **Add to access token**
4. Simpan.

> Push default bawaan Keycloak juga menambahkan grup; klaim `groups` akan berisi nama grup.

## 5. Uji Alur SSO

1. Pastikan Grafana sudah restart: `docker compose up -d grafana`.
2. Buka `https://monitor.semogasukses.com` → tombol **Sign in with Keycloak**.
3. Login sebagai user anggota `supervisor` → lihat **Admin** role (ikon admin / bisa edit).
4. Login sebagai user anggota `user` → lihat **Viewer** (tidak bisa edit dashboard).

## Troubleshooting

| Gejala | Cek |
|---|---|
| Login gagal / redirect error | `GRAFANA_CLIENT_SECRET` cocok dengan client; redirect URIs benar |
| Login sukses tapi role selalu Viewer | Klaim `groups` belum masuk token → ulangi langkah 4 |
| Grafana "Sign in with Keycloak" tidak muncul | `docker compose logs grafana` — pastikan `GF_AUTH_GENERIC_OAUTH_ENABLED=true` |
| `groups` tidak ada di token | Gunakan *jwt.io* pada token akses dari Keycloak untuk debug |