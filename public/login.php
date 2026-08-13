<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';

if (stridebr_is_logged_in()) {
    header('Location: /home.php');
    exit;
}

$redirect = stridebr_safe_redirect(isset($_GET['redirect']) ? (string) $_GET['redirect'] : null, '/home.php');
$flashes = stridebr_take_flashes();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/assets/img/favicon/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/loginsignup.css">
    <title>Entrar | StrideBR</title>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <header>
                <a href="/"><img src="/assets/img/logos/stridebr-logo-black.png" alt="StrideBR" class="logo"></a>
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
                        <i class="uil uil-envelope icon"></i>
                    </div>
                    <div class="input-field">
                        <input type="password" name="USenha" class="password" autocomplete="current-password" placeholder="Insira sua senha" required>
                        <i class="uil uil-lock icon"></i>
                        <i class="uil uil-eye-slash showHidePw" role="button" tabindex="0" aria-label="Mostrar ou ocultar senha"></i>
                    </div>
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
    <script src="/assets/js/loginform.js"></script>
</body>
</html>
