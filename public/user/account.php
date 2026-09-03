<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

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
        $errors[] = 'Ações de segurança da conta ficam bloqueadas durante o modo de teste.';
    } elseif ($action === 'change_password') {
        $current = (string) ($_POST['senha_atual'] ?? '');
        $password = (string) ($_POST['nova_senha'] ?? '');
        $confirm = (string) ($_POST['confirmar_senha'] ?? '');
        $hash = (string) ($usuario['senhausuario'] ?? '');
        if ($hash === '' || !password_verify($current, $hash)) $errors[] = 'A senha atual está incorreta.';
        if (!stridebr_password_is_valid_length($password, 8, 128)) $errors[] = 'A nova senha deve ter entre 8 e 128 caracteres.';
        if ($password !== $confirm) $errors[] = 'As novas senhas não coincidem.';
        if ($current === $password && $password !== '') $errors[] = 'Escolha uma senha diferente da atual.';
        if ($errors === []) {
            $dateSql = $passwordDateAvailable ? ', senha_alterada_em = NOW()' : '';
            $stmt = $pdo->prepare('UPDATE usuarios SET senhausuario = :senha, sessao_versao = sessao_versao + 1' . $dateSql . ' WHERE idusuario = :id RETURNING sessao_versao');
            $stmt->execute([':senha' => stridebr_password_hash($password), ':id' => $idUsuario]);
            $_SESSION['SessaoVersao'] = (int) $stmt->fetchColumn();
            $_SESSION['SessionGuardCheckedAt'] = time();
            $pdo->prepare("UPDATE auth_tokens SET usado_em = NOW() WHERE idusuario = :id AND tipo = 'redefinir_senha' AND usado_em IS NULL")->execute([':id' => $idUsuario]);
            stridebr_session_revoke_others($pdo, $idUsuario);
            stridebr_flash('success', 'Senha alterada. As outras sessões foram encerradas.');
            header('Location: /user/account.php#senha');
            exit;
        }
    } elseif ($action === 'logout_others') {
        $stmt = $pdo->prepare('UPDATE usuarios SET sessao_versao = sessao_versao + 1 WHERE idusuario = :id RETURNING sessao_versao');
        $stmt->execute([':id' => $idUsuario]);
        $_SESSION['SessaoVersao'] = (int) $stmt->fetchColumn();
        $_SESSION['SessionGuardCheckedAt'] = time();
        stridebr_session_revoke_others($pdo, $idUsuario);
        stridebr_flash('success', 'Outras sessões encerradas. Este dispositivo continua conectado.');
        header('Location: /user/account.php#sessoes');
        exit;
    } elseif ($action === 'revoke_session') {
        $hash = strtolower(trim((string) ($_POST['sessao_hash'] ?? '')));
        if (stridebr_session_revoke($pdo, $idUsuario, $hash)) {
            stridebr_flash('success', 'Sessão encerrada.');
        } else {
            stridebr_flash('info', 'Essa sessão já estava encerrada ou não está disponível.');
        }
        header('Location: /user/account.php#sessoes');
        exit;
    } elseif ($action === 'request_email_change') {
        $newEmail = stridebr_lower(trim((string) ($_POST['novo_email'] ?? '')));
        $currentPassword = (string) ($_POST['senha_email'] ?? '');
        if (!stridebr_mail_is_configured()) $errors[] = 'A alteração de e-mail está indisponível enquanto o envio de mensagens não estiver configurado.';
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || stridebr_length($newEmail) > 255) $errors[] = 'Informe um novo e-mail válido.';
        if ($newEmail !== '' && hash_equals(stridebr_lower((string) $usuario['emailusuario']), $newEmail)) $errors[] = 'Esse já é o e-mail da sua conta.';
        if (!password_verify($currentPassword, (string) $usuario['senhausuario'])) $errors[] = 'A senha atual está incorreta.';
        if ($errors === []) {
            $exists = $pdo->prepare('SELECT 1 FROM usuarios WHERE lower(emailusuario) = lower(:email) AND idusuario <> :id LIMIT 1');
            $exists->execute([':email' => $newEmail, ':id' => $idUsuario]);
            if ($exists->fetchColumn()) $errors[] = 'Esse e-mail já está em uso.';
        }
        if ($errors === []) {
            try {
                $name = stridebr_display_name();
                if (!stridebr_send_email_change_code($pdo, $idUsuario, $newEmail, $name)) {
                    throw new RuntimeException('Não foi possível enviar o código agora.');
                }
                $_SESSION['PendingEmailChange'] = ['user_id' => $idUsuario, 'email' => $newEmail, 'old_email' => (string) $usuario['emailusuario'], 'created_at' => time()];
                stridebr_flash('success', 'Enviamos um código de 6 dígitos para o novo e-mail.');
                header('Location: /user/account.php#email');
                exit;
            } catch (Throwable $e) {
                $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível iniciar a troca de e-mail.';
            }
        }
    } elseif ($action === 'confirm_email_change') {
        $pendingEmail = $_SESSION['PendingEmailChange'] ?? null;
        $code = preg_replace('/\D+/', '', (string) ($_POST['codigo_email'] ?? '')) ?? '';
        if (!is_array($pendingEmail) || (string) ($pendingEmail['user_id'] ?? '') !== $idUsuario || (int) ($pendingEmail['created_at'] ?? 0) < time() - 1800) {
            $errors[] = 'Essa solicitação expirou. Comece a alteração de e-mail novamente.';
        } elseif (!preg_match('/^[0-9]{6}$/', $code)) {
            $errors[] = 'Digite o código de 6 números.';
        } elseif (stridebr_auth_limit_is_blocked($pdo, 'change-email-code', $idUsuario)) {
            $errors[] = 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';
        } else {
            $token = stridebr_auth_find_token($pdo, $code, 'verificar_email');
            $newEmail = stridebr_lower((string) ($pendingEmail['email'] ?? ''));
            $valid = $token && hash_equals((string) $token['idusuario'], $idUsuario) && hash_equals(stridebr_lower((string) $token['email_destino']), $newEmail);
            if (!$valid) {
                stridebr_auth_limit_record_failure($pdo, 'change-email-code', $idUsuario, 8, 900, 900);
                $errors[] = 'Código incorreto ou expirado.';
            } else {
                $pdo->beginTransaction();
                try {
                    $exists = $pdo->prepare('SELECT 1 FROM usuarios WHERE lower(emailusuario) = lower(:email) AND idusuario <> :id LIMIT 1 FOR UPDATE');
                    $exists->execute([':email' => $newEmail, ':id' => $idUsuario]);
                    if ($exists->fetchColumn()) throw new InvalidArgumentException('Esse e-mail já está em uso.');
                    $use = $pdo->prepare('UPDATE auth_tokens SET usado_em = NOW() WHERE idtoken = :id AND usado_em IS NULL AND expira_em > NOW()');
                    $use->execute([':id' => $token['idtoken']]);
                    if ($use->rowCount() !== 1) throw new InvalidArgumentException('Código incorreto ou expirado.');
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
                        stridebr_send_mail($oldEmail, 'E-mail da sua conta StrideBR alterado', "O e-mail de acesso da sua conta StrideBR foi alterado para {$newEmail}.\n\nSe você não fez essa alteração, redefina sua senha e entre em contato com o suporte.");
                    }
                    stridebr_flash('success', 'E-mail alterado e confirmado. As outras sessões foram encerradas.');
                    header('Location: /user/account.php#email');
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível confirmar o novo e-mail.';
                }
            }
        }
    } elseif ($action === 'resend_email_change') {
        $pendingEmail = $_SESSION['PendingEmailChange'] ?? null;
        if (!is_array($pendingEmail) || (string) ($pendingEmail['user_id'] ?? '') !== $idUsuario) {
            $errors[] = 'Não há alteração de e-mail pendente.';
        } else {
            try {
                $sent = stridebr_send_email_change_code($pdo, $idUsuario, (string) $pendingEmail['email'], stridebr_display_name());
                if (!$sent) throw new RuntimeException('Não foi possível enviar outro código agora.');
                stridebr_flash('success', 'Novo código enviado.');
                header('Location: /user/account.php#email');
                exit;
            } catch (Throwable $e) {
                $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível reenviar o código.';
            }
        }
    } elseif ($action === 'cancel_email_change') {
        unset($_SESSION['PendingEmailChange']);
        $pdo->prepare("UPDATE auth_tokens SET usado_em = NOW() WHERE idusuario = :id AND tipo = 'verificar_email' AND usado_em IS NULL")->execute([':id' => $idUsuario]);
        stridebr_flash('info', 'Alteração de e-mail cancelada.');
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
if ($sessionTracking) {
    try {
        $stmt = $pdo->prepare('SELECT sessao_hash, criado_em, ultimo_uso_em, ip::text AS ip, user_agent, revogado_em FROM sessoes_usuario WHERE idusuario = :id ORDER BY ultimo_uso_em DESC LIMIT 20');
        $stmt->execute([':id' => $idUsuario]);
        $sessions = $stmt->fetchAll();
    } catch (Throwable) {
        $sessions = [];
        $sessionTracking = false;
    }
}
$currentSessionHash = stridebr_session_hash();
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
    <title>Conta e segurança | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell settings-shell">
            <div class="page-heading"><h1>Conta e segurança</h1><p>Acesso, sessões e dados da conta.</p></div>
            <nav class="settings-tabs" aria-label="Configurações"><a href="/user/settings.php">Perfil e preferências</a><a class="is-active" href="/user/account.php" aria-current="page">Conta e segurança</a></nav>
            <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>

            <section class="content-card account-overview-card">
                <div class="account-overview-icon" aria-hidden="true">@</div>
                <div><span>Conta</span><strong><?php echo stridebr_e($usuario['emailusuario']); ?></strong><small><?php echo $emailVerified ? 'E-mail confirmado' : 'E-mail pendente de confirmação'; ?> · <?php echo stridebr_e(stridebr_role_label((string) ($usuario['papelusuario'] ?? 'user'))); ?></small></div>
                <?php if (!$emailVerified && $emailVerificationEnabled): ?><a class="secondary-button" href="/verify-email.php">Confirmar e-mail</a><?php endif; ?>
            </section>

            <div class="account-settings-grid">
                <section class="content-card account-settings-card" id="email">
                    <div class="account-settings-heading"><div><span class="account-settings-kicker">Identidade</span><h2>E-mail de acesso</h2><p>A troca só é concluída depois que você confirma o novo endereço.</p></div></div>
                    <?php if ($pendingEmail): ?>
                        <div class="account-pending-email"><strong>Confirme <?php echo stridebr_e((string) $pendingEmail['email']); ?></strong><span>Digite o código de 6 números enviado para esse endereço.</span></div>
                        <form method="POST" class="account-password-form">
                            <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="confirm_email_change">
                            <label>Código de confirmação<input type="text" name="codigo_email" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required></label>
                            <div class="account-form-actions"><button type="submit" class="primary-button">Confirmar novo e-mail</button></div>
                        </form>
                        <div class="account-inline-buttons"><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="resend_email_change"><button type="submit" class="secondary-button">Reenviar código</button></form><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="cancel_email_change"><button type="submit" class="text-button">Cancelar alteração</button></form></div>
                    <?php else: ?>
                        <form method="POST" class="account-password-form">
                            <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="request_email_change">
                            <label>Novo e-mail<input type="email" name="novo_email" autocomplete="email" maxlength="255" placeholder="voce@exemplo.com" required></label>
                            <label>Senha atual<input type="password" name="senha_email" autocomplete="current-password" maxlength="128" required></label>
                            <div class="account-form-actions"><button type="submit" class="secondary-button">Enviar código de confirmação</button></div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="content-card account-settings-card" id="senha">
                    <div class="account-settings-heading"><div><span class="account-settings-kicker">Acesso</span><h2>Alterar senha</h2><p>Use sua senha atual para definir uma nova. A troca encerra as outras sessões.</p></div></div>
                    <form method="POST" class="account-password-form">
                        <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="change_password">
                        <label>Senha atual<input type="password" name="senha_atual" autocomplete="current-password" maxlength="128" required></label>
                        <div class="account-password-row"><label>Nova senha<input type="password" name="nova_senha" autocomplete="new-password" minlength="8" maxlength="128" required></label><label>Confirmar nova senha<input type="password" name="confirmar_senha" autocomplete="new-password" minlength="8" maxlength="128" required></label></div>
                        <div class="account-form-actions"><button type="submit" class="primary-button">Alterar senha</button><?php if ($passwordResetEnabled): ?><a href="/forgot-password.php">Esqueci minha senha atual</a><?php endif; ?></div>
                    </form>
                    <?php if ($passwordDateAvailable && !empty($usuario['senha_alterada_em'])): ?><small class="account-security-meta">Última alteração: <?php echo stridebr_e((new DateTimeImmutable((string) $usuario['senha_alterada_em']))->format('d/m/Y \à\s H:i')); ?></small><?php endif; ?>
                </section>

                <section class="content-card account-settings-card account-span-full" id="sessoes">
                    <div class="account-settings-heading"><div><span class="account-settings-kicker">Segurança</span><h2>Sessões e dispositivos</h2><p>Revise onde sua conta está conectada e encerre acessos antigos.</p></div></div>
                    <form method="POST" class="account-inline-action">
                        <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="logout_others">
                        <div><strong>Encerrar todas as outras sessões</strong><span>Este dispositivo continua conectado.</span></div>
                        <button type="submit" class="secondary-button">Encerrar outras</button>
                    </form>
                    <?php if (!$sessionTracking): ?>
                        <div class="account-session-note">O rastreamento detalhado de dispositivos será ativado quando a migration <code>20260903_v1_rc.sql</code> for aplicada. O botão acima já continua funcionando.</div>
                    <?php elseif ($sessions === []): ?>
                        <div class="account-session-note">Nenhuma sessão detalhada foi registrada ainda. Elas aparecem conforme os dispositivos acessarem novamente.</div>
                    <?php else: ?>
                        <div class="account-session-list">
                            <?php foreach ($sessions as $session): ?>
                                <?php [$browser, $os] = $deviceLabel($session['user_agent'] ?? null); $isCurrent = hash_equals((string) ($currentSessionHash ?? ''), (string) $session['sessao_hash']); $revoked = !empty($session['revogado_em']); ?>
                                <article class="account-session-item<?php echo $revoked ? ' is-revoked' : ''; ?>">
                                    <div><strong><?php echo stridebr_e($browser . ' · ' . $os); ?><?php echo $isCurrent ? ' · este dispositivo' : ''; ?></strong><span><?php echo stridebr_e((new DateTimeImmutable((string) $session['ultimo_uso_em']))->format('d/m/Y H:i')); ?><?php echo !empty($session['ip']) ? ' · ' . stridebr_e((string) $session['ip']) : ''; ?><?php echo $revoked ? ' · encerrada' : ''; ?></span></div>
                                    <?php if (!$isCurrent && !$revoked): ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="revoke_session"><input type="hidden" name="sessao_hash" value="<?php echo stridebr_e((string) $session['sessao_hash']); ?>"><button type="submit" class="secondary-button compact">Encerrar</button></form><?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="content-card account-settings-card account-span-full" id="dados-conta">
                    <div class="account-settings-heading"><div><span class="account-settings-kicker">Dados</span><h2>Dados e conta</h2><p>Exporte seus registros ou gerencie a exclusão da conta.</p></div></div>
                    <div class="settings-data-actions">
                        <article><div><strong>Exportar meus dados</strong><span>Baixe um JSON com perfil, cronogramas, atividades, rotas, metas, equipamentos e outros registros da sua conta.</span></div><form method="POST" action="/user/export-data.php"><?php echo stridebr_csrf_field(); ?><button type="submit" class="secondary-button">Baixar exportação</button></form></article>
                        <article><div><strong>Excluir conta</strong><span>Remove sua conta e os dados vinculados. A confirmação exige sua senha e não pode ser desfeita.</span></div><a class="danger-button" href="/user/delete-account.php">Gerenciar exclusão</a></article>
                    </div>
                </section>

                <section class="content-card account-settings-card account-span-full">
                    <div class="account-settings-heading"><div><span class="account-settings-kicker">Informações</span><h2>Status da conta</h2></div></div>
                    <dl class="account-definition-list">
                        <div><dt>E-mail</dt><dd><?php echo $emailVerified ? 'Verificado' : 'Não verificado'; ?><?php if (!$emailVerified && $emailVerificationEnabled): ?> · <a href="/verify-email.php">confirmar</a><?php endif; ?></dd></div>
                        <div><dt>Conta criada</dt><dd><?php echo stridebr_e((new DateTimeImmutable((string) $usuario['dataregistrousuario']))->format('d/m/Y')); ?></dd></div>
                        <div><dt>Termos aceitos</dt><dd><a href="/pages/legal/terms.php"><?php echo stridebr_e($usuario['termos_versao'] ?? 'versão anterior'); ?></a></dd></div>
                        <div><dt>Política de privacidade</dt><dd><a href="/pages/legal/privacy.php"><?php echo stridebr_e($usuario['privacidade_versao'] ?? 'versão anterior'); ?></a></dd></div>
                    </dl>
                </section>
            </div>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</body>
</html>
