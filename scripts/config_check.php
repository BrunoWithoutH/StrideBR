<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/src/includes/configuration.php';
$result = stridebr_configuration_check(!in_array('--no-filesystem', $argv, true));
foreach ($result['status'] as $name => $state) echo $name . ': ' . $state . PHP_EOL;
foreach ($result['errors'] as $name) fwrite(STDERR, 'Required configuration missing/invalid: ' . $name . PHP_EOL);
if (in_array('--database', $argv, true)) {
    $ready = stridebr_database_ready();
    echo 'READINESS: ' . ($ready ? 'ready' : 'not ready') . PHP_EOL;
    $result['ok'] = $result['ok'] && $ready;
}
exit($result['ok'] ? 0 : 1);
