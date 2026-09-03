<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/function/monetization.php';

$adsEnabled = stridebr_ads_enabled();
$adsPreview = stridebr_ads_placeholders_enabled();
$pageAllowsAds = stridebr_ads_allowed_on_current_page();
if ((!$adsEnabled && !$adsPreview) || !$pageAllowsAds) {
    return;
}

$client = stridebr_adsense_client_id();
$slots = [
    'footer' => stridebr_adsense_slot_id('footer'),
    'rail-left' => stridebr_adsense_slot_id('rail-left'),
    'rail-right' => stridebr_adsense_slot_id('rail-right'),
];
$renderSlot = static function (string $placement, string $label) use ($slots, $adsEnabled, $adsPreview): void {
    $slot = $slots[$placement] ?? '';
    if (!$adsPreview && $slot === '') return;
    ?>
    <aside class="site-ad-slot site-ad-slot-<?php echo stridebr_e($placement); ?>" data-ad-placement="<?php echo stridebr_e($placement); ?>" data-ad-slot="<?php echo stridebr_e($slot); ?>" aria-label="Publicidade">
        <span class="site-ad-label">Publicidade</span>
        <div class="site-ad-placeholder" data-ad-placeholder>
            <strong><?php echo stridebr_e($label); ?></strong>
            <span><?php echo $adsEnabled && $slot === '' ? 'Configure o identificador deste bloco.' : 'Espaço reservado para anúncio discreto.'; ?></span>
        </div>
    </aside>
    <?php
};
?>
<div class="site-ad-rails" aria-hidden="true">
    <?php $renderSlot('rail-left', 'Lateral'); ?>
    <?php $renderSlot('rail-right', 'Lateral'); ?>
</div>
<div class="site-ad-footer-wrap">
    <?php $renderSlot('footer', 'Banner horizontal'); ?>
</div>
<?php if ($adsEnabled): ?>
<div class="ad-consent" data-ad-consent hidden>
    <div>
        <strong>Publicidade e cookies</strong>
        <p>O StrideBR usa anúncios para ajudar a pagar a infraestrutura. Você pode continuar só com cookies essenciais.</p>
    </div>
    <div class="ad-consent-actions">
        <button type="button" class="secondary-action" data-ad-consent-essential>Somente essenciais</button>
        <button type="button" class="primary-action" data-ad-consent-allow>Permitir publicidade</button>
    </div>
</div>
<?php endif; ?>
<script id="stridebr-ads-config" type="application/json"><?php echo json_encode([
    'enabled' => $adsEnabled,
    'preview' => $adsPreview,
    'client' => $client,
    'slots' => $slots,
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/ads.js')); ?>"></script>
