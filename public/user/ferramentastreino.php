<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

stridebr_require_login();
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t('tools.quick_title')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content quick-tools-standalone-page" data-quick-tools-standalone>
        <div class="page-shell">
            <div class="page-heading"><h1><?php echo stridebr_e(stridebr_t('tools.quick_title')); ?></h1></div>
            <div class="quick-tools-standalone-grid">
                <div class="quick-tools-standalone-card" data-quick-tools-slot="timer"></div>
                <div class="quick-tools-standalone-card" data-quick-tools-slot="stopwatch"></div>
                <div class="quick-tools-standalone-card" data-quick-tools-slot="sets"></div>
            </div>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</body>
</html>
