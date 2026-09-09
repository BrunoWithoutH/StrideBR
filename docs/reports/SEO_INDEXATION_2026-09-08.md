# SEO técnico e indexação — validação final RC5

## Implementação central

Metadata pública está centralizada em `src/includes/seo.php`. A rodada final preserva i18n, PWA e favicon existentes e não adiciona dependência `ext-dom` ao runtime de produção.

O teste `scripts/tests/test_seo.php` foi tornado portátil em PHP puro: não usa `DOMDocument`/`DOMXPath`, portanto `./scripts/test_static.sh` não exige `ext-dom` apenas para validar SEO.

## Home final

URL canônica:

```text
https://stridebr.com.br/
```

Metadata principal:

```text
title: StrideBR — Treinos, atividades e evolução esportiva
description: StrideBR é uma plataforma brasileira para registrar atividades físicas, organizar treinos e cronogramas, acompanhar metas e evolução em corrida e outros esportes.
og:type: website
og:site_name: StrideBR
og:url: https://stridebr.com.br/
og:image: https://stridebr.com.br/assets/img/branding/stridebr-og.png
twitter:card: summary_large_image
```

`<html lang>` voltou a usar `stridebr_html_lang()`. `og:locale` acompanha o locale (`pt_BR` ou `en_US`) e o `WebSite.inLanguage` usa `pt-BR`/`en` de forma coerente.

O favicon anterior `/assets/img/favicon/favicon.png` foi preservado. Manifest, `theme-color` e `apple-touch-icon` continuam centralizados em `stridebr_ui_boot_script()`; o PWA icon não substitui o favicon.

## Open Graph image

Arquivo reutilizado, sem gerar nova arte:

```text
public/assets/img/branding/stridebr-og.png
1200 × 630
72.306 bytes (~70,6 KiB)
```

## Structured data

A home emite um único JSON-LD com:

- `Organization`;
- `WebSite`.

Dados de marca preservados:

```text
name: StrideBR
alternateName: Stride BR
url: https://stridebr.com.br/
sameAs: https://www.instagram.com/stridebr.app/
```

Não foram inventados `SearchAction`, reviews, telefone, endereço, entidade jurídica ou outros dados inexistentes.

Eventos públicos usam `SportsEvent`, `EventScheduled`/`EventCancelled`, data, URL, descrição, localização/organizador quando existentes e lista de imagens públicas seguras. A rodada final preservou múltiplas imagens válidas do evento no structured data em vez de reduzi-las gratuitamente à primeira imagem.

## Sitemap

A allowlist estática contém 14 URLs canônicas:

```text
https://stridebr.com.br/
https://stridebr.com.br/calendario.php
https://stridebr.com.br/pages/about/about.php
https://stridebr.com.br/pages/about/team.php
https://stridebr.com.br/pages/about/contact.php
https://stridebr.com.br/pages/about/support-project.php
https://stridebr.com.br/pages/help/faq.php
https://stridebr.com.br/pages/help/support.php
https://stridebr.com.br/pages/legal/terms.php
https://stridebr.com.br/pages/legal/privacy.php
https://stridebr.com.br/pages/legal/cookies.php
https://stridebr.com.br/pages/extras/roadmap.php
https://stridebr.com.br/pages/extras/changelog.php
https://stridebr.com.br/pages/extras/credits.php
```

Com PostgreSQL disponível, entram adicionalmente eventos cujo estado está em `publicado` ou `cancelado`, porque esses são exatamente os estados acessíveis pelas consultas públicas atuais (`eventosListarPublicados()`/`eventosBuscarPublico()`). `rascunho` e `encerrado` não entram no sitemap. O teste de banco `scripts/tests/test_seo_database.php` cobre essa regra quando a suíte PostgreSQL está disponível.

Nunca entram URLs de `/user/`, `/u/`, `/admin/`, `/auth/`, `/api/`, `/function/`, `/home.php`, login/signup ou dados pessoais.

## robots.txt

Produção usa `public/robots.txt` como template público, mas `.htaccess` possui antes do atendimento do arquivo físico:

```apache
RewriteRule ^robots\.txt$ robots.php [L]
```

Assim `public/robots.php` continua sendo a autoridade da resposta. Em produção ele entrega o template público; em staging, `stridebr_robots_noindex()` é sempre verdadeiro e a resposta é exatamente:

```text
User-agent: *
Disallow: /
```

A lógica foi validada de duas formas. O fixture HTTP isolado passou 6 checks para verification tags, staging HTML, `robots.txt` e sitemap staging. Além disso, um Apache 2.4.68 descartável foi iniciado com o `public/` real, `AllowOverride All`, `mod_rewrite` e ambiente staging: um request HTTP real para `/robots.txt` respondeu 200, `Content-Type: text/plain`, `X-Robots-Tag: noindex, nofollow, noarchive` e corpo exato `User-agent: *\nDisallow: /\n`. Isso confirma que a RewriteRule vence o arquivo físico em staging.

## Páginas públicas

A centralização foi revisada em:

- `/`;
- `/calendario.php`;
- `/evento.php`;
- Sobre;
- Equipe;
- Contato;
- Apoie o projeto;
- FAQ;
- Suporte;
- Termos;
- Privacidade;
- Cookies;
- Roadmap;
- Changelog;
- Créditos.

Cada renderer público usa a combinação central de `title`, description, canonical, robots, Open Graph e Twitter Card. Tags antigas duplicadas foram retiradas dos renderers centralizados.

`/calendario.php?salvos=1` é `noindex` independentemente de o visitante estar autenticado, alinhando meta robots e `X-Robots-Tag`.

## Verification opcional

Suporte preservado em `.env.example`:

```env
STRIDEBR_GOOGLE_SITE_VERIFICATION=
STRIDEBR_BING_SITE_VERIFICATION=
```

Valores vazios não geram meta. Valores configurados geram `google-site-verification` e `msvalidate.01` com escape HTML seguro. Nenhum token foi hardcoded.

## Validação final executada

- `./scripts/test_static.sh`: PASS;
- `scripts/tests/test_seo.php`: 102 assertions dentro da suíte, sem `DOMDocument`;
- HTTP real das páginas públicas sem dependência de DB: 199 checks em 13 páginas + home EN;
- HTTP isolado de environment/robots/verification: 6 checks PASS;
- Apache 2.4 real em staging: `/robots.txt` PASS via `.htaccess`/`mod_rewrite`, mesmo com `public/robots.txt` físico;
- OG image verificada: 1200×630, 72.306 bytes;
- sitemap estático calculado em production: 14 URLs;
- `browser_seo.py` contra aplicação local real: **executado, não concluído**. A home respondeu corretamente, mas `/sitemap.xml` retornou 500 porque o PHP deste ambiente não possui `pdo_pgsql` (`could not find driver`). O teste não foi marcado como aprovado e nenhuma conexão externa foi tentada;
- suíte PostgreSQL/`test_seo_database.php`: não executada nesta sessão por ausência de Docker/PostgreSQL/`pdo_pgsql`.

## Ações externas depois do deploy

### Google Search Console

- adicionar/verificar `stridebr.com.br`;
- enviar `https://stridebr.com.br/sitemap.xml`;
- solicitar indexação da home.

### Bing Webmaster Tools

- adicionar/verificar o domínio;
- enviar o sitemap.

### DuckDuckGo

Nenhuma integração específica no StrideBR. A descoberta depende da indexação web/Bing e de sinais normais da web.

### Social preview

Depois do deploy, validar a URL pública real em WhatsApp, Discord, Telegram e Facebook para conferir cache/preview do Open Graph. Nenhum verification token foi inventado nesta rodada.

Nenhuma operação Git de escrita, tag ou deploy foi realizada.

## Revisão de segurança e limpeza

- nenhuma meta de verification contém token hardcoded;
- a origem canônica é fixa em `https://stridebr.com.br` e não confia em `Host`/query do request;
- URLs de imagem do structured data de evento só aceitam uploads públicos locais validados; URLs arbitrárias não são buscadas pelo servidor;
- variáveis mortas de canonical/page URL removidas somente onde a centralização tornou seu uso claramente obsoleto;
- `theme-color`, manifest, apple-touch-icon e favicon foram verificados sem substituir a identidade PWA existente;
- `git diff --check` passou no working tree final.
