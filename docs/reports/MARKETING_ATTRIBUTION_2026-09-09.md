# StrideBR Web — Marketing / Campanhas / Atribuição

Data: 2026-09-09

## Estado de entrada

A rodada foi continuada sobre o working tree recebido, sem reset. A referência informada no pedido era `9759fb8`, mas o pacote atual já carregava `.stridebr-build = 20260909-318bfcb`, correspondente a um estado posterior do produto. O estado atual foi preservado; não houve rollback para `9759fb8`.

Também foram incorporados os dois acabamentos pedidos junto desta rodada:

- os exports iOS Liquid Glass de alta resolução foram removidos do pacote; permanecem somente os três `apple-touch-icon` usados em produção (152/167/180), cerca de 46 KB no total;
- Cronogramas usa altura natural no mobile e scroll vertical interno limitado no desktop quando necessário.

Manifest e ícones Android permaneceram byte a byte iguais ao pacote de entrada.

## Arquitetura escolhida

A aquisição foi mantida first-party e separada do analytics de produto autenticado.

Fluxo:

`QR/UTM -> entrada pública -> cookie first-party -> signup_start -> signup_complete -> activation`

A atribuição prioriza first-touch conhecido. O navegador recebe somente um token aleatório; o banco guarda o SHA-256 desse token. Não há fingerprinting, Meta Pixel, Google Analytics, localização precisa ou armazenamento de IP/user-agent para Marketing.

Uma entrada direta não cria atribuição. Uma origem explícita posterior na mesma sessão pode criar o primeiro touch conhecido e ganha sua própria `landing_view`, sem duplicar refresh normal do mesmo touch.

## Migration

Nova migration isolada:

`src/database/migrations/20260909_marketing_attribution.sql`

Ela cria somente:

- `marketing_campanhas`;
- `marketing_placements`;
- `marketing_atribuicoes`;
- `marketing_eventos_aquisicao`.

Nenhuma tabela esportiva foi alterada.

A migration também cadastra como **planejada** a campanha:

`Frederico Westphalen — Lançamento local 2026` (`fw_local_2026`)

com os 15 placements solicitados. Como a campanha nasce planejada, `/r/...` só passa a funcionar depois de ela ser alterada para **Ativa** no Admin e estar dentro do período configurado.

## Eventos registrados

- `landing_view`: uma entrada/sessão por origem relevante; refresh não duplica;
- `signup_start`: abertura real do fluxo de cadastro, deduplicada por sessão;
- `signup_complete`: somente depois do `COMMIT` da conta;
- `activation`: primeira atividade realmente criada, deduplicada por usuário.

Não há evento de “cadastro concluído” baseado apenas em clique.

## Redirect curto

Formato:

`https://stridebr.com.br/r/<placement>`

Exemplo:

`https://stridebr.com.br/r/fw_if_ginasio`

O rewrite encaminha para `public/marketing-redirect.php`. O endpoint:

- aceita somente slug cadastrado;
- exige placement ativo e campanha ativa/no período;
- registra first-touch e `landing_view`;
- envia `X-Robots-Tag: noindex, nofollow, noarchive`;
- usa 302 para o destino interno;
- rejeita scheme/host, `//`, backslash literal/codificada e loops `/r/...`;
- retorna 404 para placement inexistente/inativo/não elegível.

## UTMs

São compreendidas:

- `utm_source`;
- `utm_medium`;
- `utm_campaign`;
- `utm_content`;
- `utm_term`.

Valores recebem trim, normalização de whitespace/controles e limite de tamanho. `utm_campaign` é comparada ao slug da campanha; `utm_content` pode resolver um placement da mesma campanha. SEO/canonical existente continua responsável por remover parâmetros de campanha da URL canônica.

## Persistência

Cookie:

`stridebr_acq`

Características:

- first-party;
- token aleatório de 18 bytes em hexadecimal;
- 30 dias;
- `HttpOnly`;
- `SameSite=Lax`;
- `Secure` quando o ambiente exige;
- somente hash SHA-256 é persistido no banco.

Ao concluir cadastro, a atribuição corrente é vinculada ao `idusuario`. A origem inicial conhecida não é sobrescrita por campanhas posteriores.

## Admin -> Marketing

Nova página:

`/admin/marketing.php`

Acesso restrito a `admin`.

Mostra:

- Entradas;
- atribuídas;
- diretas/desconhecidas;
- cadastro iniciado;
- cadastro concluído;
- ativados;
- visita -> cadastro;
- cadastro -> ativação;
- campanhas;
- detalhe da campanha;
- métricas por placement.

### Criar campanha

Admin -> Marketing -> `+ Nova campanha`.

Informar nome, código, tipo, status, período opcional e notas. O status não atribui tráfego por período; a atribuição continua exigindo QR/placement ou UTM explícita.

### Criar placement

Abrir a campanha -> `+ Novo placement`.

Informar nome, código, subtipo opcional, destino interno, ativo/inativo e observação. O código vira parte permanente do QR impresso.

### QR Code

Cada placement mostra a URL curta, `Copiar link` e `Baixar QR SVG`.

O QR é gerado localmente no navegador por um pequeno arquivo vendorizado, sem request externo nem nova dependência de runtime. O QR sempre aponta para `/r/<placement>`.

## Campanha JIFSul

Não foi seedada automaticamente. A arquitetura já aceita naturalmente:

- campanha `jifsul_2026_awareness`;
- placements `story_brand_01`, `story_product_01`, `reel_brand_01`, `reel_product_01`.

Eles podem ser criados pelo Admin quando os criativos estiverem definidos.

## Privacidade / Cookies

Foram atualizadas somente as páginas legais necessárias para descrever a coleta nova.

A implementação não adiciona:

- fingerprinting;
- Meta Pixel / Conversions API;
- Google Analytics;
- Hotjar/Clarity;
- geolocalização;
- IP bruto para Marketing;
- user-agent completo para Marketing;
- envio de dados esportivos a sistemas de anúncios.

Não foi criada CMP adicional. O cookie é estritamente first-party para atribuição do próprio produto. Eventual enquadramento jurídico/consentimento deve continuar sendo revisto conforme a política de lançamento, sem alterar o desenho técnico desta rodada.

## Cronogramas / rolagem

### Mobile <= 760 px

- Semana: altura natural; sem viewport vertical artificial; página é dona da rolagem vertical; eixo horizontal continua rolável.
- Mês: altura natural; página é dona da rolagem vertical; overflow horizontal continua disponível quando necessário.

### Desktop > 760 px

- Semana: encaixa as sete colunas na largura disponível e usa viewport vertical limitada quando as 24h excedem a altura útil.
- Mês: recebe altura máxima responsiva e scroll interno somente quando as semanas excedem essa área.

Assim o mobile não vira “site dentro de site”, enquanto o desktop continua compacto.

## Ícone iOS reduzido

Foram removidos do pacote os 15 exports 256-4096 px que somavam aproximadamente 11 MB. Permanecem somente:

- `public/assets/img/branding/app-icons/ios/apple-touch-icon-152x152.png`;
- `public/assets/img/branding/app-icons/ios/apple-touch-icon-167x167.png`;
- `public/assets/img/branding/app-icons/ios/apple-touch-icon-180x180.png`.

`manifest.webmanifest`, `icon-192.png`, `icon-512.png` e `maskable-512.png` mantiveram os mesmos SHA-256 do pacote de entrada.

## Arquivos de implementação desta rodada

Principais arquivos novos/alterados:

- `src/database/migrations/20260909_marketing_attribution.sql`;
- `src/function/marketing.php`;
- `public/marketing-redirect.php`;
- `public/api/marketing-entry.php`;
- `public/admin/marketing.php`;
- `public/assets/js/marketing.js`;
- `public/assets/js/admin-marketing.js`;
- `public/assets/vendor/qrcode-generator.js`;
- `public/assets/vendor/qrcode-generator.LICENSE.txt`;
- `public/.htaccess`;
- `public/signup.php`;
- `src/function/atividade_modelo.php`;
- `src/includes/admin.php`;
- `src/layout/footer.php`;
- `src/i18n/pt-BR.php`;
- `src/i18n/en.php`;
- `public/pages/legal/privacy.php`;
- `public/pages/legal/cookies.php`;
- `public/assets/css/admin.css`;
- `public/assets/css/cronogramas.css`;
- `public/assets/js/cronogramas.js`;
- testes estáticos/DB/browser correspondentes.

## Testes

### Suíte estática final

`./scripts/test_static.sh`: PASS.

Destaques desta rodada:

- Marketing attribution static: 72 assertions;
- PWA foundation static: 17 assertions;
- Mobile/PWA schedule polish static: 31 assertions;
- SEO metadata: 111 assertions;
- migrations/theme: 15 assertions;
- i18n: 3253 assertions / 3175 keys por locale.

### Browser

`browser_schedule_mobile_pwa.py`: PASS, 58 checks / 7 screenshots.

Inclui mobile com altura natural e teste explícito de desktop Semana/Mês com viewport interna limitada.

`browser_marketing_admin.py`: PASS, 12 checks / 3 screenshots.

Cobertura representativa:

- Admin Marketing 1440 dark;
- Admin Marketing 390 dark;
- Admin Marketing 390 light;
- QR gerado localmente;
- sem body overflow mobile.

### `test_all.sh`

Executado no working tree final. Toda a etapa estática/unitária passou novamente. O script encerrou com exit 2 porque o ambiente não possui Docker Compose, portanto a suíte PostgreSQL não foi executada nesta sessão.

O teste PostgreSQL `scripts/tests/test_marketing_attribution.php` foi adicionado e fica pronto para execução em ambiente com Docker/PostgreSQL.

### Diff / segurança

Comparação contra o pacote iOS imediatamente anterior:

- `git diff --no-index --check`: sem whitespace errors;
- nenhum `.env` privado será empacotado;
- nenhum token/chave de campanha de terceiro foi adicionado.

## Limitações e ações antes de usar campanhas reais

1. Aplicar `20260909_marketing_attribution.sql` em produção.
2. Abrir Admin -> Marketing e mudar `fw_local_2026` de Planejada para Ativa quando os QR Codes forem publicados.
3. Testar um QR real em aparelho e confirmar `/r/<placement> -> /`.
4. Fazer um cadastro de teste vindo de QR/UTM e confirmar no Admin: entrada -> signup -> ativação.
5. Rodar `./scripts/test_all.sh` em ambiente com Docker/PostgreSQL antes da próxima promoção de release.
6. Validar os QR impressos depois de definidos tamanho/contraste dos cartazes.

## Conscientemente adiado

- Meta Pixel / CAPI;
- Google Analytics;
- retargeting;
- cohort/funil configurável;
- CRM;
- mapas/geofencing;
- gráficos avançados;
- campanha JIFSul pré-cadastrada antes de os criativos serem definidos.

Nenhum commit, push, tag ou deploy foi realizado.
