<?php

declare(strict_types=1);
require_once __DIR__ . '/environment.php';

function stridebr_seo_origin(): string { return 'https://stridebr.com.br'; }

/** Explicit public allowlist, shared by headers, metadata and sitemap. */
function stridebr_seo_public_paths(): array
{
    return ['/', '/calendario.php', '/pages/about/about.php', '/pages/about/team.php',
        '/pages/about/contact.php', '/pages/about/support-project.php', '/pages/help/faq.php',
        '/pages/help/support.php', '/pages/legal/terms.php', '/pages/legal/privacy.php',
        '/pages/legal/cookies.php', '/pages/extras/roadmap.php', '/pages/extras/changelog.php',
        '/pages/extras/credits.php'];
}

function stridebr_seo_public_path(string $path): bool
{
    return in_array($path, stridebr_seo_public_paths(), true) || in_array($path, ['/index.php', '/evento.php'], true);
}

function stridebr_seo_canonical(string $path, ?string $eventSlug = null): ?string
{
    $path = (string) parse_url($path, PHP_URL_PATH);
    if ($path === '/index.php') $path = '/';
    if (!stridebr_seo_public_path($path)) return null;
    if ($path === '/evento.php') return $eventSlug !== null && $eventSlug !== '' ? stridebr_seo_origin() . $path . '?e=' . rawurlencode($eventSlug) : null;
    return stridebr_seo_origin() . $path;
}

function stridebr_seo_text(string $text, int $limit = 180): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    return function_exists('mb_substr') ? mb_substr($text, 0, $limit, 'UTF-8') : substr($text, 0, $limit);
}

function stridebr_seo_image(?string $path = null): array
{
    $fallback = ['url' => stridebr_seo_origin() . '/assets/img/branding/stridebr-og-20260909.png', 'width' => 1200, 'height' => 630, 'type' => 'image/png', 'alt' => 'StrideBR'];
    if (!$path) return $fallback;
    if (str_starts_with($path, stridebr_seo_origin() . '/')) $path = substr($path, strlen(stridebr_seo_origin()));
    // Only public local event images. Never fetch arbitrary external URLs or private uploads.
    if (!preg_match('#^/uploads/events/[a-zA-Z0-9_./-]+\.(?:png|jpe?g|webp)$#D', $path) || str_contains($path, '..')) return $fallback;
    $root = realpath(dirname(__DIR__, 2) . '/public/uploads/events');
    $file = realpath(dirname(__DIR__, 2) . '/public' . $path);
    if (!$root || !$file || !str_starts_with($file, $root . '/')) return $fallback;
    $size = @getimagesize($file);
    if (!$size || $size[0] < 600 || $size[1] < 315 || filesize($file) > 5 * 1024 * 1024) return $fallback;
    return ['url' => stridebr_seo_origin() . $path, 'width' => $size[0], 'height' => $size[1], 'type' => $size['mime'], 'alt' => ''];
}

function stridebr_seo_brand_data(?string $locale = null): array
{
    $base = stridebr_seo_origin() . '/';
    $locale ??= function_exists('stridebr_locale') ? stridebr_locale() : 'pt-BR';
    $language = $locale === 'en' ? 'en' : 'pt-BR';
    $description = $locale === 'en'
        ? 'StrideBR is a Brazilian platform for planning workouts, logging physical activities, and following sports progress.'
        : 'StrideBR é uma plataforma brasileira para planejar treinos, registrar atividades físicas e acompanhar evolução esportiva.';
    return ['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'Organization', '@id' => $base . '#organization', 'name' => 'StrideBR', 'alternateName' => 'Stride BR', 'url' => $base,
            'description' => $description, 'areaServed' => ['@type' => 'Country', 'name' => 'Brazil'],
            'logo' => stridebr_seo_origin() . '/assets/img/pwa/icon-512.png', 'sameAs' => [
                'https://www.instagram.com/stridebr.app/',
                'https://github.com/BrunoWithoutH/StrideBR',
            ]],
        ['@type' => 'WebSite', '@id' => $base . '#website', 'name' => 'StrideBR', 'alternateName' => 'Stride BR', 'url' => $base,
            'description' => $description, 'publisher' => ['@id' => $base . '#organization'], 'inLanguage' => $language],
    ]];
}

/** Render once in the initial HTML head. Callers supply only public presentation data. */
function stridebr_seo_head(array $page): string
{
    $title = stridebr_seo_text((string) ($page['title'] ?? 'StrideBR'), 120);
    if (!str_contains($title, 'StrideBR')) $title .= ' — StrideBR';
    $description = stridebr_seo_text((string) ($page['description'] ?? 'StrideBR é uma plataforma esportiva brasileira, livre e open source para planejar treinos, registrar atividades físicas e acompanhar evolução.'));
    $path = (string) ($page['path'] ?? parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
    $canonical = stridebr_seo_canonical($path, $page['event_slug'] ?? null);
    $indexable = $canonical !== null && !stridebr_robots_noindex() && empty($page['noindex']) && http_response_code() < 400;
    $image = stridebr_seo_image($page['image'] ?? null);
    $image['alt'] = stridebr_seo_text((string) ($page['image_alt'] ?? ($image['alt'] ?: $title)));
    $pageLocale = (string) ($page['locale'] ?? (function_exists('stridebr_locale') ? stridebr_locale() : 'pt-BR'));
    $locale = $pageLocale === 'en' ? 'en_US' : 'pt_BR';
    $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<title>' . $e($title) . "</title>\n";
    $meta = ['description' => $description, 'robots' => $indexable ? 'index, follow, max-image-preview:large' : 'noindex, nofollow, noarchive'];
    if ($canonical !== null && http_response_code() < 400) {
        $html .= '<link rel="canonical" href="' . $e($canonical) . '">' . "\n";
        $og = ['type' => $page['type'] ?? 'website', 'site_name' => 'StrideBR', 'title' => $title, 'description' => $description, 'url' => $canonical,
            'image' => $image['url'], 'image:width' => $image['width'], 'image:height' => $image['height'], 'image:type' => $image['type'], 'image:alt' => $image['alt'], 'locale' => $locale];
        foreach ($og as $name => $content) $html .= '<meta property="og:' . $e($name) . '" content="' . $e($content) . '">' . "\n";
        $meta += ['twitter:card' => 'summary_large_image', 'twitter:title' => $title, 'twitter:description' => $description, 'twitter:image' => $image['url'], 'twitter:image:alt' => $image['alt']];
    }
    if (in_array($path, ['/', '/index.php'], true)) {
        foreach (['STRIDEBR_GOOGLE_SITE_VERIFICATION' => 'google-site-verification', 'STRIDEBR_BING_SITE_VERIFICATION' => 'msvalidate.01'] as $env => $name) {
            $value = trim((string) getenv($env));
            if ($value !== '') $meta[$name] = $value;
        }
    }
    foreach ($meta as $name => $content) $html .= '<meta name="' . $e($name) . '" content="' . $e($content) . '">' . "\n";
    if (!empty($page['structured']) && $canonical !== null && http_response_code() < 400) {
        $html .= '<script type="application/ld+json">' . json_encode($page['structured'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) . "</script>\n";
    }
    return $html;
}

function stridebr_seo_sitemap_urls(?PDO $pdo = null): array
{
    if (stridebr_robots_noindex()) return [];
    $urls = array_map(static fn($path) => stridebr_seo_origin() . $path, stridebr_seo_public_paths());
    if ($pdo !== null) {
        require_once dirname(__DIR__) . '/function/eventos.php';
        if (eventosDisponiveis($pdo) && stridebr_feature_enabled($pdo, 'events.enabled', true)) {
            $stmt = $pdo->query("SELECT slug FROM stridebr.eventos_esportivos WHERE status IN ('publicado','cancelado') ORDER BY data_inicio, idevento LIMIT 45000");
            foreach ($stmt as $row) {
                if (trim((string) $row['slug']) !== '') $urls[] = stridebr_seo_canonical('/evento.php', (string) $row['slug']);
            }
        }
    }
    return array_values(array_unique($urls));
}
