# Google Sign-in — contrato Web 1.0

Google é opcional e vem desativado. Configure `GOOGLE_OAUTH_ENABLED=1`, `GOOGLE_OAUTH_CLIENT_ID` e `GOOGLE_OAUTH_CLIENT_SECRET` somente após Infra cadastrar o cliente Web no Developer Portal.

Callback gerado exclusivamente por `STRIDEBR_APP_URL` + `/auth/google-callback.php`:

- Local: `http://localhost:8080/auth/google-callback.php`
- Staging: `https://staging.stridebr.com.br/auth/google-callback.php`
- Production: `https://stridebr.com.br/auth/google-callback.php`

`GOOGLE_OAUTH_REDIRECT_URI` é apenas uma verificação de compatibilidade opcional: se definido, precisa coincidir exatamente com a URL derivada. Não substitui APP_URL. Não colocar secrets no Git. A ativação, verificação do domínio e teste com contas reais ficam com Infra depois que os domínios estiverem operacionais. Ver [DEPLOY_DOKPLOY.md](DEPLOY_DOKPLOY.md).
