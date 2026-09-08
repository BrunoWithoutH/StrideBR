<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/function/monetization.php';

$state = static fn(bool $value): string => $value ? 'ON' : 'OFF';
$configured = static fn(bool $value): string => $value ? 'configured' : 'not configured';

printf("Environment: %s\n", trim((string) (getenv('STRIDEBR_APP_ENV') ?: 'development')));
printf("Ads master: %s\n", $state(stridebr_ads_enabled()));
printf("Authenticated ads: %s\n", $state(stridebr_ads_authenticated_enabled()));
printf("Dev preview: %s\n", $state(stridebr_ads_dev_preview_enabled()));
printf("Placeholders: %s\n", $state(stridebr_ads_placeholders_enabled()));
printf("AdSense client: %s\n", $configured(stridebr_adsense_client_id() !== ''));
printf("Site verification meta: %s\n", stridebr_adsense_verification_meta() !== '' ? 'ready' : 'not ready');

echo "Placements:\n";
foreach (array_keys(stridebr_ads_placements()) as $placement) {
    printf("  %s: %s\n", $placement, $configured(stridebr_adsense_slot_id($placement) !== ''));
}

printf("ads.txt: %s\n", is_file(dirname(__DIR__) . '/public/ads.txt') ? 'present' : 'missing');
