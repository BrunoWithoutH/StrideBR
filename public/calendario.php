<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/assets/img/favicon/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/css/style.css">
    <title>Eventos esportivos | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell">
            <div class="page-heading"><h1>Eventos esportivos</h1><p>Calendário regional de corridas, rústicas e outros eventos.</p></div>
            <section class="content-card">
                <h2>Em desenvolvimento</h2>
                <p>Esta área será conectada a fontes externas de eventos e permitirá salvar eventos de interesse sem misturar esses dados com os cronogramas de treino.</p>
            </section>
        </div>
    </main>
</div>
<?php require dirname(__DIR__) . '/src/layout/footer.php'; ?>
</body>
</html>
