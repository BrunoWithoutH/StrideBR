<?php

$root = dirname(__DIR__, 2);
$cssDir = $root . '/public/assets/css';
$style = file_get_contents($cssDir . '/style.css');
$activities = file_get_contents($cssDir . '/atividades.css');

$requiredTokens = [
    '--radius-sm: 4px',
    '--radius-md: 8px',
    '--radius-lg: 12px',
    '--radius-xl: 16px',
    '--radius-full: 999px',
    '--radius-circle: 50%',
];

foreach ($requiredTokens as $token) {
    if (!str_contains($style, $token)) {
        fwrite(STDERR, "Token de radius ausente: {$token}\n");
        exit(1);
    }
}

if (!str_contains($style, '@supports (corner-shape: squircle)') || !str_contains($style, 'corner-shape: var(--ui-corner-shape)')) {
    fwrite(STDERR, "Progressive enhancement de corner-shape ausente.\n");
    exit(1);
}

if (!str_contains($activities, '--nested-radius: max(0px, calc(var(--nested-outer-radius) - var(--nested-inset)))')) {
    fwrite(STDERR, "Cálculo reutilizável de nested corners ausente.\n");
    exit(1);
}

$allowed = [
    '0',
    '0!important',
    '0 !important',
    'inherit',
];
$invalid = [];
foreach (glob($cssDir . '/*.css') as $file) {
    $css = file_get_contents($file);
    if (!preg_match_all('/border-radius\s*:\s*([^;}{]+)/i', $css, $matches, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    foreach ($matches[1] as [$raw, $offset]) {
        $value = trim($raw);
        if (in_array($value, $allowed, true)) {
            continue;
        }
        if (str_contains($value, 'var(--radius-') || str_contains($value, 'var(--nested-')) {
            continue;
        }
        $invalid[] = basename($file) . ':' . $value;
    }
}

if ($invalid) {
    fwrite(STDERR, "Border-radius fora do sistema:\n" . implode("\n", $invalid) . "\n");
    exit(1);
}

printf("✓ radius system static: %d CSS files\n", count(glob($cssDir . '/*.css')));
