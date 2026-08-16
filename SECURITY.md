# Security

StrideBR is developed with the assumption that the source code is public. Security must not depend on hiding routes, SQL, role names, feature flags, or application structure.

Production secrets belong in environment variables and must never be committed to Git. This includes database credentials, mail credentials, API keys and private tokens.

State-changing requests use CSRF tokens. Database input must use prepared statements with bound parameters. Authorization is enforced on the server for every administrative action; hiding a button in the interface is never considered authorization.

Administrative roles follow `user < moderator < admin < owner`. Admins cannot manage peer or higher roles, and destructive account deletion is restricted to the owner. Blocking a user increments their session version so old sessions are invalidated on subsequent authenticated database requests.

Password reset and email verification tokens are random, single-use, time-limited and stored only as SHA-256 hashes. Passwords use PHP `password_hash()` / `password_verify()`.

Security-relevant administrative actions are written to `admin_audit_log`.

If a vulnerability is found during the closed alpha, report it privately to the project owner instead of testing it against other users' data.
