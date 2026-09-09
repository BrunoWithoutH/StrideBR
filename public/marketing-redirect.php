<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/function/marketing.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$code = stridebr_marketing_slug((string) ($_GET['code'] ?? ''));
if ($code === '') {
    stridebr_error_document(404);
}

$placement = stridebr_marketing_lookup_placement($pdo, $code, true);
if (!$placement) {
    stridebr_error_document(404);
}

try {
    stridebr_start_session();
    stridebr_marketing_capture_first_touch($pdo, [], $placement);
    stridebr_marketing_record_event($pdo, 'landing_view', null, '/r/' . $code);
} catch (Throwable $e) {
    error_log('StrideBR campaign redirect tracking failed: ' . get_class($e));
}

try {
    $destination = stridebr_marketing_internal_destination((string) ($placement['destino'] ?? '/'));
} catch (Throwable) {
    $destination = '/';
}
header('Location: ' . $destination, true, 302);
exit;
