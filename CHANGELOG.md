# Changelog
All notable changes to this project will be documented in this file.

## [Unreleased]
- Laravel 9, 10, 11, 12 and 13 support added.
- PHP 8.4 support: nullable parameters are now explicitly typed, and the CSV exporter passes `fputcsv()`'s escape character explicitly (no deprecation notices).
- Require `touhidurabir/laravel-model-uuid` `^1.2.0`, which contains the Laravel 12/13 boot fix.
- PHPUnit 10, 11 and 12 support for tests; test methods now use `#[Test]` attributes alongside the `@test` annotations.
- Code style formatted to PSR-12 with Laravel Pint (`composer format` / `composer lint`).
- Replaced Travis CI with GitHub Actions (PHP 8.1 × Laravel 8/9/10 and PHP 8.2–8.4 × Laravel 11/12/13, plus a code style check).
- Fixed the request body never being logged for form and JSON requests (only raw, non-arrayable content was stored). **Behaviour change:** the `request_body` column is now filled for those requests. Fields listed in the new `hidden_request_fields` config option (default `password`, `password_confirmation`, `current_password`) are left out; apps with a published config get those defaults too.
- Fixed the migration stub's error messages: the "table already exists" message used an undefined `$table` variable, and the "table not defined" message referred to `config/redirector.php`.

## [1.0.0] - 2021-12-26
- Initial release
