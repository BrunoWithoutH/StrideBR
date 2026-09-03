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
    require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
    require_once dirname(__DIR__, 2) . '/src/function/activity_file_exchange.php';

    $schemaCache = $_SESSION['StrideBRCapabilityCache']['activity_import'] ?? null;
    if (is_array($schemaCache) && isset($schemaCache['at']) && (time() - (int) $schemaCache['at']) < 300) {
        $schemaReady = (bool) ($schemaCache['value'] ?? false);
    } else {
        $schemaReadyRaw = $pdo->query("SELECT CASE WHEN to_regclass('stridebr.atividade_importacoes') IS NULL THEN 0 ELSE 1 END")->fetchColumn();
        $schemaReady = (int) $schemaReadyRaw === 1;
        $_SESSION['StrideBRCapabilityCache']['activity_import'] = ['value' => $schemaReady, 'at' => time()];
    }
    $lastCleanup = (int) ($_SESSION['StrideBRMaintenance']['activity_import_cleanup'] ?? 0);
    $cleanupDue = $lastCleanup === 0 || (time() - $lastCleanup) >= 3600;
    if ($cleanupDue) $_SESSION['StrideBRMaintenance']['activity_import_cleanup'] = time();
    stridebr_session_release();

    if (!$schemaReady) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'A importação ainda não está ativada neste banco. Atualize o banco do StrideBR e tente novamente.', 'code' => 'activity_import_schema_missing'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($cleanupDue) {
        $pdo->prepare("DELETE FROM stridebr.atividade_importacoes WHERE idusuario = :usuario AND status = 'pendente' AND data_criacao < NOW() - INTERVAL '48 hours'")
            ->execute([':usuario' => $idUsuario]);
    }

    $preview = atividadeArquivoCriarPreview($pdo, $idUsuario, $_FILES['arquivo'] ?? []);
    echo json_encode(['ok' => true, 'preview' => $preview], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $message = $e->getMessage();
    $stage = str_contains($message, '|') ? strstr($message, '|', true) : 'import_unknown';
    $publicError = match ($stage) {
        'import_duplicate' => 'O arquivo foi lido, mas houve uma falha ao verificar atividades duplicadas.',
        'import_modality' => 'O arquivo foi lido, mas não foi possível associar a modalidade no StrideBR.',
        'import_store' => 'O arquivo foi lido, mas não foi possível preparar a prévia no banco de dados.',
        'import_parse' => 'O formato do arquivo foi reconhecido, mas os dados não puderam ser interpretados.',
        default => 'Não foi possível analisar este arquivo.',
    };
    error_log('StrideBR activity import preview [' . $stage . ']: ' . $message);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $publicError, 'code' => $stage], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
