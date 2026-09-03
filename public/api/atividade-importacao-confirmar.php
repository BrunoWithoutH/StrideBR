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

    $id = trim((string) ($_POST['idimportacao'] ?? ''));
    if ($id === '') throw new InvalidArgumentException('Importação inválida.');
    $registro = atividadeArquivoConfirmarImportacao($pdo, $idUsuario, $id, [
        'modalidade_slug' => $_POST['modalidade_slug'] ?? '',
        'title' => $_POST['titulo'] ?? '',
        'notes' => $_POST['observacoes'] ?? '',
        'visibility' => $_POST['visibilidade'] ?? 'privado',
        'start_date' => $_POST['data_inicio'] ?? '',
        'start_time' => $_POST['hora_inicio'] ?? '',
        'allow_duplicate' => (string) ($_POST['permitir_duplicata'] ?? '') === '1',
    ]);
    echo json_encode(['ok' => true, 'idregistro' => $registro, 'url' => '/user/atividades.php?saved=' . rawurlencode($registro)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('StrideBR activity import confirm: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível importar a atividade.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
