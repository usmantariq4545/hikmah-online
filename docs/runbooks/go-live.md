# Runbook: go-live on the production VPS

Run only when the PRD 14.2 go/no-go list at the end of this file is fully ticked.

## 1. Buy and prepare the VPS

- x86, at least 4 vCPU / 8 GB / 160 GB NVMe, Ubuntu LTS, a region with good routing to Pakistan.
- Repeat `staging.md` steps 2 to 4 with `.env.prod` (`SITE_ENV=prod`, the production domain).
- Set the DNS TTL of the production record to 300 the day before.

## 2. Move the data (or start clean)

```bash
# On staging, if the pilot data is being kept:
HIKMAH_ENV=staging scripts/dc.sh exec -T -u www-data moodle php admin/cli/maintenance.php --enable
HIKMAH_ENV=staging scripts/backup.sh --maintenance
# Copy backups/staging/<set> to the VPS (scp or the off-host remote).

# On the VPS:
export HIKMAH_ENV=prod
RESTORE_CONFIRM=yes scripts/restore.sh backups/staging/<set>
scripts/upgrade.sh          # upgrade.php to be safe, settings, purge caches, smoke test
```

A clean start is `scripts/install.sh` instead of the restore.

## 3. Switch production settings

All through `.env.prod`, then `scripts/configure.sh`:

- Real SMTP provider; SPF, DKIM and DMARC published for the sending domain.
- Production Google OAuth redirect: `https://<domain>/admin/oauth2callback.php`.
- Production BBB (`BBB_SERVER_URL`, `BBB_SHARED_SECRET`) if not staying on the free tier.
- Real transfer account details (`HIKMAH_BANK_*`, `HIKMAH_JAZZCASH`, `HIKMAH_EASYPAISA`, `HIKMAH_RAAST`).
- No `STAGING_MAIL_*` variables: production sends real mail.
- Policies: done 2026-11-27 (Privacy Policy, Terms of Use, Refund Policy from config/settings/policies/).

## 4. Point DNS and check

1. Point the production record at the VPS. Caddy issues the certificate on first request.
2. `scripts/smoke-test.sh`
3. Log in as administrator, finance reviewer, instructor and learner; walk one paid enrolment
   and one live class end to end.
4. Schedule the nightly backup (see `backup-restore.md`) and confirm the first one reaches the
   off-host remote.

## 5. Keep staging as staging

Re-point it at `staging.<domain>` and load anonymised data. Every upgrade goes there first.

## Go/no-go (PRD 14.2)

- [ ] All P0 journeys pass staging acceptance with owner sign-off.
- [ ] Payment approvals and enrolments have complete audit trails.
- [ ] Attendance reconciliation validated on real classes (preview-only is acceptable for the first pilot).
- [ ] Nginx + PHP-FPM with HTTPS, secure email, monitoring, scheduled backups and a demonstrated restore.
- [ ] Every plugin in PRD section 9 has a 5.3-compatible release (see `versions.lock` notes).
- [ ] One instructor and a representative student group completed a real pilot with no critical blockers.
- [ ] Documentation exists for course creation, payment review, attendance correction, incident response and learner support.
