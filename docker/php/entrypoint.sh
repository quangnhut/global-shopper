#!/usr/bin/env bash
set -e

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

# Testbench copies workbench/.env into its skeleton only when none exists there,
# so drop any stale copy to always start from the current workbench/.env.
rm -f vendor/orchestra/testbench-core/laravel/.env

# Pick up package service providers added since the last start (e.g. packages/cms).
vendor/bin/testbench package:discover > /dev/null 2>&1 || true

# Expose the public disk (including admin uploads) at /storage.
vendor/bin/testbench storage:link --force > /dev/null 2>&1 || true

# Publish Filament's JS/CSS and Shopper's images into the Testbench public folder.
vendor/bin/testbench filament:assets > /dev/null 2>&1 || true
vendor/bin/testbench vendor:publish --tag=shopper-cms-assets --force > /dev/null 2>&1 || true
# Admin images (logo, favicons) are served from public/<admin prefix>, so follow SHOPPER_PREFIX.
prefix=$(grep -E '^SHOPPER_PREFIX=' workbench/.env 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d '"'"'"' \r')
prefix=${prefix:-cpanel}
public=vendor/orchestra/testbench-core/laravel/public
# Drop the link made for a previous prefix (and the folder an earlier version of this script created).
find "$public" -maxdepth 1 -type l -lname /var/www/html/packages/admin/public -delete
for old in "$public"/*/.shopper-admin-assets; do [ -e "$old" ] && rm -rf "$(dirname "$old")"; done
ln -sfn /var/www/html/packages/admin/public "$public/$prefix"

mkdir -p /tmp/shopper-views

exec "$@"
