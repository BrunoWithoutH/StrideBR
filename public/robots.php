<?php
require_once dirname(__DIR__) . '/src/includes/environment.php';
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
// .htaccess always routes /robots.txt here, even though the production template exists.
if (stridebr_robots_noindex()) {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    echo "User-agent: *\nDisallow: /\n";
    exit;
}
readfile(__DIR__ . '/robots.txt');
