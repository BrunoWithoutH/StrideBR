<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/includes/auth.php';

$enabled = stridebr_auth_password_reset_enabled($pdo);
$tokenRaw = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$token = $enabled ? stridebr_auth_find_token($pdo, $tokenRaw, 'redefinir_senha') : null;
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token) {
    stridebr_verify_csrf();
    $password = (string) ($_POST['senha'] ?? '');
    $confirm = (string) ($_POST['confirmar'] ?? '');
    if (!stridebr_password_is_valid_length($password, 8, 128)) $errors[] = 'A senha deve ter entre 8 e 128 caracteres.';
    if ($password !== $confirm) $errors[] = 'As senhas não coincidem.';
    if ($errors === []) {
        $pdo->beginTransaction();
        try {
            $consume = $pdo->prepare('UPDATE auth_tokens SET usado_em = NOW() WHERE idtoken = :id AND usado_em IS NULL AND expira_em > NOW()');
            $consume->execute([':id' => $token['idtoken']]);
            if ($consume->rowCount() !== 1) throw new RuntimeException('Token inválido.');
            $passwordDateSql = stridebr_db_column_exists($pdo, 'usuarios', 'senha_alterada_em') ? ', senha_alterada_em = NOW()' : '';
            $update = $pdo->prepare('UPDATE usuarios SET senhausuario = :senha, sessao_versao = sessao_versao + 1' . $passwordDateSql . ' WHERE idusuario = :id');
            $update->execute([':senha' => stridebr_password_hash($password), ':id' => $token['idusuario']]);
            stridebr_session_revoke_all($pdo, (string) $token['idusuario']);
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
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/loginsignup.css')); ?>">
    <title>Nova senha | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body class="auth-modern-body">
    <div class="auth-modern-shell">
        <a class="auth-modern-brand" href="/"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" width="110" height="43"></a>
        <main class="auth-modern-card">
            <h1>Nova senha</h1>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">Senha alterada. As sessões anteriores foram encerradas.</div>
                <div class="auth-modern-footer"><a href="/login.php">Entrar</a></div>
            <?php elseif (!$enabled): ?>
                <div class="alert alert-info">A recuperação por e-mail está indisponível no momento.</div>
            <?php elseif (!$token): ?>
                <div class="alert alert-danger">Esse link é inválido ou expirou.</div>
                <div class="auth-modern-footer"><a href="/forgot-password.php">Solicitar outro link</a></div>
            <?php else: ?>
                <form method="POST" class="auth-modern-form">
                    <?php echo stridebr_csrf_field(); ?>
                    <input type="hidden" name="token" value="<?php echo stridebr_e($tokenRaw); ?>">
                    <label class="auth-modern-field">Nova senha
                        <input type="password" name="senha" autocomplete="new-password" minlength="8" maxlength="128" required>
                    </label>
                    <label class="auth-modern-field">Confirmar senha
                        <input type="password" name="confirmar" autocomplete="new-password" minlength="8" maxlength="128" required>
                    </label>
                    <button class="auth-modern-submit" type="submit">Salvar nova senha</button>
                </form>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
