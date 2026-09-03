<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/includes/auth.php';

$enabled = stridebr_auth_email_verification_enabled($pdo);
$pending = stridebr_auth_pending_verification();
if (!$pending && stridebr_is_logged_in()) {
    $currentUser = stridebr_auth_load_user($pdo, (string) ($_SESSION['IdUsuario'] ?? ''));
    if ($currentUser && !stridebr_db_bool($currentUser['verificado'] ?? false) && empty($currentUser['email_verificado_em'])) {
        stridebr_auth_set_pending_verification((string) $currentUser['idusuario'], (string) $currentUser['emailusuario']);
        $pending = stridebr_auth_pending_verification();
    }
}
$errors = [];
$notice = null;
$success = false;
$verifiedWithoutSession = false;

$maskEmail = static function (string $email): string {
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($local === '' || $domain === '') return $email;
    $visible = stridebr_length($local) <= 2 ? substr($local, 0, 1) : substr($local, 0, 2);
    return $visible . str_repeat('•', max(3, stridebr_length($local) - strlen($visible))) . '@' . $domain;
};

$finishVerification = static function (array $token) use ($pdo, &$success): bool {
    if (($token['statususuario'] ?? '') !== 'Ativo') return false;
    if (!hash_equals(stridebr_lower((string) $token['emailusuario']), stridebr_lower((string) $token['email_destino']))) return false;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE auth_tokens SET usado_em = NOW() WHERE idtoken = :id AND usado_em IS NULL AND expira_em > NOW()');
        $stmt->execute([':id' => $token['idtoken']]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Código inválido.');
        $pdo->prepare('UPDATE usuarios SET verificado = TRUE, email_verificado_em = NOW() WHERE idusuario = :id')->execute([':id' => $token['idusuario']]);
        $pdo->commit();
        $success = true;
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return false;
    }
};

$rawFromLink = trim((string) ($_GET['token'] ?? ''));
if ($rawFromLink !== '') {
    $token = stridebr_auth_find_token($pdo, $rawFromLink, 'verificar_email');
    if ($token && $finishVerification($token)) {
        if ($pending && hash_equals((string) $pending['user_id'], (string) $token['idusuario'])) {
            $redirect = (string) $pending['redirect'];
            $user = stridebr_auth_load_user($pdo, (string) $token['idusuario']);
            if ($user) {
                stridebr_auth_start_session($pdo, $user, stridebr_client_ip() ?? '');
                stridebr_flash('success', 'E-mail confirmado. Sua conta está pronta.');
                header('Location: ' . (!stridebr_db_bool($user['onboarding_concluido'] ?? false) ? '/user/onboarding.php' : $redirect));
                exit;
            }
        }
        $verifiedWithoutSession = true;
    } else {
        $errors[] = 'Esse código ou link é inválido ou expirou.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? 'verify');
    $pending = stridebr_auth_pending_verification();

    if (!$enabled) {
        $errors[] = 'A verificação por e-mail está indisponível no momento.';
    } elseif (!$pending) {
        $errors[] = 'Entre com sua conta novamente para continuar a verificação.';
    } elseif ($action === 'resend') {
        $user = stridebr_auth_load_user($pdo, (string) $pending['user_id']);
        if (!$user || ($user['statususuario'] ?? '') !== 'Ativo' || !hash_equals(stridebr_lower((string) $user['emailusuario']), (string) $pending['email'])) {
            stridebr_auth_clear_pending_verification();
            $errors[] = 'Não foi possível continuar a verificação desta conta.';
        } elseif (stridebr_db_bool($user['verificado'] ?? false) || !empty($user['email_verificado_em'])) {
            $notice = 'Esse e-mail já está confirmado.';
        } else {
            try {
                $sent = stridebr_send_verification_email($pdo, (string) $user['idusuario'], (string) $user['emailusuario'], trim((string) ($user['nome_exibicao'] ?? '')) ?: (string) $user['nomeusuario']);
                $notice = $sent ? 'Enviamos um novo código. Confira sua caixa de entrada.' : 'Não foi possível enviar o código agora.';
            } catch (RuntimeException $e) {
                $notice = $e->getMessage();
            } catch (Throwable $e) {
                error_log('StrideBR verification resend failed: ' . $e->getMessage());
                $errors[] = 'Não foi possível enviar outro código agora.';
            }
        }
    } else {
        $code = preg_replace('/\D+/', '', (string) ($_POST['code'] ?? '')) ?? '';
        $ip = stridebr_client_ip() ?? '';
        $scopeKey = (string) $pending['user_id'];
        $blocked = stridebr_auth_limit_is_blocked($pdo, 'verify-code-user', $scopeKey)
            || ($ip !== '' && stridebr_auth_limit_is_blocked($pdo, 'verify-code-ip', $ip));
        if ($blocked) {
            $errors[] = 'Muitas tentativas de código. Aguarde alguns minutos e tente novamente.';
        } elseif (!preg_match('/^[0-9]{6}$/', $code)) {
            $errors[] = 'Digite o código de 6 números enviado ao seu e-mail.';
        } else {
            $token = stridebr_auth_find_token($pdo, $code, 'verificar_email');
            $validForPending = $token
                && hash_equals((string) $pending['user_id'], (string) $token['idusuario'])
                && hash_equals((string) $pending['email'], stridebr_lower((string) $token['email_destino']));
            if (!$validForPending || !$finishVerification($token)) {
                stridebr_auth_limit_record_failure($pdo, 'verify-code-user', $scopeKey, 8, 900, 900);
                if ($ip !== '') stridebr_auth_limit_record_failure($pdo, 'verify-code-ip', $ip, 20, 900, 900);
                $errors[] = 'Código incorreto ou expirado.';
            } else {
                stridebr_auth_limit_clear($pdo, 'verify-code-user', $scopeKey);
                if ($ip !== '') stridebr_auth_limit_clear($pdo, 'verify-code-ip', $ip);
                $redirect = (string) $pending['redirect'];
                $user = stridebr_auth_load_user($pdo, (string) $pending['user_id']);
                if (!$user) {
                    $errors[] = 'E-mail confirmado, mas não foi possível iniciar a sessão.';
                } else {
                    stridebr_auth_start_session($pdo, $user, $ip);
                    stridebr_flash('success', 'E-mail confirmado. Bem-vindo ao StrideBR.');
                    header('Location: ' . (!stridebr_db_bool($user['onboarding_concluido'] ?? false) ? '/user/onboarding.php' : $redirect));
                    exit;
                }
            }
        }
    }
}

$flashes = stridebr_take_flashes();
$pending = stridebr_auth_pending_verification();
$maskedEmail = $pending ? $maskEmail((string) $pending['email']) : '';
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
    <title>Confirmar e-mail | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body class="auth-modern-body">
<div class="auth-modern-shell">
    <a class="auth-modern-brand" href="/"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" width="110" height="43"></a>
    <main class="auth-modern-card">
        <h1>Confirmar e-mail</h1>
        <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
        <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
        <?php if ($notice): ?><div class="alert alert-info"><?php echo stridebr_e($notice); ?></div><?php endif; ?>
        <?php if ($verifiedWithoutSession): ?>
            <div class="alert alert-success">E-mail confirmado.</div>
            <div class="auth-modern-footer"><a href="/login.php">Entrar</a></div>
        <?php elseif (!$enabled): ?>
            <div class="alert alert-info">A verificação está indisponível no momento.</div>
            <div class="auth-modern-footer"><a href="/login.php">Voltar para entrar</a></div>
        <?php elseif (!$pending): ?>
            <div class="auth-modern-footer"><a href="/login.php">Voltar para entrar</a></div>
        <?php else: ?>
            <p class="auth-modern-note">Código enviado para <strong><?php echo stridebr_e($maskedEmail); ?></strong>.</p>
            <form method="POST" class="auth-modern-form verification-code-form">
                <?php echo stridebr_csrf_field(); ?>
                <input type="hidden" name="action" value="verify">
                <label class="auth-modern-field" for="verification-code">Código
                    <input id="verification-code" class="auth-modern-code-input" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" data-verification-code autofocus required>
                </label>
                <button type="submit" class="auth-modern-submit">Confirmar</button>
            </form>
            <form method="POST" class="auth-modern-resend">
                <?php echo stridebr_csrf_field(); ?>
                <input type="hidden" name="action" value="resend">
                <button type="submit">Enviar outro código</button>
            </form>
            <div class="auth-modern-footer"><a href="/login.php">Usar outra conta</a></div>
        <?php endif; ?>
    </main>
</div>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/loginform.js')); ?>"></script>
</body>
</html>
