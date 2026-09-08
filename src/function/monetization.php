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

function stridebr_ads_authenticated_enabled(): bool
{
    return stridebr_env_flag('STRIDEBR_ADS_AUTHENTICATED_ENABLED', false);
}

function stridebr_ads_dev_preview_enabled(): bool
{
    return !stridebr_is_production() && stridebr_env_flag('STRIDEBR_ADS_DEV_PREVIEW', false);
}

function stridebr_ads_placeholders_enabled(): bool
{
    return !stridebr_is_production() && stridebr_env_flag('STRIDEBR_ADS_PLACEHOLDERS', false);
}

function stridebr_ads_preview_enabled(): bool
{
    return stridebr_ads_dev_preview_enabled() || stridebr_ads_placeholders_enabled();
}

function stridebr_adsense_client_id(): string
{
    $value = trim((string) (getenv('STRIDEBR_ADSENSE_CLIENT') ?: ''));
    return preg_match('/^ca-pub-\d+$/', $value) === 1 ? $value : '';
}

function stridebr_ads_placements(): array
{
    return [
        'home-after-week' => [
            'slot_env' => 'STRIDEBR_ADSENSE_SLOT_HOME_AFTER_WEEK',
            'paths' => ['/home.php'],
            'requires_auth' => true,
            'requires_authenticated_ads' => true,
            'class' => 'site-ad-placement--content-break',
            'preview' => true,
        ],
        'library-end' => [
            'slot_env' => 'STRIDEBR_ADSENSE_SLOT_LIBRARY_END',
            'paths' => ['/user/biblioteca.php'],
            'requires_auth' => true,
            'requires_authenticated_ads' => true,
            'class' => 'site-ad-placement--content-end',
            'preview' => true,
        ],
        'profile-end' => [
            'slot_env' => 'STRIDEBR_ADSENSE_SLOT_PROFILE_END',
            'paths' => ['/user/perfil.php'],
            'requires_auth' => true,
            'requires_authenticated_ads' => true,
            'class' => 'site-ad-placement--content-end',
            'preview' => true,
        ],
        'equipment-end' => [
            'slot_env' => 'STRIDEBR_ADSENSE_SLOT_EQUIPMENT_END',
            'paths' => ['/user/equipamentos.php'],
            'requires_auth' => true,
            'requires_authenticated_ads' => true,
            'class' => 'site-ad-placement--content-end',
            'preview' => true,
        ],
        'events-list-end' => [
            'slot_env' => 'STRIDEBR_ADSENSE_SLOT_EVENTS_LIST_END',
            'paths' => ['/calendario.php'],
            'requires_auth' => false,
            'requires_authenticated_ads' => false,
            'class' => 'site-ad-placement--content-end',
            'preview' => true,
        ],
        'event-detail-end' => [
            'slot_env' => 'STRIDEBR_ADSENSE_SLOT_EVENT_DETAIL_END',
            'paths' => ['/evento.php'],
            'requires_auth' => false,
            'requires_authenticated_ads' => false,
            'class' => 'site-ad-placement--content-end',
            'preview' => true,
        ],
        'public-content-end' => [
            'slot_env' => 'STRIDEBR_ADSENSE_SLOT_PUBLIC_CONTENT_END',
            'paths' => [
                '/pages/help/faq.php',
                '/pages/extras/roadmap.php',
                '/pages/extras/changelog.php',
                '/pages/extras/credits.php',
                '/pages/about/about.php',
            ],
            'requires_auth' => false,
            'requires_authenticated_ads' => false,
            'class' => 'site-ad-placement--content-end',
            'preview' => true,
        ],
    ];
}

function stridebr_ads_placement_config(string $placement): ?array
{
    $registry = stridebr_ads_placements();
    if (!isset($registry[$placement])) return null;
    return ['name' => $placement] + $registry[$placement];
}

function stridebr_adsense_slot_id(string $placement): string
{
    $config = stridebr_ads_placement_config($placement);
    if ($config === null) return '';
    $value = trim((string) (getenv((string) $config['slot_env']) ?: ''));
    return preg_match('/^\d+$/', $value) === 1 ? $value : '';
}

function stridebr_ads_current_path(?string $path = null): string
{
    if ($path !== null && $path !== '') return $path;
    return (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
}

function stridebr_ads_prohibited_paths(): array
{
    return [
        'exact' => [
            '/', '/login.php', '/signup.php', '/forgot-password.php', '/reset-password.php', '/resend-verification.php', '/verify-email.php', '/accept-legal.php', '/feedback.php',
            '/user/atividades.php', '/user/editatividade.php', '/user/gravar-atividade.php', '/user/importar-exportar.php', '/user/comparar-atividades.php',
            '/user/cronogramatreinos.php', '/user/agenda-mensal.php', '/user/cronograma-sincronizado.php', '/user/treinador.php',
            '/user/settings.php', '/user/account.php', '/user/delete-account.php', '/user/export-data.php', '/user/progresso.php', '/user/metas.php', '/user/notificacoes.php', '/user/amigos.php',
        ],
        'prefixes' => ['/admin/', '/auth/', '/api/', '/function/', '/errors/'],
    ];
}

function stridebr_ads_path_is_prohibited(?string $path = null): bool
{
    $path = stridebr_ads_current_path($path);
    $deny = stridebr_ads_prohibited_paths();
    if (in_array($path, $deny['exact'], true)) return true;
    foreach ($deny['prefixes'] as $prefix) {
        if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) return true;
    }
    return false;
}

function stridebr_ads_allowed_on_current_page(?string $path = null): bool
{
    $path = stridebr_ads_current_path($path);
    if (stridebr_ads_path_is_prohibited($path)) return false;
    foreach (stridebr_ads_placements() as $config) {
        if (in_array($path, $config['paths'], true)) return true;
    }
    return false;
}

function stridebr_ads_can_render(string $placement, ?string $path = null, ?bool $authenticated = null): bool
{
    $config = stridebr_ads_placement_config($placement);
    if ($config === null) return false;

    $path = stridebr_ads_current_path($path);
    if (!in_array($path, $config['paths'], true) || stridebr_ads_path_is_prohibited($path)) return false;

    if (stridebr_ads_preview_enabled() && !empty($config['preview'])) {
        return true;
    }

    if (!stridebr_ads_enabled()) return false;
    if (stridebr_adsense_client_id() === '' || stridebr_adsense_slot_id($placement) === '') return false;

    if ($authenticated === null) {
        $authenticated = function_exists('stridebr_is_logged_in') ? stridebr_is_logged_in() : false;
    }
    if (!empty($config['requires_auth']) && !$authenticated) return false;
    if (!empty($config['requires_authenticated_ads']) && !stridebr_ads_authenticated_enabled()) return false;

    return true;
}

function stridebr_adsense_verification_meta(): string
{
    // Site ownership only; independent of provider configuration and ad activation.
    return '<meta name="google-adsense-account" content="ca-pub-3948145279411749">';
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
