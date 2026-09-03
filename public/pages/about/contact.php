<?php
require_once dirname(__DIR__, 3) . '/src/includes/app.php';
$pageTitle = 'Contato';
$pageDescription = 'Canais para falar sobre o StrideBR.';
$email = stridebr_support_email();
$mailto = stridebr_support_mailto('Contato pelo StrideBR');
$pageHtml = '<p class="static-lead">O StrideBR ainda é um projeto independente, então o contato é direto com quem mantém o sistema.</p>
<div class="contact-options"><article><h2>Conta, privacidade ou suporte</h2><p>Para problemas de acesso, dados pessoais, exclusão de conta ou assuntos que não devem ficar públicos, use e-mail.</p><a class="primary-button" href="'.stridebr_e($mailto).'">'.stridebr_e($email).'</a></article><article><h2>Bug ou sugestão</h2><p>Se você estiver logado, o formulário de feedback é o jeito mais rápido de mandar contexto da página e descrever o problema.</p><a class="secondary-button" href="/feedback.php">Enviar feedback</a></article><article><h2>Código e questões técnicas</h2><p>Issues e discussões sobre o código podem ser abertas no repositório público. Não coloque senhas, tokens, e-mails privados ou outros dados pessoais em uma issue.</p><a class="secondary-button" href="https://github.com/BrunoWithoutH/StrideBR" target="_blank" rel="noopener noreferrer">GitHub ↗</a></article></div>';
require dirname(__DIR__, 3) . '/src/layout/static_page.php';
