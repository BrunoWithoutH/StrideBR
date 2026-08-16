<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';

$username = stridebr_lower(trim((string) ($_GET['username'] ?? '')));
if ($username === '') { http_response_code(404); exit('Perfil não encontrado.'); }
$stmt = $pdo->prepare("SELECT idusuario, nomeusuario, nome_exibicao, username, fotousuario, biousuario, visibilidadeperfil, dataregistrousuario FROM usuarios WHERE lower(username) = lower(:username) AND statususuario = 'Ativo' LIMIT 1");
$stmt->execute([':username' => $username]);
$profile = $stmt->fetch();
if (!$profile) { http_response_code(404); exit('Perfil não encontrado.'); }

$viewer = stridebr_is_logged_in() ? (string) ($_SESSION['IdUsuario'] ?? '') : '';
$isSelf = $viewer !== '' && $viewer === $profile['idusuario'];
if (!$isSelf && !stridebr_feature_enabled($pdo, 'public_profiles.enabled', false)) { http_response_code(404); exit('Perfil não encontrado.'); }
$isFriend = false;
if ($viewer !== '' && !$isSelf) {
    $friendStmt = $pdo->prepare("SELECT 1 FROM amizades WHERE status = 'aceita' AND ((idusuario_solicitante = :viewer1 AND idusuario_destino = :profile1) OR (idusuario_solicitante = :profile2 AND idusuario_destino = :viewer2)) LIMIT 1");
    $friendStmt->execute([':viewer1' => $viewer, ':profile1' => $profile['idusuario'], ':profile2' => $profile['idusuario'], ':viewer2' => $viewer]);
    $isFriend = (bool) $friendStmt->fetchColumn();
}
$visibility = (string) $profile['visibilidadeperfil'];
$canView = $isSelf || $visibility === 'publico' || ($visibility === 'amigos' && $isFriend);

$sports = [];
$schedules = [];
if ($canView) {
    $sportsStmt = $pdo->prepare("SELECT m.nome FROM modalidades_usuario mu JOIN modalidades m ON m.idmodalidade = mu.idmodalidade WHERE mu.idusuario = :id AND mu.ativo = TRUE ORDER BY m.nome");
    $sportsStmt->execute([':id' => $profile['idusuario']]);
    $sports = array_column($sportsStmt->fetchAll(), 'nome');
    if ($isSelf) {
        $scheduleSql = "SELECT idcronograma, nome, descricao, visibilidade FROM cronogramas WHERE idusuario = :id AND ativo = TRUE ORDER BY data_atualizacao DESC LIMIT 12";
    } elseif ($isFriend) {
        $scheduleSql = "SELECT idcronograma, nome, descricao, visibilidade FROM cronogramas WHERE idusuario = :id AND ativo = TRUE AND visibilidade IN ('publico', 'amigos') ORDER BY data_atualizacao DESC LIMIT 12";
    } else {
        $scheduleSql = "SELECT idcronograma, nome, descricao, visibilidade FROM cronogramas WHERE idusuario = :id AND ativo = TRUE AND visibilidade = 'publico' ORDER BY data_atualizacao DESC LIMIT 12";
    }
    $scheduleStmt = $pdo->prepare($scheduleSql);
    $scheduleStmt->execute([':id' => $profile['idusuario']]);
    $schedules = $scheduleStmt->fetchAll();
}
$photo = trim((string) ($profile['fotousuario'] ?? '')) ?: '/assets/img/ui/userdefault.svg';
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>"><title><?php echo stridebr_e($profile['nome_exibicao'] ?: $profile['nomeusuario']); ?> | StrideBR</title></head><body><div class="container-fluid"><?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?><main class="main-content"><div class="page-shell profile-shell">
<section class="profile-hero content-card"><img src="<?php echo stridebr_e($photo); ?>" alt=""><div><span>@<?php echo stridebr_e($profile['username']); ?></span><h1><?php echo stridebr_e($profile['nome_exibicao'] ?: $profile['nomeusuario']); ?></h1><?php if ($canView && !empty($profile['biousuario'])): ?><p><?php echo nl2br(stridebr_e($profile['biousuario'])); ?></p><?php endif; ?></div><?php if ($isSelf): ?><a href="/user/settings.php">Editar perfil</a><?php elseif ($viewer !== '' && !$isFriend): ?><a href="/user/amigos.php?q=<?php echo urlencode('@' . $profile['username']); ?>">Adicionar amigo</a><?php endif; ?></section>
<?php if (!$canView): ?><section class="content-card private-profile"><h2>Perfil privado</h2><p>Esse usuário compartilha detalhes apenas com amigos.</p></section><?php else: ?>
<?php if ($sports !== []): ?><section class="profile-section"><h2>Esportes</h2><div class="profile-chips"><?php foreach ($sports as $sport): ?><span><?php echo stridebr_e($sport); ?></span><?php endforeach; ?></div></section><?php endif; ?>
<section class="profile-section"><h2>Cronogramas compartilhados</h2><div class="profile-schedules"><?php if ($schedules === []): ?><div class="content-card"><p>Nenhum cronograma compartilhado por aqui.</p></div><?php endif; ?><?php foreach ($schedules as $schedule): ?><article class="content-card"><span><?php echo stridebr_e($schedule['visibilidade']); ?></span><h3><?php echo stridebr_e($schedule['nome']); ?></h3><?php if ($schedule['descricao']): ?><p><?php echo stridebr_e($schedule['descricao']); ?></p><?php endif; ?></article><?php endforeach; ?></div></section>
<?php endif; ?>
</div></main></div><?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?></body></html>
