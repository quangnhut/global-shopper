# CLAUDE.md

This file provides guidance to Claude Code when working with code in the Shopper repository.

## Project Overview

Shopper is a headless e-commerce admin panel built for Laravel using the TALL stack (Tailwind CSS, Alpine.js, Laravel, Livewire). It is organized as a monorepo with eight packages.

## Monorepo Structure

```
packages/
├── admin/      → Main admin UI (Livewire components, Filament forms/tables, views, routes, assets)
├── cart/       → Cart management with pipeline-based calculation, discounts, and taxes
├── core/       → E-commerce domain logic (Models, Actions, Enums, Contracts, Stock, Taxes)
├── payment/    → Payment processing with extensible driver architecture
├── shipping/   → Shipping providers integration with driver architecture
├── sidebar/    → Sidebar navigation builder (DDD-style architecture)
├── stripe/     → Stripe payment driver for the payment system
└── types/      → TypeScript type definitions (NPM package, no PHP)
```

Each PHP package has its own service provider:
- `Shopper\ShopperServiceProvider` (admin)
- `Shopper\Cart\CartServiceProvider` (cart)
- `Shopper\Core\CoreServiceProvider` (core)
- `Shopper\Payment\PaymentServiceProvider` (payment)
- `Shopper\Shipping\ShippingServiceProvider` (shipping)
- `Shopper\Sidebar\SidebarServiceProvider` (sidebar)
- `Shopper\Stripe\StripeServiceProvider` (stripe)

Packages are split to separate repositories via the `monorepo-split.yml` workflow on push to `*.x` branches.

There is also a `packages/cms` package (`Shopper\Cms\CmsServiceProvider`, WordPress-style posts/pages), built from the plans in `.claude/plans/`.

## Running the Project

This repo is a **package monorepo, not a Laravel app**. Locally it runs inside an Orchestra Testbench
"workbench" app, and `vendor/bin/testbench` replaces `php artisan` everywhere.

### Compose files: which one to use

- **`docker-compose-dev.yml` is for localhost development**: mounts the repo so code changes apply immediately, exposes MySQL on the host.
- **`docker-compose.yml` is for production only**: code baked into the image (`docker/php/Dockerfile.prod`), required
  secrets from `.env.production`, port bound to `127.0.0.1`, scheduler service. Never use it for local development.
- `cp .env.example .env` once on a dev machine. It sets `COMPOSE_FILE=docker-compose-dev.yml`, so every plain
  `docker compose ...` command in this file targets dev. Without it, those commands read the production file and stop
  with `required variable DB_PASSWORD is missing`.
- The root `.env` is read by Compose only (`COMPOSE_FILE`, `APP_PORT`, `FORWARD_DB_PORT`). Keep each stack's changes in its own file.

### Requirements and conditions

- **Run PHP tooling in Docker.** `vendor/` needs PHP ≥ 8.4.1 and the host PHP is 8.3, so Pest, PHPStan,
  Rector and Testbench fail on the host. Only Pint works there. The `.claude/hooks/guard-bash.sh` hook blocks host runs.
- Docker + Docker Compose. Node 22+ for assets (CI uses 22).
- Dev ports: app `8088` (`APP_PORT` in the root `.env`), MySQL `3307` on the host (`FORWARD_DB_PORT`, because 3306/33060 are
  taken by a local MySQL). Inside the containers they stay `8000` and `3306`, so `DB_PORT` in `workbench/.env` must be `3306`.
- App config lives in **`workbench/.env`** (copy `workbench/.env.example`). Testbench ignores the root `.env`.
  The tests never read `workbench/.env` (`$loadEnvironmentVariables = false` in `tests/TestCase.php`).
- Demo seeders live in `workbench/database/seeders` (namespace `Database\Seeders`, entry `DatabaseSeeder`).
  The admin user model is `workbench/app/Models/User.php` (`AUTH_MODEL`).

### Localhost (development)

```bash
cp .env.example .env                          # once; plain `docker compose` now means docker-compose-dev.yml
cp workbench/.env.example workbench/.env      # once; set APP_KEY (base64:...) if empty
docker compose up -d --build                  # app (PHP 8.4, testbench serve) + mysql 8.4
docker compose exec -T app vendor/bin/testbench migrate:fresh --seed
npm install && npm run dev                    # only when changing admin CSS/JS (watch mode)
```

- Admin: http://localhost:8088/<prefix>/login, `admin@laravelshopper.dev` / `password` (all seeded customers use `password`).
  The prefix is `SHOPPER_PREFIX` in `workbench/.env` (default `cpanel`; `.env.testing` is only read by the tests).
  After changing it, `docker compose restart app` so the entrypoint links the admin images at the new prefix;
  open `/<prefix>/login` or `/<prefix>/dashboard` (the bare `/<prefix>` is shadowed by that image folder).
- The container entrypoint (`docker/php/entrypoint.sh`) runs on every start: `composer install` if needed,
  links `./uploads` into the skeleton's public disk, refreshes the skeleton `.env`, `package:discover`, `storage:link`, `filament:assets`, publishes TinyMCE
  (`vendor:publish --tag=shopper-cms-assets`), and links
  `packages/admin/public` to `/cpanel` (logo, favicons). Rebuild the image after editing the entrypoint.
- **The app container uses its own `vendor/`, kept in the `vendor` Docker volume**, not the host folder (loading it through the
  Windows bind mount made every request ~13x slower). So run Composer in the container, and do not expect host edits or
  host-side `composer` runs to reach the app. Skeleton logs and storage are in the volume too; read them with `docker compose exec`.
- The host `vendor/` is only a copy for the IDE, host Pint and `npm run build` (which imports from it). After Composer changes
  refresh it with `docker compose run --rm --no-deps -v .:/host --entrypoint sh app -c 'cp -a vendor/. /host/vendor/'`
  (about 3 minutes). `docker compose cp` stops at the first symlink on Windows and leaves a partial copy.
- Admin uploads are stored in `./uploads` on the host and served at `/storage/uploads/YYYY/MM/...`.
- Scheduled CMS commands (`shopper:cms:publish-scheduled`, `shopper:cms:empty-trash`) do not run automatically
  in dev; call them with `docker compose exec -T app vendor/bin/testbench <command>`.

Common commands:

```bash
docker compose exec -T app vendor/bin/pest [path]                     # tests (SQLite in memory)
docker compose exec -T app vendor/bin/pest --parallel                 # full suite
docker compose exec -T app vendor/bin/phpstan analyse --memory-limit=-1
docker compose exec -T app vendor/bin/testbench migrate               # new migrations only, keeps data
docker compose exec -T app vendor/bin/testbench route:list --path=cpanel
vendor/bin/pint [paths]                                               # host is fine
docker compose exec -T app composer dump-autoload                     # after adding classes/namespaces
```

### Troubleshooting (seen in this repo)

- A new package's config/addon is missing in the dev app: `docker compose exec -T app vendor/bin/testbench package:discover`.
- Tests fail with `MultipleRootElementsDetectedException`, `RootTagMissingFromViewException` or
  `Undefined variable $__blaze`: stale compiled views. Delete, inside the container,
  `vendor/orchestra/testbench-core/laravel/storage/framework/views/*.php`. The dev server compiles to
  `/tmp/shopper-views` (`VIEW_COMPILED_PATH`), so server and tests no longer clash.
- Browser console shows `filamentDropdown is not defined` (menus stuck open): Filament assets not published,
  run `docker compose restart app`.
- `UploadedFile::fake()` reports a mime type guessed from the file name; to test content checks use a real
  temp file with `new UploadedFile($path, 'name.png', test: true)`.
- Never run `docker compose down -v` unless you want to delete the MySQL data volume.

- `/usr/bin/env: 'bash\r'` when the app container starts: a shell script was checked out with CRLF. `.gitattributes`
  keeps `*.sh` on LF; convert the file with `sed -i 's/\r$//' <file>` and rebuild the image.

### Building assets (release)

- `npm run build` compiles Tailwind CSS and the esbuild bundles into `packages/admin/dist/shopper.{css,js}`, the
  sidebar bundle, and the CMS editor (`packages/cms/dist/tiny-editor.js` + a trimmed copy of TinyMCE in
  `packages/cms/dist/tinymce`). `dist/` is committed and is what installed apps serve, so rebuild and commit it whenever
  `packages/*/resources/{css,js}` change. Never edit `dist/` by hand.
- Before a release: `npm run build`, `composer test:lint`, `composer test:refactor`, PHPStan and the full Pest suite
  (all PHP commands inside Docker). Tags trigger `monorepo-split.yml`, which pushes each package to its own repository.

### Production with Docker Compose (`docker-compose.yml`)

```bash
cp .env.production.example .env.production    # once; fill in APP_KEY, DB_PASSWORD, DB_ROOT_PASSWORD, APP_URL, SHOPPER_PREFIX
docker compose --env-file .env.production up -d --build    # app + scheduler + mysql; migrations run on start
docker compose --env-file .env.production exec -T app vendor/bin/testbench db:seed --force --class='Shopper\Core\Database\Seeders\ShopperSeeder'    # once
docker compose --env-file .env.production exec -T app vendor/bin/testbench db:seed --force --class='Shopper\Database\Seeders\AuthTableSeeder'    # once
docker compose --env-file .env.production exec app vendor/bin/testbench shopper:user    # once; creates the first admin
```

- This stack runs the Testbench workbench on PHP's built-in server (`testbench serve`), with dev dependencies in the image.
  It listens on `127.0.0.1` only, so put a reverse proxy that terminates TLS in front of it. For a production-grade
  deployment, install Shopper in a real Laravel app (next section).
- Sessions, cache and logs live in the container and are lost when it is recreated; uploads and MySQL are in named volumes.
- The built-in server does not compress responses, so enable gzip or Brotli in the reverse proxy for JS and CSS.

### Production (installing Shopper in a real Laravel app)

Shopper ships as Composer packages, so production means a host Laravel 11/12 app:

1. PHP ≥ 8.3 with `intl`, `pdo_mysql`/`pdo_pgsql`, `gd`, `zip`, `exif`, `bcmath`, `soap` (shipping package). Raise `upload_max_filesize` and
   `post_max_size` above `shopper.media.uploads.max_size` (10 MB by default).
2. `composer require shopper/framework`, then `php artisan shopper:install` (publishes config and media-library
   migrations, runs `filament:assets`, `shopper:link`, migrations and the Shopper seeders).
   `packages/cms` is not on Packagist yet: until it is split and published, require it through a Composer
   `path` repository pointing at this monorepo.
3. Add `InteractsWithShopper` (and the store-authentication traits used by `workbench/app/Models/User.php`) to the
   `User` model, then `php artisan shopper:user` to create the first admin.
4. `php artisan storage:link` and a correct `APP_URL`, because upload URLs are built from the `public` disk
   (or set `SHOPPER_UPLOADS_DISK` to an S3 disk).
5. Add the Laravel scheduler cron (`* * * * * php artisan schedule:run`). Without it, scheduled posts are never
   published and the trash is never emptied.
6. On every deploy: `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`,
   `php artisan filament:assets`, `php artisan vendor:publish --tag=shopper-cms-assets --force` (TinyMCE, self-hosted
   under the GPL with `license_key: 'gpl'`), `php artisan optimize`.
7. CMS content reaches the storefront through the JSON API (`GET /api/cms/resolve?path=…`, `GET /api/cms/posts`;
   set `SHOPPER_CMS_FRONTEND_URL` to the storefront's base URL). For server-rendered pages instead, set
   `SHOPPER_CMS_ROUTES=true` (fallback route + views publishable with `vendor:publish --tag=shopper-cms-views`).
   Permalink structure and redirects are managed in the admin under Settings → Permalinks / Redirects.
8. SEO output (`packages/seo`): headless storefronts call `GET /api/seo?path=…` (pass `not_found=1` on 404s and
   `search=` on search pages) and proxy `/sitemap.xml`, `/sitemap-*.xml` and `/robots.txt` to `/api/seo/…`; set
   `SHOPPER_SEO_FRONTEND_URL` (falls back to the CMS frontend URL, then APP_URL). Blade apps use
   `<x-shopper-seo::head :for="$model" />`, `<x-shopper-seo::body-open />` and `<x-shopper-seo::body-close />`.
   The app serves `/sitemap.xml` and `/robots.txt` itself (`SHOPPER_SEO_ROUTES`): delete any static
   `public/robots.txt`. Storefront paths of products/categories/brands/collections are in `shopper.seo.paths`.
   With a real queue worker, `SHOPPER_SEO_SITEMAP_QUEUE=true` rebuilds sitemaps in the background after changes.
9. Never use the demo seeders (`workbench/database/seeders`) or the demo admin credentials in production.

## Development Commands

These Composer scripts call `pest`/`phpstan`/`rector`, so run them inside Docker
(`docker compose exec -T app composer test`); only `composer lint` works on the host.

### Testing

```bash
composer test                # Full suite (type-coverage + types + sqlite)
composer test:unit           # Pest with coverage (80% minimum) and parallel
composer test:sqlite         # Tests against SQLite
composer test:mysql          # Tests against MySQL
composer test:pgsql          # Tests against PostgreSQL
composer test:all-databases  # Run all database tests
composer test:types          # PHPStan level 5
composer test:type-coverage  # Type coverage (90% minimum)
composer test:refactor       # Rector dry-run
composer test:lint           # Laravel Pint check
```

### Code Style

```bash
composer lint       # Run Laravel Pint (fix)
composer refactor   # Run Rector (fix)
composer cs         # Run refactor + lint + prettier
```

### Assets

```bash
npm run dev       # Watch mode (CSS + JS in parallel)
npm run build     # Production build (CSS + JS)
npm run prettier  # Format Blade/CSS/JS files
```

### Build Tools

- **Tailwind CSS v4** with PostCSS for stylesheets
- **esbuild** for JavaScript bundling (configured in `scripts/build.js`)
- **Prettier** with Blade and Tailwind plugins (print width: 120, single quotes, no semicolons)

## Coding Standards

### Strict Types

Every PHP file MUST declare strict types:

```php
<?php

declare(strict_types=1);
```

### Class Element Order

Enforced by Pint (`ordered_class_elements`):

1. Traits (`use` statements)
2. Cases (for enums)
3. Constants
4. Properties
5. Constructor
6. Magic methods
7. Methods (public, then protected, then private)

### Naming Conventions

- Use descriptive variable names. Avoid abbreviations like `$e`, `$c`, `$prod`.
- Use backticks when referencing code in test names: `` it('can resolve `Product` from the container') ``
- Method references include parentheses: `` `getPrice()` method ``

### Code Style Rules

- **Arrow functions** preferred over closures when possible
- **Strict comparison** (`===`, `!==`) always
- **Void return types** required on methods that return nothing
- **No parentheses** on instantiation without arguments: `new Foo` not `new Foo()`
- **Multiline arguments** when a method has multiple parameters
- **MB string functions** preferred over standard string functions
- No superfluous `else`/`elseif` after early returns

### PHPDoc

Only add PHPDoc when it provides type information beyond what PHP can express natively:

```php
// Good: Adds generic type info not expressible in PHP
/** @return BelongsTo<Brand, $this> */
public function brand(): BelongsTo

// Bad: Redundant with native type
/** @return string */
public function name(): string
```

Use `@property-read` annotations on model classes for IDE support.

## Architecture Patterns

### Models

- Use `protected $guarded = []` (not `$fillable`)
- Use `casts()` method returning an array (not `$casts` property)
- Use `Attribute::get()` / `Attribute::set()` for accessors/mutators
- Table names resolved via `shopper_table('table_name')` helper
- Implement contracts from `Shopper\Core\Contracts`
- Compose focused traits: `HasMedia`, `HasSlug`, `HasPrices`, `HasDimensions`, etc.

```php
class Product extends Model implements ProductContract, Priceable, SpatieHasMedia
{
    use HasFactory;
    use HasMedia;
    use HasPrices;
    use HasSlug;

    protected $guarded = [];

    public function getTable(): string
    {
        return shopper_table('products');
    }

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'type' => ProductType::class,
        ];
    }
}
```

### Model Contract Pattern

Models are swappable via configuration. Use `HasModelContract` trait and resolve models through:

```php
resolve(ProductContract::class)  // Returns the configured model class
config('shopper.models.product') // Configuration key for the model
```

### Action Classes

- Marked as `final`
- Single responsibility with one public method (typically `execute()`)
- Injected dependencies via constructor

```php
final class SyncCollectionProductsAction
{
    public function execute(Collection $collection): int
    {
        // ...
    }
}
```

### Livewire Components

- Typed public properties
- `#[Computed]` attribute for lazy-loaded properties
- Return type `View` on `render()` methods
- Resolve dependencies via container: `resolve(ContractClass::class)`

### Enums

- Backed enums (`: string` or `: int`)
- Implement Filament interfaces: `HasLabel`, `HasColor`, `HasIcon`, `HasDescription`
- Use `ArrayableEnum` and `HasEnumStaticMethods` traits
- Labels use translation keys: `__('shopper-core::enum/product.virtual')`

```php
enum ProductType: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    use ArrayableEnum;
    use HasEnumStaticMethods;

    case External = 'external';
    case Standard = 'standard';
    case Virtual = 'virtual';
    case Variant = 'variant';

    public function getLabel(): string
    {
        return match ($this) {
            self::External => __('shopper-core::enum/product.external'),
            // ...
        };
    }
}
```

### Contracts/Interfaces

- Use generics in PHPDoc: `@template TModel of Model`
- Specify return types with full generic information
- Placed in `packages/core/src/Contracts/` or `packages/core/src/Models/Contracts/`

## Testing

- **Framework**: Pest PHP with Livewire and Laravel plugins
- Use `it()` syntax, not `test()`
- Use `beforeEach()` for shared setup
- Use arrow functions for callbacks
- Use Mockery for mocking
- Use `expect()` for assertions

```php
beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('can display the product list', function (): void {
    $this->actingAs($this->user);

    Livewire::test(ProductList::class)
        ->assertSuccessful();
});
```

## Static Analysis

- **PHPStan**: Level 5 with Larastan extension
- **Rector**: Type coverage level 8 (maximum)
- **Test coverage**: Minimum 80%
- **Type coverage**: Minimum 90%

## Dependencies

### Key PHP Packages

- `filament/filament` ^4.7 (headless admin panel components)
- `livewire/livewire` ^3.7
- `spatie/laravel-permission` ^6.24
- `spatie/laravel-media-library` ^11.5
- `stripe/stripe-php` ^16.0 (stripe package)
- `ivanmitrikeski/laravel-shipping` ^1.0 (shipping package)

### PHP Version

- Minimum: PHP 8.3
- Tested on: PHP 8.3 and 8.4

### Laravel Version

- Supports Laravel 11.x and 12.x

## File Organization

- Views: `packages/admin/resources/views/`
- CSS: `packages/admin/resources/css/`
- JS: `packages/admin/resources/js/` and `packages/sidebar/resources/js/`
- Translations: `packages/admin/resources/lang/` and `packages/core/resources/lang/`
- Config: `packages/*/config/`
- Migrations: `packages/core/database/migrations/`
- Routes: `packages/admin/routes/`
- Render hooks: `packages/admin/src/View/*RenderHook.php` (scoped by business domain)
- Payment drivers: `packages/payment/src/Drivers/`
- Shipping drivers: `packages/shipping/src/Drivers/`
- Cart pipelines: `packages/cart/src/Pipelines/`
- TypeScript types: `packages/types/`
