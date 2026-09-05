<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/integrations.php';

$username = stridebr_lower(trim((string) ($_GET['username'] ?? '')));
if ($username === '') { stridebr_error_document(404); }

$stmt = $pdo->prepare("SELECT idusuario, nomeusuario, nome_exibicao, username, fotousuario, biousuario, pronomesusuario, visibilidadeperfil, dataregistrousuario, preferenciasusuario FROM usuarios WHERE lower(username) = lower(:username) AND statususuario = 'Ativo' LIMIT 1");
$stmt->execute([':username' => $username]);
$profile = $stmt->fetch();
if (!$profile) { stridebr_error_document(404); }

$viewer = stridebr_is_logged_in() ? (string) ($_SESSION['IdUsuario'] ?? '') : '';
$isSelf = $viewer !== '' && $viewer === $profile['idusuario'];
if (!$isSelf && !stridebr_feature_enabled($pdo, 'public_profiles.enabled', false)) { stridebr_error_document(404); }

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
$activityStats = ['atividades' => 0, 'distancia_m' => 0.0, 'duracao_s' => 0.0];

if ($canView) {
    $sportsStmt = $pdo->prepare("SELECT m.nome FROM modalidades_usuario mu JOIN modalidades m ON m.idmodalidade = mu.idmodalidade WHERE mu.idusuario = :id AND mu.ativo = TRUE ORDER BY mu.favorita DESC, m.nome");
    $sportsStmt->execute([':id' => $profile['idusuario']]);
    $sports = array_column($sportsStmt->fetchAll(), 'nome');

    if ($isSelf) {
        $scheduleSql = "SELECT idcronograma, nome, descricao, visibilidade FROM cronogramas WHERE idusuario = :id AND ativo = TRUE ORDER BY data_atualizacao DESC LIMIT 12";
        $activityPrivacySql = '';
    } elseif ($isFriend) {
        $scheduleSql = "SELECT idcronograma, nome, descricao, visibilidade FROM cronogramas WHERE idusuario = :id AND ativo = TRUE AND visibilidade IN ('publico', 'amigos') ORDER BY data_atualizacao DESC LIMIT 12";
        $activityPrivacySql = " AND ra.visibilidade IN ('publico', 'amigos')";
    } else {
        $scheduleSql = "SELECT idcronograma, nome, descricao, visibilidade FROM cronogramas WHERE idusuario = :id AND ativo = TRUE AND visibilidade = 'publico' ORDER BY data_atualizacao DESC LIMIT 12";
        $activityPrivacySql = " AND ra.visibilidade = 'publico'";
    }

    $scheduleStmt = $pdo->prepare($scheduleSql);
    $scheduleStmt->execute([':id' => $profile['idusuario']]);
    $schedules = $scheduleStmt->fetchAll();

    $statsStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT ra.idregistro) AS atividades,
                COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'distancia'), 0) AS distancia_m,
                COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'duracao'), 0) AS duracao_s
         FROM registros_atividade ra
         LEFT JOIN valores_atividade va ON va.idregistro = ra.idregistro
         LEFT JOIN campos_modelo cm ON cm.idcampo = va.idcampo
         WHERE ra.idusuario = :id AND ra.excluido_em IS NULL AND ra.status = 'concluido'" . $activityPrivacySql
    );
    $statsStmt->execute([':id' => $profile['idusuario']]);
    $activityStats = $statsStmt->fetch() ?: $activityStats;
}

$photo = stridebr_profile_photo_url((string) ($profile['fotousuario'] ?? ''), 320);
$displayName = stridebr_person_name_for_display((string) ($profile['nome_exibicao'] ?: $profile['nomeusuario']), (string) ($profile['username'] ?? ''), 'Usuário', 60);
$profilePreferences = is_array($profile['preferenciasusuario'] ?? null) ? $profile['preferenciasusuario'] : (json_decode((string) ($profile['preferenciasusuario'] ?? '{}'), true) ?: []);
$profileSocials = is_array($profilePreferences['social_links'] ?? null) ? array_filter($profilePreferences['social_links'], static fn($value): bool => is_string($value) && trim($value) !== '') : [];
if ($canView) {
    foreach (stridebr_integrations_list($pdo, (string) $profile['idusuario']) as $providerId => $connection) {
        if ((string) ($connection['status'] ?? '') !== 'conectado' || !stridebr_db_bool($connection['mostrar_perfil'] ?? false)) continue;
        $publicUrl = trim((string) ($connection['perfil_publico_url'] ?? ''));
        if ($publicUrl !== '' && filter_var($publicUrl, FILTER_VALIDATE_URL)) $profileSocials['connected_' . $providerId] = $publicUrl;
    }
}
$profileBanner = is_array($profilePreferences['profile_banner'] ?? null) ? $profilePreferences['profile_banner'] : [];
$profileBannerColor = strtolower(trim((string) ($profileBanner['color'] ?? '#1d3150')));
if (preg_match('/^#[0-9a-f]{6}$/', $profileBannerColor) !== 1) $profileBannerColor = '#1d3150';
$profileBannerImage = trim((string) ($profileBanner['image'] ?? ''));
$profileHighlights = is_array($profilePreferences['profile_highlights'] ?? null) ? array_values($profilePreferences['profile_highlights']) : [];
if ($profileHighlights === []) $profileHighlights = [['type' => 'activities'], ['type' => 'distance'], ['type' => 'duration'], ['type' => 'sports']];
$profileHighlights = array_slice($profileHighlights, 0, 4);
$socialLabels = ['instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'strava' => 'Strava', 'garmin' => 'Garmin Connect', 'polar' => 'Polar Flow', 'suunto' => 'Suunto', 'fitbit' => 'Fitbit', 'hevy' => 'Hevy', 'github' => 'GitHub', 'website' => 'Site', 'connected_garmin' => 'Garmin Connect', 'connected_strava' => 'Strava', 'connected_polar' => 'Polar Flow', 'connected_suunto' => 'Suunto', 'connected_fitbit' => 'Fitbit'];
$memberSince = (new DateTimeImmutable((string) $profile['dataregistrousuario']))->format('m/Y');
$distanceKm = ((float) ($activityStats['distancia_m'] ?? 0)) / 1000;
$durationSeconds = (float) ($activityStats['duracao_s'] ?? 0);
$durationLabel = $durationSeconds >= 3600
    ? number_format($durationSeconds / 3600, $durationSeconds >= 36000 ? 0 : 1, ',', '.') . ' h'
    : (string) max(0, (int) round($durationSeconds / 60)) . ' min';
$distanceLabel = $distanceKm >= 100 ? number_format($distanceKm, 0, ',', '.') . ' km' : number_format($distanceKm, 1, ',', '.') . ' km';
$highlightValues = [
    'activities' => [number_format((int) ($activityStats['atividades'] ?? 0), 0, ',', '.'), 'atividades'],
    'distance' => [$distanceLabel, 'distância'],
    'duration' => [$durationLabel, 'tempo'],
    'sports' => [(string) count($sports), 'esportes'],
    'member_since' => [$memberSince, 'membro desde'],
];
$renderedHighlights = [];
foreach ($profileHighlights as $highlight) {
    if (!is_array($highlight)) continue;
    $type = (string) ($highlight['type'] ?? '');
    if ($type === 'custom') {
        $label = trim((string) ($highlight['label'] ?? ''));
        $value = trim((string) ($highlight['value'] ?? ''));
        if ($label !== '' && $value !== '') $renderedHighlights[] = [$value, $label];
        continue;
    }
    if (isset($highlightValues[$type])) $renderedHighlights[] = $highlightValues[$type];
}
if ($renderedHighlights === []) $renderedHighlights = [$highlightValues['activities'], $highlightValues['distance'], $highlightValues['duration']];
$profileCanonical = stridebr_public_url() . '/u/' . rawurlencode((string) $profile['username']);
$profileMetaDescription = $canView && trim((string) ($profile['biousuario'] ?? '')) !== ''
    ? trim(preg_replace('/\s+/u', ' ', (string) $profile['biousuario']) ?? '')
    : $displayName . ' no StrideBR — atividades, esportes e cronogramas compartilhados.';
if (stridebr_length($profileMetaDescription) > 180) {
    $profileMetaDescription = (function_exists('mb_substr') ? mb_substr($profileMetaDescription, 0, 177, 'UTF-8') : substr($profileMetaDescription, 0, 177)) . '...';
}
$profileMetaImage = $canView && trim((string) ($profile['fotousuario'] ?? '')) !== '' ? $photo : stridebr_social_image_url();
if (!str_starts_with($profileMetaImage, 'http')) $profileMetaImage = stridebr_public_url() . $profileMetaImage;
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="<?php echo stridebr_e($profileMetaDescription); ?>">
    <meta property="og:type" content="profile">
    <meta property="og:site_name" content="StrideBR">
    <meta property="og:title" content="<?php echo stridebr_e($displayName); ?> | StrideBR">
    <meta property="og:description" content="<?php echo stridebr_e($profileMetaDescription); ?>">
    <meta property="og:image" content="<?php echo stridebr_e($profileMetaImage); ?>">
    <meta property="og:url" content="<?php echo stridebr_e($profileCanonical); ?>">
    <meta name="twitter:card" content="summary">
    <link rel="canonical" href="<?php echo stridebr_e($profileCanonical); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title><?php echo stridebr_e($displayName); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell profile-shell profile-page-v2">
            <section class="profile-banner">
                <div class="profile-banner-cover" aria-hidden="true" style="--profile-banner-color: <?php echo stridebr_e($profileBannerColor); ?>;<?php echo $profileBannerImage !== '' ? ' --profile-banner-image: url(&quot;' . stridebr_e($profileBannerImage) . '&quot;);' : ''; ?>"></div>
                <div class="profile-banner-content">
                    <img class="profile-avatar-large" src="<?php echo stridebr_e($photo); ?>" alt="Foto de <?php echo stridebr_e($displayName); ?>" width="128" height="128" decoding="async">
                    <div class="profile-identity">
                        <span class="profile-handle">@<?php echo stridebr_e((string) $profile['username']); ?></span>
                        <h1><?php echo stridebr_e($displayName); ?></h1>
                        <?php if ($canView && !empty($profile['pronomesusuario'])): ?><span class="profile-pronouns"><?php echo stridebr_e((string) $profile['pronomesusuario']); ?></span><?php endif; ?>
                        <?php if ($canView && !empty($profile['biousuario'])): ?><p><?php echo nl2br(stridebr_e((string) $profile['biousuario'])); ?></p><?php endif; ?>
                    </div>
                    <div class="profile-primary-action">
                        <?php if ($isSelf): ?>
                            <a class="secondary-button" href="/user/edit-profile.php"><?php echo stridebr_e(stridebr_t('profile.edit')); ?></a>
                        <?php elseif ($isFriend): ?>
                            <span class="profile-friend-badge"><?php echo stridebr_e(stridebr_t('common.friends')); ?></span>
                        <?php elseif ($viewer !== ''): ?>
                            <a class="primary-button" href="/user/amigos.php?q=<?php echo urlencode('@' . (string) $profile['username']); ?>"><?php echo stridebr_e(stridebr_t('profile.add_friend')); ?></a>
                        <?php endif; ?>
                        <button type="button" class="secondary-button" data-copy-profile-link><?php echo stridebr_e(stridebr_t('profile.copy_link')); ?></button>
                    </div>
                </div>
                <?php if ($canView): ?>
                    <div class="profile-banner-bottom">
                        <span><?php echo stridebr_e(stridebr_t('profile.member_since')); ?> <?php echo stridebr_e($memberSince); ?></span>
                        <?php if ($profileSocials !== []): ?><nav class="profile-social-inline" aria-label="<?php echo stridebr_e(stridebr_t('profile.links')); ?>"><?php foreach ($profileSocials as $socialKey => $socialUrl): ?><?php if (!isset($socialLabels[$socialKey])) continue; ?><a href="<?php echo stridebr_e((string) $socialUrl); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php echo stridebr_e($socialLabels[$socialKey]); ?><span aria-hidden="true">↗</span></a><?php endforeach; ?></nav><?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if (!$canView): ?>
                <section class="content-card private-profile profile-private-v2"><strong><?php echo stridebr_e(stridebr_t('profile.private')); ?></strong><p><?php echo stridebr_e(stridebr_t('profile.private_help')); ?></p></section>
            <?php else: ?>
                <div class="profile-overview-grid">
                    <section class="content-card profile-summary-card">
                        <div class="profile-section-heading"><div><span><?php echo stridebr_e(stridebr_t('profile.summary')); ?></span><h2><?php echo stridebr_e(stridebr_t('profile.highlights')); ?></h2></div><?php if ($isSelf): ?><a class="profile-section-edit" href="/user/settings.php#destaques"><?php echo stridebr_e(stridebr_t('common.edit')); ?></a><?php endif; ?></div>
                        <div class="profile-stat-grid" style="--profile-highlight-count: <?php echo max(1, count($renderedHighlights)); ?>;">
                            <?php foreach ($renderedHighlights as [$highlightValue, $highlightLabel]): ?><article><strong><?php echo stridebr_e((string) $highlightValue); ?></strong><span><?php echo stridebr_e((string) $highlightLabel); ?></span></article><?php endforeach; ?>
                        </div>
                    </section>

                    <section class="content-card profile-about-card">
                        <div class="profile-section-heading"><div><span><?php echo stridebr_e(stridebr_t('common.profile')); ?></span><h2><?php echo stridebr_e(stridebr_t('profile.sports')); ?></h2></div></div>
                        <?php if ($sports !== []): ?><div class="profile-chips"><?php foreach ($sports as $sport): ?><span><?php echo stridebr_e($sport); ?></span><?php endforeach; ?></div><?php elseif ($isSelf): ?><div class="profile-empty-state"><strong><?php echo stridebr_e(stridebr_t('profile.no_sports')); ?></strong><a class="secondary-button" href="/user/settings.php#esportes"><?php echo stridebr_e(stridebr_t('profile.choose_sports')); ?></a></div><?php else: ?><p class="profile-empty-copy"><?php echo stridebr_e(stridebr_t('profile.no_sports_added')); ?></p><?php endif; ?>
                    </section>
                </div>

                <section class="profile-section profile-schedule-section">
                    <div class="profile-section-heading"><div><span><?php echo stridebr_e(stridebr_t('profile.planning')); ?></span><h2><?php echo stridebr_e(stridebr_t('profile.shared_schedules')); ?></h2></div><span class="profile-section-count"><?php echo count($schedules); ?></span></div>
                    <div class="profile-schedules">
                        <?php if ($schedules === []): ?><div class="content-card profile-empty-card"><strong><?php echo $isSelf ? 'Nenhum cronograma para mostrar ainda' : 'Nada compartilhado por enquanto'; ?></strong><p><?php echo $isSelf ? 'Crie um cronograma e escolha a visibilidade do perfil.' : 'Quando houver um cronograma disponível para você, ele aparece aqui.'; ?></p><?php if ($isSelf): ?><a class="secondary-button" href="/user/cronogramatreinos.php"><?php echo stridebr_e(stridebr_t('profile.create_schedule')); ?></a><?php endif; ?></div><?php endif; ?>
                        <?php foreach ($schedules as $schedule): ?>
                            <article class="content-card profile-schedule-card">
                                <div class="profile-schedule-meta"><span><?php echo stridebr_e($schedule['visibilidade'] === 'publico' ? 'Público' : ($schedule['visibilidade'] === 'amigos' ? 'Amigos' : 'Privado')); ?></span></div>
                                <h3><?php echo stridebr_e((string) $schedule['nome']); ?></h3>
                                <?php if ($schedule['descricao']): ?><p><?php echo stridebr_e((string) $schedule['descricao']); ?></p><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</body>
</html>
