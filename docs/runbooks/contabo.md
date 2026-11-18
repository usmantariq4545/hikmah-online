# Runbook: Hikmah Online on a Contabo VPS

Step-by-step from a freshly bought Contabo server to a live HTTPS site with off-site backups.
About an hour the first time. Commands marked **laptop** run in PowerShell on your computer;
everything else runs on the server.

Not yet executed against a real Contabo server: treat the first run as the test of this runbook
and note anything that differed in `docs/CHANGELOG.md`.

## 0. What you need before starting

| Item | Notes |
| --- | --- |
| Contabo Cloud VPS 20 | 100 GB NVMe, region Singapore, image **Ubuntu 24.04** (plain, no panel), no add-ons |
| A domain you control | You will add one DNS record, e.g. `learn.<domain>` |
| Access to the GitHub repository | To add a read-only deploy key |
| An email address | For Let's Encrypt certificate notices |
| Backblaze B2 account (free to open) | For off-site backups, about $1 a month |
| SMTP sender (e.g. Brevo free) | For sign-up and payment emails; can be added after launch |

Secrets (passwords, keys) are typed by you on the server. They never go into Git.

## 1. SSH key (laptop, once)

If you do not have one yet:

```powershell
ssh-keygen -t ed25519 -C "hikmah-admin"
Get-Content $env:USERPROFILE\.ssh\id_ed25519.pub
```

Give the server this key through Contabo's panel (learned on the first install, 2026-11-18):
**Secrets Management → add SSH key** (paste the printed line, which starts with `ssh-ed25519`, into
the *Public SSH key* box), then **VPS → Manage → Reinstall → Custom installation**: Ubuntu 24.04,
default user **root**, select the saved key, set a root password you keep yourself, cloud-init
empty. The *Standard* installation has no key option. Pasting a password into an SSH prompt in an
embedded terminal is unreliable, so prefer the key route.

Contabo's image then accepts the key as the **`ubuntu`** user (root answers "please log in as
ubuntu"); `ubuntu` has passwordless sudo.

## 2. Prepare the server (once)

Copy the setup script over and run it (**laptop**):

```powershell
cd C:\hikmah-online
scp scripts/server/bootstrap.sh ubuntu@<server IP>:
ssh ubuntu@<server IP> "sudo bash bootstrap.sh"
```

It copies your key to the `hikmah` user (ignoring Contabo's "log in as ubuntu" redirect), and its
no-password rule lives in `01-hikmah.conf` so it wins over Contabo's `50-cloud-init.conf`, which
turns passwords on. Check with `sudo sshd -T | grep passwordauthentication` → `no`.

`bootstrap.sh` installs security updates (and turns on automatic ones), a firewall that allows only
SSH/HTTP/HTTPS, fail2ban, Docker, a 4 GB swap file and rclone, creates the `hikmah` user, and
turns password logins off once your key is in place. It is safe to run again.

From now on log in as `hikmah`:

```powershell
ssh hikmah@<server IP>
```

## 3. Point the domain at the server

At your domain registrar or DNS provider add an **A record**: `learn` (or the name you chose)
→ `<server IP>`, TTL 300. Wait until `ping learn.<domain>` shows the server IP.

## 4. Get the code onto the server

**Simplest (used for the first install): copy it from the laptop.** The server then needs no
GitHub access at all. From Git Bash on the laptop, in the repository:

```bash
git ls-files -z | tar --null -T - -czf /tmp/code.tgz      # tracked files only: no .env.*, no backups
scp /tmp/code.tgz hikmah@<server IP>:
ssh hikmah@<server IP> 'mkdir -p ~/hikmah-online && tar -xzf ~/code.tgz -C ~/hikmah-online && rm ~/code.tgz'
```

Repeat the same three lines to update the code later.

**Alternative: a read-only deploy key**, so the server can `git pull` itself. On the server, as `hikmah`:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/github_deploy -N "" -C "hikmah-server"
cat ~/.ssh/github_deploy.pub
printf 'Host github.com\n  IdentityFile ~/.ssh/github_deploy\n  IdentitiesOnly yes\n' >> ~/.ssh/config
chmod 600 ~/.ssh/config
```

GitHub → repository → **Settings → Deploy keys → Add deploy key**: title `hikmah-server`, paste
the key, leave **Allow write access unticked**. Then:

```bash
git clone git@github.com:usmantariq4545/hikmah-online.git
cd hikmah-online
```

## 5. Settings file

```bash
scripts/server/new-env.sh prod learn.<domain> <your email>
nano .env.prod
```

The script generates the database password, administrator password and upgrade key. In `nano`
fill in what only you know (bank/wallet details, BBB server and secret, SMTP, `BACKUP_REMOTE`),
then save with Ctrl+O, Enter, Ctrl+X. The administrator login is `HIKMAH_ADMIN_USER` /
`HIKMAH_ADMIN_PASSWORD` in this file.

Use `staging` instead of `prod` for a test site: it shows a red STAGING banner and only sends mail
to the addresses in `STAGING_MAIL_ALLOWLIST`.

## 6. Images

Pick one; set it in `.env.prod`.

**A. Build on the server (simplest).** No registry or extra token. The first build takes about
10–20 minutes.

```
MOODLE_IMAGE=hikmah-moodle:local
NGINX_IMAGE=hikmah-nginx:local
IMAGE_PULL_POLICY=never
```

```bash
export HIKMAH_ENV=prod
scripts/dc.sh build
```

**B. Pull from GitHub's registry (as the build guide designs it).** Push a version tag (e.g.
`v0.1.0`) so `.github/workflows/build.yml` builds the images, then on the server log in with a
GitHub token that has only `read:packages`:

```
MOODLE_IMAGE=ghcr.io/usmantariq4545/hikmah-moodle:0.1.0
NGINX_IMAGE=ghcr.io/usmantariq4545/hikmah-nginx:0.1.0
IMAGE_PULL_POLICY=always
```

```bash
docker login ghcr.io -u usmantariq4545     # paste the token when asked
```

The build workflow has not been run yet; check its first run in the repository's **Actions** tab.

## 7. Install

```bash
export HIKMAH_ENV=prod
scripts/install.sh
scripts/smoke-test.sh
```

`install.sh` starts every container, installs Moodle and applies all of `config/settings`.
Caddy gets the HTTPS certificate on the first visit, so open `https://learn.<domain>` in a browser
and log in as the administrator.

Then, in the browser:
- If you use Moodle's free BBB tier, accept its data processing agreement once (Site administration
  → Plugins → Activity modules → BigBlueButton). Nobody does this on your behalf.
- Replace the draft privacy notice and terms (Site administration → Users → Privacy and policies).

## 8. Off-site backups (Backblaze B2)

1. Backblaze: create a **private** bucket (e.g. `hikmah-backups`) and an application key limited to
   that bucket.
2. On the server:

```bash
rclone config            # n (new) → name: b2 → storage: b2 → paste key ID and key → defaults
nano .env.prod           # BACKUP_REMOTE=b2:hikmah-backups
HIKMAH_ENV=prod scripts/backup.sh       # first backup, by hand
rclone ls b2:hikmah-backups | head      # it should be listed there
```

3. Nightly at 02:15 server time (UTC, which is 07:15 in Pakistan):

```bash
( crontab -l 2>/dev/null; echo '15 2 * * * cd /home/hikmah/hikmah-online && HIKMAH_ENV=prod scripts/backup.sh >> backup.log 2>&1' ) | crontab -
```

Keeps 7 daily, 4 weekly and 6 monthly sets on the server and copies each one to B2.

## 9. Updating the site later

```bash
cd ~/hikmah-online
git pull                       # or re-copy the code from the laptop (step 4)
export HIKMAH_ENV=prod
scripts/dc.sh build            # option A; for option B change the image tags in .env.prod instead
scripts/upgrade.sh             # backup-safe: maintenance mode, upgrade, settings, smoke test
```

## 10. If something goes wrong

| Symptom | Check |
| --- | --- |
| Browser cannot reach the site | `ping learn.<domain>` shows the server IP? `sudo ufw status` allows 80/443? `scripts/dc.sh ps` all running? |
| Certificate error | DNS must point at the server before the first visit; `scripts/dc.sh logs caddy` |
| 500 error or blank page | `scripts/dc.sh logs --tail 100 moodle` |
| Site slow | `docker stats --no-stream`; memory near the limit means a bigger plan |
| Restore from backup | `docs/runbooks/backup-restore.md` |
