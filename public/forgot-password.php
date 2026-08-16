<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/includes/auth.php';

$enabled = stridebr_feature_enabled($pdo, 'auth.password_reset.enabled', false);
$sentMessage = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    if (!$enabled) {
        $errors[] = 'A recuperação automática de senha ainda não está ativa.';
    } elseif (!stridebr_rate_limit('forgot-password', 3, 1800)) {
        $sentMessage = true;
    } else {
        $email = stridebr_lower(trim((string) ($_POST['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
        $sentMessage = true;
    }
}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/loginsignup.css')); ?>"><title>Recuperar senha | StrideBR</title></head><body><div class="container-fluid"><div class="auth-layout"><header><a href="/"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" class="logo" width="116" height="46"></a><h2>Recuperar acesso</h2></header><div class="form"><span class="title">Esqueci minha senha</span><?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?><?php if ($sentMessage): ?><div class="alert alert-success">Se existir uma conta ativa com esse e-mail e o envio estiver configurado, você receberá um link de redefinição.</div><?php endif; ?><?php if (!$enabled): ?><div class="alert alert-info">Este recurso está preparado, mas desativado pelo administrador durante a alpha.</div><?php else: ?><form method="POST"><?php echo stridebr_csrf_field(); ?><div class="input-field"><input type="email" name="email" autocomplete="email" placeholder="Seu e-mail" required></div><div class="input-field button"><input type="submit" value="Enviar link"></div></form><?php endif; ?><div class="login-signup"><span class="text"><a href="/login.php">Voltar para entrar</a></span></div></div></div></div></body></html>
