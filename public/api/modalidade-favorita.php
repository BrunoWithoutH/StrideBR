<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$idUsuario = stridebr_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método não permitido.']);
    exit;
}

stridebr_verify_csrf();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
stridebr_session_release();
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';

$idModalidade = trim((string) ($_POST['idmodalidade'] ?? ''));
$favoritaRaw = (string) ($_POST['favorita'] ?? '0');
$favorita = in_array($favoritaRaw, ['1', 'true', 'on'], true);

try {
    atividadeDefinirModalidadeFavorita($pdo, $idUsuario, $idModalidade, $favorita);
    echo json_encode(['ok' => true, 'favorita' => $favorita], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Não foi possível atualizar o favorito.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
