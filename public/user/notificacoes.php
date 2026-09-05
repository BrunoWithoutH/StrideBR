<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/notificacoes.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'read_all') notificacaoMarcarTodasLidas($pdo, $idUsuario);
    elseif ($action === 'read') notificacaoMarcarLida($pdo, $idUsuario, trim((string) ($_POST['idnotificacao'] ?? '')));
    header('Location: /user/notificacoes.php');
    exit;
}
$items = notificacaoListar($pdo, $idUsuario, 80);
?>
<!DOCTYPE html><html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>"><head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/product-insights.css')); ?>"><title><?php echo stridebr_e(stridebr_t('notifications.page_title')); ?></title><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head><body><div class="container-fluid"><?php require dirname(__DIR__,2).'/src/layout/header.php'; ?><main class="main-content product-page"><header class="product-page-header"><div><h1><?php echo stridebr_e(stridebr_t('notifications.title')); ?></h1></div><?php if(array_filter($items,static fn(array $n):bool=>empty($n['lida_em']))): ?><form method="post"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="read_all"><button class="product-button-secondary" type="submit"><?php echo stridebr_e(stridebr_t('notifications.mark_all_read')); ?></button></form><?php endif; ?></header><?php if(!$items): ?><section class="empty-action-state"><h2><?php echo stridebr_e(stridebr_t('notifications.none')); ?></h2><a class="product-button-secondary" href="/home.php"><?php echo stridebr_e(stridebr_t('notifications.back_home')); ?></a></section><?php else: ?><section class="notification-list"><?php foreach($items as $item): $url=trim((string)($item['url']??'')); $copy=notificacaoApresentar($item); ?><article class="notification-card<?php echo empty($item['lida_em'])?' is-unread':''; ?>"><div><h3><?php echo stridebr_e((string)$copy['titulo']); ?></h3><?php if(trim((string)$copy['mensagem'])!==''): ?><p><?php echo stridebr_e((string)$copy['mensagem']); ?></p><?php endif; ?><time><?php echo stridebr_e(stridebr_format_datetime_short((string)$item['data_criacao'])); ?></time></div><div class="sync-card-actions"><?php if($url!==''): ?><a class="product-button-secondary" href="<?php echo stridebr_e($url); ?>"><?php echo stridebr_e(stridebr_t('notifications.open')); ?></a><?php endif; ?><?php if(empty($item['lida_em'])): ?><form method="post"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="read"><input type="hidden" name="idnotificacao" value="<?php echo stridebr_e((string)$item['idnotificacao']); ?>"><button class="product-button-secondary" type="submit"><?php echo stridebr_e(stridebr_t('notifications.mark_read')); ?></button></form><?php endif; ?></div></article><?php endforeach; ?></section><?php endif; ?></main></div><?php require dirname(__DIR__,2).'/src/layout/footer.php'; ?></body></html>
