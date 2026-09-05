<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/errors.php';
require_once dirname(__DIR__) . '/includes/app.php';

$pageTitle = isset($pageTitle) ? (string) $pageTitle : 'StrideBR';
$pageDescription = isset($pageDescription) ? (string) $pageDescription : '';
$pageHtml = isset($pageHtml) ? (string) $pageHtml : '';
$currentPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$canonical = stridebr_public_url() . $currentPath;
$metaDescription = $pageDescription !== '' ? $pageDescription : 'StrideBR: planeje treinos, registre atividades e acompanhe sua evolução.';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="<?php echo stridebr_e($metaDescription); ?>">
    <meta name="theme-color" content="#40507c">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="StrideBR">
    <meta property="og:title" content="<?php echo stridebr_e($pageTitle); ?> | StrideBR">
    <meta property="og:description" content="<?php echo stridebr_e($metaDescription); ?>">
    <meta property="og:image" content="<?php echo stridebr_e(stridebr_social_image_url()); ?>">
    <meta property="og:url" content="<?php echo stridebr_e($canonical); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="<?php echo stridebr_e($canonical); ?>">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">

    <title><?php echo stridebr_e($pageTitle); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require __DIR__ . '/header.php'; ?>
    <main class="main-content">
        <div class="page-shell static-page-shell">
            <div class="page-heading"><h1><?php echo stridebr_e($pageTitle); ?></h1><?php if ($pageDescription !== ''): ?><p><?php echo stridebr_e($pageDescription); ?></p><?php endif; ?></div>
            <section class="content-card static-content"><?php echo $pageHtml; ?></section>
        </div>
    </main>
</div>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
