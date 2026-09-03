<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/account_data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /user/account.php#dados-conta');
    exit;
}
stridebr_verify_csrf();

try {
    $data = accountExportData($pdo, $idUsuario);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    $filename = 'stridebr-dados-' . date('Y-m-d-His') . '.json';
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, private');
    echo $json;
} catch (Throwable $e) {
    error_log('StrideBR account export failed: ' . $e->getMessage());
    stridebr_flash('danger', 'Não foi possível gerar a exportação agora. Tente novamente ou entre em contato pelo suporte.');
    header('Location: /user/account.php#dados-conta');
}
