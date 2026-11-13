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

5. Run the Behat journeys and walk the P0 journeys by hand on staging.
6. Production, in a maintenance window, same three commands with `HIKMAH_ENV=prod`.
7. Add a line to `docs/CHANGELOG.md`.

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
