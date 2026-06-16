#!/usr/bin/env bash
set -euo pipefail

ROLE="${CONTAINER_ROLE:-web}"

wait_for_db() {
    echo "[entrypoint] waiting for database ${DB_HOST:-mysql}:${DB_PORT:-3306} ..."
    until php -r '
        try {
            new PDO(
                "mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT")?:"3306"),
                getenv("DB_USERNAME"),
                getenv("DB_PASSWORD")
            );
        } catch (Throwable $e) { exit(1); }
        exit(0);
    '; do
        sleep 2
    done
    echo "[entrypoint] database is up."
}

case "$ROLE" in
    web)
        wait_for_db
        # NEVER migrate:fresh in prod — only forward migrations.
        php artisan migrate --force
        php artisan storage:link || true
        # NOTE: no route:cache — routes/web.php uses a closure route (SPA catch-all)
        # which cannot be serialized; route:cache would throw and crash-loop the container.
        php artisan config:cache || true
        php artisan view:cache || true
        chown -R www-data:www-data storage bootstrap/cache
        echo "[entrypoint] starting web (nginx + php-fpm)"
        exec supervisord -c /etc/supervisord.conf
        ;;

    reverb)
        echo "[entrypoint] starting Reverb WebSocket server on 0.0.0.0:8080"
        exec php artisan reverb:start --host=0.0.0.0 --port=8080
        ;;

    queue)
        wait_for_db
        # Wait until migrations (run by web) have created the queue/cache tables,
        # otherwise queue:work crash-loops on first boot of a fresh database.
        until php artisan migrate:status >/dev/null 2>&1; do
            echo "[entrypoint] queue waiting for migrations..."
            sleep 3
        done
        echo "[entrypoint] starting queue worker"
        exec php artisan queue:work --tries=3 --timeout=90 --sleep=3 --max-time=3600
        ;;

    *)
        exec "$@"
        ;;
esac
