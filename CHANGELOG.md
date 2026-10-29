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
- Require `laravel/framework` instead of only `illuminate/support`: the queue jobs use `Illuminate\Foundation\Bus\Dispatchable`, so the package never worked without it.
- **Behaviour change:** the request and response headers listed in the new `hidden_headers` config option are no longer stored. By default those are the headers that carry credentials (`authorization`, `cookie`, `set-cookie`, `php-auth-pw`, `x-csrf-token`, `x-xsrf-token`); before, every log kept working tokens and session cookies. Logs stored by earlier versions still contain them. Set the option to `[]` to store every header again.
- Fixed the Redis list getting stuck: when moving a full list to the table failed, the fallback also stored the current log in the table, although it was still in Redis. The next move then hit the unique `uuid` index, failed the whole batch and never drained the list, and `request-response-logger:redis-import` failed on it too. The fallback now only stores logs that never reached Redis, and the batch store skips the logs already in the table.
- Fixed the cleaning job's `WithoutOverlappingOfCleaningJob` middleware never locking: it called a `resolveName()` method the job does not have, and the error made it run every job unlocked. **Behaviour change:** when Redis is reachable and the cache store supports locks, only one cleaning job runs at a time; a queued one that finds the lock taken is released back to the queue for 10 seconds, a sync one is skipped. A job that throws now runs once and releases the lock (before, the middleware would have run it a second time). Without lock support the jobs run unlocked as before.
- Fixed a sync clean (`request-response-logger:clear` without `--on-job`) nesting one job per segment, which built a call stack as deep as the number of segments on a large table. It now deletes all the segments in a loop inside one job; with `--on-job`, each segment still gets its own queue job. A custom `jobs.clear` job that re-dispatches itself synchronously from its own `handle()` would now have its nested runs skipped by the lock.
- Fixed `RequestResponseLogger::factory()` throwing a class not found error: the model used `HasFactory` without a factory. It now has one, with a `marked()` state; a factory the app defines for the model, or a factory of a model extending it, is still used instead.
- Fixed `request-response-logger:export` failing on Windows without `--filename`: the default file name had `:` in it. **Behaviour change:** the default name is now like `2026-01-02_030405.csv` (`Y-m-d_His`) instead of `Fri_Jan_02_2026_03:04:05_GMT+0000.csv`.
