<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/errors.php';
require_once dirname(__DIR__) . '/includes/app.php';
require_once __DIR__ . '/ads.php';

$pageTitle = isset($pageTitle) ? (string) $pageTitle : 'StrideBR';
$pageDescription = isset($pageDescription) ? (string) $pageDescription : '';
$pageHtml = isset($pageHtml) ? (string) $pageHtml : '';
$currentPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$metaDescription = $pageDescription !== '' ? $pageDescription : 'StrideBR é uma plataforma esportiva brasileira, livre e open source para planejar treinos, registrar atividades físicas e acompanhar evolução.';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php echo stridebr_seo_head(['title' => $currentPath === '/pages/help/faq.php' ? 'Perguntas frequentes — StrideBR' : $pageTitle, 'description' => $metaDescription, 'path' => $currentPath, 'locale' => 'pt-BR']); ?>
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">

    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require __DIR__ . '/header.php'; ?>
    <main class="main-content">
        <div class="page-shell static-page-shell">
            <div class="page-heading"><h1><?php echo stridebr_e($pageTitle); ?></h1><?php if ($pageDescription !== ''): ?><p><?php echo stridebr_e($pageDescription); ?></p><?php endif; ?></div>
            <section class="content-card static-content"><?php echo $pageHtml; ?></section>
            <?php stridebr_render_ad_slot('public-content-end', $currentPath, stridebr_is_logged_in()); ?>
        </div>
    </main>
</div>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
