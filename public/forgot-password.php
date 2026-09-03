<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/includes/auth.php';

$enabled = stridebr_auth_password_reset_enabled($pdo);
$sentMessage = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    if (!$enabled) {
        $errors[] = 'A recuperação por e-mail está indisponível no momento.';
    } else {
        $email = stridebr_lower(trim((string) ($_POST['email'] ?? '')));
        $ip = stridebr_client_ip() ?? '';
        $emailValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false && stridebr_length($email) <= 255;
        $blocked = ($ip !== '' && stridebr_auth_limit_is_blocked($pdo, 'forgot-ip', $ip))
            || ($emailValid && stridebr_auth_limit_is_blocked($pdo, 'forgot-email', $email));

        if (!$blocked) {
            if ($ip !== '') {
                stridebr_auth_limit_record_attempt($pdo, 'forgot-ip', $ip, 10, 1800, 1800);
            }
            if ($emailValid) {
                stridebr_auth_limit_record_attempt($pdo, 'forgot-email', $email, 3, 1800, 1800);
                $stmt = $pdo->prepare("SELECT idusuario, emailusuario, COALESCE(NULLIF(nome_exibicao,''), nomeusuario) AS nome_exibicao FROM usuarios WHERE lower(emailusuario) = lower(:email) AND statususuario = 'Ativo' LIMIT 1");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();
                if ($user) {
                    try {
                        stridebr_send_password_reset_email($pdo, $user['idusuario'], $user['emailusuario'], $user['nome_exibicao']);
                    } catch (Throwable $e) {
                        error_log('StrideBR password reset mail failed: ' . $e->getMessage());
                    }
                }
            }
            stridebr_auth_limit_cleanup($pdo);
        }
        $sentMessage = true;
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
    <title>Recuperar senha | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body class="auth-modern-body">
    <div class="auth-modern-shell">
        <a class="auth-modern-brand" href="/"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" width="110" height="43"></a>
        <main class="auth-modern-card">
            <h1>Recuperar senha</h1>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
            <?php if ($sentMessage): ?><div class="alert alert-success">Se o e-mail estiver em uma conta ativa, enviaremos um link de redefinição.</div><?php endif; ?>
            <?php if (!$enabled): ?>
                <div class="alert alert-info">A recuperação de senha está indisponível no momento.</div>
            <?php else: ?>
                <form method="POST" class="auth-modern-form">
                    <?php echo stridebr_csrf_field(); ?>
                    <label class="auth-modern-field">E-mail
                        <input type="email" name="email" autocomplete="email" maxlength="255" required>
                    </label>
                    <button class="auth-modern-submit" type="submit">Enviar link</button>
                </form>
            <?php endif; ?>
            <div class="auth-modern-footer"><a href="/login.php">Voltar para entrar</a></div>
        </main>
    </div>
</body>
</html>
