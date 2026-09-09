<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};

$decode = static fn(string $value): string => html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$attributes = static function (string $tag) use ($decode): array {
    $result = [];
    if (preg_match_all('/([A-Za-z_:.-]+)\s*=\s*(["\'])(.*?)\2/s', $tag, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) $result[strtolower($match[1])] = $decode($match[3]);
    }
    return $result;
};
$parse = static function (string $html) use ($attributes, $decode): array {
    $document = ['title' => [], 'meta' => [], 'links' => [], 'jsonld' => [], 'scripts' => 0, 'images' => 0];
    if (preg_match_all('#<title\b[^>]*>(.*?)</title>#is', $html, $matches)) {
        foreach ($matches[1] as $value) $document['title'][] = trim($decode(strip_tags($value)));
    }
    if (preg_match_all('#<meta\b[^>]*>#is', $html, $matches)) {
        foreach ($matches[0] as $tag) {
            $attrs = $attributes($tag);
            $key = $attrs['name'] ?? $attrs['property'] ?? '';
            if ($key !== '') $document['meta'][$key][] = $attrs['content'] ?? '';
        }
    }
    if (preg_match_all('#<link\b[^>]*>#is', $html, $matches)) {
        foreach ($matches[0] as $tag) {
            $attrs = $attributes($tag);
            if (!empty($attrs['rel'])) $document['links'][$attrs['rel']][] = $attrs['href'] ?? '';
        }
    }
    if (preg_match_all('#<script\b([^>]*)>(.*?)</script>#is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $document['scripts']++;
            $attrs = $attributes('<script ' . $match[1] . '>');
            if (($attrs['type'] ?? '') === 'application/ld+json') $document['jsonld'][] = json_decode(trim($match[2]), true, 512, JSON_THROW_ON_ERROR);
        }
    }
    if (preg_match_all('#<img\b#i', $html, $matches)) $document['images'] = count($matches[0]);
    return $document;
};
$meta = static fn(array $doc, string $key): array => $doc['meta'][$key] ?? [];
$link = static fn(array $doc, string $rel): array => $doc['links'][$rel] ?? [];

$env = [];
foreach (['STRIDEBR_APP_ENV','STRIDEBR_APP_URL','STRIDEBR_ROBOTS_NOINDEX','STRIDEBR_GOOGLE_SITE_VERIFICATION','STRIDEBR_BING_SITE_VERIFICATION'] as $key) $env[$key] = getenv($key);
try {
    putenv('STRIDEBR_APP_ENV=production');
    putenv('STRIDEBR_APP_URL=http://untrusted.example');
    putenv('STRIDEBR_ROBOTS_NOINDEX=0');
    putenv('STRIDEBR_GOOGLE_SITE_VERIFICATION');
    putenv('STRIDEBR_BING_SITE_VERIFICATION');

    $page = [
        'title' => 'StrideBR — Treinos, atividades e evolução esportiva',
        'description' => 'StrideBR é uma plataforma brasileira para registrar atividades físicas.',
        'path' => '/?utm_source=test',
        'locale' => 'pt-BR',
        'structured' => stridebr_seo_brand_data('pt-BR'),
    ];
    $html = stridebr_seo_head($page);
    $doc = $parse($html);
    $assert($doc['title'] === [$page['title']], 'Home title');
    foreach (['description','robots','twitter:card','twitter:title','twitter:description','twitter:image','twitter:image:alt'] as $name) $assert(count($meta($doc, $name)) === 1, 'Single meta ' . $name);
    foreach (['type','title','description','url','site_name','image','image:width','image:height','image:type','image:alt','locale'] as $key) $assert(count($meta($doc, 'og:' . $key)) === 1, 'Single OG ' . $key);
    $assert(count($link($doc, 'canonical')) === 1, 'Single canonical');
    $assert($link($doc, 'canonical')[0] === 'https://stridebr.com.br/', 'Canonical ignores host and query');
    $assert($meta($doc, 'og:url')[0] === 'https://stridebr.com.br/', 'OG URL canonical');
    $assert($meta($doc, 'og:site_name')[0] === 'StrideBR', 'Brand');
    $assert($meta($doc, 'twitter:card')[0] === 'summary_large_image', 'Large card');
    $ogImageUrl = 'https://stridebr.com.br/assets/img/branding/stridebr-og-20260909.png';
    $assert($meta($doc, 'og:image')[0] === $ogImageUrl, 'Versioned absolute OG image');
    $assert($meta($doc, 'twitter:image')[0] === $ogImageUrl, 'Twitter uses versioned OG image');
    $schema = $doc['jsonld'][0] ?? null;
    $assert(is_array($schema) && array_column($schema['@graph'] ?? [], '@type') === ['Organization','WebSite'], 'Brand schema');
    $organization = $schema['@graph'][0] ?? [];
    $website = $schema['@graph'][1] ?? [];
    foreach ([$organization, $website] as $entity) {
        $assert(($entity['name'] ?? null) === 'StrideBR', 'Brand name');
        $assert(($entity['alternateName'] ?? null) === 'Stride BR', 'Alternate name');
    }
    $assert(($organization['sameAs'] ?? []) === ['https://www.instagram.com/stridebr.app/', 'https://github.com/BrunoWithoutH/StrideBR'], 'Official organization profiles');
    $assert(($organization['url'] ?? null) === 'https://stridebr.com.br/', 'Organization canonical domain');
    $assert(($schema['@graph'][1]['inLanguage'] ?? null) === 'pt-BR', 'Brand schema language');
    $assert(!str_contains($html, 'SearchAction') && !str_contains($html, 'twitter:site'), 'No invented search or account');
    $assert(count($meta($doc, 'google-site-verification')) === 0 && count($meta($doc, 'msvalidate.01')) === 0, 'Empty verification omitted');

    $page['path'] = '/';
    $verification = 'synthetic"><script>alert(1)</script>';
    putenv('STRIDEBR_GOOGLE_SITE_VERIFICATION=' . $verification);
    putenv('STRIDEBR_BING_SITE_VERIFICATION=' . $verification);
    $verified = $parse(stridebr_seo_head($page));
    $assert(count($meta($verified, 'google-site-verification')) === 1 && count($meta($verified, 'msvalidate.01')) === 1, 'Configured verification present');
    $assert($meta($verified, 'google-site-verification')[0] === $verification, 'Verification escaped');
    $assert($verified['scripts'] === 1, 'No injected script');

    $page['title'] = '<b>Sobre o StrideBR</b>';
    $page['description'] = '<b>Public</b> <img src=x onerror=alert(1)> ' . str_repeat('x', 400);
    $page['path'] = '/pages/about/about.php';
    $safe = $parse(stridebr_seo_head($page));
    $assert($safe['title'] === ['Sobre o StrideBR'], 'Title avoids duplicate brand');
    $safeDescription = $meta($safe, 'description')[0] ?? '';
    $safeDescriptionLength = function_exists('mb_strlen') ? mb_strlen($safeDescription, 'UTF-8') : strlen($safeDescription);
    $assert($safeDescriptionLength <= 180, 'Description limited');
    $assert($safe['images'] === 0, 'No HTML injection');

    foreach (['/home.php','/login.php','/signup.php','/user/settings.php','/user/editatividade.php','/auth/integration.php','/errors/404.php','/u/person'] as $path) {
        $private = $parse(stridebr_seo_head(['path' => $path]));
        $assert(count($link($private, 'canonical')) === 0, 'No private canonical');
        $assert(str_contains($meta($private, 'robots')[0] ?? '', 'noindex'), 'Private noindex');
    }

    $assert(stridebr_seo_canonical('/evento.php?e=bad&utm=x', 'public-event') === 'https://stridebr.com.br/evento.php?e=public-event', 'Event preserves only public identity');
    $assert(stridebr_seo_canonical('/evento.php') === null, 'No unqualified event canonical');
    $english = $parse(stridebr_seo_head(['path' => '/calendario.php', 'locale' => 'en']));
    $assert(($meta($english, 'og:locale')[0] ?? '') === 'en_US', 'English locale');
    $englishBrand = stridebr_seo_brand_data('en');
    $assert(($englishBrand['@graph'][1]['inLanguage'] ?? null) === 'en', 'English structured language');

    http_response_code(404);
    $error = $parse(stridebr_seo_head(['path' => '/']));
    $assert(count($link($error, 'canonical')) === 0 && str_contains($meta($error, 'robots')[0] ?? '', 'noindex'), '404 cannot canonicalize home');
    http_response_code(200);

    $paths = stridebr_seo_public_paths();
    foreach (stridebr_seo_sitemap_urls() as $url) $assert(str_starts_with($url, 'https://stridebr.com.br/') && in_array(parse_url($url, PHP_URL_PATH), $paths, true), 'Sitemap only canonical public routes');
    foreach ($paths as $path) $assert(is_file(dirname(__DIR__,2).'/public'.($path === '/' ? '/index.php' : $path)), 'Public route exists');

    putenv('STRIDEBR_APP_ENV=staging');
    $assert(stridebr_robots_noindex(), 'Staging cannot override noindex with false');
    $assert(stridebr_seo_sitemap_urls() === [], 'No staging sitemap URLs');
    $assert(str_contains(stridebr_seo_head(['path' => '/']), 'noindex'), 'Staging metadata noindex');

    $ogImagePath = dirname(__DIR__,2).'/public/assets/img/branding/stridebr-og-20260909.png';
    $assert(is_file($ogImagePath), 'Versioned OG image exists');
    $image = getimagesize($ogImagePath);
    $assert($image[0] === 1200 && $image[1] === 630, 'OG image dimensions');
    $assert(filesize($ogImagePath) > 0 && filesize($ogImagePath) < 300000, 'OG image lightweight');
    $robots = file_get_contents(dirname(__DIR__,2).'/public/robots.txt');
    $assert(str_contains($robots, 'Sitemap: https://stridebr.com.br/sitemap.xml') && !str_contains($robots, 'Disallow: /assets'), 'Robots sitemap and assets');
    $assert(str_contains(file_get_contents(dirname(__DIR__,2).'/public/.htaccess'), 'RewriteRule ^robots\\.txt$ robots.php [L]'), 'Dynamic robots remains authoritative');

    $uiBoot = stridebr_ui_boot_script();
    $assert(substr_count($uiBoot, 'name="theme-color"') === 1, 'Theme color remains centralized');
    $assert(str_contains($uiBoot, 'manifest.webmanifest') && str_contains($uiBoot, '/assets/img/branding/app-icons/ios/apple-touch-icon-180x180.png') && substr_count($uiBoot, 'rel="apple-touch-icon"') === 3, 'PWA metadata remains centralized');
    foreach (['public/index.php','public/calendario.php','public/evento.php','src/layout/static_page.php'] as $file) {
        $source = file_get_contents(dirname(__DIR__,2) . '/' . $file);
        $assert(str_contains($source, '/assets/img/favicon/favicon.png'), 'Existing favicon preserved in ' . $file);
        $assert(!str_contains($source, 'rel="icon" type="image/png" sizes="192x192"'), 'PWA icon not substituted for favicon in ' . $file);
    }
    $indexSource = file_get_contents(dirname(__DIR__,2).'/public/index.php');
    $assert(str_contains($indexSource, 'stridebr_html_lang()') && str_contains($indexSource, "'locale' => stridebr_locale()"), 'Home locale remains dynamic');
    $readme = file_get_contents(dirname(__DIR__,2).'/README.md');
    $assert(str_contains($readme, 'https://stridebr.com.br'), 'README identifies the official site');

    echo "✓ SEO metadata: $checks assertions\n";
} finally {
    foreach ($env as $key => $value) putenv($value === false ? $key : $key . '=' . $value);
}
