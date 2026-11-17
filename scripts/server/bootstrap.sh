#!/usr/bin/env bash
# One-time preparation of a fresh Ubuntu 24.04 server (Contabo or any VPS) for Hikmah Online.
#
# Run as root, once, right after the server is created:
#   curl -fsSLo bootstrap.sh https://raw.githubusercontent.com/<owner>/hikmah-online/main/scripts/server/bootstrap.sh
#   (or copy this file over with scp), then:
#   bash bootstrap.sh
#
# What it does (each step is safe to run again):
#   1. Security updates now, and automatic security updates from then on (no automatic reboots).
#   2. A 'hikmah' user that runs the site, in the docker group, with root's SSH keys copied in.
#   3. Firewall: only SSH, HTTP and HTTPS are reachable.
#   4. fail2ban against SSH password guessing.
#   5. Docker Engine + Compose plugin from Docker's own apt repository, with log rotation.
#   6. A swap file, so a memory spike slows the site instead of killing it.
#   7. rclone, for copying backups off the server.
#   8. SSH: password logins switched off (keys only; root keeps key login for admin), but ONLY if the hikmah user has a key,
#      so you can never lock yourself out.
#
# It installs nothing of Hikmah's own and touches no Moodle data.
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "Run this as root (or with sudo)." >&2
    exit 1
fi
. /etc/os-release
if [ "${ID:-}" != "ubuntu" ]; then
    echo "This script is for Ubuntu (found: ${PRETTY_NAME:-unknown})." >&2
    exit 1
fi

APP_USER="${APP_USER:-hikmah}"
SWAP_SIZE="${SWAP_SIZE:-4G}"
export DEBIAN_FRONTEND=noninteractive

say() { printf '\n==> %s\n' "$*"; }

say "Updating the system"
apt-get update -q
apt-get -y -q -o Dpkg::Options::=--force-confold upgrade
apt-get install -y -q ca-certificates curl gnupg git ufw fail2ban unattended-upgrades rclone \
    bc openssl tzdata

say "Automatic security updates (no automatic reboot)"
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF
cat > /etc/apt/apt.conf.d/52hikmah-unattended <<'EOF'
Unattended-Upgrade::Automatic-Reboot "false";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
EOF

say "Server clock on UTC (Moodle shows times in each user's timezone)"
timedatectl set-timezone UTC || true

say "User '$APP_USER'"
if ! id "$APP_USER" >/dev/null 2>&1; then
    useradd -m -s /bin/bash "$APP_USER"
fi

if [ -s /root/.ssh/authorized_keys ]; then
    install -d -m 700 -o "$APP_USER" -g "$APP_USER" "/home/$APP_USER/.ssh"
    touch "/home/$APP_USER/.ssh/authorized_keys"
    # Add root's keys that the user does not have yet.
    while IFS= read -r key; do
        [ -z "$key" ] && continue
        grep -qxF "$key" "/home/$APP_USER/.ssh/authorized_keys" || echo "$key" >> "/home/$APP_USER/.ssh/authorized_keys"
    done < /root/.ssh/authorized_keys
    chown "$APP_USER:$APP_USER" "/home/$APP_USER/.ssh/authorized_keys"
    chmod 600 "/home/$APP_USER/.ssh/authorized_keys"
fi

say "Firewall: SSH, HTTP and HTTPS only"
ufw default deny incoming >/dev/null
ufw default allow outgoing >/dev/null
ufw allow OpenSSH >/dev/null
ufw allow 80/tcp >/dev/null
ufw allow 443/tcp >/dev/null
ufw allow 443/udp >/dev/null
ufw --force enable >/dev/null
ufw status verbose | sed -n '1,12p'

say "fail2ban for SSH"
cat > /etc/fail2ban/jail.d/hikmah-sshd.local <<'EOF'
[sshd]
enabled = true
maxretry = 5
findtime = 10m
bantime = 1h
EOF
systemctl enable --now fail2ban >/dev/null
systemctl restart fail2ban

say "Docker Engine and Compose (Docker's own repository)"
if ! command -v docker >/dev/null 2>&1; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -q
    apt-get install -y -q docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
# Keep container logs from filling the disk.
if [ ! -f /etc/docker/daemon.json ]; then
    cat > /etc/docker/daemon.json <<'EOF'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "20m", "max-file": "5" }
}
EOF
    systemctl restart docker
fi
systemctl enable --now docker >/dev/null
usermod -aG docker "$APP_USER"

say "Swap file ($SWAP_SIZE)"
if ! swapon --show | grep -q '/swapfile'; then
    fallocate -l "$SWAP_SIZE" /swapfile
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
sysctl -q -w vm.swappiness=10
echo 'vm.swappiness=10' > /etc/sysctl.d/90-hikmah.conf

say "SSH hardening"
if [ -s "/home/$APP_USER/.ssh/authorized_keys" ]; then
    cat > /etc/ssh/sshd_config.d/90-hikmah.conf <<'EOF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin prohibit-password
EOF
    if sshd -t; then
        systemctl reload ssh || systemctl reload sshd || true
        echo "Password logins are now off (keys only). Day-to-day: ssh $APP_USER@<server IP>. Admin: ssh root@<server IP> with the same key."
    else
        rm -f /etc/ssh/sshd_config.d/90-hikmah.conf
        echo "SSH configuration test failed; left SSH as it was." >&2
    fi
else
    echo "WARNING: no SSH key found for root, so password logins are LEFT ON to avoid locking you out."
    echo "Add your public key to /home/$APP_USER/.ssh/authorized_keys and run this script again."
    echo "Meanwhile set a password for $APP_USER with: passwd $APP_USER"
fi

say "Done"
echo "Docker:  $(docker --version)"
echo "Compose: $(docker compose version)"
echo "Next: log in as '$APP_USER' and follow docs/runbooks/contabo.md from step 4."
