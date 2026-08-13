<?php
$pageTitle = 'Privacidade';
$pageDescription = 'Resumo provisório do tratamento de dados na versão atual.';
$pageHtml = '<p>O sistema armazena dados necessários para conta, perfil, cronogramas, exercícios e atividades. Senhas são armazenadas como hash, não como texto simples.</p><h2>Visibilidade</h2><p>A estrutura já prevê níveis privado, amigos e público, mas recursos sociais ainda não estão implementados. O padrão das áreas pessoais é privado.</p><h2>Infraestrutura</h2><p>Credenciais do banco devem ser fornecidas por variáveis de ambiente e não fazem parte do código-fonte.</p>';
require dirname(__DIR__, 3) . '/src/layout/static_page.php';
