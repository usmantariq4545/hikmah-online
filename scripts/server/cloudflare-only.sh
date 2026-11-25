#!/usr/bin/env bash
# Only Cloudflare may reach the web ports (80/443), so nobody can skip Cloudflare by calling the server's IP.
# SSH is not touched. Run as root on the server; safe to run again.
#
#   sudo bash cloudflare-only.sh          # lock to Cloudflare and keep it after reboots
#   sudo bash cloudflare-only.sh --off    # open 80/443 to everyone again (e.g. moving away from Cloudflare)
#
# IPv4: Docker publishes Caddy's ports with its own rules that bypass ufw, so the check sits in Docker's
# DOCKER-USER chain (chain HIKMAH-CF), restored at boot by hikmah-cloudflare-only.service.
# IPv6: Docker uses a userland proxy there, which ufw does govern, so ufw allows only Cloudflare's v6 ranges.
# Ranges: https://www.cloudflare.com/ips/ (checked 2026-11-25; same list as docker/caddy/Caddyfile).
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "Run as root (sudo)." >&2; exit 1; }

V4="173.245.48.0/20 103.21.244.0/22 103.22.200.0/22 103.31.4.0/22 141.101.64.0/18 108.162.192.0/18 190.93.240.0/20
188.114.96.0/20 197.234.240.0/22 198.41.128.0/17 162.158.0.0/15 104.16.0.0/13 104.24.0.0/14 172.64.0.0/13 131.0.72.0/22"
V6="2400:cb00::/32 2606:4700::/32 2803:f800::/32 2405:b500::/32 2405:8100::/32 2a06:98c0::/29 2c0f:f248::/32"
IF="$(ip -o route get 1.1.1.1 | sed -n 's/.* dev \([^ ]*\).*/\1/p')"
SELF=/usr/local/sbin/hikmah-cloudflare-only
UNIT=/etc/systemd/system/hikmah-cloudflare-only.service

v4_off() {
    while iptables -D DOCKER-USER -i "$IF" -p tcp -m multiport --dports 80,443 -j HIKMAH-CF 2>/dev/null; do :; done
    while iptables -D DOCKER-USER -i "$IF" -p udp --dport 443 -j DROP 2>/dev/null; do :; done
    iptables -F HIKMAH-CF 2>/dev/null || true
    iptables -X HIKMAH-CF 2>/dev/null || true
}

v4_on() {
    v4_off
    iptables -N HIKMAH-CF
    for r in $V4; do iptables -A HIKMAH-CF -s "$r" -j RETURN; done
    iptables -A HIKMAH-CF -j DROP
    iptables -I DOCKER-USER -i "$IF" -p tcp -m multiport --dports 80,443 -j HIKMAH-CF
    iptables -I DOCKER-USER -i "$IF" -p udp --dport 443 -j DROP   # Cloudflare reaches the origin over TCP only
}

if [ "${1:-}" = "--boot" ]; then v4_on; exit 0; fi   # used by the service at boot: IPv4 rules only

if [ "${1:-}" = "--off" ]; then
    v4_off
    systemctl disable --now hikmah-cloudflare-only.service 2>/dev/null || true
    rm -f "$UNIT" "$SELF"; systemctl daemon-reload
    for r in $V6; do ufw delete allow proto tcp from "$r" to any port 80,443 >/dev/null || true; done
    ufw allow 80/tcp >/dev/null; ufw allow 443/tcp >/dev/null; ufw allow 443/udp >/dev/null
    echo "Web ports open to everyone again."; exit 0
fi

v4_on
install -m 755 "$0" "$SELF"
cat > "$UNIT" <<EOF
[Unit]
Description=Hikmah Online: only Cloudflare may reach ports 80/443 (IPv4, DOCKER-USER)
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=$SELF --boot

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable hikmah-cloudflare-only.service >/dev/null 2>&1

for r in $V6; do ufw allow proto tcp from "$r" to any port 80,443 >/dev/null; done
for p in 80/tcp 443/tcp 443/udp; do ufw delete allow "$p" >/dev/null || true; done

echo "Locked: only Cloudflare reaches 80/443 (interface $IF). SSH unchanged."
iptables -S DOCKER-USER
ufw status | grep -cE "80,443/tcp.*ALLOW" | xargs echo "ufw IPv6 Cloudflare rules:"
