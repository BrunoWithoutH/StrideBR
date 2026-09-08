# Monetização do StrideBR

A infraestrutura de publicidade do StrideBR nasce desligada. Nenhum publisher, slot ou anúncio real faz parte do repositório. A aplicação registra apenas **placements visuais**; ela não envia identificadores, dados esportivos ou parâmetros de targeting para o renderer de publicidade.

## Configuração

```text
STRIDEBR_ADS_ENABLED=0
STRIDEBR_ADS_AUTHENTICATED_ENABLED=0
STRIDEBR_ADS_PLACEHOLDERS=0
STRIDEBR_ADS_DEV_PREVIEW=0
STRIDEBR_ADSENSE_CLIENT=
STRIDEBR_ADSENSE_SLOT_HOME_AFTER_WEEK=
STRIDEBR_ADSENSE_SLOT_LIBRARY_END=
STRIDEBR_ADSENSE_SLOT_PROFILE_END=
STRIDEBR_ADSENSE_SLOT_EQUIPMENT_END=
STRIDEBR_ADSENSE_SLOT_EVENTS_LIST_END=
STRIDEBR_ADSENSE_SLOT_EVENT_DETAIL_END=
STRIDEBR_ADSENSE_SLOT_PUBLIC_CONTENT_END=
```

`STRIDEBR_ADS_ENABLED` é a chave mestre. `STRIDEBR_ADS_AUTHENTICATED_ENABLED` libera separadamente os placements autenticados. Slot vazio desativa somente aquele placement. Client ausente ou inválido faz a publicidade real falhar fechada.

`STRIDEBR_ADS_DEV_PREVIEW=1`, fora de produção, mostra o envelope final dos placements registrados sem carregar AdSense. `STRIDEBR_ADS_PLACEHOLDERS=1` permanece como preview técnico local equivalente; ambos devem ficar em `0` no ambiente normal.

O loader usa `STRIDEBR_ENV_FILE` quando definido e, caso contrário, `<project-root>/.env`. Variáveis já definidas pelo processo/servidor têm prioridade sobre o arquivo, porque o loader não sobrescreve valores existentes.

## Registry de placements

A fonte de verdade fica em `src/function/monetization.php`.

| Placement | Slot | Página | Autenticado |
| --- | --- | --- | --- |
| `home-after-week` | `STRIDEBR_ADSENSE_SLOT_HOME_AFTER_WEEK` | `/home.php`, depois de **Esta semana** | sim |
| `library-end` | `STRIDEBR_ADSENSE_SLOT_LIBRARY_END` | `/user/biblioteca.php`, depois da listagem/tab principal | sim |
| `profile-end` | `STRIDEBR_ADSENSE_SLOT_PROFILE_END` | `/user/perfil.php`, no fim do conteúdo principal | sim |
| `equipment-end` | `STRIDEBR_ADSENSE_SLOT_EQUIPMENT_END` | `/user/equipamentos.php`, depois do formulário + Meus equipamentos | sim |
| `events-list-end` | `STRIDEBR_ADSENSE_SLOT_EVENTS_LIST_END` | `/calendario.php`, depois da listagem/estado vazio | não |
| `event-detail-end` | `STRIDEBR_ADSENSE_SLOT_EVENT_DETAIL_END` | `/evento.php`, depois de descrição, informações, galeria e fontes | não |
| `public-content-end` | `STRIDEBR_ADSENSE_SLOT_PUBLIC_CONTENT_END` | páginas públicas de leitura allowlisted | não |

`public-content-end` permite somente:

- `/pages/help/faq.php`
- `/pages/extras/roadmap.php`
- `/pages/extras/changelog.php`
- `/pages/extras/credits.php`
- `/pages/about/about.php`

`/pages/about/team.php` não entra na primeira versão porque o conteúdo atual é curto para esse placement. A landing `/` também permanece limpa.

## Páginas proibidas

Não recebem publicidade os fluxos de autenticação, Feedback, Admin, APIs/functions/errors e os fluxos operacionais/sensíveis de atividades, edição, GPS, import/export, comparação, cronogramas, agenda, treinador, settings/account, export/delete de conta, Progresso, Metas, Notificações e Amigos. A denylist defensiva fica no registry, e essas páginas também não declaram placement.

A arquitetura inicial aceita **no máximo um placement por request/página**.

## Zero footprint quando desligado

Com todas as flags em `0`, não há wrapper, `<ins>`, label, margem publicitária, JSON de configuração, `ads.js`, script Google ou UI de consentimento. O footer apenas pergunta ao runtime se um placement real foi registrado; sem registro, não imprime nada.

## Preview local

Exemplo com o servidor embutido do PHP:

```bash
STRIDEBR_ADS_ENABLED=0 \
STRIDEBR_ADS_AUTHENTICATED_ENABLED=0 \
STRIDEBR_ADS_DEV_PREVIEW=1 \
STRIDEBR_ADS_PLACEHOLDERS=0 \
php -S 127.0.0.1:8088 -t public
```

O preview mostra `Publicidade · Preview`/`Advertising · Preview`, usa os mesmos envelopes responsivos e **não cria `<ins class="adsbygoogle">`, configuração do provider ou request para Google**. Placements autenticados podem ser inspecionados em preview mesmo com `STRIDEBR_ADS_AUTHENTICATED_ENABLED=0`.

## Anúncios reais, CLS e no-fill

Quando um placement real é preparado:

- usa `data-ad-format="auto"`;
- usa `data-full-width-responsive="true"`;
- reserva aproximadamente 90 px em desktop/tablet e 100 px em mobile;
- o provider é carregado no máximo uma vez;
- cada slot recebe no máximo um `adsbygoogle.push({})`;
- `data-ad-status="unfilled"` recolhe o wrapper inteiro;
- falha de carregamento/inicialização também recolhe o placement.

Não existem sticky ads, overlays, bottom ads ou rails.

A CSP em `public/.htaccess` já reserva as origens principais necessárias ao provider. Antes da ativação real, valide novamente a política contra a documentação e os requests do AdSense vigente, porque domínios/requisitos do fornecedor podem mudar.

## Verificação do site sem ligar anúncios

`public/index.php` pode emitir:

```html
<meta name="google-adsense-account" content="ca-pub-...">
```

quando `STRIDEBR_ADSENSE_CLIENT` é válido. Isso independe de `STRIDEBR_ADS_ENABLED` e não carrega o script Google. Assim é possível cadastrar/verificar o domínio mantendo publicidade desligada.

## ads.txt

`public/ads.txt.example` contém somente o formato de exemplo:

```text
google.com, pub-XXXXXXXXXXXXXXXX, DIRECT, f08c47fec0942fa0
```

Na ativação real, crie `public/ads.txt` com a linha fornecida pelo próprio AdSense. Não use o placeholder do exemplo.

## Consentimento/CMP

A implementação local antiga baseada em `stridebr.ads.consent`/`ad-consent` não é tratada como CMP legal do AdSense e foi desacoplada do runtime de ads. Nenhuma CMP nova é implementada aqui.

Antes de colocar `STRIDEBR_ADS_ENABLED=1`, configure/revise a solução de consentimento/CMP exigida pelo Google e pelas regiões atendidas. As políticas de Cookies e Privacidade devem refletir a solução realmente publicada.

## Dados do usuário

O placement conhece apenas sua posição visual. Não recebe `idusuario`, username, idade, sexo, localização, atividade, modalidade, distância, pace, frequência cardíaca, treino, rota, equipamento, meta ou treinador. Dados esportivos ou de integrações não são construídos como targeting.

## Ativação pública

Depois de VPS, domínio, HTTPS, aprovação e consentimento/CMP:

1. configure `STRIDEBR_ADSENSE_CLIENT`;
2. publique `public/ads.txt` real;
3. preencha somente os slots públicos desejados (`EVENTS_LIST_END`, `EVENT_DETAIL_END`, `PUBLIC_CONTENT_END`);
4. mantenha `STRIDEBR_ADS_AUTHENTICATED_ENABLED=0`;
5. valide CSP, no-fill e layout;
6. defina `STRIDEBR_ADS_ENABLED=1`.

Slots vazios continuam silenciosamente desligados.

## Ativação em páginas autenticadas

Somente depois de domínio aprovado, política/consentimento revisados e crawler login configurado quando necessário:

1. preencha os slots autenticados desejados;
2. valide a experiência autenticada/crawler;
3. defina `STRIDEBR_ADS_AUTHENTICATED_ENABLED=1`.

Home, Library, Profile e Equipment não podem preparar anúncio real enquanto essa flag estiver em `0`.

## Crawler login

Se o fornecedor precisar revisar conteúdo autenticado, configure o acesso de crawler conforme a documentação oficial vigente antes de habilitar ads autenticados. Não crie bypass de autenticação no StrideBR.

## Status

```bash
php scripts/ads_status.php
```

O comando mostra flags, presença do client, readiness do meta, configuração de cada placement e presença de `ads.txt`, sem imprimir publisher ou slot IDs e sem fazer request externo.

## Checklist de ativação no VPS

- [ ] VPS e domínio definitivos
- [ ] HTTPS válido
- [ ] domínio cadastrado/aprovado no AdSense
- [ ] `STRIDEBR_ADSENSE_CLIENT` configurado
- [ ] meta de verificação conferido
- [ ] `public/ads.txt` criado com a linha oficial
- [ ] CMP/consentimento aplicável configurado e revisado
- [ ] CSP validada com a documentação vigente do provider
- [ ] slots públicos desejados preenchidos
- [ ] preview visual conferido
- [ ] `STRIDEBR_ADS_ENABLED=1` somente depois dos itens anteriores
- [ ] crawler login configurado, se necessário
- [ ] slots autenticados revisados
- [ ] `STRIDEBR_ADS_AUTHENTICATED_ENABLED=1` somente quando a etapa autenticada estiver pronta
- [ ] `STRIDEBR_ADS_DEV_PREVIEW=0` e `STRIDEBR_ADS_PLACEHOLDERS=0` em produção

## Doações

```text
STRIDEBR_DONATION_ENABLED=0
STRIDEBR_DONATION_PIX_KEY=
STRIDEBR_DONATION_PIX_NAME=
STRIDEBR_DONATION_URL=
```

A página `/pages/about/support-project.php` só publica meios de apoio quando `STRIDEBR_DONATION_ENABLED=1` e existe uma chave PIX ou URL HTTPS configurada. Doações são voluntárias e não alteram recursos, visibilidade, moderação ou acesso a dados.
