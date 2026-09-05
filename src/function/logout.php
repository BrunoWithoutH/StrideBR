<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

stridebr_verify_csrf();

$idUsuario = stridebr_user_id();
if ($idUsuario !== null) {
    try {
        require_once dirname(__DIR__) . '/config/pg_config.php';
        stridebr_session_revoke_current($pdo, $idUsuario);
    } catch (Throwable $e) {
        error_log('StrideBR logout session tracking failed: ' . $e->getMessage());
    }
}

stridebr_destroy_session();
header('Location: /');
exit;
