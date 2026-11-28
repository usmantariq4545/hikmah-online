# Changelog

## 2026-11-28 — Broken-link check of the public site

- Crawled 150 public pages and tested all 177 links on them: every page and link works. The only dead link was the
  footer's "Support our work" (href="#"); removed with its string at the owner's request until a donation page
  exists. The Google button needs a browser session, so a script sees it as 404; it works in a browser once the
  Google redirect address is fixed.

## 2026-11-28 — Live classes open 5 minutes early, no waiting for the teacher

- Owner's request: every enrolled learner can join a live class from 5 minutes before its start, and nobody waits
  for the teacher to start it.
- Moodle lets nobody in before an activity's opening time (core, unchanged), so bbbext_hikmah now stores the opening
  time 5 minutes before the class start the teacher enters (mod_instance_helper), and the activity form shows the
  class start again (mod_form_addons). Hikmah's course page, dashboard and the attendance window add the 5 minutes
  back (setting bbbext_hikmah | earlyopen = 300, helper bbbext_hikmah\local\schedule). Moodle's calendar entry keeps
  the class start. The upgrade to bbbext_hikmah 2026112800 moved every existing class's opening time back once.
- 080-bbb.php: wait for moderator off by default, hidden from the activity form, and turned off on every existing
  class.
- Not changed: Moodle's own activity header and the "Opens" date in some core lists show the room's opening time
  (5 minutes before the start).

## 2026-11-27 — Sign-up form in the usual order

- Owner's request: first and last name side by side, email, password with a live checklist (10+ characters, upper
  case, lower case, a number) and a Weak / Fair / Strong bar, confirm password (checked on submit), "I'm not a
  robot" with no heading, the agreement checkbox, the optional news opt-in, Create account, then "or" and Continue
  with Google below the button (moved there by signup.js; above it without JavaScript).
- No username or "Email (again)" boxes: on submit, after_config fills email2 from the email and the username from
  it (lower case, allowed characters only, "-2" etc. when taken; hook_callbacks::signup_username, tested).
  People sign in with their email address (authloginviaemail).
- Checkbox rows have no id in Moodle's markup, so their styles now match `.fitem:has(> .checkbox)`; the old
  `#fitem_id_hkagree` rule never applied.
- local_hikmahaccess version 2026112702, theme_hikmah 2026112703.

## 2026-11-27 — About us and Contact us pages

- New public pages /about (about.php) and /contact (contact.php), built from the home page v2 blocks and the
  owner's canvas designs. Where the canvas and the live site differ, the live site wins: the site's own header and
  footer, no breadcrumbs.
- About: hero with the rosette, four values, our story beside a "Hikmah, noun" card, mission and vision, the
  teachers row from the home page (co-founders labelled as on the home page), ways to learn, Qur'an 2:269, and a
  call to action.
- Contact: WhatsApp, email (info@hikmahonline.com), Instagram and Facebook cards, Moodle's own "Contact site
  support" form (reCAPTCHA for visitors, sent to supportemail) and five quick answers. 110-messaging.php sets
  supportavailability = 2 so visitors can use the form.
- Footer: "About us" and "Contact us" now open these pages (Contact used to open WhatsApp).
- Teachers: Khizer Khalid and Shahzaib Aijaz show "LUMS · Aalimiyyah Graduate" (home page too); a credential still
  in [brackets] is no longer shown.
- Clean links /about and /contact (rewriter, Nginx, smoke test). Theme version 2026112702.
## 2026-11-27 — Privacy Policy, Terms of Use and Refund Policy live

- The owner approved the texts. They are in config/settings/policies/*.html, and 130-privacy.php publishes them as
  revision 1.0 of "Privacy Policy" (new version of the old draft privacy notice), "Terms of Use" (new version of the
  old draft terms) and "Refund Policy" (new). All three are compulsory, so every user accepts them again at their
  next sign-in. To change a text later: edit the file and raise its revision in 130-privacy.php.
- Defaults filled in for the doc's open items: owner Hikmah Institute; contact hikmahonline1@gmail.com (the site's
  support email); under-18s need a parent or guardian's permission; Pakistani law with no city named; activity logs
  kept 2 years (logstore_standard loglifetime 730, set here); recordings "a limited time"; learning records kept as
  long as the account exists.
- Branded public pages /privacy-policy, /terms-of-use and /refund-policy (theme_hikmah privacy.php, terms.php,
  refund.php, local/policypage.php, policy.mustache, .hk-policy styles), showing the current tool_policy version.
  The site footer, the sign-in page and the sign-up checkbox link to them by these three names ("I agree to the
  Terms of Use, Privacy Policy and Refund Policy"). Clean URL aliases in local_hikmahurls and nginx.
- theme_hikmah and local_hikmahaccess version 2026112701.

## 2026-11-27 — Marketing opt-in on sign-up

- The sign-up form has a second, optional checkbox under "I agree to the Terms of Use and Privacy Policy":
  "Send me news about new courses and offers by email". It is unticked by default and saved to the profile field
  hkconsentcomms, which learners can change in their profile. That field is renamed to the same words
  (040-profile.php now also syncs field names). local_hikmahaccess version 2026112700.
- The terms checkbox and its record were already in place: each new account gets a versioned acceptance of every
  compulsory policy (Site administration > Users > Privacy and policies > User agreements, with download).

## 2026-11-27 — Home page v2 footer on every page

- The site footer is now the v2 footer (templates/footer_site.mustache, class local/footer.php, renderer
  hk_footer()) on every page, the login page included; the live-class page stays without one. Home v2 no longer
  carries its own copy.
- Owner's edits: logo and mission on the left, Learn / About / Help on the right; "Institute" renamed "About";
  "Become a teacher" removed; the "Preview only" newsletter note removed; newsletter text "Get new courses and
  session alerts in your inbox."; rights line ends "Powered by Ottom Solutions"; WhatsApp, Instagram and Facebook
  as icons on the right of that line (inline SVG, labelled, open in a new tab). Theme version 2026112700.

## 2026-11-27 — Clean links: no ".php", courses by name

- New plugin local_hikmahurls ($CFG->urlrewriteclass) prints every Moodle link without ".php":
  /mod/quiz/view?id=9, /my/, /login/. Courses go by name: /course/<name> for the course itself and
  /courses/<name> for its catalog page, made from the course title (table local_hikmahurls_slug, kept up to date
  by course observers). A renamed course keeps its old name, which sends a permanent redirect to the new one.
  The theme pages get short names: /courses (catalog), /faq, /verify, /classroom.
- Nginx maps clean links back to the scripts (location @hkclean). Every ".php" link still works, so old links,
  emails and certificate QR codes are unaffected. Links that other systems call keep ".php": Google sign-in
  callback, BigBlueButton, the Moodle App, web services, AJAX, file and theme asset links.
- Kill switch: CLEAN_URLS=0 in the env file (then recreate the moodle container) prints ".php" links again.
- Links that core JavaScript or templates build as plain text still show ".php" and keep working.
## 2026-11-27 — Home page v2 is the public home page again; previous home kept as an admin draft

- At the owner's request, re-applies "Home page v2 is now the public home page" (undoing the rollback): / shows v2
  for everyone, with the WhatsApp, Instagram and Facebook links, the guide video card leading home, and no app link;
  teacher photos are public again; /theme/hikmah/home2.php stays as an admin preview.
- The previous home page is kept at /theme/hikmah/home1.php for site administrators only (templates/home1.mustache,
  same markup, home::export()). Theme version 2026112605.

## 2026-11-26 — Cloudflare ECH turned off

- The owner's laptop and phone got ERR_CONNECTION_RESET on hikmahonline.com while curl and the in-app browser worked:
  their provider resets Cloudflare's Encrypted Client Hello (outer name cloudflare-ech.com), which browsers use when
  secure DNS or Private DNS is on. ECH turned off for the zone through the API (Free plans have no switch); the HTTPS
  DNS record no longer carries `ech=`, and both devices load the site. Runbook: contabo.md section 11, step 2.

## 2026-11-26 — Home page v2 draft: latest canvas changes

- Announcement bar stays on one line (the course name shortens on phones).
- Hero: no curve at the bottom; the eyebrow stays on one line on phones; on tablet portrait and phones the rosette sits
  behind the text, half off the right edge, without the Arabic word (like the current home page).
- Featured course: yellow "New course" chip with a blue dot, "Foundations of Fiqh" above a larger title with
  "Islamic Ruling" highlighted (new string home2_spot_title_mark).
- Topic carousel: the arrow buttons move from beside the heading to quiet frosted circles on the carousel's edges.
- Teachers: Khizer Khalid, Faaiz Hassan, Shahzaib Aijaz, Jawwad Mehmood, then the others; on wider screens Khizer and
  Shahzaib are the two large cards in the middle. Theme version 2026112604.

## 2026-11-26 — Rolled back: the previous home page is the public home page again

- Undoes "Home page v2 is now the public home page" (PR #26) at the owner's request, without restoring the database:
  / shows the previous home page again, v2 is back to the admin-only draft at /theme/hikmah/home2.php, teacher photos
  are admin-only again, and v2's links are back to their earlier placeholders. Theme version 2026112603 (newer, so
  no downgrade).

## 2026-11-26 — Home page v2 is now the public home page

- The front page (/) shows the v2 design for everyone; the old home markup is removed from layout_frontpage.mustache.
  /theme/hikmah/home2.php stays as an admin preview.
- Teacher photos are served publicly by home2_photo.php (still kept in moodledata, not in the repository).
- Links: WhatsApp +92 331 2755374 (FAQ line, footer Contact us and Connect), Instagram and Facebook
  (online.hikmah). YouTube and the mobile app link removed. The guide video card leads to the home page until the
  video is ready. About us, Become a teacher, Support our work and the newsletter box stay as placeholders.
- Theme version 2026112601.

## 2026-11-26 — Home page v2: motion and hero light, matching the current home page

- The hero gets the same soft blue and yellow light behind the rosette; the eyebrow, title, text and buttons rise in
  turn; the rosette is now inline so its tiles assemble one by one and the ring turns slowly, leaning toward the pointer
  like the home page hero; "حكمة" pops in. New template home2_rosette.mustache.
- Section headings, promises, spotlight, enroll card, topics, steps, teachers row, ways, video, FAQs, verse and closing
  banner reveal on scroll through the existing motion module (off under reduced motion).
- Featured course card: lit cover, and on hover it lifts, the cover grows and the rosette turns, like the course cards. Class
  times table has no box or column lines, only soft lines between rows.
- Topic, course and teacher rows glide in one smooth eased motion and land on a card edge; they move every 2.5s
  (courses 2.8s) instead of 3–4s.
- Footer: Hikmah Online logo and the paper rosette in the top corner, as in the site footer.
- Verse band: Arabic in white Noto Naskh with the home page sizes; Moodle's grey blockquote colour and left bar removed.
- Theme version 2026112600.

## 2026-11-25 — Home page v2 draft rebuilt from the design canvas (admins only)

- /theme/hikmah/home2.php now follows the "Hikmah Online homepage v2" Claude Design canvas: announcement bar, hero with the
  logo rosette, promises strip, featured course (fixed copy), live course grid, topic carousel with photos and arrows,
  how it works, teachers with photos, ways to learn, "where to start" guide video, five platform FAQs, verse band,
  closing banner and its own footer with newsletter box (not connected). Still needs moodle/site:config.
- Responsive for widescreen, desktop, tablet and phone (portrait and landscape): fewer courses and a swiping course
  row on phones, compact topic cards, two teachers at a time, promises hidden on phones, notch-safe side margins.
- New: templates/home2_arrow.mustache, amd/src/home2.js (row scrolling; pauses on hover/touch, off for reduced
  motion), pix/home2/ (rosette traced from the logo, topic photos from Unsplash, teacher photos served from moodledata/hikmah-media/teachers by the admin-only home2_photo.php, kept out of the
  public repo). Rewritten home2 strings. Theme version 2026112501.

## 2026-11-25 — Branded email templates

- New email layout (theme_hikmah templates/core/email_html.mustache): ink header with logo, white card, yellow pill
  buttons with inline styles, "Seek knowledge with wisdom" footer. New wording for sign-up, password reset/change,
  staff-created accounts, new sign-in alerts, assignment feedback, certificates and enrol_apply notices
  (config/settings/email-strings.php, installed by 135-strings.php).
- Payment emails (paygw_hikmahtransfer notifier) now show a status badge, an order-style payment summary table with
  total, the reviewer's reason, and a course card. Amounts read "PKR 5,000".
- scripts/dev/email-preview.sh mails a sample of every email from the checkout without changing the site. Owner
  approved the second preview round.


## 2026-11-25 — Email sending through Amazon SES

- Moodle now sends mail (sign-up confirmations, password resets, payment and enrolment notices) through Amazon SES
  in Mumbai from no-reply@hikmahonline.com. Domain verified with Easy DKIM (three CNAMEs in Cloudflare, DNS only);
  SMTP login set with ~/set-smtp.sh on the server (now asks for the server, so it works for SES or Brevo). Steps:
  docs/runbooks/contabo.md section 12.
- Tested: a test email reached a Gmail inbox with SPF, DKIM and DMARC PASS. Not tested yet: a real sign-up
  confirmation and a password reset.

## 2026-11-25 — Router check and HTTP/3

- config.php sets `$CFG->routerconfigured = true` (Nginx already sends unknown paths to /r.php), so the
  Environment page Router check stops reporting an error. Found in an error sweep: no PHP, cron or task errors;
  all 5xx in the last day were during deploys.
- Cloudflare HTTP/3 (QUIC) switched off by the owner: some networks broke it (ERR_QUIC_PROTOCOL_ERROR when
  changing pages). Keep it off (docs/runbooks/contabo.md section 11).


## 2026-11-25 — Only Cloudflare reaches the web ports

- scripts/server/cloudflare-only.sh: ports 80/443 accept only Cloudflare's ranges, so the site can't be reached by
  its IP around Cloudflare. IPv4 in Docker's DOCKER-USER chain (Docker's published ports bypass ufw) with a boot
  service; IPv6 in ufw; SSH untouched; `--off` undoes it. Applied on the live server.
- Tested: login via Cloudflare 200; direct IPv4 and IPv6 to the server time out (200 before); ACME path still reaches
  Caddy; boot service re-run gives no duplicate rules; smoke test passed. Not tested: a full reboot.
- Security check the same day: gitleaks full-history run (141 commits) and a pattern search of all commits found no
  secrets; sensitive paths (.env, .git, config, backups, scripts) not served; TLS 1.0/1.1 refused; security headers
  and Secure/HttpOnly cookies present. Moodle's CLI security report crashed (Class "curl" not found) — use the
  browser report (Site administration → Reports → Security checks).

## 2026-11-25 — Moved to https://hikmahonline.com behind Cloudflare

- The site moved from the temporary online.hikmahinstitute.org.pk to hikmahonline.com (owner's Namecheap domain; DNS
  moved from Hostinger to Cloudflare free). The domain's old Hostinger courses site is replaced (its files at Hostinger
  are untouched); no mailboxes were in use, mail records kept as DNS only. The old Moodle address is switched off.
- Cloudflare: proxied, Full (strict), Always Use HTTPS, www → root redirect (301), cache rule for Moodle's theme/JS
  URLs. Caddy keeps its own Let's Encrypt certificate (HTTP-01 reaches Caddy through the proxy).
- Server: `.env.prod` MOODLE_WWWROOT/SITE_HOST changed (backup copy `~/env.prod.bak-20261125`), backup
  20261125-105821 taken first, `tool_replace` rewrote old links in the database, caches purged. reCAPTCHA v2 on
  sign-up turned on with keys for the new domain (030-auth). Steps: docs/runbooks/contabo.md section 11.
- Verified: login page 200 through Cloudflare with "Log in to Hikmah Online" and the Google button, www 301, old host
  no longer answers, smoke test passed (twice), CSS and JS cached by Cloudflare (MISS then HIT), reCAPTCHA on the
  sign-up page. Owner confirmed password and Google sign-in on the new address. Some providers' DNS still showed the
  old Hostinger site for a while after the nameserver change (Chrome secure DNS or mobile data showed the new site).

## 2026-11-25 — Off-host backups follow the same retention

- scripts/backup.sh now applies the 7 daily / 4 weekly / 6 monthly rule to the R2 copy too; before, R2 kept every set
  forever. Remote pruning runs only when the listing succeeds and contains the new set. Tested with the real R2 set
  list and 8 months of simulated nightly sets (keeps 16). Server: Docker build cache cleared on 2026-11-24 (disk 76% → 16%).

## 2026-11-24 — Home page v2 draft: visual fixes

- Quote cards and the verse band no longer show Moodle's grey blockquote bar; the free taster band drops the corner
  pattern that sat behind its small print. Draft page only; SCSS and template, no version bump.

## 2026-11-24 — Home page v2 draft (admins only)

- theme_hikmah: new draft page /theme/hikmah/home2.php built from the "Homepage Copy" doc: announcement bar, hero,
  four promises, topic tiles, featured course spotlight (or a row of cards once there is more than one course), how it
  works, teachers, learning formats, sample student quotes (marked as samples), free taster, FAQ, verse and closing call
  to action. Courses and teachers come from the live catalog. Reuses the home page blocks; new styles in
  scss/hikmah/_home2.scss. Needs moodle/site:config, so only site admins can open it. The front page is unchanged.
- Not in the draft: the four-column footer and newsletter from the doc (the site footer is shared by every page).

## 2026-11-24 — One enrollment per learner per course

- Payment: a learner who already has the course (any enrollment method) is not offered the pay page, cannot
  submit a transfer, and a reviewer cannot approve one (the approval is refused with a message, so they reject
  it instead). This was how demo.student1 got enrolled twice in Foundations of Tajweed (manual, then fee).
- Staff enrolling someone by hand who already has active access another way: the new manual enrollment is
  removed at once (local_hikmahaccess observer). Cohort sync is left alone.
- Users page (teacher view): a learner enrolled two ways shows once, with the first enrollment.
- PHPUnit: request_test (enrolled learner cannot submit; approval refused) and new enrolment_test.
## 2026-11-24 — JavaScript builds on GitHub

- New workflow amd-build.yml: when a branch changes a plugin's amd/src, GitHub rebuilds amd/build with
  Moodle 5.3's grunt, commits it to the branch and starts CI (without Behat) and the secrets scan on that
  commit. ci.yml gains a "Run Behat too" input for manual runs (default on); no check was relaxed.
- scripts/dev/build-amd.sh rebuilds amd/build locally with a portable Node 22 and Moodle's grunt, no Docker.
- Payment Behat: the test payment account has an IBAN so the pay page opens. Payment events use the course
  context; test user names fixed in local_hikmahaccess userlist_test.
- Payment: "a teacher is refused the review queue" moved from Behat (which fails any step that lands on an
  error page) to PHPUnit (test_teacher_denied_review_queue). The other Behat journeys are unchanged.

## 2026-11-24 — graphify code map for Claude sessions

- Claude Code tooling: the graphify skill (project-scoped, .claude/skills/graphify) and its hooks (.claude/settings.json) so the
  main chat, forks and subagents query a local knowledge graph before searching files. graphify-out/ is built locally with
  `graphify update .` and git-ignored; .graphifyignore keeps .env files, backups, Moodle source and session worktrees out.
  No effect on the site or the server.

## 2026-11-23 — Attendance tables renamed

- Course form on phones: the step bar shows step numbers only (the current one highlighted) and a
  smaller "Preview card" button, so all four steps fit without swiping.
- Server: kernel 6.8.0-151 (TCP fix CVE-2026-98432) and ClamAV 1.5.5 (10 scanner CVEs) applied after a CVE
  review of every component; everything else was already on its fixed version. An @reboot job now starts all
  containers after a reboot (the moodle container stayed down once); documented in runbooks/contabo.md.
- local_hikmahattendance tables renamed from local_hikmahatt_* to local_hikmahattendance_* (link, decision,
  audit, course) so they carry the plugin name, as Moodle requires. The upgrade step renames them in place
  and keeps every row; take a backup before deploying.

## 2026-11-23 — Plugin checks pass

- All Hikmah plugins pass moodle-plugin-ci on Moodle 5.3 again: code style, PHPDoc, valid template HTML
  (account details, history times and the statistics blocks), stylelint on the theme SCSS, rebuilt payment
  JavaScript, a PHPUnit warning and the payment Behat setup (the transfer gateway is enabled first).

## 2026-11-20 — Live classes standard; attendance marked by the platform

- Course form ("Add a new course" / course settings): four steps (Basics, Catalog details, Certificate rules,
  Advanced settings) with a step bar, labels above fields, a save bar that stays on screen and a live
  preview of the catalog card (theme_hikmah/courseform). The two certificate fields moved to their own
  custom-field category "Certificate rules" (060-courses.php; values kept). Presentation only: the same
  fields are saved.

- Course visibility: the course form now offers Hidden (only staff), Public, or Scheduled (hidden until a
  chosen date and time, then public by itself, checked every 5 minutes; local_hikmahaccess, table
  local_hikmahaccess_publish). Scheduled courses show "Scheduled: <time>" on the course management page.
- Version control: each change on its own branch, merged into main through a pull request the owner
  approves, then deployed. A pre-push hook (.githooks/pre-push) refuses pushes straight to main.

- "Users" tab (local_hikmahaccess): administrators, managers and support see every user (view only: Moodle's own
  user list without its edit actions, plus the phone number); teachers see the students of the courses they
  teach (name, email, phone, course, enrolled on, last access). Download for administrators and managers only;
  finance reviewers and learners get no tab. Replaces support's "People" tab. Each view is logged.
- Users page: filters match the columns on screen; administrators and managers get a "Columns" button to show
  any detail (gender, date of birth, guardian, city, country, sign-in method, roles, number of courses, joined,
  first and last access, last IP...), remembered per person. Support keeps name, email, phone and last access.
- Users page: the table scrolls inside a screen-high box, so its sideways scrollbar is always in view; the header
  row stays put and cells stay on one line.
- "Download table data as" rows (every downloadable table): label, format list and Download button centred on one line.
- Users page: a Courses column shows each person's courses as tags (on by default, support included); the Courses filter
  matches a course name.
- Releases: every successful deploy is tagged live-<date>-<commit> on GitHub with the backup taken before it;
  scripts/deploy-live.sh <tag> goes back to an earlier release (docs/runbooks/contabo.md).
- Smoke test: the "cron last run" check waits up to 3 minutes for cron after a long deploy instead of failing.
- Deploys reload PHP-FPM after the upgrade: production PHP kept the old class list, so new code (the Users
  page) answered "class not found" until a reload.
- CI trimmed (GitHub Actions free minutes ran out, 2026-11-23): plugin checks run only when plugins/ or
  versions.lock change, a newer push cancels the older run, Behat runs weekly and on demand, jobs time out
  after 40 min; the secrets scan moved to secrets.yml (every push). local_hikmahcompletion added to the matrix.

- Off-site backups go to Cloudflare R2 instead of Backblaze B2 (owner, 2026-11-23): `~/set-r2.sh` on the
  server writes the rclone remote and BACKUP_REMOTE; runbook contabo.md section 8.

- Monitoring (security review fix 9): the cron container pings HEALTHCHECK_CRON_URL after each successful
  cron run and backup.sh pings HEALTHCHECK_BACKUP_URL when it finishes (both optional; empty = off).
  Uptime, server and alert setup (HetrixTools, Healthchecks.io; alerts to Discord and email) in docs/runbooks/monitoring.md.

- Sign-up page: "Continue with Google" button above the form (and an "or sign up with email" divider),
  shown once Google sign-in has its keys (030-auth.php reads GOOGLE_OAUTH_CLIENT_ID/SECRET).
- Moodle app: signs in through the website (embedded browser), so two-step sign-in applies there too;
  managers, finance reviewers and support get no app access (createmobiletoken prohibited), since the
  app's password endpoint skips two-step sign-in.
- Two-step sign-in (tool_mfa) for site administrators, managers, finance reviewers and support: an
  authenticator-app code, 7 days' grace to set it up; teachers and learners unaffected. Email codes off
  (no outgoing email yet). Failed sign-in alerts (10 in a row) go to the main administrator. Sign-up
  reCAPTCHA comes on by itself once RECAPTCHA_SITE_KEY / RECAPTCHA_SECRET_KEY are set.
- Plugins updated (security review): mod_attendance 2026111201 (group access and self-marking checks),
  tool_certificate and mod_coursecertificate 5.0.12 (now declare Moodle 5.3 support; revoke fix).
- Security review fixes: the admin password goes only to the one-time install command, no longer into the
  running containers; Nginx passes the real visitor address on (Moodle logged every visitor as Caddy's
  172.18.0.9) and limits sign-in, sign-up, password-reset and app-token posts to 20 a minute per visitor
  (bursts of 30; 429 after); Content-Security-Policy (object-src none, base-uri and frame-ancestors self,
  upgrade-insecure-requests) and Permissions-Policy (unused device features off) headers.
- Spelling: "enroll" everywhere people read it (Enroll, Enrollment, Unenroll), in Hikmah's own wording and,
  through the en_local language pack (135-strings.php), in Moodle's and its plugins' wording (338 strings).
  Code names and addresses (enrol_fee, /enrol/) are unchanged.
- Course list (left drawer): a lock beside a class that has locked items; a held live class reads
  "Class recording" there too. Attendance page: "Not marked yet" instead of "?" (and "–" for its points).
  Date of birth left empty is now refused by the server as well (local_hikmahaccess). Live class: the main
  buttons are Hikmah blue, no longer near-black on the dark meeting background.
- Course list: on short pages it now ends where the footer starts, so its last classes stay reachable
  (the footer used to cover them).
- Security: Nginx no longer serves Moodle's source, test and package files (/behat/, db/install.xml,
  upgrade.txt, environment.xml and the like; Moodle's "public paths" check). "Open to search engines"
  off: it only let crawlers in as guest, and guest access is off.
- Forms: date of birth and a payment's "date paid" start empty ("Day / Month / Year", required) instead of
  showing today's date; no default country (was Pakistan), so the profile form asks for it.
- Google sign-in no longer asks for an email confirmation (Google has verified the address); a Google
  account joins an existing account with the same email (owner's decision, 030-auth.php).

- Theme: a held class is titled "Class recording" on the course page (not while editing); Moodle's
  "Opens / Closes" under a scheduled live class becomes "Starts: Sat 21 Nov, 6:00 PM · Duration:
  1 hour" ("Started" once held). Student dashboard: attendance % per course and an Attendance tile
  (mod_attendance's own numbers, marked classes only).
- local_hikmahattendance 0.2.0: a live class with an opening and closing time gets its register
  session (an existing one at that time is reused), the link, and automatic marking for its course
  (observer on course module created/updated; `manager::ensure_for_room`; moving a class not yet held
  moves its session). With autowrite on and no BBB report 2 hours after class, learners who joined
  are marked Present and the rest Absent; a report arriving later replaces those marks. `link.php`
  uses the shared `manager::create_session`. Tests: join-log fallback and room set-up.
- `090-attendance.php` runs the same set-up for existing scheduled live classes.
- Demo Tajweed course: rooms close at class end (one hour) instead of 30 minutes after.
- Decision change (owner, 2026-11-20): autowrite no longer ships off for scheduled classes; PRD
  ATT-05, open decisions and CLAUDE.md updated.
- Instructor view: dashboard for people who only teach shows Courses, Students, Classes this week
  and Class attendance, and "Your courses" with students and attendance per course (no learner
  progress rings). Attendance review page explains automatic marking when autowrite is on, names
  each live class by its class section, and uses 12-hour times throughout.
- Demo Tajweed course: class time no longer repeated in the section summaries; the emptied general
  "Live classroom" removed.
- Instructor UI pass: teachers were tagged `hk-learner` (they lack `moodle/course:update` by design),
  so the learner check now uses `moodle/grade:viewall`. Activity-header dates wrap instead of running
  off the page. Footer text kept clear of the "?" button. Recordings table hides the repeated name
  and description columns for everyone. Teacher menus trimmed (050-roles): filters, content bank,
  permissions, LTI tools, AI usage, course reset, attendance status set, temporary users and import.
  "Attendance reconciliation" renamed "Live class attendance"; dashboard tile "Classes needing
  attention".
- Held live class page headed "Class recording" (as on the course page); recording dates reordered to
  "Wednesday 18 November 2026"; forum discussion list shows topic, replies and actions only on phones.
- Staff dashboards: managers and support (no enrolments) see site numbers, the site's courses and its
  upcoming live classes; finance reviewers see payment numbers only. Grading and attendance tiles open
  the course with the work. Payment queue, attendance pages, course management and the progress
  report no longer widen the page on phones (shrinkable grid column, wrapping action bars and selects,
  hidden table labels kept inside the table's scroll box).
- Finance reviewer pass: on narrow screens the receipt sits above the decision buttons; payment dates
  read "19 November 2026, 8:28 PM"; "Payment reviews" is highlighted in the menu on its pages; "My
  courses" is hidden for finance reviewers with no courses. (A typo in that last change broke pages for
  finance reviewers for about 3 minutes; fixed in 2d874b8.)
- Support pass: support may view student and teacher roles (050-roles), so participants pages show
  roles instead of "No roles"; the course Grades tab is hidden from staff who are not on the course and
  cannot see grades (it led to "Cannot view grades."); "My courses" hidden for staff with no courses
  who cannot create one; new "People" menu item opens the site participant list, headed "People",
  without the site-level Roles and Groups columns.
- 060-courses removes Moodle's empty install-time "Category 1" once real categories exist (owner's
  decision); course requests default to the first remaining category. Skipped if it holds anything.
- Sign-up is one short form (owner's review): local_hikmahaccess skips the policy tool's reading and
  consent pages (after_config hook sets its pre-signup flag on /login/signup.php) and adds one required
  "I agree to the Terms of Use and Privacy Policy" checkbox with links; the policy tool still records a
  versioned acceptance of each compulsory policy when the account is created. City, country and the
  Hikmah profile fields are off the form (040-profile: signup = 0); the required ones are asked after
  the first sign-in. Password rules sit under the password box. Required fields are marked "*"
  (theme icon system maps core:req to fa-asterisk). Sign-in/up pages no longer overflow on phones and
  have more room around the form. The live class page shows "Starts … · Duration …" too.
- Course pages in the owner's reference style: the course home opens on the learner's current class
  (next or ongoing live class, else first class with something left to complete) on Moodle's own
  section page, with the class list (course index) on the left; edit mode keeps the full page. Each
  item says what it is and how long ("Quiz • 20 min", "Assignment • Due Sat 21 Nov"); each class page
  has a summary line ("1 live class · 2 readings · 1 quiz"). "General" reads "Course info"
  (135-strings). The footer spans the window over the class list instead of being cut by it.
- New plugin local_hikmahcompletion: certificate rules per course. Certificates were being issued to
  anyone who opened them (no restriction, no completion rules). Each course now sets a minimum course
  grade and a minimum attendance (course fields certgrade/certattendance, or the course's "Certificate
  rules" page for teachers; defaults 50% and 75% in 100-certificates); item weights stay in Gradebook
  setup. rules::sync() sets the register's pass grade and completion, the course completion criteria
  (every tracked activity + course grade) and the certificate's restriction; it re-runs when activities
  or course settings change, and once for every course from 100-certificates. scripts/dev/
  revoke-unearned.php revokes unearned certificates of demo accounts only. PHPUnit written, not run.
  The certificate shows to learners as locked with every requirement listed (a hidden failing
  condition had hidden it entirely).
- Course items: the type label is left out when the name already says it ("Your certificate", "Quiz 1:
  …") and for live classes (their Starts/Duration line says it). Once a learner has submitted an
  assignment, finished a quiz attempt or completed an item, its "Due / Closes" line is removed and the
  label reads "Submitted" or "Attempted".
- Header loads at once: the top menu is server-rendered (theme override of core/primarymoremenu without
  Moodle 5.3's React PrimaryNav, which replaced the menu only after its scripts loaded and measured;
  core/moremenu still handles "More"). Logos shrunk from 900x434 (19 KB) to 360x174 (4 KB / 3.5 KB);
  the header logo is preloaded with high priority and the footer logo is no longer lazy-loaded.
- Profile, grades, My payments and preferences open with the dashboard's banner (eyebrow, title, lead
  and numbers: courses/completed/certificates; graded courses/average; waiting/needs action/approved);
  Moodle's repeated page heading is hidden there and the content sits in the dashboard's white panels.
- Follow-up: the logo resize was reverted (GD's palette conversion dropped the transparency, and the
  header logo showed on a black box for about 4 minutes); the originals stay, with preload and high
  priority. The grades banner read the course id as the user id (showed 0 courses); fixed. The
  repeated avatar heading is hidden (Bootstrap's d-flex is !important). Until core/moremenu has moved
  overflowing items into "More", the menu's second line is clipped (hk-pending) instead of wrapping.
  050-roles: no block editing on one's own profile (moodle/user:manageownblocks prevented), so learners
  no longer get "Edit mode" and "Reset page to default" there.
- Top menu look (owner's choice): exactly Moodle 5.3's own menu look (design-system nav pills through
  its --mds-* variables: Noto Sans 0.875rem, grey selected pill with a 4px blue dot, no underline or
  click box) on the fast server-rendered menu, centred on the bar like the logo and avatar, text 15px.
  Profile, grades, payments and preferences highlight
  Dashboard instead of Home.
- Catalogue cards: the start date reads "Starts 1 Nov" or "Started 1 Nov" (year added when it is not
  this year) instead of "1/11/26". Month names use US English (135-strings: langconfig locale en_US),
  so September is "Sep", not Australian "Sept", across the site.

## 2026-11-19 — One live class per class

- Theme: under every scheduled live class (opening and closing time) the course page shows
  Upcoming + "Join class", Live now + "Join now", or Held + "Watch recording" (in-portal player),
  "Recording is being prepared" or "No recording". Dashboard sessions link to their own room.
- Demo Tajweed course (`scripts/dev/tajweed-liveclasses.php`, run on live): a "Live class" at the top
  of each class section; recordings, attendance links and evidence moved from the general room,
  which is hidden; upcoming classes' materials restricted per activity so their room stays visible.

## 2026-11-19 — Demo course "Foundations of Tajweed" built out

- `scripts/dev/tajweed-course.php` (+ `tajweed-content.php`, `tajweed-gradebook.php`): eight live
  classes on Saturdays and Sundays from 1 November 2026 (five held, three to come, notes released the
  day before each class), lesson notes with Arabic examples, PDF slides, handbook, book, glossary,
  links, handouts, poll, survey, three quizzes, three assignments, four announcements and Q&A.
  Built as the instructor and assistant (attendance taken, work graded with feedback, questions
  answered) and as each student (submissions, quiz attempts, progress). Gradebook weighted as the
  handbook says. Run on the live site on 2026-11-19; `--dry-run` rolls everything back.
- Theme: activity dates wrap on narrow screens; inline Arabic slightly larger; only column headings
  in small capitals (grade report item names are readable).

## 2026-11-19 — Student side: no repeated titles or clutter

- Theme: `theme_hikmah_page_init` adds body class `hk-learner` on course pages for people who
  cannot edit the course. Breadcrumb's current-page item hidden (it repeats the title); live class
  room heading hidden; students' recordings table without the Name/Description columns, durations
  in minutes, paging only with more than one page; attendance period filters hidden for students;
  "Verify certificate" only in the top menu for visitors who are not signed in (footer for all).
- `120-theme.php`: Timeline, Recently accessed items and Calendar removed from dashboards (they
  repeat "Coming up" and "Continue learning"). `010-site.php`: competencies off.
  `090-attendance.php`: attendance default view "All".

## 2026-11-19 — Recordings table; session locks after deploys

- Theme: mod_bigbluebuttonbn's YUI recordings table is fitted to the page (YUI fixes it at its
  load-time width), restyled, and shows "No recordings yet." when empty; Moodle's standard wide
  tables scroll inside themselves on phones.
- Deploy restarts cut off in-flight requests, leaving Redis session locks that blocked those users
  with a 500 for up to two hours. `upgrade.sh` now clears them after the restart and
  `session_redis_lock_expire` is 360 s.

## 2026-11-19 — Account pages per role

- `050-roles.php`: personal access tokens (`moodle/api:createtoken`) prevented for every role but
  the super administrator; the Moodle app's own sign-in permission (`createmobiletoken`) unchanged.
- `120-theme.php`: user menu no longer lists Preferences twice (Moodle adds it itself).
- `local_hikmahaccess`: Preferences page hides Preferred language and Editor preferences for
  everyone but the administrator, and Content bank preferences for people without content-bank
  access. PHPUnit `preferences_test` added (not yet run: no PHPUnit setup on the live server).
- `role-audit.php`: two new rows (tokens: admin only; app sign-in: everyone).

## 2026-11-19 — English interface only; lighter fonts; MathJax off

- `010-site.php`: language menu off, Urdu and Arabic language packs uninstalled, users/courses
  on those languages moved to English; MathJax filter disabled (it loaded from a CDN).
- Theme: RTL page-layout partial removed. Arabic/Urdu fonts kept for course text (they download
  only when a page contains Arabic script). New 5.8 KB "Hikmah Brand Arabic" font for the logo
  word in the footer and hero, so pages no longer load the 124 KB Kufi font for four letters.
- PRD section on accessibility updated with the owner's decision.

## 2026-11-19 — Student pages: UI/UX fixes

- Phones: 16px side margin, padding inside the white content card, drawer buttons parked in the
  bottom corners (Boost's hide-on-scroll never fires because Hikmah scrolls the window), footer
  reaches the bottom of short pages. Course pages drop the outer white card.
- Dashboard: "Coming up" shows register sessions as "Live class" linked to the classroom; core
  "Course overview" removed from dashboards (`120-theme.php`), still on My courses.
- New `135-strings.php` (en_local language pack): "Live class" instead of "BigBlueButton",
  "Your teacher hasn't started the class yet.", plain attendance wording. Theme icon for live
  classes. Demo generator writes real descriptions instead of "Test …".
- Deploys keep browsers' cached script bundle unless JavaScript changed (`HIKMAH_PURGE_JS`).
- Not changed: the "?" for upcoming sessions on the attendance page is hard-coded in mod_attendance.

## 2026-11-19 — Role access tightened

- `050-roles.php`: managers get the payments report and lose "log in as"; dashboard "Edit mode"
  only for the super administrator; the classmates switch `HIKMAH_STUDENT_PEERS` (default off)
  prevents students' `moodle/course:viewparticipants` and `moodle/user:viewdetails`, and restores
  the student role's defaults when turned on.
- New `local_hikmahaccess`: keeps every student's "who can message me" preference at "my contacts
  only" while the switch is off (observer on role assignment + hourly sync), which blocks
  student-to-student messages but not student↔teacher (teachers hold `moodle/site:messageanyuser`
  in their courses). PHPUnit `peers_test` covers off/on/sync/staff.
- Theme: "Payment reviews" in the top menu for users who can review payments (finance, managers,
  administrator), via Moodle's `primary_extend` hook.
- `scripts/server/role-audit.php`: read-only PASS/FAIL check of the agreed access table and the
  three messaging rules, run on the live site after the deploy.
- Laptop dev site deleted on 2026-11-19 to free disk space; work continues on the live site.

## 2026-11-18 — Enrolment page: one simple flow, and approval courses work again

- **Bug:** applying for an approval course always said "Enrolment is disabled or inactive". The
  demo script created the enrol_apply instance without `customint6` (accept new applications);
  fixed in `seed.php` and on both sites. The plugin's own default is on, so instances staff add
  through Moodle were not affected.
- **Bug:** the Hikmah course page never showed "Application sent": enrol_apply stores a new
  application as status 1 (suspended), the page only checked 2 (wait list). Now checks both.
- **Flow:** the theme now renders Moodle's enrolment page (`core_course_renderer::enrolment_options`
  override; core untouched). When it has nothing the Hikmah course page does not already offer
  (free, paid, or any course opened by someone not enrolled) it sends people to the course page.
  Approval courses get a short "Apply to join this course" page: a compact course card with a
  link to the full details, three plain steps, an optional note box and a "Send application"
  button (the plugin labelled it "Enrol me"). After applying, the student lands on the course
  page with "Your application has been sent" and an "Application sent" button.
- Verified on the laptop site: a full application as a demo student (saved, redirected, button
  state), page alignment at 390px and 1440px, no sideways scrolling.
- Note: the messages say staff will email the student; email is not set up on the live site yet.

## 2026-11-18 — Payment page redesign; full-width pages and footer

- **Width (site-wide):** Moodle capped standard pages, and their footer, at 830px. Set
  `$course-content-maxwidth: 1120px` (matches Hikmah's own pages; Boost derives drawer positions
  from it) and made the Hikmah footer span the window (it was capped and rounded like a card).
  Checked on the payment page, dashboard, course enrolment page and payments list.
- **Payment page:** one heading (was "Pay by transfer" twice), the course name and a shorter
  intro; account details as rows with the label above an aligned value and the copy button on the
  right; the receipt form stacked with required marks next to labels, the date on one line, the
  file picker without folder/view/path controls, and no duplicate size/type lines (the hint says
  it). "Paid by" is hidden when only one method exists. Removed the step-2 sentence that repeated
  its title; the live note is now just the NTN (the reference instruction already appears once).
- **Phones:** the payment panels no longer push the page wider than the screen (390px check:
  page width 390); Moodle's footer pop-up contents, shown inline on phones, are styled into the
  footer and lose the links the Hikmah footer already has.
- **Dashboard:** "Course overview" and "Timeline" now line up with the welcome banner.
- Verified on the laptop site with headless Chrome at 1440px and 390px; payment PHPUnit tests
  pass (10); the single-method form hides the choice and validates.

## 2026-11-18 — Bank transfer details live; Nginx survives Moodle restarts

- Live site: transfer details set in `.env.prod` (account title, bank, account number, plus the
  NTN in the payment-page note) and applied; the payment gateway is now enabled with the bank
  method. JazzCash, Easypaisa and Raast are still empty, so only bank transfer is offered.
- `HIKMAH_TRANSFER_NOTE` was read by `070-enrol.php` but never passed to the containers; it is now
  in `compose.base.yml` and documented in `.env.example`.
- Incident (about 2 minutes, live site): recreating only the moodle container left Nginx sending
  requests to its old address, so every page returned 502 until Nginx was restarted. Nginx now
  resolves the PHP-FPM host through Docker's DNS on each request (`resolver 127.0.0.11`,
  `fastcgi_pass $hk_fpm`). Verified on the laptop by recreating only moodle (site stayed 200),
  then deployed to the live site; smoke test passed.

## 2026-11-18 — Live classes on the live site

- BiggerBlueButton (`hikmahonline1`) connected on https://online.hikmahinstitute.org.pk:
  `BBB_SERVER_URL` / `BBB_SHARED_SECRET` copied from the laptop site into `.env.prod` on the server
  (never through Git or chat), applied by `configure.sh`; the server accepted the secret.
- The owner switched off BiggerBlueButton's "BlueTune" app/browser join screen. Verified: joins go
  straight to the class page, which sends no frame-blocking headers.
- Branding verified: BiggerBlueButton applies `bbbext_hikmah`'s stylesheet (class buttons render
  in Hikmah blue `#0a6a9c`).
- The owner tested a real class (teacher and student) in the in-portal classroom page: it stays
  inside Hikmah with microphone and camera working.
- Fixed (classroom page): microphone and camera were refused inside the class because the frame's
  `allow` list only covered the frame's starting address (this site); it now names the
  BigBlueButton server's origin as well. The owner confirmed audio and video work.
- Fixed (classroom page): ending a meeting showed "The class could not be opened here". The page
  now notices the class opening by checking every second (the class page can take long to fire
  `load`), and any return to this site after that shows "You have left the class". Tested on the
  laptop site headlessly and confirmed by the owner on the live site.
- Fixed: `seed.php` passed `participants` to the test-data generator, which ignores it, so demo
  classes had no moderator and could never start. It is now set after creation; the four demo
  classes on the live site were corrected the same way.

## 2026-11-18 — Live on Contabo: https://online.hikmahinstitute.org.pk

First production install, following `docs/runbooks/contabo.md` (runbook corrected as it ran).

- Server: Contabo, 157.173.114.25, Ubuntu 24.04.5, 4 vCPU / 7.8 GB / 96 GB, x86_64.
- `bootstrap.sh` fixes found on the real server: Contabo puts the key on `ubuntu` and gives root a
  "log in as ubuntu" redirect, so keys are now copied from the sudo user and stripped of forced
  commands; the no-password rule moved to `01-hikmah.conf` because Contabo's `50-cloud-init.conf`
  (read first, first value wins) turned password logins back on. Verified: password logins refused.
- Code copied from the laptop (tracked files only); images built on the server
  (`IMAGE_PULL_POLICY=never`); `SITE_ENV=prod`.
- `install.sh` passed, including the smoke test over HTTPS; Let's Encrypt certificate issued
  (valid to 16 Feb 2027); HTTP redirects to HTTPS; all containers healthy; `configure.sh` second
  run 0 changes; 2.2 GB memory in use.
- First backup taken (5 MB) and nightly backup scheduled at 02:15 UTC. Backups stay on the server
  until `BACKUP_REMOTE` (Backblaze B2) is set.

Still to do: off-site backups, SMTP sender, BBB server URL and secret, real bank/wallet details,
approved privacy notice and terms.

## 2026-11-17 — Hosting on Contabo

Decision: the site is hosted on a Contabo Cloud VPS 20 (6 vCPU, 12 GB, 100 GB NVMe, Ubuntu 24.04,
Singapore). This replaces the guide's 160 GB disk minimum for the pilot; recordings live with the
BBB provider, so 100 GB is ample.

### Added

- `scripts/server/bootstrap.sh`: one-time preparation of a fresh Ubuntu 24.04 server (security
  updates and automatic security updates, `hikmah` user, firewall for SSH/HTTP/HTTPS only,
  fail2ban, Docker from Docker's repository with log rotation, 4 GB swap, rclone; password SSH
  logins switched off only once a key is in place).
- `scripts/server/new-env.sh`: creates `.env.staging` / `.env.prod` on the server with generated
  database password, administrator password and upgrade key; never overwrites.
- `docs/runbooks/contabo.md`: from purchase to live HTTPS site with nightly off-site backups.
- `IMAGE_PULL_POLICY` (compose.server.yml, .env.example): `never` lets the images be built on the
  server instead of pulled from GitHub's registry.

### Verified

- Both scripts pass `bash -n`; `new-env.sh` tested in a scratch folder (fills address and email,
  generates secrets, refuses to overwrite, rejects `https://` in the host).

### Not verified

- Nothing has run on a real Contabo server yet; `bootstrap.sh` has not been executed anywhere.
- `.github/workflows/build.yml` (registry images) has never run.

## 2026-11-14 — Smooth scrolling and login page polish

### Changed

- **Smooth scrolling.** The pulsing red "Live" dot on course cards animated a box-shadow, which
  repainted the whole card on every frame for as long as the page was open. It is now a separate
  ring that only scales and fades (GPU-composited) and pauses when off screen. Rosette animations
  pause off screen; the quote rosette breathes as one piece instead of tile by tile. The top bar
  and catalog search bar no longer blur what scrolls behind them (a live blur is repainted on
  every scroll frame). Scroll reveals move less (16 px instead of 28 px) and drop their GPU layer
  once shown.
- **Login page.** The rosette is smaller, set back and faded, with dark shading behind the logo
  and the message so neither is covered. Moodle's footer links (data retention summary, mobile
  app) now sit in the card's link row instead of an unstyled list under the page; the duplicate
  "Policies" link is hidden (same page as "Privacy and terms").

### Measured (headless Chrome, CPU slowed 4×, real mouse-wheel input)

- CPU busy time on an idle page, per 3.3 s: catalog 1,415 → 169 ms, course detail 1,017 → 51 ms,
  home about 17 ms once settled.
- Home page while scrolling: 40 → 55 frames per second, dropped frames 24 → 4.
- Not measurable headless: the cost of the removed blur, which falls on the graphics chip.
  Check on a real laptop and phone.

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
