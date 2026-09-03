<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/gps_web.php';
require_once dirname(__DIR__, 2) . '/src/function/product_analytics.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new InvalidArgumentException('Método inválido.');
    stridebr_verify_csrf();
    $recording = gpsWebParseRecording($_POST['recording'] ?? null);
    $recordingKey = gpsWebRecordingKey($recording);
    $existing = gpsWebFindExistingRecording($pdo, $idUsuario, $recordingKey);
    if ($existing !== null) {
        echo json_encode(['ok' => true, 'idregistro' => $existing, 'edit_url' => '/user/editatividade.php?id=' . rawurlencode($existing), 'reused' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
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
                echo json_encode(['ok' => true, 'idregistro' => $existing, 'edit_url' => '/user/editatividade.php?id=' . rawurlencode($existing), 'reused' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                exit;
            }
        }
        throw $e;
    }
    productAnalyticsRegistrar($pdo, $idUsuario, 'activity_saved', ['source' => 'gps_web', 'goal' => $meta['goal_type'] ?? null]);
    echo json_encode(['ok' => true, 'idregistro' => $idRegistro, 'edit_url' => '/user/editatividade.php?id=' . rawurlencode($idRegistro)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $known = $e instanceof InvalidArgumentException || $e instanceof RuntimeException;
    http_response_code($known ? 400 : 500);
    if (!$known) error_log('StrideBR GPS save: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $known ? $e->getMessage() : 'Não foi possível salvar a atividade GPS. A gravação continua guardada neste navegador.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
