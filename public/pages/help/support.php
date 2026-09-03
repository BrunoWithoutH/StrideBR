<?php
require_once dirname(__DIR__, 3) . '/src/includes/app.php';
$pageTitle = 'Suporte';
$pageDescription = 'Onde pedir ajuda dependendo do tipo de problema.';
$email = stridebr_support_email();
$pageHtml = '<p class="static-lead">Não existe uma equipe de atendimento separada: o suporte é feito diretamente pelo responsável pelo StrideBR. Escolha o canal que evita expor informação desnecessária.</p>
<div class="support-grid"><article><span class="eyebrow">Dentro do produto</span><h2>Bug ou ideia</h2><p>Use o formulário de feedback. Ele é o melhor lugar para dizer o que aconteceu, em qual página e o que você esperava.</p><a class="primary-button" href="/feedback.php">Enviar feedback</a></article><article><span class="eyebrow">Privado</span><h2>Conta, dados ou privacidade</h2><p>Envie um e-mail para <strong>'.stridebr_e($email).'</strong>. Não envie sua senha, códigos de autenticação ou tokens.</p><a class="secondary-button" href="'.stridebr_e(stridebr_support_mailto('Suporte StrideBR')).'">Abrir e-mail</a></article><article><span class="eyebrow">Open source</span><h2>Problema técnico no código</h2><p>Se o assunto puder ser público e reproduzido sem dados pessoais, abra uma issue no GitHub.</p><a class="secondary-button" href="https://github.com/BrunoWithoutH/StrideBR/issues" target="_blank" rel="noopener noreferrer">Issues no GitHub ↗</a></article></div>
<h2>Ao pedir ajuda</h2><p>Se puder, informe a página, o que você estava tentando fazer, o que aconteceu e qual navegador/dispositivo estava usando. Prints ajudam, desde que não mostrem senha, token ou informação que você não queira compartilhar.</p>';
require dirname(__DIR__, 3) . '/src/layout/static_page.php';
