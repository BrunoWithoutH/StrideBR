# Preparação Web 1.0 — auditoria do working tree, 08/09/2026

> Registro histórico anterior à RC3. Para a validação desta publicação, consulte [RC3_RELEASE_VALIDATION_2026-09-08.md](RC3_RELEASE_VALIDATION_2026-09-08.md).

Esta lista é encaminhamento para Produto/UX e Código WEB. Não implementa migração nem altera a infraestrutura. O fluxo permanece localhost → Git/GitHub → Dokploy staging → validação → produção. A rodada de treino/mobile tem relatório próprio.

## A. Alterar no Código WEB antes do Dokploy

1. **Distribuição de produção:** `compose.yaml` fixa `STRIDEBR_APP_ENV=development`, monta o projeto inteiro, publica a porta do app e oferece senha de desenvolvimento. Criar configuração de deploy independente, com imagem construída do código, env obrigatório, sem senha default ou bind mount do código; rede interna/Traefik, PostgreSQL e uploads persistentes. Preservar o Compose local. O `Dockerfile` já copia o código e aponta Apache para `/public`, mas só habilita explicitamente `rewrite`: assegurar `headers` e validar os headers HTTP reais.
2. **Domínio antigo:** `public/.htaccess` força HTTPS especificamente para Alwaysdata; `public/robots.txt` anuncia sitemap no domínio antigo. `docs/FINAL_SETUP_CHECKLIST.md` ainda ensina deploy, URL e callbacks Alwaysdata. Atualizar documentação operacional e URLs canônicas para configuração por ambiente. Manter o redirecionamento legado como tarefa de Infra; não executar o script de deploy antigo.
3. **Ambientes e proxy:** `src/includes/app.php::stridebr_is_production()` só reconhece `production`. Staging precisa de política explícita de cookies/HTTPS, sem ser tratado como produção oficial. `stridebr_client_ip()` aceita qualquer `X-Real-IP` válido em produção; confiar em headers encaminhados somente quando `REMOTE_ADDR` pertence aos proxies configurados. Aplicar a mesma decisão à identificação de HTTPS. Testar spoofing e localhost. `stridebr_public_url()` ainda pode derivar host/protocolo de headers; em ambientes publicados usar apenas URL configurada e validada.
4. **E-mail:** `src/includes/auth.php::stridebr_send_mail()` usa `mail()`. O Dockerfile não instala/configura transporte de e-mail; a detecção atual de disponibilidade verifica apenas função e remetente. Preparar transporte configurável (SMTP ou sendmail/relay explicitamente configurado), falhas observáveis e teste de entrega. Reutilizar cadastro/reset atuais. A escolha/configuração das credenciais reais fica com Infra.
5. **Persistência e uploads:** persistir `/var/www/html/public/uploads/`, incluindo `avatars/`, `avatars/cache/`, `profile-banners/` e `events/`. Cache é regenerável, originais não. A regra atual de bloqueio está em `public/uploads/.htaccess`; um volume vazio/externo não pode remover essa proteção. Colocar a proibição de execução também na configuração Apache, negar extensões executáveis sem depender de caixa e impedir overrides no volume. Excluir uploads reais do contexto de build; `.dockerignore` atualmente não os exclui. Definir permissões de escrita e teste de rebuild com arquivo de fixture.
6. **Contrato env:** `.env.example` já cobre os serviços principais. Documentar também variáveis usadas no runtime e ausentes: `FITBIT_OAUTH_AUTHORIZE_URL`, `FITBIT_OAUTH_TOKEN_URL`, `POLAR_OAUTH_AUTHORIZE_URL`, `POLAR_OAUTH_TOKEN_URL`, `STRIDEBR_ENV_FILE`, `STRIDEBR_FEATURE_CACHE_TTL`, `STRIDEBR_SESSION_GUARD_TTL`. Distinguir overrides opcionais de requisitos. Encaminhar no Compose publicado Google OAuth, versão/build e demais variáveis do contrato, sem expor valores sensíveis. Acrescentar as variáveis do transporte de e-mail e proxies quando implementados.
7. **Release gate:** `scripts/release_check.sh` detecta `PENDENTE`, mas permite pular a checagem sem `psql`/credenciais. No caminho publicado, falhar se não for possível validar migrations; executar runner antes de servir tráfego. Não renomear/recriar migrations nem mudar RC.
8. **Segredos:** `.env` não aparece na lista de arquivos versionados e está excluído da imagem. Isso não constitui auditoria completa de histórico Git. Incluir verificação de segredos do conteúdo versionado e do artefato de build, com saída que não revele valores; configuração deve vir do ambiente.

## B. Já existe; preservar e validar, sem refazer

- PWA: `public/manifest.webmanifest` tem `display: standalone`, scope/start URL e ícones any/maskable. `src/includes/i18n.php` fornece metadados Apple e manifest; há service worker e identidade visual existente.
- GPS: `public/assets/js/gps-recorder.js` já tem Wake Lock, tratamento de release/visibility, bloqueio de controles e desbloqueio deliberado. Há fallback/comunicação e recuperação local no fluxo existente; não inventar background GPS nativo. Testes estáticos de PWA/GPS passaram nesta rodada; não equivalem a teste físico de instalação/GPS.
- Dockerfile já instala dependências Composer sem dev e usa `/public` como document root.
- PostgreSQL tem volume nomeado no Compose local. Runner já é idempotente e há seis migrations versionadas. Nesta execução, banco descartável aplicou as seis duas vezes; suíte PostgreSQL passou (11 grupos, 157 assertions). O teste de upgrade em banco descartável também passou, preservando timestamps das migrations anteriores. Upgrade de uma cópia do banco real continua tarefa posterior.
- Cookies têm HttpOnly, SameSite=Lax, strict mode e Secure em produção. Há CSRF e regras de headers/CSP existentes; ajustar proxy/staging e garantir execução real dessas regras, sem redesenhar segurança.
- `stridebr_app_url()` exige URL HTTPS em produção; callbacks das integrações esportivas usam essa função. Google possui configuração própria de redirect, que deve ser consistente com cada ambiente.
- `src/function/integrations.php` mantém Garmin com `implementation_ready=false`. Preservar indisponibilidade limpa sem credenciais e não ativar integrações nesta rodada.
- Anúncios/doações têm flags e variáveis; exemplos vêm desativados. Não ativar nem criar monetização adicional.
- Versão permanece `1.0.0-rc.2` nesta auditoria.

## C. Infra, somente quando houver VPS

- Revisar o artefato resultante e a configuração de deploy, criar Dokploy/Traefik, redes e volumes, secrets e permissões.
- DNS de `stridebr.com.br` e `staging.stridebr.com.br`, TLS, redirecionamento legado, política de HTTPS/HSTS sem duplicação conflitante.
- Definir proxies confiáveis e verificar IP real e spoofing pela rota externa.
- PostgreSQL persistente: backup, restore ensaiado, migração do banco real, estratégia de rollback e janela de corte.
- Migrar uploads preservando arquivos, ownership e bloqueio de execução.
- Configurar transporte e domínio de e-mail, SPF/DKIM/DMARC e testar cadastro, verificação e reset.
- Configurar Developer Portals/callbacks por ambiente após domínio operacional; serviços incompletos permanecem desativados.
- Validar PWA instalada em iPhone/iOS e Android, standalone, safe areas, teclado, Wake Lock/fallback, perda de GPS em background e recuperação local. WebKit automatizado não comprova iOS 15 físico.
- Smoke tests em staging e produção; fechar versão/tag 1.0.0 somente após validação final autorizada.

O prompt único de implementação do grupo A fica para depois da concordância com esta lista, conforme solicitado. Nenhuma mensagem foi enviada a outro chat por esta auditoria.
