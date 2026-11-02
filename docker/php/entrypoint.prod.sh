#!/usr/bin/env bash
set -e

# Testbench copies workbench/.env (the mounted .env.production) into its skeleton only when none exists there.
rm -f vendor/orchestra/testbench-core/laravel/.env

vendor/bin/testbench package:discover > /dev/null
vendor/bin/testbench storage:link --force > /dev/null
vendor/bin/testbench filament:assets > /dev/null
vendor/bin/testbench vendor:publish --tag=shopper-cms-assets --force > /dev/null

# Admin images (logo, favicons) are served from public/<admin prefix>.
prefix=$(grep -E '^SHOPPER_PREFIX=' workbench/.env 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d '"'"'"' \r')
ln -sfn /var/www/html/packages/admin/public "vendor/orchestra/testbench-core/laravel/public/${prefix:-cpanel}"

mkdir -p /tmp/shopper-views

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    vendor/bin/testbench migrate --force
fi

exec "$@"
