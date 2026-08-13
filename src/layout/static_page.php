<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/errors.php';
require_once dirname(__DIR__) . '/includes/app.php';

$pageTitle = isset($pageTitle) ? (string) $pageTitle : 'StrideBR';
$pageDescription = isset($pageDescription) ? (string) $pageDescription : '';
$pageHtml = isset($pageHtml) ? (string) $pageHtml : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/assets/img/favicon/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/css/style.css">
    <title><?php echo stridebr_e($pageTitle); ?> | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require __DIR__ . '/header.php'; ?>
    <main class="main-content">
        <div class="page-shell">
            <div class="page-heading"><h1><?php echo stridebr_e($pageTitle); ?></h1><?php if ($pageDescription !== ''): ?><p><?php echo stridebr_e($pageDescription); ?></p><?php endif; ?></div>
            <section class="content-card static-content"><?php echo $pageHtml; ?></section>
        </div>
    </main>
</div>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
