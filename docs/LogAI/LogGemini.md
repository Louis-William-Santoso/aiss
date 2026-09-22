# **Jadi saya punya arsitektur seperti ini: User \--\> MikroTik \--\> Safeline \--\> Traefik \--\> Container App Di dalam container app ini bakal ada grafana (monitor.example.com) dan dashboard (apache2 sederhana, example.com) Jadi ketika user masuk, maka dia akan masuk ke main dashboard utama. Disini ada tombol "Monitor" Buat masuk ke monitor.example.com Di grafana, dia akan ngecek pakai keycloak. Jika user belum ada sesinya, maka harus login dengan keycloak. Jika sudah ada maka akan masuk ke grafana. Di grafana, akan ada authorization, dimana jika group "user" Maka hanya bisa read only, dan group "supervisor" Bisa melakukan semuanya. Nah saya mau di mikrotik buat VLAN. VLAN 10 untuk app, dan VLAN 20 untuk management. Jadi ketika user akses, maka akan langsung masuk VLAN 10\. VLAN 20 hanya dapat diakses jika pakai VPN Admin. VLAN 20 ini bisa memanage dashboard traefik, safeline, keycloak. VLAN 10 tidak bisa. Bagaimana penerapan dari semua arsitektur yang kubuat ya?**

[https://gemini.google.com/app/2e09db0e44d33cf8](https://gemini.google.com/app/2e09db0e44d33cf8)

*User prompt: Jadi saya punya arsitektur seperti ini: User \--\> MikroTik \--\> Safeline \--\> Traefik \--\> Container App Di dalam container app ini bakal ada grafana (monitor.example.com) dan dashboard (apache2 sederhana, example.com) Jadi ketika user masuk, maka dia akan masuk ke main dashboard utama. Disini ada tombol "Monitor" Buat masuk ke monitor.example.com Di grafana, dia akan ngecek pakai keycloak. Jika user belum ada sesinya, maka harus login dengan keycloak. Jika sudah ada maka akan masuk ke grafana. Di grafana, akan ada authorization, dimana jika group "user" Maka hanya bisa read only, dan group "supervisor" Bisa melakukan semuanya. Nah saya mau di mikrotik buat VLAN. VLAN 10 untuk app, dan VLAN 20 untuk management. Jadi ketika user akses, maka akan langsung masuk VLAN 10\. VLAN 20 hanya dapat diakses jika pakai VPN Admin. VLAN 20 ini bisa memanage dashboard traefik, safeline, keycloak. VLAN 10 tidak bisa. Bagaimana penerapan dari semua arsitektur yang kubuat ya?*

Response: Arsitektur yang Anda buat sangat solid dan menerapkan prinsip *Defense in Depth* dengan baik. Pemisahan jalur data (VLAN 10\) dan jalur kontrol/manajemen (VLAN 20\) dipadukan dengan WAF (Safeline) dan Reverse Proxy (Traefik) memastikan keamanan berlapis.

Berikut adalah panduan komprehensif langkah demi langkah untuk mengimplementasikan arsitektur ini.

## **1\. Konfigurasi Jaringan & Keamanan di MikroTik**

Fokus utama di sini adalah membuat segmentasi dan aturan *Firewall* agar VLAN 10 murni untuk trafik publik, sedangkan VLAN 20 sangat terisolasi.

**A. Pembuatan VLAN & IP Address**

> * **VLAN 10 (App):** Alokasikan subnet, misal 10.10.10.0/24. Arahkan trafik HTTP (80) dan HTTPS (443) dari internet (Public IP) ke IP Server di VLAN 10 (misal 10.10.10.10).  
> * **VLAN 20 (Management):** Alokasikan subnet, misal 10.10.20.0/24. Server Anda juga harus memiliki IP di subnet ini (misal 10.10.20.10).

**B. Konfigurasi VPN Admin**

> * Buat server VPN (disarankan **WireGuard** untuk kecepatan dan keamanan) di MikroTik dengan subnet terpisah, misal 10.10.30.0/24.

**C. Aturan Firewall (Filter Rules)** Terapkan aturan *Drop Default* dan izinkan hanya jalur yang spesifik:

> 1. **Allow Internet \-\> VLAN 10:** Izinkan port 80 & 443 dari interface WAN masuk ke 10.10.10.10.  
> 2. **Allow VPN \-\> VLAN 20:** Izinkan trafik dari subnet VPN (10.10.30.0/24) ke subnet VLAN 20 (10.10.20.0/24).  
> 3. **Drop VLAN 10 \-\> VLAN 20:** Pastikan tidak ada komunikasi routing dari VLAN 10 ke VLAN 20\.  
> 4. **Drop Internet \-\> VLAN 20:** Blokir semua akses dari WAN ke VLAN 20\.

## **2\. Implementasi Safeline WAF**

Safeline bertindak sebagai gerbang terdepan sebelum Traefik.

> * **WAF Listener (Publik):** Konfigurasikan Safeline untuk mendengarkan port 80 dan 443 pada IP VLAN 10 (10.10.10.10). Set *Upstream* atau *Backend* ke IP lokal Traefik.  
> * **Management Dashboard Safeline:** Secara default berjalan di port 9443\. Ikat (bind) port ini **hanya** ke IP VLAN 20 (10.10.20.10:9443). Jika menggunakan Docker, gunakan parameter \-p 10.10.20.10:9443:9443. Dengan ini, dashboard Safeline mustahil diakses dari VLAN 10\.

## **3\. Konfigurasi Traefik & Container App (Docker Compose)**

Gunakan docker-compose.yml untuk mendeploy Traefik, Apache (Dashboard), Grafana, dan Keycloak dalam satu jaringan Docker internal. Traefik akan menggunakan *Middleware* untuk memblokir akses ke halaman manajemen dari luar VLAN 20\.

**A. Traefik Middleware (IP AllowList)** Anda perlu mendefinisikan middleware di Traefik yang hanya mengizinkan subnet VPN dan VLAN 20\.

\# Pada file docker-compose.yml di bagian labels Traefik atau file dinamis  
labels:  
  \- "traefik.http.middlewares.mgmt-ip-whitelist.ipwhitelist.sourcerange=10.10.20.0/24, 10.10.30.0/24"

**B. Pengaturan Routing Traefik**

> * **Main Dashboard (example.com):** Route ke container Apache2 tanpa middleware whitelist (terbuka untuk publik).  
> * **Grafana (monitor.example.com):** Route ke container Grafana tanpa middleware whitelist (terbuka untuk publik, keamanan ditangani Keycloak).  
> * **Keycloak Publik (sso.example.com):** Buka akses publik agar user bisa login dari rumah.  
> * **Traefik Dashboard:** Terapkan rule untuk domain traefik.example.com dan pasang middleware mgmt-ip-whitelist.  
> * **Keycloak Admin Console:** Buat route khusus di Traefik untuk path /auth/admin pada Keycloak, dan pasang middleware mgmt-ip-whitelist.

## **4\. Konfigurasi SSO & RBAC (Keycloak \+ Grafana)**

Setelah infrastruktur berjalan, tahap terakhir adalah mengintegrasikan otorisasi.

**A. Pengaturan Keycloak**

> 1. Buat **Realm** baru (misal: AppRealm).  
> 2. Buat **Client** untuk Grafana (misal: grafana-client). Aktifkan *Client Authentication* dan catat *Client Secret*\-nya.  
> 3. Buat dua **Groups**: user dan supervisor.  
> 4. Buat **Client Scope** atau gunakan mapper pada *Client* untuk menyuntikkan (inject) informasi *Group* ke dalam token JWT (biasanya dengan nama klaim groups).

**B. Pengaturan Grafana (grafana.ini)** Konfigurasikan OIDC OAuth dan pemetaan peran (Role Mapping) berdasarkan grup Keycloak menggunakan sintaks JMESPath.

\[auth.generic\_oauth\]  
enabled \= true  
name \= Keycloak  
allow\_sign\_up \= true  
client\_id \= grafana-client  
client\_secret \= \<YOUR\_CLIENT\_SECRET\>  
scopes \= openid profile email groups  
auth\_url \= https://sso.example.com/realms/AppRealm/protocol/openid-connect/auth  
token\_url \= https://sso.example.com/realms/AppRealm/protocol/openid-connect/token  
api\_url \= https://sso.example.com/realms/AppRealm/protocol/openid-connect/userinfo

\# Pemetaan Grup Keycloak ke Role Grafana  
role\_attribute\_path \= contains(groups\[\*\], 'supervisor') && 'Admin' || contains(groups\[\*\], 'user') && 'Viewer'

*Catatan:* Konfigurasi role\_attribute\_path di atas akan mengecek token JWT. Jika user ada di grup supervisor, mereka mendapat akses 'Admin' (semua akses). Jika ada di grup user, mereka mendapat akses 'Viewer' (Read Only).

## **Ringkasan Alur Akses**

> 1. **User Biasa (VLAN 10):** Mengetik example.com \-\> Masuk ke MikroTik \-\> Diarahkan ke Safeline (IP VLAN 10\) \-\> Di-forward ke Traefik \-\> Masuk ke Apache. User klik "Monitor" \-\> Traefik mengarahkan ke Grafana \-\> Grafana melempar ke sso.example.com untuk login.  
> 2. **Admin (Manajemen):** Menyalakan VPN (mendapat IP 10.10.30.x) \-\> Akses traefik.example.com atau IP Safeline VLAN 20 (10.10.20.10:9443). Trafik masuk lewat MikroTik \-\> Diizinkan Firewall \-\> Traefik mengecek IP asal (VPN) \-\> Lolos *IP AllowList* middleware \-\> Dashboard terbuka.

---

*User prompt: Buatkan konfigurasi semuanya*

## **Response: 1\. Skrip MikroTik (RouterOS)**

Jalankan perintah ini di terminal MikroTik untuk mengatur VLAN, WireGuard VPN, dan Firewall Rules. Sesuaikan nama antarmuka (bridge1, ether1) dengan topologi perangkat keras Anda.

\# Buat VLAN dan IP Address  
/interface vlan add name=vlan10-app vlan-id=10 interface=bridge1  
/interface vlan add name=vlan20-mgmt vlan-id=20 interface=bridge1  
/ip address add address=10.10.10.1/24 interface=vlan10-app  
/ip address add address=10.10.20.1/24 interface=vlan20-mgmt

\# Setup WireGuard VPN untuk Admin  
/interface wireguard add name=wg1 listen-port=13231  
/ip address add address=10.10.30.1/24 interface=wg1

\# Firewall Filter & NAT  
/ip firewall filter  
add action=accept chain=forward in-interface=wg1 out-interface=vlan20-mgmt comment="Allow VPN to Mgmt"  
add action=drop chain=forward in-interface=vlan10-app out-interface=vlan20-mgmt comment="Drop App to Mgmt"  
add action=drop chain=forward in-interface=ether1 out-interface=vlan20-mgmt comment="Drop WAN to Mgmt"

/ip firewall nat  
add action=dst-nat chain=dstnat dst-port=80,443 in-interface=ether1 protocol=tcp to-addresses=10.10.10.10 comment="Forward Web to WAF/VLAN10"

## **2\. Docker Compose (Infrastruktur Utama)**

Simpan konfigurasi ini sebagai docker-compose.yml di server (10.10.10.10 / 10.10.20.10). File ini mendefinisikan Traefik dengan *IP AllowList*, Apache (Dashboard), Grafana, dan Keycloak.

version: "3.8"  
services:  
  traefik:  
    image: "traefik:v2.10"  
    command:  
      \- "--providers.docker=true"  
      \- "--entrypoints.web.address=:80"  
    ports:  
      \- "80:80"  
    volumes:  
      \- "/var/run/docker.sock:/var/run/docker.sock:ro"  
    labels:  
      \- "traefik.http.middlewares.mgmt-whitelist.ipwhitelist.sourcerange=10.10.20.0/24, 10.10.30.0/24"

  apache:  
    image: httpd:alpine  
    labels:  
      \- "traefik.http.routers.apache.rule=Host(\`example.com\`)"

  grafana:  
    image: grafana/grafana:latest  
    environment:  
      \- GF\_AUTH\_GENERIC\_OAUTH\_ENABLED=true  
      \- GF\_AUTH\_GENERIC\_OAUTH\_CLIENT\_ID=grafana-client  
      \- GF\_AUTH\_GENERIC\_OAUTH\_CLIENT\_SECRET=YOUR\_SECRET  
      \- GF\_AUTH\_GENERIC\_OAUTH\_SCOPES=openid profile email groups  
      \- GF\_AUTH\_GENERIC\_OAUTH\_AUTH\_URL=https://sso.example.com/realms/AppRealm/protocol/openid-connect/auth  
      \- GF\_AUTH\_GENERIC\_OAUTH\_TOKEN\_URL=https://sso.example.com/realms/AppRealm/protocol/openid-connect/token  
      \- GF\_AUTH\_GENERIC\_OAUTH\_API\_URL=https://sso.example.com/realms/AppRealm/protocol/openid-connect/userinfo  
      \- GF\_AUTH\_GENERIC\_OAUTH\_ROLE\_ATTRIBUTE\_PATH=contains(groups\[\*\], 'supervisor') && 'Admin' || contains(groups\[\*\], 'user') && 'Viewer'  
    labels:  
      \- "traefik.http.routers.grafana.rule=Host(\`monitor.example.com\`)"

  keycloak:  
    image: quay.io/keycloak/keycloak:latest  
    command: start-dev  
    environment:  
      \- KEYCLOAK\_ADMIN=admin  
      \- KEYCLOAK\_ADMIN\_PASSWORD=admin  
    labels:  
      \- "traefik.http.routers.keycloak.rule=Host(\`sso.example.com\`)"  
      \# Proteksi Endpoint Admin Keycloak  
      \- "traefik.http.routers.keycloak-admin.rule=Host(\`sso.example.com\`) && PathPrefix(\`/auth/admin\`)"  
      \- "traefik.http.routers.keycloak-admin.middlewares=mgmt-whitelist"

## **3\. Instalasi Safeline WAF**

Karena Safeline diinstal menggunakan skrip *installer* resminya (bash \-c "$(curl \-fsSLk \[https://waf.chaitin.com/release/latest/setup.sh\](https://waf.chaitin.com/release/latest/setup.sh))"), Anda harus memodifikasi file .env yang dihasilkan Safeline setelah instalasi.

> * Ubah variabel port manajemen di file .env Safeline agar terikat langsung ke IP VLAN 20: MGT\_PORT=10.10.20.10:9443.  
> * Pada dashboard Safeline, buat *Protected Site* di port 80/443 dan arahkan *Upstream Server* ke IP docker lokal Traefik (misal 172.17.0.1:80).

Apakah Anda memerlukan panduan pengaturan *Client Scope/Mapper* di GUI Keycloak agar grup JWT dapat terbaca oleh variabel lingkungan Grafana di atas?

---

*User prompt: Tapi docker compose yang kamu kasih itu bind portnya 80:80, berarti kan traefik dkk itu bakal ada 1 interface aja? Gimana caranya mereka tau bahwa ini traffic yang boleh mengakses dan tidak*

Response: Pertanyaan yang **sangat kritis dan sangat tepat\!** Anda benar, jika kita menggunakan 80:80 di Docker, secara *default* Docker akan melakukan *binding* ke 0.0.0.0:80 (mendengarkan di **semua** interface/IP yang ada di server tersebut). Ini akan menghancurkan isolasi VLAN yang sudah kita buat di MikroTik.

Jika Traefik terbuka di 0.0.0.0, maka Traefik tidak tahu dari interface mana asal trafik secara fisik, dan kita hanya bergantung pada WAF atau Middleware (yang rentan di-*bypass* jika ada miskonfigurasi).

Agar isolasinya **benar-benar terpisah secara fisik di level jaringan (Network Layer)**, kita harus menerapkan 2 hal:

> 1. **Specific IP Binding di Docker:** Mengunci port Docker ke IP VLAN tertentu (contoh: 10.10.20.10:80:80).  
> 2. **Multiple Entrypoints di Traefik:** Membuat "Pintu masuk" yang berbeda. Satu pintu khusus menerima trafik dari Safeline WAF (VLAN 10), dan satu pintu khusus menerima trafik Manajemen (VLAN 20).

Berikut adalah revisi arsitektur dan konfigurasinya agar benar-benar aman:

### **Konsep Jalur (Routing Flow) yang Benar**

> * **Jalur App (VLAN 10 \- 10.10.10.10):** Safeline WAF mendengarkan secara *exclusive* di IP 10.10.10.10:80/443. Safeline kemudian meneruskan trafik ke Traefik melalui port internal (misal: 127.0.0.1:8080).  
> * **Jalur Manajemen (VLAN 20 \- 10.10.20.10):** Traefik mendengarkan secara *exclusive* di IP 10.10.20.10:80. Akses langsung ke IP ini hanya bisa dilakukan oleh Anda yang sedang terhubung ke VPN.

### **Revisi File docker-compose.yml**

Kita akan mendefinisikan *Entrypoints* web-app (untuk publik) dan web-mgmt (untuk admin).

version: "3.8"  
services:  
  traefik:  
    image: "traefik:v2.10"  
    command:  
      \- "--providers.docker=true"  
      \# Pintu masuk untuk aplikasi publik (di-forward oleh Safeline)  
      \- "--entrypoints.web-app.address=:8080"   
      \# Pintu masuk khusus manajemen  
      \- "--entrypoints.web-mgmt.address=:80"  
        
      \# (Opsional) Mengizinkan Traefik membaca IP asli client dari Safeline WAF  
      \- "--entrypoints.web-app.forwardedHeaders.insecure=true"   
    ports:  
      \# BINDING PENTING:  
      \# Hanya terima traffic manajemen di IP VLAN 20 (10.10.20.10) pada port 80  
      \- "10.10.20.10:80:80"  
        
      \# Buka port 8080 hanya di localhost, Safeline WAF akan forward ke port ini  
      \- "127.0.0.1:8080:8080"  
    volumes:  
      \- "/var/run/docker.sock:/var/run/docker.sock:ro"  
    labels:  
      \# Middleware IP Whitelist sebagai lapisan keamanan ganda (Defense in Depth)  
      \- "traefik.http.middlewares.mgmt-whitelist.ipwhitelist.sourcerange=10.10.20.0/24, 10.10.30.0/24"

  apache:  
    image: httpd:alpine  
    labels:  
      \# Hanya masuk lewat pintu "web-app" (Publik \-\> WAF \-\> Traefik)  
      \- "traefik.http.routers.apache.entrypoints=web-app"  
      \- "traefik.http.routers.apache.rule=Host(\`example.com\`)"

  grafana:  
    image: grafana/grafana:latest  
    environment:  
      \# (Konfigurasi OIDC sama seperti sebelumnya)  
      \- GF\_AUTH\_GENERIC\_OAUTH\_ENABLED=true  
    labels:  
      \# Hanya masuk lewat pintu "web-app"  
      \- "traefik.http.routers.grafana.entrypoints=web-app"  
      \- "traefik.http.routers.grafana.rule=Host(\`monitor.example.com\`)"

  keycloak:  
    image: quay.io/keycloak/keycloak:latest  
    command: start-dev  
    environment:  
      \- KEYCLOAK\_ADMIN=admin  
      \- KEYCLOAK\_ADMIN\_PASSWORD=admin  
    labels:  
      \# 1\. Endpoint Publik untuk User Login (sso.example.com/)  
      \- "traefik.http.routers.keycloak.entrypoints=web-app"  
      \- "traefik.http.routers.keycloak.rule=Host(\`sso.example.com\`)"  
        
      \# 2\. Endpoint ADMIN Keycloak yang SANGAT KETAT  
      \# HANYA bisa diakses dari pintu "web-mgmt" (VLAN 20\)  
      \- "traefik.http.routers.keycloak-admin.entrypoints=web-mgmt"  
      \- "traefik.http.routers.keycloak-admin.rule=Host(\`sso.example.com\`) && PathPrefix(\`/auth/admin\`, \`/admin\`)"  
      \- "traefik.http.routers.keycloak-admin.middlewares=mgmt-whitelist"  
      \# Prioritas tinggi agar rule admin menang menimpa rule publik  
      \- "traefik.http.routers.keycloak-admin.priority=100"

### **Penyesuaian di Safeline WAF**

Karena Traefik sekarang kita batasi *binding*\-nya, cara Safeline WAF menembakkan trafik (Upstream) juga harus disesuaikan:

> 1. Di Dashboard Safeline (yang diakses via VPN ke 10.10.20.10:9443), buat konfigurasi situs untuk example.com, monitor.example.com, dan sso.example.com.  
> 2. Set **Listen IP** di Safeline HANYA pada IP VLAN 10, yaitu: 10.10.10.10 port 80 dan 443\.  
> 3. Set **Upstream Server / Backend Node** di Safeline mengarah ke IP lokal Traefik, yaitu \[http://127.0.0.1:8080\](http://127.0.0.1:8080).

### **Mengapa Desain ini Sangat Aman?**

Jika *Attacker* berada di internet atau VLAN 10 dan mencoba mengakses halaman admin Keycloak (\[sso.example.com/auth/admin\](https://sso.example.com/auth/admin)) atau dashboard Traefik:

> 1. Trafik mereka akan ditangkap oleh Safeline WAF di 10.10.10.10.  
> 2. Safeline meneruskan ke Traefik di port 8080 (Entrypoint web-app).  
> 3. Traefik melihat bahwa rule untuk PathPrefix('/auth/admin') HANYA ada di Entrypoint web-mgmt (port 80).  
> 4. Karena Attacker masuk dari web-app, Traefik akan merespons **404 Not Found**. Halaman admin benar-benar gaib (tidak eksis) dari sisi publik.

Satu-satunya cara admin Keycloak muncul adalah jika ada koneksi langsung ke 10.10.20.10:80. Dan IP tersebut dilindungi di MikroTik hanya untuk pengguna VPN 10.10.30.x. Inilah wujud asli *Network Segmentation*.

---

*User prompt: Tapi kayaknya kamu lupa menambahkan network untuk semua container di docker composenya, itu bagaimana ya? Apakah kita pakai driver yang bisa berkomunikasi dengan VLAN di host ubuntu, atau bagaimana?*

Response: Pertanyaan yang **sangat jeli dan teliti\!** Anda benar, saya belum mendefinisikan jaringan (*network*) secara eksplisit di file compose sebelumnya.

Mengenai interaksi antara Docker dan VLAN di host Ubuntu, ada **dua pendekatan**. Mari kita bahas mana yang paling tepat untuk arsitektur Anda:

### **Pendekatan 1: *Custom Bridge* \+ *Host IP Binding* (Sangat Disarankan)**

Dalam arsitektur *Reverse Proxy* (menggunakan Traefik dan WAF), kita **tidak perlu** menggunakan driver macvlan atau ipvlan.

Mengapa? Karena sistem operasi Ubuntu (*host*) Anda sudah menangani VLAN tersebut. Ubuntu memiliki IP 10.10.10.10 (dari antarmuka VLAN 10\) dan 10.10.20.10 (dari antarmuka VLAN 20). Dengan mengikat (*binding*) port secara spesifik ke IP Host (seperti 10.10.20.10:80:80 yang kita bahas sebelumnya), Docker cukup menggunakan tipe jaringan **bridge biasa** di dalam. Traefik yang akan menjembatani trafik dari Host ke container-container di dalam bridge tersebut.

**Keuntungannya:** Isolasi maksimal. Container tidak terekspos langsung ke jaringan fisik, mencegah isu keamanan, dan menghindari masalah umum macvlan (di mana host Linux secara default memblokir komunikasi antara host dan container di jaringan macvlan yang sama).

### **Pendekatan 2: Driver macvlan (Tidak Disarankan untuk Kasus Ini)**

Ini akan memberikan IP address dari VLAN 10 atau 20 secara langsung ke masing-masing container (misal: Grafana mendapat IP 10.10.10.15). Ini justru membahayakan karena memotong fungsi Traefik sebagai *single point of entry* dan mem-bypass proteksi WAF.

### **Konfigurasi Final docker-compose.yml (Menggunakan Pendekatan 1\)**

Kita akan membuat satu custom network internal bernama app-network dengan driver bridge. Semua container hanya akan berkomunikasi di dalam jaringan terisolasi ini.

version: "3.8"

\# 1\. Definisikan Custom Network (Bridge)  
networks:  
  app-network:  
    driver: bridge  
    \# Opsional: Tentukan subnet internal agar tidak bentrok  
    ipam:  
      config:  
        \- subnet: 172.25.0.0/16

services:  
  traefik:  
    image: "traefik:v2.10"  
    command:  
      \- "--providers.docker=true"  
      \- "--providers.docker.exposedbydefault=false" \# Keamanan tambahan: hanya expose container dengan label enable=true  
      \- "--entrypoints.web-app.address=:8080"  
      \- "--entrypoints.web-mgmt.address=:80"  
    ports:  
      \# Listen IP VLAN 20 untuk manajemen  
      \- "10.10.20.10:80:80"  
      \# Listen localhost untuk menerima lemparan dari Safeline WAF  
      \- "127.0.0.1:8080:8080"  
    volumes:  
      \- "/var/run/docker.sock:/var/run/docker.sock:ro"  
    networks:  
      \- app-network \# Masukkan ke jaringan internal  
    labels:  
      \- "traefik.enable=true"  
      \- "traefik.http.middlewares.mgmt-whitelist.ipwhitelist.sourcerange=10.10.20.0/24, 10.10.30.0/24"

  apache:  
    image: httpd:alpine  
    networks:  
      \- app-network \# Masukkan ke jaringan internal  
    labels:  
      \- "traefik.enable=true"  
      \- "traefik.http.routers.apache.entrypoints=web-app"  
      \- "traefik.http.routers.apache.rule=Host(\`example.com\`)"

  grafana:  
    image: grafana/grafana:latest  
    environment:  
      \- GF\_AUTH\_GENERIC\_OAUTH\_ENABLED=true  
      \# ... (konfigurasi OIDC lainnya sama seperti sebelumnya) ...  
    networks:  
      \- app-network \# Masukkan ke jaringan internal  
    labels:  
      \- "traefik.enable=true"  
      \- "traefik.http.routers.grafana.entrypoints=web-app"  
      \- "traefik.http.routers.grafana.rule=Host(\`monitor.example.com\`)"

  keycloak:  
    image: quay.io/keycloak/keycloak:latest  
    command: start-dev  
    environment:  
      \- KEYCLOAK\_ADMIN=admin  
      \- KEYCLOAK\_ADMIN\_PASSWORD=admin  
    networks:  
      \- app-network \# Masukkan ke jaringan internal  
    labels:  
      \- "traefik.enable=true"  
      \# Rule Publik  
      \- "traefik.http.routers.keycloak.entrypoints=web-app"  
      \- "traefik.http.routers.keycloak.rule=Host(\`sso.example.com\`)"  
      \# Rule Manajemen (VLAN 20\)  
      \- "traefik.http.routers.keycloak-admin.entrypoints=web-mgmt"  
      \- "traefik.http.routers.keycloak-admin.rule=Host(\`sso.example.com\`) && PathPrefix(\`/auth/admin\`, \`/admin\`)"  
      \- "traefik.http.routers.keycloak-admin.middlewares=mgmt-whitelist"  
      \- "traefik.http.routers.keycloak-admin.priority=100"

### **Catatan Penting di Sisi Host (Ubuntu):**

Agar konfigurasi ini bekerja, pastikan server Ubuntu Anda sudah diatur (netplan atau /etc/network/interfaces) untuk menerima *VLAN Tagging* dari port trunk MikroTik.

Misalnya jika menggunakan **Netplan** (/etc/netplan/01-netcfg.yaml), konfigurasinya harus memiliki blok vlans seperti ini:

network:  
  version: 2  
  ethernets:  
    enp3s0: \# Sesuaikan dengan nama interface fisik  
      dhcp4: no  
  vlans:  
    vlan10:  
      id: 10  
      link: enp3s0  
      addresses: \[10.10.10.10/24\]  
    vlan20:  
      id: 20  
      link: enp3s0  
      addresses: \[10.10.20.10/24\]

Dengan setup *Netplan* (Host) \+ *Custom Bridge* (Docker) ini, container tetap terisolasi di jaringan internal 172.25.x.x, namun rute *masuk* trafik dari luar sepenuhnya dikendalikan oleh IP fisik VLAN mana yang diakses oleh user/admin.

---

*User prompt: Bisa jelaskan apa itu labels yang ada di setiap services container? Kenapa ada traefik.enable dan routers gitu? Lalu bagaimana keycloak tau bahwa yang mengakses itu dari publik atau pakai VPN, karena kan service container ini hanya di binding di app-network saja*

Response: Pertanyaan yang sangat bagus\! Memahami konsep ini adalah kunci utama untuk menguasai arsitektur *Reverse Proxy* dan *Docker Container*.

Mari kita bedah dua pertanyaan Anda:

### **1\. Apa itu labels, traefik.enable, dan routers?**

Secara sederhana, **Traefik adalah "Satpam Cerdas"**, dan **Labels adalah "Buku Instruksi"** yang ditempelkan di setiap container agar satpam tahu apa yang harus dilakukan terhadap tamu yang datang.

Karena Traefik terkoneksi langsung dengan sistem Docker (melalui *volume* /var/run/docker.sock), Traefik bisa membaca *labels* ini secara otomatis tanpa perlu kita menulis file konfigurasi manual yang panjang.

> * **traefik.enable=true**: Di konfigurasi sebelumnya, kita memberitahu Traefik: *"Jangan izinkan siapa pun mengakses container, kecuali saya suruh" (exposedbydefault=false)*. Nah, label traefik.enable=true adalah perintah pengecualian. Label ini memberitahu Traefik: *"Tolong pantau dan atur rute untuk container ini."*  
> * **routers (traefik.http.routers.xxx)**: Ini adalah aturan (*rule*) rute. Anda memberi instruksi spesifik kepada Traefik:  
  * rule=Host('example.com'): *"Kalau ada tamu ngetik example.com, antarkan ke container ini."*  
  * entrypoints=web-app: *"Tamu untuk container ini HANYA boleh masuk lewat pintu bernama web-app."*  
  * middlewares=...: *"Sebelum tamu diantar ke container, periksa dulu KTP-nya (IP Address)."*

### **2\. Bagaimana Keycloak Tahu Itu Publik atau VPN?**

Jawaban singkatnya: **Keycloak tidak tahu dan tidak peduli.**

Container Keycloak (dan Grafana/Apache) memang terkurung di dalam app-network dan tidak tahu menahu soal VLAN, WAF, atau VPN. **Yang bertugas memilah, mengecek, dan memblokir adalah Traefik.**

Keycloak pasrah sepenuhnya pada Traefik. Traefik membedakannya berdasarkan **Pintu Masuk (Entrypoints)** yang Anda definisikan:

**Skenario A: User Publik (Hacker) Mencoba Akses Admin Keycloak**

> 1. Hacker mengetik \[sso.example.com/auth/admin\](https://sso.example.com/auth/admin).  
> 2. Trafik masuk lewat MikroTik \-\> Safeline WAF (VLAN 10).  
> 3. Safeline melempar trafik tersebut ke port 8080\.  
> 4. Traefik menerima tamu ini di pintu **web-app** (karena 8080 adalah web-app).  
> 5. Traefik mengecek "Buku Instruksi" (Labels) Keycloak:  
   * Ada instruksi untuk sso.example.com di web-app? *Ada, ini halaman login publik.*  
   * Ada instruksi untuk path /auth/admin di web-app? **TIDAK ADA.** Instruksi admin HANYA ada untuk tamu yang masuk dari pintu web-mgmt.  
> 6. Karena jalurnya tidak valid, Traefik akan menendang hacker tersebut dengan pesan **404 Page Not Found**. Trafik sama sekali tidak pernah menyentuh Keycloak.

**Skenario B: Anda (Admin) Mengakses Pakai VPN**

> 1. Anda menyalakan VPN, mendapat IP 10.10.30.5.  
> 2. Anda mengakses 10.10.20.10 (IP Manajemen di VLAN 20).  
> 3. Traefik menerima Anda di pintu **web-mgmt** (karena port 80 pada IP 10.10.20.10 diikat ke web-mgmt).  
> 4. Traefik mengecek Labels Keycloak:  
   * Ada instruksi untuk \[sso.example.com/auth/admin\](https://sso.example.com/auth/admin) di web-mgmt? *Ada.*  
> 5. Traefik melihat ada syarat tambahan: **middlewares=mgmt-whitelist**.  
> 6. Traefik memeriksa IP Anda. *"Oh, dia pakai IP 10.10.30.5. IP ini terdaftar di whitelist."*  
> 7. Traefik membuka pintu masuk ke app-network dan meneruskan Anda ke halaman Admin Keycloak.

**Kesimpulan:** Isolasi jaringan (app-network) memastikan container tidak terekspos ke luar. Traefik bertindak sebagai jembatan tunggal (berdiri di antara host/VLAN dan app-network), dan memutuskan siapa yang boleh menyeberang ke container berdasarkan IP fisik tempat datangnya request dan aturan (*labels*) yang melekat.

---

*User prompt: Sepertinya anda salah deh. Kan kita menyalakan VPN, lalu kita akses IP 10.10.20.10:80, berarti kita masuk ke dashboardnya traefik dong? Kenapa kok tiba2 bisa masuk ke sso? Kasih penjelasannya dengan detail*

Response: Anda sangat teliti\! Terima kasih sudah mempertanyakan hal ini. Anda benar, penjelasan saya melompati satu konsep krusial tentang **bagaimana Traefik (dan Reverse Proxy pada umumnya) membaca trafik web**.

Mari kita luruskan apa yang sebenarnya terjadi jika Anda mengetik \[http://10.10.20.10\](http://10.10.20.10) di browser.

### **1\. Mengetik IP 10.10.20.10 TIDAK akan membuka SSO ataupun Traefik Dashboard.**

Jika Anda menyalakan VPN lalu mengetik IP 10.10.20.10 di browser, Traefik justru akan menampilkan pesan **404 Not Found**.

**Kenapa?** Karena Traefik menggunakan sistem yang disebut **HTTP Host Header**. Saat Anda mengetik alamat di browser, browser mengirimkan nama domain (atau IP) tersebut ke Traefik.

> * Di konfigurasi Keycloak sebelumnya, kita menulis label: rule=Host('sso.example.com').  
> * Saat Anda mengetik 10.10.20.10, Host yang diterima Traefik adalah 10.10.20.10.  
> * Traefik akan mencari daftar aturannya: *"Apakah ada aturan untuk Host(10.10.20.10)?"* Jawabannya: **Tidak ada**. Maka Traefik mengembalikan 404\.

Lalu bagaimana Traefik Dashboard? Sama saja. Secara *default*, Traefik tidak menampilkan dashboard apa pun di port 80 kecuali kita memerintahkannya.

### **2\. Lalu Bagaimana Cara Admin Masuk ke SSO Admin?**

Agar Admin (via VPN di VLAN 20\) bisa mengakses \[sso.example.com/auth/admin\](https://sso.example.com/auth/admin) dan masuk lewat pintu web-mgmt (10.10.20.10), Anda harus menggunakan teknik yang disebut **Split DNS** (DNS Terpisah).

> 1. Di pengaturan DNS MikroTik Anda, buat sebuah entri DNS statis: sso.example.com mengarah ke IP 10.10.20.10.  
> 2. Pastikan pengguna VPN (Admin) menggunakan IP MikroTik sebagai DNS Server mereka.  
> 3. Saat Admin mengetik \[sso.example.com/auth/admin\](https://sso.example.com/auth/admin) di browser, komputer Admin akan menterjemahkannya ke IP 10.10.20.10.  
> 4. Trafik sampai ke port 80 10.10.20.10 (pintu web-mgmt).  
> 5. Traefik membaca Host Header-nya: "sso.example.com". *Cocok\!*  
> 6. Traefik membaca Path-nya: "/auth/admin". *Cocok\!*  
> 7. Admin diizinkan masuk ke halaman SSO Admin.

*(Catatan: Untuk User Publik dari internet, DNS Server global akan mengarahkan sso.example.com ke IP Publik, yang berujung ke Safeline WAF di 10.10.10.10, masuk pintu web-app, dan ditolak saat mencoba buka path /auth/admin).*

### **3\. Lalu Bagaimana Cara Membuka Traefik Dashboard?**

Untuk bisa membuka dashboard manajemen Traefik itu sendiri, kita harus:

> 1. Menyalakan fitur API/Dashboard di setting Traefik.  
> 2. Membuat "Router" (label) khusus untuk Traefik agar ia tahu domain/IP apa yang dipakai untuk membuka dashboard-nya.

Mari kita perbaiki konfigurasinya agar Traefik Dashboard bisa diakses murni menggunakan IP 10.10.20.10 khusus dari jalur VPN:

version: "3.8"  
networks:  
  app-network:  
    driver: bridge

services:  
  traefik:  
    image: "traefik:v2.10"  
    command:  
      \- "--api.dashboard=true" \# 1\. Nyalakan Dashboard Traefik  
      \- "--providers.docker=true"  
      \- "--providers.docker.exposedbydefault=false"  
      \- "--entrypoints.web-app.address=:8080"  
      \- "--entrypoints.web-mgmt.address=:80"  
    ports:  
      \- "10.10.20.10:80:80"     \# Pintu Manajemen (VLAN 20\)  
      \- "127.0.0.1:8080:8080"   \# Pintu Aplikasi (VLAN 10 via WAF)  
    volumes:  
      \- "/var/run/docker.sock:/var/run/docker.sock:ro"  
    networks:  
      \- app-network  
    labels:  
      \- "traefik.enable=true"  
      \- "traefik.http.middlewares.mgmt-whitelist.ipwhitelist.sourcerange=10.10.20.0/24, 10.10.30.0/24"  
        
      \# 2\. Aturan untuk membuka Dashboard Traefik  
      \# Rule berdasarkan Host IP, jadi Admin cukup ketik http://10.10.20.10 di browser  
      \- "traefik.http.routers.traefik-dash.rule=Host(\`10.10.20.10\`)"  
      \- "traefik.http.routers.traefik-dash.entrypoints=web-mgmt"  
      \- "traefik.http.routers.traefik-dash.service=api@internal" \# Arahkan ke service internal dashboard  
      \- "traefik.http.routers.traefik-dash.middlewares=mgmt-whitelist"

  \# ... (service apache dan grafana tetap sama) ...

  keycloak:  
    image: quay.io/keycloak/keycloak:latest  
    command: start-dev  
    environment:  
      \- KEYCLOAK\_ADMIN=admin  
      \- KEYCLOAK\_ADMIN\_PASSWORD=admin  
    networks:  
      \- app-network  
    labels:  
      \- "traefik.enable=true"  
        
      \# \[PUBLIK & ADMIN\] Halaman Utama Keycloak (Login, Profil)  
      \# Diizinkan masuk dari DUA pintu sekaligus (web-app dan web-mgmt)  
      \- "traefik.http.routers.keycloak.entrypoints=web-app, web-mgmt"  
      \- "traefik.http.routers.keycloak.rule=Host(\`sso.example.com\`)"  
        
      \# \[ADMIN ONLY\] Halaman Konfigurasi Keycloak (/auth/admin)  
      \# HANYA diizinkan masuk dari pintu manajemen  
      \- "traefik.http.routers.keycloak-admin.entrypoints=web-mgmt"  
      \- "traefik.http.routers.keycloak-admin.rule=Host(\`sso.example.com\`) && PathPrefix(\`/auth/admin\`, \`/admin\`)"  
      \- "traefik.http.routers.keycloak-admin.middlewares=mgmt-whitelist"  
      \- "traefik.http.routers.keycloak-admin.priority=100"

### **Kesimpulan Alur yang Benar:**

> * **Buka Traefik Dashboard:** Anda (Admin via VPN) mengetik \[http://10.10.20.10\](http://10.10.20.10). Traefik membaca Host 10.10.20.10 \-\> *Cocok dengan rule traefik-dash* \-\> Anda masuk ke Dashboard Traefik.  
> * **Buka Keycloak Admin:** Anda mengatur DNS lokal (Split DNS) agar sso.example.com diarahkan ke 10.10.20.10. Anda mengetik \[http://sso.example.com/auth/admin\](http://sso.example.com/auth/admin). Traefik membaca Host sso.example.com dan Path /auth/admin \-\> *Cocok dengan rule keycloak-admin* \-\> Anda masuk ke panel Keycloak Admin.

Terima kasih atas koreksinya. Pemisahan identitas *Host* (Nama Domain) dengan *Entrypoint* (Port/IP Fisik) inilah yang membuat Traefik menjadi alat *Routing* lapis 7 (Application Layer) yang sangat tangguh.

---

*User prompt: Bagaimana cara kerja traefik entrypoints? Kok bisa tau bahwa satunya web-app, dan satunya web-mgmt*

Response: Konsep **Entrypoint** di Traefik sebenarnya sangat sederhana. Bayangkan *Entrypoint* sebagai **"Pintu Gerbang Fisik"** pada sebuah gedung, di mana setiap pintu memiliki nama dan gembok (port) yang berbeda.

Traefik tahu sebuah trafik masuk ke web-app atau web-mgmt murni berdasarkan **Port (angka port) internal berapa yang menerima trafik tersebut di dalam container.**

Mari kita bedah cara kerjanya selangkah demi selangkah:

### **1\. Penamaan Adalah Bebas (Hanya Alias)**

Nama web-app dan web-mgmt bukanlah kata kunci bawaan sistem Traefik. Itu adalah nama **yang kita buat sendiri**. Anda bebas menamainya pintu-publik dan pintu-rahasia.

Di file docker-compose.yml, kita mendefinisikan dan menamai pintu-pintu ini di bagian command:

command:  
  \- "--entrypoints.web-app.address=:8080"  \# Pintu bernama "web-app" mendengarkan di port 8080  
  \- "--entrypoints.web-mgmt.address=:80"   \# Pintu bernama "web-mgmt" mendengarkan di port 80

### **2\. Penghubung antara Dunia Luar dan Traefik**

Traefik hidup terisolasi di dalam container Docker. Ia tidak tahu menahu soal IP 10.10.20.10 atau settingan VLAN di MikroTik. Ia hanya tahu: *"Ada paket masuk lewat port 80"* atau *"Ada paket masuk lewat port 8080"*.

Yang bertugas menerjemahkan dan mengarahkan trafik fisik dari host Ubuntu ke dalam container Traefik adalah **Docker Port Binding** (di bagian ports):

ports:  
  \- "127.0.0.1:8080:8080" \# Trafik localhost diarahkan ke port internal 8080  
  \- "10.10.20.10:80:80"   \# Trafik dari IP VLAN 20 diarahkan ke port internal 80

### **3\. Alur Deteksi Traefik (Bagaimana Ia Melakukan Labeling)**

**Skenario 1: Trafik Publik (Via Safeline WAF)**

> 1. Trafik publik masuk dari internet \-\> MikroTik \-\> Safeline WAF (di IP VLAN 10).  
> 2. WAF memproses keamanan, lalu melempar trafik tersebut ke *upstream* lokal di 127.0.0.1 port 8080\.  
> 3. Docker melihat ada paket di 127.0.0.1:8080 dan meneruskannya ke dalam container Traefik **di port internal 8080**.  
> 4. Traefik menerima paket di port 8080\. Traefik mengecek catatannya: *"Port 8080 ini adalah gerbang yang namanya **web-app**."*  
> 5. Traefik memberi "stempel" pada request tersebut: **Asal: web-app**.

**Skenario 2: Trafik Admin (Via VPN)**

> 1. Anda terkoneksi VPN, lalu mengetik \[http://10.10.20.10\](http://10.10.20.10) atau \[http://sso.example.com\](http://sso.example.com) (lewat DNS split lokal). Request berjalan di port web standar (80).  
> 2. Trafik masuk ke antarmuka host Ubuntu pada IP 10.10.20.10:80.  
> 3. Docker menangkapnya dan meneruskannya ke dalam container Traefik **di port internal 80**.  
> 4. Traefik menerima paket di port 80\. Ia mengecek catatannya: *"Port 80 ini adalah gerbang yang namanya **web-mgmt**."*  
> 5. Traefik memberi stempel pada request tersebut: **Asal: web-mgmt**.

### **4\. Eksekusi Aturan Rute (Labels)**

Setelah trafik diberi stempel asal pintu, Traefik tinggal mencocokkan stempel tersebut dengan syarat (*labels*) yang ada di container tujuan (seperti Apache, Grafana, atau Keycloak).

Jika di container Keycloak Admin Anda menulis label: \- "traefik.http.routers.keycloak-admin.entrypoints=web-mgmt"

Maka Traefik akan membacanya sebagai instruksi: *"Halaman admin Keycloak HANYA boleh dibuka jika tamu tersebut membawa stempel **web-mgmt**."*

Jika ada seseorang dari internet (via WAF) mencoba mengakses /auth/admin, Traefik melihat bahwa orang tersebut masuk membawa stempel web-app. Karena stempelnya tidak cocok dengan syarat rute, Traefik langsung memblokir aksesnya di depan pintu dengan pesan **404 Not Found**, dan trafik tersebut tidak akan pernah menyentuh container Keycloak sama sekali.

---

*User prompt: Kenapa traefik yang web-app itu listen di 127.0.0.1? Bagaimana safeline dapat mengaksesnya?*

Response: Anda benar-benar tajam\! Ini adalah **"jebakan Batman"** yang paling sering terjadi dalam arsitektur Docker, dan Anda berhasil menyadarinya.

Asumsi awal penggunaan 127.0.0.1 adalah karena Traefik dan Safeline berada di dalam satu server Ubuntu yang sama (satu mesin fisik/virtual). Idenya adalah agar port 8080 Traefik tidak bisa diakses dari jaringan luar mana pun, melainkan hanya dari dalam server itu sendiri (localhost).

**TETAPI, ada masalah besar dengan 127.0.0.1 jika Safeline WAF juga berjalan di dalam Docker.**

Karena Safeline (Chaitin WAF) juga menggunakan infrastruktur Docker (Tengine container), jika Anda menyetel Upstream Server di dashboard Safeline ke 127.0.0.1, maka kontainer Safeline akan **mencari ke dalam dirinya sendiri**, bukan mencari ke Traefik\!

Untuk mengatasi hal ini agar komunikasi antara WAF dan Traefik berjalan mulus tanpa merusak keamanan, kita harus merevisi IP binding Traefik.

### **Solusi yang Benar: Gunakan IP VLAN 10 (10.10.10.10)**

Alih-alih mengikat (binding) port 8080 Traefik ke 127.0.0.1, kita akan mengikatnya ke **IP host Ubuntu di VLAN 10**.

**Perubahan di docker-compose.yml (Traefik):**

    ports:  
      \# Pintu Manajemen (VLAN 20\)  
      \- "10.10.20.10:80:80"  
        
      \# Pintu Aplikasi/Publik (VLAN 10\) \- DIGANTI  
      \- "10.10.10.10:8080:8080"

**Konfigurasi di Dashboard Safeline WAF:**

> 1. **Listen IP:** 10.10.10.10 pada port 80 dan 443  
> 2. **Upstream Server (Backend):** \[http://10.10.10.10:8080\](http://10.10.10.10:8080)

### **Bagaimana Alurnya Sekarang?**

> 1. Trafik dari luar (publik) masuk ke router MikroTik.  
> 2. MikroTik mengarahkan trafik port 80/443 ke IP Ubuntu 10.10.10.10.  
> 3. Di Ubuntu, port 80 dan 443 pada IP 10.10.10.10 sedang "dijaga" oleh Safeline WAF. WAF memeriksa trafik tersebut (apakah ada SQL Injection, XSS, dll).  
> 4. Jika aman, Safeline meneruskan trafik itu ke **Upstream Server**, yaitu IP Ubuntu itu sendiri, tapi di port yang berbeda: 10.10.10.10:8080.  
> 5. Docker menangkap trafik di 10.10.10.10:8080 ini dan meneruskannya ke pintu web-app di dalam Traefik.

### **"Tunggu, kalau port 8080 dibuka di IP 10.10.10.10, apakah hacker bisa bypass Safeline dengan langsung mengakses IP-publik:8080?"**

**Tidak bisa.** Inilah mengapa arsitektur Anda di awal sangat solid karena ada perlindungan ganda (Defense in Depth) dari MikroTik:

Mari kita lihat kembali aturan NAT MikroTik yang sudah kita buat:

/ip firewall nat  
add action=dst-nat chain=dstnat dst-port=80,443 in-interface=ether1 protocol=tcp to-addresses=10.10.10.10

Perhatikan bahwa **hanya port 80 dan 443** yang diizinkan masuk dan diteruskan oleh MikroTik dari internet ke IP 10.10.10.10.

Jika hacker mencoba mengakses http://IP\_PUBLIK:8080, MikroTik akan **membuang (drop)** trafik tersebut karena tidak ada aturan firewall yang mengizinkan port 8080 masuk dari internet (WAN). Port 8080 hanya terbuka untuk komunikasi lokal di dalam kabel/VLAN tersebut.

---

*User prompt: Berarti kalau kita pakai VPN, kita bypass pengecekan dari WAF safeline ya? Lalu bagaimana caranya agar WAF safeline ini dashboard managementnya hanya bisa diakses dari VLAN MGMT saja, tidak dari publik*

Response: Benar sekali, akses melalui VPN akan **mem-bypass pengecekan WAF Safeline**.

Ini sebenarnya adalah desain yang disengaja dan sangat disarankan (*Best Practice*) untuk arsitektur *Management/Admin*.

Trafik admin sering kali mengandung *payload* yang kompleks (seperti menyimpan konfigurasi JSON, mengunggah skrip, atau mengatur rule di Keycloak/Traefik). Jika trafik admin ini dilewatkan ke WAF, kemungkinan besar akan ditandai sebagai serangan (*False Positive*) dan diblokir oleh WAF. Karena Anda masuk melalui VPN (yang sudah divalidasi dengan kunci kriptografi WireGuard), Anda dianggap sebagai entitas tepercaya yang tidak perlu difilter oleh WAF.

### **Cara Mengamankan Dashboard Management Safeline WAF**

Secara default, saat Anda menginstal Safeline, dashboard manajemennya akan terbuka di semua interface (0.0.0.0:9443). Untuk menguncinya agar **hanya bisa diakses dari VLAN 20 (Management)**, Anda perlu mengubah konfigurasi *binding* IP pada file instalasi Safeline.

Berikut adalah langkah-langkahnya:

**1\. Edit File .env Safeline** Secara default, Safeline diinstal di direktori /data/safeline. Masuk ke direktori tersebut dan edit file .env:

cd /data/safeline  
nano .env

**2\. Ubah Variabel Port Management** Cari baris yang mengatur port manajemen, biasanya tertulis MGT\_PORT=9443. Ubah baris tersebut dengan menambahkan IP VLAN 20 di depannya:

MGT\_PORT=10.10.20.10:9443

*(Catatan: Docker Compose Safeline akan membaca nilai ini dan melakukan binding port secara spesifik, mengubahnya menjadi 10.10.20.10:9443:1443 di belakang layar).*

**3\. Terapkan Perubahan (Restart Container Safeline)** Setelah file .env disimpan, muat ulang container Safeline agar perubahan IP binding ini aktif:

docker compose down  
docker compose up \-d

### **Mengapa Ini Sangat Aman?**

Sekarang, perlindungan jaringan Anda bekerja seperti ini:

> 1. **Di Level Docker (Host):** Dashboard Safeline sekarang "buta" dan "tuli" terhadap permintaan yang datang dari internet atau VLAN 10\. Ia hanya mau merespons jika ada yang memanggil IP 10.10.20.10 port 9443\.  
> 2. **Di Level MikroTik (Router):** Jika ada orang dari internet atau dari aplikasi (VLAN 10\) mencoba iseng memanggil IP 10.10.20.10, mereka akan menabrak tembok *Firewall Rule* yang sudah kita buat sebelumnya di MikroTik:  
>    add action=drop chain=forward in-interface=ether1 out-interface=vlan20-mgmt comment="Drop WAN to Mgmt"  
>    add action=drop chain=forward in-interface=vlan10-app out-interface=vlan20-mgmt comment="Drop App to Mgmt"

Satu-satunya jalan yang tersisa untuk membuka halaman \[https://10.10.20.10:9443\](https://10.10.20.10:9443) adalah dari antarmuka WireGuard VPN (wg1), yang aturannya sudah kita izinkan di firewall MikroTik.

---

*User prompt: version: "3.8" networks:   app-network:     driver: bridge     internal: true   mgmt-network:     driver: bridge     internal: true   db-network:     driver: bridge     internal: true volumes:   mariadb-data:   grafana-data:   keycloak-data: services:   \# \============================================================   \# TRAEFIK   \# \============================================================   traefik:     image: traefik:v2.10     command:       \# Docker provider       \- "--providers.docker=true"       \- "--providers.docker.exposedbydefault=false"       \# Docker socket       \- "--providers.docker.endpoint=unix:///var/run/docker.sock"       \# Application entrypoint       \- "--entrypoints.web-app.address=:8080"       \# Management entrypoint       \- "--entrypoints.web-mgmt.address=:8081"       \# Dashboard       \- "--api.dashboard=true"       \# Logs       \- "--log.level=INFO"       \- "--accesslog=true"     ports:       \# APP VLAN       \# SafeLine \-\> Traefik       \- "10.10.10.10:8080:8080"       \# MGMT VLAN       \# VPN \-\> Traefik dashboard       \- "10.20.20.10:8081:8081"     volumes:       \- "/var/run/docker.sock:/var/run/docker.sock:ro"     networks:       \- app-network       \- mgmt-network     labels:       \- "traefik.enable=true"       \# \--------------------------------------------------------       \# Traefik Dashboard       \# \--------------------------------------------------------       \- "traefik.http.routers.traefik-dashboard.rule=Host(\`traefik.mgmt.semogasukses.com\`)"       \- "traefik.http.routers.traefik-dashboard.entrypoints=web-mgmt"       \- "traefik.http.routers.traefik-dashboard.service=api@internal"       \# VPN whitelist       \- "traefik.http.middlewares.mgmt-whitelist.ipallowlist.sourcerange=10.30.30.0/24"       \- "traefik.http.routers.traefik-dashboard.middlewares=mgmt-whitelist"   \# \============================================================   \# NGINX \- LANDING PAGE   \# \============================================================   nginx:     image: nginx:alpine     networks:       \- app-network       \- db-network     depends\_on:       \- mariadb     volumes:       \- ./nginx/html:/usr/share/nginx/html:ro       \- ./nginx/conf.d:/etc/nginx/conf.d:ro     labels:       \- "traefik.enable=true"       \# \--------------------------------------------------------       \# Landing page       \# \--------------------------------------------------------       \- "traefik.http.routers.landing.rule=Host(\`semogasukses.com\`)"       \- "traefik.http.routers.landing.entrypoints=web-app"       \- "traefik.http.routers.landing.service=landing-service"       \- "traefik.http.services.landing-service.loadbalancer.server.port=80"   \# \============================================================   \# GRAFANA   \# \============================================================   grafana:     image: grafana/grafana:latest     environment:       \- GF\_SERVER\_ROOT\_URL=https://monitor.semogasukses.com/       \# OAuth / Keycloak       \- GF\_AUTH\_GENERIC\_OAUTH\_ENABLED=true       \- GF\_AUTH\_GENERIC\_OAUTH\_NAME=Keycloak       \- GF\_AUTH\_GENERIC\_OAUTH\_ALLOW\_SIGN\_UP=true       \- GF\_AUTH\_GENERIC\_OAUTH\_CLIENT\_ID=grafana       \- GF\_AUTH\_GENERIC\_OAUTH\_CLIENT\_SECRET=${GRAFANA\_CLIENT\_SECRET}       \- GF\_AUTH\_GENERIC\_OAUTH\_SCOPES=openid profile email       \- GF\_AUTH\_GENERIC\_OAUTH\_AUTH\_URL=https://sso.semogasukses.com/realms/master/protocol/openid-connect/auth       \- GF\_AUTH\_GENERIC\_OAUTH\_TOKEN\_URL=http://keycloak:8080/realms/master/protocol/openid-connect/token       \- GF\_AUTH\_GENERIC\_OAUTH\_API\_URL=https://sso.semogasukses.com/realms/master/protocol/openid-connect/userinfo       \- GF\_AUTH\_GENERIC\_OAUTH\_ROLE\_ATTRIBUTE\_PATH=contains(groups\[\*\], 'grafana-admin') && 'Admin' || 'Viewer'     volumes:       \- grafana-data:/var/lib/grafana     networks:       \- app-network     labels:       \- "traefik.enable=true"       \# \--------------------------------------------------------       \# Grafana       \# \--------------------------------------------------------       \- "traefik.http.routers.grafana.rule=Host(\`monitor.semogasukses.com\`)"       \- "traefik.http.routers.grafana.entrypoints=web-app"       \- "traefik.http.routers.grafana.service=grafana-service"       \- "traefik.http.services.grafana-service.loadbalancer.server.port=3000"   \# \============================================================   \# KEYCLOAK   \# \============================================================   keycloak:     image: quay.io/keycloak/keycloak:latest     command:       \- start-dev     environment:       KEYCLOAK\_ADMIN: ${KEYCLOAK\_ADMIN}       KEYCLOAK\_ADMIN\_PASSWORD: ${KEYCLOAK\_ADMIN\_PASSWORD}       KC\_PROXY\_HEADERS: xforwarded       KC\_HTTP\_ENABLED: "true"       KC\_HOSTNAME: sso.semogasukses.com     networks:       \- app-network     volumes:       \- keycloak-data:/opt/keycloak/data     labels:       \- "traefik.enable=true"       \# \--------------------------------------------------------       \# Keycloak SSO       \# \--------------------------------------------------------       \- "traefik.http.routers.keycloak.rule=Host(\`sso.semogasukses.com\`)"       \- "traefik.http.routers.keycloak.entrypoints=web-app"       \- "traefik.http.routers.keycloak.service=keycloak-service"       \- "traefik.http.services.keycloak-service.loadbalancer.server.port=8080"       \# \--------------------------------------------------------       \# Keycloak Admin       \# \--------------------------------------------------------       \- "traefik.http.routers.keycloak-admin.rule=Host(\`sso.semogasukses.com\`) && PathPrefix(\`/admin\`)"       \- "traefik.http.routers.keycloak-admin.entrypoints=web-mgmt"       \- "traefik.http.routers.keycloak-admin.service=keycloak-service"       \- "traefik.http.routers.keycloak-admin.middlewares=mgmt-whitelist"       \- "traefik.http.routers.keycloak-admin.priority=100"   \# \============================================================   \# MARIADB   \# \============================================================   mariadb:     image: mariadb:11     restart: unless-stopped     environment:       MARIADB\_ROOT\_PASSWORD: ${MARIADB\_ROOT\_PASSWORD}       MARIADB\_DATABASE: website       MARIADB\_USER: website       MARIADB\_PASSWORD: ${MARIADB\_PASSWORD}     volumes:       \- mariadb-data:/var/lib/mysql     networks:       \- db-network     \# IMPORTANT:     \# No ports exposed to host. Perbaiki ini supaya bisa ada skenario seperti berikut: Jadi disini aku bakal pakai 2 VLAN ke Ubuntu Host, yaitu vlan 10 (10.10.10.0/24) untuk APP, dan vlan 20 (10.20.20.0/24) untuk MGMT. User biasa mengakses "semogasukses.com" akan masuk ke safeline WAF untuk di cek, dan jika aman masuk ke traefik untuk di mapping ke landing page. Di dashboardnya akan ada tombol "MONITOR" yang akan mengarahkan ke "monitor.semogasukses.com". Disini grafana akan mengecek apakah ada sesi yang sudah terbentuk? Jika belum maka akan di autentikasi ke Keycloak. Dashboard management dari keycloak, traefik, dan safeline harus bisa diakses oleh user VPN 10.30.30.0/24. Melalui IP 10.20.20.10 dan port nya sembarang. Di landing page ini juga akan terkoneksi dengan mariadb server. Nanti akan ada database product dan solution gitu yang akan disimpan ke database dan ditampilkan di halaman webnya.*

Response: Berikut adalah revisi docker-compose.yml yang sudah disempurnakan untuk mengakomodasi skenario jaringan ganda (VLAN 10 & 20), integrasi WAF, keamanan SSO, dan topologi database.

### **1\. Revisi docker-compose.yml**

version: "3.8"

networks:  
  app-network:  
    driver: bridge  
    internal: true  
  mgmt-network:  
    driver: bridge  
    internal: true  
  db-network:  
    driver: bridge  
    internal: true

volumes:  
  mariadb-data:  
  grafana-data:  
  keycloak-data:

services:  
  \# \============================================================  
  \# TRAEFIK (REVERSE PROXY & ROUTER)  
  \# \============================================================  
  traefik:  
    image: traefik:v2.10  
    command:  
      \- "--providers.docker=true"  
      \- "--providers.docker.exposedbydefault=false"  
      \- "--providers.docker.endpoint=unix:///var/run/docker.sock"  
        
      \# Entrypoint App (Trafik dari Safeline WAF)  
      \- "--entrypoints.web-app.address=:8080"  
      \# Entrypoint Mgmt (Trafik langsung dari VPN)  
      \# Diubah ke :80 agar admin tidak perlu mengetik port di URL  
      \- "--entrypoints.web-mgmt.address=:80"  
        
      \- "--api.dashboard=true"  
      \- "--log.level=INFO"  
      \- "--accesslog=true"  
    ports:  
      \# \[VLAN 10 \- APP\] Listen hanya untuk lemparan dari Safeline WAF  
      \- "10.10.10.10:8080:8080"  
      \# \[VLAN 20 \- MGMT\] Listen khusus jalur VPN untuk manajemen  
      \- "10.20.20.10:80:80"  
    volumes:  
      \- "/var/run/docker.sock:/var/run/docker.sock:ro"  
    networks:  
      \- app-network  
      \- mgmt-network  
    labels:  
      \- "traefik.enable=true"

      \# \--------------------------------------------------------  
      \# Traefik Dashboard (HANYA VIA VPN / VLAN 20\)  
      \# \--------------------------------------------------------  
      \- "traefik.http.routers.traefik-dashboard.rule=Host(\`traefik.mgmt.semogasukses.com\`)"  
      \- "traefik.http.routers.traefik-dashboard.entrypoints=web-mgmt"  
      \- "traefik.http.routers.traefik-dashboard.service=api@internal"  
      \# VPN Whitelist Middleware  
      \- "traefik.http.middlewares.mgmt-whitelist.ipallowlist.sourcerange=10.30.30.0/24"  
      \- "traefik.http.routers.traefik-dashboard.middlewares=mgmt-whitelist"

  \# \============================================================  
  \# NGINX \- LANDING PAGE  
  \# \============================================================  
  nginx:  
    image: nginx:alpine  
    networks:  
      \- app-network  
      \- db-network \# Terhubung ke database  
    depends\_on:  
      \- mariadb  
    volumes:  
      \- ./nginx/html:/usr/share/nginx/html:ro  
      \- ./nginx/conf.d:/etc/nginx/conf.d:ro  
    labels:  
      \- "traefik.enable=true"  
      \# \--------------------------------------------------------  
      \# Landing page (PUBLIK VIA WAF)  
      \# \--------------------------------------------------------  
      \- "traefik.http.routers.landing.rule=Host(\`semogasukses.com\`)"  
      \- "traefik.http.routers.landing.entrypoints=web-app"  
      \- "traefik.http.routers.landing.service=landing-service"  
      \- "traefik.http.services.landing-service.loadbalancer.server.port=80"

  \# \============================================================  
  \# GRAFANA  
  \# \============================================================  
  grafana:  
    image: grafana/grafana:latest  
    environment:  
      \- GF\_SERVER\_ROOT\_URL=https://monitor.semogasukses.com/  
      \- GF\_AUTH\_GENERIC\_OAUTH\_ENABLED=true  
      \- GF\_AUTH\_GENERIC\_OAUTH\_NAME=Keycloak  
      \- GF\_AUTH\_GENERIC\_OAUTH\_ALLOW\_SIGN\_UP=true  
      \- GF\_AUTH\_GENERIC\_OAUTH\_CLIENT\_ID=grafana  
      \- GF\_AUTH\_GENERIC\_OAUTH\_CLIENT\_SECRET=${GRAFANA\_CLIENT\_SECRET}  
      \- GF\_AUTH\_GENERIC\_OAUTH\_SCOPES=openid profile email  
      \- GF\_AUTH\_GENERIC\_OAUTH\_AUTH\_URL=https://sso.semogasukses.com/realms/master/protocol/openid-connect/auth  
      \# Komunikasi Internal antar Container (Tanpa lewat Traefik/WAF)  
      \- GF\_AUTH\_GENERIC\_OAUTH\_TOKEN\_URL=http://keycloak:8080/realms/master/protocol/openid-connect/token  
      \- GF\_AUTH\_GENERIC\_OAUTH\_API\_URL=https://sso.semogasukses.com/realms/master/protocol/openid-connect/userinfo  
      \- GF\_AUTH\_GENERIC\_OAUTH\_ROLE\_ATTRIBUTE\_PATH=contains(groups\[\*\], 'grafana-admin') && 'Admin' || 'Viewer'  
    volumes:  
      \- grafana-data:/var/lib/grafana  
    networks:  
      \- app-network  
    labels:  
      \- "traefik.enable=true"  
      \# \--------------------------------------------------------  
      \# Grafana (PUBLIK VIA WAF)  
      \# \--------------------------------------------------------  
      \- "traefik.http.routers.grafana.rule=Host(\`monitor.semogasukses.com\`)"  
      \- "traefik.http.routers.grafana.entrypoints=web-app"  
      \- "traefik.http.routers.grafana.service=grafana-service"  
      \- "traefik.http.services.grafana-service.loadbalancer.server.port=3000"

  \# \============================================================  
  \# KEYCLOAK SSO  
  \# \============================================================  
  keycloak:  
    image: quay.io/keycloak/keycloak:latest  
    command: start-dev  
    environment:  
      KEYCLOAK\_ADMIN: ${KEYCLOAK\_ADMIN}  
      KEYCLOAK\_ADMIN\_PASSWORD: ${KEYCLOAK\_ADMIN\_PASSWORD}  
      KC\_PROXY\_HEADERS: xforwarded  
      KC\_HTTP\_ENABLED: "true"  
      KC\_HOSTNAME: sso.semogasukses.com  
    networks:  
      \- app-network  
    volumes:  
      \- keycloak-data:/opt/keycloak/data  
    labels:  
      \- "traefik.enable=true"  
      \# \--------------------------------------------------------  
      \# Keycloak SSO Base (Halaman Login, Assets, dll)  
      \# HARUS DIBUKA DI 2 PINTU agar Admin via VPN tetap bisa memuat halaman login  
      \# \--------------------------------------------------------  
      \- "traefik.http.routers.keycloak.rule=Host(\`sso.semogasukses.com\`)"  
      \- "traefik.http.routers.keycloak.entrypoints=web-app, web-mgmt"  
      \- "traefik.http.routers.keycloak.service=keycloak-service"  
      \- "traefik.http.services.keycloak-service.loadbalancer.server.port=8080"

      \# \--------------------------------------------------------  
      \# Keycloak Admin Console (HANYA VIA VPN / VLAN 20\)  
      \# \--------------------------------------------------------  
      \- "traefik.http.routers.keycloak-admin.rule=Host(\`sso.semogasukses.com\`) && PathPrefix(\`/admin\`)"  
      \- "traefik.http.routers.keycloak-admin.entrypoints=web-mgmt"  
      \- "traefik.http.routers.keycloak-admin.middlewares=mgmt-whitelist"  
      \- "traefik.http.routers.keycloak-admin.priority=100"

  \# \============================================================  
  \# MARIADB  
  \# \============================================================  
  mariadb:  
    image: mariadb:11  
    restart: unless-stopped  
    environment:  
      MARIADB\_ROOT\_PASSWORD: ${MARIADB\_ROOT\_PASSWORD}  
      MARIADB\_DATABASE: website  
      MARIADB\_USER: website  
      MARIADB\_PASSWORD: ${MARIADB\_PASSWORD}  
    volumes:  
      \- mariadb-data:/var/lib/mysql  
    networks:  
      \- db-network  
    \# TIDAK ADA PORTS EXPOSED KE HOST (Aman, hanya diakses oleh Nginx/Backend di jaringan docker)

### **2\. Mengunci Dashboard Safeline WAF (Hanya untuk VLAN 20\)**

Agar halaman admin Safeline tidak terekspos ke jaringan publik atau aplikasi, Anda harus mengunci port *binding*\-nya langsung dari file instalasi Safeline.

> 1. Buka terminal di server Ubuntu Anda dan masuk ke folder instalasi Safeline (biasanya di /data/safeline).  
> 2. Edit file .env:  
>    nano /data/safeline/.env

> 3. Cari variabel MGT\_PORT. Ubah nilainya agar spesifik mengikat ke IP VLAN 20 (10.20.20.10):  
>    MGT\_PORT=10.20.20.10:9443

> 4. Simpan file, lalu *restart* Safeline agar perubahan IP binding ini aktif:  
>    docker compose down && docker compose up \-d

Setelah langkah ini, Safeline WAF Management HANYA bisa diakses dengan mengetik \[https://10.20.20.10:9443\](https://10.20.20.10:9443) saat Anda terkoneksi ke VPN. Akses melalui IP Public atau VLAN 10 akan langsung ditolak oleh sistem operasi *Host*.

### **3\. Catatan Penting tentang Nginx & MariaDB**

Dalam konfigurasi di atas, Nginx dan MariaDB sudah berada dalam db-network yang sama sehingga secara jaringan bisa saling berkomunikasi.

**Namun, ada aspek arsitektur aplikasi yang perlu diperhatikan:** Nginx (versi murni/HTML statis) **tidak memiliki kemampuan** untuk melakukan query (*SELECT/INSERT*) langsung ke MariaDB. Nginx hanya bisa melayani file statis (.html, .js, .css) atau meneruskan trafik (Reverse Proxy).

Untuk menampilkan database "Product dan Solution" ke halaman web, Anda akan memerlukan sebuah **Backend Application** (misalnya PHP-FPM, Node.js, Python FastAPI, atau Golang) yang berada di tengah-tengah Nginx dan MariaDB:

> * **Nginx** menerima *request* dari user (via Traefik).  
> * **Nginx** meneruskan *request* tersebut ke **Backend Container**.  
> * **Backend Container** mengambil data dari **MariaDB**.  
> * **Backend Container** mengirimkan HTML/JSON kembali ke user melalui Nginx.

---

*User prompt: safeline nya juga mau di masukkan ke container docker. Tolong perbarui docker compose diatas untuk mengakomodasi semuanya*

Response: Menggabungkan Safeline WAF ke dalam satu file docker-compose.yml yang sama adalah langkah yang sangat cerdas\!

Dengan memasukkan Safeline ke ekosistem Compose yang sama, **kita mendapatkan keuntungan keamanan dan performa yang sangat besar:** Traefik dan WAF sekarang bisa berkomunikasi murni di dalam jaringan internal Docker (Bridge). Trafik dari WAF tidak perlu lagi keluar ke antarmuka Host (10.10.10.10:8080) untuk masuk ke Traefik.

Berikut adalah konfigurasi docker-compose.yml "All-in-One" yang sudah disesuaikan. Saya menyertakan struktur standar *container* Safeline (Tengine, Detector, Management, PostgreSQL, Redis) dan menghubungkannya dengan arsitektur VLAN Anda.

### **docker-compose.yml Terpadu**

version: "3.8"

networks:  
  app-network:  
    driver: bridge  
    internal: true  
  mgmt-network:  
    driver: bridge  
    internal: true  
  db-network:  
    driver: bridge  
    internal: true  
  \# Jaringan internal khusus untuk komponen-komponen Safeline WAF  
  safeline-network:  
    driver: bridge  
    internal: true

volumes:  
  mariadb-data:  
  grafana-data:  
  keycloak-data:  
  safeline-pg-data:  
  safeline-redis-data:  
  safeline-logs:  
  safeline-data:

services:  
  \# \============================================================  
  \# 1\. SAFELINE WAF (FRONTGATE & SECURITY)  
  \# \============================================================  
    
  \# A. Tengine (Reverse Proxy / WAF Engine yang berhadapan dengan User)  
  safeline-tengine:  
    image: chaitin/safeline-tengine:latest  
    container\_name: safeline-tengine  
    ports:  
      \# \[VLAN 10 \- APP\] Listen di IP Publik/App untuk trafik User  
      \- "10.10.10.10:80:80"  
      \- "10.10.10.10:443:443"  
    volumes:  
      \- safeline-data:/etc/nginx  
      \- safeline-logs:/var/log/nginx  
    networks:  
      \- safeline-network  
      \- app-network \# Harus terhubung ke app-network agar bisa mengirim trafik ke Traefik

  \# B. Management UI (Dashboard Safeline)  
  safeline-mgt:  
    image: chaitin/safeline-mgt:latest  
    container\_name: safeline-mgt  
    ports:  
      \# \[VLAN 20 \- MGMT\] Listen di IP Manajemen untuk Admin (via VPN)  
      \# Port internal safeline mgt adalah 1443  
      \- "10.20.20.10:9443:1443"  
    volumes:  
      \- safeline-data:/safeline  
      \- safeline-logs:/var/log/safeline  
    networks:  
      \- safeline-network  
      \- mgmt-network  
    depends\_on:  
      \- safeline-pg  
      \- safeline-redis  
      \- safeline-mgt-api

  \# C. Detector (Mesin Deteksi Ancaman WAF)  
  safeline-detector:  
    image: chaitin/safeline-detector:latest  
    container\_name: safeline-detector  
    volumes:  
      \- safeline-data:/safeline  
      \- safeline-logs:/var/log/safeline  
    networks:  
      \- safeline-network

  \# D. Mgt API, Database & Redis untuk Safeline  
  safeline-mgt-api:  
    image: chaitin/safeline-mgt-api:latest  
    container\_name: safeline-mgt-api  
    networks:  
      \- safeline-network  
    depends\_on:  
      \- safeline-pg  
      \- safeline-redis  
        
  safeline-pg:  
    image: postgres:15.2  
    container\_name: safeline-pg  
    environment:  
      POSTGRES\_USER: ${SAFELINE\_DB\_USER:-safeline-ce}  
      POSTGRES\_PASSWORD: ${SAFELINE\_DB\_PASS:-safeline-pass}  
    volumes:  
      \- safeline-pg-data:/var/lib/postgresql/data  
    networks:  
      \- safeline-network

  safeline-redis:  
    image: redis:7.0  
    container\_name: safeline-redis  
    volumes:  
      \- safeline-redis-data:/data  
    networks:  
      \- safeline-network

  \# \============================================================  
  \# 2\. TRAEFIK (ROUTER INTERNAL)  
  \# \============================================================  
  traefik:  
    image: traefik:v2.10  
    container\_name: traefik  
    command:  
      \- "--providers.docker=true"  
      \- "--providers.docker.exposedbydefault=false"  
      \- "--providers.docker.endpoint=unix:///var/run/docker.sock"  
      \- "--entrypoints.web-app.address=:8080"  
      \- "--entrypoints.web-mgmt.address=:80"  
      \- "--api.dashboard=true"  
    ports:  
      \# \[VLAN 20 \- MGMT\] Listen khusus jalur VPN untuk Traefik Dashboard & SSO Admin  
      \- "10.20.20.10:80:80"  
      \# PERHATIAN: Port 8080 (web-app) DIHAPUS DARI HOST BINDING\!  
      \# Traefik tidak lagi diekspos ke Host Ubuntu.  
      \# Safeline WAF akan langsung menembak "http://traefik:8080" via jaringan internal.  
    volumes:  
      \- "/var/run/docker.sock:/var/run/docker.sock:ro"  
    networks:  
      \- app-network  
      \- mgmt-network  
    labels:  
      \- "traefik.enable=true"  
      \# Traefik Dashboard (HANYA VIA VPN)  
      \- "traefik.http.routers.traefik-dashboard.rule=Host(\`traefik.mgmt.semogasukses.com\`)"  
      \- "traefik.http.routers.traefik-dashboard.entrypoints=web-mgmt"  
      \- "traefik.http.routers.traefik-dashboard.service=api@internal"  
      \- "traefik.http.middlewares.mgmt-whitelist.ipallowlist.sourcerange=10.30.30.0/24"  
      \- "traefik.http.routers.traefik-dashboard.middlewares=mgmt-whitelist"

  \# \============================================================  
  \# 3\. NGINX \- LANDING PAGE  
  \# \============================================================  
  nginx:  
    image: nginx:alpine  
    container\_name: nginx  
    networks:  
      \- app-network  
      \- db-network  
    depends\_on:  
      \- mariadb  
    volumes:  
      \- ./nginx/html:/usr/share/nginx/html:ro  
      \- ./nginx/conf.d:/etc/nginx/conf.d:ro  
    labels:  
      \- "traefik.enable=true"  
      \- "traefik.http.routers.landing.rule=Host(\`semogasukses.com\`)"  
      \- "traefik.http.routers.landing.entrypoints=web-app"  
      \- "traefik.http.routers.landing.service=landing-service"  
      \- "traefik.http.services.landing-service.loadbalancer.server.port=80"

  \# \============================================================  
  \# 4\. GRAFANA  
  \# \============================================================  
  grafana:  
    image: grafana/grafana:latest  
    container\_name: grafana  
    environment:  
      \- GF\_SERVER\_ROOT\_URL=https://monitor.semogasukses.com/  
      \- GF\_AUTH\_GENERIC\_OAUTH\_ENABLED=true  
      \- GF\_AUTH\_GENERIC\_OAUTH\_NAME=Keycloak  
      \- GF\_AUTH\_GENERIC\_OAUTH\_ALLOW\_SIGN\_UP=true  
      \- GF\_AUTH\_GENERIC\_OAUTH\_CLIENT\_ID=grafana  
      \- GF\_AUTH\_GENERIC\_OAUTH\_CLIENT\_SECRET=${GRAFANA\_CLIENT\_SECRET}  
      \- GF\_AUTH\_GENERIC\_OAUTH\_SCOPES=openid profile email  
      \- GF\_AUTH\_GENERIC\_OAUTH\_AUTH\_URL=https://sso.semogasukses.com/realms/master/protocol/openid-connect/auth  
      \- GF\_AUTH\_GENERIC\_OAUTH\_TOKEN\_URL=http://keycloak:8080/realms/master/protocol/openid-connect/token  
      \- GF\_AUTH\_GENERIC\_OAUTH\_API\_URL=https://sso.semogasukses.com/realms/master/protocol/openid-connect/userinfo  
      \- GF\_AUTH\_GENERIC\_OAUTH\_ROLE\_ATTRIBUTE\_PATH=contains(groups\[\*\], 'grafana-admin') && 'Admin' || 'Viewer'  
    volumes:  
      \- grafana-data:/var/lib/grafana  
    networks:  
      \- app-network  
    labels:  
      \- "traefik.enable=true"  
      \- "traefik.http.routers.grafana.rule=Host(\`monitor.semogasukses.com\`)"  
      \- "traefik.http.routers.grafana.entrypoints=web-app"  
      \- "traefik.http.routers.grafana.service=grafana-service"  
      \- "traefik.http.services.grafana-service.loadbalancer.server.port=3000"

  \# \============================================================  
  \# 5\. KEYCLOAK SSO  
  \# \============================================================  
  keycloak:  
    image: quay.io/keycloak/keycloak:latest  
    container\_name: keycloak  
    command: start-dev  
    environment:  
      KEYCLOAK\_ADMIN: ${KEYCLOAK\_ADMIN}  
      KEYCLOAK\_ADMIN\_PASSWORD: ${KEYCLOAK\_ADMIN\_PASSWORD}  
      KC\_PROXY\_HEADERS: xforwarded  
      KC\_HTTP\_ENABLED: "true"  
      KC\_HOSTNAME: sso.semogasukses.com  
    networks:  
      \- app-network  
    volumes:  
      \- keycloak-data:/opt/keycloak/data  
    labels:  
      \- "traefik.enable=true"  
      \# SSO Base (Masuk dari WAF & VPN)  
      \- "traefik.http.routers.keycloak.rule=Host(\`sso.semogasukses.com\`)"  
      \- "traefik.http.routers.keycloak.entrypoints=web-app, web-mgmt"  
      \- "traefik.http.routers.keycloak.service=keycloak-service"  
      \- "traefik.http.services.keycloak-service.loadbalancer.server.port=8080"  
      \# SSO Admin (HANYA VIA VPN)  
      \- "traefik.http.routers.keycloak-admin.rule=Host(\`sso.semogasukses.com\`) && PathPrefix(\`/admin\`)"  
      \- "traefik.http.routers.keycloak-admin.entrypoints=web-mgmt"  
      \- "traefik.http.routers.keycloak-admin.middlewares=mgmt-whitelist"  
      \- "traefik.http.routers.keycloak-admin.priority=100"

  \# \============================================================  
  \# 6\. MARIADB  
  \# \============================================================  
  mariadb:  
    image: mariadb:11  
    container\_name: mariadb  
    restart: unless-stopped  
    environment:  
      MARIADB\_ROOT\_PASSWORD: ${MARIADB\_ROOT\_PASSWORD}  
      MARIADB\_DATABASE: website  
      MARIADB\_USER: website  
      MARIADB\_PASSWORD: ${MARIADB\_PASSWORD}  
    volumes:  
      \- mariadb-data:/var/lib/mysql  
    networks:  
      \- db-network

### **Perubahan Signifikan pada Arsitektur:**

> 1. **Traefik Sepenuhnya Tersembunyi (No Exposed Port 8080):** Perhatikan pada konfigurasi traefik, saya menghapus port mapping 10.10.10.10:8080:8080. Mengapa? Karena Traefik dan WAF (safeline-tengine) sekarang berada di app-network yang sama. Mereka bisa berkomunikasi langsung di dalam memori docker.  
> 2. **Konfigurasi di Dashboard Safeline (Penting\!):** Saat Anda mengakses Dashboard Safeline (di \[https://10.20.20.10:9443\](https://10.20.20.10:9443) menggunakan VPN), Anda perlu membuat pengaturan situs untuk semogasukses.com, monitor.semogasukses.com, dan sso.semogasukses.com. Pada bagian **Upstream Server / Backend Node** di Safeline, Anda **TIDAK** lagi memasukkan IP Host. Anda cukup memasukkan nama containernya: 👉 http://traefik:8080  
> 3. **Variabel Environment Safeline:** Safeline membutuhkan database internal PostgreSQL dan Redis. Saya telah menambahkan *environment variables* dasar untuk DB Safeline. Anda bisa menambahkan file .env di folder yang sama untuk mengatur *password*\-nya (misal: SAFELINE\_DB\_PASS=rahasia123).

Dengan desain ini, **Zero Trust Network** Anda sudah sempurna. Trafik publik dikunci secara fisik di port 80/443 (VLAN 10), masuk ke *Tengine* (WAF), lalu dilempar ke *Traefik* di dalam ruang hampa Docker, lalu dipilah ke aplikasi masing-masing. Trafik Admin murni masuk dari pintu belakang via IP VLAN 20\.

---

*User prompt: Harusnya kan pakai satu network driver gapapa kan ya? jadi yang di expose keluar cuma beberapa saja*

Response: Anda **benar sekali\!** Menggunakan satu jaringan internal (misalnya hanya membuat satu main-network berjenis *bridge*) sebenarnya **tidak masalah sama sekali**, dan sistem Anda akan tetap berjalan dengan sempurna.

Pemahaman Anda sangat tepat: selama kita tidak menuliskan ports: "X:Y" pada sebuah container, maka container tersebut **sepenuhnya tertutup dari dunia luar** (host/internet/VLAN), terlepas dari berapapun jumlah jaringan Docker yang kita buat.

Lalu, mengapa saya sebelumnya memecahnya menjadi banyak jaringan (app-network, mgmt-network, db-network, safeline-network)?

Alasannya adalah prinsip **Isolasi Internal (East-West Security)**. Mari kita lihat perbandingannya:

### **Skenario 1: Menggunakan 1 Jaringan (Single Network)**

Jika semua container disatukan ke dalam satu jaringan (misal: default), maka Traefik, Nginx, Grafana, MariaDB, hingga database internal milik Safeline (Postgres & Redis) berada di satu "ruangan" yang sama.

> * **Kelebihan:** Konfigurasi docker-compose.yml jauh lebih rapi, ringkas, dan mudah di-maintenance.  
> * **Kekurangan:** Jika suatu hari *Landing Page* (Nginx) Anda memiliki celah keamanan dan berhasil diretas oleh *hacker*, *hacker* tersebut (yang kini berada di dalam container Nginx) bisa langsung melakukan *ping*, *scanning*, dan mencoba meretas mariadb:3306, safeline-pg:5432, atau safeline-redis:6379 karena mereka semua berada di satu ruangan yang sama.

### **Skenario 2: Menggunakan Banyak Jaringan (Multi-Network)**

Ini adalah pendekatan *Zero Trust* di dalam Docker (seperti konfigurasi saya sebelumnya).

> * Container Nginx hanya dimasukkan ke app-network (untuk ngobrol dengan Traefik) dan db-network (untuk ngobrol dengan MariaDB).  
> * Nginx **tidak akan pernah bisa** menyentuh atau melihat container safeline-pg atau safeline-redis, karena database milik Safeline diisolasi khusus di safeline-network.  
> * Jika Nginx diretas, kerusakan bisa dilokalisir hanya pada container yang satu jaringan dengannya.

### **Kesimpulan & Rekomendasi**

Jika arsitektur ini untuk skala **Enterprise/Produksi**, memecah jaringan berdasarkan fungsinya (DB sendiri, WAF sendiri, App sendiri) adalah **Best Practice** yang sangat disarankan.

Namun, jika Anda lebih mengutamakan **kesederhanaan konfigurasi** (dan mengingat Anda sudah dilindungi WAF di luar), menyatukan semuanya ke dalam satu jaringan adalah pilihan yang sangat masuk akal.

Jika Anda ingin menggunakan **satu jaringan saja**, Anda cukup menghapus blok networks di setiap *services*, dan Docker Compose otomatis akan memasukkan semuanya ke dalam satu jaringan bawaan (default\_network). Konfigurasi Anda akan menjadi jauh lebih ringkas seperti ini:

version: "3.8"

\# Tidak perlu mendefinisikan networks secara eksplisit, biarkan Docker membuat 1 default bridge network.

volumes:  
  mariadb-data:  
  \# ... (volume lainnya)

services:  
  traefik:  
    image: traefik:v2.10  
    \# ... (command dan labels tetap sama) ...  
    ports:  
      \- "10.20.20.10:80:80" \# VPN / MGMT

  safeline-tengine:  
    image: chaitin/safeline-tengine:latest  
    ports:  
      \- "10.10.10.10:80:80" \# APP / Publik  
      \- "10.10.10.10:443:443"

  safeline-mgt:  
    image: chaitin/safeline-mgt:latest  
    ports:  
      \- "10.20.20.10:9443:1443" \# MGMT  
      
  mariadb:  
    image: mariadb:11  
    \# ... Tidak ada expose port, aman dari luar. Bisa dipanggil via "mariadb:3306" oleh container lain.

Pilihan sepenuhnya ada di tangan Anda. Apakah Anda lebih menyukai kesederhanaan (1 network) atau keamanan internal ekstra (multi-network)?

---

