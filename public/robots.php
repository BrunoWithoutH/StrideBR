<?php
require_once dirname(__DIR__) . '/src/includes/environment.php';
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
echo "User-agent: *\n";
if (stridebr_robots_noindex()) { echo "Disallow: /\n"; exit; }
echo "Allow: /\nDisallow: /admin/\nDisallow: /user/\nDisallow: /function/\nDisallow: /api/\nDisallow: /auth/\nDisallow: /accept-legal.php\nDisallow: /errors/\n\nSitemap: " . stridebr_app_url() . "/sitemap.xml\n";
