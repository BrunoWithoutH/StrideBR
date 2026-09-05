<?php
http_response_code(403);
?>
<!doctype html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
<?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#171a1e">
<title>403 · Acesso restrito | StrideBR</title>
<link rel="icon" type="image/png" href="/assets/img/favicon/favicon.png">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/ui-refresh.css">
<link rel="stylesheet" href="/assets/css/errors.css">
</head>
<body>
<header class="error-topbar"><div class="error-topbar-inner"><a href="/" aria-label="StrideBR"><img class="error-logo" src="/assets/img/logos/stridebr-logo-white.svg" alt="StrideBR"></a><span>Registro, planejamento e progresso esportivo</span></div></header>
<main class="error-main"><section class="error-shell"><div class="error-content"><span class="error-code">Erro 403</span><h1>Essa área é restrita.</h1><p>A conta atual não tem permissão para abrir este conteúdo. Se você chegou aqui por um link antigo, volte e escolha outra área.</p><div class="error-actions"><a class="error-primary" href="/">Ir para o StrideBR</a><button class="error-secondary" type="button" data-error-back>Voltar à página anterior</button></div></div><div class="error-foot"><span>Nenhuma alteração foi feita na sua conta.</span><a href="/pages/help/support.php">Precisa de ajuda?</a></div><img class="error-mark" src="/assets/img/logos/stridebr-icon-white.png" alt="" aria-hidden="true"></section></main>
<script src="/assets/js/errors.js"></script>
</body>
</html>
