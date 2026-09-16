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
            stridebr_flash('success', 'Rota salva.');
            header('Location: /user/rotas.php?id=' . rawurlencode($id));
            exit;
        }
        if ($action === 'archive' || $action === 'restore') {
            if (!routeSavedArchive($pdo, $idUsuario, $id, $action === 'archive')) throw new InvalidArgumentException('Rota não encontrada.');
            stridebr_flash('success', $action === 'archive' ? 'Rota arquivada.' : 'Rota reativada.');
            header('Location: /user/rotas.php' . ($action === 'restore' ? '?status=archived' : ''));
            exit;
        }
        throw new InvalidArgumentException('Ação inválida.');
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível atualizar a rota.';
    }
}

$status = (string) ($_GET['status'] ?? 'active') === 'archived' ? 'archived' : 'active';
$routes = routeSavedList($pdo, $idUsuario, $status === 'archived');
if ($status === 'archived') $routes = array_values(array_filter($routes, static fn(array $route): bool => stridebr_db_bool($route['arquivada'] ?? false)));
$id = trim((string) ($_GET['id'] ?? ''));
$detail = $id !== '' ? routeSavedGet($pdo, $idUsuario, $id) : null;
if ($id !== '' && $detail === null) $errors[] = 'Rota não encontrada.';
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
<html lang="pt-BR">
<head>
    <?php echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/routes.css')); ?>">
    <title>Minhas Rotas | StrideBR</title>
</head>
<body>
<div class="container-fluid">
<?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content routes-page" data-routes-page>
    <header class="routes-head"><div><span>Atividades</span><h1>Minhas Rotas</h1><p>Rotas salvas para repetir em treinos.</p></div><?php if ($routes !== [] || $detail !== null): ?><a class="secondary-button" href="/user/atividades.php?with_route=1">Atividades com rota</a><?php endif; ?></header>
    <?php foreach ($errors as $error): ?><div class="routes-error" role="alert"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
    <?php if ($routes === [] && $detail === null): ?>
        <section class="routes-zero-state"><h2>Nenhuma rota salva.</h2><p>Abra uma atividade com GPS e use “Salvar rota” para reutilizar esse percurso em futuros treinos.</p><div><a class="primary-button" href="/user/atividades.php?with_route=1">Ver atividades com rota</a><a class="secondary-button" href="/user/atividades.php">Como funciona</a></div></section>
    <?php else: ?>
    <nav class="routes-status" aria-label="Estado das rotas"><a class="<?php echo $status === 'active' ? 'is-active' : ''; ?>" href="/user/rotas.php">Ativas</a><a class="<?php echo $status === 'archived' ? 'is-active' : ''; ?>" href="/user/rotas.php?status=archived">Arquivadas</a></nav>
    <div class="routes-layout">
        <section class="routes-library" aria-label="Rotas salvas">
            <?php if ($routes === []): ?>
                <div class="routes-empty"><strong>Nenhuma rota aqui.</strong><span>Abra uma atividade com GPS e use “Salvar rota”.</span></div>
            <?php else: ?>
                <div class="routes-grid">
                <?php foreach ($routes as $route): ?>
                    <a class="route-card<?php echo $detail && (string) $detail['idrota_salva'] === (string) $route['idrota_salva'] ? ' is-active' : ''; ?>" href="/user/rotas.php?id=<?php echo rawurlencode((string) $route['idrota_salva']); ?><?php echo $status === 'archived' ? '&status=archived' : ''; ?>">
                        <span class="route-card-map" data-route-mini-map="<?php echo stridebr_e((string) $route['idrota_salva']); ?>" aria-hidden="true"></span>
                        <span class="route-card-copy"><strong><?php echo stridebr_e((string) $route['nome']); ?></strong><small><?php echo stridebr_e((string) ($route['modalidade_nome'] ?? 'Rota')); ?><?php if (is_numeric($route['distancia_m'] ?? null)): ?> · <?php echo stridebr_e(number_format((float) $route['distancia_m'] / 1000, 2, ',', '.')); ?> km<?php endif; ?><?php if (is_numeric($route['ganho_elevacao_m'] ?? null)): ?> · +<?php echo stridebr_e(number_format((float) $route['ganho_elevacao_m'], 0, ',', '.')); ?> m<?php endif; ?></small><small><?php echo (int) ($route['atividades_count'] ?? 0); ?> atividades<?php if (!empty($route['ultima_atividade'])): ?> · último uso <?php echo stridebr_e(stridebr_format_date_short((string) $route['ultima_atividade'])); ?><?php endif; ?></small></span>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <section class="route-detail" aria-live="polite">
        <?php if ($detail === null): ?>
            <div class="routes-empty"><strong>Selecione uma rota.</strong><span>Veja mapa, uso e opções de treino.</span></div>
        <?php else: ?>
            <div class="route-detail-head"><div><span><?php echo stridebr_e((string) ($detail['modalidade_nome'] ?? 'Rota')); ?></span><h2><?php echo stridebr_e((string) $detail['nome']); ?></h2></div><span class="route-privacy"><?php echo stridebr_e((string) $detail['privacidade']); ?></span></div>
            <div class="route-detail-map" data-route-detail-map="<?php echo stridebr_e((string) $detail['idrota_salva']); ?>" aria-label="Mapa da rota"></div>
            <div class="route-stats">
                <div><strong><?php echo is_numeric($detail['distancia_m'] ?? null) ? stridebr_e(number_format((float) $detail['distancia_m'] / 1000, 2, ',', '.')) . ' km' : '—'; ?></strong><span>Distância</span></div>
                <div><strong><?php echo is_numeric($detail['ganho_elevacao_m'] ?? null) ? '+' . stridebr_e(number_format((float) $detail['ganho_elevacao_m'], 0, ',', '.')) . ' m' : '—'; ?></strong><span>Elevação</span></div>
                <div><strong><?php echo count((array) ($detail['atividades'] ?? [])); ?></strong><span>atividades</span></div>
            </div>
            <div class="route-actions"><a class="primary-button" href="/user/cronogramatreinos.php?new=workout&route=<?php echo rawurlencode((string) $detail['idrota_salva']); ?>">Usar em treino</a><?php if (!empty($detail['idatividade_origem'])): ?><a class="secondary-button" href="/user/atividades.php?atividade=<?php echo rawurlencode((string) $detail['idatividade_origem']); ?>">Atividade de origem</a><?php endif; ?><?php if (count((array) ($detail['atividades'] ?? [])) >= 2): ?><a class="secondary-button" href="/user/comparar-atividades.php?a=<?php echo rawurlencode((string) $detail['atividades'][0]['idregistro']); ?>&b=<?php echo rawurlencode((string) $detail['atividades'][1]['idregistro']); ?>">Comparar usos</a><?php endif; ?></div>
            <form method="POST" class="route-settings">
                <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?php echo stridebr_e((string) $detail['idrota_salva']); ?>">
                <label>Nome<input name="nome" maxlength="140" required value="<?php echo stridebr_e((string) $detail['nome']); ?>"></label>
                <label>Privacidade<select name="privacidade"><option value="privado"<?php echo $detail['privacidade'] === 'privado' ? ' selected' : ''; ?>>Privado</option><option value="nao_listado"<?php echo $detail['privacidade'] === 'nao_listado' ? ' selected' : ''; ?>>Não listado</option><option value="publico"<?php echo $detail['privacidade'] === 'publico' ? ' selected' : ''; ?>>Público</option></select></label>
                <label>Pacer Plan<select name="idpacerplan"><option value="">Sem Pacer</option><?php foreach ($pacerPlans as $plan): ?><?php if ((string) ($detail['idmodalidade'] ?? '') !== '' && (string) ($plan['sport']['id'] ?? '') !== (string) $detail['idmodalidade']) continue; ?><option value="<?php echo stridebr_e((string) $plan['id']); ?>"<?php echo (string) ($detail['idpacerplan'] ?? '') === (string) $plan['id'] ? ' selected' : ''; ?>><?php echo stridebr_e((string) $plan['name']); ?></option><?php endforeach; ?></select></label>
                <button class="primary-button" type="submit">Salvar</button>
            </form>
            <section class="route-history"><div class="route-section-head"><strong>Histórico</strong><span>atividades vinculadas a esta rota.</span></div><?php if (($detail['atividades'] ?? []) === []): ?><p>Nenhuma atividade vinculada ainda.</p><?php else: ?><div><?php foreach ($detail['atividades'] as $activity): ?><a href="/user/atividades.php?atividade=<?php echo rawurlencode((string) $activity['idregistro']); ?>"><span><strong><?php echo stridebr_e((string) $activity['titulo']); ?></strong><small><?php echo stridebr_e(stridebr_format_datetime_short((string) $activity['data_inicio'])); ?></small></span><span><?php echo is_numeric($activity['distancia_metros'] ?? null) ? stridebr_e(number_format((float) $activity['distancia_metros'] / 1000, 2, ',', '.')) . ' km' : ''; ?></span></a><?php endforeach; ?></div><?php endif; ?></section>
            <form method="POST" class="route-archive" data-confirm="<?php echo stridebr_e(stridebr_db_bool($detail['arquivada'] ?? false) ? 'Reativar esta rota?' : 'Arquivar esta rota?'); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="id" value="<?php echo stridebr_e((string) $detail['idrota_salva']); ?>"><input type="hidden" name="action" value="<?php echo stridebr_db_bool($detail['arquivada'] ?? false) ? 'restore' : 'archive'; ?>"><button type="submit"><?php echo stridebr_db_bool($detail['arquivada'] ?? false) ? 'Reativar rota' : 'Arquivar rota'; ?></button></form>
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
