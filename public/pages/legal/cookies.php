<?php
$pageTitle = 'Cookies';
$pageDescription = 'Uso atual de cookies no StrideBR.';
$pageHtml = '<p>A versão atual usa o cookie de sessão do PHP para manter autenticação e dados temporários de sessão, incluindo proteção CSRF e mensagens de retorno. Não há integração de publicidade ou rastreamento de terceiros implementada pelo projeto.</p>';
require dirname(__DIR__, 3) . '/src/layout/static_page.php';
