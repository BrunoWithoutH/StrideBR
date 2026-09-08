<?php

declare(strict_types=1);
require_once __DIR__ . '/environment.php';

/** Language labels are autonyms, independent of the active UI dictionary. */
function stridebr_locale_registry(): array
{
    return [
        'pt-BR' => ['autonym' => 'Português (Brasil)', 'fallback' => 'pt-BR', 'language' => 'pt'],
        'en' => ['autonym' => 'English', 'fallback' => 'pt-BR', 'language' => 'en'],
    ];
}

function stridebr_supported_locales(): array
{
    return array_keys(stridebr_locale_registry());
}

function stridebr_supported_locale_modes(): array
{
    return array_merge(['auto'], stridebr_supported_locales());
}

function stridebr_normalize_locale(?string $locale): string
{
    $locale = trim((string) $locale);
    $normalized = str_replace('_', '-', $locale);
    foreach (stridebr_locale_registry() as $id => $metadata) {
        if (preg_match('/^' . preg_quote($metadata['language'], '/') . '(?:-|$)/i', $normalized) === 1) return $id;
    }
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
            'secure' => stridebr_secure_cookie(),
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

function stridebr_t_locale(string $locale, string $key, array $replace = [], ?string $fallback = null): string
{
    $locale = stridebr_normalize_locale($locale);
    $dictionary = stridebr_locale_dictionary($locale);
    $pt = stridebr_locale_dictionary('pt-BR');
    $text = (string) ($dictionary[$key] ?? $pt[$key] ?? $fallback ?? $key);
    foreach ($replace as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }
    return $text;
}

function stridebr_t(string $key, array $replace = [], ?string $fallback = null): string
{
    return stridebr_t_locale(stridebr_locale(), $key, $replace, $fallback);
}

function stridebr_tn_locale(string $locale, string $oneKey, string $otherKey, int|float $count, array $replace = []): string
{
    $replace['count'] = $replace['count'] ?? stridebr_format_number($count, 0, false, $locale);
    return stridebr_t_locale($locale, ((float) $count === 1.0) ? $oneKey : $otherKey, $replace);
}

function stridebr_tn(string $oneKey, string $otherKey, int|float $count, array $replace = []): string
{
    return stridebr_tn_locale(stridebr_locale(), $oneKey, $otherKey, $count, $replace);
}

function stridebr_format_number(int|float|string|null $value, int $decimals = 0, bool $trimZeros = false, ?string $locale = null): string
{
    if ($value === null || $value === '' || !is_numeric($value)) return '';
    $locale = stridebr_normalize_locale($locale ?? stridebr_locale());
    $decimal = $locale === 'pt-BR' ? ',' : '.';
    $thousands = $locale === 'pt-BR' ? '.' : ',';
    $formatted = number_format((float) $value, max(0, $decimals), $decimal, $thousands);
    if ($trimZeros && $decimals > 0) {
        $formatted = rtrim(rtrim($formatted, '0'), $decimal);
    }
    return $formatted;
}

function stridebr_weekday_names(?string $locale = null): array
{
    $locale = stridebr_normalize_locale($locale ?? stridebr_locale());
    return $locale === 'en'
        ? ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
        : ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
}

function stridebr_weekday_short_names(?string $locale = null): array
{
    $locale = stridebr_normalize_locale($locale ?? stridebr_locale());
    return $locale === 'en'
        ? ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
        : ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
}

function stridebr_month_names(?string $locale = null): array
{
    $locale = stridebr_normalize_locale($locale ?? stridebr_locale());
    return $locale === 'en'
        ? [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
        : [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
}

function stridebr_month_short_names(?string $locale = null): array
{
    $locale = stridebr_normalize_locale($locale ?? stridebr_locale());
    return $locale === 'en'
        ? [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
        : [1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
}

function stridebr_i18n_date(mixed $value): ?DateTimeImmutable
{
    if ($value instanceof DateTimeImmutable) return $value;
    if ($value instanceof DateTimeInterface) return DateTimeImmutable::createFromInterface($value);
    $raw = trim((string) $value);
    if ($raw === '') return null;
    try { return new DateTimeImmutable($raw); } catch (Throwable) { return null; }
}

function stridebr_weekday_name(mixed $date, ?string $locale = null): string
{
    $date = stridebr_i18n_date($date); if (!$date) return '';
    return stridebr_weekday_names($locale)[(int) $date->format('w')] ?? '';
}

function stridebr_weekday_short(mixed $date, ?string $locale = null): string
{
    $date = stridebr_i18n_date($date); if (!$date) return '';
    return stridebr_weekday_short_names($locale)[(int) $date->format('w')] ?? '';
}

function stridebr_month_name(int|DateTimeInterface|string $month, ?string $locale = null): string
{
    $number = is_int($month) ? $month : (int) (stridebr_i18n_date($month)?->format('n') ?? 0);
    return stridebr_month_names($locale)[$number] ?? '';
}

function stridebr_month_short(int|DateTimeInterface|string $month, ?string $locale = null): string
{
    $number = is_int($month) ? $month : (int) (stridebr_i18n_date($month)?->format('n') ?? 0);
    return stridebr_month_short_names($locale)[$number] ?? '';
}

function stridebr_format_month_year(mixed $date, ?string $locale = null): string
{
    $date = stridebr_i18n_date($date); if (!$date) return '';
    $locale = stridebr_normalize_locale($locale ?? stridebr_locale());
    $month = stridebr_month_name($date, $locale);
    return $locale === 'en' ? $month . ' ' . $date->format('Y') : $month . ' de ' . $date->format('Y');
}

function stridebr_format_date_short(mixed $date, ?string $locale = null): string
{
    $date = stridebr_i18n_date($date); if (!$date) return '';
    $locale = stridebr_normalize_locale($locale ?? stridebr_locale());
    if ($locale === 'en') return stridebr_month_short($date, $locale) . ' ' . (int) $date->format('j');
    return $date->format('d/m');
}

function stridebr_format_date(mixed $date, ?string $locale = null): string
{
    $date = stridebr_i18n_date($date); if (!$date) return '';
    $locale = stridebr_normalize_locale($locale ?? stridebr_locale());
    if ($locale === 'en') return stridebr_month_short($date, $locale) . ' ' . (int) $date->format('j') . ', ' . $date->format('Y');
    return $date->format('d/m/Y');
}

function stridebr_format_date_weekday(mixed $date, ?string $locale = null): string
{
    $date = stridebr_i18n_date($date); if (!$date) return '';
    $locale = stridebr_normalize_locale($locale ?? stridebr_locale());
    return stridebr_weekday_name($date, $locale) . ', ' . stridebr_format_date_short($date, $locale);
}

function stridebr_format_datetime_short(mixed $date, ?string $locale = null): string
{
    $date = stridebr_i18n_date($date); if (!$date) return '';
    return stridebr_format_date_short($date, $locale) . ' · ' . $date->format('H:i');
}

function stridebr_format_sport_duration(int|float|string|null $seconds, bool $forceClock = false, ?string $locale = null): string
{
    if ($seconds === null || $seconds === '' || !is_numeric($seconds)) return '';
    $totalMilliseconds = max(0, (int) round((float) $seconds * 1000));
    $hours = intdiv($totalMilliseconds, 3600000);
    $remaining = $totalMilliseconds % 3600000;
    $minutes = intdiv($remaining, 60000);
    $remaining %= 60000;
    $wholeSeconds = intdiv($remaining, 1000);
    $milliseconds = $remaining % 1000;
    $locale = stridebr_normalize_locale($locale ?? stridebr_locale());
    $separator = $locale === 'pt-BR' ? ',' : '.';
    $fraction = $milliseconds > 0 ? $separator . str_pad((string) $milliseconds, 3, '0', STR_PAD_LEFT) : '';
    if ($hours > 0) return sprintf('%d:%02d:%02d', $hours, $minutes, $wholeSeconds) . $fraction;
    if ($minutes > 0 || $forceClock) return sprintf('%d:%02d', $minutes, $wholeSeconds) . $fraction;
    return (string) $wholeSeconds . $fraction . ' s';
}

function stridebr_sport_translation_key(string $slug): ?string
{
    $slug = strtolower(trim($slug));
    if ($slug === '') return null;
    $known = [
        'corrida','caminhada','corrida-em-trilha','trilha','corrida-em-esteira','ciclismo','mountain-bike','gravel','bicicleta-eletrica','e-mountain-bike','ciclismo-indoor','handcycle','velomovel',
        'natacao','natacao-piscina','natacao-aguas-abertas','remo','remo-indoor','canoagem','caiaque','stand-up-paddle','surfe','kitesurf','windsurf','vela',
        'musculacao','calistenia','crossfit','hiit','treino-funcional','cardio','eliptico','simulador-de-escada','pular-corda','yoga','pilates','mobilidade','danca',
        'tenis','tenis-de-mesa','badminton','pickleball','squash','raquetebol','futebol','futsal','basquete','volei','volei-de-praia','handebol','rugby','futebol-americano','criquete',
        'judo','jiu-jitsu','boxe','muay-thai','taekwondo','capoeira','luta-olimpica','kickboxing','esgrima','atletismo','marcha-atletica','salto-em-distancia','salto-triplo','salto-em-altura','salto-com-vara','arremesso-de-peso','lancamento-de-disco','lancamento-de-dardo','lancamento-de-martelo',
        'escalada','boulder','patinacao-inline','patins','skate','roller-ski','esqui-alpino','esqui-nordico','esqui-fora-de-pista','snowboard','raquete-de-neve','patinacao-no-gelo','golfe','equitacao','cadeira-de-rodas','downhill','bmx'
    ];
    return in_array($slug, $known, true) ? 'sport.' . str_replace('-', '_', $slug) : null;
}

function stridebr_sport_name(string $slug, ?string $fallback = null, ?string $locale = null): string
{
    $key = stridebr_sport_translation_key($slug);
    if ($key === null) return trim((string) ($fallback ?? $slug));
    $locale = stridebr_normalize_locale($locale ?? stridebr_locale());
    $translated = stridebr_t_locale($locale, $key, [], $fallback ?? $slug);
    return $translated === $key ? trim((string) ($fallback ?? $slug)) : $translated;
}

function stridebr_activity_field_translation_key(string $slug): ?string
{
    $slug = strtolower(trim(str_replace('_', '-', $slug)));
    return match ($slug) {
        'distancia' => 'activity.distance',
        'duracao' => 'activity.duration',
        'ritmo', 'pace' => 'activity.pace',
        'velocidade' => 'activity.speed',
        'elevacao', 'desnivel' => 'activity.elevation',
        'codigo-treino' => 'activity.code',
        'foco-muscular' => 'activity.focus',
        'series' => 'common.series',
        'repeticoes' => 'common.repetitions',
        'carga' => 'common.load',
        'cadencia' => 'common.cadence',
        'potencia' => 'activity.power',
        'vento' => 'activity.wind',
        'tempo-reacao' => 'activity.reaction_time',
        default => null,
    };
}

function stridebr_activity_field_label(string $slug, ?string $fallback = null, ?string $locale = null): string
{
    $fallback = trim((string) ($fallback ?? $slug));
    $key = stridebr_activity_field_translation_key($slug);
    if ($key === null) return $fallback;
    return stridebr_t_locale($locale ?? stridebr_locale(), $key, [], $fallback);
}

function stridebr_auto_activity_title(string $sportSlug, mixed $when, ?string $storedTitle = null, ?string $locale = null): string
{
    $sportSlug = strtolower(trim($sportSlug));
    $locale = stridebr_normalize_locale($locale ?? stridebr_locale());
    $hour = $when instanceof DateTimeInterface ? (int) $when->format('G') : (int) date('G', strtotime((string) $when) ?: time());
    $period = $hour < 5 ? 'late_night' : ($hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening'));
    $specialKey = 'activity.auto_title.' . str_replace('-', '_', $sportSlug) . '.' . $period;
    $dict = stridebr_locale_dictionary($locale);
    if (isset($dict[$specialKey])) return stridebr_t_locale($locale, $specialKey);
    return stridebr_t_locale($locale, 'activity.auto_title.generic.' . $period, ['sport' => stridebr_sport_name($sportSlug, $storedTitle ?: $sportSlug, $locale)]);
}

function stridebr_present_activity_title(string $title, string $sportSlug, ?string $locale = null): string
{
    $locale = stridebr_normalize_locale($locale ?? stridebr_locale());
    if ($locale !== 'en') return $title;
    $canonical = [
        'corrida' => [
            'Corrida de madrugada' => 'activity.auto_title.corrida.late_night',
            'Corrida pela manhã' => 'activity.auto_title.corrida.morning',
            'Corrida à tarde' => 'activity.auto_title.corrida.afternoon',
            'Corrida à noite' => 'activity.auto_title.corrida.evening',
        ],
        'musculacao' => ['Treino de musculação' => 'activity.auto_title.musculacao.generic'],
        'calistenia' => ['Treino de calistenia' => 'activity.auto_title.calistenia.generic'],
        'crossfit' => ['Treino de CrossFit' => 'activity.auto_title.crossfit.generic'],
    ];
    $key = $canonical[$sportSlug][$title] ?? null;
    return $key ? stridebr_t_locale($locale, $key) : $title;
}


function stridebr_js_i18n_dictionary(?string $locale = null): array
{
    $dictionary = stridebr_locale_dictionary($locale ?? stridebr_locale());
    $allowedPrefixes = ['workout_session.', 'planning.', 'common.', 'nav.', 'activity.', 'route.', 'schedule.', 'agenda.', 'library.', 'sport.', 'notifications.', 'onboarding.', 'auth.', 'trainer.', 'friends.', 'events.', 'event.', 'profile.', 'settings.', 'account.', 'progress.', 'goals.', 'home.', 'js.'];
    return array_filter($dictionary, static function (mixed $value, string $key) use ($allowedPrefixes): bool {
        if (!is_string($value) || $value === '') return false;
        foreach ($allowedPrefixes as $prefix) if (str_starts_with($key, $prefix)) return true;
        return false;
    }, ARRAY_FILTER_USE_BOTH);
}

function stridebr_i18n_runtime_script(bool $defer = true): string
{
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $src = function_exists('stridebr_asset') ? stridebr_asset('/assets/js/i18n-runtime.js') : '/assets/js/i18n-runtime.js';
    $json = json_encode(stridebr_js_i18n_dictionary(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    $encoded = base64_encode($json);
    return '<script src="' . $escape($src) . '" data-stridebr-i18n data-locale="' . $escape(stridebr_locale()) . '" data-dictionary="' . $escape($encoded) . '"' . ($defer ? ' defer' : '') . '></script>';
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
            'secure' => stridebr_secure_cookie(),
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
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $uiSrc = function_exists('stridebr_asset') ? stridebr_asset('/assets/js/ui-boot.js') : '/assets/js/ui-boot.js';
    $pwaSrc = function_exists('stridebr_asset') ? stridebr_asset('/assets/js/pwa.js') : '/assets/js/pwa.js';
    $touchIcon = function_exists('stridebr_asset') ? stridebr_asset('/assets/img/pwa/apple-touch-icon.png') : '/assets/img/pwa/apple-touch-icon.png';
    return '<link rel="manifest" href="/manifest.webmanifest">'
        . '<meta name="theme-color" content="#40507C">'
        . '<meta name="mobile-web-app-capable" content="yes">'
        . '<meta name="apple-mobile-web-app-capable" content="yes">'
        . '<meta name="apple-mobile-web-app-title" content="StrideBR">'
        . '<meta name="apple-mobile-web-app-status-bar-style" content="default">'
        . '<link rel="apple-touch-icon" href="' . $escape($touchIcon) . '">'
        . '<script data-stridebr-ui-boot src="' . $escape($uiSrc) . '" data-theme-mode="' . $escape(stridebr_theme()) . '" data-locale-mode="' . $escape(stridebr_locale_preference()) . '" data-locale="' . $escape(stridebr_locale()) . '"></script>'
        . '<script src="' . $escape($pwaSrc) . '" data-build="' . $escape(function_exists('stridebr_build') ? stridebr_build() : 'rc') . '" defer></script>';
}
