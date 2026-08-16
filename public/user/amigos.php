<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma_compartilhar.php';

if (!stridebr_feature_enabled($pdo, 'friends.enabled', false)) {
    http_response_code(404);
    exit('A área de amigos está temporariamente indisponível.');
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
            stridebr_flash('success', 'Solicitação de amizade enviada.');
        } elseif ($action === 'accept') {
            $stmt = $pdo->prepare("UPDATE amizades SET status = 'aceita', data_atualizacao = NOW() WHERE idusuario_solicitante = :target AND idusuario_destino = :me AND status = 'pendente'");
            $stmt->execute([':target' => $target, ':me' => $idUsuario]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('Solicitação não encontrada.');
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
            $stmt = $pdo->prepare("SELECT snapshot FROM cronograma_compartilhamentos WHERE idcompartilhamento = :id AND idusuario_destino = :me AND tipo = 'snapshot' AND status = 'pendente'");
            $stmt->execute([':id' => $idShare, ':me' => $idUsuario]);
            $raw = $stmt->fetchColumn();
            if ($raw === false) throw new RuntimeException('Compartilhamento não encontrado.');
            $snapshot = is_array($raw) ? $raw : json_decode((string) $raw, true, 64, JSON_THROW_ON_ERROR);
            $idNovo = compartilhamentoImportarSnapshot($pdo, $idUsuario, $snapshot);
            $pdo->prepare("UPDATE cronograma_compartilhamentos SET status = 'aceito', data_atualizacao = NOW() WHERE idcompartilhamento = :id AND idusuario_destino = :me AND status = 'pendente'")->execute([':id' => $idShare, ':me' => $idUsuario]);
            stridebr_flash('success', 'Cronograma adicionado à sua conta.');
            header('Location: /user/cronogramatreinos.php?id=' . urlencode($idNovo));
            exit;
        } elseif ($action === 'reject_share') {
            $idShare = trim((string) ($_POST['idcompartilhamento'] ?? ''));
            $stmt = $pdo->prepare("UPDATE cronograma_compartilhamentos SET status = 'recusado', data_atualizacao = NOW() WHERE idcompartilhamento = :id AND idusuario_destino = :me AND status = 'pendente'");
            $stmt->execute([':id' => $idShare, ':me' => $idUsuario]);
            stridebr_flash('info', 'Compartilhamento recusado.');
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
        WHERE u.idusuario <> :me_where AND u.statususuario = 'Ativo'
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
$flashes = stridebr_take_flashes();

function friendAvatar(array $user): string {
    $photo = trim((string) ($user['fotousuario'] ?? ''));
    if ($photo !== '' && (str_starts_with($photo, '/') || filter_var($photo, FILTER_VALIDATE_URL))) return $photo;
    return '/assets/img/ui/userdefault.svg';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>"><title>Amigos | StrideBR</title></head>
<body><div class="container-fluid"><?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content"><div class="page-shell friends-shell">
    <div class="page-heading"><h1>Amigos</h1><p>Encontre pessoas pelo username e compartilhe treinos sem transformar o StrideBR em um feed.</p></div>
    <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
    <form method="GET" class="friend-search content-card"><label for="friend-q">Buscar pessoa</label><div><input id="friend-q" type="search" name="q" value="<?php echo stridebr_e($search); ?>" placeholder="@username ou nome"><button type="submit">Buscar</button></div></form>

    <?php if ($search !== ''): ?><section class="friend-section"><h2>Resultados</h2><div class="people-grid">
        <?php if ($searchResults === []): ?><p class="content-card">Nenhum usuário encontrado.</p><?php endif; ?>
        <?php foreach ($searchResults as $user): ?><article class="person-card"><img src="<?php echo stridebr_e(friendAvatar($user)); ?>" alt=""><div><strong><?php echo stridebr_e($user['nome_exibicao']); ?></strong><?php if ($user['username']): ?><a href="/u/<?php echo rawurlencode($user['username']); ?>">@<?php echo stridebr_e($user['username']); ?></a><?php endif; ?></div>
            <?php if (!$user['amizade_status']): ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="send"><input type="hidden" name="idusuario" value="<?php echo stridebr_e($user['idusuario']); ?>"><button type="submit">Adicionar</button></form><?php else: ?><span class="relationship-badge"><?php echo $user['amizade_status'] === 'aceita' ? 'Amigo' : 'Pendente'; ?></span><?php endif; ?>
        </article><?php endforeach; ?>
    </div></section><?php endif; ?>

    <?php if ($shares !== []): ?><section class="friend-section"><h2>Cronogramas recebidos</h2><div class="shared-schedule-list"><?php foreach ($shares as $share): ?><article class="shared-schedule-card"><div><span>De <?php echo stridebr_e($share['origem_nome']); ?><?php echo $share['username'] ? ' · @' . stridebr_e($share['username']) : ''; ?></span><strong><?php echo stridebr_e($share['cronograma_nome']); ?></strong><small><?php echo (int) $share['treinos_total']; ?> treino(s) · cópia estática</small></div><div class="person-actions"><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="accept_share"><input type="hidden" name="idcompartilhamento" value="<?php echo stridebr_e($share['idcompartilhamento']); ?>"><button type="submit">Adicionar</button></form><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="reject_share"><input type="hidden" name="idcompartilhamento" value="<?php echo stridebr_e($share['idcompartilhamento']); ?>"><button type="submit" class="quiet">Recusar</button></form></div></article><?php endforeach; ?></div></section><?php endif; ?>

    <?php if ($incoming !== []): ?><section class="friend-section"><h2>Solicitações</h2><div class="people-grid"><?php foreach ($incoming as $user): ?><article class="person-card"><img src="<?php echo stridebr_e(friendAvatar($user)); ?>" alt=""><div><strong><?php echo stridebr_e($user['nome_exibicao']); ?></strong><?php if ($user['username']): ?><span>@<?php echo stridebr_e($user['username']); ?></span><?php endif; ?></div><div class="person-actions"><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="accept"><input type="hidden" name="idusuario" value="<?php echo stridebr_e($user['idusuario']); ?>"><button type="submit">Aceitar</button></form><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="reject"><input type="hidden" name="idusuario" value="<?php echo stridebr_e($user['idusuario']); ?>"><button type="submit" class="quiet">Recusar</button></form></div></article><?php endforeach; ?></div></section><?php endif; ?>

    <section class="friend-section"><div class="section-title-row"><h2>Seus amigos</h2><span><?php echo count($friends); ?></span></div><div class="people-grid">
        <?php if ($friends === []): ?><div class="content-card"><p>Quando você aceitar uma amizade, ela aparece aqui.</p></div><?php endif; ?>
        <?php foreach ($friends as $user): ?><article class="person-card"><img src="<?php echo stridebr_e(friendAvatar($user)); ?>" alt=""><div><strong><?php echo stridebr_e($user['nome_exibicao']); ?></strong><?php if ($user['username']): ?><a href="/u/<?php echo rawurlencode($user['username']); ?>">@<?php echo stridebr_e($user['username']); ?></a><?php endif; ?></div><form method="POST" onsubmit="return confirm('Remover esta amizade?');"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="remove"><input type="hidden" name="idusuario" value="<?php echo stridebr_e($user['idusuario']); ?>"><button type="submit" class="quiet">Remover</button></form></article><?php endforeach; ?>
    </div></section>
    <?php if ($outgoing !== []): ?><p class="friend-pending-note"><?php echo count($outgoing); ?> solicitação(ões) enviada(s) aguardando resposta.</p><?php endif; ?>
</div></main></div><?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?></body></html>
