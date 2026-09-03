<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/src/includes/app.php';
require_once dirname(__DIR__, 3) . '/src/function/monetization.php';

$pageTitle = 'Apoie o StrideBR';
$pageDescription = 'Apoio voluntário para ajudar a manter a infraestrutura e o desenvolvimento do StrideBR.';
$donationsEnabled = stridebr_donation_enabled();
$pixKey = $donationsEnabled ? stridebr_donation_pix_key() : '';
$pixName = $donationsEnabled ? stridebr_donation_pix_name() : '';
$donationUrl = $donationsEnabled ? stridebr_donation_url() : '';

$pixHtml = $pixKey !== ''
    ? '<p class="support-project-pix"><code data-donation-pix>'.stridebr_e($pixKey).'</code><button type="button" class="secondary-button" data-copy-donation-pix>Copiar chave</button></p>'.($pixName !== '' ? '<p>Favorecido: <strong>'.stridebr_e($pixName).'</strong></p>' : '')
    : '<p>A chave PIX ainda não foi publicada. Quando as doações estiverem ativas, ela aparecerá aqui.</p>';

$linkHtml = $donationUrl !== ''
    ? '<a class="primary-button" href="'.stridebr_e($donationUrl).'" target="_blank" rel="noopener noreferrer">Apoiar por outro meio ↗</a>'
    : '<p>Também será possível configurar um link externo de apoio sem alterar esta página.</p>';

$pageHtml = '<div class="support-project-hero"><div><p class="static-lead">O StrideBR é desenvolvido de forma independente e foi pensado para continuar acessível. Se ele for útil para você e você quiser ajudar a manter hospedagem, domínio, serviços e desenvolvimento, o apoio é bem-vindo.</p><p>Doar é totalmente opcional. O StrideBR não usa pop-ups de cobrança, não pede apoio depois de um treino ou recorde e não transforma contribuição financeira em vantagem sobre outras contas.</p></div><div class="support-project-card"><span class="eyebrow">Apoio voluntário</span><h2>Use primeiro. Apoie se fizer sentido.</h2><p>O produto continua funcionando independentemente de doação. A contribuição serve para ajudar a manter o projeto no ar e financiar melhorias.</p></div></div>
<div class="support-project-methods"><article class="support-project-method"><span class="eyebrow">PIX</span><h2>Apoio direto</h2>'.$pixHtml.'</article><article class="support-project-method"><span class="eyebrow">Outro meio</span><h2>Cartão ou plataforma externa</h2>'.$linkHtml.'</article></div>
<h2>Outras formas de ajudar</h2><p>Usar o StrideBR, reportar bugs, sugerir melhorias e contribuir com o projeto no GitHub também ajudam bastante. Doações são sempre voluntárias e não dão acesso especial a dados, moderação ou decisões sobre contas.</p>
<script src="'.stridebr_e(stridebr_asset('/assets/js/support-project.js')).'"></script>';

require dirname(__DIR__, 3) . '/src/layout/static_page.php';
