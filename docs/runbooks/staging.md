# Runbook: staging on Oracle Cloud (Ampere A1)

Staging is the public test site and the rehearsal for production. It must be rebuildable from
this repository in under an hour; time yourself the first time and note it in `docs/CHANGELOG.md`.

Not yet done from this laptop: nothing in this runbook has been executed against a real Oracle
instance. Treat the first run as the test of the runbook.

## 1. Create the instance

1. Oracle Cloud console, your **home region**: Compute > Instances > Create.
2. Shape **VM.Standard.A1.Flex**, **2 OCPU / 12 GB** (stays inside Always Free).
3. Image Ubuntu 24.04 (or 22.04). Boot volume 100 GB.
4. Add your SSH public key. Note the public IP.

## 2. Open the firewall (both places)

- VCN > Security list: ingress TCP 80 and 443 from `0.0.0.0/0`, UDP 443 optional.
- On the host:

```bash
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
```

## 3. Install Docker and get the code

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo useradd -m -s /bin/bash -G docker hikmah
sudo -iu hikmah
git clone <your repository URL> hikmah-online
cd hikmah-online
```

## 4. Environment file and DNS

1. Point `staging.<domain>` (A record) at the instance's public IP.
2. Create `.env.staging` from `.env.example` on the server (never in Git):

```
COMPOSE_PROJECT_NAME=hikmah
SITE_ENV=staging
MOODLE_WWWROOT=https://staging.<domain>
SITE_HOST=staging.<domain>
ACME_EMAIL=<address for Let's Encrypt>
MOODLE_IMAGE=ghcr.io/<owner>/hikmah-moodle:<tag>
NGINX_IMAGE=ghcr.io/<owner>/hikmah-nginx:<tag>
DB_PASSWORD=<generated>
HIKMAH_ADMIN_PASSWORD=<generated>
MOODLE_UPGRADE_KEY=<generated>
SMTP_HOST=… SMTP_USER=… SMTP_PASSWORD=… SMTP_NOREPLY=no-reply@<domain>
STAGING_MAIL_DIVERT=<inbox for diverted mail>
STAGING_MAIL_ALLOWLIST=<comma-separated real testers>
GOOGLE_OAUTH_CLIENT_ID=… GOOGLE_OAUTH_CLIENT_SECRET=…
HIKMAH_BANK_TITLE=… HIKMAH_BANK_IBAN=… HIKMAH_JAZZCASH=… HIKMAH_EASYPAISA=… HIKMAH_RAAST=…
BACKUP_REMOTE=<rclone remote:path>
```

3. `chmod 600 .env.staging`.
4. Google Cloud console: add `https://staging.<domain>/admin/oauth2callback.php` as an authorised
   redirect URI of the OAuth client.

## 5. Start

Images are pulled from the registry (built by `.github/workflows/build.yml` for arm64 and amd64);
nothing is built or edited on the server.

```bash
export HIKMAH_ENV=staging
docker login ghcr.io            # if the images are private
scripts/install.sh              # first time: installs Moodle and applies config/settings
# or, from a backup:
scripts/restore.sh backups/staging/<set> && scripts/configure.sh
```

A red **STAGING** banner shows on every page, and all mail except the allow-list goes to the
divert inbox.

## 6. Check and schedule backups

```bash
scripts/smoke-test.sh
crontab -e
# 15 2 * * * cd /home/hikmah/hikmah-online && HIKMAH_ENV=staging scripts/backup.sh >> backup.log 2>&1
```

Install `rclone` and configure the remote named in `BACKUP_REMOTE` so backups leave the host:
Oracle's free capacity and terms can change.

## 7. Prove it is rebuildable

```bash
HIKMAH_ENV=staging scripts/backup.sh --maintenance
HIKMAH_ENV=staging scripts/dc.sh down -v      # destroys staging data on purpose
HIKMAH_ENV=staging RESTORE_CONFIRM=yes scripts/restore.sh backups/staging/<set>
```

Pass condition: site identical, users can log in, smoke test green, under one hour.

## BigBlueButton on staging

Moodle's free tier (25 users, 60 minutes, recordings 7 days). An administrator accepts its data
processing agreement once in Site administration > Plugins > Activity modules > BigBlueButton.
Attendance callbacks need the public HTTPS hostname; they cannot reach a laptop.
