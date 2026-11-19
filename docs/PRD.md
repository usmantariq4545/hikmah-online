## Hikmah Online — PRD v3.1

Nov 12, 2026 · @Muhammad Usman

Version 3.1 keeps Moodle as the platform and changes how it is hosted, packaged and extended. It is self-hosted: built on a laptop, staged on Oracle Cloud Always Free, and moved to a purchased VPS at go-live. Self-hosted LMS for Hikmah Institute. No public hostname is prescribed.

Step-by-step build instructions for Claude: Build guide for Claude

### Changes from v2.0

| Area | v2.0 | v3.0 |
| --- | --- | --- |
| Moodle release | 5.3+ (pinned commit) | 5.3 LTS, pinned to tagged minor releases; stay on 5.3 until the next LTS; skip 6.0 |
| Container base | Alpine 3.22 (musl) | Debian-based PHP 8.4 image (glibc locales and iconv for Arabic/Urdu) |
| Web server | PHP built-in dev server + custom router | Nginx + PHP-FPM, docroot = /public, Moodle Router via try_files to r.php |
| Cache and sessions | Not specified | Redis for sessions and Moodle cache stores (MUC) |
| Upload safety | File validation only | ClamAV antivirus on all uploads, mandatory for payment receipts |
| Live classroom | BBB server unspecified | Dedicated BBB 3.0 host (Ubuntu 22.04) or managed BBB provider; never inside the Moodle Compose stack |
| Attendance | Custom join/leave interval engine from signed callbacks | Custom plugin that reads BBB's post-meeting analytics callback already stored by Moodle's BBB module, then writes to mod_attendance |
| Certificates | Unspecified | tool_certificate + mod_coursecertificate (Moodle Workplace, free) |
| Paid enrollment | Custom bank-transfer workflow | Custom payment-gateway plugin on Moodle's Payment API (paygw_bank as reference); also JazzCash/Easypaisa/Raast manual transfers |
| Free approval enrollment | Unspecified | Approval enrolment plugin (e.g. enrol_apply) |
| Catalog | Moodle catalog | Course custom fields + catalog theme work; separate public site deferred |
| Mobile | Non-goal; Phase 2 "possible" | Moodle App (free) as P1; native apps stay a non-goal |
| Recordings | Access via Moodle | Playback must not be reachable outside Moodle's access check |
| P2 gateway | Unspecified | Safepay (cards + Easypaisa + JazzCash in PKR) via a custom Moodle paygw plugin |

Added in v3.1: self-hosted environments (laptop dev, Oracle staging, VPS production) with portable multi-arch images; Moodle's free BBB tier for dev, staging and the pilot; a low-cost pilot budget; a lean first release (standard theme + manual receipt verification while the payment plugin is built); and a build guide for Claude in the second tab.

### 1. Executive summary

Hikmah Online is Hikmah Institute's learning platform, built on Moodle 5.3 LTS, for live, recorded and hybrid Islamic education courses. It covers the full path from registration and enrollment to live classes, assessment, attendance, completion and verifiable certificates.

Moodle is kept because it already provides the expensive parts: quiz engine, question bank, gradebook, rubrics, completion rules, roles, groups and cohorts. Custom work is limited to three plugins: bank-transfer payments, BBB-to-attendance reconciliation, and the catalog theme. Moodle core is never modified.

The pilot targets about 50 concurrent live learners, scaling to about 200 after a measured load test of Moodle and BBB. If a custom front end is ever needed, it can sit on Moodle's web-service APIs without a migration.

### 2. Goals, non-goals, assumptions

#### 2.1 Product goals

- Learners can discover, enroll in, attend and complete quality courses simply.
- Instructors can deliver live and recorded teaching and evaluate learning.
- Administrators control access, payments, enrollment, reporting and support.
- Attendance reflects real BBB participation, with manual correction and an audit trail.
- Certificates are verifiable and revocable; records are kept with privacy in mind.

#### 2.2 Non-goals for launch

- Replacing Moodle with a custom LMS, or modifying Moodle core.
- Native iOS/Android apps (the free Moodle App is the mobile path, see MOB-01).
- Automated payment gateways, forums or WhatsApp integration.
- Automatic attendance writes for real students before instructor reconciliation and production validation.
- Running BBB on the same host or Compose stack as Moodle.

#### 2.3 Assumptions and decisions

- Access is open to men and women; course eligibility (including gender-specific cohorts) is set with profile fields, cohorts and access restrictions.
- Admins create and assign courses; instructors run their assigned teaching activities only.
- Free enrollment is instant or approval-based. Paid enrollment uses manual transfer verification (bank, JazzCash, Easypaisa, Raast).
- Access duration is lifetime, a fixed period after approval, a cohort window, or a fixed expiry date.
- The attendance threshold starts at 75% and is configurable per course and session.
- Default display timezone is Asia/Karachi; storage is UTC.
- Still to finalize: production hostname, hosting provider, budget, branding assets, final profile fields and data retention policy.

### 3. Users, roles, permissions

Each role is a Moodle role built from capabilities; scoped roles (finance, support) are assigned at system or category level.

| Role | Permissions and boundaries |
| --- | --- |
| Super administrator | Platform configuration, security, plugin management, backups, role design, all reports |
| Administrator / course manager | Create courses and cohorts, assign instructors, manage applications, payments and enrollments, publish certificates, run exports; scoped by permission |
| Instructor | Manage materials, schedule assigned live sessions, run assessments, grade, view attendance, correct attendance with a reason and audit record |
| Student | Register, manage own profile, enroll in eligible courses, attend classes, submit work, view grades and attendance, access recordings and certificates |
| Finance reviewer (optional) | Review payment evidence and approve or reject receipts, with no access to coursework or other learner data |
| Support (optional) | Resolve account and course access issues with limited data visibility and an audit trail |

Decided 2026-11-18: only the super administrator may log in as another user; dashboard "Edit mode"
is for the super administrator only; students see their teachers but not classmate lists or
profiles and cannot message other students — a switch (`HIKMAH_STUDENT_PEERS`, off) turns
classmates back on. Students can always message their teachers and teachers their students.
`scripts/server/role-audit.php` checks all of this on a live site.

### 4. Learner journey

- Discover a course and view eligibility, schedule, fees and access policy.
- Register by email or Google, complete the profile and verify the account.
- Choose free or paid enrollment; paid learners upload a transfer receipt.
- Get instant approval or wait in the review queue.
- Open the course dashboard (web or Moodle App).
- Attend BBB sessions and/or watch recordings.
- Take quizzes, written tasks and group work.
- Review attendance, marks and feedback.
- Meet completion rules and receive a certificate with a unique ID and QR verification.

### 5. Functional requirements

Every requirement names how it is delivered: Core (Moodle out of the box, configured), Plugin (maintained third-party plugin) or Custom (Hikmah-built plugin or theme). Priorities: P0 launch, P1 shortly after, P2 later.

#### 5.1 Identity, registration, profiles

| ID | Requirement | Priority | Delivered by |
| --- | --- | --- | --- |
| AUTH-01 | Email/password registration, email verification, login/logout, password reset | P0 | Core (email-based self-registration) |
| AUTH-02 | Google sign-in via OAuth2/OpenID Connect; account linking to prevent duplicates | P0 | Core (OAuth 2 auth + linked logins) |
| AUTH-03 | One-time detailed profile with mandatory admin-defined fields, consent and validation | P0 | Core (custom profile fields, policies tool) |
| AUTH-04 | RBAC, suspension, password/session protection, admin audit logs | P0 | Core |
| AUTH-05 | Accessible responsive login/onboarding, clear errors, privacy notice | P0 | Core + theme |

#### 5.2 Catalog, structure, access

| ID | Requirement | Priority | Delivered by |
| --- | --- | --- | --- |
| CRS-01 | Course modes: live, self-paced, hybrid | P0 | Core (course custom field "mode") |
| CRS-02 | Catalog cards and detail pages: outcomes, syllabus, teachers, language, dates/timezone, length, price, eligibility, prerequisites, mode, enrollment status | P0 | Custom (catalog theme/block over course custom fields) |
| CRS-03 | Admin create/approve/publish courses; multiple instructors; cohorts and groups | P0 | Core |
| CRS-04 | Sections, lessons, downloads, announcements, progress/completion | P0 | Core |
| CRS-05 | Access policies: lifetime, N days/months after approval, cohort window, fixed expiry; enforced across materials and recordings | P0 | Core (enrolment duration/end date) + recording protection (LIVE-05) |
| CRS-06 | Visibility, capacity/waitlist, prerequisites, enrollment open/close dates | P1 | Core (waitlist via plugin if needed) |

#### 5.3 Enrollment and payments

| ID | Requirement | Priority | Delivered by |
| --- | --- | --- | --- |
| ENR-01 | Free courses: instant enrollment or admin approval | P0 | Core self-enrolment + Plugin (approval enrolment, e.g. enrol_apply) |
| ENR-02 | Paid courses show transfer instructions (bank account, JazzCash, Easypaisa, Raast) and collect transaction reference + receipt image/PDF | P0 | Custom paygw plugin on Moodle Payment API |
| ENR-03 | State machine: Draft → Pending Verification → Approved / Rejected / Resubmission Requested; timestamps, reviewer, remarks, notifications | P0 | Custom paygw plugin |
| ENR-04 | No paid access before approval; no duplicate enrollment or repeated approval side effects | P0 | Custom (idempotent approval) |
| ENR-05 | Receipts: restricted permissions, ClamAV scan, type/size validation, retention limits, download audit, no public URL | P0 | Custom + Core antivirus |
| ENR-06 | Fee, approved discount, payment status, refund/adjustment history | P1 | Custom |
| ENR-07 | Online gateway with automated settlement (Safepay: cards + Easypaisa + JazzCash in PKR) | P2 | Custom paygw plugin |

#### 5.4 Live classroom and recordings

| ID | Requirement | Priority | Delivered by |
| --- | --- | --- | --- |
| LIVE-01 | BBB sessions as Moodle activities with moderator and learner roles | P0 | Core (mod_bigbluebuttonbn) |
| LIVE-02 | Mic/camera, presentation, screen share, chat, polls, whiteboard, moderation | P0 | BBB 3.0 |
| LIVE-03 | Breakout rooms, timed group work, instructor controls | P0 | BBB 3.0 |
| LIVE-04 | Recording lifecycle: capture, process, review, publish/unpublish, show to authorized learners | P0 | Core + BBB |
| LIVE-05 | Recording availability, viewer permissions, retention, playback, download policy; playback must not work via a shared link outside Moodle | P0 | BBB protected-recordings config (to verify) + Core |
| LIVE-06 | Schedule, join window, timezone, missed-session notices, follow-up materials | P1 | Core + notifications |

#### 5.5 Attendance and participation

Source of truth: BBB's post-meeting analytics callback (meta_analytics-callback-url). Moodle's BBB module already receives it, stores per-user events in bigbluebuttonbn_logs and keeps the raw payload. The Hikmah attendance plugin reads that data; it does not run its own webhook receiver or interval engine.

| ID | Requirement | Priority | Delivered by |
| --- | --- | --- | --- |
| ATT-01 | Attendance activity and session records | P0 | Plugin (mod_attendance) |
| ATT-02 | Enable BBB "Register meeting events"; map callback data to course, BBB activity and attendance session | P0 | Core config + Custom (local_hikmahattendance) |
| ATT-03 | Attended time from the callback's per-user durations, clipped to the class window; if the payload lacks detail, fall back to join/leave logs | P0 | Custom |
| ATT-04 | Configurable threshold (start 75%) and status mapping; handle instructors, cancellations, late arrival, missing callback, early end | P0 | Custom |
| ATT-05 | Preview before write; automatic marking only after approval; idempotent; never overwrite manual marks | P0 | Custom |
| ATT-06 | Instructor override with reason, who/when audit, reconciliation report (evidence vs final mark) | P0 | Custom + mod_attendance |
| ATT-07 | Missing-callback queue and retry, dedup, monitoring, retention; payload schema pinned to BBB version with a regression test | P0 | Custom |
| ATT-08 | Optional BBB activity completion by minimum attendance duration | P1 | Core |

#### 5.6 Assessments and grades

| ID | Requirement | Priority | Delivered by |
| --- | --- | --- | --- |
| ASM-01 | Quizzes with question bank, many formats, deadlines, attempt/feedback policies | P0 | Core |
| ASM-02 | Timed exams with open/close windows, duration, grades, audit trail | P0 | Core |
| ASM-03 | Written and file submissions, individual or group | P0 | Core (Assignment) |
| ASM-04 | Rubrics, feedback, resubmission, gradebook, learner-visible progress | P0 | Core |
| ASM-05 | Completion combines activities, pass marks and attendance | P0 | Core + attendance plugin |
| ASM-06 | Academic integrity, identity checks, misconduct escalation | P1 | Core + process |

#### 5.7 Certificates

| ID | Requirement | Priority | Delivered by |
| --- | --- | --- | --- |
| CERT-01 | Auto-issue branded PDF when completion criteria are met | P0 | Plugin (mod_coursecertificate) |
| CERT-02 | Learner name, course, issue date, unique ID, QR | P0 | Plugin (tool_certificate) |
| CERT-03 | Public verification without login, minimal fields only | P0 | Plugin (tool_certificate) |
| CERT-04 | Revoke/reissue; verification shows revoked or invalid | P0 | Plugin (tool_certificate) |
| CERT-05 | Template variants and bilingual (English + Urdu/Arabic) layouts; RTL and Nastaliq rendering tested | P1 | Plugin + custom fonts |

#### 5.8 Notifications, dashboards, reporting, mobile

| ID | Requirement | Priority | Delivered by |
| --- | --- | --- | --- |
| OPS-01 | Email for signup, enrollment decisions, payment review, upcoming class, deadlines, grading, certificates | P0 | Core messaging + transactional email provider |
| OPS-02 | Student dashboard: courses, progress, meetings, attendance, grades, recordings, certificates | P0 | Core + theme |
| OPS-03 | Instructor dashboard: courses, attendance exceptions, submissions, progress | P0 | Core + Custom report |
| OPS-04 | Admin views and CSV exports (enrollments, payments, participation, attendance, completion, grades); access-limited and timestamped | P0 | Core Report builder + Custom |
| OPS-05 | Logs for approvals, overrides, access changes and sensitive actions; documented support workflows | P0 | Core logs + Custom events |
| MOB-01 | Learners use the free Moodle App for courses, notifications and BBB joining; tested on Android and iOS | P1 | Core (Moodle App) |
| OPS-06 | Forums and moderated community | P2 | Core |
| OPS-07 | WhatsApp notifications/community, with consent and API policy compliance | P2 | Custom message output plugin |

### 6. Business rules

- Every enrollment has a course, learner, start date and access-end policy. Access checks apply the same way to live sessions, recordings and assessments.
- Only an authorized reviewer can approve a paid application, and the approval is recorded. A submitted receipt is not proof of payment until verified.
- A course can have several instructors, but only authorized staff can change enrollment, payment, completion or published marks.
- Attendance % = verified attended seconds in the class window ÷ eligible class-window seconds × 100, with stated rounding rules.
- A BBB join alone is not proof of attendance duration. Incomplete or conflicting evidence stays Pending Review and is never auto-marked Present.
- Attendance processing is replay-safe: a repeated or retried callback never duplicates or overwrites a mark.
- Certificates are issued only after approved completion criteria are met; revocation shows on verification.
- Times are stored in UTC and shown in the user's timezone (default Asia/Karachi).
- Receipt files are never served from a public URL and are deleted at the end of the retention period.

### 7. Information architecture

| Area | Key screens |
| --- | --- |
| Public | Home, catalog with search and filters, course detail, FAQ, policies, login/registration |
| Student | Dashboard, course page, lessons and recordings, live class launch, assessments, progress, attendance, grades, certificates, profile, enrollment/payment status |
| Instructor | Assigned courses, content, live session schedule, roster, attendance reconciliation preview, gradebook, activity settings, announcements |
| Administrator | Course builder, users and roles, payment review queue, enrollment manager, attendance reconciliation, certificate manager, reports, audit logs, support tools |
| Certificate verifier | Public lookup by ID or QR, showing validity and minimal fields |

### 8. Technical stack and production architecture

Production runs Moodle 5.3 LTS on Debian-based containers behind Nginx + PHP-FPM, with PostgreSQL, Redis and a separate BBB host. The laptop/WSL2 setup stays for development only.

production architecture · Moodle host, BBB host, email

Learners reach Moodle only through Nginx. BBB sits on its own server: Moodle calls its API to start and join classes, and BBB posts attendance data back after each class.

#### 8.1 Target stack

| Layer | Choice | Notes |
| --- | --- | --- |
| LMS | Moodle 5.3 LTS, tagged minor releases | Security support to Oct 2029. Upgrade only within 5.3.x until the next LTS. |
| Runtime | PHP 8.4 (FPM) on a Debian-based image | Replaces Alpine: musl lacks full locale support and iconv //TRANSLIT, which risks Arabic/Urdu text and PDFs |
| Web server | Nginx + PHP-FPM | Docroot = moodle/public; try_files $uri /r.php for the Router; X-Accel-Redirect for file serving; TLS termination here or at a load balancer |
| Database | PostgreSQL 17 | Private network only; daily backups + point-in-time recovery; tested restore |
| Cache and sessions | Redis 7 | Moodle session handler + MUC application cache |
| Background jobs | Moodle cron every minute (separate container) + adhoc task runners | Runs notifications, enrollment automation, attendance reconciliation |
| File storage | moodledata on persistent volume; object storage for backups (and optionally files via tool_objectfs) | Recordings stay on the BBB/recording host |
| Antivirus | ClamAV daemon + Moodle antivirus_clamav | Required for receipt uploads |
| Email | Transactional provider over SMTP (e.g. Amazon SES, Postmark, Brevo) | SPF/DKIM/DMARC on the sending domain; bounce monitoring |
| Live classroom | BBB 3.0 on dedicated Ubuntu 22.04 server with public IP, hostname, TURN; or managed BBB host | Never in the Moodle Compose stack |
| Monitoring | Uptime checks, container health checks, log shipping, alerts on cron, queue, disk, email and BBB callback failures | Tool to pick with hosting |
| Mobile | Moodle App (free) | Mobile web services enabled; branding is a paid option later |
| Dev environment | Docker Desktop + WSL2 (Ubuntu 24.04), same images as production | Same Nginx config as prod; no PHP built-in server |

#### 8.2 Environments and deployment

- Three environments: development → staging → production. Staging mirrors production versions and config.
- Images are built in CI from a pinned Moodle tag plus a pinned plugin list (Section 9); no code edits on servers.
- Secrets come from runtime secrets management, never Git, image layers or exports.
- Every upgrade: back up, apply to staging, run regression tests, then production with smoke tests and a tested rollback.
- Custom code lives only in Hikmah plugins and the theme; Moodle core is never patched. Avoid deprecated APIs so 6.x upgrades stay cheap.

#### 8.3 BBB hosting options

| Option | Fit | Notes |
| --- | --- | --- |
| Managed BBB provider | Best for pilot | Example market price: about US$45/month for 50 concurrent users, about US$169/month for 200 (100 per room max). Confirm analytics callback, recordings, breakout rooms and data location before signing. |
| Self-hosted BBB 3.0 | When volume or data control justifies it | Needs a dedicated server sized for webcams and recording processing, plus someone to run it. Scalelite later for several servers. |

Decision for v3.1: use Moodle's free BBB tier (25 concurrent users, 60-minute sessions, recordings deleted after 7 days) on dev, staging and the pilot. Move to managed BBB when a class exceeds 25 learners, runs over an hour, or needs kept recordings.

#### 8.4 Hosting decision

Hikmah hosts Moodle itself. Vercel, Netlify and similar platforms cannot run Moodle: it needs an always-on PHP process, a persistent moodledata disk, cron every minute and PostgreSQL. Vercel's free plan is also non-commercial only. Shared hosting and a home/office machine are ruled out for production (no static IP, power cuts, no room for BBB).

| Environment | Host | Spec | Purpose |
| --- | --- | --- | --- |
| Development | Developer laptop, Docker Desktop + WSL2 | — | Building with Claude; Cloudflare Tunnel or ngrok for callback tests |
| Staging | Oracle Cloud Always Free, Ampere A1 (ARM) | 2 OCPU / 12 GB (free limit since Aug 2026), up to 200 GB block storage | Public testing and the pilot; rehearses production |
| Production | Purchased VPS (x86) | At least 4 vCPU / 8 GB / 160 GB NVMe | Live platform |

Images are built for amd64 and arm64 so the same build runs on Oracle and the VPS. Production readiness is proven by destroying and rebuilding staging from the repo in under an hour.

#### 8.5 Cost estimate

Moodle and every plugin in this PRD are free. Costs are hosting, live classes and people. Server prices below are estimates; Hetzner raised cloud prices sharply in 2026, so compare providers at purchase time.

| Item | Pilot (staging on Oracle) | Production, ~50 live | Production, ~200 live |
| --- | --- | --- | --- |
| Moodle server | $0 (Oracle Always Free) | $40–100/month | $80–150/month |
| Staging server | — | $0 (keep Oracle) | $0 (keep Oracle) |
| Off-host backups | $0–5/month | $5–15/month | $10–25/month |
| BBB live classes | $0 (free tier) | $0 free tier, or $45–85/month managed | $150–300/month managed |
| Email (Amazon SES, $0.10 per 1,000) | under $1/month | under $5/month | under $5/month |
| Domain + TLS | ~$15/year; TLS free | same | same |
| Total | about $0–10/month | about $50–200/month | about $250–500/month |

Build effort: 15–21 person-weeks for the full scope (about 4–5 months for one developer). A lean first release (standard theme with Hikmah branding, receipts verified manually until the payment plugin ships) takes about 10–12 weeks. After launch, budget 2–4 developer days a month for updates and support.

### 9. Plugin register

Keep this list short: every plugin must have a 5.3-compatible release (or be Hikmah-owned) before production. Check each third-party plugin's 5.3 support before every upgrade.

| Plugin | Type | Purpose | Owner | 5.3 status |
| --- | --- | --- | --- | --- |
| mod_bigbluebuttonbn | Core | Live classes, recordings, meeting-events callback | Moodle HQ | Included |
| mod_attendance | Third-party | Attendance sessions and register | Community | Installed (2026110900); confirm 5.3 listing |
| tool_certificate | Third-party | Certificate templates, QR, public verification, revocation | Moodle Workplace team | Confirm 5.3 release |
| mod_coursecertificate | Third-party | Auto-issue on course completion | Moodle Workplace team | Confirm 5.3 release |
| enrol_apply (or equivalent) | Third-party | Approval-based free enrollment | Community | Confirm 5.3 release |
| antivirus_clamav | Core | Upload scanning | Moodle HQ | Included |
| paygw_hikmahtransfer | Custom | Bank/JazzCash/Easypaisa/Raast receipt workflow (paygw_bank used as reference only; its last confirmed support is Moodle 3.11/4.0) | Hikmah | To build |
| local_hikmahattendance | Custom | Reads BBB meeting-events data, previews and applies attendance | Hikmah | Prototype exists; to rework |
| theme_hikmah (Boost child) | Custom | Branding, catalog cards, course detail page, RTL checks | Hikmah | To build |
| paygw_safepay | Custom (P2) | Online payments in PKR | Hikmah | Later |

Certificate caution: a reported issue showed that changing the course category structure deleted tool_certificate templates and revoked issued certificates. Treat category changes as a controlled change with a backup first.

### 10. Data model (logical)

| Entity | Minimum attributes and controls | Stored in |
| --- | --- | --- |
| User/profile | Moodle user ID, verified email, Google identity link, required profile fields, consent flags | Core |
| Course/cohort | Course ID, mode, instructors, start/end, access policy, eligibility, price, capacity, completion conditions | Core + course custom fields |
| Enrollment application | Learner, course, state, reviewer, timestamps, access activation | Core enrolment + approval plugin |
| Payment submission | Reference, amount/currency, transfer method, protected receipt file, scan result, status history, reviewer and reason; no public URL | paygw_hikmahtransfer tables + core payments |
| Live session | Course/activity/attendance session IDs, BBB meeting ID, scheduled window, recording policy | Core BBB + mod_attendance |
| Participation evidence | Callback record (raw payload), per-user join/leave and duration, BBB user → Moodle user mapping, received time | Core bigbluebuttonbn_logs |
| Attendance decision | Verified duration, denominator, threshold, computed status, evidence link, preview/approved/applied state, override history | local_hikmahattendance tables |
| Assessment/grade | Activity, submission, rubric, grade, feedback, timestamps | Core |
| Certificate | Unique code, course, learner, issue/revocation status, verification URL, audit events | tool_certificate |

### 11. Security, privacy, accessibility, reliability

- Capability checks on every operation: reports, exports, receipts, recordings and certificates. Public verification is separate from learner data.
- Attendance accepts server-side evidence only; the BBB callback is checked for authenticity, and learner-submitted values are rejected.
- All uploads pass ClamAV; receipts are restricted to finance reviewers and every download is logged.
- Recording playback must require Moodle authorization; confirm BBB protected recordings (or an equivalent) before launch.
- TLS everywhere, secure cookies, session expiry, login rate limiting, CSRF protection (core), patched dependencies.
- Retention and deletion policies for receipts, recordings and attendance events; Moodle privacy API for data requests.
- WCAG 2.1 AA practices: semantic headings, keyboard access, labels, contrast, mobile usability, RTL layout for Arabic/Urdu.
- Daily automated backups (database + moodledata) stored off-host; an evidenced full restore drill before launch; agreed RPO/RTO.
- Monitoring covers uptime, database storage, cron, adhoc task failures, email delivery and BBB callback arrival.
- Legal/privacy terms and consent suited to Pakistan (and other learners' jurisdictions); final compliance review pending.

### 12. Capacity and non-functional targets

| Category | Acceptance criterion |
| --- | --- |
| Concurrency | Pilot: about 50 concurrent live learners. Scale to about 200 only after a measured load test of Moodle (with Redis) and BBB (webcams + recording on). |
| Performance | Common pages usable on typical Pakistani mobile broadband; baseline page timings in staging, alert on regression |
| Availability | Uptime SLA agreed; maintenance windows and incident escalation documented |
| Data integrity | No unapproved paid unlock; no duplicate auto-attendance writes; audit trail for every reviewer action |
| Recovery | Automated off-host backups and an evidenced full restore before launch |
| Security | No exposed secrets, test accounts or public receipts; vulnerability review before launch |
| Accessibility | Keyboard and mobile flows verified for registration, enrollment, live class and assessments, in English and an RTL language |

### 13. Delivery phases

| Phase | Deliverables | Where | Effort | Exit gate |
| --- | --- | --- | --- | --- |
| 0. Foundation | Moodle 5.3, PostgreSQL, Docker, BBB demo, roles | Laptop | Done | — |
| 1. Portable stack (M1) | Debian image, Nginx + PHP-FPM with /public root and Router, Redis, ClamAV, cron container, backup/restore scripts | Laptop | 1–2 weeks | Environment checks green; backup → wipe → restore works |
| 2. Staging (M2) | Oracle A1 instance, domain, TLS, multi-arch images, off-host backups | Oracle | 3–5 days | Staging rebuilt from repo in under an hour |
| 3. Core configuration (M3) | Settings as code, Google auth, profile fields, roles, enrolment, certificates, free BBB tier, theme branding | Laptop → Oracle | 1–2 weeks | Registration, enrollment, quiz, completion, certificate and BBB tests pass on staging |
| 4. Payments (M4) | paygw_hikmahtransfer | Laptop → Oracle | 3–4 weeks | Payment tests in 14.1 pass |
| 5. Attendance (M5) | local_hikmahattendance on meeting-events data | Laptop → Oracle | 2–3 weeks | Preview matches instructor register on three real classes |
| 6. Pilot (M6) | Real classes with one instructor and a student group; Moodle App test | Oracle | 2–4 weeks of use | No critical blockers |
| 7. Catalog (M7) | Catalog and course detail pages, RTL and accessibility | Laptop → Oracle | 2–3 weeks | Accessibility checks pass |
| 8. Production readiness (M8) | Load test, security review, restore drill, runbooks, training; buy VPS; go-live runbook | VPS | 1 week | 14.2 go/no-go met |
| 9. Phase 2 | Forums, WhatsApp, Safepay gateway, analytics, managed BBB as classes grow | VPS | Later | — |

Lean option: launch the pilot after phase 3 with receipts checked by hand and the standard theme, running phases 4, 5 and 7 during the pilot.

### 14. Verification and release gates

#### 14.1 Required end-to-end tests

- Email registration, Google sign-in, duplicate account linking; incomplete profile blocked.
- Free instant and approval enrollment; paid receipt approval, rejection and resubmission; unauthorized review denied; infected or invalid file rejected.
- Access expiry enforced for course, resources, assessments and recording links, including a recording link opened outside Moodle.
- Instructor permissions; multiple instructors; student cannot edit grades or payments.
- BBB join/rejoin, late arrival, cancellation, early end, missing callback, repeated callback, callback after a BBB upgrade (schema regression).
- Attendance preview vs instructor register for a real class; corrections audited; no writes over manual marks.
- Quizzes, timed exam, written/file group submissions, feedback, completion rules.
- Recording permissions, publishing, retention and playback on expected networks.
- Certificate issue, QR verification, unknown ID, revocation, minimal public data; Urdu/Arabic name rendering.
- Moodle App login, course access and BBB join on Android and iOS.
- Accessibility, email delivery, load test, backup restore and restart recovery.

#### 14.2 MVP go/no-go

- All P0 journeys pass staging acceptance with owner sign-off.
- Payment approvals and enrollments have complete audit trails.
- Attendance reconciliation validated on real classes; preview-only mode is acceptable for the first pilot.
- Production runs on Nginx + PHP-FPM with HTTPS, secure email, monitoring, scheduled backups and a demonstrated restore.
- Every plugin in Section 9 has a 5.3-compatible release.
- At least one instructor and a representative student group complete a real pilot with no critical blockers.
- Documentation exists for course creation, payment review, attendance correction, incident response and learner support.

### 15. Open decisions and risks

| Decision / risk | Action required |
| --- | --- |
| Hosting and monthly budget | Decided: self-hosted (laptop dev, Oracle staging, VPS production). Still open: VPS provider and purchase date, production BBB route, operational owner |
| Domain and routing | Approve the HTTPS hostname; test BBB → Moodle callback delivery through firewalls end to end |
| BBB analytics callback | The payload schema is not formally documented and can change between BBB versions. Pin the BBB version, keep raw payloads, and run the schema regression test on every BBB upgrade |
| In-portal classroom (added 2026-11-13) | Decided: live classes and recordings open inside a Hikmah page (framed BBB client; BBB still on its own host), with an "Open in a new tab" fallback and an off switch (HIKMAH_EMBED_CLASSROOM). Still open: test audio, camera and screen share on HTTPS staging, and on Safari; for production prefer a BBB host on a subdomain of the Hikmah domain so third-party cookie blocking cannot break the framed class; the Moodle App keeps its own join. A fourth Hikmah plugin, bbbext_hikmah, asks the BBB server for Hikmah colours and logo inside the class. Still open: the current BBB host (BiggerBlueButton) shows its own join screen that opens a new window, to be switched off in that account; confirm the host honours the branding, or use its own branding settings or another host |
| Recording privacy | Confirm the chosen BBB host supports protected recordings or equivalent link protection |
| Plugin compatibility | Confirm 5.3 releases for mod_attendance, tool_certificate, mod_coursecertificate and the approval plugin; replace or fork any without one |
| Custom plugin effort | Three Hikmah plugins plus a theme need PHP/Moodle API skills and a maintainer after launch |
| Enrollment policy | Final profile fields, eligibility, fee/refund/discount rules, payment accounts and reviewer authority |
| Attendance accuracy | Finish register comparison; decide status mapping, rounding, grace periods, exceptions and write authorization |
| Certificates | Approve design, signatory, QR data, revocation policy, issuing authority; test Urdu/Arabic fonts |
| Privacy and retention | Approve retention and access rules for learner data, receipts, recordings and participation events |
| Operations | Platform owner, support hours, escalation, monitoring and an upgrade calendar aligned to Moodle minor releases |

### 16. Ownership and success metrics

Hikmah program leadership owns academic rules and eligibility. The product/admin owner approves workflows. The technical owner runs Moodle, BBB and infrastructure. Instructors own teaching and attendance verification. Finance reviewers own payment decisions within restricted permissions.

| Metric | Definition |
| --- | --- |
| Registration conversion | Verified signups ÷ started registrations |
| Enrollment conversion | Approved enrollments ÷ eligible applications |
| Payment turnaround | Median hours from valid receipt to decision |
| Attendance data quality | Share of BBB sessions with a received meeting-events callback and fully matched users; exceptions per session |
| Course engagement | Activities completed, live participation, recording views |
| Completion rate | Learners meeting all criteria ÷ eligible enrolled learners |
| Certificate integrity | Issued and revoked counts; verification success and invalid lookup rates |
| Platform reliability | Uptime, errors, failed cron/adhoc tasks, backup and restore success, helpdesk resolution time |

#### Appendix A: priorities

P0 = required for launch or safe operation; P1 = shortly after MVP; P2 = later. "Supported by Moodle" does not mean configured, tested or production-ready.

#### Appendix B: verification snapshot (November 2026, local)

Moodle environment checks passed after routing fixes. The OAuth2 endpoint returned HTTP 200. mod_attendance was installed via CLI upgrade. A BBB instructor/student session ran. A fictional preview classified 100/100 seconds as Present and 55/100 as Below threshold at 75%; no real marks were written. CSS and dashboard route issues were fixed locally; they trace to running the PHP built-in server instead of a web server pointed at /public. This is evidence of a working prototype, not production certification.

### Sources

- Moodle release and support dates (endoflife.date)
- Moodle 5.1 upgrade: /public folder
- Moodle Router is compulsory from 5.1
- Moodle Docs: Nginx
- Alpine Wiki: musl locale limits
- BigBlueButton: analytics with Moodle
- Moodle Tracker MDL-82854: raw callback data stored
- BBB meta parameters: analytics callback
- bbb-install (BBB 3.0 on Ubuntu 22.04)
- MynaParrot BBB hosting prices
- paygw_bank plugin
- tool_certificate plugin
- Safepay gateway (ECOSIRE)
- Vercel fair use guidelines (Hobby is non-commercial)
- Oracle Always Free cut to 2 OCPU / 12 GB (2026)
- Moodle free BBB tier limits
- Amazon SES pricing
- Hetzner 2026 price increases
- Managed BBB pricing (bbbhost)
