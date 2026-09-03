<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método não permitido.']);
    exit;
}

$idUsuario = stridebr_require_login();
stridebr_verify_csrf();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
stridebr_session_release();
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';

try {
    if (stridebr_auth_limit_is_blocked($pdo, 'route-elevation', $idUsuario)) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'message' => 'Aguarde um pouco antes de recalcular a elevação.']);
        exit;
    }
    stridebr_auth_limit_record_attempt($pdo, 'route-elevation', $idUsuario, 30, 60, 60);
    $route = atividadeValidarRotaGeoJson($_POST['coordenadas'] ?? null, false);
    $elevation = atividadeConsultarElevacao($route);
    if ($elevation === null) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'message' => 'A estimativa de terreno está indisponível agora. Você ainda pode salvar a atividade.']);
        exit;
    }
    echo json_encode(['ok' => true, 'elevation' => $elevation], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // Esse endpoint é sempre consumido via fetch()+JSON pelo front-end. Sem
    // esse catch-all, um erro inesperado (timeout da API de elevação, falha
    // de banco etc.) deixava o handler global de erro devolver uma página
    // HTML — e o JS quebrava tentando fazer .json() numa resposta que não é
    // JSON. Aqui sempre respondemos JSON, mesmo quando algo dá errado.
    error_log('atividade-elevacao: ' . $e->getMessage());
    if (!headers_sent()) http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Não foi possível calcular a elevação agora. Você ainda pode salvar a atividade normalmente.'], JSON_UNESCAPED_UNICODE);
}
