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
require_once dirname(__DIR__) . '/src/includes/auth.php';
$passwordResetEnabled = stridebr_auth_password_reset_enabled($pdo);
$googleAuthEnabled = stridebr_auth_google_enabled();
$flashes = stridebr_take_flashes();
?>
<!DOCTYPE html>
<html lang="<?php echo stridebr_e(stridebr_html_lang()); ?>" data-theme="<?php echo stridebr_e(stridebr_theme()); ?>" data-locale="<?php echo stridebr_e(stridebr_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php echo stridebr_ui_boot_script(); ?>
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/loginsignup.css')); ?>">

    <title><?php echo stridebr_e(stridebr_t('auth.login')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body class="onboarding-body signup-onboarding-body auth-unified-body">
    <div class="onboarding-shell signup-onboarding-shell auth-unified-shell">
        <a class="onboarding-brand" href="/"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" width="110" height="43"></a>
        <main class="onboarding-card auth-unified-card">
            <div class="auth-unified-heading">
                <h1><?php echo stridebr_e(stridebr_t('auth.login_title')); ?></h1>
                <p><?php echo stridebr_e(stridebr_t('auth.login_subtitle')); ?></p>
            </div>
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div>
            <?php endforeach; ?>
            <?php if ($googleAuthEnabled): ?>
                <a class="auth-google-button" href="/auth/google.php?redirect=<?php echo rawurlencode($redirect); ?>"><span class="auth-google-mark" aria-hidden="true">G</span><span><?php echo stridebr_e(stridebr_t('auth.google')); ?></span></a>
                <div class="auth-divider"><span><?php echo stridebr_e(stridebr_t('auth.or')); ?></span></div>
            <?php endif; ?>
            <form action="/function/testlogin.php" method="POST" class="auth-modern-form">
                <?php echo stridebr_csrf_field(); ?>
                <input type="hidden" name="redirect" value="<?php echo stridebr_e($redirect); ?>">
                <label class="auth-modern-field"><?php echo stridebr_e(stridebr_t('auth.email')); ?>
                    <input type="email" name="UEmail" autocomplete="email" required>
                </label>
                <label class="auth-modern-field"><?php echo stridebr_e(stridebr_t('auth.password')); ?>
                    <span class="auth-modern-field-wrap">
                        <input type="password" name="USenha" class="password" autocomplete="current-password" maxlength="128" required>
                        <button type="button" class="showHidePw" aria-label="<?php echo stridebr_e(stridebr_t('auth.show_password')); ?>"><?php echo stridebr_e(stridebr_t('auth.show_password')); ?></button>
                    </span>
                </label>
                <div class="auth-modern-links">
                    <span></span>
                    <?php if ($passwordResetEnabled): ?><a href="/forgot-password.php"><?php echo stridebr_e(stridebr_t('auth.forgot_password')); ?></a><?php endif; ?>
                </div>
                <button class="auth-modern-submit" type="submit" name="submit"><?php echo stridebr_e(stridebr_t('auth.login')); ?></button>
            </form>
            <div class="auth-modern-footer"><?php echo stridebr_e(stridebr_t('auth.no_account')); ?> <a href="/signup.php"><?php echo stridebr_e(stridebr_t('auth.create_account')); ?></a></div>
        </main>
    </div>
    <script src="<?php echo stridebr_e(stridebr_asset('/assets/js/loginform.js')); ?>"></script>
    <script src="<?php echo stridebr_e(stridebr_asset('/assets/js/ui-preferences.js')); ?>"></script>
    <?php echo stridebr_i18n_runtime_script(false); ?>
</body>
</html>
