<?php
// Isolated HTTP test router. Never deployed under public/.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!str_starts_with($path, '/__seo/')) return false;
if (str_contains($path, '/staging/')) {
    putenv('STRIDEBR_APP_ENV=staging');
    putenv('STRIDEBR_ROBOTS_NOINDEX=0');
} else {
    putenv('STRIDEBR_APP_ENV=development');
    putenv('STRIDEBR_ROBOTS_NOINDEX=0');
}
if (str_contains($path, '/verified/')) {
    putenv('STRIDEBR_GOOGLE_SITE_VERIFICATION=synthetic-google"><b>');
    putenv('STRIDEBR_BING_SITE_VERIFICATION=synthetic-bing');
} else {
    putenv('STRIDEBR_GOOGLE_SITE_VERIFICATION');
    putenv('STRIDEBR_BING_SITE_VERIFICATION');
}
$root = dirname(__DIR__,2).'/public';
if (str_ends_with($path, '/robots.txt')) require $root.'/robots.php';
elseif (str_ends_with($path, '/sitemap.xml')) require $root.'/sitemap.php';
else { $_SERVER['REQUEST_URI']='/'; require $root.'/index.php'; }
