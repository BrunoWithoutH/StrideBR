<?php

declare(strict_types=1);
$pageTitle='Cookies e armazenamento local';
$pageDescription='O que o StrideBR usa no navegador durante a alpha.';
$pageHtml='<p class="legal-lead"><strong>Última atualização: 15 de agosto de 2026.</strong></p><h2>Cookies essenciais</h2><p>O StrideBR usa o cookie de sessão do PHP para autenticação, proteção CSRF, mensagens temporárias e continuidade da sessão. Esse cookie é necessário para áreas autenticadas.</p><h2>Armazenamento local</h2><p>Algumas preferências de interface e ferramentas rápidas podem usar armazenamento local do navegador, por exemplo zoom do cronograma, timers e ferramentas fixadas. Esses dados ficam no dispositivo até serem limpos pelo usuário ou pelo próprio aplicativo.</p><h2>Terceiros e publicidade</h2><p>A versão atual não implementa cookies de publicidade nem rastreamento publicitário de terceiros. Caso analytics não essenciais sejam adicionados no futuro, esta página e os controles de consentimento deverão ser atualizados antes da ativação.</p>';
require dirname(__DIR__,3).'/src/layout/static_page.php';
