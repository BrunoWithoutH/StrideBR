<?php
http_response_code(404);
?>
<!doctype html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
<?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#171a1e">
<title>404 · Página não encontrada | StrideBR</title>
<link rel="icon" type="image/png" href="/assets/img/favicon/favicon.png">
<link rel="stylesheet" href="/assets/css/errors.css">
</head>
<body>
<header class="error-topbar"><div class="error-topbar-inner"><a href="/" aria-label="StrideBR"><img class="error-logo" src="/assets/img/logos/stridebr-logo-white.svg" alt="StrideBR"></a><span>Registro, planejamento e progresso esportivo</span></div></header>
<main class="error-main"><section class="error-shell"><div class="error-content"><span class="error-code">Erro 404</span><h1>Essa página não está aqui.</h1><p>O endereço pode ter mudado, sido removido ou estar incorreto. Seus registros e sua conta não foram afetados.</p><div class="error-actions"><a class="error-primary" href="/">Voltar para o StrideBR</a><button class="error-secondary" type="button" data-error-back>Voltar à página anterior</button></div></div><div class="error-foot"><span>Você pode continuar usando o restante do StrideBR normalmente.</span><a href="/pages/help/support.php">Precisa de ajuda?</a></div><img class="error-mark" src="/assets/img/logos/stridebr-icon-white.png" alt="" aria-hidden="true"></section></main>
<script src="/assets/js/errors.js"></script>
</body>
</html>
