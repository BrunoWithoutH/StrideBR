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
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/atividade_modelo.php';

$idRegistro = trim((string) ($_POST['id'] ?? ''));

if ($idRegistro === '' || !atividadeExcluirRegistro($pdo, $idRegistro, $idUsuario)) {
    stridebr_flash('danger', 'Atividade não encontrada ou já removida.');
} else {
    stridebr_flash('success', 'Atividade excluída.');
}

header('Location: /user/atividades.php');
exit;
