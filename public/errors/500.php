<?php
http_response_code(500);
?>
<!doctype html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
<?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#171a1e">
<title>500 · Erro interno | StrideBR</title>
<link rel="icon" type="image/png" href="/assets/img/favicon/favicon.png">
<link rel="stylesheet" href="/assets/css/errors.css">
</head>
<body>
<header class="error-topbar"><div class="error-topbar-inner"><a href="/" aria-label="StrideBR"><img class="error-logo" src="/assets/img/logos/stridebr-logo-white.svg" alt="StrideBR"></a><span>Registro, planejamento e progresso esportivo</span></div></header>
<main class="error-main"><section class="error-shell"><div class="error-content"><span class="error-code">Erro 500</span><h1>O StrideBR não conseguiu concluir isso.</h1><p>Foi um erro interno e pode ser temporário. Tente novamente; se continuar acontecendo, use a referência abaixo ao relatar o problema.</p><?php if (!empty($GLOBALS['stridebr_request_id'])): ?><p class="error-reference">Referência <code><?php echo htmlspecialchars((string) $GLOBALS['stridebr_request_id'], ENT_QUOTES, 'UTF-8'); ?></code></p><?php endif; ?><div class="error-actions"><button class="error-primary" type="button" data-error-retry>Tentar novamente</button><button class="error-secondary" type="button" data-error-back>Voltar à página anterior</button><a class="error-secondary" href="/">Ir para o início</a></div></div><div class="error-foot"><span>Se o erro persistir, a referência ajuda a localizar o problema nos logs.</span><a href="/feedback.php">Relatar problema</a></div><img class="error-mark" src="/assets/img/logos/stridebr-icon-white.png" alt="" aria-hidden="true"></section></main>
<script src="/assets/js/errors.js"></script>
</body>
</html>
