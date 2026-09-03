<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/strength_activity.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
stridebr_session_release();

try {
    $idExercise = trim((string) ($_GET['id'] ?? ''));
    $name = trim((string) ($_GET['nome'] ?? ''));
    if ($idExercise === '' && $name === '') throw new InvalidArgumentException('Exercício não informado.');
    $reference = atividadeForcaReferenciaExercicio($pdo, $idUsuario, $idExercise !== '' ? $idExercise : null, $name);
    echo json_encode(['ok' => true, 'referencia' => $reference], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    if (!$e instanceof InvalidArgumentException) error_log('StrideBR strength exercise reference API: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível carregar o histórico do exercício.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
