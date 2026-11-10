#!/usr/bin/env bash
# Update the live site.
#
#   Automatic:  every push to main → GitHub Action → cPanel pulls the code and
#               runs this script through .cpanel.yml (SKIP_PULL=1).
#   By hand:    cPanel → Terminal:  cd ~/speedtech && bash deploy.sh
#
# Safe to run any number of times. On the first run it also generates the app
# key, creates the admin user and the brands / part types, and (if
# DEPLOY_PUBLIC_HTML is set in .env) points public_html at this app.
set -euo pipefail
cd "$(dirname "$0")"
APP_DIR="$(pwd)"

if [ ! -f .env ]; then
  echo "!! No .env file in $APP_DIR. Create it first (see README → Deploying to cPanel)." >&2
  exit 1
fi
env_value() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' || true; }

# --- PHP 8.2+ (cPanel's default CLI can be older than the site's PHP) ---------
if [ -z "${PHP:-}" ]; then
  for candidate in php /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php /opt/cpanel/ea-php82/root/usr/bin/php; do
    if command -v "$candidate" >/dev/null 2>&1 && "$candidate" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' 2>/dev/null; then
      PHP="$candidate"; break
    fi
  done
fi
[ -n "${PHP:-}" ] || { echo "!! PHP 8.2 or newer not found. Select PHP 8.2+ in cPanel → MultiPHP Manager." >&2; exit 1; }
echo "==> Using $($PHP -r 'echo PHP_BINARY, " ", PHP_VERSION;')"

# chmod below must not show up as local changes that block the next git pull
git config core.fileMode false

echo "==> Maintenance mode on"
$PHP artisan down --retry=15 >/dev/null 2>&1 || true
trap '$PHP artisan up >/dev/null 2>&1 || true' EXIT   # always bring the site back up

if [ "${SKIP_PULL:-0}" != "1" ]; then
  echo "==> Pulling latest code from GitHub"
  git pull --ff-only origin main
fi

echo "==> Installing PHP packages"
if command -v composer >/dev/null 2>&1; then
  COMPOSER="composer"
else
  [ -f composer.phar ] || curl -sS https://getcomposer.org/installer | $PHP -- --quiet
  COMPOSER="$PHP composer.phar"
fi
export COMPOSER_HOME="${COMPOSER_HOME:-$HOME/.composer}"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction --no-progress

mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache public/uploads/products
$PHP artisan config:clear >/dev/null

if [ -z "$(env_value APP_KEY)" ]; then
  echo "==> Generating APP_KEY (first install)"
  $PHP artisan key:generate --force
fi

echo "==> Updating the database"
$PHP artisan migrate --force

if [ "$($PHP artisan tinker --execute='echo \App\Models\User::where("is_admin", true)->exists() ? "yes" : "no";' 2>/dev/null | tail -n1)" = "no" ]; then
  echo "==> First install: creating admin user, brands, models and part types"
  $PHP artisan db:seed --class=AdminUserSeeder --force
  $PHP artisan db:seed --class=CatalogStructureSeeder --force
fi

echo "==> Refreshing caches"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

# --- Point the domain's web folder at public/ (one time, opt-in via .env) ------
DOCROOT="$(env_value DEPLOY_PUBLIC_HTML)"
if [ -n "$DOCROOT" ] && [ "$(readlink -f "$DOCROOT" 2>/dev/null)" != "$APP_DIR/public" ]; then
  if [ -e "$DOCROOT" ] && [ ! -L "$DOCROOT" ]; then
    BACKUP="$DOCROOT.before-laravel-$(date +%Y%m%d%H%M%S)"
    echo "==> Moving the old $DOCROOT to $BACKUP"
    mv "$DOCROOT" "$BACKUP"
  else
    rm -f "$DOCROOT"
  fi
  ln -s "$APP_DIR/public" "$DOCROOT"
  echo "==> $DOCROOT now points to $APP_DIR/public"
fi

# Uploaded files must never run as code: serve only images, never execute scripts.
cat > public/uploads/.htaccess <<'HTACCESS'
Options -Indexes -ExecCGI
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .phar .cgi .pl .py .sh
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .phar
<IfModule mod_php.c>
  php_flag engine off
</IfModule>
<FilesMatch "(?i)\.(php\d?|phtml|phps|phar|cgi|pl|py|sh|html?|svg|js)$">
  Require all denied
</FilesMatch>
<IfModule mod_headers.c>
  Header set X-Content-Type-Options "nosniff"
</IfModule>
HTACCESS
chmod -R 775 storage bootstrap/cache public/uploads

echo "==> Done. Site is live: $(env_value APP_URL)"
