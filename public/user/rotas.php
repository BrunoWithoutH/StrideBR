<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/routes.php';
require_once dirname(__DIR__, 2) . '/src/function/pacer_service.php';

$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    stridebr_verify_csrf();
    try {
        $action = trim((string) ($_POST['action'] ?? ''));
        $id = trim((string) ($_POST['id'] ?? ''));
        if ($action === 'update') {
            routeSavedUpdate($pdo, $idUsuario, $id, $_POST);
            stridebr_flash('success', stridebr_t('routes.saved_message'));
            header('Location: /user/rotas.php?id=' . rawurlencode($id));
            exit;
        }
        if ($action === 'archive' || $action === 'restore') {
            if (!routeSavedArchive($pdo, $idUsuario, $id, $action === 'archive')) throw new InvalidArgumentException(stridebr_t('routes.not_found'));
            stridebr_flash('success', $action === 'archive' ? stridebr_t('routes.archived_message') : stridebr_t('routes.restored_message'));
            header('Location: /user/rotas.php' . ($action === 'restore' ? '?status=archived' : ''));
            exit;
        }
        throw new InvalidArgumentException(stridebr_t('routes.invalid_action'));
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : stridebr_t('routes.update_error');
    }
}

$status = (string) ($_GET['status'] ?? 'active') === 'archived' ? 'archived' : 'active';
$routes = routeSavedList($pdo, $idUsuario, $status === 'archived');
if ($status === 'archived') $routes = array_values(array_filter($routes, static fn(array $route): bool => stridebr_db_bool($route['arquivada'] ?? false)));
$routeTotal = count($routes);
$routeSearch = trim((string) ($_GET['q'] ?? ''));
$routeSport = trim((string) ($_GET['sport'] ?? ''));
$routeDistance = (string) ($_GET['distance'] ?? '');
$routeSports = [];
foreach ($routes as $route) if (!empty($route['idmodalidade'])) $routeSports[(string) $route['idmodalidade']] = stridebr_sport_name((string) ($route['modalidade_slug'] ?? ''), (string) $route['modalidade_nome']);
$routes = array_values(array_filter($routes, static function (array $route) use ($routeSearch, $routeSport, $routeDistance): bool {
    if ($routeSearch !== '' && !str_contains(stridebr_lower((string) $route['nome']), stridebr_lower($routeSearch))) return false;
    if ($routeSport !== '' && (string) ($route['idmodalidade'] ?? '') !== $routeSport) return false;
    $distance = $route['distancia_m'] ?? null;
    if ($routeDistance === 'short' && (!is_numeric($distance) || (float) $distance >= 10000)) return false;
    if ($routeDistance === 'long' && (!is_numeric($distance) || (float) $distance < 10000)) return false;
    return true;
}));
$id = trim((string) ($_GET['id'] ?? ''));
$detail = $id !== '' ? routeSavedGet($pdo, $idUsuario, $id) : null;
if ($id !== '' && $detail === null) $errors[] = stridebr_t('routes.not_found');
$pacerPlans = pacerPlanList($pdo, $idUsuario, ['status' => 'active']);
$routePayload = static function (array $route): array {
    $geo = json_decode((string) ($route['coordenadas'] ?? ''), true);
    return [
        'id' => (string) ($route['idrota_salva'] ?? ''),
        'coordinates' => is_array($geo) && ($geo['type'] ?? '') === 'LineString' && is_array($geo['coordinates'] ?? null) ? $geo['coordinates'] : [],
    ];
};
$mapRoutes = array_map($routePayload, $routes);
if ($detail !== null && !array_filter($mapRoutes, static fn(array $route): bool => $route['id'] === (string) $detail['idrota_salva'])) $mapRoutes[] = $routePayload($detail);
?>
<!DOCTYPE html>
<html lang="<?php echo stridebr_e(stridebr_html_lang()); ?>">
<head>
    <?php echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/routes.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t('routes.title')); ?> | StrideBR</title>
</head>
<body>
<div class="container-fluid">
<?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content routes-page" data-routes-page>
    <header class="routes-head"><div><span><?php echo stridebr_e(stridebr_t('nav.tools')); ?></span><h1><?php echo stridebr_e(stridebr_t('routes.title')); ?></h1></div><a class="primary-button" href="/user/atividades.php?with_route=1"><?php echo stridebr_e(stridebr_t('routes.new')); ?></a></header>
    <form method="GET" class="routes-toolbar">
        <input type="hidden" name="status" value="<?php echo stridebr_e($status); ?>">
        <label><span class="sr-only"><?php echo stridebr_e(stridebr_t('routes.search')); ?></span><input type="search" name="q" value="<?php echo stridebr_e($routeSearch); ?>" placeholder="<?php echo stridebr_e(stridebr_t('routes.search')); ?>"></label>
        <label><span class="sr-only"><?php echo stridebr_e(stridebr_t('routes.sport')); ?></span><select name="sport"><option value=""><?php echo stridebr_e(stridebr_t('routes.all_sports')); ?></option><?php foreach ($routeSports as $sportId => $sportName): ?><option value="<?php echo stridebr_e($sportId); ?>"<?php echo $routeSport === (string) $sportId ? ' selected' : ''; ?>><?php echo stridebr_e($sportName); ?></option><?php endforeach; ?></select></label>
        <label><span class="sr-only"><?php echo stridebr_e(stridebr_t('routes.distance')); ?></span><select name="distance"><option value=""><?php echo stridebr_e(stridebr_t('routes.all_distances')); ?></option><option value="short"<?php echo $routeDistance === 'short' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('routes.short')); ?></option><option value="long"<?php echo $routeDistance === 'long' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('routes.long')); ?></option></select></label>
        <button class="secondary-button" type="submit"><?php echo stridebr_e(stridebr_t('routes.submit')); ?></button>
    </form>
    <?php foreach ($errors as $error): ?><div class="routes-error" role="alert"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
    <nav class="routes-status" aria-label="<?php echo stridebr_e(stridebr_t('routes.status')); ?>"><a class="<?php echo $status === 'active' ? 'is-active' : ''; ?>" href="/user/rotas.php"><?php echo stridebr_e(stridebr_t('routes.active')); ?></a><a class="<?php echo $status === 'archived' ? 'is-active' : ''; ?>" href="/user/rotas.php?status=archived"><?php echo stridebr_e(stridebr_t('routes.archived')); ?></a></nav>
    <?php if ($routeTotal === 0 && $detail === null): ?>
        <section class="routes-zero-state"><h2><?php echo stridebr_e(stridebr_t('routes.empty')); ?></h2><p><?php echo stridebr_e(stridebr_t('routes.create_help')); ?></p><div><a class="primary-button" href="/user/atividades.php?with_route=1"><?php echo stridebr_e(stridebr_t('routes.new')); ?></a><a class="secondary-button" href="/user/atividades.php"><?php echo stridebr_e(stridebr_t('nav.activities')); ?></a></div></section>
    <?php else: ?>
    <div class="routes-layout">
        <section class="routes-library" aria-label="<?php echo stridebr_e(stridebr_t('routes.saved')); ?>">
            <?php if ($routes === []): ?>
                <div class="routes-empty"><strong><?php echo stridebr_e(stridebr_t('routes.no_results')); ?></strong><span><?php echo stridebr_e(stridebr_t('routes.create_help')); ?></span></div>
            <?php else: ?>
                <div class="routes-grid">
                <?php foreach ($routes as $route): ?>
                    <article class="route-card<?php echo $detail && (string) $detail['idrota_salva'] === (string) $route['idrota_salva'] ? ' is-active' : ''; ?>" >
                        <span class="route-card-map" data-route-mini-map="<?php echo stridebr_e((string) $route['idrota_salva']); ?>" aria-hidden="true"></span>
                        <span class="route-card-copy"><a href="/user/rotas.php?id=<?php echo rawurlencode((string) $route['idrota_salva']); ?><?php echo $status === 'archived' ? '&status=archived' : ''; ?>"><strong><?php echo stridebr_e((string) $route['nome']); ?></strong></a><small><?php echo stridebr_e(stridebr_sport_name((string) ($route['modalidade_slug'] ?? ''), (string) ($route['modalidade_nome'] ?? stridebr_t('routes.route')))); ?><?php if (is_numeric($route['distancia_m'] ?? null)): ?> · <?php echo stridebr_e(number_format((float) $route['distancia_m'] / 1000, 2, ',', '.')); ?> km<?php endif; ?><?php if (is_numeric($route['ganho_elevacao_m'] ?? null)): ?> · +<?php echo stridebr_e(number_format((float) $route['ganho_elevacao_m'], 0, ',', '.')); ?> m<?php endif; ?></small><small><?php echo (int) ($route['atividades_count'] ?? 0); ?> <?php echo stridebr_e(stridebr_t('routes.activities')); ?><?php if (!empty($route['ultima_atividade'])): ?> · <?php echo stridebr_e(stridebr_t('routes.last_use')); ?> <?php echo stridebr_e(stridebr_format_date_short((string) $route['ultima_atividade'])); ?><?php endif; ?></small></span>
                        <details class="route-card-menu"><summary aria-label="<?php echo stridebr_e(stridebr_t('common.more_options')); ?>">…</summary><div><a href="/user/rotas.php?id=<?php echo rawurlencode((string) $route['idrota_salva']); ?>&edit=1#route-edit"><?php echo stridebr_e(stridebr_t('routes.edit')); ?></a><form method="POST" data-confirm="<?php echo stridebr_e(stridebr_t($status === 'archived' ? 'routes.restore_confirm' : 'routes.archive_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="id" value="<?php echo stridebr_e((string) $route['idrota_salva']); ?>"><input type="hidden" name="action" value="<?php echo $status === 'archived' ? 'restore' : 'archive'; ?>"><button type="submit"><?php echo stridebr_e(stridebr_t($status === 'archived' ? 'routes.restore' : 'routes.archive')); ?></button></form></div></details>
                    </article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <section class="route-detail" aria-live="polite">
        <?php if ($detail === null): ?>
            <div class="routes-empty"><strong><?php echo stridebr_e(stridebr_t('routes.select')); ?></strong><span><?php echo stridebr_e(stridebr_t('routes.select_help')); ?></span></div>
        <?php else: ?>
            <div class="route-detail-head"><div><span><?php echo stridebr_e(stridebr_sport_name((string) ($detail['modalidade_slug'] ?? ''), (string) ($detail['modalidade_nome'] ?? stridebr_t('routes.route')))); ?></span><h2><?php echo stridebr_e((string) $detail['nome']); ?></h2></div><span class="route-privacy"><?php echo stridebr_e((string) $detail['privacidade']); ?></span></div>
            <div class="route-detail-map" data-route-detail-map="<?php echo stridebr_e((string) $detail['idrota_salva']); ?>" aria-label="<?php echo stridebr_e(stridebr_t('routes.map')); ?>"></div>
            <div class="route-stats">
                <div><strong><?php echo is_numeric($detail['distancia_m'] ?? null) ? stridebr_e(number_format((float) $detail['distancia_m'] / 1000, 2, ',', '.')) . ' km' : '—'; ?></strong><span><?php echo stridebr_e(stridebr_t('routes.distance')); ?></span></div>
                <div><strong><?php echo is_numeric($detail['ganho_elevacao_m'] ?? null) ? '+' . stridebr_e(number_format((float) $detail['ganho_elevacao_m'], 0, ',', '.')) . ' m' : '—'; ?></strong><span><?php echo stridebr_e(stridebr_t('routes.elevation')); ?></span></div>
                <div><strong><?php echo count((array) ($detail['atividades'] ?? [])); ?></strong><span><?php echo stridebr_e(stridebr_t('routes.activities')); ?></span></div>
            </div>
            <div class="route-actions"><a class="primary-button" href="/user/cronogramatreinos.php?new=workout&route=<?php echo rawurlencode((string) $detail['idrota_salva']); ?>"><?php echo stridebr_e(stridebr_t('routes.use')); ?></a><?php if (!empty($detail['idatividade_origem'])): ?><a class="secondary-button" href="/user/atividades.php?atividade=<?php echo rawurlencode((string) $detail['idatividade_origem']); ?>"><?php echo stridebr_e(stridebr_t('routes.source')); ?></a><?php endif; ?><?php if (count((array) ($detail['atividades'] ?? [])) >= 2): ?><a class="secondary-button" href="/user/comparar-atividades.php?a=<?php echo rawurlencode((string) $detail['atividades'][0]['idregistro']); ?>&b=<?php echo rawurlencode((string) $detail['atividades'][1]['idregistro']); ?>"><?php echo stridebr_e(stridebr_t('routes.compare')); ?></a><?php endif; ?></div>
            <details class="route-edit" id="route-edit"<?php echo isset($_GET['edit']) ? ' open' : ''; ?>><summary><?php echo stridebr_e(stridebr_t('routes.edit_route')); ?></summary><form method="POST" class="route-settings">
                <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?php echo stridebr_e((string) $detail['idrota_salva']); ?>">
                <label><?php echo stridebr_e(stridebr_t('routes.name')); ?><input name="nome" maxlength="140" required value="<?php echo stridebr_e((string) $detail['nome']); ?>"></label>
                <label><?php echo stridebr_e(stridebr_t('routes.privacy')); ?><select name="privacidade"><option value="privado"<?php echo $detail['privacidade'] === 'privado' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('routes.private')); ?></option><option value="nao_listado"<?php echo $detail['privacidade'] === 'nao_listado' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('routes.unlisted')); ?></option><option value="publico"<?php echo $detail['privacidade'] === 'publico' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('routes.public')); ?></option></select></label>
                <label>Pacer Plan<select name="idpacerplan"><option value=""><?php echo stridebr_e(stridebr_t('routes.no_pacer')); ?></option><?php foreach ($pacerPlans as $plan): ?><?php if ((string) ($detail['idmodalidade'] ?? '') !== '' && (string) ($plan['sport']['id'] ?? '') !== (string) $detail['idmodalidade']) continue; ?><option value="<?php echo stridebr_e((string) $plan['id']); ?>"<?php echo (string) ($detail['idpacerplan'] ?? '') === (string) $plan['id'] ? ' selected' : ''; ?>><?php echo stridebr_e((string) $plan['name']); ?></option><?php endforeach; ?></select></label>
                <button class="primary-button" type="submit"><?php echo stridebr_e(stridebr_t('routes.save')); ?></button>
            </form></details>
            <section class="route-history"><div class="route-section-head"><strong><?php echo stridebr_e(stridebr_t('routes.history')); ?></strong><span><?php echo stridebr_e(stridebr_t('routes.history_help')); ?></span></div><?php if (($detail['atividades'] ?? []) === []): ?><p><?php echo stridebr_e(stridebr_t('routes.history_empty')); ?></p><?php else: ?><div><?php foreach ($detail['atividades'] as $activity): ?><a href="/user/atividades.php?atividade=<?php echo rawurlencode((string) $activity['idregistro']); ?>"><span><strong><?php echo stridebr_e((string) $activity['titulo']); ?></strong><small><?php echo stridebr_e(stridebr_format_datetime_short((string) $activity['data_inicio'])); ?></small></span><span><?php echo is_numeric($activity['distancia_metros'] ?? null) ? stridebr_e(number_format((float) $activity['distancia_metros'] / 1000, 2, ',', '.')) . ' km' : ''; ?></span></a><?php endforeach; ?></div><?php endif; ?></section>
            <form method="POST" class="route-archive" data-confirm="<?php echo stridebr_e(stridebr_db_bool($detail['arquivada'] ?? false) ? stridebr_t('routes.restore_confirm') : stridebr_t('routes.archive_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="id" value="<?php echo stridebr_e((string) $detail['idrota_salva']); ?>"><input type="hidden" name="action" value="<?php echo stridebr_db_bool($detail['arquivada'] ?? false) ? 'restore' : 'archive'; ?>"><button type="submit"><?php echo stridebr_db_bool($detail['arquivada'] ?? false) ? stridebr_t('routes.restore') : stridebr_t('routes.archive'); ?></button></form>
        <?php endif; ?>
        </section>
    </div>
    <?php endif; ?>
    <?php if ($mapRoutes !== []): ?><script type="application/json" data-routes-map-data><?php echo json_encode($mapRoutes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script><?php endif; ?>
</main>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<?php echo stridebr_maps_runtime_script(); ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/web-map.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/routes.js')); ?>"></script>
</body>
</html>
