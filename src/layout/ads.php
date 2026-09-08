<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/function/monetization.php';

function stridebr_ads_request_state(): array
{
    $state = $GLOBALS['stridebr_ads_request_state'] ?? null;
    if (!is_array($state)) {
        $state = ['placement' => null, 'real' => false, 'preview' => false];
        $GLOBALS['stridebr_ads_request_state'] = $state;
    }
    return $state;
}

function stridebr_ads_reset_request_state(): void
{
    $GLOBALS['stridebr_ads_request_state'] = ['placement' => null, 'real' => false, 'preview' => false];
}

function stridebr_ads_label(bool $preview = false): string
{
    $label = function_exists('stridebr_locale') && stridebr_locale() === 'en' ? 'Advertising' : 'Publicidade';
    return $preview ? $label . ' · Preview' : $label;
}

function stridebr_render_ad_slot(string $placement, ?string $path = null, ?bool $authenticated = null): bool
{
    $state = stridebr_ads_request_state();
    if (!empty($state['placement'])) return false;
    if (!stridebr_ads_can_render($placement, $path, $authenticated)) return false;

    $config = stridebr_ads_placement_config($placement);
    if ($config === null) return false;
    $preview = stridebr_ads_preview_enabled();
    $client = stridebr_adsense_client_id();
    $slot = stridebr_adsense_slot_id($placement);
    $label = stridebr_ads_label($preview);
    $class = trim('site-ad-placement ' . (string) ($config['class'] ?? ''));

    $GLOBALS['stridebr_ads_request_state'] = [
        'placement' => $placement,
        'real' => !$preview,
        'preview' => $preview,
    ];
    ?>
    <aside class="<?php echo stridebr_e($class); ?>" data-ad-placement="<?php echo stridebr_e($placement); ?>"<?php echo $preview ? ' data-ad-preview="1"' : ''; ?> aria-label="<?php echo stridebr_e($label); ?>">
        <span class="site-ad-label"><?php echo stridebr_e($label); ?></span>
        <?php if ($preview): ?>
            <div class="site-ad-preview" aria-hidden="true"><span><?php echo stridebr_e($placement); ?></span></div>
        <?php else: ?>
            <ins class="adsbygoogle site-ad-provider"
                 data-ad-client="<?php echo stridebr_e($client); ?>"
                 data-ad-slot="<?php echo stridebr_e($slot); ?>"
                 data-ad-format="auto"
                 data-full-width-responsive="true"></ins>
        <?php endif; ?>
    </aside>
    <?php
    return true;
}

function stridebr_render_ads_runtime(): void
{
    $state = stridebr_ads_request_state();
    if (empty($state['placement']) || empty($state['real'])) return;

    $client = stridebr_adsense_client_id();
    if ($client === '') return;
    ?>
    <script id="stridebr-ads-config" type="application/json"><?php echo json_encode([
        'enabled' => true,
        'client' => $client,
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <script src="<?php echo stridebr_e(stridebr_asset('/assets/js/ads.js')); ?>"></script>
    <?php
}
