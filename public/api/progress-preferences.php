<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/dashboard.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => stridebr_t('progress.preference_method_error')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    stridebr_verify_csrf();
    $preferences = dashboardSalvarPreferenciasProgress($pdo, $idUsuario, (string) ($_POST['period'] ?? ''));
    echo json_encode(['ok' => true, 'preferences' => $preferences], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $invalid = $e instanceof InvalidArgumentException;
    http_response_code($invalid ? 400 : 500);
    if (!$invalid) error_log('StrideBR progress preferences API: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $invalid ? $e->getMessage() : stridebr_t('progress.preference_save_error')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
