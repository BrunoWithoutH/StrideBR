<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');

$idUsuario = stridebr_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido.']);
    exit;
}

try {
    stridebr_verify_csrf();
    require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
    stridebr_session_release();
    require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';

    $ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
    $result = atividadeAtualizarRegistrosEmLote($pdo, $idUsuario, $ids, [
        'apagar' => (string) ($_POST['action'] ?? '') === 'delete',
        'idmodalidade' => $_POST['idmodalidade'] ?? '',
        'duracao_modo' => $_POST['duracao_modo'] ?? 'keep',
        'duracao_minutos' => $_POST['duracao_minutos'] ?? '',
        'visibilidade' => $_POST['visibilidade'] ?? '',
    ]);
    echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível atualizar as atividades selecionadas.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
