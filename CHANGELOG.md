# Changelog
All notable changes to this project will be documented in this file.

## [Unreleased]


## [1.0.0] - 2021-12-26
- Initial release

## [1.1.0] - 2026-10-29
- Laravel 9, 10, 11, 12 and 13 support added.
- PHP 8.4 support: nullable parameters are now explicitly typed, and the CSV exporter passes `fputcsv()`'s escape character explicitly (no deprecation notices).
- Require `touhidurabir/laravel-model-uuid` `^1.2.0`, which contains the Laravel 12/13 boot fix.
- PHPUnit 10, 11 and 12 support for tests; test methods now use `#[Test]` attributes alongside the `@test` annotations.
- Code style formatted to PSR-12 with Laravel Pint (`composer format` / `composer lint`).
- Replaced Travis CI with GitHub Actions (PHP 8.1 × Laravel 8/9/10 and PHP 8.2–8.4 × Laravel 11/12/13, plus a code style check).
- Fixed the request body never being logged for form and JSON requests (only raw, non-arrayable content was stored). **Behaviour change:** the `request_body` column is now filled for those requests. Fields listed in the new `hidden_request_fields` config option (default `password`, `password_confirmation`, `current_password`) are left out; apps with a published config get those defaults too.
- Fixed the migration stub's error messages: the "table already exists" message used an undefined `$table` variable, and the "table not defined" message referred to `config/redirector.php`.
- Fixed the Redis storage (`store_on_redis`) and the `request-response-logger:redis-import` command: the `redis_configs` array was passed to Laravel as a connection name, which throws a `TypeError`. **Behaviour change:** with the default `fallback_on_redis_failure` the logs used to be written straight to the database; they now go to Redis as configured. `redis_configs` may also be the name of a connection in `config/database.php`.
- Fixed the logs stored in Redis reaching the table without `created_at`/`updated_at`, so `--keep-till-last` cleaning and `--of-last` exporting skipped them. They now keep the time of the request.
- Fixed `request-response-logger:export --of-last=N` exporting the records older than N hours instead of the ones of the last N hours. **Behaviour change** for anyone who relied on the inverted filter.
- README: documented middleware registration in `bootstrap/app.php` and command scheduling in `routes/console.php` for Laravel 11+.
