# Checklist final Web 1.0

Contrato técnico atual: [DEPLOY_DOKPLOY.md](DEPLOY_DOKPLOY.md). `.env.example` é a lista canônica de configuração. Não editar source no VPS.

- Localhost e testes verdes; release RC preservada.
- Imagens `app` e `migrations` do mesmo artefato; produção sem source bind mount.
- Configurar separadamente staging/production e APP_URL HTTPS; noindex no staging.
- Infra preenche DB, SMTP e proxies; serviços opcionais permanecem indisponíveis sem configuração.
- Volume de uploads e PostgreSQL persistente; backup e restore ensaiados antes do corte real.
- Migrations aplicadas por job antes de servir tráfego; readiness obrigatória.
- TLS, proxy e HSTS definidos por Infra; testar headers, cookies, IP e spoofing pela rota externa.
- Testar cadastro/verificação/reset com SMTP real, uploads após rebuild e PWA instalada iOS/Android.
- Registrar callbacks por ambiente nos Developer Portals; Garmin continua OFF.
- Ads e doações não devem ser ativados inadvertidamente.
- Smoke de staging antes de produção. Versão/tag final só após aprovação da validação Infra.

Nenhum VPS, domínio, secret real ou migração de dados foi configurado durante a preparação do repositório.
