<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/errors.php';
require_once dirname(__DIR__) . '/includes/app.php';

$idUsuario = stridebr_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /user/atividades.php');
    exit;
}

stridebr_verify_csrf();

require_once dirname(__DIR__) . '/config/pg_config.php';
require_once __DIR__ . '/atividade_modelo.php';

$idRegistro = trim((string) ($_POST['id'] ?? ''));
$wantsJson = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
$embedded = (string) ($_GET['embed'] ?? $_POST['embed'] ?? '') === '1';
$removed = $idRegistro !== '' && atividadeExcluirRegistro($pdo, $idRegistro, $idUsuario);

if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($removed ? 200 : 404);
    echo json_encode([
        'ok' => $removed,
        'message' => $removed ? 'Atividade excluída.' : 'Atividade não encontrada ou já removida.',
        'id' => $idRegistro,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($embedded) {
    if ($removed) {
        header('Content-Type: text/html; charset=utf-8');
        $bridge = stridebr_asset('/assets/js/activity-edit-bridge.js');
        echo '<!doctype html><html><body><div data-activity-edit-result data-type="stridebr:activity-edit-deleted" data-id="' . stridebr_e($idRegistro) . '"></div><script src="' . stridebr_e($bridge) . '"></script></body></html>';
        exit;
    }
    header('Location: /user/editatividade.php?id=' . rawurlencode($idRegistro) . '&embed=1&delete_error=1');
    exit;
}

if (!$removed) {
    stridebr_flash('danger', 'Atividade não encontrada ou já removida.');
} else {
    $_SESSION['activity_undo'] = [
        'idregistro' => $idRegistro,
        'token' => bin2hex(random_bytes(16)),
        'expires' => time() + 300,
    ];
    stridebr_flash('success', 'Atividade excluída. Você pode desfazer esta ação.');
}

header('Location: /user/atividades.php');
exit;
