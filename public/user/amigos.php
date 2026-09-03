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
        if (in_array($action, ['send', 'accept', 'reject', 'remove'], true) && ($target === '' || $target === $idUsuario)) throw new InvalidArgumentException('Usuário inválido.');
        if ($action === 'send') {
            $exists = $pdo->prepare('SELECT 1 FROM usuarios WHERE idusuario = :id AND statususuario = \'Ativo\'');
            $exists->execute([':id' => $target]);
            if (!$exists->fetchColumn()) throw new RuntimeException('Usuário não encontrado.');
            $pair = $pdo->prepare('SELECT status FROM amizades WHERE LEAST(idusuario_solicitante, idusuario_destino) = LEAST(:me1, :target1) AND GREATEST(idusuario_solicitante, idusuario_destino) = GREATEST(:me2, :target2) LIMIT 1');
            $pair->execute([':me1' => $idUsuario, ':target1' => $target, ':me2' => $idUsuario, ':target2' => $target]);
            if ($pair->fetchColumn()) throw new RuntimeException('Já existe uma solicitação ou amizade com esse usuário.');
            $pdo->prepare('INSERT INTO amizades (idamizade, idusuario_solicitante, idusuario_destino) VALUES (:id, :me, :target)')->execute([
                ':id' => stridebr_generate_id(), ':me' => $idUsuario, ':target' => $target,
            ]);
            notificacaoCriar($pdo, $target, 'amizade_solicitacao', 'Nova solicitação de amizade', 'Alguém quer adicionar você no StrideBR.', '/user/amigos.php');
            stridebr_flash('success', 'Solicitação de amizade enviada.');
        } elseif ($action === 'accept') {
            $stmt = $pdo->prepare("UPDATE amizades SET status = 'aceita', data_atualizacao = NOW() WHERE idusuario_solicitante = :target AND idusuario_destino = :me AND status = 'pendente'");
            $stmt->execute([':target' => $target, ':me' => $idUsuario]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('Solicitação não encontrada.');
            notificacaoCriar($pdo, $target, 'amizade_aceita', 'Solicitação de amizade aceita', 'Sua solicitação de amizade foi aceita.', '/user/amigos.php');
            stridebr_flash('success', 'Amizade aceita.');
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("DELETE FROM amizades WHERE idusuario_solicitante = :target AND idusuario_destino = :me AND status = 'pendente'");
            $stmt->execute([':target' => $target, ':me' => $idUsuario]);
            stridebr_flash('info', 'Solicitação removida.');
        } elseif ($action === 'remove') {
            $stmt = $pdo->prepare("DELETE FROM amizades WHERE status = 'aceita' AND ((idusuario_solicitante = :me1 AND idusuario_destino = :target1) OR (idusuario_solicitante = :target2 AND idusuario_destino = :me2))");
            $stmt->execute([':me1' => $idUsuario, ':target1' => $target, ':target2' => $target, ':me2' => $idUsuario]);
            stridebr_flash('info', 'Amizade removida.');
        } elseif ($action === 'accept_share') {
            $idShare = trim((string) ($_POST['idcompartilhamento'] ?? ''));
            $idNovo = compartilhamentoAceitarSnapshot($pdo, $idUsuario, $idShare);
            stridebr_flash('success', 'Cronograma adicionado à sua conta.');
            header('Location: /user/cronogramatreinos.php?id=' . urlencode($idNovo));
            exit;
        } elseif ($action === 'reject_share') {
            $idShare = trim((string) ($_POST['idcompartilhamento'] ?? ''));
            compartilhamentoRecusarSnapshot($pdo, $idUsuario, $idShare);
            stridebr_flash('info', 'Compartilhamento recusado.');
        } elseif ($action === 'send_sync') {
            $scheduleId = trim((string) ($_POST['idcronograma'] ?? ''));
            $shareId = compartilhamentoEnviarSincronizado($pdo, $idUsuario, $scheduleId, $target);
            $schedule = cronogramaBuscar($pdo, $scheduleId, $idUsuario);
            notificacaoCriar($pdo, $target, 'cronograma_sincronizado_convite', 'Convite para cronograma sincronizado', 'Você recebeu acesso em modo leitura a “' . (string) ($schedule['nome'] ?? 'um cronograma') . '”.', '/user/amigos.php#cronogramas-sincronizados', ['share_id' => $shareId]);
            productAnalyticsRegistrar($pdo, $idUsuario, 'schedule_shared', ['type' => 'synced']);
            stridebr_flash('success', 'Convite para cronograma sincronizado enviado.');
        } elseif ($action === 'accept_sync') {
            $shareId = trim((string) ($_POST['idcompartilhamento'] ?? ''));
            $infoStmt = $pdo->prepare("SELECT idusuario_origem FROM cronograma_compartilhamentos WHERE idcompartilhamento=:id AND idusuario_destino=:me LIMIT 1");
            $infoStmt->execute([':id'=>$shareId, ':me'=>$idUsuario]);
            $source = (string) ($infoStmt->fetchColumn() ?: '');
            $scheduleId = compartilhamentoAceitarSincronizado($pdo, $idUsuario, $shareId);
            if ($source !== '') notificacaoCriar($pdo, $source, 'cronograma_sincronizado_aceito', 'Cronograma sincronizado aceito', 'Seu amigo agora acompanha a versão atual do cronograma.', '/user/amigos.php#cronogramas-sincronizados');
            stridebr_flash('success', 'Cronograma sincronizado adicionado em modo leitura.');
            header('Location: /user/cronograma-sincronizado.php?id=' . rawurlencode($scheduleId));
            exit;
        } elseif ($action === 'reject_sync') {
            compartilhamentoRecusarSincronizado($pdo, $idUsuario, trim((string) ($_POST['idcompartilhamento'] ?? '')));
            stridebr_flash('info', 'Convite sincronizado recusado.');
        } elseif ($action === 'revoke_sync') {
            compartilhamentoRevogarSincronizado($pdo, $idUsuario, trim((string) ($_POST['idcompartilhamento'] ?? '')));
            stridebr_flash('info', 'Acesso sincronizado encerrado.');
        } else {
            throw new InvalidArgumentException('Ação inválida.');
        }
        header('Location: /user/amigos.php');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível atualizar seus amigos.';
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
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>"><title>Amigos | StrideBR</title><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/product-insights.css')); ?>"></head>
<body><div class="container-fluid"><?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content"><div class="page-shell friends-shell">
    <div class="page-heading"><h1>Amigos</h1><p>Encontre pessoas pelo username e compartilhe treinos.</p></div>
    <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
    <form method="GET" class="friend-search content-card"><label for="friend-q">Buscar pessoa</label><div><input id="friend-q" type="search" name="q" value="<?php echo stridebr_e($search); ?>" placeholder="@username ou nome"><button type="submit">Buscar</button></div></form>

    <?php if ($search !== ''): ?><section class="friend-section"><h2>Resultados</h2><div class="people-grid">
        <?php if ($searchResults === []): ?><div class="content-card"><strong>Ninguém apareceu nessa busca.</strong><p>Tente o @username exato ou parte do nome da pessoa.</p></div><?php endif; ?>
        <?php foreach ($searchResults as $user): ?><article class="person-card"><img src="<?php echo stridebr_e(friendAvatar($user)); ?>" alt="" width="48" height="48" loading="lazy" decoding="async"><div><strong><?php echo stridebr_e(stridebr_person_name_for_display((string)$user['nome_exibicao'], (string)($user['username'] ?? ''), 'Usuário', 60)); ?></strong><?php if ($user['username']): ?><a href="/u/<?php echo rawurlencode($user['username']); ?>">@<?php echo stridebr_e($user['username']); ?></a><?php endif; ?></div>
            <?php if (!$user['amizade_status']): ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="send"><input type="hidden" name="idusuario" value="<?php echo stridebr_e($user['idusuario']); ?>"><button type="submit">Adicionar</button></form><?php else: ?><span class="relationship-badge"><?php echo $user['amizade_status'] === 'aceita' ? 'Amigo' : 'Pendente'; ?></span><?php endif; ?>
        </article><?php endforeach; ?>
    </div></section><?php endif; ?>

    <section class="friend-section" id="cronogramas-sincronizados"><div class="section-title-row"><div><h2>Cronogramas sincronizados</h2><p>Compartilhe um cronograma em modo leitura. Alterações no original aparecem para o convidado.</p></div></div>
        <?php if ($friends && $ownedSchedules): ?><form method="POST" class="content-card friend-search" style="margin-bottom:14px"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="send_sync"><label>Cronograma<select name="idcronograma" required><?php foreach($ownedSchedules as $schedule): ?><option value="<?php echo stridebr_e((string)$schedule['idcronograma']); ?>"><?php echo stridebr_e((string)$schedule['nome']); ?></option><?php endforeach; ?></select></label><label>Amigo<select name="idusuario" required><?php foreach($friends as $friend): ?><option value="<?php echo stridebr_e((string)$friend['idusuario']); ?>"><?php echo stridebr_e(stridebr_person_name_for_display((string)$friend['nome_exibicao'],(string)($friend['username']??''),'Usuário',60)); ?></option><?php endforeach; ?></select></label><button type="submit">Enviar acesso</button></form><?php elseif(!$friends): ?><div class="content-card"><strong>Adicione um amigo para sincronizar cronogramas.</strong><p>O compartilhamento sincronizado é reservado a pessoas que já estão na sua lista.</p></div><?php elseif(!$ownedSchedules): ?><div class="content-card"><strong>Crie um cronograma antes de compartilhar.</strong><a href="/user/cronogramatreinos.php?new=schedule">Criar cronograma</a></div><?php endif; ?>
        <?php if ($syncShares): ?><div class="notification-list"><?php foreach($syncShares as $sync): $incomingSync=(string)$sync['idusuario_destino']===$idUsuario;$pending=(string)$sync['status']==='pendente'; ?><article class="sync-card"><div><strong><?php echo stridebr_e((string)$sync['cronograma_nome']); ?></strong><p class="insight-muted"><?php if($incomingSync): ?>De <?php echo stridebr_e(stridebr_person_name_for_display((string)$sync['origem_nome'],(string)($sync['origem_username']??''),'Usuário',60)); ?><?php else: ?>Com <?php echo stridebr_e(stridebr_person_name_for_display((string)$sync['destino_nome'],(string)($sync['destino_username']??''),'Usuário',60)); ?><?php endif; ?> · <?php echo $pending?'aguardando resposta':'sincronizado'; ?></p></div><div class="sync-card-actions"><?php if($incomingSync && $pending): ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="accept_sync"><input type="hidden" name="idcompartilhamento" value="<?php echo stridebr_e((string)$sync['idcompartilhamento']); ?>"><button type="submit">Aceitar</button></form><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="reject_sync"><input type="hidden" name="idcompartilhamento" value="<?php echo stridebr_e((string)$sync['idcompartilhamento']); ?>"><button type="submit" class="quiet">Recusar</button></form><?php elseif(!$pending): ?><a class="product-button-secondary" href="/user/cronograma-sincronizado.php?id=<?php echo rawurlencode((string)$sync['idcronograma_origem']); ?>">Abrir</a><form method="POST" data-confirm="Encerrar este acesso sincronizado?"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="revoke_sync"><input type="hidden" name="idcompartilhamento" value="<?php echo stridebr_e((string)$sync['idcompartilhamento']); ?>"><button type="submit" class="quiet"><?php echo $incomingSync?'Sair':'Revogar'; ?></button></form><?php else: ?><form method="POST" data-confirm="Cancelar este convite?"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="revoke_sync"><input type="hidden" name="idcompartilhamento" value="<?php echo stridebr_e((string)$sync['idcompartilhamento']); ?>"><button type="submit" class="quiet">Cancelar convite</button></form><?php endif; ?></div></article><?php endforeach; ?></div><?php endif; ?>
    </section>

    <?php if ($shares !== []): ?><section class="friend-section"><h2>Cronogramas recebidos</h2><div class="shared-schedule-list"><?php foreach ($shares as $share): ?><article class="shared-schedule-card"><div><span>De <?php echo stridebr_e($share['origem_nome']); ?><?php echo $share['username'] ? ' · @' . stridebr_e($share['username']) : ''; ?></span><strong><?php echo stridebr_e($share['cronograma_nome']); ?></strong><small><?php echo (int) $share['treinos_total']; ?> treino(s) · cópia estática</small></div><div class="person-actions"><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="accept_share"><input type="hidden" name="idcompartilhamento" value="<?php echo stridebr_e($share['idcompartilhamento']); ?>"><button type="submit">Adicionar</button></form><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="reject_share"><input type="hidden" name="idcompartilhamento" value="<?php echo stridebr_e($share['idcompartilhamento']); ?>"><button type="submit" class="quiet">Recusar</button></form></div></article><?php endforeach; ?></div></section><?php endif; ?>

    <?php if ($incoming !== []): ?><section class="friend-section"><h2>Solicitações</h2><div class="people-grid"><?php foreach ($incoming as $user): ?><article class="person-card"><img src="<?php echo stridebr_e(friendAvatar($user)); ?>" alt="" width="48" height="48" loading="lazy" decoding="async"><div><strong><?php echo stridebr_e(stridebr_person_name_for_display((string)$user['nome_exibicao'], (string)($user['username'] ?? ''), 'Usuário', 60)); ?></strong><?php if ($user['username']): ?><span>@<?php echo stridebr_e($user['username']); ?></span><?php endif; ?></div><div class="person-actions"><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="accept"><input type="hidden" name="idusuario" value="<?php echo stridebr_e($user['idusuario']); ?>"><button type="submit">Aceitar</button></form><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="reject"><input type="hidden" name="idusuario" value="<?php echo stridebr_e($user['idusuario']); ?>"><button type="submit" class="quiet">Recusar</button></form></div></article><?php endforeach; ?></div></section><?php endif; ?>

    <section class="friend-section"><div class="section-title-row"><h2>Seus amigos</h2><span><?php echo count($friends); ?></span></div><div class="people-grid">
        <?php if ($friends === []): ?><div class="content-card"><strong>Sua lista ainda está vazia.</strong><p>Busque alguém acima pelo @username para começar a compartilhar cronogramas.</p><a href="#friend-q">Buscar pessoas</a></div><?php endif; ?>
        <?php foreach ($friends as $user): ?><article class="person-card"><img src="<?php echo stridebr_e(friendAvatar($user)); ?>" alt="" width="48" height="48" loading="lazy" decoding="async"><div><strong><?php echo stridebr_e(stridebr_person_name_for_display((string)$user['nome_exibicao'], (string)($user['username'] ?? ''), 'Usuário', 60)); ?></strong><?php if ($user['username']): ?><a href="/u/<?php echo rawurlencode($user['username']); ?>">@<?php echo stridebr_e($user['username']); ?></a><?php endif; ?></div><form method="POST" data-confirm="Remover esta amizade?"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="remove"><input type="hidden" name="idusuario" value="<?php echo stridebr_e($user['idusuario']); ?>"><button type="submit" class="quiet">Remover</button></form></article><?php endforeach; ?>
    </div></section>
    <?php if ($outgoing !== []): ?><p class="friend-pending-note"><?php echo count($outgoing); ?> solicitação(ões) enviada(s) aguardando resposta.</p><?php endif; ?>
</div></main></div><?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?></body></html>
