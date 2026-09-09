<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
header('Content-Type: application/xml; charset=UTF-8');
$urls = stridebr_seo_sitemap_urls();
if (!stridebr_robots_noindex()) {
    try {
        require dirname(__DIR__) . '/src/config/pg_config.php';
        $urls = stridebr_seo_sitemap_urls($pdo);
    } catch (Throwable $error) {
        error_log('StrideBR sitemap dynamic URLs unavailable [' . get_class($error) . ']');
    }
}
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) echo '  <url><loc>' . $esc($url) . '</loc></url>' . "\n";
echo '</urlset>';
