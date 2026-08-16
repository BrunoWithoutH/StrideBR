<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/includes/auth.php';

$tokenRaw = trim((string) ($_GET['token'] ?? ''));
$token = stridebr_auth_find_token($pdo, $tokenRaw, 'verificar_email');
$success = false;

if ($token) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE auth_tokens SET usado_em = NOW() WHERE idtoken = :id AND usado_em IS NULL AND expira_em > NOW()");
        $stmt->execute([':id' => $token['idtoken']]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Token inválido.');
        }
        $pdo->prepare('UPDATE usuarios SET verificado = TRUE, email_verificado_em = NOW() WHERE idusuario = :id')->execute([':id' => $token['idusuario']]);
        $pdo->commit();
        $success = true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>"><title>Verificação de e-mail | StrideBR</title></head><body><main class="page-shell auth-result-shell"><section class="content-card"><h1><?php echo $success ? 'E-mail confirmado' : 'Link inválido ou expirado'; ?></h1><p><?php echo $success ? 'Seu e-mail foi confirmado. Você já pode entrar normalmente.' : 'Solicite um novo link de verificação para continuar.'; ?></p><a class="primary-action" href="<?php echo $success ? '/login.php' : '/resend-verification.php'; ?>"><?php echo $success ? 'Entrar' : 'Reenviar verificação'; ?></a></section></main></body></html>
