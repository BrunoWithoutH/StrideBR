<?php
require_once dirname(__DIR__) . '/src/includes/configuration.php';
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$ready = stridebr_configuration_check()['ok'] && stridebr_database_ready();
http_response_code($ready ? 200 : 503);
echo $ready ? "READY\n" : "NOT READY\n";
