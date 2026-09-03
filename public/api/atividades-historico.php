<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_presenter.php';
require_once dirname(__DIR__, 2) . '/src/includes/sport_icons.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') stridebr_session_release();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $pdo->exec("SET statement_timeout TO '6500ms'; SET lock_timeout TO '1500ms'");
}

try {
    $queryStartedAt = microtime(true);
    $cursor = trim((string) ($_GET['cursor'] ?? '')) ?: null;
    $sport = trim((string) ($_GET['sport'] ?? ''));
    $result = atividadeListarRegistrosPagina(
        $pdo,
        $idUsuario,
        (int) ($_GET['limit'] ?? 20),
        $cursor,
        trim((string) ($_GET['q'] ?? '')),
        $sport
    );
    if ($cursor === null) $result['resumo'] = atividadeResumoHistorico($pdo, $idUsuario, $sport);
    stridebr_timing_measure('activity_history', $queryStartedAt, 'Histórico de atividades');
    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    if (!$e instanceof InvalidArgumentException) error_log('StrideBR activity history API: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível carregar o histórico.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
