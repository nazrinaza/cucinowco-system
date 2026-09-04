#!/bin/bash

# One-time staging recovery for cPanel accounts where "Deploy HEAD Commit"
# cannot run. The completion marker makes repeated cron executions harmless.

set -Eeuo pipefail

APP_ROOT="${1:-/home2/shahjaha/public_html/staging.cucinow.co}"
PHP_BIN="/opt/alt/php83/usr/bin/php"
MIGRATION="2026_09_04_000001_add_email_delivery_support.php"
COMPLETION_MARKER="${APP_ROOT}/storage/framework/resend-setup.completed"

timestamp() {
    /bin/date '+%Y-%m-%d %H:%M:%S %Z'
}

log() {
    echo "[$(timestamp)] $*"
}

fail() {
    log "ERROR: $*"
    log "Correct the problem and leave this cron job enabled for one more minute."
    exit 1
}

on_error() {
    local exit_code=$?
    log "ERROR: Setup stopped at line ${BASH_LINENO[0]} (exit ${exit_code})."
    log "No completion marker was written, so the next cron run can retry."
    exit "${exit_code}"
}

trap on_error ERR

log "Starting CuciNow Resend staging setup."

[[ -x "${PHP_BIN}" ]] || fail "PHP 8.3 was not found at ${PHP_BIN}."
[[ -d "${APP_ROOT}" ]] || fail "Laravel root does not exist: ${APP_ROOT}"
[[ -f "${APP_ROOT}/artisan" ]] || fail "artisan was not found in ${APP_ROOT}."
[[ -f "${APP_ROOT}/.env" ]] || fail ".env was not found in ${APP_ROOT}."

if [[ -f "${COMPLETION_MARKER}" ]]; then
    log "SETUP ALREADY COMPLETE: remove this temporary cron job."
    exit 0
fi

[[ -f "${APP_ROOT}/vendor/resend/resend-laravel/src/ResendServiceProvider.php" ]] || \
    fail "Resend package is missing. In cPanel, track the deploy branch and run Update from Remote first."
[[ -f "${APP_ROOT}/database/migrations/${MIGRATION}" ]] || \
    fail "The email migration is missing. Update the cPanel repository from the deploy branch first."

if ! /bin/grep -Eq '^[[:space:]]*MAIL_MAILER[[:space:]]*=[[:space:]]*"?resend"?[[:space:]]*$' "${APP_ROOT}/.env"; then
    fail "Set MAIL_MAILER=resend in .env."
fi

if ! /bin/grep -Eq '^[[:space:]]*RESEND_API_KEY[[:space:]]*=[[:space:]]*"?re_[^[:space:]"#]+' "${APP_ROOT}/.env"; then
    fail "Set a valid, non-placeholder RESEND_API_KEY in .env. The secret value will not be printed."
fi

if ! /bin/grep -Eq '^[[:space:]]*QUEUE_CONNECTION[[:space:]]*=[[:space:]]*"?database"?[[:space:]]*$' "${APP_ROOT}/.env"; then
    fail "Set QUEUE_CONNECTION=database in .env."
fi

if ! /bin/grep -Eq '^[[:space:]]*RESEND_WEBHOOK_SECRET[[:space:]]*=[[:space:]]*"?whsec_[^[:space:]"#]+' "${APP_ROOT}/.env"; then
    log "WARNING: RESEND_WEBHOOK_SECRET is missing. Sending can work, but delivery/open tracking will not."
fi

cd "${APP_ROOT}"

log "Clearing cached Laravel configuration."
"${PHP_BIN}" artisan optimize:clear

log "Applying pending database migrations."
"${PHP_BIN}" artisan migrate --force --no-interaction

log "Preparing writable Laravel directories."
/usr/bin/find storage bootstrap/cache -type d -exec /bin/chmod 775 {} \;
/usr/bin/find storage bootstrap/cache -type f ! -name .gitignore -exec /bin/chmod 664 {} \;

if [[ ! -L public/storage ]]; then
    log "Creating the public storage link."
    "${PHP_BIN}" artisan storage:link || log "WARNING: storage:link failed; email sending can still continue."
fi

log "Rebuilding Laravel production caches."
"${PHP_BIN}" artisan optimize

log "Restarting queue workers and processing all currently queued emails."
"${PHP_BIN}" artisan queue:restart || true
"${PHP_BIN}" artisan queue:work database --stop-when-empty --max-time=50 --tries=3 --no-interaction

/usr/bin/touch "${COMPLETION_MARKER}"
log "SETUP COMPLETE: migration applied and queued email jobs processed."
log "Remove this temporary cron job, keep the normal schedule:run cron, then send a fresh test email."
