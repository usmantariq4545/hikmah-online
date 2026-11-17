#!/bin/sh
# Hikmah Online container entrypoint. Modes: fpm (default), cron, adhoc, or any command.
set -eu

MOODLE_DIR=/var/www/moodle
CLI="$MOODLE_DIR/admin/cli"
[ -d "$CLI" ] || CLI="$MOODLE_DIR/public/admin/cli"

# Persistent volumes arrive root-owned on first start.
for dir in /var/moodledata /var/localcache; do
    mkdir -p "$dir"
    chown www-data:www-data "$dir"
    chmod 2770 "$dir"
done

as_www() {
    runuser -u www-data -- "$@"
}

case "${1:-fpm}" in
    fpm)
        exec php-fpm
        ;;
    cron)
        echo "cron: running $CLI/cron.php every 60 s"
        while true; do
            # Before the first install cron.php exits with an error; that is expected, keep waiting.
            as_www php "$CLI/cron.php" --keep-alive=0 \
                || echo "cron: run failed or Moodle is not installed yet (exit $?); retrying in 60 s"
            sleep 60
        done
        ;;
    adhoc)
        exec runuser -u www-data -- php "$CLI/adhoc_task.php" --execute --keep-alive=59
        ;;
    *)
        exec "$@"
        ;;
esac
