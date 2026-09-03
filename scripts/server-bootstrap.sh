#!/usr/bin/env bash

set -Eeuo pipefail

if [[ ${EUID} -ne 0 ]]; then
    echo "Run this script as root on a fresh Ubuntu Droplet." >&2
    exit 1
fi

if [[ ! -s /root/.ssh/authorized_keys ]]; then
    echo "No root SSH public key found; refusing to disable password login." >&2
    exit 1
fi

source /etc/os-release

if [[ ${ID:-} != ubuntu ]]; then
    echo "This bootstrap supports Ubuntu only." >&2
    exit 1
fi

apt-get update
apt-get install -y ca-certificates curl git rsync unattended-upgrades

install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc

cat > /etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: ${UBUNTU_CODENAME:-$VERSION_CODENAME}
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF

apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

if ! id deploy >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" deploy
fi

usermod -aG docker deploy
install -d -m 0700 -o deploy -g deploy /home/deploy/.ssh
install -m 0600 -o deploy -g deploy /root/.ssh/authorized_keys /home/deploy/.ssh/authorized_keys
install -d -m 0755 -o deploy -g deploy /opt/pikado

if [[ ! -e /swapfile ]]; then
    fallocate -l 2G /swapfile
    chmod 0600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

cat > /etc/ssh/sshd_config.d/99-pikado-hardening.conf <<'EOF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin prohibit-password
EOF

sshd -t
systemctl restart ssh
systemctl enable --now docker

echo "Server bootstrap complete. Open a new SSH session as the deploy user."
