<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$page = $read('public/user/atividades.php');
$js = $read('public/assets/js/atividades.js');
$css = $read('public/assets/css/atividades.css');
$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) {
        fwrite(STDERR, "Activity Detail Main Takeover failed: {$message}\n");
        exit(1);
    }
};

$historyPos = strpos($page, 'data-activities-history-view');
$detailPos = strpos($page, 'data-activity-detail-view');
$workspacePos = strpos($page, 'data-activity-history-workspace');
$previewHostPos = strpos($page, 'data-activity-detail-preview-host');
$drawerPos = strpos($page, 'data-activity-detail-drawer');

$assert($historyPos !== false && $detailPos !== false && $detailPos > $historyPos, 'History e Detail precisam existir como surfaces irmãs na main.');
$assert($workspacePos !== false && $previewHostPos !== false && $drawerPos !== false && $drawerPos > $previewHostPos, 'Preview precisa manter um host próprio dentro do History.');
$assert(str_contains($js, "targetHost = detailExpanded ? activityDetailView : detailPreviewHost"), 'A mesma surface de detalhe precisa migrar entre preview e full-page host.');
$assert(str_contains($js, "activitiesHistoryView.hidden = detailExpanded") && str_contains($js, "activityDetailView.hidden = !detailExpanded"), 'Detail mode precisa ocultar todo o History e mostrar apenas o Detail.');
$assert(str_contains($js, "activitiesMain?.classList.toggle('is-detail-mode', detailExpanded)"), 'A main precisa possuir estado explícito de detail mode.');
$assert(str_contains($css, '.activities-page.is-detail-mode') && str_contains($css, '1560px'), 'Detail mode precisa ganhar largura útil maior sem usar 100vw.');
$assert(str_contains($css, '.activity-detail-page>.activity-detail-drawer.is-expanded-detail') && str_contains($css, 'border:0!important') && str_contains($css, 'box-shadow:none!important'), 'Full Detail não pode parecer um card/modal gigante.');
$assert(str_contains($css, '[data-activity-v3-panel="summary"]>.activity-v3-route.has-context-rail{grid-column:span 8}') && str_contains($css, 'grid-template-columns:repeat(12,minmax(0,1fr))'), 'Resumo desktop precisa poder usar composição 8/4 em grid de 12 colunas.');
$assert(str_contains($css, '.activity-detail-page .activity-v3-map{height:clamp(300px,30vw,440px)}'), 'Mapa full-page precisa usar altura densa e controlada.');
$assert(str_contains($css, '.activity-detail-header-menu>div{display:flex!important;flex-direction:column!important'), 'Menu de ações precisa empilhar os itens em rows próprias.');
$assert(str_contains($css, '.activity-detail-header-menu>div>a,.activity-detail-header-menu>div>button') && str_contains($css, 'min-height:36px!important'), 'Cada item do menu precisa ter row clicável própria.');
$assert(str_contains($css, '.activity-detail-header-menu>div>.is-danger') && str_contains($css, 'border-top:1px solid var(--ui-border-soft)!important'), 'Excluir precisa ficar separado visualmente como danger.');
$assert(str_contains($js, "history.pushState") && str_contains($js, "window.addEventListener('popstate', event => syncActivityDetailFromLocation(event.state))"), 'Main takeover precisa preservar History API e popstate.');
$assert(str_contains($js, 'state?.stridebrActivityDetail') && str_contains($js, 'activityId'), 'Back/Forward deve restaurar Detail também pelo state da History API.');
$assert(str_contains($css, '.activity-detail-page .activity-v3-unit-list article') && str_contains($css, 'minmax(420px,2fr)'), 'Trechos precisam aproveitar a largura extra em rows compactas.');
$assert(!str_contains($css, '.activity-detail-page{width:100vw'), 'Detail não pode usar 100vw e provocar overflow global.');

printf("✓ Activity Detail Main Takeover static: %d assertions\n", $count);
