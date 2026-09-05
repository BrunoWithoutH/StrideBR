<?php

declare(strict_types=1);
$pageTitle = 'Cookies e armazenamento local';
$pageDescription = 'Como o StrideBR usa cookies essenciais e dados salvos no navegador.';
$pageHtml = '<p class="legal-lead"><strong>Última atualização: 1º de setembro de 2026.</strong></p>
<h2>Cookies essenciais</h2><p>O StrideBR usa o cookie de sessão do PHP para manter autenticação, proteção CSRF, mensagens temporárias e continuidade da sessão. Esse cookie é necessário para as áreas autenticadas. Em produção ele é configurado com proteções como HttpOnly, SameSite e Secure quando aplicável.</p>
<h2>Armazenamento local do navegador</h2><p>Algumas preferências e ferramentas podem usar <code>localStorage</code>, <code>sessionStorage</code> ou cookies funcionais. Exemplos incluem estado de timers, rascunhos, ferramentas rápidas, visualização do cronograma e preferências ligadas apenas ao dispositivo. Esses dados podem ser apagados nas configurações do navegador.</p>
<h2>Preferências salvas na conta</h2><p>Outras configurações, como organização da Home, privacidade padrão de atividades e opção de analytics de produto, são salvas no servidor para acompanhar a conta entre dispositivos. Elas não dependem de cookies publicitários.</p>
<h2>Analytics de produto</h2><p>O analytics de produto atual é registrado pelo próprio StrideBR a partir de eventos permitidos no servidor e pode ser desativado nas preferências. Ele não depende de cookies de rastreamento de terceiros e não deve coletar rota GPS, notas, cargas ou conteúdo pessoal livre.</p>
<h2>Mapas e serviços externos</h2><p>Ao usar mapas, o navegador baixa recursos visuais do provedor configurado para os tiles. A consulta de elevação é feita pelo servidor do StrideBR e não exige cookie publicitário.</p>
<h2>Publicidade</h2><p>O StrideBR possui suporte opcional a publicidade em páginas públicas sem dados do atleta. A rede de anúncios permanece desligada enquanto não estiver configurada no servidor. Quando ativada, o navegador só carrega o fornecedor após a escolha <strong>Permitir publicidade</strong>; a escolha fica armazenada localmente. Páginas autenticadas, atividades, progresso, treinos e outras áreas com dados esportivos não carregam o componente de anúncios.</p><p>Você pode manter apenas os recursos essenciais. Dados esportivos ou recebidos de conexões externas não são usados para segmentar publicidade.</p>
<h2>Como limpar</h2><p>Você pode apagar cookies e armazenamento local nas configurações do navegador. Isso pode encerrar sua sessão, remover rascunhos locais e redefinir preferências salvas apenas naquele dispositivo.</p>';
require dirname(__DIR__, 3) . '/src/layout/static_page.php';
