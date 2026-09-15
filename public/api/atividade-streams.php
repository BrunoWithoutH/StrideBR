<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/function/activity_stream_service.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
stridebr_session_release();

try {
    $id = trim((string) ($_GET['id'] ?? ''));
    if ($id === '') throw new InvalidArgumentException('Atividade inválida.');
    activityStreamEnsureMaterialized($pdo, $idUsuario, $id);
    $data = activityStreamRead($pdo, $idUsuario, $id, $_GET);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $notFound = $e instanceof InvalidArgumentException && $e->getMessage() === 'Atividade não encontrada.';
    http_response_code($notFound ? 404 : ($e instanceof InvalidArgumentException ? 422 : 500));
    if (!$e instanceof InvalidArgumentException) error_log('StrideBR activity streams web API: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível carregar os gráficos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
