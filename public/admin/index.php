<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
stridebr_require_role('moderator');
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/includes/admin.php';
require_once dirname(__DIR__, 2) . '/src/includes/auth.php';

$isAdmin = stridebr_has_role('admin');
$isOwner = stridebr_has_role('owner');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'toggle_preview_favorite') {
            if (!$isOwner) throw new RuntimeException('Apenas o proprietário pode fixar contas de teste.');
            $targetId = trim((string) ($_POST['idusuario'] ?? ''));
            $targetStmt = $pdo->prepare("SELECT 1 FROM usuarios WHERE idusuario = :id AND statususuario = 'Ativo' AND papelusuario <> 'owner' LIMIT 1");
            $targetStmt->execute([':id' => $targetId]);
            if (!$targetStmt->fetchColumn()) throw new RuntimeException('Conta de teste indisponível.');
            $prefsStmt = $pdo->prepare('SELECT preferenciasusuario FROM usuarios WHERE idusuario = :id FOR UPDATE');
            $prefsStmt->execute([':id' => $idUsuario]);
            $rawPrefs = $prefsStmt->fetchColumn();
            $ownerPrefs = is_array($rawPrefs) ? $rawPrefs : (json_decode((string) $rawPrefs, true) ?: []);
            $favorites = array_values(array_unique(array_filter(array_map('strval', (array) ($ownerPrefs['admin_preview_favorites'] ?? [])))));
            if (in_array($targetId, $favorites, true)) {
                $favorites = array_values(array_diff($favorites, [$targetId]));
                $message = 'Conta removida dos acessos fixados.';
            } else {
                $favorites[] = $targetId;
                $favorites = array_slice($favorites, -12);
                $message = 'Conta fixada no acesso rápido.';
            }
            $ownerPrefs['admin_preview_favorites'] = $favorites;
            $pdo->prepare('UPDATE usuarios SET preferenciasusuario = CAST(:prefs AS jsonb) WHERE idusuario = :id')->execute([':prefs' => json_encode($ownerPrefs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':id' => $idUsuario]);
            stridebr_flash('success', $message);
            header('Location: /admin/index.php');
            exit;
        }

        if ($action === 'impersonate_user') {
            if (!$isOwner) throw new RuntimeException('Apenas o proprietário pode usar a troca rápida de conta.');
            $targetId = trim((string) ($_POST['idusuario'] ?? ''));
            $targetStmt = $pdo->prepare("SELECT idusuario, nomeusuario, COALESCE(NULLIF(nome_exibicao,''), nomeusuario) AS nome_exibicao, username, papelusuario, emailusuario, fotousuario, sessao_versao, statususuario, onboarding_concluido FROM usuarios WHERE idusuario = :id LIMIT 1");
            $targetStmt->execute([':id' => $targetId]);
            $target = $targetStmt->fetch();
            if (!$target || $target['statususuario'] !== 'Ativo') throw new RuntimeException('Conta de teste indisponível.');
            if ((string) $target['papelusuario'] === 'owner') throw new RuntimeException('Não é possível alternar para outra conta proprietária.');

            $_SESSION['OwnerImpersonation'] = [
                'IdUsuario' => $_SESSION['IdUsuario'],
                'NomeUsuario' => $_SESSION['NomeUsuario'] ?? '',
                'NomeExibicao' => $_SESSION['NomeExibicao'] ?? '',
                'Username' => $_SESSION['Username'] ?? null,
                'PapelUsuario' => $_SESSION['PapelUsuario'] ?? 'owner',
                'EmailUsuario' => $_SESSION['EmailUsuario'] ?? '',
                'FotoUsuario' => $_SESSION['FotoUsuario'] ?? null,
                'SessaoVersao' => $_SESSION['SessaoVersao'] ?? 1,
                'OnboardingConcluido' => $_SESSION['OnboardingConcluido'] ?? true,
            ];
            stridebr_admin_audit($pdo, $idUsuario, 'account.impersonate', 'usuario', $targetId, ['role' => $target['papelusuario']]);
            session_regenerate_id(true);
            $_SESSION['IdUsuario'] = $target['idusuario'];
            $_SESSION['NomeUsuario'] = $target['nomeusuario'];
            $_SESSION['NomeExibicao'] = $target['nome_exibicao'];
            $_SESSION['Username'] = $target['username'] ?? null;
            $_SESSION['PapelUsuario'] = $target['papelusuario'] ?? 'user';
            $_SESSION['EmailUsuario'] = $target['emailusuario'];
            $_SESSION['FotoUsuario'] = $target['fotousuario'] ?: null;
            $_SESSION['SessaoVersao'] = (int) ($target['sessao_versao'] ?? 1);
            $_SESSION['OnboardingConcluido'] = stridebr_db_bool($target['onboarding_concluido'] ?? false);
            $_SESSION['SessionGuardCheckedAt'] = 0;
            header('Location: /home.php');
            exit;
        }

        if ($action === 'send_test_email') {
            if (!$isAdmin) throw new RuntimeException('Apenas administradores podem testar o e-mail transacional.');
            if (!stridebr_mail_is_configured()) throw new RuntimeException('Configure STRIDEBR_MAIL_FROM antes de testar o envio.');
            if (!stridebr_rate_limit('admin-mail-test', 3, 300)) throw new RuntimeException('Aguarde alguns minutos antes de enviar outro teste.');
            $recipientStmt = $pdo->prepare("SELECT emailusuario, COALESCE(NULLIF(nome_exibicao,''), nomeusuario) AS nome FROM usuarios WHERE idusuario = :id LIMIT 1");
            $recipientStmt->execute([':id' => $idUsuario]);
            $recipient = $recipientStmt->fetch();
            if (!$recipient || !filter_var((string) $recipient['emailusuario'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Sua conta administrativa não tem um e-mail válido.');
            $body = "Olá, {$recipient['nome']}.\n\nEste é um teste do e-mail transacional do StrideBR.\n\nVersão: " . stridebr_version() . "\nBuild: " . stridebr_build() . "\nHorário: " . date('d/m/Y H:i:s') . "\n\nSe esta mensagem chegou, o envio básico está funcionando.";
            if (!stridebr_send_mail((string) $recipient['emailusuario'], 'Teste de e-mail do StrideBR', $body)) throw new RuntimeException('O servidor não confirmou o envio do e-mail de teste.');
            stridebr_admin_audit($pdo, $idUsuario, 'auth.mail.test', 'email', (string) $recipient['emailusuario'], []);
            stridebr_flash('success', 'E-mail de teste enviado para sua conta administrativa.');
            header('Location: /admin/index.php');
            exit;
        }

        if (in_array($action, ['set_email_verification', 'set_password_reset'], true)) {
            if (!$isAdmin) throw new RuntimeException('Apenas administradores podem alterar autenticação.');
            $enabled = ($_POST['ativo'] ?? '') === '1';
            if ($enabled && !stridebr_mail_is_configured()) {
                throw new RuntimeException('Configure STRIDEBR_MAIL_FROM antes de ativar recursos de e-mail.');
            }

            $pdo->beginTransaction();
            try {
                if ($action === 'set_email_verification') {
                    $stmt = $pdo->prepare("UPDATE feature_flags SET ativo=:ativo, atualizado_por=:actor, data_atualizacao=NOW() WHERE chave IN ('auth.email_verification.enabled','auth.email_verification.required')");
                    $stmt->bindValue(':ativo', $enabled, PDO::PARAM_BOOL);
                    $stmt->bindValue(':actor', $idUsuario);
                    $stmt->execute();
                    if ($stmt->rowCount() < 2) throw new RuntimeException('As flags de verificação de e-mail não estão completas no banco.');
                    $label = 'Verificação de e-mail';
                } else {
                    $stmt = $pdo->prepare("UPDATE feature_flags SET ativo=:ativo, atualizado_por=:actor, data_atualizacao=NOW() WHERE chave='auth.password_reset.enabled'");
                    $stmt->bindValue(':ativo', $enabled, PDO::PARAM_BOOL);
                    $stmt->bindValue(':actor', $idUsuario);
                    $stmt->execute();
                    if ($stmt->rowCount() !== 1) throw new RuntimeException('A flag de recuperação de senha não existe no banco.');
                    $label = 'Recuperação de senha';
                }
                $pdo->commit();
            } catch (Throwable $toggleError) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $toggleError;
            }

            stridebr_feature_cache_clear();
            stridebr_admin_audit($pdo, $idUsuario, 'auth.feature.update', 'feature_flag', $action, ['ativo' => $enabled]);
            stridebr_flash('success', $label . ($enabled ? ' ativada.' : ' desativada.'));
            header('Location: /admin/index.php');
            exit;
        }

        if ($action !== 'toggle_flag') throw new InvalidArgumentException('Ação inválida.');
        if (!$isAdmin) throw new RuntimeException('Apenas administradores podem alterar feature flags.');

        $key = trim((string) ($_POST['chave'] ?? ''));
        $enabled = ($_POST['ativo'] ?? '') === '1';
        if ($enabled && in_array($key, ['auth.email_verification.enabled', 'auth.email_verification.required', 'auth.password_reset.enabled'], true)) {
            $mailFrom = trim((string) (getenv('STRIDEBR_MAIL_FROM') ?: ''));
            if (!filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Configure STRIDEBR_MAIL_FROM antes de ativar recursos de e-mail.');
            }
        }

        $stmt = $pdo->prepare('UPDATE feature_flags SET ativo = :ativo, atualizado_por = :actor, data_atualizacao = NOW() WHERE chave = :chave');
        $stmt->bindValue(':ativo', $enabled, PDO::PARAM_BOOL);
        $stmt->bindValue(':actor', $idUsuario);
        $stmt->bindValue(':chave', $key);
        $stmt->execute();
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Feature flag não encontrada.');

        if ($key === 'auth.email_verification.required' && $enabled) {
            $pdo->prepare("UPDATE feature_flags SET ativo=TRUE, atualizado_por=:actor, data_atualizacao=NOW() WHERE chave='auth.email_verification.enabled'")->execute([':actor' => $idUsuario]);
        }
        if ($key === 'auth.email_verification.enabled' && !$enabled) {
            $pdo->prepare("UPDATE feature_flags SET ativo=FALSE, atualizado_por=:actor, data_atualizacao=NOW() WHERE chave='auth.email_verification.required'")->execute([':actor' => $idUsuario]);
        }

        stridebr_feature_cache_clear();
        stridebr_admin_audit($pdo, $idUsuario, 'feature_flag.update', 'feature_flag', $key, ['ativo' => $enabled]);
        stridebr_flash('success', 'Feature flag atualizada.');
        header('Location: /admin/index.php');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof RuntimeException || $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : 'Não foi possível executar a ação administrativa.';
    }
}

$metricQueries = [
    'users' => 'SELECT count(*) FROM usuarios',
    'active24' => "SELECT count(*) FROM usuarios WHERE ultimologin >= NOW() - INTERVAL '24 hours'",
    'new7' => "SELECT count(*) FROM usuarios WHERE dataregistrousuario >= NOW() - INTERVAL '7 days'",
    'activities7' => "SELECT count(*) FROM registros_atividade WHERE excluido_em IS NULL AND data_criacao >= NOW() - INTERVAL '7 days'",
    'feedbackNew' => "SELECT count(*) FROM feedbacks WHERE status = 'novo'",
    'feedbackPending' => "SELECT count(*) FROM feedbacks WHERE status <> 'resolvido'",
];
if ($isAdmin) $metricQueries['activeSessions'] = "SELECT count(*) FROM sessoes_treino WHERE status = 'ativo'";

$metrics = [];
foreach ($metricQueries as $key => $sql) $metrics[$key] = (int) $pdo->query($sql)->fetchColumn();

$authFlagKeys = ['auth.email_verification.enabled', 'auth.email_verification.required', 'auth.password_reset.enabled'];
$flags = $isAdmin ? $pdo->query("SELECT chave, ativo, descricao, data_atualizacao FROM feature_flags WHERE chave NOT IN ('auth.email_verification.enabled','auth.email_verification.required','auth.password_reset.enabled') ORDER BY chave")->fetchAll() : [];
$mailReady = $isAdmin && stridebr_mail_is_configured();
$emailVerificationOn = $isAdmin && stridebr_feature_enabled($pdo, 'auth.email_verification.enabled', false) && stridebr_feature_enabled($pdo, 'auth.email_verification.required', false);
$passwordResetOn = $isAdmin && stridebr_feature_enabled($pdo, 'auth.password_reset.enabled', false);
$systemHasAttention = $isAdmin && !$mailReady && ($emailVerificationOn || $passwordResetOn);
$recentUsers = $isAdmin ? $pdo->query("SELECT idusuario, COALESCE(NULLIF(nome_exibicao,''), nomeusuario) AS nome_exibicao, username, papelusuario, statususuario, ultimologin, ipultimologin, dataregistrousuario FROM usuarios ORDER BY COALESCE(ultimologin, dataregistrousuario) DESC LIMIT 15")->fetchAll() : [];
$audit = $isAdmin ? $pdo->query("SELECT l.acao, l.alvo_tipo, l.alvo_id, l.detalhes, l.ip, l.data_criacao, COALESCE(NULLIF(u.nome_exibicao,''), u.nomeusuario, 'Sistema') AS ator FROM admin_audit_log l LEFT JOIN usuarios u ON u.idusuario = l.idator ORDER BY l.data_criacao DESC LIMIT 20")->fetchAll() : [];
$recentAccess = $isOwner ? $pdo->query("SELECT a.ip, a.user_agent, a.data_acesso, COALESCE(NULLIF(u.nome_exibicao,''), u.nomeusuario, 'Usuário removido') AS nome_exibicao, u.username FROM acessos_usuario a LEFT JOIN usuarios u ON u.idusuario = a.idusuario ORDER BY a.data_acesso DESC LIMIT 30")->fetchAll() : [];
$previewUsers = $isOwner ? $pdo->query("SELECT idusuario, COALESCE(NULLIF(nome_exibicao,''), nomeusuario) AS nome_exibicao, username, papelusuario FROM usuarios WHERE statususuario = 'Ativo' AND papelusuario <> 'owner' ORDER BY CASE papelusuario WHEN 'user' THEN 1 WHEN 'moderator' THEN 2 ELSE 3 END, nome_exibicao LIMIT 100")->fetchAll() : [];
$previewFavorites = [];
if ($isOwner) {
    $prefsStmt = $pdo->prepare('SELECT preferenciasusuario FROM usuarios WHERE idusuario = :id');
    $prefsStmt->execute([':id' => $idUsuario]);
    $rawPrefs = $prefsStmt->fetchColumn();
    $ownerPrefs = is_array($rawPrefs) ? $rawPrefs : (json_decode((string) $rawPrefs, true) ?: []);
    $favoriteIds = array_fill_keys(array_map('strval', (array) ($ownerPrefs['admin_preview_favorites'] ?? [])), true);
    $previewFavorites = array_values(array_filter($previewUsers, static fn(array $user): bool => isset($favoriteIds[(string) $user['idusuario']])));
}
$flashes = stridebr_take_flashes();

function adminBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $value = (float) $bytes;
    while ($value >= 1024 && $i < count($units) - 1) { $value /= 1024; $i++; }
    return number_format($value, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
}
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">

    <title><?php echo $isAdmin ? 'Administração' : 'Moderação'; ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/admin.css')); ?>">
</head>
<body class="admin-body">
<div class="container-fluid">
<?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content"><div class="admin-shell">
    <?php echo stridebr_admin_nav('dashboard'); ?>
    <div class="admin-heading">
        <div><span class="eyebrow">StrideBR</span><h1><?php echo $isAdmin ? 'Administração' : 'Moderação'; ?></h1><p><?php echo $isAdmin ? 'Visão operacional do site, usuários e recursos em liberação.' : 'Acompanhe a comunidade e cuide da fila de feedback sem acesso às contas dos usuários.'; ?></p></div>
        <span class="admin-role"><?php echo stridebr_e(stridebr_role_label()); ?></span>
    </div>
    <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>

    <section class="admin-operation-strip" aria-label="<?php echo stridebr_e(stridebr_t('admin.overview.attention')); ?>">
        <article class="admin-operation-item<?php echo $metrics['feedbackNew'] > 0 ? ' has-attention' : ''; ?>">
            <div class="admin-operation-copy"><strong><?php echo stridebr_e($metrics['feedbackNew'] > 0 ? stridebr_t('admin.overview.feedback_unread') : stridebr_t('admin.overview.feedback_clear')); ?></strong><span><?php echo stridebr_e(stridebr_tn('admin.feedback.results.one', 'admin.feedback.results.other', $metrics['feedbackPending'], ['count' => $metrics['feedbackPending']])); ?> <?php echo stridebr_e(stridebr_t('admin.overview.feedback_open_suffix')); ?></span></div>
            <div class="admin-operation-count"><?php echo $metrics['feedbackNew']; ?></div>
            <a class="secondary-action compact" href="/admin/feedback.php?state=unread"><?php echo stridebr_e(stridebr_t('admin.overview.open_feedback')); ?></a>
        </article>
        <?php if ($isAdmin): ?><article class="admin-operation-item">
            <div class="admin-operation-copy"><strong><?php echo stridebr_e(stridebr_t('admin.overview.system')); ?></strong><span class="admin-system-status<?php echo $systemHasAttention ? ' has-problem' : ''; ?>"><?php echo stridebr_e($systemHasAttention ? stridebr_t('admin.overview.system_attention') : stridebr_t('admin.overview.system_ok')); ?></span></div>
            <a class="secondary-action compact" href="/admin/diagnostics.php"><?php echo stridebr_e(stridebr_t('admin.overview.open_diagnostics')); ?></a>
        </article><?php endif; ?>
    </section>

    <?php if ($isOwner): ?><section class="admin-preview-bar"><div class="admin-preview-heading"><strong>Visualizar como outro papel</strong><span>Entre temporariamente em uma conta de nível inferior. Você volta à sua conta pelo aviso no topo.</span><?php if ($previewFavorites !== []): ?><div class="admin-preview-favorites"><?php foreach ($previewFavorites as $favorite): ?><form method="POST"><input type="hidden" name="action" value="impersonate_user"><input type="hidden" name="idusuario" value="<?php echo stridebr_e($favorite['idusuario']); ?>"><?php echo stridebr_csrf_field(); ?><button type="submit"><span>★</span><?php echo stridebr_e(stridebr_person_name_for_display((string) $favorite['nome_exibicao'], (string) ($favorite['username'] ?? ''), 'Usuário', 40)); ?></button></form><?php endforeach; ?></div><?php endif; ?></div><form method="POST" class="admin-preview-picker"><?php echo stridebr_csrf_field(); ?><select name="idusuario" required><option value="">Escolha uma conta…</option><?php foreach ($previewUsers as $previewUser): ?><option value="<?php echo stridebr_e($previewUser['idusuario']); ?>"><?php echo stridebr_e(stridebr_role_label((string) $previewUser['papelusuario']) . ' · ' . stridebr_person_name_for_display((string) $previewUser['nome_exibicao'], (string) ($previewUser['username'] ?? ''), 'Usuário', 60)); ?><?php echo $previewUser['username'] ? ' (@' . stridebr_e($previewUser['username']) . ')' : ''; ?></option><?php endforeach; ?></select><button type="submit" name="action" value="toggle_preview_favorite" class="admin-preview-star">☆ Fixar</button><button type="submit" name="action" value="impersonate_user">Visualizar</button></form></section><?php endif; ?>

    <section class="admin-metrics">
        <article><strong><?php echo $metrics['users']; ?></strong><span>Usuários</span><small><?php echo $metrics['new7']; ?> novos em 7 dias</small></article>
        <article><strong><?php echo $metrics['active24']; ?></strong><span>Ativos em 24h</span><small>por último login</small></article>
        <article><strong><?php echo $metrics['activities7']; ?></strong><span>Atividades / 7 dias</span><?php if ($isAdmin): ?><small><?php echo $metrics['activeSessions']; ?> treino(s) em andamento</small><?php endif; ?></article>
        <article><strong><?php echo $metrics['feedbackPending']; ?></strong><span>Feedback em aberto</span><small><a href="/admin/feedback.php?state=all">consultar histórico</a></small></article>
    </section>

    <?php if (!$isAdmin): ?>
    <div class="admin-grid">
        <section class="admin-card"><div class="admin-card-heading"><div><h2>O que um moderador pode fazer</h2><p>O papel é voltado à comunidade, não à administração de contas.</p></div></div><div class="health-list"><div><span class="health-dot ok"></span><strong>Feedback</strong><span>ver e atualizar a fila</span></div><div><span class="health-dot ok"></span><strong>Visão geral</strong><span>métricas agregadas</span></div><div><span class="health-dot"></span><strong>Usuários</strong><span>sem e-mail, bloqueio ou edição</span></div><div><span class="health-dot"></span><strong>Configuração</strong><span>sem feature flags, cargos ou acessos</span></div></div></section>
        <section class="admin-card"><div class="admin-card-heading"><div><h2>Fila de feedback</h2><p>É a área principal de moderação nesta versão.</p></div></div><p class="admin-note">Use a fila para acompanhar relatos e sugestões. Contas, permissões, convites e configurações continuam restritos aos administradores.</p><a class="primary-action" href="/admin/feedback.php">Abrir feedback</a></section>
    </div>
    <?php else: ?>
    <div class="admin-grid">
        <section class="admin-card"><div class="admin-card-heading"><div><h2>Autenticação por e-mail</h2><p>Controles principais para novas contas e recuperação.</p></div></div><div class="flag-list auth-flag-list"><article><div><strong>Verificação de e-mail</strong><small>Quando ligada, novas contas recebem um código de 6 dígitos e precisam confirmar o endereço antes de entrar.</small></div><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="set_email_verification"><input type="hidden" name="ativo" value="<?php echo $emailVerificationOn ? '0' : '1'; ?>"><button type="submit" class="flag-toggle<?php echo $emailVerificationOn ? ' is-on' : ''; ?>" aria-label="<?php echo $emailVerificationOn ? 'Desativar' : 'Ativar'; ?> verificação de e-mail"<?php echo !$mailReady ? ' disabled title="Configure STRIDEBR_MAIL_FROM primeiro"' : ''; ?>><span></span></button></form></article><article><div><strong>Recuperação de senha</strong><small>Permite solicitar um link de redefinição por e-mail.</small></div><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="set_password_reset"><input type="hidden" name="ativo" value="<?php echo $passwordResetOn ? '0' : '1'; ?>"><button type="submit" class="flag-toggle<?php echo $passwordResetOn ? ' is-on' : ''; ?>" aria-label="<?php echo $passwordResetOn ? 'Desativar' : 'Ativar'; ?> recuperação de senha"<?php echo !$mailReady ? ' disabled title="Configure STRIDEBR_MAIL_FROM primeiro"' : ''; ?>><span></span></button></form></article></div><p class="admin-note"><?php echo $mailReady ? 'Remetente configurado: ' . stridebr_e((string) getenv('STRIDEBR_MAIL_FROM')) : 'Configure STRIDEBR_MAIL_FROM para liberar estes controles.'; ?></p><?php if ($mailReady): ?><form method="POST" class="admin-mail-test-form"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="send_test_email"><button type="submit" class="secondary-action">Enviar e-mail de teste para mim</button></form><?php endif; ?></section>
        <section class="admin-card"><div class="admin-card-heading"><div><h2>Feature flags</h2><p>Ligue recursos gradualmente sem novo deploy.</p></div></div><div class="flag-list"><?php foreach ($flags as $flag): ?><article><div><strong><?php echo stridebr_e($flag['chave']); ?></strong><small><?php echo stridebr_e($flag['descricao'] ?? ''); ?></small></div><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="toggle_flag"><input type="hidden" name="chave" value="<?php echo stridebr_e($flag['chave']); ?>"><input type="hidden" name="ativo" value="<?php echo stridebr_db_bool($flag['ativo']) ? '0' : '1'; ?>"><button type="submit" class="flag-toggle<?php echo stridebr_db_bool($flag['ativo']) ? ' is-on' : ''; ?>" aria-label="Alternar <?php echo stridebr_e($flag['chave']); ?>"><span></span></button></form></article><?php endforeach; ?></div></section>
        <?php if ($systemHasAttention): ?><section class="admin-card"><div class="admin-card-heading"><div><h2><?php echo stridebr_e(stridebr_t('admin.overview.system_attention')); ?></h2><p><?php echo stridebr_e(stridebr_t('admin.overview.mail_configuration_problem')); ?></p></div></div><a class="secondary-action" href="/admin/diagnostics.php"><?php echo stridebr_e(stridebr_t('admin.overview.open_diagnostics')); ?></a></section><?php endif; ?>
    </div>

    <section class="admin-card admin-table-card"><div class="admin-card-heading"><div><h2>Usuários recentes</h2><p>Admins veem dados de conta; IP continua exclusivo do proprietário.</p></div></div><div class="admin-table-wrap"><table><thead><tr><th>Usuário</th><th>Papel</th><th>Último login</th><?php if ($isOwner): ?><th>IP</th><th>Acesso</th><?php endif; ?></tr></thead><tbody><?php foreach ($recentUsers as $user): ?><tr><td><strong><?php echo stridebr_e(stridebr_person_name_for_display((string) $user['nome_exibicao'], (string) ($user['username'] ?? ''), 'Usuário', 60)); ?></strong><small><?php echo $user['username'] ? '@' . stridebr_e($user['username']) : 'sem username'; ?></small></td><td><?php echo stridebr_e(stridebr_role_label((string) $user['papelusuario'])); ?><small><?php echo stridebr_e($user['statususuario']); ?></small></td><td><?php echo stridebr_e($user['ultimologin'] ?? '—'); ?></td><?php if ($isOwner): ?><td><code><?php echo stridebr_e($user['ipultimologin'] ?? '—'); ?></code></td><td><?php echo stridebr_e($user['dataregistrousuario']); ?><div class="admin-user-actions"><a href="/admin/user.php?id=<?php echo rawurlencode($user['idusuario']); ?>">Gerenciar</a><?php if ((string) $user['papelusuario'] !== 'owner' && (string) $user['statususuario'] === 'Ativo'): ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="impersonate_user"><input type="hidden" name="idusuario" value="<?php echo stridebr_e($user['idusuario']); ?>"><button type="submit" class="admin-impersonate-button">Entrar como</button></form><?php endif; ?></div></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div></section>

    <?php if ($isOwner): ?><section class="admin-card admin-table-card"><div class="admin-card-heading"><div><h2>Acessos recentes</h2><p>IP e dispositivo ficam restritos ao proprietário.</p></div></div><div class="admin-table-wrap"><table><thead><tr><th>Quando</th><th>Usuário</th><th>IP</th><th>Dispositivo</th></tr></thead><tbody><?php foreach ($recentAccess as $row): ?><tr><td><?php echo stridebr_e($row['data_acesso']); ?></td><td><?php echo stridebr_e(stridebr_person_name_for_display((string) $row['nome_exibicao'], (string) ($row['username'] ?? ''), 'Usuário', 60)); ?></td><td><code><?php echo stridebr_e((string) ($row['ip'] ?? '—')); ?></code></td><td class="admin-user-agent"><?php echo stridebr_e((string) ($row['user_agent'] ?? '—')); ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>

    <section class="admin-card admin-table-card"><div class="admin-card-heading"><div><h2>Auditoria administrativa</h2><p>Alterações sensíveis feitas por administradores.</p></div></div><div class="admin-table-wrap"><table><thead><tr><th>Quando</th><th>Ator</th><th>Ação</th><th>Alvo</th><?php if ($isOwner): ?><th>IP</th><?php endif; ?></tr></thead><tbody><?php if ($audit === []): ?><tr><td colspan="<?php echo $isOwner ? '5' : '4'; ?>">Nenhuma ação registrada ainda.</td></tr><?php endif; ?><?php foreach ($audit as $row): ?><tr><td><?php echo stridebr_e($row['data_criacao']); ?></td><td><?php echo stridebr_e($row['ator']); ?></td><td><code><?php echo stridebr_e($row['acao']); ?></code></td><td><?php echo stridebr_e(trim(($row['alvo_tipo'] ?? '') . ' ' . ($row['alvo_id'] ?? '')) ?: '—'); ?></td><?php if ($isOwner): ?><td><code><?php echo stridebr_e((string) ($row['ip'] ?? '—')); ?></code></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div></section>
    <?php endif; ?>
</div></main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</body>
</html>
