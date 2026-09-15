<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/function/teams_surface_provider.php';

$idUsuario = stridebr_require_login();
if (!stridebr_teams_enabled()) stridebr_error_document(404);
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
$context = stridebr_teams_surface_context($pdo, $idUsuario);
$teams = (array) ($context['my_teams'] ?? []);
?>
<!doctype html><html lang="<?php echo stridebr_e(stridebr_html_lang()); ?>"><head><?php echo stridebr_ui_boot_script(); ?><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/institutional.css')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>"><title><?php echo stridebr_e(stridebr_t('teams.my_teams')); ?> | StrideBR</title></head><body><div class="container-fluid"><?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?><main class="main-content"><div class="institutional-shell"><header class="institutional-head"><div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('nav.people')); ?></span><h1><?php echo stridebr_e(stridebr_t('teams.my_teams')); ?></h1></div></header><?php if ($teams === []): ?><section class="institutional-card institutional-empty"><p><?php echo stridebr_e(stridebr_t('teams.empty')); ?></p></section><?php else: ?><div class="institutional-grid"><?php foreach ($teams as $item): ?><a class="institutional-card institutional-card-link" href="/user/equipe.php?team=<?php echo rawurlencode((string) $item['team']['ref']); ?>&amp;season=<?php echo rawurlencode((string) $item['season']['ref']); ?>"><span class="institutional-badge"><?php echo stridebr_e((string) $item['season']['label']); ?></span><h2><?php echo stridebr_e((string) $item['team']['name']); ?></h2><p><?php echo stridebr_e((string) $item['organization']['name']); ?></p><div class="institutional-meta"><span><?php echo stridebr_e(implode(', ', (array) $item['roles'])); ?></span><?php if (!empty($item['group'])): ?><span><?php echo stridebr_e((string) $item['group']); ?></span><?php endif; ?></div></a><?php endforeach; ?></div><?php endif; ?></div></main></div><?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?></body></html>
