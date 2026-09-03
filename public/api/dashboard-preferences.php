<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/dashboard.php';
require_once dirname(__DIR__, 2) . '/src/function/product_analytics.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        stridebr_session_release();
        echo json_encode(['ok' => true, 'preferences' => dashboardPreferenciasHome($pdo, $idUsuario)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    stridebr_verify_csrf();
    $order = json_decode((string) ($_POST['order'] ?? '[]'), true);
    $hidden = json_decode((string) ($_POST['hidden'] ?? '[]'), true);
    if (!is_array($order) || !is_array($hidden)) throw new InvalidArgumentException('Preferências inválidas.');
    $preferences = dashboardSalvarPreferenciasHome($pdo, $idUsuario, $order, $hidden);
    productAnalyticsRegistrar($pdo, $idUsuario, 'dashboard_customized', ['hidden_count' => count($hidden), 'module_count' => count($order)]);
    echo json_encode(['ok' => true, 'preferences' => $preferences], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    if (!$e instanceof InvalidArgumentException) error_log('StrideBR dashboard preferences API: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível salvar a personalização.'], JSON_UNESCAPED_UNICODE);
}
