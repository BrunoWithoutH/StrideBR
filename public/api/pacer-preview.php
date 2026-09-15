<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/activity_stream_service.php';
require_once dirname(__DIR__, 2) . '/src/function/pacer_service.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    stridebr_verify_csrf();
    $payload = json_decode((string) ($_POST['payload'] ?? ''), true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) throw new InvalidArgumentException('Payload inválido.');
    $data = pacerBlueprint($pdo, $idUsuario, $payload);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    if (!$e instanceof InvalidArgumentException) error_log('StrideBR Pacer Web preview: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível gerar a prévia.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
