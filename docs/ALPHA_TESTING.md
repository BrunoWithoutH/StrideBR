# Closed alpha testing

The closed alpha is intended for a small group of known testers. Testers should expect interface changes and occasional data resets.

Recommended configuration before inviting testers:

- `registration.enabled=true`
- `registration.invite_only.enabled=true` if access should stay restricted
- `feedback.enabled=true`
- `feedback.anonymous.enabled=true` se você quiser permitir feedback sem vínculo com conta, IP ou navegador
- `access_logs.enabled=true` only if the privacy policy matches the chosen retention
- `auth.email_verification.enabled=false` until transactional mail is tested
- `auth.email_verification.required=false` until verification mail is known to work
- `auth.password_reset.enabled=false` until reset mail is known to work
- `legal.reaccept.required=false` until a new legal version needs explicit re-acceptance

Before enabling email flows, configure `STRIDEBR_APP_URL`, `STRIDEBR_MAIL_FROM` and `STRIDEBR_MAIL_FROM_NAME`, then test with a non-owner account.

Useful tester feedback includes the page, device/browser, what they tried to do, what they expected, what happened, and a screenshot when possible.

Feedback enviado anonimamente não aparece em "Meus envios", porque nenhuma referência ao usuário é armazenada nesse registro.
