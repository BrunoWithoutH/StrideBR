<?php

declare(strict_types=1);

function stridebr_supported_locales(): array
{
    return ['pt-BR', 'en'];
}

function stridebr_supported_locale_modes(): array
{
    return ['auto', 'pt-BR', 'en'];
}

function stridebr_normalize_locale(?string $locale): string
{
    $locale = trim((string) $locale);
    $normalized = str_replace('_', '-', $locale);
    if (preg_match('/^pt(?:-|$)/i', $normalized) === 1) return 'pt-BR';
    if (preg_match('/^en(?:-|$)/i', $normalized) === 1) return 'en';
    return 'pt-BR';
}

function stridebr_normalize_locale_mode(?string $locale): string
{
    $locale = trim((string) $locale);
    if ($locale === '' || strtolower($locale) === 'auto' || strtolower($locale) === 'system') return 'auto';
    return stridebr_normalize_locale($locale);
}

function stridebr_detect_locale(): string
{
    $accept = trim((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    if ($accept === '') return 'en';
    $candidate = trim(explode(';', explode(',', $accept, 2)[0] ?? '', 2)[0] ?? '');
    if ($candidate !== '' && preg_match('/^pt(?:-|$)/i', str_replace('_', '-', $candidate)) === 1) return 'pt-BR';
    return 'en';
}

function stridebr_set_locale_preference(string $locale, bool $persistCookie = true): string
{
    $mode = stridebr_normalize_locale_mode($locale);
    $_SESSION['StrideBRLocaleMode'] = $mode;
    unset($_SESSION['StrideBRLocale']);
    if ($persistCookie && !headers_sent()) {
        $cookieOptions = [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => stridebr_is_production() || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => false,
            'samesite' => 'Lax',
        ];
        setcookie('stridebr_locale_mode', $mode, $cookieOptions);
        if ($mode === 'auto') {
            setcookie('stridebr_locale', '', array_replace($cookieOptions, ['expires' => time() - 3600]));
        } else {
            setcookie('stridebr_locale', $mode, $cookieOptions);
        }
    }
    return $mode;
}

function stridebr_set_locale(string $locale, bool $persistCookie = true): string
{
    $mode = stridebr_set_locale_preference($locale, $persistCookie);
    return $mode === 'auto' ? stridebr_detect_locale() : stridebr_normalize_locale($mode);
}

function stridebr_locale_preference(): string
{
    if (isset($_GET['lang']) && is_string($_GET['lang'])) {
        return stridebr_set_locale_preference($_GET['lang']);
    }
    if (isset($_COOKIE['stridebr_locale_mode']) && is_string($_COOKIE['stridebr_locale_mode'])) {
        return stridebr_normalize_locale_mode($_COOKIE['stridebr_locale_mode']);
    }
    if (isset($_SESSION['StrideBRLocaleMode']) && is_string($_SESSION['StrideBRLocaleMode'])) {
        return stridebr_normalize_locale_mode($_SESSION['StrideBRLocaleMode']);
    }
    if (isset($_COOKIE['stridebr_locale']) && is_string($_COOKIE['stridebr_locale'])) {
        return stridebr_normalize_locale_mode($_COOKIE['stridebr_locale']);
    }
    if (isset($_SESSION['StrideBRLocale']) && is_string($_SESSION['StrideBRLocale'])) {
        return stridebr_normalize_locale_mode($_SESSION['StrideBRLocale']);
    }
    return 'auto';
}

function stridebr_locale(): string
{
    static $resolved = null;
    if ($resolved !== null) return $resolved;
    $mode = stridebr_locale_preference();
    return $resolved = $mode === 'auto' ? stridebr_detect_locale() : stridebr_normalize_locale($mode);
}

function stridebr_locale_dictionary(?string $locale = null): array
{
    static $cache = [];
    $locale = stridebr_normalize_locale($locale ?? stridebr_locale());
    if (isset($cache[$locale])) return $cache[$locale];
    $path = dirname(__DIR__) . '/i18n/' . $locale . '.php';
    $dictionary = is_file($path) ? require $path : [];
    return $cache[$locale] = is_array($dictionary) ? $dictionary : [];
}

function stridebr_t(string $key, array $replace = [], ?string $fallback = null): string
{
    $dictionary = stridebr_locale_dictionary();
    $pt = stridebr_locale_dictionary('pt-BR');
    $text = (string) ($dictionary[$key] ?? $pt[$key] ?? $fallback ?? $key);
    foreach ($replace as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }
    return $text;
}

function stridebr_theme_normalize(?string $theme): string
{
    $theme = strtolower(trim((string) $theme));
    return in_array($theme, ['light', 'dark'], true) ? $theme : 'light';
}

function stridebr_set_theme(string $theme, bool $persistCookie = true): string
{
    $theme = stridebr_theme_normalize($theme);
    $_SESSION['StrideBRTheme'] = $theme;
    if ($persistCookie && !headers_sent()) {
        setcookie('stridebr_theme', $theme, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => stridebr_is_production() || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
    return $theme;
}

function stridebr_theme(): string
{
    if (isset($_COOKIE['stridebr_theme']) && is_string($_COOKIE['stridebr_theme'])) {
        return stridebr_theme_normalize($_COOKIE['stridebr_theme']);
    }
    if (isset($_SESSION['StrideBRTheme']) && is_string($_SESSION['StrideBRTheme'])) {
        return stridebr_theme_normalize($_SESSION['StrideBRTheme']);
    }
    return 'light';
}

function stridebr_html_lang(): string
{
    return stridebr_locale() === 'en' ? 'en' : 'pt-BR';
}

function stridebr_ui_boot_script(): string
{
    $serverLocaleMode = json_encode(stridebr_locale_preference(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $serverLocale = json_encode(stridebr_locale(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $serverThemeMode = json_encode(stridebr_theme(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return '<script data-stridebr-ui-boot>(function(){var d=document.documentElement,sm=' . $serverThemeMode . ',sl=' . $serverLocaleMode . ',sr=' . $serverLocale . ';function ls(k){try{return localStorage.getItem(k)||""}catch(e){return""}}var tm=sm||ls("stridebr_theme_mode")||ls("stridebr_theme")||"light";if(["light","dark"].indexOf(tm)<0)tm="light";var lm=sl||ls("stridebr_locale_mode")||"auto";if(["auto","pt-BR","en"].indexOf(lm)<0)lm="auto";var locale=lm==="auto"?(sr==="pt-BR"?"pt-BR":"en"):lm;d.lang=locale==="en"?"en":"pt-BR";d.dataset.locale=locale;d.dataset.localeMode=lm;d.dataset.themeMode=tm;d.dataset.theme=tm;d.style.colorScheme=tm;})();</script>';
}
