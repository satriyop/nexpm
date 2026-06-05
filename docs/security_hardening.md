# Security Hardening
## Step 1: Baseline Check

  Run these first and send me the output if you want me to tailor the next step:

  lsb_release -a
  whoami
  id
  hostname -I
  sudo ss -tulpn
  sudo ufw status verbose
  sudo systemctl status ssh --no-pager

  Also check pending updates:

  sudo apt update
  apt list --upgradable

##  Step 2: Update The Server

  If the update list looks normal:

  sudo apt upgrade -y
  sudo apt autoremove --purge -y

  If a reboot is required:

  [ -f /var/run/reboot-required ] && cat /var/run/reboot-required

  Reboot only if needed:

  sudo reboot

##  Step 3: Create A Non-Root Admin User

  If you already use satriyo with sudo, skip this.

  sudo adduser deploy
  sudo usermod -aG sudo deploy

  Test:

  su - deploy
  sudo whoami

  Expected output:

  root

##  Step 4: Set Up SSH Key Login

  On your local machine, not the server:

  ssh-keygen -t ed25519 -C "your_email_or_device_name"
  ssh-copy-id satriyo@YOUR_SERVER_IP

  Then test from a second terminal:

  ssh satriyo@YOUR_SERVER_IP

  Do not disable password login until this works.

##  Step 5: Harden SSH

  Create a separate SSH hardening config:

  sudo nano /etc/ssh/sshd_config.d/99-hardening.conf

  Put this inside:

  PermitRootLogin no
  PasswordAuthentication no
  KbdInteractiveAuthentication no
  PubkeyAuthentication yes
  X11Forwarding no
  AllowTcpForwarding no
  ClientAliveInterval 300
  ClientAliveCountMax 2
  MaxAuthTries 3

  If your app requires SSH tunneling, do not use AllowTcpForwarding no.

  Validate config:

  sudo sshd -t

  Restart SSH:

  sudo systemctl restart ssh

  Now open a new terminal and test login:

  ssh satriyo@YOUR_SERVER_IP

  Only close your old SSH session after the new one works.

##  Step 6: Tighten UFW

  Your firewall is already active with 22, 80, and 443. Good.

  Set defaults explicitly:

  sudo ufw default deny incoming
  sudo ufw default allow outgoing
  sudo ufw allow OpenSSH
  sudo ufw allow 80/tcp
  sudo ufw allow 443/tcp
  sudo ufw status verbose

  If you have a fixed home/office IP, SSH should be limited to that IP:

  sudo ufw delete allow OpenSSH
  sudo ufw allow from YOUR_PUBLIC_IP to any port 22 proto tcp
  sudo ufw status numbered

  Do this only if your public IP is stable.

##  Step 7: Install Fail2Ban

  sudo apt install fail2ban -y
  sudo systemctl enable --now fail2ban

  Create local jail config:

  sudo nano /etc/fail2ban/jail.local

  Use:

  [sshd]
  enabled = true
  port = ssh
  maxretry = 5
  findtime = 10m
  bantime = 1h
  backend = systemd

  Restart and check:

  sudo systemctl restart fail2ban
  sudo fail2ban-client status
  sudo fail2ban-client status sshd

##  Step 8: Enable Automatic Security Updates

  Ubuntu Server commonly includes unattended upgrades, but verify it:

  sudo apt install unattended-upgrades apt-listchanges -y
  sudo dpkg-reconfigure --priority=low unattended-upgrades

  Check config:

  sudo nano /etc/apt/apt.conf.d/50unattended-upgrades

  Recommended basics:

  Unattended-Upgrade::Remove-Unused-Kernel-Packages "true";
  Unattended-Upgrade::Remove-New-Unused-Dependencies "true";
  Unattended-Upgrade::Automatic-Reboot "false";

  Check timer:

  systemctl status unattended-upgrades --no-pager
  systemctl list-timers apt-daily* --no-pager

  Step 9: Reduce Exposed Services

  From the earlier command:

  sudo ss -tulpn

  Anything listening on 0.0.0.0 or :: is internet-facing unless blocked by UFW. For a Laravel app, usually only these should be public:

  22/tcp or restricted SSH
  80/tcp
  443/tcp

  Databases, Redis, Meilisearch, queues, mail tools, dashboards, etc. should bind to 127.0.0.1 or private network only.

  Step 10: Basic Intrusion Visibility

  Install audit tools:

  sudo apt install auditd audispd-plugins -y
  sudo systemctl enable --now auditd

  Check auth logs:

  sudo journalctl -u ssh --since "24 hours ago"
  sudo last -a
  sudo lastb -a

  lastb may require:

  sudo touch /var/log/btmp
  sudo chmod 600 /var/log/btmp

  Step 11: Lock Down Sudo

  Check sudo users:

  getent group sudo

  Remove anyone who should not be admin:

  sudo deluser USERNAME sudo

  Require sudo password, unless you have a deliberate automation reason:

  sudo grep -R "NOPASSWD" /etc/sudoers /etc/sudoers.d/

  Edit only with:

  sudo visudo

##  Step 12: Laravel App Security Checks

  From /var/www/nexpm:

  php artisan about
  php artisan config:show app.debug
  php artisan config:show app.env

  Production should be:

  APP_ENV=production
  APP_DEBUG=false

  Check .env permissions:

  ls -la .env

  Recommended:

  sudo chown www-data:www-data .env
  sudo chmod 640 .env

  Make sure the web server does not serve sensitive files like .env, .git, backups, or logs.

##  Step 13: Reboot And Confirm

  sudo reboot

  After reconnecting:

  sudo ufw status verbose
  sudo systemctl status ssh fail2ban unattended-upgrades --no-pager
  sudo fail2ban-client status sshd
  sudo ss -tulpn
