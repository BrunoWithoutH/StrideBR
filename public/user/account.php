<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/layout/settings_workspace.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/includes/auth.php';

$errors = [];
$isImpersonating = is_array($_SESSION['OwnerImpersonation'] ?? null);
$sessionTracking = stridebr_session_tracking_available($pdo);
$passwordDateAvailable = stridebr_db_column_exists($pdo, 'usuarios', 'senha_alterada_em');

$pendingEmail = $_SESSION['PendingEmailChange'] ?? null;
if (!is_array($pendingEmail) || (int) ($pendingEmail['created_at'] ?? 0) < time() - 1800 || (string) ($pendingEmail['user_id'] ?? '') !== $idUsuario) {
    unset($_SESSION['PendingEmailChange']);
    $pendingEmail = null;
}

$loadCurrentAccount = static function () use ($pdo, $idUsuario, $passwordDateAvailable): ?array {
    $extra = $passwordDateAvailable ? ', senha_alterada_em' : '';
    $stmt = $pdo->prepare('SELECT emailusuario, verificado, email_verificado_em, dataregistrousuario, termos_versao, privacidade_versao, papelusuario, senhausuario, sessao_versao' . $extra . ' FROM usuarios WHERE idusuario = :id LIMIT 1');
    $stmt->execute([':id' => $idUsuario]);
    $row = $stmt->fetch();
    return $row ?: null;
};

$usuario = $loadCurrentAccount();
if (!$usuario) {
    stridebr_destroy_session();
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($isImpersonating) {
        $errors[] = stridebr_t('account.test_mode_blocked');
    } elseif ($action === 'change_password') {
        $current = (string) ($_POST['senha_atual'] ?? '');
        $password = (string) ($_POST['nova_senha'] ?? '');
        $confirm = (string) ($_POST['confirmar_senha'] ?? '');
        $hash = (string) ($usuario['senhausuario'] ?? '');
        if ($hash === '' || !password_verify($current, $hash)) $errors[] = stridebr_t('account.current_password_wrong');
        if (!stridebr_password_is_valid_length($password, 8, 128)) $errors[] = stridebr_t('account.password_length_error');
        if ($password !== $confirm) $errors[] = stridebr_t('account.password_mismatch');
        if ($current === $password && $password !== '') $errors[] = stridebr_t('account.password_same');
        if ($errors === []) {
            $dateSql = $passwordDateAvailable ? ', senha_alterada_em = NOW()' : '';
            $stmt = $pdo->prepare('UPDATE usuarios SET senhausuario = :senha, sessao_versao = sessao_versao + 1' . $dateSql . ' WHERE idusuario = :id RETURNING sessao_versao');
            $stmt->execute([':senha' => stridebr_password_hash($password), ':id' => $idUsuario]);
            $_SESSION['SessaoVersao'] = (int) $stmt->fetchColumn();
            $_SESSION['SessionGuardCheckedAt'] = time();
            $pdo->prepare("UPDATE auth_tokens SET usado_em = NOW() WHERE idusuario = :id AND tipo = 'redefinir_senha' AND usado_em IS NULL")->execute([':id' => $idUsuario]);
            stridebr_session_revoke_others($pdo, $idUsuario);
            stridebr_flash('success', stridebr_t('account.password_changed'));
            header('Location: /user/account.php#senha');
            exit;
        }
    } elseif ($action === 'logout_others') {
        $stmt = $pdo->prepare('UPDATE usuarios SET sessao_versao = sessao_versao + 1 WHERE idusuario = :id RETURNING sessao_versao');
        $stmt->execute([':id' => $idUsuario]);
        $_SESSION['SessaoVersao'] = (int) $stmt->fetchColumn();
        $_SESSION['SessionGuardCheckedAt'] = time();
        stridebr_session_revoke_others($pdo, $idUsuario);
        stridebr_flash('success', stridebr_t('account.other_sessions_ended'));
        header('Location: /user/account.php#sessoes');
        exit;
    } elseif ($action === 'revoke_session') {
        $hash = strtolower(trim((string) ($_POST['sessao_hash'] ?? '')));
        if (stridebr_session_revoke($pdo, $idUsuario, $hash)) {
            stridebr_flash('success', stridebr_t('account.session_ended'));
        } else {
            stridebr_flash('info', stridebr_t('account.session_unavailable'));
        }
        header('Location: /user/account.php#sessoes');
        exit;
    } elseif ($action === 'request_email_change') {
        $newEmail = stridebr_lower(trim((string) ($_POST['novo_email'] ?? '')));
        $currentPassword = (string) ($_POST['senha_email'] ?? '');
        if (!stridebr_mail_is_configured()) $errors[] = stridebr_t('account.email_change_mail_unavailable');
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || stridebr_length($newEmail) > 255) $errors[] = stridebr_t('account.invalid_new_email');
        if ($newEmail !== '' && hash_equals(stridebr_lower((string) $usuario['emailusuario']), $newEmail)) $errors[] = stridebr_t('account.same_email');
        if (!password_verify($currentPassword, (string) $usuario['senhausuario'])) $errors[] = stridebr_t('account.current_password_wrong');
        if ($errors === []) {
            $exists = $pdo->prepare('SELECT 1 FROM usuarios WHERE lower(emailusuario) = lower(:email) AND idusuario <> :id LIMIT 1');
            $exists->execute([':email' => $newEmail, ':id' => $idUsuario]);
            if ($exists->fetchColumn()) $errors[] = stridebr_t('account.email_in_use');
        }
        if ($errors === []) {
            try {
                $name = stridebr_display_name();
                if (!stridebr_send_email_change_code($pdo, $idUsuario, $newEmail, $name)) {
                    throw new RuntimeException(stridebr_t('account.code_send_error'));
                }
                $_SESSION['PendingEmailChange'] = ['user_id' => $idUsuario, 'email' => $newEmail, 'old_email' => (string) $usuario['emailusuario'], 'created_at' => time()];
                stridebr_flash('success', stridebr_t('account.code_sent'));
                header('Location: /user/account.php#email');
                exit;
            } catch (Throwable $e) {
                $errors[] = $e instanceof RuntimeException ? $e->getMessage() : stridebr_t('account.email_change_start_error');
            }
        }
    } elseif ($action === 'confirm_email_change') {
        $pendingEmail = $_SESSION['PendingEmailChange'] ?? null;
        $code = preg_replace('/\D+/', '', (string) ($_POST['codigo_email'] ?? '')) ?? '';
        if (!is_array($pendingEmail) || (string) ($pendingEmail['user_id'] ?? '') !== $idUsuario || (int) ($pendingEmail['created_at'] ?? 0) < time() - 1800) {
            $errors[] = stridebr_t('account.request_expired');
        } elseif (!preg_match('/^[0-9]{6}$/', $code)) {
            $errors[] = stridebr_t('account.enter_six_digit_code');
        } elseif (stridebr_auth_limit_is_blocked($pdo, 'change-email-code', $idUsuario)) {
            $errors[] = stridebr_t('account.too_many_attempts');
        } else {
            $token = stridebr_auth_find_token($pdo, $code, 'verificar_email');
            $newEmail = stridebr_lower((string) ($pendingEmail['email'] ?? ''));
            $valid = $token && hash_equals((string) $token['idusuario'], $idUsuario) && hash_equals(stridebr_lower((string) $token['email_destino']), $newEmail);
            if (!$valid) {
                stridebr_auth_limit_record_failure($pdo, 'change-email-code', $idUsuario, 8, 900, 900);
                $errors[] = stridebr_t('account.code_invalid');
            } else {
                $pdo->beginTransaction();
                try {
                    $exists = $pdo->prepare('SELECT 1 FROM usuarios WHERE lower(emailusuario) = lower(:email) AND idusuario <> :id LIMIT 1 FOR UPDATE');
                    $exists->execute([':email' => $newEmail, ':id' => $idUsuario]);
                    if ($exists->fetchColumn()) throw new InvalidArgumentException(stridebr_t('account.email_in_use'));
                    $use = $pdo->prepare('UPDATE auth_tokens SET usado_em = NOW() WHERE idtoken = :id AND usado_em IS NULL AND expira_em > NOW()');
                    $use->execute([':id' => $token['idtoken']]);
                    if ($use->rowCount() !== 1) throw new InvalidArgumentException(stridebr_t('account.code_invalid'));
                    $update = $pdo->prepare('UPDATE usuarios SET emailusuario = :email, verificado = TRUE, email_verificado_em = NOW(), sessao_versao = sessao_versao + 1 WHERE idusuario = :id RETURNING sessao_versao');
                    $update->execute([':email' => $newEmail, ':id' => $idUsuario]);
                    $newVersion = (int) $update->fetchColumn();
                    $pdo->prepare('UPDATE auth_tokens SET usado_em = NOW() WHERE idusuario = :id AND usado_em IS NULL')->execute([':id' => $idUsuario]);
                    $pdo->commit();
                    $_SESSION['EmailUsuario'] = $newEmail;
                    $_SESSION['SessaoVersao'] = $newVersion;
                    $_SESSION['SessionGuardCheckedAt'] = time();
                    stridebr_session_revoke_others($pdo, $idUsuario);
                    stridebr_auth_limit_clear($pdo, 'change-email-code', $idUsuario);
                    $oldEmail = (string) ($pendingEmail['old_email'] ?? '');
                    unset($_SESSION['PendingEmailChange']);
                    if (filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
                        stridebr_send_mail($oldEmail, stridebr_t('account.email_change_notice_subject'), stridebr_t('account.email_change_notice_body', ['email' => $newEmail]));
                    }
                    stridebr_flash('success', stridebr_t('account.email_changed'));
                    header('Location: /user/account.php#email');
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : stridebr_t('account.email_confirm_error');
                }
            }
        }
    } elseif ($action === 'resend_email_change') {
        $pendingEmail = $_SESSION['PendingEmailChange'] ?? null;
        if (!is_array($pendingEmail) || (string) ($pendingEmail['user_id'] ?? '') !== $idUsuario) {
            $errors[] = stridebr_t('account.no_pending_email_change');
        } else {
            try {
                $sent = stridebr_send_email_change_code($pdo, $idUsuario, (string) $pendingEmail['email'], stridebr_display_name());
                if (!$sent) throw new RuntimeException(stridebr_t('account.resend_error'));
                stridebr_flash('success', stridebr_t('account.code_resent'));
                header('Location: /user/account.php#email');
                exit;
            } catch (Throwable $e) {
                $errors[] = $e instanceof RuntimeException ? $e->getMessage() : stridebr_t('account.resend_code_error');
            }
        }
    } elseif ($action === 'cancel_email_change') {
        unset($_SESSION['PendingEmailChange']);
        $pdo->prepare("UPDATE auth_tokens SET usado_em = NOW() WHERE idusuario = :id AND tipo = 'verificar_email' AND usado_em IS NULL")->execute([':id' => $idUsuario]);
        stridebr_flash('info', stridebr_t('account.email_change_cancelled'));
        header('Location: /user/account.php#email');
        exit;
    }
}

$usuario = $loadCurrentAccount();
if (!$usuario) {
    stridebr_destroy_session();
    header('Location: /login.php');
    exit;
}
$pendingEmail = $_SESSION['PendingEmailChange'] ?? null;
if (!is_array($pendingEmail)) $pendingEmail = null;
$emailVerified = stridebr_db_bool($usuario['verificado'] ?? false) || !empty($usuario['email_verificado_em']);
$passwordResetEnabled = stridebr_auth_password_reset_enabled($pdo);
$emailVerificationEnabled = stridebr_auth_email_verification_enabled($pdo);
$sessions = [];
$currentSessionHash = stridebr_session_hash();
if ($sessionTracking) {
    try {
        $stmt = $pdo->prepare('SELECT sessao_hash, criado_em, ultimo_uso_em, ip::text AS ip, user_agent, revogado_em FROM sessoes_usuario WHERE idusuario = :id ORDER BY (sessao_hash = :current) DESC, (revogado_em IS NULL) DESC, ultimo_uso_em DESC LIMIT 20');
        $stmt->execute([':id' => $idUsuario, ':current' => $currentSessionHash]);
        $sessions = $stmt->fetchAll();
    } catch (Throwable) {
        $sessions = [];
        $sessionTracking = false;
    }
}
$sessionGroups = ['current' => [], 'recent' => [], 'history' => []];
foreach ($sessions as $session) {
    $isCurrent = hash_equals((string) $currentSessionHash, (string) $session['sessao_hash']);
    $recent = empty($session['revogado_em']) && strtotime((string) $session['ultimo_uso_em']) >= time() - max(1, (int) ini_get('session.gc_maxlifetime'));
    $sessionGroups[$isCurrent ? 'current' : ($recent ? 'recent' : 'history')][] = $session;
}
$deviceLabel = static function (?string $agent): array {
    $agent = (string) $agent;
    $browser = str_contains($agent, 'Firefox/') ? 'Firefox' : (str_contains($agent, 'Edg/') ? 'Edge' : (str_contains($agent, 'Chrome/') ? 'Chrome' : (str_contains($agent, 'Safari/') ? 'Safari' : 'Navegador')));
    $os = str_contains($agent, 'Android') ? 'Android' : (str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') ? 'iOS' : (str_contains($agent, 'Windows') ? 'Windows' : (str_contains($agent, 'Linux') ? 'Linux' : (str_contains($agent, 'Mac OS') ? 'macOS' : 'dispositivo'))));
    return [$browser, $os];
};
$flashes = stridebr_take_flashes();
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t('account.page_title')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell settings-shell">
            <?php stridebr_settings_workspace_heading(stridebr_t('account.page_title'), stridebr_t('account.page_help'), '/user/perfil.php'); ?>
            <?php stridebr_settings_workspace_navigation('account'); ?>
            <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>

            <section class="content-card account-overview-card">
                <div class="account-overview-icon" aria-hidden="true">@</div>
                <div><span><?php echo stridebr_e(stridebr_t('account.account_label')); ?></span><strong><?php echo stridebr_e($usuario['emailusuario']); ?></strong><small><?php echo stridebr_e(stridebr_t($emailVerified ? 'account.email_confirmed' : 'account.email_pending')); ?> · <?php echo stridebr_e(stridebr_role_label((string) ($usuario['papelusuario'] ?? 'user'))); ?></small></div>
                <?php if (!$emailVerified && $emailVerificationEnabled): ?><a class="secondary-button" href="/verify-email.php"><?php echo stridebr_e(stridebr_t('account.confirm_email')); ?></a><?php endif; ?>
            </section>

            <div class="account-settings-grid">
                <section class="content-card account-settings-card" id="email">
                    <div class="account-settings-heading"><div><span class="account-settings-kicker"><?php echo stridebr_e(stridebr_t('account.identity')); ?></span><h2><?php echo stridebr_e(stridebr_t('account.access_email')); ?></h2><p><?php echo stridebr_e(stridebr_t('account.email_change_help')); ?></p></div></div>
                    <?php if ($pendingEmail): ?>
                        <div class="account-pending-email"><strong><?php echo stridebr_e(stridebr_t('account.confirm')); ?> <?php echo stridebr_e((string) $pendingEmail['email']); ?></strong><span><?php echo stridebr_e(stridebr_t('account.code_help')); ?></span></div>
                        <form method="POST" class="account-password-form">
                            <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="confirm_email_change">
                            <label><?php echo stridebr_e(stridebr_t('account.confirmation_code')); ?><input type="text" name="codigo_email" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required></label>
                            <div class="account-form-actions"><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('account.confirm_new_email')); ?></button></div>
                        </form>
                        <div class="account-inline-buttons"><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="resend_email_change"><button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('account.resend_code')); ?></button></form><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="cancel_email_change"><button type="submit" class="text-button"><?php echo stridebr_e(stridebr_t('account.cancel_change')); ?></button></form></div>
                    <?php else: ?>
                        <form method="POST" class="account-password-form">
                            <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="request_email_change">
                            <label><?php echo stridebr_e(stridebr_t('account.new_email')); ?><input type="email" name="novo_email" autocomplete="email" maxlength="255" placeholder="voce@exemplo.com" required></label>
                            <label><?php echo stridebr_e(stridebr_t('account.current_password')); ?><span class="password-field"><input type="password" id="account-email-password" name="senha_email" autocomplete="current-password" maxlength="128" required><button type="button" class="showHidePw" aria-controls="account-email-password" aria-pressed="false" aria-label="<?php echo stridebr_e(stridebr_t('auth.password_visibility')); ?>" data-show-label="<?php echo stridebr_e(stridebr_t('auth.show_password')); ?>" data-hide-label="<?php echo stridebr_e(stridebr_t('auth.hide_password')); ?>"><?php echo stridebr_e(stridebr_t('auth.show_password')); ?></button></span></label>
                            <div class="account-form-actions"><button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('account.send_code')); ?></button></div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="content-card account-settings-card" id="senha">
                    <div class="account-settings-heading"><div><span class="account-settings-kicker"><?php echo stridebr_e(stridebr_t('account.access')); ?></span><h2><?php echo stridebr_e(stridebr_t('account.change_password')); ?></h2><p><?php echo stridebr_e(stridebr_t('account.password_help')); ?></p></div></div>
                    <form method="POST" class="account-password-form">
                        <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="change_password">
                        <label><?php echo stridebr_e(stridebr_t('account.current_password')); ?><span class="password-field"><input type="password" id="account-current-password" name="senha_atual" autocomplete="current-password" maxlength="128" required><button type="button" class="showHidePw" aria-controls="account-current-password" aria-pressed="false" aria-label="<?php echo stridebr_e(stridebr_t('auth.password_visibility')); ?>" data-show-label="<?php echo stridebr_e(stridebr_t('auth.show_password')); ?>" data-hide-label="<?php echo stridebr_e(stridebr_t('auth.hide_password')); ?>"><?php echo stridebr_e(stridebr_t('auth.show_password')); ?></button></span></label>
                        <div class="account-password-row"><label><?php echo stridebr_e(stridebr_t('account.new_password')); ?><span class="password-field"><input type="password" id="account-new-password" name="nova_senha" autocomplete="new-password" minlength="8" maxlength="128" required><button type="button" class="showHidePw" aria-controls="account-new-password" aria-pressed="false" aria-label="<?php echo stridebr_e(stridebr_t('auth.password_visibility')); ?>" data-show-label="<?php echo stridebr_e(stridebr_t('auth.show_password')); ?>" data-hide-label="<?php echo stridebr_e(stridebr_t('auth.hide_password')); ?>"><?php echo stridebr_e(stridebr_t('auth.show_password')); ?></button></span></label><label><?php echo stridebr_e(stridebr_t('account.confirm_password')); ?><span class="password-field"><input type="password" id="account-confirm-password" name="confirmar_senha" autocomplete="new-password" minlength="8" maxlength="128" required><button type="button" class="showHidePw" aria-controls="account-confirm-password" aria-pressed="false" aria-label="<?php echo stridebr_e(stridebr_t('auth.password_visibility')); ?>" data-show-label="<?php echo stridebr_e(stridebr_t('auth.show_password')); ?>" data-hide-label="<?php echo stridebr_e(stridebr_t('auth.hide_password')); ?>"><?php echo stridebr_e(stridebr_t('auth.show_password')); ?></button></span></label></div>
                        <div class="account-form-actions"><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('account.change_password')); ?></button><?php if ($passwordResetEnabled): ?><a href="/forgot-password.php"><?php echo stridebr_e(stridebr_t('account.forgot_current_password')); ?></a><?php endif; ?></div>
                    </form>
                    <?php if ($passwordDateAvailable && !empty($usuario['senha_alterada_em'])): ?><small class="account-security-meta"><?php echo stridebr_e(stridebr_t('account.last_change')); ?> <?php echo stridebr_e(stridebr_t('account.last_change_at', ['date' => stridebr_format_datetime_short((string) $usuario['senha_alterada_em'])])); ?></small><?php endif; ?>
                </section>

                <section class="content-card account-settings-card account-span-full" id="sessoes">
                    <div class="account-settings-heading"><div><span class="account-settings-kicker"><?php echo stridebr_e(stridebr_t('account.security')); ?></span><h2><?php echo stridebr_e(stridebr_t('account.sessions_devices')); ?></h2><p><?php echo stridebr_e(stridebr_t('account.sessions_help')); ?></p></div></div>
                    <form method="POST" class="account-inline-action">
                        <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="logout_others">
                        <div><strong><?php echo stridebr_e(stridebr_t('account.end_other_sessions')); ?></strong><span><?php echo stridebr_e(stridebr_t('account.this_device_stays')); ?></span></div>
                        <button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('account.end_others')); ?></button>
                    </form>
                    <?php if (!$sessionTracking): ?>
                        <div class="account-session-note"><?php echo stridebr_e(stridebr_t('account.session_tracking_pending')); ?></div>
                    <?php elseif ($sessions === []): ?>
                        <div class="account-session-note"><?php echo stridebr_e(stridebr_t('account.no_sessions')); ?></div>
                    <?php else: ?>
                        <p class="account-session-note"><?php echo stridebr_e(stridebr_t('account.sessions_activity_help')); ?></p>
                        <?php foreach ($sessionGroups as $group => $groupSessions): if ($groupSessions === []) continue; ?>
                        <?php if ($group === 'history'): ?><details class="account-session-history"><summary><?php echo stridebr_e(stridebr_t('account.sessions_history')); ?> (<?php echo count($groupSessions); ?>)</summary><?php else: ?><h3><?php echo stridebr_e(stridebr_t('account.sessions_' . $group)); ?></h3><?php endif; ?>
                        <div class="account-session-list">
                            <?php foreach ($groupSessions as $session): ?>
                                <?php [$browser, $os] = $deviceLabel($session['user_agent'] ?? null); $isCurrent = hash_equals((string) ($currentSessionHash ?? ''), (string) $session['sessao_hash']); $revoked = !empty($session['revogado_em']); ?>
                                <article class="account-session-item<?php echo $isCurrent ? ' is-current' : ($revoked ? ' is-revoked' : ''); ?>">
                                    <div><strong><?php echo stridebr_e($browser . ' · ' . $os); ?><?php echo $isCurrent ? ' · ' . stridebr_e(stridebr_t('account.this_device')) : ''; ?></strong><span><?php echo stridebr_e(stridebr_t('account.session_last_activity')); ?> · <?php echo stridebr_e((new DateTimeImmutable((string) $session['ultimo_uso_em']))->format('d/m/Y H:i')); ?><?php echo !empty($session['ip']) ? ' · ' . stridebr_e((string) $session['ip']) : ''; ?><?php echo $revoked ? ' · ' . stridebr_e(stridebr_t('account.session_closed')) : ''; ?></span></div>
                                    <?php if (!$isCurrent && !$revoked): ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="revoke_session"><input type="hidden" name="sessao_hash" value="<?php echo stridebr_e((string) $session['sessao_hash']); ?>"><button type="submit" class="secondary-button compact"><?php echo stridebr_e(stridebr_t('account.end')); ?></button></form><?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($group === 'history'): ?></details><?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <section class="content-card account-settings-card account-span-full" id="dados-conta">
                    <div class="account-settings-heading"><div><span class="account-settings-kicker"><?php echo stridebr_e(stridebr_t('account.data')); ?></span><h2><?php echo stridebr_e(stridebr_t('account.data_account')); ?></h2><p><?php echo stridebr_e(stridebr_t('account.data_help')); ?></p></div></div>
                    <div class="settings-data-actions">
                        <article><div><strong><?php echo stridebr_e(stridebr_t('account.export_data')); ?></strong><span><?php echo stridebr_e(stridebr_t('account.export_help')); ?></span></div><form method="POST" action="/user/export-data.php"><?php echo stridebr_csrf_field(); ?><button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('account.download_export')); ?></button></form></article>
                        <article><div><strong><?php echo stridebr_e(stridebr_t('account.delete_account')); ?></strong><span><?php echo stridebr_e(stridebr_t('account.delete_help')); ?></span></div><a class="danger-button" href="/user/delete-account.php"><?php echo stridebr_e(stridebr_t('account.manage_deletion')); ?></a></article>
                    </div>
                </section>

                <section class="content-card account-settings-card account-span-full">
                    <div class="account-settings-heading"><div><span class="account-settings-kicker"><?php echo stridebr_e(stridebr_t('account.information')); ?></span><h2><?php echo stridebr_e(stridebr_t('account.status')); ?></h2></div></div>
                    <dl class="account-definition-list">
                        <div><dt><?php echo stridebr_e(stridebr_t('auth.email')); ?></dt><dd><?php echo stridebr_e(stridebr_t($emailVerified ? 'account.verified' : 'account.not_verified')); ?><?php if (!$emailVerified && $emailVerificationEnabled): ?> · <a href="/verify-email.php"><?php echo stridebr_e(stridebr_t('account.confirm')); ?></a><?php endif; ?></dd></div>
                        <div><dt><?php echo stridebr_e(stridebr_t('account.created')); ?></dt><dd><?php echo stridebr_e(stridebr_format_date((string) $usuario['dataregistrousuario'])); ?></dd></div>
                        <div><dt><?php echo stridebr_e(stridebr_t('account.terms_accepted')); ?></dt><dd><a href="/pages/legal/terms.php"><?php echo stridebr_e($usuario['termos_versao'] ?? stridebr_t('account.previous_version')); ?></a></dd></div>
                        <div><dt><?php echo stridebr_e(stridebr_t('account.privacy_policy')); ?></dt><dd><a href="/pages/legal/privacy.php"><?php echo stridebr_e($usuario['privacidade_versao'] ?? stridebr_t('account.previous_version')); ?></a></dd></div>
                    </dl>
                </section>
            </div>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/loginform.js')); ?>"></script>
</body>
</html>
