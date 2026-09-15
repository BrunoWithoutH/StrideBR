<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/endurance_workout_analysis.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
stridebr_session_release();

try {
    $id = trim((string) ($_GET['id'] ?? ''));
    if ($id === '') throw new InvalidArgumentException('Activity inválida.');
    echo json_encode(['ok' => true, 'data' => enduranceWorkoutCompareActivity($pdo, $idUsuario, $id)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : ($e instanceof RuntimeException ? 404 : 500));
    if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) error_log('StrideBR planned actual: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível comparar o treino.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
