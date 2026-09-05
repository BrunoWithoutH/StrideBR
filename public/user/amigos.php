<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma_compartilhar.php';
require_once dirname(__DIR__, 2) . '/src/function/notificacoes.php';
require_once dirname(__DIR__, 2) . '/src/function/product_analytics.php';

if (!stridebr_feature_enabled($pdo, 'friends.enabled', false)) {
    stridebr_error_document(404);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $target = trim((string) ($_POST['idusuario'] ?? ''));
    try {
        if (in_array($action, ['send', 'accept', 'reject', 'remove'], true) && ($target === '' || $target === $idUsuario)) throw new InvalidArgumentException(stridebr_t('friends.invalid_user'));
        if ($action === 'send') {
            $exists = $pdo->prepare('SELECT 1 FROM usuarios WHERE idusuario = :id AND statususuario = \'Ativo\'');
            $exists->execute([':id' => $target]);
            if (!$exists->fetchColumn()) throw new RuntimeException(stridebr_t('friends.user_not_found'));
            $pair = $pdo->prepare('SELECT status FROM amizades WHERE LEAST(idusuario_solicitante, idusuario_destino) = LEAST(:me1, :target1) AND GREATEST(idusuario_solicitante, idusuario_destino) = GREATEST(:me2, :target2) LIMIT 1');
            $pair->execute([':me1' => $idUsuario, ':target1' => $target, ':me2' => $idUsuario, ':target2' => $target]);
            if ($pair->fetchColumn()) throw new RuntimeException(stridebr_t('friends.existing_relation'));
            $pdo->prepare('INSERT INTO amizades (idamizade, idusuario_solicitante, idusuario_destino) VALUES (:id, :me, :target)')->execute([
                ':id' => stridebr_generate_id(), ':me' => $idUsuario, ':target' => $target,
            ]);
            notificacaoCriar($pdo, $target, 'amizade_solicitacao', stridebr_t('notification.friend_request.title'), stridebr_t('notification.friend_request.message'), '/user/amigos.php');
            stridebr_flash('success', stridebr_t('friends.request_sent'));
        } elseif ($action === 'accept') {
            $stmt = $pdo->prepare("UPDATE amizades SET status = 'aceita', data_atualizacao = NOW() WHERE idusuario_solicitante = :target AND idusuario_destino = :me AND status = 'pendente'");
            $stmt->execute([':target' => $target, ':me' => $idUsuario]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException(stridebr_t('friends.request_not_found'));
            notificacaoCriar($pdo, $target, 'amizade_aceita', stridebr_t('notification.friend_accepted.title'), stridebr_t('notification.friend_accepted.message'), '/user/amigos.php');
            stridebr_flash('success', stridebr_t('friends.friendship_accepted'));
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("DELETE FROM amizades WHERE idusuario_solicitante = :target AND idusuario_destino = :me AND status = 'pendente'");
            $stmt->execute([':target' => $target, ':me' => $idUsuario]);
            stridebr_flash('info', stridebr_t('friends.request_removed'));
        } elseif ($action === 'remove') {
            $stmt = $pdo->prepare("DELETE FROM amizades WHERE status = 'aceita' AND ((idusuario_solicitante = :me1 AND idusuario_destino = :target1) OR (idusuario_solicitante = :target2 AND idusuario_destino = :me2))");
            $stmt->execute([':me1' => $idUsuario, ':target1' => $target, ':target2' => $target, ':me2' => $idUsuario]);
            stridebr_flash('info', stridebr_t('friends.friendship_removed'));
        } elseif ($action === 'accept_share') {
            $idShare = trim((string) ($_POST['idcompartilhamento'] ?? ''));
            $idNovo = compartilhamentoAceitarSnapshot($pdo, $idUsuario, $idShare);
            stridebr_flash('success', stridebr_t('friends.schedule_added'));
            header('Location: /user/cronogramatreinos.php?id=' . urlencode($idNovo));
            exit;
        } elseif ($action === 'reject_share') {
            $idShare = trim((string) ($_POST['idcompartilhamento'] ?? ''));
            compartilhamentoRecusarSnapshot($pdo, $idUsuario, $idShare);
            stridebr_flash('info', stridebr_t('friends.share_rejected'));
        } elseif ($action === 'send_sync') {
            $scheduleId = trim((string) ($_POST['idcronograma'] ?? ''));
            $shareId = compartilhamentoEnviarSincronizado($pdo, $idUsuario, $scheduleId, $target);
            $schedule = cronogramaBuscar($pdo, $scheduleId, $idUsuario);
            notificacaoCriar($pdo, $target, 'cronograma_sincronizado_convite', stridebr_t('notification.schedule_invite.title'), stridebr_t('notification.schedule_invite.message', ['name' => (string) ($schedule['nome'] ?? stridebr_t('nav.schedule'))]), '/user/amigos.php#cronogramas-sincronizados', ['share_id' => $shareId, 'schedule_name' => (string) ($schedule['nome'] ?? '')]);
            productAnalyticsRegistrar($pdo, $idUsuario, 'schedule_shared', ['type' => 'synced']);
            stridebr_flash('success', stridebr_t('friends.sync_invite_sent'));
        } elseif ($action === 'accept_sync') {
            $shareId = trim((string) ($_POST['idcompartilhamento'] ?? ''));
            $infoStmt = $pdo->prepare("SELECT idusuario_origem FROM cronograma_compartilhamentos WHERE idcompartilhamento=:id AND idusuario_destino=:me LIMIT 1");
            $infoStmt->execute([':id'=>$shareId, ':me'=>$idUsuario]);
            $source = (string) ($infoStmt->fetchColumn() ?: '');
            $scheduleId = compartilhamentoAceitarSincronizado($pdo, $idUsuario, $shareId);
            if ($source !== '') notificacaoCriar($pdo, $source, 'cronograma_sincronizado_aceito', stridebr_t('notification.schedule_accepted.title'), stridebr_t('notification.schedule_accepted.message'), '/user/amigos.php#cronogramas-sincronizados');
            stridebr_flash('success', stridebr_t('friends.sync_added'));
            header('Location: /user/cronograma-sincronizado.php?id=' . rawurlencode($scheduleId));
            exit;
        } elseif ($action === 'reject_sync') {
            compartilhamentoRecusarSincronizado($pdo, $idUsuario, trim((string) ($_POST['idcompartilhamento'] ?? '')));
            stridebr_flash('info', stridebr_t('friends.sync_rejected'));
        } elseif ($action === 'revoke_sync') {
            compartilhamentoRevogarSincronizado($pdo, $idUsuario, trim((string) ($_POST['idcompartilhamento'] ?? '')));
            stridebr_flash('info', stridebr_t('friends.sync_ended'));
        } else {
            throw new InvalidArgumentException(stridebr_t('friends.invalid_action'));
        }
        header('Location: /user/amigos.php');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : stridebr_t('friends.update_error');
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$searchResults = [];
if ($search !== '') {
    $usernameSearch = ltrim(stridebr_lower($search), '@');
    $term = '%' . $search . '%';
    $usernameTerm = '%' . $usernameSearch . '%';
    $stmt = $pdo->prepare("SELECT u.idusuario, u.username, COALESCE(NULLIF(u.nome_exibicao, ''), u.nomeusuario) AS nome_exibicao, u.fotousuario, a.status AS amizade_status
        FROM usuarios u
        LEFT JOIN amizades a ON LEAST(a.idusuario_solicitante, a.idusuario_destino) = LEAST(:me_join1, u.idusuario)
          AND GREATEST(a.idusuario_solicitante, a.idusuario_destino) = GREATEST(:me_join2, u.idusuario)
        WHERE u.idusuario <> :me_where AND u.statususuario = 'Ativo' AND u.descobrivel = TRUE
          AND (u.username ILIKE :term_username OR COALESCE(NULLIF(u.nome_exibicao, ''), u.nomeusuario) ILIKE :term_name)
        ORDER BY CASE WHEN lower(u.username) = lower(:exact) THEN 0 ELSE 1 END, u.username NULLS LAST
        LIMIT 20");
    $stmt->execute([':me_join1' => $idUsuario, ':me_join2' => $idUsuario, ':me_where' => $idUsuario, ':term_username' => $usernameTerm, ':term_name' => $term, ':exact' => $usernameSearch]);
    $searchResults = $stmt->fetchAll();
}

$incomingStmt = $pdo->prepare("SELECT u.idusuario, u.username, COALESCE(NULLIF(u.nome_exibicao, ''), u.nomeusuario) AS nome_exibicao, u.fotousuario FROM amizades a JOIN usuarios u ON u.idusuario = a.idusuario_solicitante WHERE a.idusuario_destino = :me AND a.status = 'pendente' ORDER BY a.data_criacao DESC");
$incomingStmt->execute([':me' => $idUsuario]);
$incoming = $incomingStmt->fetchAll();

$outgoingStmt = $pdo->prepare("SELECT u.idusuario, u.username, COALESCE(NULLIF(u.nome_exibicao, ''), u.nomeusuario) AS nome_exibicao FROM amizades a JOIN usuarios u ON u.idusuario = a.idusuario_destino WHERE a.idusuario_solicitante = :me AND a.status = 'pendente' ORDER BY a.data_criacao DESC");
$outgoingStmt->execute([':me' => $idUsuario]);
$outgoing = $outgoingStmt->fetchAll();

$friendsStmt = $pdo->prepare("SELECT u.idusuario, u.username, COALESCE(NULLIF(u.nome_exibicao, ''), u.nomeusuario) AS nome_exibicao, u.fotousuario
    FROM amizades a
    JOIN usuarios u ON u.idusuario = CASE WHEN a.idusuario_solicitante = :me_case THEN a.idusuario_destino ELSE a.idusuario_solicitante END
    WHERE a.status = 'aceita' AND (a.idusuario_solicitante = :me_left OR a.idusuario_destino = :me_right)
    ORDER BY nome_exibicao");
$friendsStmt->execute([':me_case' => $idUsuario, ':me_left' => $idUsuario, ':me_right' => $idUsuario]);
$friends = $friendsStmt->fetchAll();
$sharesStmt = $pdo->prepare("SELECT cs.idcompartilhamento, cs.snapshot, cs.data_criacao, COALESCE(NULLIF(u.nome_exibicao,''), u.nomeusuario) AS origem_nome, u.username FROM cronograma_compartilhamentos cs JOIN usuarios u ON u.idusuario = cs.idusuario_origem WHERE cs.idusuario_destino = :me AND cs.status = 'pendente' AND cs.tipo = 'snapshot' ORDER BY cs.data_criacao DESC");
$sharesStmt->execute([':me' => $idUsuario]);
$shares = $sharesStmt->fetchAll();
foreach ($shares as &$share) {
    $snapshot = is_array($share['snapshot']) ? $share['snapshot'] : (json_decode((string) $share['snapshot'], true) ?: []);
    $share['cronograma_nome'] = (string) ($snapshot['cronograma']['nome'] ?? 'Cronograma compartilhado');
    $share['treinos_total'] = is_array($snapshot['treinos'] ?? null) ? count($snapshot['treinos']) : 0;
}
unset($share);
$syncShares = compartilhamentoSincronizados($pdo, $idUsuario);
$ownedSchedulesStmt = $pdo->prepare("SELECT idcronograma,nome FROM cronogramas WHERE idusuario=:usuario AND ativo=TRUE ORDER BY data_atualizacao DESC,nome");
$ownedSchedulesStmt->execute([':usuario'=>$idUsuario]);
$ownedSchedules = $ownedSchedulesStmt->fetchAll();

$flashes = stridebr_take_flashes();

function friendAvatar(array $user): string {
    return stridebr_profile_photo_url((string) ($user['fotousuario'] ?? ''), 96);
}
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>"><title><?php echo stridebr_e(stridebr_t('friends.page_title')); ?> | StrideBR</title><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/product-insights.css')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body><div class="container-fluid"><?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content"><div class="page-shell friends-shell">
    <div class="page-heading"><h1><?php echo stridebr_e(stridebr_t('friends.page_title')); ?></h1><p><?php echo stridebr_e(stridebr_t('friends.subtitle')); ?></p></div>
    <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
    <form method="GET" class="friend-search content-card"><label for="friend-q"><?php echo stridebr_e(stridebr_t('friends.search_person')); ?></label><div><input id="friend-q" type="search" name="q" value="<?php echo stridebr_e($search); ?>" placeholder="<?php echo stridebr_e(stridebr_t('friends.search_placeholder')); ?>"><button type="submit"><?php echo stridebr_e(stridebr_t('common.search')); ?></button></div></form>

    <?php if ($search !== ''): ?><section class="friend-section"><h2><?php echo stridebr_e(stridebr_t('friends.results')); ?></h2><div class="people-grid">
        <?php if ($searchResults === []): ?><div class="content-card"><strong><?php echo stridebr_e(stridebr_t('friends.no_results')); ?></strong><p><?php echo stridebr_e(stridebr_t('friends.no_results_help')); ?></p></div><?php endif; ?>
        <?php foreach ($searchResults as $user): ?><article class="person-card"><img src="<?php echo stridebr_e(friendAvatar($user)); ?>" alt="" width="48" height="48" loading="lazy" decoding="async"><div><strong><?php echo stridebr_e(stridebr_person_name_for_display((string)$user['nome_exibicao'], (string)($user['username'] ?? ''), stridebr_t('common.user'), 60)); ?></strong><?php if ($user['username']): ?><a href="/u/<?php echo rawurlencode($user['username']); ?>">@<?php echo stridebr_e($user['username']); ?></a><?php endif; ?></div>
            <?php if (!$user['amizade_status']): ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="send"><input type="hidden" name="idusuario" value="<?php echo stridebr_e($user['idusuario']); ?>"><button type="submit"><?php echo stridebr_e(stridebr_t('friends.add')); ?></button></form><?php else: ?><span class="relationship-badge"><?php echo $user['amizade_status'] === 'aceita' ? stridebr_t('friends.accepted') : stridebr_t('friends.pending'); ?></span><?php endif; ?>
        </article><?php endforeach; ?>
    </div></section><?php endif; ?>

    <section class="friend-section" id="cronogramas-sincronizados"><div class="section-title-row"><div><h2><?php echo stridebr_e(stridebr_t('friends.synced_schedules')); ?></h2><p><?php echo stridebr_e(stridebr_t('friends.synced_help')); ?></p></div></div>
        <?php if ($friends && $ownedSchedules): ?><form method="POST" class="content-card friend-search friend-sync-form"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="send_sync"><label><?php echo stridebr_e(stridebr_t('nav.schedule')); ?><select name="idcronograma" required><?php foreach($ownedSchedules as $schedule): ?><option value="<?php echo stridebr_e((string)$schedule['idcronograma']); ?>"><?php echo stridebr_e((string)$schedule['nome']); ?></option><?php endforeach; ?></select></label><label><?php echo stridebr_e(stridebr_t('friends.friend')); ?><select name="idusuario" required><?php foreach($friends as $friend): ?><option value="<?php echo stridebr_e((string)$friend['idusuario']); ?>"><?php echo stridebr_e(stridebr_person_name_for_display((string)$friend['nome_exibicao'],(string)($friend['username']??''),stridebr_t('common.user'),60)); ?></option><?php endforeach; ?></select></label><button type="submit"><?php echo stridebr_e(stridebr_t('friends.send_access')); ?></button></form><?php elseif(!$friends): ?><div class="content-card"><strong><?php echo stridebr_e(stridebr_t('friends.add_friend_sync')); ?></strong><p><?php echo stridebr_e(stridebr_t('friends.sync_reserved')); ?></p></div><?php elseif(!$ownedSchedules): ?><div class="content-card"><strong><?php echo stridebr_e(stridebr_t('friends.create_schedule_first')); ?></strong><a href="/user/cronogramatreinos.php?new=schedule"><?php echo stridebr_e(stridebr_t('schedule.create_schedule')); ?></a></div><?php endif; ?>
        <?php if ($syncShares): ?><div class="notification-list"><?php foreach($syncShares as $sync): $incomingSync=(string)$sync['idusuario_destino']===$idUsuario;$pending=(string)$sync['status']==='pendente'; ?><article class="sync-card"><div><strong><?php echo stridebr_e((string)$sync['cronograma_nome']); ?></strong><p class="insight-muted"><?php if($incomingSync): ?><?php echo stridebr_e(stridebr_t('friends.from')); ?> <?php echo stridebr_e(stridebr_person_name_for_display((string)$sync['origem_nome'],(string)($sync['origem_username']??''),stridebr_t('common.user'),60)); ?><?php else: ?><?php echo stridebr_e(stridebr_t('friends.with')); ?> <?php echo stridebr_e(stridebr_person_name_for_display((string)$sync['destino_nome'],(string)($sync['destino_username']??''),stridebr_t('common.user'),60)); ?><?php endif; ?> · <?php echo stridebr_e(stridebr_t($pending ? 'friends.awaiting_response' : 'friends.synchronized')); ?></p></div><div class="sync-card-actions"><?php if($incomingSync && $pending): ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="accept_sync"><input type="hidden" name="idcompartilhamento" value="<?php echo stridebr_e((string)$sync['idcompartilhamento']); ?>"><button type="submit"><?php echo stridebr_e(stridebr_t('friends.accept')); ?></button></form><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="reject_sync"><input type="hidden" name="idcompartilhamento" value="<?php echo stridebr_e((string)$sync['idcompartilhamento']); ?>"><button type="submit" class="quiet"><?php echo stridebr_e(stridebr_t('friends.decline')); ?></button></form><?php elseif(!$pending): ?><a class="product-button-secondary" href="/user/cronograma-sincronizado.php?id=<?php echo rawurlencode((string)$sync['idcronograma_origem']); ?>"><?php echo stridebr_e(stridebr_t('common.open')); ?></a><form method="POST" data-confirm="<?php echo stridebr_e(stridebr_t('friends.end_sync_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="revoke_sync"><input type="hidden" name="idcompartilhamento" value="<?php echo stridebr_e((string)$sync['idcompartilhamento']); ?>"><button type="submit" class="quiet"><?php echo stridebr_e(stridebr_t($incomingSync ? 'friends.leave' : 'friends.revoke')); ?></button></form><?php else: ?><form method="POST" data-confirm="<?php echo stridebr_e(stridebr_t('friends.cancel_invite_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="revoke_sync"><input type="hidden" name="idcompartilhamento" value="<?php echo stridebr_e((string)$sync['idcompartilhamento']); ?>"><button type="submit" class="quiet"><?php echo stridebr_e(stridebr_t('friends.cancel_invite')); ?></button></form><?php endif; ?></div></article><?php endforeach; ?></div><?php endif; ?>
    </section>

    <?php if ($shares !== []): ?><section class="friend-section"><h2><?php echo stridebr_e(stridebr_t('friends.received_schedules')); ?></h2><div class="shared-schedule-list"><?php foreach ($shares as $share): ?><article class="shared-schedule-card"><div><span><?php echo stridebr_e(stridebr_t('friends.from')); ?> <?php echo stridebr_e($share['origem_nome']); ?><?php echo $share['username'] ? ' · @' . stridebr_e($share['username']) : ''; ?></span><strong><?php echo stridebr_e($share['cronograma_nome']); ?></strong><small><?php echo stridebr_e(stridebr_tn('friends.workout_count.one', 'friends.workout_count.other', (int) $share['treinos_total'], ['count' => (int) $share['treinos_total']])); ?> · <?php echo stridebr_e(stridebr_t('friends.static_copy')); ?></small></div><div class="person-actions"><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="accept_share"><input type="hidden" name="idcompartilhamento" value="<?php echo stridebr_e($share['idcompartilhamento']); ?>"><button type="submit"><?php echo stridebr_e(stridebr_t('friends.add')); ?></button></form><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="reject_share"><input type="hidden" name="idcompartilhamento" value="<?php echo stridebr_e($share['idcompartilhamento']); ?>"><button type="submit" class="quiet"><?php echo stridebr_e(stridebr_t('friends.decline')); ?></button></form></div></article><?php endforeach; ?></div></section><?php endif; ?>

    <?php if ($incoming !== []): ?><section class="friend-section"><h2><?php echo stridebr_e(stridebr_t('friends.requests')); ?></h2><div class="people-grid"><?php foreach ($incoming as $user): ?><article class="person-card"><img src="<?php echo stridebr_e(friendAvatar($user)); ?>" alt="" width="48" height="48" loading="lazy" decoding="async"><div><strong><?php echo stridebr_e(stridebr_person_name_for_display((string)$user['nome_exibicao'], (string)($user['username'] ?? ''), stridebr_t('common.user'), 60)); ?></strong><?php if ($user['username']): ?><span>@<?php echo stridebr_e($user['username']); ?></span><?php endif; ?></div><div class="person-actions"><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="accept"><input type="hidden" name="idusuario" value="<?php echo stridebr_e($user['idusuario']); ?>"><button type="submit"><?php echo stridebr_e(stridebr_t('friends.accept')); ?></button></form><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="reject"><input type="hidden" name="idusuario" value="<?php echo stridebr_e($user['idusuario']); ?>"><button type="submit" class="quiet"><?php echo stridebr_e(stridebr_t('friends.decline')); ?></button></form></div></article><?php endforeach; ?></div></section><?php endif; ?>

    <section class="friend-section"><div class="section-title-row"><h2><?php echo stridebr_e(stridebr_t('friends.your_friends')); ?></h2><span><?php echo count($friends); ?></span></div><div class="people-grid">
        <?php if ($friends === []): ?><div class="content-card"><strong><?php echo stridebr_e(stridebr_t('friends.empty')); ?></strong><p><?php echo stridebr_e(stridebr_t('friends.empty_help')); ?></p><a href="#friend-q"><?php echo stridebr_e(stridebr_t('friends.search_people')); ?></a></div><?php endif; ?>
        <?php foreach ($friends as $user): ?><article class="person-card"><img src="<?php echo stridebr_e(friendAvatar($user)); ?>" alt="" width="48" height="48" loading="lazy" decoding="async"><div><strong><?php echo stridebr_e(stridebr_person_name_for_display((string)$user['nome_exibicao'], (string)($user['username'] ?? ''), stridebr_t('common.user'), 60)); ?></strong><?php if ($user['username']): ?><a href="/u/<?php echo rawurlencode($user['username']); ?>">@<?php echo stridebr_e($user['username']); ?></a><?php endif; ?></div><form method="POST" data-confirm="<?php echo stridebr_e(stridebr_t('friends.remove_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="remove"><input type="hidden" name="idusuario" value="<?php echo stridebr_e($user['idusuario']); ?>"><button type="submit" class="quiet"><?php echo stridebr_e(stridebr_t('friends.remove')); ?></button></form></article><?php endforeach; ?>
    </div></section>
    <?php if ($outgoing !== []): ?><p class="friend-pending-note"><?php echo stridebr_e(stridebr_tn('friends.pending_sent', 'friends.pending_sent', count($outgoing), ['count' => count($outgoing)])); ?></p><?php endif; ?>
</div></main></div><?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?></body></html>
