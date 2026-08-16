<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';

if (stridebr_is_logged_in()) {
    header('Location: /home.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="author" content="Bruno Evaristo Pinheiro">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/index.css')); ?>">
    <title>StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <section class="intro landing-intro">
            <img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-icon.svg')); ?>" alt="" class="landing-icon" width="74" height="74" decoding="async">
            <h1>StrideBR</h1>
            <p>Uma plataforma flexível para planejar treinos e registrar atividades físicas do seu jeito.</p>
            <div class="landing-actions">
                <a class="landing-primary" href="/signup.php">Criar conta</a>
                <a class="landing-secondary" href="/login.php">Entrar</a>
            </div>
        </section>
        <section class="landing-features">
            <article><h2>Planejamento</h2><p>Monte cronogramas semanais independentes, com horários e exercícios.</p></article>
            <article><h2>Registro flexível</h2><p>Modalidades e modelos definem os dados que fazem sentido para cada atividade.</p></article>
            <article><h2>Biblioteca</h2><p>Use exercícios do StrideBR ou mantenha sua própria biblioteca reutilizável.</p></article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/src/layout/footer.php'; ?>
</body>
</html>
