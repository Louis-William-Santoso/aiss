# jan/02/1970 12:26:54 by RouterOS 6.48.6
# software id = 3IHU-Q6JW
#
# model = RB952Ui-5ac2nD
# serial number = HE408PF9PFT
/interface bridge
add name=LAN
add name=WAN
/interface wireless
set [ find default-name=wlan2 ] ssid=MikroTik
/interface vlan
add interface=LAN name=VLAN-10-APP vlan-id=10
add interface=LAN name=VLAN-20-MGMT vlan-id=20
/interface wireless security-profiles
set [ find default=yes ] supplicant-identity=MikroTik
add authentication-types=wpa-psk,wpa2-psk mode=dynamic-keys name="wifi wan" \
    supplicant-identity="" wpa-pre-shared-key=admin123 wpa2-pre-shared-key=\
    admin123
/interface wireless
set [ find default-name=wlan1 ] band=2ghz-g/n disabled=no mode=ap-bridge \
    security-profile="wifi wan" ssid=aiss-uts
/ip pool
add name=dhcp_pool0 ranges=10.23.23.2-10.23.23.254
add name=dhcp_pool1 ranges=20.20.20.200-20.20.20.254
/ip dhcp-server
add address-pool=dhcp_pool1 disabled=no interface=WAN name=dhcp1
/interface bridge port
add bridge=WAN interface=ether2
add bridge=WAN interface=ether3
add bridge=LAN interface=ether5
add bridge=WAN interface=ether4
add bridge=WAN interface=wlan1
/interface l2tp-server server
set authentication=mschap1,mschap2 enabled=yes ipsec-secret=admin123 \
    use-ipsec=required
/ip address
add address=10.23.23.1/24 interface=LAN network=10.23.23.0
add address=10.10.10.1/24 interface=VLAN-10-APP network=10.10.10.0
add address=10.20.20.1/24 interface=VLAN-20-MGMT network=10.20.20.0
add address=20.20.20.20/24 interface=WAN network=20.20.20.0
/ip dhcp-server network
add address=20.20.20.0/24 gateway=20.20.20.20
/ip firewall filter
add action=add-src-to-address-list address-list="PORT SCANNER" \
    address-list-timeout=none-dynamic chain=input comment=\
    "CATCH PORT SCANNER" in-interface=WAN protocol=tcp psd=21,3s,3,1
/ip firewall nat
add action=dst-nat chain=dstnat comment=\
    "PORT FORWARD 80 & 443 TO 10.10.10.10" dst-address=20.20.20.20 dst-port=\
    80 protocol=tcp to-addresses=10.10.10.10 to-ports=80
add action=dst-nat chain=dstnat dst-address=20.20.20.20 dst-port=443 \
    protocol=tcp to-addresses=10.10.10.10 to-ports=443
/ip firewall raw
add action=drop chain=prerouting comment="DROP PORT SCANNER" \
    src-address-list="PORT SCANNER"
add action=accept chain=prerouting comment=\
    "ACCEPT INTERNAL IP & VPN TO 10.20.20.0/24" dst-address=10.20.20.0/24 \
    src-address=10.20.20.0/24
add action=accept chain=prerouting dst-address=10.20.20.0/24 src-address=\
    10.30.30.0/24
add action=drop chain=prerouting comment=\
    "DROP OTHER TRAFFIC TO 10.20.20.0/24" dst-address=10.20.20.0/24
add action=accept chain=prerouting comment=\
    "ACCEPT NORMAL ICMP TRAFFIC LIMIT=10 BURST=20" icmp-options=8:0 limit=\
    5,5:packet protocol=icmp
add action=drop chain=prerouting comment="DROP ICMP FLOODING" protocol=icmp
/ip service
set telnet disabled=yes
set ftp disabled=yes
set www disabled=yes
set ssh disabled=yes
set api disabled=yes
set api-ssl disabled=yes
/ppp secret
add local-address=10.30.30.1 name=louis password=louis123 profile=\
    default-encryption remote-address=10.30.30.2 service=l2tp
add local-address=10.30.30.1 name=elvin password=elvin123 profile=\
    default-encryption remote-address=10.30.30.3 service=l2tp
add local-address=10.30.30.1 name=hizkia password=hizkia123 profile=\
    default-encryption remote-address=10.30.30.4 service=l2tp
/system clock
set time-zone-name=Asia/Jakarta
/system identity
set name=mikrotik-hizkia
