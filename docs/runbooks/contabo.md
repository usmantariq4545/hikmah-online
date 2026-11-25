# Runbook: Hikmah Online on a Contabo VPS

Step-by-step from a freshly bought Contabo server to a live HTTPS site with off-site backups.
About an hour the first time. Commands marked **laptop** run in PowerShell on your computer;
everything else runs on the server.

Live since 2026-11-18; since 2026-11-25 at **https://hikmahonline.com** behind Cloudflare (section 11).
Note anything that differs from this runbook in `docs/CHANGELOG.md`.

## 0. What you need before starting

| Item | Notes |
| --- | --- |
| Contabo Cloud VPS 20 | 100 GB NVMe, region Singapore, image **Ubuntu 24.04** (plain, no panel), no add-ons |
| A domain you control | You will add one DNS record, e.g. `learn.<domain>` |
| Access to the GitHub repository | To add a read-only deploy key |
| An email address | For Let's Encrypt certificate notices |
| Cloudflare account with R2 (free up to 10 GB; asks for a card) | For off-site backups |
| SMTP sender (live: Amazon SES, Mumbai; Brevo free also works) | For sign-up and payment emails; section 12 |

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

## 8. Off-site backups (Cloudflare R2)

1. Cloudflare dashboard → R2 Object Storage → create bucket `hikmah-backups` (location: Automatic;
   it stays private: no public access, no custom domain).
2. R2 → Manage API tokens → Create API token: permission **Object Read & Write**, applied to
   **only** `hikmah-backups`. Note the Access Key ID, the Secret Access Key (shown once) and the Account ID.
3. Bucket → Settings → Object lifecycle rules: delete objects after **190 days** (the server keeps
   its own 7 daily, 4 weekly and 6 monthly sets; this stops the bucket growing forever).
4. On the server, as `hikmah` (writes the rclone `r2` remote and `BACKUP_REMOTE=r2:hikmah-backups`, then tests an upload):

```bash
~/set-r2.sh
HIKMAH_ENV=prod scripts/backup.sh       # first backup, by hand
rclone ls r2:hikmah-backups | head      # it should be listed there
```

Restore from R2: `rclone copy r2:hikmah-backups/prod/<stamp> backups/prod/<stamp>`, then
`docs/runbooks/backup-restore.md`.

5. Nightly at 02:15 server time (UTC, which is 07:15 in Pakistan):

```bash
( crontab -l 2>/dev/null; echo '15 2 * * * cd /home/hikmah/hikmah-online && HIKMAH_ENV=prod scripts/backup.sh >> backup.log 2>&1' ) | crontab -
```

Keeps 7 daily, 4 weekly and 6 monthly sets on the server and copies each one to R2.

Uptime, server, cron and backup alerts: `docs/runbooks/monitoring.md`.

Start every container after a reboot (2026-11-23: after a kernel reboot the moodle container stayed
stopped although its restart policy is unless-stopped, and the site returned 502 until it was started by hand):

```bash
( crontab -l 2>/dev/null; echo "@reboot sleep 90 && cd /home/hikmah/hikmah-online && HIKMAH_ENV=prod scripts/dc.sh up -d >> /home/hikmah/boot.log 2>&1" ) | crontab -
```

## 9. Updating the site later (branch, pull request, merge, then deploy)

Every change goes on its own branch and reaches `main` only through a pull request (owner, 2026-11-23):

1. Branch from the latest main: `git switch main && git pull && git switch -c <short-name>`; commit there.
2. `git push -u origin <short-name>`, then open the pull request:
   `https://github.com/usmantariq4545/hikmah-online/compare/main...<short-name>?expand=1`.
3. Merge once the checks pass (secrets scan always; plugin checks when plugin code changed). Claude does
   steps 2–4 with the GitHub CLI after the owner approves in chat (`gh pr create --fill`,
   `gh pr checks --watch`, `gh pr merge --merge --delete-branch`); the owner can also merge on GitHub.
4. `git switch main && git pull`, then deploy (below). Test on the live site; if it is wrong, go back
   to the previous release (below) and fix it on a new branch.

`git push` to `main` itself is refused by `.githooks/pre-push` (switched on by `deploy-live.sh`, or by hand
with `git config core.hooksPath .githooks`). Emergency only: `HIKMAH_ALLOW_MAIN_PUSH=1 git push`.
GitHub's own branch protection would also stop it on GitHub's side, but for private repositories it
needs a paid plan (GitHub Pro).

The live site only ever runs what is on main. From the laptop, in the repository (Git Bash):

```bash
scripts/deploy-live.sh
```

It refuses uncommitted or unpushed work, ships exactly that commit (`git archive`), and on the
server runs: backup, image rebuild, `upgrade.sh` (maintenance mode, upgrade, settings, smoke test).
The work runs on the server, so a dropped connection cannot stop it halfway. The deployed commit is
recorded in `~/hikmah-online/DEPLOYED_COMMIT`; the full log is `~/deploy.log` on the server.

Each successful deploy is tagged on GitHub as `live-<date>-<commit>`; the tag message names the backup
taken just before it. List them with `git tag -n1 -l 'live-*'`.

### Going back to an earlier release

1. Pick the release to return to: `git tag -n1 -l 'live-*'`.
2. If the release being undone changed plugin versions or the database (most do), first put the data
   back as it was before it. On the server, use the backup named in the **bad** release's tag:
   `cd ~/hikmah-online && HIKMAH_ENV=prod scripts/restore.sh backups/prod/<stamp>`.
   Moodle refuses to run older code on a newer database, so this step can't be skipped then.
   Anything learners did since that backup (sign-ups, submissions, payments) is lost: note it first.
3. From the laptop: `scripts/deploy-live.sh live-<date>-<commit>` (the good release).

The tag's backup is kept only while backup retention keeps it (7 daily, 4 weekly, 6 monthly sets;
off-site copies too once `BACKUP_REMOTE` is set).

## 10. If something goes wrong

| Symptom | Check |
| --- | --- |
| Browser cannot reach the site | `ping learn.<domain>` shows the server IP? `sudo ufw status` allows 80/443? `scripts/dc.sh ps` all running? |
| Certificate error | DNS must point at the server before the first visit; `scripts/dc.sh logs caddy` |
| 500 error or blank page | `scripts/dc.sh logs --tail 100 moodle` |
| Site slow | `docker stats --no-stream`; memory near the limit means a bigger plan |
| Restore from backup | `docs/runbooks/backup-restore.md` |
| Cloudflare error 525/526 | Caddy has no valid certificate for that name: `scripts/dc.sh logs caddy`; a name that is not in `SITE_HOST` (e.g. `www`) must be redirected by a Cloudflare rule |
| Some visitors still see an old site after a DNS change | Their provider's DNS still caches the old records (up to ~2 days for a nameserver change); `nslookup <host> 1.1.1.1` shows what the world sees |

## 11. Moving to another domain (done 2026-11-25: online.hikmahinstitute.org.pk → hikmahonline.com)

How the move was done, with no gap for visitors on the old address. About an hour with the owner clicking.

**DNS on Cloudflare (free plan).** The domain stays registered where it is (Namecheap); only its DNS moves.
1. Cloudflare → Add a domain → Free. It imports the current records. Delete the apex/`www` A and AAAA records that
   point at the old web host. Add `A @ <server IP>` as **DNS only** (grey) and `CNAME www → <domain>` **Proxied**.
   Mail records (MX, SPF, DKIM CNAMEs, `autoconfig`/`autodiscover`) stay **DNS only**: proxying breaks them.
   Keep the CAA records if they list `letsencrypt.org` and `sectigo.com` (Caddy's two CAs).
2. SSL/TLS → **Full (strict)**; Edge Certificates → **Always Use HTTPS** on. Rocket Loader off (breaks Moodle's
   scripts); Bot Fight Mode off (can block the uptime monitor).
3. Rules → Redirect Rules → template **"Redirect from WWW to root"** (301, keeps path and query). Caddy has no
   certificate for `www`, so without this rule `www` gives error 525.
4. Caching → Cache Rules → `Moodle static`: expression
   `starts_with(http.request.uri.path, "/theme/styles.php") or … "/theme/javascript.php" … "/theme/image.php" …
   "/theme/font.php" … "/theme/yui_combo.php" … "/lib/javascript.php" … "/lib/requirejs.php"` (one `starts_with` per
   path, joined with `or`), **Eligible for cache**, Edge TTL **use cache-control header if present, bypass if not**.
   Moodle marks only revisioned URLs as cacheable, so nothing stale is kept. Without the rule Cloudflare caches
   `.js`/`.woff2` by extension but not the CSS (`…/styles.php/…/all`) or theme images.
5. Registrar → nameservers → the two Cloudflare names. `nslookup -type=NS <domain> 1.1.1.1` showed them within
   minutes; some providers' DNS took longer (the owner's laptop still saw the old site afterwards).

**Server, before the cutover (site keeps running on the old address).**
6. `cp -p .env.prod ~/env.prod.bak-<date>`, then `SITE_HOST=<new host>, <old host>` (Caddy accepts a comma list) and
   `scripts/dc.sh up -d caddy`. Caddy gets the new certificate straight away while the record is grey; Moodle
   redirects the new name to the old wwwroot. Then turn the `A` record to **Proxied**.
   Renewals behind the proxy: plain-http `/.well-known/acme-challenge/` requests reach Caddy (checked: Caddy, not
   Cloudflare, answered), so HTTP-01 renewal works with Always Use HTTPS on.
7. Owner: Google OAuth client → add `https://<new host>/admin/oauth2callback.php`; reCAPTCHA v2 checkbox keys for the
   new domain.

**Cutover (about 5 minutes of maintenance).**
8. `HIKMAH_ENV=prod scripts/backup.sh`.
9. Maintenance on; in `.env.prod` set `MOODLE_WWWROOT=https://<new host>` and `SITE_HOST=<new host>` (old host
   dropped = switched off); `scripts/dc.sh up -d`; then
   `php public/admin/tool/replace/cli/replace.php --search=https://<old host> --replace=https://<new host> --non-interactive`
   inside the moodle container (note `public/`: Moodle 5.3 keeps the tool there; `admin/cli` stays at the top);
   `admin/cli/purge_caches.php`; maintenance off. Run long server steps detached (`setsid nohup … &`): the laptop's
   SSH connection dropped several times during the move.
10. Owner: `ssh -t hikmah@<server IP> ./set-recaptcha.sh`; then `scripts/dc.sh up -d moodle cron adhoc` and
    `scripts/configure.sh` (030-auth turns reCAPTCHA on when both keys are set).
11. `scripts/smoke-test.sh`; HetrixTools uptime monitor URL → new address (`docs/runbooks/monitoring.md`).
12. Afterwards (owner): remove the old host's DNS record and the old Google redirect URI.

13. Lock the web ports to Cloudflare (done 2026-11-25), so nobody can skip Cloudflare by calling the server's IP.
    As `ubuntu`: `sudo bash ~/hikmah-online/scripts/server/cloudflare-only.sh` (`--off` undoes it). IPv4 goes in
    Docker's `DOCKER-USER` chain, because Docker's published ports bypass ufw; a boot service
    (`hikmah-cloudflare-only.service`) restores it. IPv6 is limited in ufw. SSH is untouched. Re-run it after
    `bootstrap.sh` (which opens 80/443 to everyone) and when Cloudflare's list at cloudflare.com/ips changes (also
    update `docker/caddy/Caddyfile`). Checked: login through Cloudflare 200, direct IPv4/IPv6 to the server time out,
    `/.well-known/acme-challenge/` still reaches Caddy, smoke test passed.

Side effects: everyone is signed out once; Moodle App users add the site again with the new address; certificates
issued before the move carry the old verification link.

## 12. Outgoing email: Amazon SES (done 2026-11-25)

Live: SES in **Asia Pacific (Mumbai)** (`ap-south-1`, production access, 50,000 a day), sender `no-reply@hikmahonline.com`.

1. SES console (Mumbai) → **Identities → Create identity → Domain** `hikmahonline.com`, Easy DKIM RSA 2048, no custom
   MAIL FROM. Add the three `…._domainkey` CNAME records in Cloudflare as **DNS only** (an orange cloud breaks DKIM).
   Status turns Verified within minutes.
2. **SMTP settings** → right-hand box **IAM SMTP credentials → Create IAM credentials** (not "Mail Manager SMTP", which
   is a separate paid service with a `…mail-manager-smtp.amazonaws.com` address). Download the .csv: SMTP user name
   `AKIA…` (20 characters), SMTP password (about 44). An ordinary IAM access key does not work as the password.
3. On the server, as `hikmah`: `~/set-smtp.sh` (asks server `email-smtp.ap-south-1.amazonaws.com`, user name,
   password hidden, sender; trims pasted spaces and names the answer it refuses), then
   `HIKMAH_ENV=prod scripts/dc.sh up -d moodle cron adhoc`.
4. Test: `email_to_user()` from a CLI PHP snippet in the moodle container. Checked in Gmail: inbox, SPF, DKIM
   (hikmahonline.com) and DMARC all PASS.
