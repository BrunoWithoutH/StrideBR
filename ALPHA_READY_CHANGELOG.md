# Alpha readiness update

This revision prepares StrideBR for a small closed alpha without requiring public-release infrastructure to be enabled immediately.

## Added

- Versioned Terms of Use and Privacy Policy acceptance on signup
- Optional forced re-acceptance when legal-document versions change
- Closed-alpha feedback form and admin feedback queue
- Feature flags for registration, invite-only signup, email verification, required verification, password reset, feedback and legal re-acceptance
- Invite generation/revocation in the admin user area
- Email verification and password-reset token flows, disabled by default
- Admin user search, edit, block/unblock, role management and owner-only hard deletion
- Session-version invalidation after sensitive account changes or blocks
- Security headers through Apache `.htaccess`
- Admin audit logging for new sensitive actions
- Security and alpha-testing documentation

## Database

Apply, in order:

1. `src/database/migrations/20260815_product_foundation.sql`
2. `src/database/migrations/20260815_alpha_readiness.sql`

For an existing database, `./scripts/migrate_product.sh` now runs both migrations.

## Email

Email verification and password reset stay disabled until transactional email is configured. Set:

- `STRIDEBR_APP_URL`
- `STRIDEBR_MAIL_FROM`
- `STRIDEBR_MAIL_FROM_NAME`

Then enable the corresponding feature flags in `/admin/`.

## Removed

The old `pg_config-local-template.php` and `pg_config-web-template.php` files were removed. Runtime configuration is only through `src/config/pg_config.php` and environment variables.
