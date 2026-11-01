---
name: docker-dev
description: How to run, seed, test and debug this Shopper monorepo with Docker Compose and Orchestra Testbench (there is no Laravel app, `vendor/bin/testbench` replaces `php artisan`). Use whenever you need to run PHP commands, tests, migrations, or look at the admin in a browser.
---

# Docker development environment

- Stack: `docker-compose.yml` → `app` (PHP 8.4, `testbench serve` on :8000) and `mysql` (8.4, host port 3307).
- The host PHP is 8.3 and cannot load `vendor/` (needs ≥ 8.4.1). Run PHP tooling in the container.
  Only Pint works on the host.
- App config: `workbench/.env` (Testbench reads it from there, not from the root `.env`).
- Admin: http://localhost:8000/cpanel, `admin@laravelshopper.dev` / `password`.

## Commands

```bash
docker compose up -d --build                                          # start / rebuild
docker compose exec -T app vendor/bin/testbench migrate:fresh --seed  # reset + seed demo data
docker compose exec -T app vendor/bin/pest tests/Admin/...            # tests (DB_CONNECTION=testing, SQLite in memory)
docker compose exec -T app vendor/bin/pest --parallel                 # full suite
docker compose exec -T app vendor/bin/phpstan analyse --memory-limit=-1
docker compose exec -T app vendor/bin/testbench route:list --path=cpanel
vendor/bin/pint <paths>                                               # host is fine
```

## Where things live

- Demo seeders: `workbench/database/seeders` (namespace `Database\Seeders`, `DatabaseSeeder` is the entry point).
- Workbench user model: `workbench/app/Models/User.php` (`AUTH_MODEL` in `workbench/.env`).
- Uploaded files: `./uploads` on the host, mounted at the skeleton `storage/app/public/uploads`, served at `/storage/uploads/...`.
- The entrypoint (`docker/php/entrypoint.sh`) refreshes the skeleton `.env`, runs `storage:link`,
  `filament:assets`, and links `packages/admin/public` to `/cpanel` (logo, favicons).

## Gotchas

- A new package's config/addon missing in the dev app (`testbench config:show` says the key does not exist):
  run `docker compose exec -T app vendor/bin/testbench package:discover` (the entrypoint also does it on start).

- `MultipleRootElementsDetectedException`, `Undefined variable $__blaze` or `RootTagMissingFromViewException`
  in tests means stale compiled views in `vendor/orchestra/testbench-core/laravel/storage/framework/views`.
  Delete `*.php` in that folder. The dev server compiles into `/tmp/shopper-views` (`VIEW_COMPILED_PATH`), so the two never clash.
- Alpine errors like `filamentDropdown is not defined` in the browser mean Filament assets were not
  published. Restart the app container (`docker compose restart app`).
- `Illuminate\Http\Testing\File` (`UploadedFile::fake()`) reports a mime type guessed from the file *name*.
  To test content sniffing use a real temp file with `new UploadedFile($path, 'name.png', test: true)`.
- New Livewire pages need a browser check: log in and open the page with the Chrome DevTools MCP tools,
  and read the console for errors.
