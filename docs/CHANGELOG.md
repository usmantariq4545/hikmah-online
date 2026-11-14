# Changelog

## 2026-11-14 — Speed and UI review

### Faster

- **Developer debugging is now opt-in on dev** (`MOODLE_DEBUG=developer`). It made Moodle rescan
  every plugin folder on each request: about 180 ms for every script, font and image, and a page
  makes around a hundred of them. Scripts, fonts and images now take under 10 ms each, and pages
  about 0.08–0.18 s on the server instead of 0.3–0.5 s.
- PHP: `opcache.revalidate_freq` 0 → 2, longer realpath cache, more spare PHP-FPM workers.
- Nginx: `open_file_cache`, `gzip_vary`, `gzip_proxied any`, no re-compressing of woff2.
- Scroll reveals no longer wait for Moodle's script bundle: a small inline script in the page head
  shows content as it scrolls into view. Before, sections stayed blank until all scripts had loaded.

### UI fixes

- Page titles no longer read "… | Hikmah | Hikmah" (Moodle already adds the site name).
- Course detail pages highlight "Courses" in the top menu instead of "Home".
- Dashboard: progress rings show "0%" instead of "0"; on phones the decorative tiles no longer sit
  on top of the date and greeting.

### Fixed in this pass

- A static-file Nginx rule I added stole Moodle's font and image addresses, so fonts and icons
  failed to load; removed before finishing.

### Verified on the laptop

- Timings above (curl, 4–5 runs each); page review at 1366 px and 375 px: no sideways scrolling,
  no broken images; `check-pages.sh`, `smoke-test.sh`, `configure.sh` second run 0 changes.

### Not done

- The stylesheet is 1.4 MB (245 KB compressed), mostly Moodle core's Font Awesome, Noto Sans JP and
  VideoJS font rules. Trimming it means overriding core styles; left as is.
- Moodle's policy-acceptance page (shown at sign-up) and the cookie/policy banner still use plain
  Moodle styling; the policies themselves are drafts.
- Real-device testing, RTL, accessibility audit, load test.

## 2026-11-13 — Hikmah look inside the live class (bbbext_hikmah)

Approved by the owner in chat; a fourth Hikmah plugin, not in the original PRD or build guide.

### Added

- **`bbbext_hikmah`** (`plugins/mod/bigbluebuttonbn/extension/hikmah`), a BigBlueButton extension
  subplugin using Moodle's `action_url_addons` extension point (no core or BBB module change). It
  only adds parameters to the calls Moodle already sends to the BBB server:
  - join: Hikmah colours (`userdata-bbb_custom_style_url` on HTTPS sites, inline
    `userdata-bbb_custom_style` on HTTP sites such as the laptop), `userdata-bbb_client_title`,
    and `userdata-bbb_display_branding_area` on HTTPS;
  - create: `logo` and `darklogo` from the theme, on HTTPS sites only.
- Stylesheet `client/classroom.css`: sets the web client's colour variables only, no generated
  class names. Setting `bbbext_hikmah | brandclient` from `HIKMAH_BBB_BRANDING` (default 1) in
  `config/settings/080-bbb.php`. Wired into the image, dev mounts, CI matrix and README.

### Fixed

- Classroom page: the "can this class be joined" check read a cached meeting status and could turn
  people away from a running class. It now asks the BBB server, and lets the join decide if the
  server cannot be reached.

### Found

- **Why learners still left the portal:** the BBB host in use (BiggerBlueButton) puts its own
  "Join meeting" screen in front of every class; its "Join from browser" button opens a new
  window. Only the account owner can switch it off (BiggerBlueButton settings, "mobile" section).
  Until then the class cannot load inside the frame.
- The BBB server URL and secret on this laptop were entered in the admin screen, not in `.env.dev`
  (both are empty there), so a rebuilt site would fall back to Moodle's free tier. Add them to
  `.env.dev` to make it repeatable.

### Verified on the laptop

- PHPUnit `branding_test` (4 tests, 24 assertions) passes; plugin installs through the upgrade;
  `configure.sh` second run 0 changes; `scripts/dev/check-pages.sh` passes.
- A real join link from the dev site carries `userdata-bbb_client_title` and a 601-character
  inline `userdata-bbb_custom_style`.

### Not verified

- Whether BiggerBlueButton honours the colours, title and logo: the class could not be opened in
  the frame while the provider's join screen is on. The colour variable names come from the
  BigBlueButton web client and were not checked against this provider's version.
- The HTTPS path (stylesheet address and logo), moodle-plugin-ci, Behat.

## 2026-11-13 — In-portal classroom (live classes and recordings stay inside Hikmah)

Approved by the owner in chat; not in the original PRD or build guide (see PRD section 15).

### Added

- **Classroom page** `theme/hikmah/classroom.php` with its own `classroom` layout: a slim Hikmah
  bar (class, course, Open in a new tab, Full screen, Leave) and the BigBlueButton room in a frame
  that fills the window. The frame loads the BBB activity's own `bbb_view.php` join or play
  address, so access checks, the Join log and the recording-played event are unchanged, and
  attendance reconciliation is unaffected. The BBB server stays on its own host.
- **Link rerouting** (`theme_hikmah/classroom` AMD module): on BBB activity pages, "Join session"
  and recording playback open the classroom page instead of a new window. Ctrl/middle-click keeps
  the standard new-window behaviour. No core or BBB module file is changed.
- **States:** joining, live, left the class (rejoin or back to course), opened in another tab,
  could not open (retry or new tab). A leave-page prompt guards a running class against a stray
  Back or reload.
- **Setting** `theme_hikmah | embedclassroom` from `HIKMAH_EMBED_CLASSROOM` (default 1) in
  `config/settings/120-theme.php`; set 0 to return to the standard new-window join.

### Fixed

- `theme_hikmah\hook_callbacks::is_active()` used `isset()` on the page's magic `theme` property,
  which is always false, so the motion script and the staging banner were not being added to any
  page. Now reads the property directly.
- Demo seed: live classrooms had an empty participant list with "wait for moderator" on, so no one
  could start a class. Teachers and assistant teachers are now moderators (existing demo rows
  updated on this laptop).

### Verified on the laptop

- Teacher clicks "Join session": no new window; the classroom page opens and the BBB host's page
  renders inside the frame (desktop and 375 px wide).
- Student opening the classroom before a moderator has joined is sent back to the activity page
  with a notice.
- Left-the-class, another-tab and rejoin states; `configure.sh` twice (second run 0 changes);
  `scripts/dev/check-pages.sh` passes.
- The BBB host in `.env.dev` sends no `X-Frame-Options` or `frame-ancestors` header on its client
  page, and its cookies are `SameSite=None; Secure`.

### Not verified

- A full class with audio, camera and screen share inside the frame: browsers only allow these in
  a framed page on HTTPS, so this needs staging. The BBB host also shows its own "Join meeting"
  choice screen first, which was not clicked through (it accepts that provider's terms).
- A student joining a running class, recording playback in the frame (no recordings on dev),
  Safari and browsers that block third-party cookies, phones, RTL, moodle-plugin-ci, Behat.

## 2026-11-13 — First full build on the laptop (dev)

Built in one pass at the owner's request, covering the laptop parts of M1, M3, M4, M5 and M7.
M2 (Oracle staging), M6 (pilot) and M8 (production readiness) need real servers and people and
are not started.

### Added

- **Stack (M1):** Debian `php:8.4-fpm-bookworm` image with Moodle `v5.3.1`, Nginx with the
  `/public` docroot and Router, PostgreSQL 17, Redis (sessions and application cache), ClamAV,
  cron container, Mailpit; Compose overlays for dev and server (Caddy TLS); resumable source
  download; `install.sh`, `configure.sh`, `upgrade.sh`, `backup.sh`, `restore.sh`, `smoke-test.sh`.
- **Settings as code (M3):** `config/settings/010`–`130` plus `015-cache.php`; roles exported to
  `config/roles/`.
- **paygw_hikmahtransfer (M4):** transfer instructions and reference, receipt upload with type,
  size and antivirus checks, state machine with history and events, reviewer queue, idempotent
  approval through the Payment API, private receipts with download logging, notifications,
  privacy provider, retention and digest tasks, PHPUnit tests, Behat feature.
- **local_hikmahattendance (M5):** versioned payload parser with fixtures, interval arithmetic,
  preview decisions, exceptions queue, overrides with reason, apply through mod_attendance that
  never overwrites a manual mark, per-course autowrite (off), audit table and events, CSV report,
  privacy provider, PHPUnit tests.
- **theme_hikmah (M3 pilot step and M7):** Hikmah colours, mark, bundled Latin/Arabic/Urdu fonts,
  home page, catalog with search and filters, course detail with the right enrol action, FAQ,
  public certificate verification, login layout, dashboard summary, staging banner, motion that
  respects reduced-motion.
- Runbooks (staging, go-live, backup-restore, upgrade), CI and multi-arch build workflows.
- Dev-only demo data (`scripts/dev/seed.sh`) and a per-role page checker
  (`scripts/dev/check-pages.sh`).

### Verified on the laptop

- `configure.sh` run twice: second run reports 0 changes in every file.
- PHPUnit: payments 10 tests, attendance arithmetic and parser 18, attendance engine 11; all pass.
- `check-pages.sh`: every page for anonymous, learner, instructor, finance reviewer and manager
  loads without errors; staff pages refuse learners; no course access before payment approval;
  a receipt opens for the reviewer (200) and not for another learner (404) or a visitor (303).
- In the browser: sign-in, dashboard, pay page, and one payment approved from the review queue
  (learner enrolled once, one payment row). Attendance applied twice through the page: 4 marks
  the first time, none the second.
- `smoke-test.sh` passes, including cron within five minutes.
- Restore drill: `backup.sh --maintenance` then `restore.sh` over the dev site; smoke test and
  page checks pass afterwards and row counts match.

### Not verified

- moodle-plugin-ci (phpcs, phpdoc, mustache lint, grunt) has not been run; only PHP lint and
  PHPUnit were. Expect style findings on the first CI run.
- The Behat feature for payments is written but has not been run. No Behat features exist yet
  for attendance or the catalog.
- No real BigBlueButton class: attendance was exercised with fixture and simulated callback data.
  The BBB free tier still needs an administrator to accept its data processing agreement.
- Google sign-in (no OAuth credentials), real email delivery (Mailpit only; demo addresses are
  invalid on purpose), the Moodle App, RTL and accessibility walkthroughs, phone-width layout,
  load test, security scan.
- ClamAV flagged the EICAR test file when called directly; the refusal message through the
  receipt form itself was not exercised.
- The certificate PDF layout was created by script but not opened and checked by eye; Urdu and
  Arabic names on certificates are untested.
- Staging and go-live runbooks have never been executed.

### Decisions and deviations to review

- **Third-party plugins on 5.3:** `tool_certificate` and `mod_coursecertificate` declare support
  up to 5.2 and `enrol_apply` is from 2023. All three install and their pages load on 5.3.1 in
  dev; none has an upstream 5.3 release yet (PRD 14.2 requires one before production).
- **"Adhoc task on callback event" (guide section 7):** Moodle's BBB module fires no event when
  the analytics callback arrives, so processing relies on the five-minute scheduled task only.
- **User mapping:** the callback's `ext_user_id` is already the Moodle user id, so mapping uses
  it directly and treats BBB time without a Moodle join log as conflicting (Pending review).
- **Unique open request:** XMLDB has no partial indexes, so an `openkey` column with a unique
  index enforces one open request per item and learner.
- **Public home and catalog:** `forcelogin` is off so visitors can browse; course content always
  needs a login and guest access is off.
- **Policies:** the privacy notice and terms created by `130-privacy.php` are marked drafts and
  must be replaced with approved wording.
- **Required profile fields apply to the administrator too:** the first administrator login asks
  for gender, date of birth and phone once.
- **Instructor limits:** `050-roles.php` prevents teachers from changing course settings,
  enrolment, roles and completion overrides. Confirm this matches how Hikmah wants to work.
- **Home page quote:** Qur'an 2:269 with an English rendering, in `lang/en/theme_hikmah.php`
  (`quote_*`); remove or change it there.
- ENR-06 (discounts, refunds), CRS-06 waitlist and CERT-05 bilingual templates are P1 and not built.
