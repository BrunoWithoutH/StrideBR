<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';

stridebr_require_login();
$user = (string) ($_SESSION['NomeUsuario'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title>Painel | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell">
            <div class="page-heading">
                <h1>Bem-vindo, <?php echo stridebr_e($user); ?></h1>
                <p>Escolha onde quer continuar.</p>
            </div>
            <div class="dashboard-grid">
                <a class="dashboard-card" href="/user/cronogramatreinos.php"><h2>Cronogramas</h2><p>Organize sua semana, horários e exercícios.</p></a>
                <a class="dashboard-card" href="/user/atividades.php"><h2>Atividades</h2><p>Registre atividades realizadas e suas unidades.</p></a>
                <a class="dashboard-card" href="/user/bibliotecaexercicios.php"><h2>Biblioteca de exercícios</h2><p>Reutilize exercícios do StrideBR e os seus.</p></a>
                <a class="dashboard-card" href="/user/ferramentastreino.php"><h2>Ferramentas</h2><p>Contador e temporizador para usar durante o treino.</p></a>
                <a class="dashboard-card" href="/calendario.php"><h2>Eventos</h2><p>Área preparada para calendário de corridas e eventos esportivos.</p></a>
                <a class="dashboard-card" href="/user/settings.php"><h2>Configurações</h2><p>Atualize seus dados e a privacidade do perfil.</p></a>
            </div>
        </div>
    </main>
</div>
<?php require dirname(__DIR__) . '/src/layout/footer.php'; ?>
</body>
</html>
