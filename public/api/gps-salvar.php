<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ob_start();

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

function gpsApiRespond(int $status, array $payload): never
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    gpsApiRespond(405, ['ok' => false, 'code' => 'method_not_allowed', 'error' => 'Método inválido.']);
}

if (!stridebr_is_logged_in()) {
    gpsApiRespond(401, ['ok' => false, 'code' => 'auth_required', 'error' => 'Sua sessão expirou. Entre novamente para salvar a atividade.']);
}

$csrf = $_POST['csrf_token'] ?? '';
if (!is_string($csrf) || !hash_equals(stridebr_csrf_token(), $csrf)) {
    gpsApiRespond(403, ['ok' => false, 'code' => 'csrf_invalid', 'error' => 'A sessão de segurança expirou. Recarregue após entrar novamente e tente salvar.']);
}

$idUsuario = (string) stridebr_user_id();

try {
    require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
    require_once dirname(__DIR__, 2) . '/src/function/gps_web.php';
    require_once dirname(__DIR__, 2) . '/src/function/product_analytics.php';

    $recording = gpsWebParseRecording($_POST['recording'] ?? null);
    $recordingKey = gpsWebRecordingKey($recording);
    $existing = gpsWebFindExistingRecording($pdo, $idUsuario, $recordingKey);
    if ($existing !== null) {
        gpsApiRespond(200, ['ok' => true, 'idregistro' => $existing, 'edit_url' => '/user/editatividade.php?id=' . rawurlencode($existing), 'reused' => true]);
    }

    $payload = gpsWebBuildActivityPayload($pdo, $idUsuario, $recording);
    $meta = $payload['_gps_meta'];
    unset($payload['_gps_meta']);

    $pdo->beginTransaction();
    try {
        $idRegistro = atividadeSalvarRegistro($pdo, $idUsuario, $payload);
        gpsWebSaveMetadata($pdo, $idRegistro, $meta);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && $e->getCode() === '23505') {
            $existing = gpsWebFindExistingRecording($pdo, $idUsuario, $recordingKey);
            if ($existing !== null) {
                gpsApiRespond(200, ['ok' => true, 'idregistro' => $existing, 'edit_url' => '/user/editatividade.php?id=' . rawurlencode($existing), 'reused' => true]);
            }
        }
        throw $e;
    }

    productAnalyticsRegistrar($pdo, $idUsuario, 'activity_saved', ['source' => 'gps_web', 'goal' => $meta['goal_type'] ?? null]);
    gpsApiRespond(200, ['ok' => true, 'idregistro' => $idRegistro, 'edit_url' => '/user/editatividade.php?id=' . rawurlencode($idRegistro)]);
} catch (InvalidArgumentException $e) {
    gpsApiRespond(422, ['ok' => false, 'code' => 'validation_error', 'error' => $e->getMessage()]);
} catch (RuntimeException $e) {
    gpsApiRespond(400, ['ok' => false, 'code' => 'request_error', 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('StrideBR GPS save: ' . $e->getMessage());
    gpsApiRespond(500, ['ok' => false, 'code' => 'server_error', 'error' => 'Não foi possível salvar a atividade GPS. A gravação continua guardada neste navegador.']);
}
