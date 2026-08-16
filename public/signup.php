<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/includes/auth.php';

if (stridebr_is_logged_in()) {
    header('Location: /home.php');
    exit;
}

$registrationEnabled = stridebr_feature_enabled($pdo, 'registration.enabled', true);
$inviteOnly = stridebr_feature_enabled($pdo, 'registration.invite_only.enabled', false);
$emailVerificationEnabled = stridebr_feature_enabled($pdo, 'auth.email_verification.enabled', false);
$errors = [];
$values = [
    'nome' => '',
    'email' => '',
    'invite' => trim((string) ($_GET['invite'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    if (!$registrationEnabled) {
        $errors[] = 'Novos cadastros estão temporariamente fechados.';
    }

    $values['nome'] = trim((string) ($_POST['NomeUsuario'] ?? ''));
    $values['email'] = stridebr_lower(trim((string) ($_POST['EmailUsuario'] ?? '')));
    $values['invite'] = strtoupper(trim((string) ($_POST['CodigoConvite'] ?? '')));
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
        $errors[] = 'Você precisa aceitar os Termos de Uso e a Política de Privacidade.';
    }
    if ($inviteOnly && $values['invite'] === '') {
        $errors[] = 'Esta alpha está com cadastro por convite.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE lower(emailusuario) = lower(:email) LIMIT 1');
        $stmt->execute([':email' => $values['email']]);
        if ($stmt->fetchColumn()) {
            $errors[] = 'Já existe uma conta com este e-mail.';
        }
    }

    $inviteId = null;
    if ($errors === [] && $inviteOnly) {
        $inviteStmt = $pdo->prepare("SELECT idconvite FROM convites_alpha WHERE codigo_hash = :hash AND ativo = TRUE AND usos < usos_maximos AND (expira_em IS NULL OR expira_em > NOW()) LIMIT 1");
        $inviteStmt->execute([':hash' => hash('sha256', $values['invite'])]);
        $inviteId = $inviteStmt->fetchColumn();
        if ($inviteId === false) {
            $errors[] = 'Convite inválido, expirado ou já utilizado.';
            $inviteId = null;
        }
    }

    if ($errors === []) {
        $id = stridebr_generate_id();
        $pdo->beginTransaction();
        try {
            if ($inviteOnly && is_string($inviteId)) {
                $consume = $pdo->prepare("UPDATE convites_alpha SET usos = usos + 1, ativo = CASE WHEN usos + 1 >= usos_maximos THEN FALSE ELSE ativo END WHERE idconvite = :id AND ativo = TRUE AND usos < usos_maximos AND (expira_em IS NULL OR expira_em > NOW())");
                $consume->execute([':id' => $inviteId]);
                if ($consume->rowCount() !== 1) {
                    throw new RuntimeException('O convite não está mais disponível.');
                }
            }

            $stmt = $pdo->prepare('INSERT INTO usuarios (idusuario, nomeusuario, nome_exibicao, emailusuario, senhausuario, ipregistro, termos_versao, privacidade_versao, termos_aceitos_em) VALUES (:id, :nome, :nome_exibicao, :email, :senha, :ip, :termos, :privacidade, NOW())');
            $stmt->execute([
                ':id' => $id,
                ':nome' => $values['nome'],
                ':nome_exibicao' => $values['nome'],
                ':email' => $values['email'],
                ':senha' => password_hash($password, PASSWORD_DEFAULT),
                ':ip' => stridebr_client_ip(),
                ':termos' => stridebr_terms_version(),
                ':privacidade' => stridebr_privacy_version(),
            ]);
            $pdo->commit();

            if ($emailVerificationEnabled) {
                $sent = stridebr_send_verification_email($pdo, $id, $values['email'], $values['nome']);
                stridebr_flash($sent ? 'success' : 'warning', $sent ? 'Conta criada. Enviamos um link para confirmar seu e-mail.' : 'Conta criada. O envio da verificação de e-mail não foi concluído; tente novamente depois ou fale com um administrador.');
            } else {
                stridebr_flash('success', 'Conta criada. Agora você já pode entrar.');
            }
            header('Location: /login.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof PDOException && $e->getCode() === '23505') {
                $errors[] = 'Já existe uma conta com este e-mail.';
            } elseif ($e instanceof RuntimeException) {
                $errors[] = $e->getMessage();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/loginsignup.css')); ?>">
    <title>Cadastro | StrideBR</title>
</head>
<body>
    <div class="container-fluid">
        <header>
            <a href="/"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" class="logo" width="116" height="46" decoding="async"></a>
            <h2>Sua jornada começa aqui</h2>
        </header>
        <div class="auth-layout">
            <div class="forms">
                <div class="form">
                    <span class="title">Cadastre-se</span>
                    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
                    <?php if (!$registrationEnabled): ?>
                        <div class="alert alert-info">A criação de novas contas está fechada no momento. Contas já existentes continuam funcionando.</div>
                    <?php else: ?>
                    <form action="/signup.php" method="POST">
                        <?php echo stridebr_csrf_field(); ?>
                        <div class="input-field"><input type="text" name="NomeUsuario" value="<?php echo stridebr_e($values['nome']); ?>" autocomplete="name" placeholder="Insira seu nome" maxlength="255" required></div>
                        <div class="input-field"><input type="email" name="EmailUsuario" value="<?php echo stridebr_e($values['email']); ?>" autocomplete="email" placeholder="Insira seu email" maxlength="255" required></div>
                        <?php if ($inviteOnly): ?><div class="input-field"><input type="text" name="CodigoConvite" value="<?php echo stridebr_e($values['invite']); ?>" autocomplete="off" placeholder="Código de convite" maxlength="80" required></div><?php endif; ?>
                        <div class="input-field"><input type="password" name="SenhaUsuario" class="password" autocomplete="new-password" placeholder="Crie uma senha" minlength="8" required><button type="button" class="showHidePw" aria-label="Mostrar senha">Mostrar</button></div>
                        <div class="input-field"><input type="password" name="ConfirmarSenhaUsuario" class="password" autocomplete="new-password" placeholder="Confirme sua senha" minlength="8" required><button type="button" class="showHidePw" aria-label="Mostrar senha">Mostrar</button></div>
                        <div class="checkbox-text"><div class="checkbox-content"><input type="checkbox" id="termCon" name="TermosUsuario" required><label for="termCon" class="text">Li e aceito os <a href="/pages/legal/terms.php" target="_blank" rel="noopener">Termos de Uso</a> e a <a href="/pages/legal/privacy.php" target="_blank" rel="noopener">Política de Privacidade</a>.</label></div></div>
                        <p class="auth-alpha-note">O StrideBR está em alpha fechada. Funcionalidades e dados de teste podem mudar durante o desenvolvimento.</p>
                        <div class="input-field button"><input type="submit" name="submit" value="Cadastrar"></div>
                        <div class="login-signup"><span class="text">Já tem uma conta? <a href="/login.php">Entrar</a></span></div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="<?php echo stridebr_e(stridebr_asset('/assets/js/loginform.js')); ?>"></script>
</body>
</html>
