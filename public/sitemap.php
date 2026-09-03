<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';

header('Content-Type: application/xml; charset=UTF-8');

$base = stridebr_public_url();
$paths = [
    '/',
    '/calendario.php',
    '/pages/about/about.php',
    '/pages/about/team.php',
    '/pages/about/contact.php',
    '/pages/help/faq.php',
    '/pages/help/support.php',
    '/pages/legal/terms.php',
    '/pages/legal/privacy.php',
    '/pages/legal/cookies.php',
    '/pages/extras/roadmap.php',
    '/pages/extras/changelog.php',
    '/pages/extras/credits.php',
];

$urls = [];
foreach ($paths as $path) {
    $urls[] = $base . ($path === '/' ? '/' : $path);
}

try {
    require dirname(__DIR__) . '/src/config/pg_config.php';
    $exists = $pdo->query("SELECT to_regclass('stridebr.eventos_esportivos')")?->fetchColumn();
    if ($exists) {
        $stmt = $pdo->query("SELECT slug FROM stridebr.eventos_esportivos WHERE status = 'publicado' AND data_inicio >= NOW() - INTERVAL '30 days' ORDER BY data_inicio");
        foreach ($stmt ?: [] as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug !== '') {
                $urls[] = $base . '/evento.php?e=' . rawurlencode($slug);
            }
        }
    }
} catch (Throwable $e) {
    error_log('StrideBR sitemap dynamic URLs unavailable: ' . $e->getMessage());
}

$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (array_values(array_unique($urls)) as $url) {
    echo '  <url><loc>' . $esc($url) . '</loc></url>' . "\n";
}
echo '</urlset>';
