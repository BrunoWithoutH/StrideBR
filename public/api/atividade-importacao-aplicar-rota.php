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
    require_once dirname(__DIR__, 2) . '/src/function/activity_file_exchange.php';
    $idImportacao = trim((string) ($_POST['idimportacao'] ?? ''));
    $idRegistro = trim((string) ($_POST['idregistro'] ?? ''));
    if ($idImportacao === '' || $idRegistro === '') throw new InvalidArgumentException('Escolha uma atividade para receber a rota.');
    $registro = atividadeArquivoAplicarPercurso($pdo, $idUsuario, $idImportacao, $idRegistro);
    echo json_encode(['ok' => true, 'idregistro' => $registro, 'url' => '/user/atividades.php?highlight=' . rawurlencode($registro)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('StrideBR attach imported route: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível adicionar a rota à atividade.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
