<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) {
        fwrite(STDERR, "Pacer P0 + Activity Detail Density failed: {$message}\n");
        exit(1);
    }
};

$pacer = $read('public/user/pacer.php');
$detailJs = $read('public/assets/js/activity-detail-v3.js');
$activitiesJs = $read('public/assets/js/atividades.js');
$activitiesCss = $read('public/assets/css/atividades.css');
$detailCss = $read('public/assets/css/activity-detail-v3.css');
$page = $read('public/user/atividades.php');

$assert(str_contains($pacer, "\$requestedStatus = strtolower(trim((string) (\$_GET['status'] ?? 'active')));"), 'Pacer precisa normalizar status uma única vez com fallback active.');
$assert(str_contains($pacer, "in_array(\$requestedStatus, ['active', 'archived'], true) ? \$requestedStatus : 'active'"), 'Pacer precisa aceitar apenas active/archived e fazer fallback para active.');
$assert(str_contains($pacer, "\$alternateStatus = \$status === 'active' ? 'archived' : 'active';"), 'Consulta alternativa precisa derivar do status normalizado.');
$assert(!str_contains($pacer, "? (string) \$_GET['status']"), 'Pacer não pode acessar status opcional novamente no branch verdadeiro.');
$assert(substr_count($pacer, "\$_GET['status']") === 1, 'status opcional deve ser lido de $_GET somente uma vez.');

$normalize = static function (?string $raw): string {
    $requestedStatus = strtolower(trim((string) ($raw ?? 'active')));
    return in_array($requestedStatus, ['active', 'archived'], true) ? $requestedStatus : 'active';
};
foreach ([null => 'active', 'active' => 'active', 'archived' => 'archived', 'banana' => 'active', '' => 'active'] as $input => $expected) {
    $raw = $input === '' ? '' : ($input === null ? null : (string) $input);
    $assert($normalize($raw) === $expected, 'normalização de status precisa cobrir ausência, active, archived, invalid e vazio.');
}

$assert(!str_contains($page, 'data-detail-visibility'), 'Privacidade não pode ser duplicada no kicker e nos badges.');
$assert(str_contains($activitiesJs, "gps:'GPS do app'") && str_contains($activitiesJs, "manual:'Registro manual'") && str_contains($detailJs, "gps:'GPS do app'") && str_contains($detailJs, "manual:'Registro manual'"), 'Origem precisa usar labels de produto no header e no resumo.');
$assert(str_contains($detailJs, "tabs.length > 1 ? `<nav class=\"activity-v3-tabs\""), 'Tab bar deve existir somente quando houver mais de uma capability/tab.');
$assert(str_contains($detailJs, "tabs.length === 1 ? ' has-single-tab' : ''"), 'Detail precisa marcar o estado sem navegação quando só existe Resumo.');
$assert(str_contains($detailJs, "contextRail = infoRows(activity).length >= 2") && str_contains($detailJs, "is-rail-worthy") && str_contains($detailJs, "is-compact"), 'Mapa/contexto precisa escolher layout conforme quantidade real de contexto.');
$assert(str_contains($detailCss, '.activity-v3-route.has-context-rail') && str_contains($detailCss, '.activity-v3-context.is-rail-worthy'), 'Grid 8/4 só pode ser aplicado quando contexto é realmente útil.');
$assert(str_contains($detailCss, 'height:clamp(300px,30vw,440px)'), 'Mapa full detail precisa ser mais denso sem perder responsividade.');
$assert(str_contains($activitiesCss, 'repeat(auto-fit,minmax(min(130px,100%),180px))'), 'Métricas principais precisam ter largura fluida e limitada no desktop.');
$assert(str_contains($activitiesJs, '<span><strong>${escapeHtml(item.valor)}</strong><b>${escapeHtml(item.rotulo)}</b></span>'), 'Faixa principal deve priorizar valor e usar label secundário.');
$assert(str_contains($detailCss, '.activity-detail-page .activity-v3-section{border:0;border-top:1px solid var(--ui-border-soft)'), 'Sections do full detail devem evitar card dentro de card.');
$assert(str_contains($detailCss, 'grid-template-columns:minmax(160px,1.4fr) minmax(0,2.6fr)'), 'Trechos precisam usar colunas fluidas e compactas.');
$assert(str_contains($detailJs, "label:'Elevação'") && str_contains($detailJs, 'const routeProfileHtml'), 'Detail precisa mostrar elevação quando a rota ou stream fornecer dados válidos.');
$assert(str_contains($activitiesJs, "activitiesHistoryView.hidden = detailExpanded") && str_contains($activitiesJs, "activityDetailView.hidden = !detailExpanded"), 'Main Takeover precisa permanecer intacto.');
$assert(str_contains($activitiesCss, 'var(--ui-border)') && str_contains($detailCss, 'var(--ui-border-soft)'), 'Polish precisa continuar baseado em tokens para light/dark.');

printf("✓ Pacer P0 + Activity Detail Density: %d assertions\n", $count);
