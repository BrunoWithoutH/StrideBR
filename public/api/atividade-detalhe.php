<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_presenter.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') stridebr_session_release();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $pdo->exec("SET statement_timeout TO '6500ms'; SET lock_timeout TO '1500ms'");
}

try {
    $id = trim((string) ($_GET['id'] ?? ''));
    if ($id === '') throw new InvalidArgumentException('Atividade inválida.');
    $queryStartedAt = microtime(true);
    $detail = atividadeDetalheApi($pdo, $id, $idUsuario);
    stridebr_timing_measure('activity_detail', $queryStartedAt, 'Detalhe da atividade');
    if ($detail === []) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Atividade não encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true, 'atividade' => $detail], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    if (!$e instanceof InvalidArgumentException) error_log('StrideBR activity detail API: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível carregar a atividade.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
