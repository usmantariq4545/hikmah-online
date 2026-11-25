# Monitoring and alerts

What tells you, on Discord (private server "Hikmah alerts", #alerts) and by email, that something is wrong:

| What | Service | Alerts when |
| --- | --- | --- |
| Site up | HetrixTools uptime monitor | the sign-in page doesn't load, or no longer shows "Log in to Hikmah Online" |
| Server | HetrixTools agent | disk > 85%, RAM > 85%, CPU > 90% for 5 min |
| Background tasks (cron) | Healthchecks.io "Hikmah cron" | no successful cron run for 11 minutes |
| Nightly backup | Healthchecks.io "Hikmah backup" | no finished backup for 26 hours |

Failed sign-in alerts (10 in a row) are separate: they go to the main administrator inside Moodle
(020-security.php).

Free plans and their terms change; check each provider's pricing page. HetrixTools may disable a free
account that nobody signs in to for a long time (reported as 90 days): sign in now and then.

## 1. Alert channels: Discord and email

Discord: a private server "Hikmah alerts" with an #alerts channel; its notification setting is "All Messages"
so alerts reach the phone. Each service connects to it from its own Integrations page; email is the backup.

## 2. Healthchecks.io (cron and backup)

1. Sign up at healthchecks.io, then Integrations → Discord (pick Hikmah alerts / #alerts) and Email.
2. Add check **Hikmah cron**: Simple, period **1 minute**, grace **10 minutes** (one long task can make a run slow).
3. Add check **Hikmah backup**: Simple, period **1 day**, grace **2 hours** (backup runs 02:15 UTC).
4. Copy both ping URLs (`https://hc-ping.com/...`).
5. On the server, as `hikmah`: run `~/set-monitoring.sh`, paste each URL when asked.
   It writes `HEALTHCHECK_CRON_URL` and `HEALTHCHECK_BACKUP_URL` into `.env.prod` and restarts the cron container.
6. Within 2 minutes "Hikmah cron" turns green. "Hikmah backup" turns green after the next backup, or now:
   `cd ~/hikmah-online && HIKMAH_ENV=prod scripts/backup.sh`.

The ping URLs are like passwords (anyone with one can send fake "all well" signals): keep them in
`.env.prod` only, never in the repository.

## 3. HetrixTools (site and server)

1. Sign up at hetrixtools.com, then Contact Lists → add Discord and email.
2. **Uptime monitor**: Website, URL `https://hikmahonline.com/login/index.php` (until 2026-11-25: online.hikmahinstitute.org.pk),
   keyword `Log in to Hikmah Online` (the check interval is the plan default). No SSL alert: Caddy, later Cloudflare, renews the certificate. The keyword comes from
   PHP and the database, so a broken database shows as down even when the web server answers.
3. **Server agent**: Server Monitor → add, copy the install command. Before running it, have the
   script read (it runs as root): paste the command in the chat. Then as `ubuntu`: run the command.
   It runs as its own limited user `hetrixtools` (the `0` after the ID). After installing, in
   `/etc/hetrixtools/hetrixtools.cfg`: `NetworkInterfaces="eth0"` and add `|overlay|nsfs` to `IgnoredDisks`, so
   Docker's short-lived interfaces and mounts are left out. Installed 2026-11-23.
   Warnings (set 2026-11-23): Uptime Monitors → click the monitor name ("Hikmah Online") → the box under each
   graph saves as soon as it changes. CPU ≥ 90%, RAM ≥ 85%, disk ≥ 85%, each averaged over 5 min, repeated every
   60 min; agent silent for 15 min; SSL expiry 10 days before. All go to the monitor's contact list
   ("Default Contact": email + Discord). Monitor locations: London, Singapore, Sydney, Mumbai.
4. **Blacklist monitor** for 157.173.114.25: useful once the site sends email.

Monitors only read pages (GET), so the sign-in limit (20 posts a minute) and fail2ban don't affect them.

## 4. Maintenance

- Planned work longer than 10 minutes (upgrades with maintenance mode): pause "Hikmah cron" in
  Healthchecks and the uptime monitor in HetrixTools, and resume them afterwards.
- To test alerts: `HIKMAH_ENV=prod scripts/dc.sh stop cron`, wait about 12 minutes for the Discord and email alert, then
  `HIKMAH_ENV=prod scripts/dc.sh start cron` (a recovery message follows). For the site, change the keyword to a word
  not on the page for 2 minutes, then change it back.

## 5. At the domain move

- Done 2026-11-25: uptime monitor URL changed to `https://hikmahonline.com/login/index.php` (contabo.md section 11).
  The monitor now reaches the site through Cloudflare; keep Bot Fight Mode off so it is not challenged.
- Optional: a public status page (HetrixTools status page) at `status.<domain>` with a CNAME record.
