# Runbook: upgrades

Moodle stays on **5.3 LTS** (security support to October 2029). Take the minor releases
(5.3.x, about every two months). Do not move to 6.0; wait for the next LTS and for every plugin in
`versions.lock` to support it.

## Minor Moodle release or plugin update

1. Branch. Edit `versions.lock`: new `MOODLE_TAG` and `MOODLE_COMMIT` (the commit the tag points
   at), and any plugin commits. For each third-party plugin, check its page lists 5.3 support; if
   not, test on dev and write the result in the lock file comment.
2. Push. CI (`.github/workflows/ci.yml`) runs moodle-plugin-ci for the three Hikmah plugins.
3. Tag the commit. `build.yml` builds and pushes multi-arch images.
4. Staging:

```bash
export HIKMAH_ENV=staging
# set MOODLE_IMAGE / NGINX_IMAGE to the new tag in .env.staging
scripts/backup.sh --maintenance
scripts/upgrade.sh
```

5. Run the Behat journeys and walk the P0 journeys by hand on staging, then the
   [after-upgrade checklist](#after-upgrade-checklist-hikmah-adjustments-to-moodle-pages) below.
6. Production, in a maintenance window, same three commands with `HIKMAH_ENV=prod`.
7. Add a line to `docs/CHANGELOG.md`.

## After-upgrade checklist: Hikmah adjustments to Moodle pages

Moodle core is never patched, so an upgrade keeps all Hikmah code. These few spots depend on how
Moodle itself draws a page. If Moodle changes that, nothing breaks and no data is at risk: the spot
quietly falls back to plain Moodle. Check each on staging after every Moodle upgrade (minor or major).

**1. Moodle templates the theme overrides.** Compare each with the new Moodle's own copy and carry
over any change Moodle made (new links, accessibility fixes):

| Hikmah copy (`plugins/theme/hikmah/templates/`) | Moodle's original (`public/`) |
| --- | --- |
| `theme_boost/footer.mustache` | `theme/boost/templates/footer.mustache` |
| `theme_boost/navbar.mustache` | `theme/boost/templates/navbar.mustache` |
| `core/primarymoremenu.mustache` | `lib/templates/primarymoremenu.mustache` |

Course and activity pages use Boost's own layout unchanged. Hikmah's own layouts (home, catalogue,
FAQ, sign-in, classroom) load Boost's `theme_boost/loader` and `theme_boost/drawer` scripts: check those
pages open with no errors in the browser console.

Quick way: `git diff v5.3.<old> v5.3.<new> --stat -- <those paths>` in a Moodle checkout. No lines
listed means nothing to do.

**2. Small scripts that adjust Moodle's pages after they load** (`classes/hook_callbacks.php` and
`classes/local/blankdates.php`). Look at each as a learner (demo.student1) and as a teacher:

| Where | Should show | If Moodle changed its page, you'd see |
| --- | --- | --- |
| Course page, live class | Status, "Starts … · Duration …", join button; held class named "Class recording" | Moodle's "Opens / Closes" dates, no button |
| Course page, every item | Type and length under the name ("Quiz • 20 min"); no "Due" line once done | No line under names |
| A class's own page | One-line summary under the title ("2 readings · 1 quiz") | No summary |
| Left course list | Lock beside classes with locked items; "Class recording" names | No locks; original names |
| Desktop, short page | Left list ends above the footer | Footer covers the list's end |
| Attendance page | "Not marked yet" (and "–") | "?" |
| Live class page | "Starts … · Duration …"; recordings dated "Wednesday 18 November 2026" | "Opened / Closed"; US date order |
| Profile edit, payment form | Date of birth and date paid start as empty Day / Month / Year | Today's date filled in |

If one falls back, open the new page's HTML and update the marker the script looks for (for example
`li.activity[data-id]`, `[data-region="activity-dates"]`, `.courseindex [data-for="section"]`).

**3. Other things to glance at**
- Required fields show a red `*`, not `!` (`classes/output/icon_system_fontawesome.php`).
- Wording: "Sep" in dates, "Enroll" in buttons. `config/settings/135-strings.php` re-applies the
  language overrides on every deploy; a string Moodle renamed just shows Moodle's wording.
- Hook callbacks (`db/hooks.php` in each plugin): if Moodle removed a hook, the upgrade page warns. Read
  `UPGRADING.md` of the new version for "deprecated" or "removed" hooks and APIs used by Hikmah plugins.

## Rollback

`scripts/upgrade.sh` leaves the previous backup untouched. To go back: put the previous image
tags in the env file and `scripts/restore.sh` the backup taken in step 4. Never run an older
Moodle against a database that a newer one has upgraded.

## After a BigBlueButton upgrade

The analytics payload is not formally documented. Before trusting attendance again:

1. Capture a real callback from the new version and add it to
   `plugins/local/hikmahattendance/tests/fixtures/` (strip names first).
2. Add its expectations to `calculator_test::test_fixture_*` and run the tests.
3. If the shape changed, sessions show "Unreadable payload" in the exceptions queue until the
   parser has a new version; nothing is guessed in the meantime.

## Changing the course category structure

Take a backup first. A reported tool_certificate issue deleted templates and revoked issued
certificates when categories were restructured.
