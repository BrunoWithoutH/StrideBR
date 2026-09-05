<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$style = (string) file_get_contents($root . '/public/assets/css/style.css');
$ui = (string) file_get_contents($root . '/public/assets/css/ui-refresh.css');
$schedule = (string) file_get_contents($root . '/public/assets/css/cronogramas.css');
$dashboard = (string) file_get_contents($root . '/public/assets/css/dashboard.css');
$activities = (string) file_get_contents($root . '/public/assets/css/atividades.css');
$sportHub = (string) file_get_contents($root . '/public/assets/css/sport-hub.css');

$checks = [
    'dark usa borda global suavizada' => str_contains($style, '--color-border: #303943') && str_contains($style, '--color-border-soft: #29323A'),
    'grade possui tokens próprios de contraste' => str_contains($style, '--ui-grid-border: #303944') && str_contains($style, '--ui-grid-border-soft: #29313A') && str_contains($style, '--ui-grid-header: #1B2126'),
    'ícones de histórico possuem superfície dark sem branco' => str_contains($style, '--ui-icon-surface: #1B2127') && str_contains($style, '--ui-icon-foreground: #BFC7D0'),
    'agenda semanal usa tokens de grade' => str_contains($schedule, 'border-top: 1px solid var(--ui-grid-border-soft)') && str_contains($schedule, 'border-left: 1px solid var(--ui-grid-border)') && str_contains($schedule, 'background: var(--ui-grid-header)'),
    'histórico recente usa tokens de ícone' => str_contains($dashboard, 'border: 1px solid var(--ui-icon-border)') && str_contains($dashboard, 'background: var(--ui-icon-surface)'),
    'musculação não usa texto secundário como borda dark' => !str_contains($activities, 'border-color:var(--ui-text-secondary)') && str_contains($activities, ':root[data-theme=dark] .activity-strength-exercise{background:var(--ui-panel-soft);border-color:var(--ui-border)}'),
    'progresso não usa texto secundário como borda dark' => !str_contains($sportHub, 'border-color:var(--ui-text-secondary)') && str_contains($sportHub, 'background:var(--ui-panel);border-color:var(--ui-border)'),
    'menus e auth seguem superfícies sem hex dark legado' => str_contains($ui, '.global-create-content { background: var(--ui-panel);') && str_contains($ui, '.signup-onboarding-card { background: var(--ui-panel);'),
    'hover dark usa superfície sem literal legado' => str_contains($ui, '.global-create-content a:hover { background: var(--ui-surface-hover); }') && !str_contains($ui, '#22272b'),
    'cards de treino dark têm contraste próprio' => str_contains($ui, ':root[data-theme="dark"] .workout-card{background:color-mix(in srgb,var(--ui-panel) 72%,var(--ui-panel-soft))'),
];

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
if ($failed !== []) {
    fwrite(STDERR, "Falhas no contraste dark:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

printf("✓ dark contrast static: %d assertions\n", count($checks));
