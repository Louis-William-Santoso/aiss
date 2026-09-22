# ============================================================
# AISS UTS — Skrip MikroTik (RouterOS v7)
# Segmentasi VLAN + WireGuard VPN Admin + Firewall
# ============================================================
# ADAPTASI YANG PERLU KAMU SESUAIKAN:
#   - Nama bridge/interfaces LAN & WAN (contoh: bridge1, ether1)
#   - Subnet yang dipakai (default sesuai .env: 10.10.10.0/24 app,
#     10.20.20.0/24 mgmt, 10.30.30.0/24 VPN)
#
# Topologi:
#   Internet ── ether1 (WAN)
#                ├─ VLAN 10 (app)     : 10.10.10.0/24  → SafeLine WAF di 10.10.10.10
#                └─ VLAN 20 (mgmt)    : 10.20.20.0/24  → Traefik/SafeLine/Keycloak 10.20.20.10
#   VPN Admin (WireGuard wg1)         : 10.30.30.0/24  → satu-satunya jalur ke VLAN 20 dari luar

# ------------------------------------------------------------
# 1. VLAN & ALAMAT IP
# ------------------------------------------------------------
/interface vlan add name=vlan10-app vlan-id=10 interface=bridge1
/interface vlan add name=vlan20-mgmt vlan-id=20 interface=bridge1

/ip address add address=10.10.10.1/24  interface=vlan10-app comment="Gateway App"
/ip address add address=10.20.20.1/24  interface=vlan20-mgmt comment="Gateway Management"

# IP gateway pada server Ubuntu (Docker bind IP ini):
#   VLAN 10 -> 10.10.10.10/24 (WAF / safeline-tengine)
#   VLAN 20 -> 10.20.20.10/24 (Traefik mgmt, SafeLine mgt, dsb)

# ------------------------------------------------------------
# 2. WIREGUARD VPN ADMIN
# ------------------------------------------------------------
# Generate keypair admin: /interface wireguard peers print / secret
# (atau gunakan tools.generate-wireguard-key di Winbox / via /interface wireguard)
/interface wireguard add name=wg1 listen-port=13231

# Tambahkan IP tunnel untuk server WireGuard (MikroTik)
/ip address add address=10.30.30.1/24 interface=wg1

# Peer: laptop/HP admin — ganti PUBLIC_KEY dan allowed-address
# /interface wireguard peers add interface=wg1 \
#   public-key="<PUBLIC_KEY_ADMIN>" \
#   allowed-address=10.30.30.2/32

# ------------------------------------------------------------
# 3. FIREWALL (Filter Rules)
# ------------------------------------------------------------
/ip firewall filter
add chain=input  connection-state=established,related action=accept comment="Allow established"
add chain=input  in-interface=wg1   action=accept        comment="Allow VPN to Router"
add chain=input  in-interface=bridge1 action=accept      comment="Allow lokal bridge"
add chain=input  action=drop                             comment="Drop other input"

# Forward: app boleh keluar internet
add chain=forward in-interface=vlan10-app     connection-state=established,related action=accept comment="Allow App established"
add chain=forward in-interface=vlan10-app     out-interface=ether1 action=accept comment="Allow App to Internet"
add chain=forward in-interface=wg1            out-interface=vlan20-mgmt action=accept comment="Allow VPN to Mgmt (VLAN20)"
add chain=forward in-interface=vlan20-mgmt    connection-state=established,related action=accept comment="Allow Mgmt established"

# ISOLASI: VLAN 10 TIDAK BOLEH MENYENTUH VLAN 20
add chain=forward in-interface=vlan10-app out-interface=vlan20-mgmt action=drop comment="DROP App -> Mgmt"
# Internet TIDAK boleh akses langsung ke VLAN 20
add chain=forward in-interface=ether1      out-interface=vlan20-mgmt action=drop comment="DROP WAN -> Mgmt"
# VPN hanya boleh ke jaringan manajemen & yang diizinkan
add chain=forward in-interface=wg1 action=drop comment="DROP VPN lainnya"

# ------------------------------------------------------------
# 4. NAT
# ------------------------------------------------------------
/ip firewall nat
# Teruskan trafik web publik (80/443) ke WAF di VLAN 10
add chain=dstnat in-interface=ether1 protocol=tcp dst-port=80,443 \
    action=dst-nat to-addresses=10.10.10.10 to-ports=80,443 \
    comment="DNAT Web -> SafeLine WAF (VLAN10)"
# Masquerade internet untuk VLAN
add chain=srcnat out-interface=ether1 action=masquerade comment="Masquerade Internet"

# ------------------------------------------------------------
# 5. DNS Statis (opsional, agar nama domain resolv dari dalam)
# ------------------------------------------------------------
/ip dns static add name=semogasukses.com      address=10.10.10.10
/ip dns static add name=monitor.semogasukses.com address=10.10.10.10
/ip dns static add name=sso.semogasukses.com  address=10.10.10.10
# Untuk user VPN yang ingin akses traefik dashboard langsung:
# /ip dns static add name=traefik.mgmt.semogasukses.com address=10.20.20.10

# ------------------------------------------------------------
# 6. CATATAN SERVER (Ubuntu)
# ------------------------------------------------------------
# Ubuntu host harus mengenali VLAN tagging dari port trunk MikroTik.
# Contoh netplan (/etc/netplan/01-netcfg.yaml):
#
#   network:
#     version: 2
#     ethernets:
#       eth0: { dhcp4: no, optional: true }
#     vlans:
#       vlan10:
#         id: 10
#         link: eth0
#         addresses: [10.10.10.10/24]
#         routes:
#           - to: default
#             via: 10.10.10.1
#       vlan20:
#         id: 20
#         link: eth0
#         addresses: [10.20.20.10/24]
#         routes:
#           - to: 10.20.20.0/24
#             via: 10.20.20.1
#           - to: 10.30.30.0/24
#             via: 10.20.20.1