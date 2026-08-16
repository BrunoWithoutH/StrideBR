<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';

if (stridebr_is_logged_in()) {
    header('Location: /home.php');
    exit;
}

$redirect = stridebr_safe_redirect(isset($_GET['redirect']) ? (string) $_GET['redirect'] : null, '/home.php');
require_once dirname(__DIR__) . '/src/config/pg_config.php';
$passwordResetEnabled = stridebr_feature_enabled($pdo, 'auth.password_reset.enabled', false);
$emailVerificationEnabled = stridebr_feature_enabled($pdo, 'auth.email_verification.enabled', false);
$flashes = stridebr_take_flashes();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/loginsignup.css')); ?>">
    <title>Entrar | StrideBR</title>
</head>
<body>
    <div class="container-fluid">
        <div class="auth-layout">
            <header>
                <a href="/"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" class="logo" width="116" height="46" decoding="async"></a>
                <h2>Sua jornada começa aqui</h2>
            </header>
            <div class="form">
                <span class="title">Entrar</span>
                <?php foreach ($flashes as $flash): ?>
                    <div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div>
                <?php endforeach; ?>
                <form action="/function/testlogin.php" method="POST">
                    <?php echo stridebr_csrf_field(); ?>
                    <input type="hidden" name="redirect" value="<?php echo stridebr_e($redirect); ?>">
                    <div class="input-field">
                        <input type="email" name="UEmail" autocomplete="email" placeholder="Insira seu email" required>

                    </div>
                    <div class="input-field">
                        <input type="password" name="USenha" class="password" autocomplete="current-password" placeholder="Insira sua senha" required>

                        <button type="button" class="showHidePw" aria-label="Mostrar senha">Mostrar</button>
                    </div>
                    <?php if ($passwordResetEnabled || $emailVerificationEnabled): ?><div class="auth-inline-links"><?php if ($passwordResetEnabled): ?><a href="/forgot-password.php">Esqueci minha senha</a><?php endif; ?><?php if ($emailVerificationEnabled): ?><a href="/resend-verification.php">Reenviar verificação</a><?php endif; ?></div><?php endif; ?>
                    <div class="input-field button">
                        <input type="submit" name="submit" value="Entrar">
                    </div>
                    <div class="login-signup">
                        <span class="text">Não tem uma conta? <a href="/signup.php">Cadastre-se agora</a></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="<?php echo stridebr_e(stridebr_asset('/assets/js/loginform.js')); ?>"></script>
</body>
</html>
