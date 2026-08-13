<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';

stridebr_require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    stridebr_flash('info', 'O cronograma agora é salvo diretamente pela nova interface.');
}
header('Location: /user/cronogramatreinos.php');
exit;
