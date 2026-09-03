# Security

StrideBR is developed with the assumption that the source code is public. Security must not depend on hiding routes, SQL, role names, feature flags, or application structure.

Production secrets belong in environment variables and must never be committed to Git. This includes database credentials, mail credentials, API keys and private tokens.

State-changing requests use CSRF tokens. Database input must use prepared statements with bound parameters. Authorization is enforced on the server for every administrative action; hiding a button in the interface is never considered authorization.

Administrative roles follow `user < moderator < admin < owner`. Admins cannot manage peer or higher roles, and destructive account deletion is restricted to the owner. Blocking a user increments their session version so old sessions are invalidated on subsequent authenticated database requests.

Password reset and email verification tokens are random, single-use, time-limited and stored only as SHA-256 hashes. Passwords use PHP `password_hash()` / `password_verify()`, prefer Argon2id when available and are limited to 128 characters at application boundaries.

Security-relevant administrative actions are written to `admin_audit_log`.

If a vulnerability is found, report it privately to the project owner instead of testing it against other users' data.


Authentication, signup, password recovery, verification resend and authenticated feedback use persistent throttling stored in PostgreSQL. In production on alwaysdata, the application uses the reverse proxy-provided `X-Real-IP` as the client address and falls back to `REMOTE_ADDR` outside production. A successful login clears the account/e-mail bucket but does not reset the global IP failure bucket.

Production-generated authentication links require an explicit HTTPS `STRIDEBR_APP_URL`; the application must not trust the incoming Host header for reset or verification links in production. Production responses also enforce HTTPS and HSTS.

Trainer mode is a product capability, not an administrative role. Trainer-athlete access requires an accepted relationship and athlete-controlled permissions. A trainer cannot use that relationship to modify the athlete account, credentials, privacy settings or platform role.

GPS Web recordings are persisted locally in IndexedDB while in progress so a network failure does not destroy the session. Server save requests still require an authenticated session and CSRF validation, validate coordinate ranges and payload limits, and use a per-recording unique key to make retries idempotent. Browser GPS is treated as user-provided measurement data: the UI exposes its uncertainty and lets the owner review the final metrics instead of presenting Web geolocation as authoritative.

## Google OAuth credentials

- Keep `GOOGLE_OAUTH_CLIENT_SECRET` only in the server environment; never commit it to the repository or expose it to browser JavaScript.
- Keep `GOOGLE_OAUTH_ENABLED=0` whenever Google sign-in should be unavailable; disabling the feature hides the entry point and blocks the OAuth flow server-side without deleting credentials.
- Register the production callback URL exactly as `/auth/google-callback.php` on the HTTPS application origin.
- The login flow validates a session-bound OAuth `state` value before exchanging the authorization code.
- StrideBR requests only `openid email profile` for sign-in and does not persist Google access or refresh tokens.
