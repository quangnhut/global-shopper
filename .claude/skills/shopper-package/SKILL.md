---
name: shopper-package
description: How to add a new package to the Shopper monorepo (packages/<name>) so it autoloads, is discovered by Laravel/Testbench, is analysed by PHPStan, tested by Pest and split by CI. Use before creating packages/cms or packages/seo.
---

# Add a package to the monorepo

1. **Skeleton**: `packages/<name>/{composer.json,config,database/migrations,resources/{views,lang},routes,src}`.
   `composer.json` mirrors `packages/stripe/composer.json`: name `shopper/<name>`, MIT,
   `"php": "^8.3"`, require `shopper/core: "*"` (and `shopper/framework: "*"` if it uses the admin),
   PSR-4 `Shopper\\<Name>\\` → `src/`, and `extra.laravel.providers`.
2. **Service provider** `src/<Name>ServiceProvider.php`: merge config under `shopper.<name>`,
   `loadMigrationsFrom`, `loadViewsFrom($path, 'shopper-<name>')`,
   `loadTranslationsFrom($path, 'shopper-<name>')`, and register the admin addon if it has UI.
3. **Root composer.json** (this repo is one Composer project):
   - `autoload.psr-4`: add `"Shopper\\<Name>\\": "packages/<name>/src"` (and to the `Shopper\\` array if needed).
   - `replace`: add `"shopper/<name>": "self.version"`.
   - `extra.laravel.providers`: add the provider.
   - Then `composer dump-autoload`.
4. **Monorepo split**: in `.github/workflows/monorepo-split.yml` add the package to `matrix.package`
   and an `include` entry mapping it to its split repository.
5. **Quality tools**: PHPStan already scans `packages/`. Add `packages/<name>/src` to `<source>` in
   `phpunit.xml.dist` and the `phpunit.*.xml` files for coverage.
6. **Tests**: `tests/<Name>/` with its own `TestCase` extending `Tests\TestCase` (or `Tests\Admin\TestCase`
   for admin UI). Each test file opts in with `uses(Tests\<Name>\TestCase::class);` (`tests/Pest.php` maps nothing).
7. **Assets** (JS/CSS such as TinyMCE): build with `scripts/build.js` (esbuild) into `packages/<name>/dist`
   and expose them through the addon `addScripts()/addStyles()`. Never edit `dist/` by hand.
8. Verify: `docker compose exec -T app vendor/bin/testbench package:discover` lists the provider and
   `docker compose exec -T app vendor/bin/pest tests/<Name>` passes.
