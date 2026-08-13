<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
use Hidehalo\Nanoid\Client;

if (stridebr_is_logged_in()) {
    header('Location: /home.php');
    exit;
}

$errors = [];
$values = [
    'nome' => '',
    'email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    require_once dirname(__DIR__) . '/src/config/pg_config.php';

    $values['nome'] = trim((string) ($_POST['NomeUsuario'] ?? ''));
    $values['email'] = stridebr_lower(trim((string) ($_POST['EmailUsuario'] ?? '')));
    $password = (string) ($_POST['SenhaUsuario'] ?? '');
    $confirm = (string) ($_POST['ConfirmarSenhaUsuario'] ?? '');
    $accepted = isset($_POST['TermosUsuario']);

    if ($values['nome'] === '' || stridebr_length($values['nome']) > 255) {
        $errors[] = 'Informe um nome válido.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || stridebr_length($values['email']) > 255) {
        $errors[] = 'Informe um e-mail válido.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
    }
    if ($password !== $confirm) {
        $errors[] = 'As senhas não coincidem.';
    }
    if (!$accepted) {
        $errors[] = 'Você precisa aceitar os Termos de Uso.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE lower(emailusuario) = lower(:email) LIMIT 1');
        $stmt->execute([':email' => $values['email']]);
        if ($stmt->fetchColumn()) {
            $errors[] = 'Já existe uma conta com este e-mail.';
        }
    }

    if ($errors === []) {
        $client = new Client();
        try {
            $stmt = $pdo->prepare('INSERT INTO usuarios (idusuario, nomeusuario, emailusuario, senhausuario, ipregistro) VALUES (:id, :nome, :email, :senha, :ip)');
            $stmt->execute([
                ':id' => $client->generateId(21),
                ':nome' => $values['nome'],
                ':email' => $values['email'],
                ':senha' => password_hash($password, PASSWORD_DEFAULT),
                ':ip' => stridebr_client_ip(),
            ]);
            stridebr_flash('success', 'Conta criada. Agora você já pode entrar.');
            header('Location: /login.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                $errors[] = 'Já existe uma conta com este e-mail.';
            } else {
                throw $e;
            }
        }
    }
}
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
    <title>Cadastro | StrideBR</title>
</head>
<body>
    <div class="container-fluid">
        <header>
            <a href="/"><img src="/assets/img/logos/stridebr-logo-black.png" alt="StrideBR" class="logo"></a>
            <h2>Sua jornada começa aqui</h2>
        </header>
        <div class="row">
            <div class="forms">
                <div class="form">
                    <span class="title">Cadastre-se</span>
                    <?php foreach ($errors as $error): ?>
                        <div class="alert alert-danger"><?php echo stridebr_e($error); ?></div>
                    <?php endforeach; ?>
                    <form action="/signup.php" method="POST">
                        <?php echo stridebr_csrf_field(); ?>
                        <div class="input-field">
                            <input type="text" name="NomeUsuario" value="<?php echo stridebr_e($values['nome']); ?>" autocomplete="name" placeholder="Insira seu nome" maxlength="255" required>
                            <i class="uil uil-user"></i>
                        </div>
                        <div class="input-field">
                            <input type="email" name="EmailUsuario" value="<?php echo stridebr_e($values['email']); ?>" autocomplete="email" placeholder="Insira seu email" maxlength="255" required>
                            <i class="uil uil-envelope icon"></i>
                        </div>
                        <div class="input-field">
                            <input type="password" name="SenhaUsuario" class="password" autocomplete="new-password" placeholder="Crie uma senha" minlength="8" required>
                            <i class="uil uil-lock icon"></i>
                            <i class="uil uil-eye-slash showHidePw" role="button" tabindex="0" aria-label="Mostrar ou ocultar senha"></i>
                        </div>
                        <div class="input-field">
                            <input type="password" name="ConfirmarSenhaUsuario" class="password" autocomplete="new-password" placeholder="Confirme sua senha" minlength="8" required>
                            <i class="uil uil-lock icon"></i>
                            <i class="uil uil-eye-slash showHidePw" role="button" tabindex="0" aria-label="Mostrar ou ocultar senha"></i>
                        </div>
                        <div class="checkbox-text">
                            <div class="checkbox-content">
                                <input type="checkbox" id="termCon" name="TermosUsuario" required>
                                <label for="termCon" class="text">Li e aceito os <a href="/pages/legal/terms.php">Termos de Uso</a></label>
                            </div>
                        </div>
                        <div class="input-field button">
                            <input type="submit" name="submit" value="Cadastrar">
                        </div>
                        <div class="login-signup">
                            <span class="text">Já tem uma conta? <a href="/login.php">Entrar</a></span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="/assets/js/loginform.js"></script>
</body>
</html>
