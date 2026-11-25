# Runbook: backup and restore

## What a backup set contains

`backups/<env>/<UTC timestamp>/`

| File | Content |
| --- | --- |
| `database.dump` | `pg_dump -Fc` of the Moodle database |
| `moodledata.tgz` | moodledata without `cache`, `localcache`, `sessions`, `temp`, `trashdir`, `lock`, `muc` |
| `muc-config.php` | cache store mapping (Redis) |
| `versions.lock` | the Moodle and plugin versions the data belongs to |
| `SHA256SUMS` | checksums, verified before every restore |

A set holds personal data, receipts and integration secrets stored in the database. Keep it
private; never commit it or share it in chat. `.env.<env>` is not in the set: keep a copy in a
password manager, because a restore needs the same database password.

## Back up

```bash
scripts/backup.sh                 # nightly; site stays up
scripts/backup.sh --maintenance   # consistent snapshot; use before upgrades and go-live
```

Retention is applied on every run: 7 daily, 4 weekly, 6 monthly sets, on this host and on the
off-host copy (`BACKUP_REMOTE`). Off-host sets are only pruned when the remote listing works and
already holds the new set, so a broken connection never deletes remote copies.

Off-host copy: set `BACKUP_REMOTE=<rclone remote:path>` in the env file and install `rclone`.
Without it the script says plainly that the backup exists only on this machine.

Nightly schedule on a server:

```
15 2 * * * cd /home/hikmah/hikmah-online && HIKMAH_ENV=prod scripts/backup.sh >> backup.log 2>&1
```

## Restore

```bash
scripts/restore.sh backups/<env>/<set>
```

It verifies checksums, asks you to type `restore`, stops web and cron, replaces the database and
moodledata, fixes ownership, flushes Redis, starts everything, purges caches and runs the smoke
test. Set `RESTORE_CONFIRM=yes` to skip the question in a scripted drill.

A restore replaces everything in the target environment. Restore into staging or a scratch
project first unless production is already lost.

## Restore drill (monthly, and before go-live)

1. `scripts/backup.sh --maintenance` on the source.
2. On a fresh server (or after `scripts/dc.sh down -v` on staging): `scripts/restore.sh <set>`.
3. Pass: smoke test green, an administrator and a learner can log in, a course page and a
   receipt (as finance reviewer) open, the row counts of `mdl_user` and `mdl_course` match.
4. Record the date, duration and result in `docs/CHANGELOG.md`.

## Recovery targets

RPO and RTO are still to be agreed (PRD open decision). With a nightly backup the worst-case data
loss is 24 hours; a restore on the reference VPS should take well under an hour.
