<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método inválido.']);
    exit;
}

try {
    stridebr_csrf_validate_request();
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $id = trim((string) ($input['id'] ?? ''));
    $excluded = filter_var($input['excluded'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    if ($id === '' || $excluded === null) throw new InvalidArgumentException('Dados inválidos.');
    if (!atividadeDefinirExclusaoEstatisticas($pdo, $idUsuario, $id, $excluded)) throw new InvalidArgumentException('Atividade não encontrada.');
    echo json_encode(['ok' => true, 'id' => $id, 'excluded_from_stats' => $excluded], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode(['ok' => false, 'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível atualizar a atividade.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
