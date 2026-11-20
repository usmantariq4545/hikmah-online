## Hikmah Online — Build guide for Claude

This tab tells Claude (Claude Code) exactly how to build Hikmah Online from the PRD into a production-ready, portable deployment. Save it as CLAUDE.md in the repo root, and keep the PRD as docs/PRD.md.

### 1. Rules Claude must follow

Read docs/PRD.md before any task; requirement IDs (AUTH-01, ENR-03, ATT-05…) are the contract. When the PRD and this guide disagree, stop and ask.

Never

- Modify Moodle core. All custom code lives in plugins/ (Hikmah plugins and theme) and is mounted or copied into the right Moodle folders.
- Configure the site by clicking in the admin UI as the only record. Every setting goes into config/settings/*.php or a CLI script so a fresh server comes out identical.
- Commit secrets. .env.* files are git-ignored; only .env.example is committed.
- Use the PHP built-in server, Alpine images, or a document root other than moodle/public.
- Write real attendance marks automatically unless local_hikmahattendance | autowrite is enabled for that course. (Owner decision 2026-11-20: autowrite comes on by itself for a course when a live class with start and end times is added; a course can switch it off.)
- Use deprecated Moodle APIs; check lib/upgrade.txt and the 5.3 developer docs.

Always

- Target Moodle 5.3 LTS (pinned tag in versions.lock), PHP 8.4, PostgreSQL 17.
- Follow Moodle coding style; run moodle-plugin-ci checks (phplint, phpcs, phpdoc, savepoints, mustache, grunt) before saying a task is done.
- Write PHPUnit tests for business logic and Behat tests for each P0 user journey you touch.
- Use Moodle APIs: capabilities via access.php, DB via $DB + install.xml + upgrade.php, files via File API, events for every sensitive action, Privacy API provider in every plugin that stores user data, scheduled/adhoc tasks for background work, language strings in lang/en (Urdu/Arabic packs later).
- Store times in UTC; display with userdate().
- Keep each task small: one plugin feature or one infra change per branch, with a short summary of what changed and how it was tested.
- Update docs/CHANGELOG.md and, if a decision changes, the PRD's open-decisions table.

### 2. Environments

One repo, three environments, same images. Production is created by running the staging steps on a new VPS.

| Environment | Where | Purpose | URL | Live classes |
| --- | --- | --- | --- | --- |
| dev | Your laptop: Docker Desktop + WSL2 (Ubuntu 24.04) | Daily building with Claude, fast edit-reload | http://localhost:8080 | Moodle's free BBB tier; callbacks via Cloudflare Tunnel or ngrok only when testing attendance |
| staging | Oracle Cloud Always Free, Ampere A1 (ARM), 2 OCPU / 12 GB, Ubuntu 22.04/24.04 | Public tests with real instructors and students; deployment rehearsal | https://staging.<domain> | Moodle's free BBB tier (25 users, 60 min, recordings kept 7 days) |
| prod | Purchased VPS (x86), at least 4 vCPU / 8 GB / 160 GB NVMe | Live platform | https://<domain> | Free tier for pilot; managed BBB (from about US$45/month) when classes exceed 25 learners or need kept recordings |

Images are built for both linux/amd64 and linux/arm64 so the same image runs on Oracle (ARM) and the VPS (x86). Staging must be destroyable and rebuildable from the repo in under an hour; that is the test of production readiness.

Oracle notes: create the instance in your home region, keep it within 2 OCPU / 12 GB so it stays free after the trial, open ports 80/443 in both the security list and the host firewall, and keep off-host backups because free capacity and terms can change.

### 3. Repository layout

```
hikmah-online/

├── CLAUDE.md                  # this guide

├── docs/

│   
├── PRD.md                 # PRD tab exported as Markdown

│   
├── CHANGELOG.md

│   
└── runbooks/              # staging.md, go-live.md, backup-restore.md, upgrade.md

├── versions.lock              # Moodle tag + every third-party plugin version/commit

├── docker/

│   
├── moodle/Dockerfile      # Debian php:8.4-fpm-bookworm base

│   
├── moodle/php.ini

│   
├── moodle/entrypoint.sh

│   
└── nginx/default.conf.template

├── compose/

│   
├── compose.base.yml       # moodle-fpm, nginx, postgres, redis, clamav, cron

│   
├── compose.dev.yml        # bind-mounts plugins/, mailpit, xdebug

│   
└── compose.server.yml     # staging/prod: caddy or certbot TLS, restart policies, logging

├── config/

│   
├── config.php             # reads everything from env vars

│   
└── settings/              # site configuration as code (Section 5)

├── plugins/

│   
├── payment/gateway/hikmahtransfer/   # paygw_hikmahtransfer

│   
├── local/hikmahattendance/           # local_hikmahattendance

│   
└── theme/hikmah/                     # theme_hikmah

├── scripts/

│   
├── fetch-moodle.sh        # clones pinned Moodle + third-party plugins from versions.lock

│   
├── install.sh             # first install via admin/cli/install_database.php

│   
├── configure.sh           # applies config/settings/*

│   
├── upgrade.sh             # maintenance mode, admin/cli/upgrade.php, purge caches

│   
├── backup.sh / restore.sh

│   
└── smoke-test.sh

├── tests/behat/ and plugin-level tests/

├── .env.example

└── .github/workflows/         # ci.yml (moodle-plugin-ci), build.yml (multi-arch images)
```

Moodle source is not committed; fetch-moodle.sh pulls the pinned tag at build time and copies plugins/ into place (public/payment/gateway/, public/local/, public/theme/ under the 5.1+ layout; confirm paths against the 5.3 tree).

### 4. Infrastructure spec

#### 4.1 Containers

| Service | Image | Notes |
| --- | --- | --- |
| moodle | Built from docker/moodle/Dockerfile on php:8.4-fpm-bookworm | Extensions: pgsql, intl, gd, zip, soap, xml, mbstring, sodium, opcache, exif, redis (pecl). Install locales and generate en_US.UTF-8, en_AU.UTF-8, ar_SA.UTF-8, ur_PK.UTF-8. Add ghostscript, poppler-utils (PDF annotation), fonts for Arabic/Urdu (e.g. Noto Naskh Arabic, Noto Nastaliq Urdu). |
| cron | Same image | Loop: php admin/cli/cron.php every 60 s; separate container so web restarts do not stop it. Optional adhoc_task.php --keep-alive worker. |
| nginx | nginx:stable (Debian) | Root /var/www/moodle/public; try_files $uri /r.php; PHP via FastCGI to moodle:9000; X-Accel-Redirect for /dataroot/ (internal); client_max_body_size 100M; deny direct access to dotfiles. |
| postgres | postgres:17 (Debian) | Volume pgdata; not exposed publicly. |
| redis | redis:7 | Sessions + MUC application cache; no persistence needed beyond AOF optional. |
| clamav | clamav/clamav | antivirus_clamav in TCP socket mode. |
| mailpit (dev only) | axllent/mailpit | Catches all mail locally. |

PHP settings: memory_limit=512M, max_execution_time=300, upload_max_filesize=100M, post_max_size=100M, max_input_vars=5000, opcache enabled with opcache.memory_consumption=256.

Volumes: moodledata (persistent, on the host disk; never inside the image), pgdata, localcache (fast local disk), backups.

#### 4.2 config.php

All values from environment variables: MOODLE_WWWROOT, DB_*, REDIS_HOST, SMTP_*, SITE_ENV (dev|staging|prod). Required lines:

- $CFG->dataroot = '/var/moodledata'; $CFG->localcachedir = '/var/localcache';
- $CFG->session_handler_class = '\core\session\redis'; with session_redis_host from env.
- $CFG->xsendfile = 'X-Accel-Redirect'; $CFG->xsendfilealiases = ['/dataroot/' => $CFG->dataroot];
- $CFG->sslproxy = true; on staging/prod when TLS ends at a proxy.
- $CFG->noemailever = true; on dev unless Mailpit is used.
- Staging: a red "STAGING" banner via theme setting and $CFG->forced_plugin_settings, and outbound email limited to an allow-list.

#### 4.3 Environment files

.env.example lists every variable with a comment. Real files: .env.dev, .env.staging, .env.prod, stored only on the machine that uses them (or in a password manager).

#### 4.4 TLS and domain

Staging and prod terminate TLS with Caddy (automatic Let's Encrypt) or Nginx + certbot. DNS: staging.<domain> → Oracle public IP; <domain> → VPS IP at go-live.

#### 4.5 CI

- ci.yml: on every push, run moodle-plugin-ci for each Hikmah plugin against Moodle 5.3 + PostgreSQL.
- build.yml: on tag, docker buildx build --platform linux/amd64,linux/arm64 and push to GitHub Container Registry.

### 5. Site configuration as code

scripts/configure.sh runs every file in config/settings/ in order, using admin/cli/cfg.php --component=<x> --name=<y> --set=<z> or small PHP CLI scripts that call Moodle APIs. Every script must be idempotent: running it twice changes nothing.

| File | What it sets |
| --- | --- |
| 010-site.php | Site name, timezone default Asia/Karachi, forcetimezone off, default language en + Urdu/Arabic packs installed, forcelogin for course content, guest access off |
| 020-security.php | Password policy, lockout, cookiesecure, cookiehttponly, session timeout, antivirus enabled (ClamAV TCP), upload size limits, allowobjectembed off |
| 030-auth.php | Email-based self-registration on, OAuth 2 Google service (client ID/secret from env), linked logins on, duplicate-email prevention |
| 040-profile.php | Custom profile fields (category "Hikmah profile"): gender, date of birth, country, city, phone, guardian contact (optional), consent flags; required-field enforcement |
| 050-roles.php | Create roles Finance reviewer and Support with the exact capabilities in PRD Section 3; restrict instructors from site-level settings |
| 060-courses.php | Course custom fields: mode, language, price, currency, access policy, eligibility, outcomes, schedule text; course defaults (completion tracking on) |
| 070-enrol.php | Enable self, manual, cohort sync, approval enrolment, payment-based enrolment (enrol_fee) with paygw_hikmahtransfer |
| 080-bbb.php | BBB server URL + secret from env (free tier on dev/staging), recordings on, "Register meeting events" on, BBB activity completion on |
| 090-attendance.php | mod_attendance status set (Present, Late, Excused, Absent, Pending review), local_hikmahattendance threshold 75, autowrite off |
| 100-certificates.php | Import the Hikmah certificate template (PDF background + fields + QR), set verification page public |
| 110-messaging.php | SMTP from env, no-reply address, notification defaults for enrolment, payment, grading, certificates |
| 120-theme.php | Theme hikmah as default, brand colours, logo, catalog settings |
| 130-privacy.php | Site policies (privacy, terms, consent), data retention categories and periods (receipts, recordings, attendance events) |

Roles and capability overrides are also exported to config/roles/*.xml (Moodle role export) for review.

### 6. Plugin spec: paygw_hikmahtransfer

A Moodle payment gateway (Payment API, core_payment) for manual transfers that a reviewer approves. Used through core enrol_fee so access unlocks only through the standard payment-success path. Covers ENR-02 to ENR-06. Read paygw_bank for reference only; do not copy code without checking its licence (GPL is compatible) and do not depend on it.

Learner flow

- Course page shows fee, accepted methods (bank account, JazzCash, Easypaisa, Raast), account details and a unique payment reference (e.g. HKM-<courseid>-<userid>-<short hash>).
- Learner pays outside Moodle, then submits: method, transaction ID, amount, date, receipt file (JPG/PNG/PDF, max 5 MB).
- Status page shows Pending verification, Approved, Rejected (with reason) or Resubmission requested.

Reviewer flow

- Queue page (/payment/gateway/hikmahtransfer/review.php) for users with paygw/hikmahtransfer:review: filters by status, course, method, date; receipt preview inline; approve, reject with reason, or request resubmission.
- Approve calls \core_payment\helper::deliver_order() exactly once (idempotent: wrapped in a DB transaction, guarded by status check).

State machine: draft → pending → approved | rejected | resubmit, resubmit → pending. Every transition writes a history row (who, when, from, to, remarks) and fires an event.

Tables (install.xml): paygw_hikmahtransfer_req (id, paymentarea, itemid, component, userid, amount, currency, method, reference, txnid, paiddate, status, timecreated, timemodified, reviewerid), paygw_hikmahtransfer_hist (requestid, fromstatus, tostatus, userid, remarks, timecreated). Unique index on (component, paymentarea, itemid, userid, status in open states) to block duplicate open requests.

Files: receipts in file area receipt, context system, served only through pluginfile with capability check; antivirus scan on upload (core \core\antivirus\manager); every download logs an event.

Capabilities: submit (authenticated user), review (Finance reviewer, Manager), viewreceipt (Finance reviewer, Manager).

Notifications (message providers): learner on submit, approve, reject, resubmit; reviewers on new submission.

Privacy API: export and delete requests and receipts; retention task deletes receipt files after the configured period (default 365 days) while keeping the history row.

Settings: per-account instructions (bank title, IBAN, JazzCash/Easypaisa numbers, Raast ID), enabled methods, max file size, retention days, reviewer email digest.

Tests: PHPUnit for state transitions, double-approve, duplicate submission, permission checks; Behat for submit → approve → course access, reject → resubmit → approve, unauthorized reviewer denied, infected file rejected (use the EICAR test string on dev).

### 7. Plugin spec: local_hikmahattendance

Turns BBB participation into mod_attendance marks with preview, approval and audit. Covers ATT-02 to ATT-07. It does not run its own webhook receiver: Moodle's BBB module already receives BBB's post-meeting analytics callback ("Register meeting events") and stores per-user data plus the raw payload in bigbluebuttonbn_logs. Rework the existing prototype onto this source.

Inputs

- bigbluebuttonbn_logs rows for the meeting: join logs (log = 'Join'), meeting-events callback rows with the raw JSON in meta.
- Link table local_hikmahatt_link (bbbinstanceid, attendanceid, attendance sessionid, class window start/end, threshold override). Instructors create the link from the attendance session or BBB activity page.

Processing (scheduled task every 5 min + adhoc task on callback event)

- Find linked sessions whose class window has ended and that have a meeting-events callback; sessions past window + 2 h with no callback go to the exceptions queue as "missing callback".
- Parse per-user attendance from the payload. Prefer per-user intervals; if only totals exist, use totals. Map BBB internal user IDs to Moodle user IDs via the join logs.
- Clip each interval to the class window, merge overlaps and reconnects, sum seconds.
- Percentage = attended seconds ÷ window seconds × 100, rounded down to whole percent. Status: ≥ threshold → Present; > 0 and below threshold → Late/Partial (configurable mapping); 0 with no join → Absent; any conflict or unmapped user → Pending review.
- Exclude users with an instructor/moderator role in the course. Cancelled sessions are skipped.
- Write results to local_hikmahatt_decision (sessionid, userid, seconds, denominator, percent, threshold, computedstatus, evidencehash, state = preview|approved|applied|overridden).

Applying

- Default: preview only. Instructor opens the reconciliation page, compares computed status with the register, edits any row with a reason, then clicks Apply.
- Apply writes to mod_attendance through its API only for students with no existing manual mark; never overwrites a manual mark.
- Idempotent: an evidence hash per (session, user) means re-running with the same data does nothing; new data creates a new preview, never a silent change.
- autowrite (per course) applies automatically. Decided 2026-11-20 by the owner: it comes on when a scheduled live class is added (manager::ensure_for_room); the course can switch it off. With autowrite on and no BBB report 2 h after class, joined learners are marked Present and the rest Absent, replaced if the report arrives later.

Audit: every override, approve and apply fires an event and writes local_hikmahatt_audit (who, when, from, to, reason). Reconciliation report shows evidence vs final mark, exportable to CSV.

Schema safety: the analytics payload is not formally documented and can change between BBB versions. Store the BBB version seen, validate the payload against a versioned parser, send unknown shapes to the exceptions queue, and keep fixtures from each BBB version in tests/fixtures/ for a regression test.

Capabilities: local/hikmahattendance:review (teacher), :apply (teacher), :configure (manager), :viewreports (manager).

Privacy API: export and delete decisions and audit rows; retention task deletes raw evidence after the configured period.

Tests: PHPUnit with fixture payloads covering full stay, late join, leave and rejoin, overlaps, missing callback, unmapped user, instructor exclusion, early end, duplicate callback, existing manual mark.

### 8. Plugin spec: theme_hikmah and catalog

A Boost child theme. Covers CRS-02, AUTH-05 and branding. Ship it in two steps so the pilot is not blocked:

- Pilot (1 week): Hikmah colours, logo, fonts (including Arabic/Urdu), login page, footer, staging banner. Moodle's standard course listing.
- Catalog (2–3 weeks, after pilot): catalog page and course detail page.

Catalog page (/theme/hikmah/catalog.php or a block on the front page): course cards from visible courses with image, title, mode, language, teacher(s), start date, price or "Free", enrollment status. Filters: mode, language, price (free/paid), category. Search by title. Server-rendered Mustache templates; no SPA.

Course detail page: outcomes, syllabus, teachers, schedule with timezone, length, price, eligibility, prerequisites, access policy, and the right enrol button (free, approval, pay by transfer, full, closed). Data comes from course custom fields (Section 5, 060-courses.php).

Requirements: responsive down to 360 px, keyboard accessible, WCAG 2.1 AA contrast, RTL tested with Arabic and Urdu, no external CDN dependencies, Mustache templates overridable.

Moodle App: add db/mobile.php only if catalog pages need app support; otherwise the app uses standard course pages. Test login, courses and BBB join in the free Moodle App.

### 9. Third-party plugins and configuration

Pin every version in versions.lock. Before adding any plugin, check that it lists Moodle 5.3 support; if not, test it on dev and record the result in the lock file comment.

| Plugin | Use | Configuration notes |
| --- | --- | --- |
| mod_attendance | Attendance register | Status set from 090-attendance.php; one attendance activity per course; sessions created per scheduled class |
| tool_certificate | Templates, QR, public verification, revocation | Create one site-level Hikmah template; never change the course category structure without a backup (a reported issue deleted templates and revoked certificates) |
| mod_coursecertificate | Auto-issue on completion | Add to each course; access restriction = course completion; check Urdu/Arabic names render with the embedded fonts |
| enrol_apply (or equivalent approval plugin) | Free courses needing approval | Notifications to managers; confirm 5.3 support or replace |
| enrol_fee (core) | Paid courses | Payment account linked to paygw_hikmahtransfer |
| mod_bigbluebuttonbn (core) | Live classes | Free tier on dev/staging; managed or self-hosted server URL for prod via env |
| auth_oauth2 (core) | Google sign-in | Google Cloud OAuth client with redirect https://<host>/admin/oauth2callback.php for each environment |
| antivirus_clamav (core) | Upload scanning | TCP mode to clamav:3310; action on error = refuse upload |

Completion rules per course (decided 2026-11-20): every activity with completion tracking + the course's minimum course grade + minimum attendance (register pass grade), each set per course (local_hikmahcompletion, "Certificate rules"); item weights in Gradebook setup. The certificate activity is restricted to those rules (local_hikmahcompletion writes the restriction and the course completion criteria).

### 10. Milestones

Build in this order. Do not start a milestone until the previous one's checks pass. Tick items as they land.

#### M1 — Portable stack (dev) · about 1–2 weeks

- ☐ Repo skeleton (Section 3), .env.example, versions.lock with Moodle 5.3 tag
- ☐ Debian PHP 8.4 FPM image, Nginx with /public root + Router, PostgreSQL 17, Redis, ClamAV, cron container, Mailpit
- ☐ install.sh, configure.sh skeleton, backup.sh, restore.sh, smoke-test.sh
- ☐ Migrate data from the current local prototype (or reinstall clean if test data only)

Check: Environment page all green including Router; CSS and dashboard routes work; backup.sh → wipe volumes → restore.sh gives the same site.

#### M2 — Staging on Oracle · about 3–5 days

- ☐ Oracle A1 instance (2 OCPU / 12 GB), Docker, firewall 80/443, domain staging.<domain>, TLS
- ☐ Multi-arch images from CI; deploy with compose.server.yml
- ☐ Daily backup.sh to off-host storage (object storage or another machine)

Check: public HTTPS works; destroy and rebuild staging from the repo in under an hour; restore from backup works.

#### M3 — Core configuration · about 1–2 weeks

- ☐ Settings scripts 010–130 (Section 5), Google OAuth for staging, profile fields, roles, course custom fields
- ☐ Third-party plugins installed and configured (Section 9)
- ☐ Free BBB tier connected; meeting events on
- ☐ Certificate template with QR and public verification
- ☐ theme_hikmah pilot step (branding only)

Check: PRD 14.1 tests for registration, Google sign-in, free and approval enrollment, quizzes, completion, certificates and BBB join pass on staging.

#### M4 — Payments · about 3–4 weeks

- ☐ paygw_hikmahtransfer (Section 6) with tests

Check: submit → approve → access; reject → resubmit; double approve does nothing; non-reviewer denied; infected file rejected; receipt URL not public.

#### M5 — Attendance · about 2–3 weeks

- ☐ local_hikmahattendance (Section 7), preview and apply UI, exceptions queue, report, fixtures

Check: on staging, three real classes compared with the instructor's register; differences explained; autowrite still off.

#### M6 — Pilot · 2–4 weeks of use

- ☐ One instructor + a representative student group run real classes on staging
- ☐ Moodle App tested on Android and iOS
- ☐ Fix list triaged; no critical blockers

#### M7 — Catalog and polish · about 2–3 weeks

- ☐ theme_hikmah catalog and course detail pages (Section 8), RTL and accessibility checks

#### M8 — Production readiness · about 1 week

- ☐ Load test (Section 11), security review, restore drill, runbooks complete, admin and reviewer training
- ☐ Decide BBB route for production (free tier, managed, or self-hosted)

Check: PRD 14.2 go/no-go criteria all met. Then run the go-live runbook (Section 12).

### 11. Testing and quality gates

| Gate | Tool | When | Pass condition |
| --- | --- | --- | --- |
| Code checks | moodle-plugin-ci (phplint, phpcs, phpdoc, savepoints, mustache, grunt) | Every push | Zero errors |
| Unit tests | PHPUnit | Every push | All pass; business logic in payments and attendance covered |
| Journey tests | Behat (headless browser) | Every merge to main | All P0 journeys in PRD 14.1 that exist so far pass |
| Environment | admin/cli/checks.php + Environment page | Every deploy | No errors |
| Smoke test | scripts/smoke-test.sh (login page 200, a course page, a static asset, cron last run < 5 min) | After every deploy | All pass |
| Restore drill | backup.sh → fresh server → restore.sh | Monthly and before go-live | Site identical, users can log in |
| Load test | k6 or JMeter against staging: 50 then 200 virtual users browsing, quiz attempts, file downloads | M8 | Pages under 2 s at 50 users; no errors; note the ceiling |
| Security | Moodle security overview report, OWASP ZAP baseline scan, secrets scan (gitleaks) | M8 and before each release | No high findings |
| Accessibility | axe browser extension + keyboard-only walkthrough, in English and Arabic/Urdu | M7, M8 | No serious issues on P0 pages |

Claude reports each task as: what changed, files touched, tests run with results, anything not verified.

### 12. Runbooks

Claude writes each of these as a file in docs/runbooks/ and keeps it working. Summaries:

#### 12.1 Staging on Oracle

- Create an Ampere A1 instance (2 OCPU / 12 GB, Ubuntu, 100 GB boot volume) in the home region; add your SSH key.
- Open TCP 80/443 in the VCN security list and in the host firewall (ufw or iptables).
- Install Docker Engine + Compose plugin; create user hikmah; clone the repo.
- Copy .env.staging to the server; point staging.<domain> DNS to the public IP.
- docker compose -f compose/compose.base.yml -f compose/compose.server.yml up -d, then scripts/install.sh (first time) or scripts/restore.sh (from backup), then scripts/configure.sh.
- Run scripts/smoke-test.sh; set up the daily backup cron to off-host storage.

#### 12.2 Go-live on the VPS

- Buy the VPS (x86, at least 4 vCPU / 8 GB / 160 GB NVMe, Ubuntu LTS, region close to Pakistan users with good routing).
- Repeat 12.1 steps 2–4 with .env.prod and the production domain (set DNS TTL to 300 a day before).
- Put staging in maintenance mode; run backup.sh on staging (if launching with pilot data) or do a clean install.
- restore.sh on the VPS, then configure.sh, then admin/cli/upgrade.php to be safe; purge caches.
- Switch production settings: real SMTP, production Google OAuth redirect, production BBB server, payment account details, email allow-list removed.
- Point DNS; issue TLS; run smoke-test.sh and the go/no-go checklist (PRD 14.2).
- Keep staging as staging (re-point it to staging.<domain> with anonymized data).

#### 12.3 Backup and restore

- backup.sh: enable maintenance mode (optional for nightly), pg_dump -Fc, tar of moodledata (excluding cache, localcache, sessions, temp, trashdir), checksum file, upload to off-host storage, keep 7 daily + 4 weekly + 6 monthly.
- restore.sh <archive>: stop moodle and cron, restore database and moodledata, fix ownership, purge caches, start services, run smoke test.

#### 12.4 Upgrades

Minor Moodle releases (5.3.x) every two months: bump versions.lock on a branch → CI → deploy to staging → run Behat + smoke → backup prod → deploy prod in a maintenance window. Stay on 5.3 LTS until the next LTS is out and all plugins support it.

### 13. Session prompts

Start each Claude Code session with the milestone prompt, from the repo root, so Claude reads CLAUDE.md and docs/PRD.md first. One milestone per session; split big ones by sub-task.

| Milestone | Prompt to give Claude |
| --- | --- |
| M1 | "Read CLAUDE.md and docs/PRD.md. Do milestone M1: create the repo skeleton, Debian PHP 8.4 FPM image, Nginx with the /public root and Router, compose files, and install/backup/restore/smoke scripts. Bring my current local Moodle data across. Stop when the M1 checks pass and report results." |
| M2 | "Do milestone M2. Write docs/runbooks/staging.md for Oracle Cloud Ampere A1, the multi-arch build workflow, and compose.server.yml with TLS. Give me the exact commands to run on the server." |
| M3 | "Do milestone M3. Write config/settings scripts 010–130 exactly as Section 5, install the Section 9 plugins pinned in versions.lock, and the theme pilot step. Run configure.sh twice to prove it is idempotent." |
| M4 | "Build paygw_hikmahtransfer exactly per Section 6, with PHPUnit and Behat tests. Run moodle-plugin-ci and report." |
| M5 | "Rework the attendance prototype into local_hikmahattendance per Section 7. Start with the payload parser and fixtures, then decisions, then the preview/apply UI. Autowrite stays off." |
| M7 | "Build the theme_hikmah catalog and course detail pages per Section 8, then run accessibility and RTL checks." |
| M8 | "Run the Section 11 gates for production readiness: load test, security scan, restore drill. Then complete the go-live runbook and the PRD 14.2 checklist." |

If Claude hits something not covered here, it should propose an option, note the trade-off, and wait for approval rather than improvise.
