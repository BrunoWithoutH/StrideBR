# Closed alpha testing

The closed alpha is intended for a small group of known testers. Testers should expect interface changes and occasional data resets.

Recommended configuration before inviting testers:

- `registration.enabled=true`
- `registration.invite_only.enabled=false` quando o cadastro aberto for intencional e a distribuição da alpha for controlada por link/Close Friends; use `true` se quiser exigir convite
- `feedback.enabled=true`
- `trainer.enabled=true` para testar vínculo treinador-atleta e prescrições
- `monthly_calendar.enabled=true` para testar a agenda mensal
- `feedback.anonymous.enabled=true` se você quiser permitir feedback sem vínculo com conta, IP ou navegador
- `access_logs.enabled=true` only if the privacy policy matches the chosen retention
- `auth.email_verification.enabled=false` until transactional mail is tested
- `auth.email_verification.required=false` until verification mail is known to work
- `auth.password_reset.enabled=false` until reset mail is known to work
- `legal.reaccept.required=false` until a new legal version needs explicit re-acceptance

Before enabling email flows, configure `STRIDEBR_APP_URL` with an explicit HTTPS URL in production, plus `STRIDEBR_MAIL_FROM` and `STRIDEBR_MAIL_FROM_NAME`, then test with a non-owner account.

Useful tester feedback includes the page, device/browser, what they tried to do, what they expected, what happened, and a screenshot when possible.

Feedback enviado anonimamente não aparece em "Meus envios", porque nenhuma referência ao usuário é armazenada nesse registro.


Trainer testing should use two ordinary accounts. Confirm that the athlete must accept the relationship, can revoke each permission, can end the relationship immediately, and that the trainer never gains access to account credentials, e-mail changes, privacy settings or administrative controls.

## Checks antes de liberar uma build

Rode a suíte completa em ambiente local com Docker:

```bash
./scripts/test_all.sh
```

Ela cria um banco de teste isolado e destrutível. Os testes PHP também recusam, por padrão, bancos cujo nome não contenha `test` ou `alpha`, para reduzir o risco de executar limpeza de fixtures em um banco real.

Sem Docker, rode os checks que não dependem de PostgreSQL:

```bash
./scripts/test_static.sh
```

A checklist completa de smoke test, mobile, produção e backup fica em [`ALPHA_RELEASE_CHECKLIST.md`](archive/ALPHA_RELEASE_CHECKLIST.md).
