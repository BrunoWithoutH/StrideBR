<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/includes/auth.php';

$enabled = stridebr_auth_password_reset_enabled($pdo);
$errors = [];
$notice = null;
$success = false;
$pending = stridebr_auth_pending_password_reset();
$authorization = stridebr_auth_password_reset_session();
$ip = stridebr_client_ip() ?? '';

$consumeResetToken = static function (array $token) use ($pdo): bool {
    if (($token['statususuario'] ?? '') !== 'Ativo') return false;
    $consume = $pdo->prepare('UPDATE auth_tokens SET usado_em = NOW() WHERE idtoken = :id AND usado_em IS NULL AND expira_em > NOW()');
    $consume->execute([':id' => $token['idtoken']]);
    return $consume->rowCount() === 1;
};

$legacyToken = trim((string) ($_GET['token'] ?? ''));
if ($enabled && $legacyToken !== '' && preg_match('/^[a-f0-9]{64}$/i', $legacyToken)) {
    $token = stridebr_auth_find_token($pdo, $legacyToken, 'redefinir_senha');
    if ($token && $consumeResetToken($token)) {
        stridebr_auth_start_password_reset_session((string) $token['idusuario'], (string) $token['idtoken']);
        stridebr_auth_clear_pending_password_reset();
        header('Location: /reset-password.php');
        exit;
    }
    $errors[] = stridebr_t('auth.reset_code_invalid');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? 'verify_code');
    $pending = stridebr_auth_pending_password_reset();
    $authorization = stridebr_auth_password_reset_session();

    if (!$enabled) {
        $errors[] = stridebr_t('auth.reset_unavailable');
    } elseif ($action === 'resend') {
        if (!$pending) {
            $errors[] = stridebr_t('auth.reset_restart');
        } else {
            $email = (string) ($pending['email'] ?? '');
            $blocked = ($ip !== '' && stridebr_auth_limit_is_blocked($pdo, 'forgot-ip', $ip))
                || ($email !== '' && stridebr_auth_limit_is_blocked($pdo, 'forgot-email', $email));
            if (!$blocked && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                if ($ip !== '') stridebr_auth_limit_record_attempt($pdo, 'forgot-ip', $ip, 10, 1800, 1800);
                stridebr_auth_limit_record_attempt($pdo, 'forgot-email', $email, 3, 1800, 1800);
                $stmt = $pdo->prepare("SELECT idusuario, emailusuario, COALESCE(NULLIF(nome_exibicao,''), nomeusuario) AS nome_exibicao FROM usuarios WHERE lower(emailusuario) = lower(:email) AND statususuario = 'Ativo' LIMIT 1");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();
                if ($user) {
                    try {
                        stridebr_send_password_reset_email($pdo, (string) $user['idusuario'], (string) $user['emailusuario'], (string) $user['nome_exibicao']);
                        stridebr_auth_set_pending_password_reset($email, (string) $user['idusuario']);
                    } catch (Throwable $e) {
                        error_log('StrideBR password reset resend failed: ' . get_class($e));
                    }
                }
            }
            $notice = stridebr_t('auth.reset_code_resent_generic');
        }
    } elseif ($action === 'verify_code') {
        if (!$pending) {
            $errors[] = stridebr_t('auth.reset_restart');
        } else {
            $code = preg_replace('/\D+/', '', (string) ($_POST['code'] ?? '')) ?? '';
            $flowKey = hash('sha256', (string) ($pending['email'] ?? '') . '|' . session_id());
            $blocked = stridebr_auth_limit_is_blocked($pdo, 'reset-code-flow', $flowKey)
                || ($ip !== '' && stridebr_auth_limit_is_blocked($pdo, 'reset-code-ip', $ip));
            if ($blocked) {
                $errors[] = stridebr_t('auth.reset_code_too_many');
            } elseif (!preg_match('/^[0-9]{6}$/', $code)) {
                $errors[] = stridebr_t('auth.reset_code_six_digits');
            } else {
                $token = stridebr_auth_find_token($pdo, $code, 'redefinir_senha');
                $valid = $token
                    && (string) ($pending['user_id'] ?? '') !== ''
                    && hash_equals((string) $pending['user_id'], (string) $token['idusuario'])
                    && hash_equals((string) ($pending['email'] ?? ''), stridebr_lower((string) $token['email_destino']));
                if (!$valid || !$consumeResetToken($token)) {
                    stridebr_auth_limit_record_failure($pdo, 'reset-code-flow', $flowKey, 6, 900, 900);
                    if ($ip !== '') stridebr_auth_limit_record_failure($pdo, 'reset-code-ip', $ip, 20, 900, 900);
                    $errors[] = stridebr_t('auth.reset_code_invalid');
                } else {
                    stridebr_auth_limit_clear($pdo, 'reset-code-flow', $flowKey);
                    if ($ip !== '') stridebr_auth_limit_clear($pdo, 'reset-code-ip', $ip);
                    stridebr_auth_start_password_reset_session((string) $token['idusuario'], (string) $token['idtoken']);
                    stridebr_auth_clear_pending_password_reset();
                    header('Location: /reset-password.php');
                    exit;
                }
            }
        }
    } elseif ($action === 'reset_password') {
        if (!$authorization) {
            $errors[] = stridebr_t('auth.reset_session_expired');
        } else {
            $password = (string) ($_POST['senha'] ?? '');
            $confirm = (string) ($_POST['confirmar'] ?? '');
            if (!stridebr_password_is_valid_length($password, 8, 128)) $errors[] = stridebr_t('auth.password_length_error');
            if ($password !== $confirm) $errors[] = stridebr_t('auth.passwords_do_not_match');
            if ($errors === []) {
                $pdo->beginTransaction();
                try {
                    $userId = (string) $authorization['user_id'];
                    $passwordDateSql = stridebr_db_column_exists($pdo, 'usuarios', 'senha_alterada_em') ? ', senha_alterada_em = NOW()' : '';
                    $update = $pdo->prepare('UPDATE usuarios SET senhausuario = :senha, sessao_versao = sessao_versao + 1' . $passwordDateSql . " WHERE idusuario = :id AND statususuario = 'Ativo'");
                    $update->execute([':senha' => stridebr_password_hash($password), ':id' => $userId]);
                    if ($update->rowCount() !== 1) throw new RuntimeException('reset-user-unavailable');
                    stridebr_session_revoke_all($pdo, $userId);
                    $pdo->prepare("UPDATE auth_tokens SET usado_em = NOW() WHERE idusuario = :id AND tipo = 'redefinir_senha' AND usado_em IS NULL")->execute([':id' => $userId]);
                    $pdo->commit();
                    stridebr_auth_clear_password_reset_session();
                    stridebr_auth_clear_pending_password_reset();
                    session_regenerate_id(true);
                    $authorization = null;
                    $success = true;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = stridebr_t('auth.reset_save_error');
                }
            }
        }
    }
}

$pending = stridebr_auth_pending_password_reset();
$authorization = stridebr_auth_password_reset_session();
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
    <title><?php echo stridebr_e(stridebr_t($authorization ? 'auth.new_password_title' : 'auth.reset_code_title')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body class="onboarding-body signup-onboarding-body auth-unified-body">
    <div class="onboarding-shell signup-onboarding-shell auth-unified-shell">
        <a class="onboarding-brand" href="/"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" width="110" height="43"></a>
        <main class="onboarding-card auth-unified-card">
            <div class="auth-unified-heading">
                <h1><?php echo stridebr_e(stridebr_t($authorization ? 'auth.new_password_title' : 'auth.reset_code_title')); ?></h1>
                <?php if (!$authorization && !$success): ?><p><?php echo stridebr_e(stridebr_t('auth.reset_code_guidance')); ?></p><?php endif; ?>
            </div>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger" role="alert"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
            <?php if ($notice): ?><div class="alert alert-info" role="status"><?php echo stridebr_e($notice); ?></div><?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success" role="status"><?php echo stridebr_e(stridebr_t('auth.reset_success')); ?></div>
                <div class="auth-modern-footer"><a href="/login.php"><?php echo stridebr_e(stridebr_t('auth.login')); ?></a></div>
            <?php elseif (!$enabled): ?>
                <div class="alert alert-info"><?php echo stridebr_e(stridebr_t('auth.reset_unavailable')); ?></div>
            <?php elseif ($authorization): ?>
                <form method="POST" class="auth-modern-form">
                    <?php echo stridebr_csrf_field(); ?>
                    <input type="hidden" name="action" value="reset_password">
                    <label class="auth-modern-field"><?php echo stridebr_e(stridebr_t('auth.new_password')); ?>
                        <span class="auth-modern-field-wrap">
                            <input type="password" id="reset-password" name="senha" class="password" autocomplete="new-password" minlength="8" maxlength="128" required autofocus>
                            <button type="button" class="showHidePw" aria-controls="reset-password" aria-pressed="false" aria-label="<?php echo stridebr_e(stridebr_t('auth.password_visibility')); ?>" data-show-label="<?php echo stridebr_e(stridebr_t('auth.show_password')); ?>" data-hide-label="<?php echo stridebr_e(stridebr_t('auth.hide_password')); ?>"><?php echo stridebr_e(stridebr_t('auth.show_password')); ?></button>
                        </span>
                    </label>
                    <label class="auth-modern-field"><?php echo stridebr_e(stridebr_t('auth.confirm_password')); ?>
                        <span class="auth-modern-field-wrap">
                            <input type="password" id="reset-password-confirm" name="confirmar" class="password" autocomplete="new-password" minlength="8" maxlength="128" required>
                            <button type="button" class="showHidePw" aria-controls="reset-password-confirm" aria-pressed="false" aria-label="<?php echo stridebr_e(stridebr_t('auth.password_visibility')); ?>" data-show-label="<?php echo stridebr_e(stridebr_t('auth.show_password')); ?>" data-hide-label="<?php echo stridebr_e(stridebr_t('auth.hide_password')); ?>"><?php echo stridebr_e(stridebr_t('auth.show_password')); ?></button>
                        </span>
                    </label>
                    <button class="auth-modern-submit" type="submit"><?php echo stridebr_e(stridebr_t('auth.save_new_password')); ?></button>
                </form>
            <?php elseif ($pending): ?>
                <div class="alert alert-info" role="status"><?php echo stridebr_e(stridebr_t('auth.reset_request_generic')); ?></div>
                <form method="POST" class="auth-modern-form verification-code-form">
                    <?php echo stridebr_csrf_field(); ?>
                    <input type="hidden" name="action" value="verify_code">
                    <label class="auth-modern-field" for="reset-code"><?php echo stridebr_e(stridebr_t('auth.code')); ?>
                        <input id="reset-code" class="auth-modern-code-input" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" autofocus required>
                    </label>
                    <button type="submit" class="auth-modern-submit"><?php echo stridebr_e(stridebr_t('auth.confirm_code')); ?></button>
                </form>
                <form method="POST" class="auth-modern-resend">
                    <?php echo stridebr_csrf_field(); ?>
                    <input type="hidden" name="action" value="resend">
                    <button type="submit"><?php echo stridebr_e(stridebr_t('auth.resend_code')); ?></button>
                </form>
                <div class="auth-modern-footer"><a href="/forgot-password.php"><?php echo stridebr_e(stridebr_t('auth.use_another_email')); ?></a></div>
            <?php else: ?>
                <div class="alert alert-info"><?php echo stridebr_e(stridebr_t('auth.reset_restart')); ?></div>
                <div class="auth-modern-footer"><a href="/forgot-password.php"><?php echo stridebr_e(stridebr_t('auth.request_new_code')); ?></a></div>
            <?php endif; ?>
        </main>
    </div>
    <script src="<?php echo stridebr_e(stridebr_asset('/assets/js/loginform.js')); ?>"></script>
</body>
</html>
