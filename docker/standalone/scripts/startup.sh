#!/bin/bash
# CtrlPanel standalone boot, mirrors manual install guide.
# Owns: wait for deps, vendor, perms, processes. Never owns: .env content, migrate/seed (installer does).
set -e

APP_DIR="/var/www/html"
LOG_DIR="$APP_DIR/storage/logs"
LOG_FILE="$LOG_DIR/startup-script.log"

echo "CtrlPanel boot..."

log_message() {
    if [ -d "$LOG_DIR" ]; then
        echo "$1" >> "$LOG_FILE" 2>/dev/null || true
    fi
    echo "$1"
}

wait_for_host_port() {
    local host="$1" port="$2" name="$3" tries=0 max=60
    while ! (echo > "/dev/tcp/$host/$port") >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -ge "$max" ]; then
            log_message "ERROR: $name at $host:$port unreachable after ${max}s. Check compose network."
            exit 1
        fi
        sleep 1
    done
    log_message "OK: $name reachable at $host:$port."
}

# 1. Code present. Empty ./panel bind hides baked code, seed it once from pristine copy.
if [ ! -f "$APP_DIR/artisan" ] || [ ! -d "$APP_DIR/public" ]; then
    if [ -f "/var/panel-seed/artisan" ]; then
        log_message "./panel bind is empty, seeding from image (one time)..."
        cp -a /var/panel-seed/. "$APP_DIR/"
    fi
fi
if [ ! -f "$APP_DIR/artisan" ] || [ ! -d "$APP_DIR/public" ]; then
    echo "ERROR: $APP_DIR/artisan or public/ missing and no seed available."
    exit 1
fi

# 2. Wait for MariaDB + Redis by service name, same values installer prefill shows.
DB_HOST="${DB_HOST:-mariadb}"
DB_PORT="${DB_PORT:-3306}"
REDIS_HOST="${REDIS_HOST:-redis}"
REDIS_PORT="${REDIS_PORT:-6379}"
wait_for_host_port "$DB_HOST" "$DB_PORT" "MariaDB"
wait_for_host_port "$REDIS_HOST" "$REDIS_PORT" "Redis"

# 3. Permissions + storage skeleton first. Bind ./storage starts root-owned/empty.
mkdir -p "$APP_DIR/storage/app/public" "$APP_DIR/storage/framework/cache" "$APP_DIR/storage/framework/sessions" "$APP_DIR/storage/framework/views" "$APP_DIR/storage/logs" "$APP_DIR/bootstrap/cache"
chown -R laravel:laravel "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true
chmod 755 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true

# 4. Composer packages, same command as manual guide. Only if vendor missing.
if [ ! -f "$APP_DIR/vendor/autoload.php" ]; then
    log_message "vendor/ missing. Running: composer install --no-dev --optimize-autoloader"
    cd "$APP_DIR"
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
    cd - >/dev/null
    chown -R laravel:laravel "$APP_DIR/vendor" 2>/dev/null || true
else
    log_message "OK: vendor/ present, skipping composer install."
fi

# 5. Storage symlink, same as manual guide. Installer also runs it, keep idempotent.
if [ ! -L "$APP_DIR/public/storage" ]; then
    log_message "Creating storage symlink..."
    runuser -u laravel -- php "$APP_DIR/artisan" storage:link || log_message "Warning: storage:link failed, installer will retry."
else
    log_message "OK: storage symlink present."
fi
# No values written here, only ensure template exists. Installer fills values.
# Empty check matters: bind-mounted `touch panel.env` is 0 bytes, installer skips existing files.
if [ ! -f "$APP_DIR/.env" ] || [ ! -s "$APP_DIR/.env" ]; then
    log_message ".env missing or empty. Seeding from .env.example..."
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
fi
chown laravel:laravel "$APP_DIR/.env"
if grep -q "^APP_KEY=$" "$APP_DIR/.env"; then
    log_message "No .env values yet. Open /installer, it fills them."
fi

# 6. Nginx site config fallback.
if [ ! -f "/etc/nginx/conf.d/default.conf" ]; then
    log_message "Nginx site missing, restoring baked default.conf..."
    cp "$APP_DIR/docker/standalone/nginx/default.conf" /etc/nginx/conf.d/default.conf
fi

# 7. Background workers, same flags as manual systemd unit + cron.
log_message "Starting queue worker (queue:work --sleep=3 --tries=3)..."
runuser -u laravel -- php "$APP_DIR/artisan" queue:work --sleep=3 --tries=3 &
log_message "Starting scheduler (schedule:work)..."
runuser -u laravel -- php "$APP_DIR/artisan" schedule:work &
# No migrate here. Installer feedDB step owns migrate --seed.

# 8. Foreground web stack.
log_message "Starting Nginx..."
service nginx start
log_message "Starting PHP-FPM (PID 1)..."
exec php-fpm -F
