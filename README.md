# Hikmah Online

Hikmah Institute's learning platform: Moodle 5.3 LTS in Docker with three Hikmah plugins
(bank-transfer payments, BBB attendance reconciliation, the Hikmah theme and catalog).
Requirements are in [docs/PRD.md](docs/PRD.md), build rules in [CLAUDE.md](CLAUDE.md), and what
has and has not been verified in [docs/CHANGELOG.md](docs/CHANGELOG.md).

## Run it on this laptop

Needs Docker Desktop running. Commands are for Git Bash, from this folder.

```bash
scripts/init-env.sh        # once: creates .env.dev with generated passwords
scripts/install.sh         # first time: builds images, installs Moodle, applies settings
scripts/dev/seed.sh        # optional: fictional demo courses, accounts, payments, a live class
```

- Site: http://localhost:8080
- Mail inbox (dev): http://localhost:8025
- Administrator: `admin`; its password is `HIKMAH_ADMIN_PASSWORD` in `.env.dev`. The first login
  asks for the required profile fields once.
- Demo accounts (after seeding): `demo.student1`–`demo.student6`, `demo.teacher`, `demo.teacher2`,
  `demo.finance`, `demo.manager`; they share `HIKMAH_DEMO_PASSWORD` in `.env.dev`.

Day to day:

```bash
scripts/dc.sh up -d              # start
scripts/dc.sh stop               # stop, keep data
scripts/configure.sh             # re-apply config/settings (safe to repeat)
scripts/smoke-test.sh            # quick health check
scripts/dev/check-pages.sh       # every role's pages and access rules
scripts/backup.sh                # backup to backups/dev/<timestamp>
scripts/restore.sh backups/dev/<timestamp>
node scripts/build-amd.js        # after editing a plugin's amd/src JavaScript
```

Do not run `scripts/dc.sh down -v` unless you mean to delete the site's data.

After editing theme styles or templates, purge caches:

```bash
scripts/dc.sh exec -T -u www-data moodle php admin/cli/purge_caches.php
```

## Where things are

| Path | What |
| --- | --- |
| `plugins/payment/gateway/hikmahtransfer` | Transfer payments: receipt upload, review queue, approval |
| `plugins/local/hikmahattendance` | BBB evidence to attendance preview, override, apply, report |
| `plugins/theme/hikmah` | Branding, home, catalog, course detail, FAQ, verification, dashboard |
| `config/settings` | Site configuration as code, applied by `scripts/configure.sh` |
| `docker`, `compose` | Images and Compose files for dev and servers |
| `docs/runbooks` | Staging, go-live, backup and restore, upgrades |
| `versions.lock` | Pinned Moodle and third-party plugin versions |
