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
    require_once dirname(__DIR__, 2) . '/src/function/activity_file_exchange.php';
    $id = trim((string) ($_POST['idimportacao'] ?? ''));
    if ($id === '') throw new InvalidArgumentException('Importação inválida.');
    atividadeArquivoDescartarPreview($pdo, $idUsuario, $id);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('StrideBR discard activity import: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível fechar esta importação.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
