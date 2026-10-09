# P1-UAT-001 - Deployment Cache Failure Evidence

## Observation

The user supplied a sanitized deployment log captured at `2026-10-09
07:58:22` for `GET /`. CodeIgniter raised `CacheException` from
`FileHandler.php:66` because it could not write:

`/home/u493345855/domains/monmin.net/public_html/api/writable/cache/`

The later `Undefined array key "args"` in `BaseExceptionHandler.php:106` is
secondary exception-rendering fallout and is not the root cause.

## Repository correlation

`backend/app/Config/Cache.php` uses the `file` handler with
`WRITEPATH . 'cache/'`. CodeIgniter checks the directory in the
`FileHandler` constructor before routing. The configured `dummy` backup is not
reached when the primary constructor itself throws `CacheException`.

## Required operator verification

1. Confirm the deployed `WRITEPATH` resolves to the expected release path.
2. Confirm `writable/cache/` exists and is owned/grouped for the PHP-FPM user.
3. As that runtime user, create and delete a temporary file in the directory.
4. Correct permissions using the host convention; do not use world-writable
   permissions.
5. Re-test `/` and `/api/v1/health`; clear only stale cache files if needed.

No remote shell or host filesystem mutation was available in this task. The
follow-up browser recheck proves the SPA can now authenticate and navigate, but
does not independently prove host-level ownership/permission state. No code
fallback change was made.
