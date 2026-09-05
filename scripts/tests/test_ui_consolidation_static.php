<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "UI consolidation static failed: {$message}\n");
        exit(1);
    }
};

$activities = $read('public/assets/css/atividades.css');
$sharing = $read('public/assets/css/activity-sharing.css');
$ui = $read('public/assets/css/ui-refresh.css');
$style = $read('public/assets/css/style.css');
$events = $read('public/assets/css/events.css');
$page = $read('public/user/atividades.php');
$js = $read('public/assets/js/atividades.js');
$settings = $read('public/user/settings.php');
$account = $read('public/user/account.php');
$workspace = $read('src/layout/settings_workspace.php');
$staticPage = $read('src/layout/static_page.php');
$gpsPage = $read('public/user/gravar-atividade.php');
$gpsCss = $read('public/assets/css/gps-recorder.css');
$gpsJs = $read('public/assets/js/gps-recorder.js');
$progressPage = $read('public/user/progresso.php');
$progressCss = $read('public/assets/css/sport-hub.css');
$goalsPage = $read('public/user/metas.php');
$dashboardCss = $read('public/assets/css/dashboard.css');

$assert(!str_contains($activities, '.activity-share-') && !str_contains($ui, '.activity-share-'), 'compartilhamento deve ter uma folha dedicada, sem versões concorrentes em atividades/ui-refresh.');
$assert(str_contains($page, '/assets/css/activity-sharing.css'), 'Atividades precisa carregar a folha consolidada do compartilhamento.');
$assert(!str_contains($sharing, '!important'), 'folha consolidada do compartilhamento não deve depender de !important.');
$assert(!preg_match('/(?:v3|v5|v8|v9|v10|final fix)/i', $sharing), 'folha consolidada não deve criar uma nova pilha de versões.');
$assert(str_contains($sharing, 'grid-template-columns: repeat(3, minmax(0, 1fr))') && str_contains($sharing, 'grid-template-rows: minmax(0, 1fr) auto'), 'preview precisa reservar stage real e os três formatos em barra horizontal.');
$assert(str_contains($sharing, 'height: 84px') && str_contains($sharing, 'height: 64px') && !preg_match('/font-size\s*:\s*\.5[0-9]*rem/i', $sharing), 'seletores visuais não podem ser comprimidos para 32px/texto microscópico.');
$assert(str_contains($js, 'const availableHeight = Math.max(0, stageRect.height') && str_contains($js, 'shareCanvas.style.width = `${fitted.width}px`') && !str_contains($js, 'shareCanvas.style.setProperty(\'width\', `${fitted.width}px`, \'important\')'), 'JS da preview deve apenas dimensionar/centralizar dentro do stage CSS.');
$assert(str_contains($js, "let activeShareCompositionId = 'standard'") && str_contains($js, "const isWide = format === 'square'") && !str_contains($js, "compactWide: {id: 'compactWide'"), 'Compacto deve ser composição e a versão em grade deve derivar do formato Quadrado.');
$assert(str_contains($page, "stridebr_t('activity.share.composition_compact')") && str_contains($page, 'data-share-composition'), 'rótulo de Compacto precisa ficar no seletor de composição.');

$semanticShareModifiers = [
    'activity-share-background-block' => 'activity-share-choice-block activity-share-background-block',
    'activity-share-content-block' => 'activity-share-choice-block activity-share-content-block',
    'activity-share-elements-group' => 'activity-share-group activity-share-elements-group',
    'activity-share-session-layout-block' => 'activity-share-choice-block activity-share-session-layout-block',
    'activity-share-style-grid' => 'activity-share-choice-grid is-background activity-share-style-grid',
    'activity-share-visual-options-group' => 'activity-share-group activity-share-visual-options-group',
];
foreach ($semanticShareModifiers as $modifier => $pairedMarkup) {
    $assert(str_contains($page, $pairedMarkup), "{$modifier} precisa permanecer somente como modificador semântico pareado a uma classe estrutural.");
    $assert(!preg_match('/\.' . preg_quote($modifier, '/') . '(?![A-Za-z0-9_-])/', $sharing), "{$modifier} não deve ganhar regra duplicada só para satisfazer a auditoria.");
}

$assert(str_contains($activities, '.activity-editor-heading') && str_contains($activities, 'background: var(--ui-panel-soft)') && !str_contains($ui, '#fbfbfc'), 'superfícies estruturais de Atividades precisam usar tokens em vez de branco literal.');
$assert(str_contains($activities, '.activity-history-filters :is(input, select, .sport-select-trigger, .activity-secondary-button)') && str_contains($activities, 'height: var(--control-default)'), 'filtros do histórico precisam usar contrato de 36px.');
$assert(str_contains($events, 'height: var(--control-comfortable)') && str_contains($events, 'height: var(--control-touch)'), 'Eventos precisa usar 40px no desktop e 44px no touch.');
$assert(!str_contains($events, '#4f5965') && !str_contains($events, '#cbd1d8') && !str_contains($events, '#20252b'), 'Eventos não deve depender das cores estruturais antigas confirmadas.');

$assert(str_contains($settings, "stridebr_settings_workspace_navigation(") && str_contains($account, "stridebr_settings_workspace_navigation('account')"), 'Preferências e Conta precisam usar o mesmo workspace de navegação.');
$assert(str_contains($workspace, "'profile' =>") && str_contains($workspace, "'preferences' =>") && str_contains($workspace, "'account' =>") && str_contains($workspace, 'aria-current="page"'), 'workspace de Configurações precisa ter três seções canônicas e aria-current.');
$assert(str_contains($style, '.settings-tabs a[aria-current="page"]') && str_contains($style, 'box-shadow: inset 0 -2px 0 var(--ui-accent)'), 'aba atual de Configurações precisa ter marcador estrutural.');

$bootPos = strpos($staticPage, 'stridebr_ui_boot_script()');
$stylePos = strpos($staticPage, '/assets/css/style.css');
$assert($bootPos !== false && $stylePos !== false && $bootPos < $stylePos, 'static pages precisam inicializar tema antes dos estilos.');
$assert(str_contains($ui, '.static-content p') && str_contains($ui, 'var(--ui-text-secondary)') && str_contains($ui, '.static-content code'), 'static pages precisam usar tokens de texto/código.');
$assert(preg_match('/\.pinned-tool-chip\s*\{[^}]*background:\s*var\(--ui-panel\)/s', $style) === 1, 'Timer/pinned tool precisa usar superfície temática.');

$assert(str_contains($ui, '.account-delete-form label') && str_contains($ui, 'color: var(--ui-text-secondary)') && !str_contains($ui, '#4f5965'), 'Conta e segurança deve usar tokens estruturais nas áreas consolidadas.');
$assert(str_contains($ui, '.settings-social-input:focus-within { border-color: var(--ui-accent); box-shadow: var(--focus-ring); }'), 'inputs compostos de Configurações precisam usar foco do design system.');
$assert(str_contains($ui, '.primary-button,') && str_contains($ui, 'box-sizing: border-box;') && str_contains($ui, 'display: inline-flex;') && str_contains($ui, 'vertical-align: middle;'), 'variantes de botão precisam compartilhar contrato geométrico independente da tag HTML.');
$assert(!str_contains($activities, 'border-top:1px solid #e1e6ed') && !str_contains($activities, 'color:#536174') && !str_contains($activities, 'background:#171b1e'), 'seleção em lote de Atividades precisa ser temática na regra de origem.');
$assert(str_contains($style, '.site-header:has(.user-menu[open])') && str_contains($style, 'z-index: var(--z-header-menu)'), 'menu do usuário precisa subir acima do drawer de atividade enquanto estiver aberto.');
$assert(!preg_match('/\.sport-favorites-quick\s*\{[^}]*background:\s*#f/is', $activities) && !preg_match('/\.activity-repeat-banner\s*\{[^}]*background:\s*#f/is', $activities) && !preg_match('/\.activity-unit-route-editor\s*\{[^}]*background:\s*#f/is', $activities), 'dark leaks confirmados de Atividades precisam usar superfícies semânticas.');
$assert(str_contains($ui, '.faq-list details') && str_contains($ui, 'border-radius: var(--radius-card)') && str_contains($ui, '.roadmap-columns section'), 'FAQ e cards de páginas estáticas precisam manter cantos do design system.');
$assert(str_contains($gpsPage, 'data-gps-discard-current') && str_contains($gpsPage, 'data-gps-discard-review') && str_contains($gpsJs, 'resetLocalRecording'), 'GPS precisa permitir cancelar e descartar a gravação atual.');
$assert(str_contains($gpsCss, '.gps-live-metrics .gps-primary-metric') && str_contains($gpsCss, 'display: flex') && str_contains($gpsCss, 'background: var(--ui-panel)'), 'cronômetro e inputs do GPS precisam usar geometria e superfícies temáticas.');
$assert(str_contains($progressPage, "stridebr_t('common.apply')") && !str_contains($progressPage, '<button type="submit">Ver</button>'), 'Progresso não deve exibir botão Ver redundante quando o mês já atualiza automaticamente.');
$assert(str_contains($progressCss, '.sport-hub-tabs a.is-active') && preg_match('/\.sport-hub-tabs a\.is-active\{[^}]*background:\s*var\(--ui-accent-soft\)[^}]*inset 0 -2px 0 var\(--ui-accent\)/s', $progressCss) === 1, 'aba ativa de Progresso precisa ter estado estrutural visível no dark.');
$assert(str_contains($goalsPage, 'class="goals-editor-modal"') && str_contains($goalsPage, 'class="goals-editor-close"') && str_contains($dashboardCss, '.goals-editor-modal') && str_contains($dashboardCss, 'position: fixed'), 'criação e edição de metas precisam usar modal com fechamento no topo.');

printf("✓ UI consolidation static: %d assertions\n", $checks);
