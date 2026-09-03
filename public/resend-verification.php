<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/includes/auth.php';

$enabled = stridebr_auth_email_verification_enabled($pdo);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    if (!$enabled) {
        $errors[] = 'A verificação por e-mail está indisponível no momento.';
    } else {
        $email = stridebr_lower(trim((string) ($_POST['email'] ?? '')));
        $ip = stridebr_client_ip() ?? '';
        $emailValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false && stridebr_length($email) <= 255;
        $blocked = ($ip !== '' && stridebr_auth_limit_is_blocked($pdo, 'verify-ip', $ip))
            || ($emailValid && stridebr_auth_limit_is_blocked($pdo, 'verify-email', $email));

        if (!$blocked && $emailValid) {
            if ($ip !== '') stridebr_auth_limit_record_attempt($pdo, 'verify-ip', $ip, 10, 1800, 1800);
            stridebr_auth_limit_record_attempt($pdo, 'verify-email', $email, 3, 1800, 1800);
            $stmt = $pdo->prepare("SELECT idusuario, emailusuario, COALESCE(NULLIF(nome_exibicao,''), nomeusuario) AS nome_exibicao, verificado, email_verificado_em FROM usuarios WHERE lower(emailusuario) = lower(:email) AND statususuario = 'Ativo' LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            if ($user && !stridebr_db_bool($user['verificado']) && empty($user['email_verificado_em'])) {
                stridebr_auth_set_pending_verification((string) $user['idusuario'], (string) $user['emailusuario']);
                try {
                    stridebr_send_verification_email($pdo, (string) $user['idusuario'], (string) $user['emailusuario'], (string) $user['nome_exibicao']);
                } catch (RuntimeException $e) {
                    stridebr_flash('info', $e->getMessage());
                } catch (Throwable $e) {
                    error_log('StrideBR verification mail failed: ' . $e->getMessage());
                    stridebr_flash('warning', 'Não foi possível enviar outro código agora.');
                }
                header('Location: /verify-email.php');
                exit;
            }
            stridebr_auth_limit_cleanup($pdo);
        }
        stridebr_flash('info', 'Se a conta existir e ainda precisar de confirmação, você poderá continuar a verificação ao entrar nela.');
        header('Location: /login.php');
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
    <title>Reenviar código | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body class="auth-modern-body">
    <div class="auth-modern-shell">
        <a class="auth-modern-brand" href="/"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" width="110" height="43"></a>
        <main class="auth-modern-card">
            <h1>Reenviar código</h1>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
            <?php if (!$enabled): ?>
                <div class="alert alert-info">A verificação de e-mail está indisponível no momento.</div>
            <?php else: ?>
                <form method="POST" class="auth-modern-form">
                    <?php echo stridebr_csrf_field(); ?>
                    <label class="auth-modern-field">E-mail
                        <input type="email" name="email" autocomplete="email" maxlength="255" required>
                    </label>
                    <button class="auth-modern-submit" type="submit">Continuar</button>
                </form>
            <?php endif; ?>
            <div class="auth-modern-footer"><a href="/login.php">Voltar para entrar</a></div>
        </main>
    </div>
</body>
</html>
