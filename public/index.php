<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/function/monetization.php';

if (stridebr_is_logged_in()) {
    header('Location: /home.php');
    exit;
}

$accountDeleted = (string) ($_GET['conta'] ?? '') === 'excluida';
$pageUrl = stridebr_public_url() . '/';
$pageDescription = 'Planeje treinos, registre atividades, acompanhe metas, rotas e evolução em diferentes esportes com o StrideBR.';
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="author" content="Bruno Evaristo Pinheiro">
    <?php echo stridebr_adsense_verification_meta(); ?>
    <meta name="description" content="<?php echo stridebr_e($pageDescription); ?>">
    <meta name="theme-color" content="#40507c">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="StrideBR">
    <meta property="og:title" content="StrideBR · Planeje, treine e registre">
    <meta property="og:description" content="<?php echo stridebr_e($pageDescription); ?>">
    <meta property="og:image" content="<?php echo stridebr_e(stridebr_social_image_url()); ?>">
    <meta property="og:url" content="<?php echo stridebr_e($pageUrl); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="<?php echo stridebr_e($pageUrl); ?>">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/index.css')); ?>">
    <title>StrideBR · Planeje, treine e registre</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="landing-shell">
            <?php if ($accountDeleted): ?><div class="alert alert-success">Sua conta e os dados vinculados foram excluídos.</div><?php endif; ?>
            <section class="landing-hero">
                <div class="landing-copy"><span class="landing-eyebrow">Treinos e atividades</span><h1>Planeje. Treine.<br>Registre sua evolução.</h1><p>Organize o treino de hoje, registre atividades e acompanhe sua evolução.</p><div class="landing-actions"><a class="landing-primary" href="/signup.php">Criar conta</a><a class="landing-secondary" href="/login.php">Já tenho uma conta</a></div></div>
                <div class="landing-principles"><p>Planejamento, registro e análise.</p><span>Cronogramas, atividades, progresso, rotas e privacidade no mesmo lugar.</span></div>
            </section>
            <section class="landing-features"><article><span>01</span><h2>Planeje sua rotina</h2><p>Cronogramas semanais, agenda mensal, recorrências e treinos com exercícios detalhados.</p></article><article><span>02</span><h2>Registre qualquer esporte</h2><p>Atividades com métricas próprias, equipamentos, rotas, distância e elevação.</p></article><article><span>03</span><h2>Compare com você mesmo</h2><p>Progresso, consistência, recordes e comparação de atividades.</p></article></section>
            <section class="landing-product-example">
                <div class="landing-example-copy"><span class="landing-eyebrow">Sua semana</span><h2>Entenda sua semana de relance.</h2><p>Veja o treino de hoje, o resumo da semana, metas e atividades recentes.</p></div>
                <div class="landing-preview" aria-label="Exemplo visual do painel semanal"><div class="landing-preview-head"><span><small>Visão geral</small><strong>Sua semana</strong></span><small>Exemplo</small></div><div class="landing-preview-stats"><span><small>Atividades</small><strong>4</strong></span><span><small>Tempo</small><strong>3h 20min</strong></span><span><small>Distância</small><strong>18,6 km</strong></span></div><div class="landing-preview-chart"><span><i style="height:35%"></i><small>S</small></span><span><i style="height:68%"></i><small>T</small></span><span><i style="height:45%"></i><small>Q</small></span><span><i style="height:88%"></i><small>Q</small></span><span><i style="height:58%"></i><small>S</small></span><span><i style="height:76%"></i><small>S</small></span><span><i style="height:42%"></i><small>D</small></span></div></div>
            </section>
        </div>
    </main>
</div>
<?php require dirname(__DIR__) . '/src/layout/footer.php'; ?>
</body>
</html>
