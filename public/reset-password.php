<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/includes/auth.php';

$enabled = stridebr_feature_enabled($pdo, 'auth.password_reset.enabled', false);
$tokenRaw = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$token = $enabled ? stridebr_auth_find_token($pdo, $tokenRaw, 'redefinir_senha') : null;
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token) {
    stridebr_verify_csrf();
    $password = (string) ($_POST['senha'] ?? '');
    $confirm = (string) ($_POST['confirmar'] ?? '');
    if (strlen($password) < 8) $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
    if ($password !== $confirm) $errors[] = 'As senhas não coincidem.';
    if ($errors === []) {
        $pdo->beginTransaction();
        try {
            $consume = $pdo->prepare('UPDATE auth_tokens SET usado_em = NOW() WHERE idtoken = :id AND usado_em IS NULL AND expira_em > NOW()');
            $consume->execute([':id' => $token['idtoken']]);
            if ($consume->rowCount() !== 1) throw new RuntimeException('Token inválido.');
            $update = $pdo->prepare('UPDATE usuarios SET senhausuario = :senha, sessao_versao = sessao_versao + 1 WHERE idusuario = :id');
            $update->execute([':senha' => password_hash($password, PASSWORD_DEFAULT), ':id' => $token['idusuario']]);
            $pdo->prepare("UPDATE auth_tokens SET usado_em = NOW() WHERE idusuario = :id AND tipo = 'redefinir_senha' AND usado_em IS NULL")->execute([':id' => $token['idusuario']]);
            $pdo->commit();
            $success = true;
            $token = null;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Não foi possível redefinir a senha. Solicite um novo link.';
        }
    }
}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/loginsignup.css')); ?>"><title>Nova senha | StrideBR</title></head><body><div class="container-fluid"><div class="auth-layout"><header><a href="/"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" class="logo" width="116" height="46"></a><h2>Nova senha</h2></header><div class="form"><span class="title">Redefinir senha</span><?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?><?php if ($success): ?><div class="alert alert-success">Senha alterada. As sessões anteriores foram invalidadas.</div><div class="login-signup"><a href="/login.php">Entrar com a nova senha</a></div><?php elseif (!$enabled): ?><div class="alert alert-info">A recuperação automática de senha está desativada.</div><?php elseif (!$token): ?><div class="alert alert-danger">Esse link é inválido ou expirou.</div><div class="login-signup"><a href="/forgot-password.php">Solicitar outro link</a></div><?php else: ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="token" value="<?php echo stridebr_e($tokenRaw); ?>"><div class="input-field"><input type="password" name="senha" autocomplete="new-password" placeholder="Nova senha" minlength="8" required></div><div class="input-field"><input type="password" name="confirmar" autocomplete="new-password" placeholder="Confirme a nova senha" minlength="8" required></div><div class="input-field button"><input type="submit" value="Salvar nova senha"></div></form><?php endif; ?></div></div></div></body></html>
