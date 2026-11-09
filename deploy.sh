#!/usr/bin/env bash
# Update the live site from GitHub.
# On the server:   cd ~/speedtech && bash deploy.sh
set -euo pipefail
cd "$(dirname "$0")"

PHP="${PHP:-php}"

echo "==> Maintenance mode on"
$PHP artisan down --retry=15 >/dev/null 2>&1 || true
trap '$PHP artisan up >/dev/null 2>&1 || true' EXIT   # always bring the site back up

echo "==> Pulling latest code from GitHub"
git pull --ff-only origin main

echo "==> Installing PHP packages"
if command -v composer >/dev/null 2>&1; then
  COMPOSER="composer"
else
  [ -f composer.phar ] || curl -sS https://getcomposer.org/installer | $PHP -- --quiet
  COMPOSER="$PHP composer.phar"
fi
$COMPOSER install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> Updating the database"
$PHP artisan migrate --force

echo "==> Refreshing caches"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

mkdir -p public/uploads/products
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

echo "==> Done. Site is live: $(grep -E '^APP_URL=' .env | cut -d= -f2-)"
