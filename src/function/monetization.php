<?php

declare(strict_types=1);

function stridebr_env_flag(string $name, bool $default = false): bool
{
    $raw = getenv($name);
    if ($raw === false || trim((string) $raw) === '') return $default;
    return in_array(stridebr_lower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
}

function stridebr_ads_enabled(): bool
{
    return stridebr_env_flag('STRIDEBR_ADS_ENABLED', false);
}

function stridebr_ads_placeholders_enabled(): bool
{
    if (stridebr_env_flag('STRIDEBR_ADS_PLACEHOLDERS', false)) return true;
    return !stridebr_is_production() && stridebr_env_flag('STRIDEBR_ADS_DEV_PREVIEW', false);
}

function stridebr_adsense_client_id(): string
{
    $value = trim((string) (getenv('STRIDEBR_ADSENSE_CLIENT') ?: ''));
    return preg_match('/^ca-pub-\d+$/', $value) === 1 ? $value : '';
}

function stridebr_adsense_slot_id(string $placement): string
{
    $map = [
        'footer' => 'STRIDEBR_ADSENSE_SLOT_FOOTER',
        'rail-left' => 'STRIDEBR_ADSENSE_SLOT_RAIL_LEFT',
        'rail-right' => 'STRIDEBR_ADSENSE_SLOT_RAIL_RIGHT',
    ];
    $name = $map[$placement] ?? null;
    if ($name === null) return '';
    $value = trim((string) (getenv($name) ?: ''));
    return preg_match('/^\d+$/', $value) === 1 ? $value : '';
}

function stridebr_ads_allowed_on_current_page(?string $path = null): bool
{
    $path = $path ?? (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $blockedPrefixes = [
        '/login.php', '/signup.php', '/auth/', '/function/', '/api/', '/admin/', '/errors/',
        '/user/', '/home.php', '/calendario.php', '/evento.php',
        '/pages/legal/', '/pages/about/support-project.php',
    ];
    foreach ($blockedPrefixes as $prefix) {
        if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) return false;
    }
    return true;
}

function stridebr_donation_enabled(): bool
{
    if (!stridebr_env_flag('STRIDEBR_DONATION_ENABLED', false)) return false;
    return stridebr_donation_pix_key() !== '' || stridebr_donation_url() !== '';
}

function stridebr_donation_pix_key(): string
{
    return trim((string) (getenv('STRIDEBR_DONATION_PIX_KEY') ?: ''));
}

function stridebr_donation_pix_name(): string
{
    return trim((string) (getenv('STRIDEBR_DONATION_PIX_NAME') ?: ''));
}

function stridebr_donation_url(): string
{
    $value = trim((string) (getenv('STRIDEBR_DONATION_URL') ?: ''));
    if ($value === '') return '';
    if (filter_var($value, FILTER_VALIDATE_URL) === false) return '';
    $scheme = stridebr_lower((string) parse_url($value, PHP_URL_SCHEME));
    return in_array($scheme, ['https'], true) ? $value : '';
}
