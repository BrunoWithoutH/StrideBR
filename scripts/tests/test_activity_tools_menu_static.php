<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$page = $read('public/user/atividades.php');
$js = $read('public/assets/js/scripts.js');
$activityJs = $read('public/assets/js/atividades.js');
$css = $read('public/assets/css/atividades.css');
$browser = $read('scripts/tests/browser_activity_history_detail.py');
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) { fwrite(STDERR, "Activity tools menu failed: {$message}\n"); exit(1); }
};
$assert(str_contains($page, 'class="activity-toolbar-tools" data-ui-menu="toggle"'), 'Ferramentas deve usar o primitive compartilhado de dropdown.');
$assert(str_contains($js, "document.querySelectorAll('[data-header-menu], [data-ui-menu]')"), 'Controller deve tratar menus globais e locais com uma única coleção.');
$assert(str_contains($js, "[data-ui-menu=\"toggle\"]"), 'Ferramentas deve receber toggle controlado.');
$assert(str_contains($js, 'event.preventDefault()') && str_contains($js, 'const shouldOpen = !details.open'), 'Segundo clique deve fechar sem depender do toggle nativo.');
$assert(str_contains($js, 'if (details !== current) closeDetailsMenu(details)'), 'Abrir um menu deve fechar os concorrentes.');
$assert(str_contains($js, "if (event.key !== 'Escape' || !details.open) return") && str_contains($js, 'summary?.focus()'), 'Escape deve fechar e devolver foco.');
$assert(str_contains($js, 'aria-expanded') && str_contains($js, 'details.contains(target)'), 'ARIA e click-outside devem ser sincronizados pelo controller.');
$assert(str_contains($js, '[data-ui-menu] a, [data-ui-menu] button'), 'Clique em item do Ferramentas deve fechar o menu.');
$assert(str_contains($css, '.activity-toolbar-tools[open]{z-index:var(--z-header-menu)}'), 'Ancestral do menu precisa subir acima do drawer.');
$assert(str_contains($css, '.activity-toolbar-tools-menu{') && str_contains($css, 'z-index:var(--z-header-menu)'), 'Painel Ferramentas deve usar a camada canônica de menu interativo.');
$assert(!str_contains($css, 'z-index:999999'), 'Correção não pode usar z-index arbitrário.');
$assert(!str_contains($css, '--activity-detail-available-height') && !str_contains($activityJs, 'syncDesktopDetailViewport'), 'Preview desktop não deve depender de altura calculada pela viewport.');
$assert((bool) preg_match('/@media \(min-width:901px\)\{.*?\.activity-detail-drawer\{.*?position:sticky;.*?max-height:calc\(100dvh - 24px\);.*?\.activity-detail-panel\{.*?max-height:calc\(100dvh - 24px\);.*?overflow-y:auto;.*?overscroll-behavior-y:auto;/s', $css), 'Preview desktop deve manter scroll interno com chaining nativo e sem body lock.');
$assert(str_contains($page, 'class="activity-detail-panel" role="region"') && !str_contains($activityJs, "detailPanel?.setAttribute('aria-modal'"), 'Activity Detail deve permanecer região da página e nunca alternar aria-modal.');
$assert(str_contains($browser, "public/assets/js/scripts.js") && str_contains($browser, "ui-modal-scroll-locked"), 'Browser regression deve carregar o lock modal global e garantir que preview desktop não bloqueia a página mãe.');
$assert(str_contains($page, 'data-activities-history-view') && str_contains($page, 'data-activity-detail-view') && str_contains($activityJs, 'activityDetailView') && str_contains($css, '.activity-detail-page>.activity-detail-drawer.is-expanded-detail'), 'Detalhe expandido deve assumir a main e sair do workspace de History.');
$assert(str_contains($css, '@media(max-width:900px)') && str_contains($css, 'html.activity-detail-open body{overflow:hidden!important}'), 'Drawer mobile deve manter body lock próprio.');
$assert(str_contains($browser, "'style.css'") && str_contains($browser, "'atividades.css'") && str_contains($browser, "'ui-refresh.css'") && str_contains($browser, "'activity-sharing.css'"), 'Browser regression deve carregar a pilha CSS real da tela de Atividades.');
echo "Activity tools menu static passed ({$checks} assertions).\n";
