<?php

$root = dirname(__DIR__, 2);
$style = file_get_contents($root . '/public/assets/css/style.css');
$ui = file_get_contents($root . '/public/assets/css/ui-refresh.css');
$errors = file_get_contents($root . '/public/assets/css/errors.css');
$scripts = file_get_contents($root . '/public/assets/js/scripts.js');

$tokens = [
    '--color-background: #EEF0F2',
    '--color-panel: #FFFFFF',
    '--color-soft: #F5F7F9',
    '--color-border: #CDD3DB',
    '--color-text: #20252B',
    '--color-muted: #667085',
    '--color-accent: #4A5C88',
    '--color-route: #4F72DF',
    '--color-success: #18794E',
    '--color-warning: #B7791F',
    '--color-danger: #B42318',
    '--color-info: #175CD3',
    '--type-display-size: 40px',
    '--type-display-line: 44px',
    '--type-h1-size: 28px',
    '--type-h1-line: 34px',
    '--type-h2-size: 20px',
    '--type-h2-line: 26px',
    '--type-h3-size: 16px',
    '--type-h3-line: 22px',
    '--type-body-size: 14px',
    '--type-body-line: 20px',
    '--type-label-size: 13px',
    '--type-label-line: 16px',
    '--type-meta-size: 12px',
    '--type-meta-line: 16px',
    '--space-1: 4px',
    '--space-2: 8px',
    '--space-3: 12px',
    '--space-4: 16px',
    '--space-5: 24px',
    '--space-6: 32px',
    '--space-7: 48px',
    '--control-compact: 32px',
    '--control-default: 36px',
    '--control-comfortable: 40px',
    '--control-touch: 44px',
    '--motion-hover: 120ms',
    '--motion-popover: 150ms',
    '--motion-modal: 190ms',
    '--motion-state: 150ms',
    '--breakpoint-mobile: 640px',
    '--breakpoint-tablet: 1024px',
    '--breakpoint-desktop: 1440px',
    '--z-header: 100',
    '--z-mobile-nav: 3000',
    '--z-dropdown: 3600',
    '--z-popover: 3800',
    '--z-modal: 7000',
    '--z-toast: 8000',
    '--z-confirm: 9000',
];

foreach ($tokens as $token) {
    if (!str_contains($style, $token)) {
        fwrite(STDERR, "Token visual ausente: {$token}\n");
        exit(1);
    }
}

$darkTokens = [
    '--color-background: #1A1F24',
    '--color-panel: #14191D',
    '--color-soft: #1C2227',
    '--color-border: #303943',
    '--color-text: #D5DAE1',
    '--color-text-strong: #EBEEF2',
    '--color-muted: #969FAA',
    '--color-accent: #687DB3',
];
foreach ($darkTokens as $token) {
    if (!str_contains($style, $token)) {
        fwrite(STDERR, "Token dark ausente: {$token}\n");
        exit(1);
    }
}

$uiRules = [
    'font-variant-numeric: tabular-nums lining-nums',
    'outline: var(--focus-width) solid var(--focus-color)',
    'background: var(--ui-panel);',
    'box-shadow: var(--shadow-sm);',
    'box-shadow: var(--shadow-md);',
    '@media (max-width: 1023px)',
    '@media (max-width: 639px)',
    '@media (min-width: 1440px)',
    '@media (prefers-reduced-motion: reduce)',
];
foreach ($uiRules as $rule) {
    if (!str_contains($ui, $rule)) {
        fwrite(STDERR, "Regra do design system ausente: {$rule}\n");
        exit(1);
    }
}

if (str_contains($ui, '.draft-recovery-toast{position:fixed') || str_contains($ui, '.ui-toast{display:grid') && str_contains($ui, 'background:linear-gradient(145deg')) {
    fwrite(STDERR, "Feedback legado decorativo ainda está ativo.\n");
    exit(1);
}

if (!str_contains($scripts, "'draft-recovery draft-recovery-activity'") || !str_contains($scripts, "recovery.setAttribute('role', 'region')")) {
    fwrite(STDERR, "Recuperação persistente de rascunho ausente.\n");
    exit(1);
}

if (preg_match('/z-index\s*:\s*\d{3,}/i', implode("\n", array_map('file_get_contents', glob($root . '/public/assets/css/*.css'))))) {
    fwrite(STDERR, "z-index global numérico fora dos tokens.\n");
    exit(1);
}

if (preg_match('/(^|\n)\s*:root\s*\{/m', $errors)) {
    fwrite(STDERR, "errors.css criou um sistema de tokens concorrente.\n");
    exit(1);
}

foreach (['403.php', '404.php', '500.php'] as $name) {
    $html = file_get_contents($root . '/public/errors/' . $name);
    foreach (['/assets/css/style.css', '/assets/css/ui-refresh.css', '/assets/css/errors.css'] as $sheet) {
        if (!str_contains($html, $sheet)) {
            fwrite(STDERR, "{$name} sem {$sheet}.\n");
            exit(1);
        }
    }
}

$phpFiles = array_merge(
    glob($root . '/public/*.php'),
    glob($root . '/public/admin/*.php'),
    glob($root . '/public/user/*.php')
);
foreach ($phpFiles as $file) {
    $html = file_get_contents($file);
    $uiPos = strrpos($html, '/assets/css/ui-refresh.css');
    if ($uiPos === false || str_contains($file, '/errors/')) {
        continue;
    }
    preg_match_all('/href=["\']([^"\']+\.css[^"\']*)["\']/i', $html, $matches, PREG_OFFSET_CAPTURE);
    foreach ($matches[1] as [$href, $offset]) {
        if ($offset > $uiPos && !str_contains($href, 'errors.css')) {
            fwrite(STDERR, basename($file) . " carrega CSS de página depois de ui-refresh.css: {$href}\n");
            exit(1);
        }
    }
}

printf("✓ design system static\n");
