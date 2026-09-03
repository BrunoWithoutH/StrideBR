# Performance

## Current optimizations

- Static assets use versioned URLs and long-lived immutable browser cache.
- Brotli is enabled when the Apache module is available; DEFLATE remains the fallback.
- Sporticon pictograms are referenced as external SVG masks instead of repeating complete SVG paths in the HTML.
- Activity history is loaded asynchronously in pages; the registration editor data is fetched only when the editor is opened.
- Logged-in session validation is cached briefly in the PHP session (`STRIDEBR_SESSION_GUARD_TTL`, default 60 seconds).
- Feature flags are cached briefly in the PHP session (`STRIDEBR_FEATURE_CACHE_TTL`, default 60 seconds).
- Leaflet is loaded only when a route editor or route detail actually needs a map.
- The global active-workout check is deferred until the browser is idle.
- Avatar uploads are cropped and compressed to WebP in the browser when supported and again on the server when GD/Imagick is available.
- Existing local avatars receive cached 96 px / 320 px WebP derivatives when image processing is available.
- List avatars use explicit dimensions, lazy loading and asynchronous decoding where appropriate.

## Database

Run `src/database/migrations/20260903_v1_rc.sql` to add indexes used by activity history/dashboard queries.

## Deploy

`scripts/deploy_alwaysdata.sh` preserves `public/uploads/` so deploys with `--delete` do not remove user avatars.

## Measuring

Use the browser Network tab to separate:

- TTFB: PHP + PostgreSQL/server time.
- Content Download: response/image/asset size.
- DOMContentLoaded / Load: browser parsing, CSS, JS and images.

On alwaysdata, HTTP logs are under `/home/stridebr/admin/logs/http/` and PHP logs under the account admin logs directory.

## Server timing

Responses expose a `Server-Timing` header with total request duration and, when available, PostgreSQL connection, session guard and activity-query timings. Requests slower than 2 seconds are also written to the PHP error log with the `[StrideBR slow request]` prefix.

## Performance pass 2026-08-29

This pass reduces work on the PHP hot path, database round-trips and idle browser work without changing the product flow.

- Request-time NanoID generation now uses the existing `random_bytes`-based StrideBR ID generator, so normal web requests no longer need the Composer autoloader just to create IDs.
- Read-heavy and long-running APIs release the PHP session lock after authentication/CSRF validation, allowing history, details, maps and file analysis requests from the same user to overlap instead of waiting on one another.
- The global quick-tools timer no longer wakes the browser every 100 ms while idle, and the alarm audio no longer preloads on every logged-in page.
- The global workout-session check keeps a short same-tab absence cache. Historical workout data is loaded in batches instead of two history queries per exercise.
- The dashboard combines the weekly summary and seven-day chart into one activity scan. Repeated goal aggregates are reused within the request, completion rows only update when the achieved value grows, and goal-form sport/exercise options are fetched only when the dialog is opened.
- Activity history fetches fewer columns and avoids an unnecessary model join. Off-screen activity rows, export rows and the footer use `content-visibility: auto` where supported.
- Import/export loads route-attachment targets only when a route/course needs them. Bulk file previews are limited to three simultaneous analyses, schema checks are briefly cached, and stale-preview cleanup runs at most once per hour per session.
- Repeated elevation lookups for the same sampled route use a seven-day server-side temporary cache.
- Asset version lookups are cached within each PHP request.

Apply `src/database/migrations/20260903_v1_rc.sql` after the activity-file-exchange migration. It adds focused indexes for activity units, completed workout history, exercise-history matching and imported-source lookups.

On alwaysdata, `STRIDEBR_DB_PORT=5433` can be used when the account is configured to connect through its PgBouncer endpoint. Keep `5432` when connecting directly to PostgreSQL or in local development.

## Reliability/performance pass 2026-08-29 v3

- PostgreSQL connections now use an explicit connection timeout. Server statements and lock waits also have bounded defaults so a stalled database operation fails instead of leaving an interface waiting indefinitely. The defaults can be overridden with `STRIDEBR_DB_CONNECT_TIMEOUT`, `STRIDEBR_DB_STATEMENT_TIMEOUT_MS` and `STRIDEBR_DB_LOCK_TIMEOUT_MS`.
- The activities history keeps a short same-tab snapshot in `sessionStorage`. A reload can render the last result immediately and revalidate it in the background instead of returning to an empty skeleton.
- Activity-history, detail, editor, elevation and map requests have browser-side deadlines. Safe GET requests retry once after transient failures; mutation requests are never automatically repeated.
- Activity history and the activity editor now load in parallel when the registration panel starts open.
- Loading Leaflet has an eight-second fail-safe so a CDN problem cannot keep route UI pending forever.
- Import/export, schedule, dashboard-goal and workout-session requests use the shared bounded network helper.
- Loading a detailed activity no longer performs one values query per activity unit; all unit values for the record are fetched in one query.
- Strength activities can store a workout code and muscular focus. Existing linked strength activities are backfilled from their schedule workout where those values exist, and activity-history cards prioritize that context over generic duration metrics.

Apply `src/database/migrations/20260903_v1_rc.sql` after `20260903_v1_rc.sql`.
