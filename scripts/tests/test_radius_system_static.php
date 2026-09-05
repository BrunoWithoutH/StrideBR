<?php

$root = dirname(__DIR__, 2);
$cssDir = $root . '/public/assets/css';
$style = file_get_contents($cssDir . '/style.css');
$ui = file_get_contents($cssDir . '/ui-refresh.css');
$activities = file_get_contents($cssDir . '/atividades.css');
$dashboard = file_get_contents($cssDir . '/dashboard.css');
$sportHub = file_get_contents($cssDir . '/sport-hub.css');
$login = file_get_contents($cssDir . '/loginsignup.css');
$cronogramas = file_get_contents($cssDir . '/cronogramas.css');
$gps = file_get_contents($cssDir . '/gps-recorder.css');
$product = file_get_contents($cssDir . '/product-insights.css');
$events = file_get_contents($cssDir . '/events.css');

$requiredTokens = [
    '--radius-sm: 6px',
    '--radius-md: 10px',
    '--radius-lg: 14px',
    '--radius-xl: 18px',
    '--radius-full: 999px',
    '--radius-circle: 50%',
    '--radius-detail: var(--radius-sm)',
    '--radius-control: var(--radius-md)',
    '--radius-menu: var(--radius-lg)',
    '--radius-card: var(--radius-lg)',
    '--radius-modal: var(--radius-xl)',
    '--nested-radius: max(0px, calc(var(--nested-outer-radius) - var(--nested-inset)))',
];

foreach ($requiredTokens as $token) {
    if (!str_contains($style, $token)) {
        fwrite(STDERR, "Token de radius ausente: {$token}\n");
        exit(1);
    }
}

if (!str_contains($style, '@supports (corner-shape: squircle)') || !str_contains($ui, ':where(*, *::before, *::after)') || !str_contains($ui, 'corner-shape: var(--corner-shape)')) {
    fwrite(STDERR, "Cobertura progressiva de corner-shape ausente.\n");
    exit(1);
}

if (!preg_match('/:where\(input:not\(\[type="checkbox"\]\):not\(\[type="radio"\]\):not\(\[type="range"\]\):not\(\[type="color"\]\):not\(\[type="hidden"\]\), select, textarea\)\s*\{[^{}]*border-radius:\s*var\(--radius-control\)\s*!important/s', $ui)) {
    fwrite(STDERR, "Regra base de radius dos campos precisa permanecer de baixa especificidade.\n");
    exit(1);
}

if (preg_match('/:is\(input:not\(\[type="checkbox"\]\):not\(\[type="radio"\]\):not\(\[type="range"\]\):not\(\[type="color"\]\):not\(\[type="hidden"\]\), select, textarea\)\s*\{[^{}]*border-radius:/s', $ui)) {
    fwrite(STDERR, "Regra de alta especificidade está sobrescrevendo compound controls.\n");
    exit(1);
}

if (!str_contains($activities, '--nested-outer-radius: var(--radius-card)') || !str_contains($activities, '--nested-radius: max(0px, calc(var(--nested-outer-radius) - var(--nested-inset)))')) {
    fwrite(STDERR, "Cálculo semântico de nested corners ausente em atividades.\n");
    exit(1);
}

$semanticPatterns = [
    [$activities, '/\.duration-segments\s*\{[^{}]*border-radius:\s*var\(--radius-control\)/s', 'Duração não usa radius-control.'],
    [$ui, '/\.activity-editor-shell \.activity-editor \.derived-result-wrap\s*\{[^{}]*border-radius:\s*var\(--radius-control\)/s', 'Ritmo calculado não usa radius-control.'],
    [$ui, '/\.activity-editor-shell \.activity-editor \.derived-result-wrap input\s*\{[^{}]*border-radius:\s*0\s*!important/s', 'Input interno do ritmo calculado não usa radius 0.'],
    [$ui, '/\.activity-editor-shell \.activity-editor \.activity-time-field \.clock-segments\s*\{[^{}]*border-radius:\s*var\(--radius-control\)/s', 'Hora não usa radius-control no wrapper.'],
    [$ui, '/\.activity-editor-shell \.activity-editor \.activity-time-field \.clock-segments\s*>\s*input[^{}]*\{[^{}]*border-radius:\s*0\s*!important/s', 'Inputs internos da hora não usam radius 0.'],
    [$dashboard, '/\.dashboard-target-input\s*\{[^{}]*border-radius:\s*var\(--radius-control\)[^{}]*overflow:\s*hidden/s', 'Compound control do dashboard não usa wrapper control com clipping.'],
    [$dashboard, '/\.dashboard-target-input input\s*\{[^{}]*border-radius:\s*0\s*!important/s', 'Input interno do compound control do dashboard não usa radius 0.'],
    [$ui, '/\.settings-form \.settings-social-input input\s*\{[^{}]*border-radius:\s*0\s*!important/s', 'Input interno de rede social não usa radius 0.'],
    [$ui, '/\.user-menu-content\s*\{[^{}]*border-radius:\s*var\(--radius-menu\)/s', 'Menu de usuário não usa radius-menu.'],
    [$ui, '/\.workout-preview-menu\s*\{[^{}]*border-radius:\s*var\(--radius-menu\)/s', 'Menu de preview não usa radius-menu.'],
    [$ui, '/\.calendar-quick-create-popover\s*\{[^{}]*border-radius:\s*var\(--radius-modal\)\s*!important/s', 'Quick create overlay não usa radius-modal.'],
    [$login, '/\.container-fluid \.form\s*\{[^{}]*border-radius:\s*var\(--radius-card\)/s', 'Superfície de autenticação não usa radius-card.'],
    [$activities, '/\.activity-edit-form\s*\{[^{}]*border-radius:\s*var\(--radius-card\)/s', 'Editor de atividade standalone não usa radius-card.'],
    [$activities, '/@media\s*\(min-width:901px\)[\s\S]*?\.activity-detail-panel\s*\{[^{}]*border-radius:\s*var\(--radius-card\)/s', 'Painel de detalhe desktop não usa radius-card.'],
    [$style, '/\.profile-banner\s*\{[^{}]*border-radius:\s*var\(--radius-card\)/s', 'Profile banner não usa radius-card.'],
    [$style, '/\.mobile-bottom-nav\s*\{[^{}]*border-radius:\s*var\(--radius-menu\)/s', 'Navegação mobile flutuante não usa radius-menu.'],
    [$style, '/\.workout-choice-row button\s*\{[^{}]*border-radius:\s*var\(--radius-control\)/s', 'Botões de escolha de treino não podem virar pills.'],
    [$style, '/\.feedback-fab\s*\{[^{}]*border-radius:\s*var\(--radius-control\)/s', 'Feedback FAB retangular não usa radius-control.'],
    [$sportHub, '/\.sport-subtabs a\{[^{}]*border-radius:var\(--radius-control\)/s', 'Tabs de esporte não podem usar radius-full.'],
    [$sportHub, '/\.progress-period-ranges a,\.progress-period-current\{[^{}]*border-radius:var\(--radius-control\)/s', 'Controles de período não podem usar radius-full.'],
    [$ui, '/@media\s*\(max-width:\s*720px\)[\s\S]*?\.activity-editor-shell \.activity-editor,[\s\S]*?border-radius:\s*0\s*!important/s', 'Editor fullscreen mobile não zera radius.'],
    [$activities, '/\.activity-detail-route-map\s*\{[^{}]*border-radius:\s*var\(--radius-card\)/s', 'Mapa de detalhe não usa radius-card.'],
    [$activities, '/\.activity-unit-route-editor\s*\{[^{}]*border-radius:\s*var\(--radius-card\)/s', 'Editor de rota não usa radius-card.'],
    [$activities, '/\.activity-unit-route-(?:map|preview)\s*\{[^{}]*border-radius:\s*var\(--radius-detail\)/s', 'Preview interno de rota não usa radius-detail.'],
    [$activities, '/\.activity-gps-web-notice\s*\{[^{}]*border-radius:\s*var\(--radius-card\)/s', 'Aviso principal de GPS não usa radius-card.'],
    [$activities, '/\.sport-option-row\s*\{[^{}]*border-radius:\s*0/s', 'Linha interna do seletor de esporte não permanece reta.'],
    [$dashboard, '/\.goals-form-block\s*\{[^{}]*border-radius:\s*var\(--radius-card\)/s', 'Bloco do formulário de metas não usa radius-card.'],
    [$dashboard, '/\.dashboard-today\s*\{[^{}]*border-radius:\s*var\(--radius-card\)/s', 'Superfície de hoje não usa radius-card.'],
    [$dashboard, '/\.dashboard-today-chip\s*\{[^{}]*border-radius:\s*var\(--radius-full\)[^{}]*corner-shape:\s*round/s', 'Chip de hoje não preserva pill round.'],
    [$dashboard, '/\.dashboard-customize-item\s*\{[^{}]*border-radius:\s*0/s', 'Itens contíguos de customização não usam radius 0.'],
    [$dashboard, '/\.goals-editor-close\s*\{[^{}]*border-radius:\s*var\(--radius-detail\)/s', 'Fechar editor de metas não usa radius-detail.'],
    [$cronogramas, '/\.quick-create-exercises\s*\{[^{}]*border-radius:\s*var\(--radius-card\)/s', 'Exercícios do quick create não usam radius-card.'],
    [$cronogramas, '/\.quick-create-error\s*\{[^{}]*border-radius:\s*var\(--radius-detail\)/s', 'Erro compacto do quick create não usa radius-detail.'],
    [$cronogramas, '/\.library-selector-panel\s*\{[^{}]*border-radius:\s*var\(--radius-card\)/s', 'Painel seletor da biblioteca não usa radius-card.'],
    [$cronogramas, '/\.schedule-undo-bar\s*\{[^{}]*border-radius:\s*var\(--radius-card\)/s', 'Barra de undo não usa radius-card.'],
    [$cronogramas, '/\.schedule-create-panel\s*\{[^{}]*border-radius:\s*0/s', 'Shell externo de criação de cronograma não usa radius 0.'],
    [$gps, '/\.gps-(?:lap-strip|segment-row)\s*\{[^{}]*border-radius:\s*var\(--radius-detail\)/s', 'Detalhes compactos do GPS não usam radius-detail.'],
    [$product, '/\.strength-summary div\{[^{}]*border-radius:var\(--radius-detail\)/s', 'Resumo compacto de força não usa radius-detail.'],
    [$sportHub, '/\.strength-exercise-chart\{[^{}]*border-radius:var\(--radius-card\)/s', 'Gráfico de força não usa radius-card.'],
    [$sportHub, '/\.strength-session-table\{[^{}]*border-radius:var\(--radius-card\)/s', 'Tabela de força não usa radius-card.'],
    [$events, '/\.event-card-media time\s*\{[^{}]*border-radius:\s*var\(--radius-detail\)/s', 'Data compacta do card de evento não usa radius-detail.'],
    [$ui, '/\.activity-row-icon\s*\{[^{}]*border-radius:\s*var\(--radius-detail\)/s', 'Ícone compacto da atividade não usa radius-detail.'],
    [$ui, '/\.monthly-legend i\s*\{[^{}]*border-radius:\s*var\(--radius-circle\)[^{}]*corner-shape:\s*round/s', 'Indicador da legenda mensal não permanece circular.'],
    [$ui, '/\.floating-utility-dock \.pinned-tool-chip\s*\{[^{}]*border-radius:\s*var\(--radius-full\)[^{}]*corner-shape:\s*round/s', 'Pinned tool chip perdeu geometria pill.'],
    [$ui, '/\.trainer-more-menu>summary\{[^{}]*border-radius:var\(--radius-detail\)/s', 'Trigger compacto do menu do treinador não usa radius-detail.'],
    [$ui, '/\.library-more-menu>summary\{[^{}]*border-radius:var\(--radius-detail\)/s', 'Trigger compacto do menu da biblioteca não usa radius-detail.'],
    [$ui, '/\.global-create-menu > summary\s*\{[^{}]*border-radius:\s*var\(--radius-detail\)/s', 'Trigger compacto de criação global não usa radius-detail.'],
    [$ui, '/\.schedule-history-grid fieldset\{[^{}]*border-radius:var\(--radius-card\)/s', 'Grupo de histórico não usa radius-card.'],
    [$ui, '/\.onboarding-result\s*\{[^{}]*border-radius:\s*var\(--radius-card\)/s', 'Resultado do onboarding não usa radius-card.'],
    [$ui, '/\.onboarding-result-actions > div\s*\{[^{}]*border-radius:\s*var\(--radius-detail\)/s', 'Mini superfícies do onboarding não usam radius-detail.'],
    [$ui, '/\.ui-server-undo::before\s*\{[^{}]*border-radius:\s*var\(--radius-detail\)/s', 'Ícone interno do undo não usa radius-detail.'],
];

foreach ($semanticPatterns as [$css, $pattern, $message]) {
    if (!preg_match($pattern, $css)) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$allowed = ['0', '0!important', '0 !important', 'inherit'];
$invalid = [];
$genericScale = [];
$missingRound = [];
$mixedSheets = [];

foreach (glob($cssDir . '/*.css') as $file) {
    $css = file_get_contents($file);
    if (preg_match_all('/border-radius\s*:\s*([^;}{]+)/i', $css, $matches)) {
        foreach ($matches[1] as $raw) {
            $value = trim($raw);
            if (!in_array($value, $allowed, true) && !str_contains($value, 'var(--radius-') && !str_contains($value, 'var(--nested-')) {
                $invalid[] = basename($file) . ':' . $value;
            }
            if (preg_match('/var\(--radius-(?:sm|md|lg|xl)\)/', $value)) {
                $genericScale[] = basename($file) . ':' . $value;
            }
            if (preg_match('/^var\(--radius-([^)]+)\)\s+var\(--radius-([^)]+)\)\s+0\s+0(?:\s*!important)?$/', $value, $sheet) && $sheet[1] !== $sheet[2]) {
                $mixedSheets[] = basename($file) . ':' . $value;
            }
        }
    }

    if (preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $rules, PREG_SET_ORDER)) {
        foreach ($rules as $rule) {
            $body = $rule[2];
            if (!preg_match('/border-radius\s*:[^;]*(?:var\(--radius-full\)|var\(--radius-circle\))/', $body)) {
                continue;
            }
            if (!preg_match('/corner-shape\s*:\s*round(?:\s*!important)?/', $body)) {
                $missingRound[] = basename($file) . ':' . trim(preg_replace('/\s+/', ' ', $rule[1]));
            }
        }
    }
}

if ($invalid) {
    fwrite(STDERR, "Border-radius fora do sistema:\n" . implode("\n", $invalid) . "\n");
    exit(1);
}

if ($genericScale) {
    fwrite(STDERR, "Componente usando escala genérica em vez de alias semântico:\n" . implode("\n", $genericScale) . "\n");
    exit(1);
}

if ($missingRound) {
    fwrite(STDERR, "Pill/circle sem corner-shape round:\n" . implode("\n", $missingRound) . "\n");
    exit(1);
}

if ($mixedSheets) {
    fwrite(STDERR, "Bottom sheet com cantos superiores de famílias diferentes:\n" . implode("\n", $mixedSheets) . "\n");
    exit(1);
}

printf("✓ radius system static: %d CSS files\n", count(glob($cssDir . '/*.css')));
