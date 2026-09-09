<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/includes/auth.php';

$enabled = stridebr_auth_password_reset_enabled($pdo);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    if (!$enabled) {
        $errors[] = stridebr_t('auth.reset_unavailable');
    } else {
        $email = stridebr_lower(trim((string) ($_POST['email'] ?? '')));
        $ip = stridebr_client_ip() ?? '';
        $emailValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false && stridebr_length($email) <= 255;
        $blocked = ($ip !== '' && stridebr_auth_limit_is_blocked($pdo, 'forgot-ip', $ip))
            || ($emailValid && stridebr_auth_limit_is_blocked($pdo, 'forgot-email', $email));
        $userId = '';

        if (!$blocked) {
            if ($ip !== '') stridebr_auth_limit_record_attempt($pdo, 'forgot-ip', $ip, 10, 1800, 1800);
            if ($emailValid) {
                stridebr_auth_limit_record_attempt($pdo, 'forgot-email', $email, 3, 1800, 1800);
                $stmt = $pdo->prepare("SELECT idusuario, emailusuario, COALESCE(NULLIF(nome_exibicao,''), nomeusuario) AS nome_exibicao FROM usuarios WHERE lower(emailusuario) = lower(:email) AND statususuario = 'Ativo' LIMIT 1");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();
                if ($user) {
                    $userId = (string) $user['idusuario'];
                    try {
                        stridebr_send_password_reset_email($pdo, $userId, (string) $user['emailusuario'], (string) $user['nome_exibicao']);
                    } catch (Throwable $e) {
                        error_log('StrideBR password reset mail failed: ' . get_class($e));
                    }
                }
            }
            stridebr_auth_limit_cleanup($pdo);
        }

        stridebr_auth_clear_password_reset_session();
        stridebr_auth_set_pending_password_reset($email, $userId);
        header('Location: /reset-password.php');
        exit;
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
    <title><?php echo stridebr_e(stridebr_t('auth.forgot_title')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body class="onboarding-body signup-onboarding-body auth-unified-body">
    <div class="onboarding-shell signup-onboarding-shell auth-unified-shell">
        <a class="onboarding-brand" href="/"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" width="110" height="43"></a>
        <main class="onboarding-card auth-unified-card">
            <div class="auth-unified-heading">
                <h1><?php echo stridebr_e(stridebr_t('auth.forgot_title')); ?></h1>
                <p id="recovery-guidance"><?php echo stridebr_e(stridebr_t('auth.forgot_guidance_code')); ?></p>
            </div>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger" role="alert"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
            <?php if (!$enabled): ?>
                <div class="alert alert-info"><?php echo stridebr_e(stridebr_t('auth.reset_unavailable')); ?></div>
            <?php else: ?>
                <form method="POST" class="auth-modern-form">
                    <?php echo stridebr_csrf_field(); ?>
                    <label class="auth-modern-field"><?php echo stridebr_e(stridebr_t('auth.email')); ?>
                        <input type="email" name="email" aria-describedby="recovery-guidance" autocomplete="email" maxlength="255" required>
                    </label>
                    <button class="auth-modern-submit" type="submit"><?php echo stridebr_e(stridebr_t('auth.send_code')); ?></button>
                </form>
            <?php endif; ?>
            <div class="auth-modern-footer"><a href="/login.php"><?php echo stridebr_e(stridebr_t('auth.back_to_login')); ?></a></div>
        </main>
    </div>
</body>
</html>
