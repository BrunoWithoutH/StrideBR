<?php
$root = dirname(__DIR__, 2);
$page = file_get_contents($root . '/public/user/cronogramatreinos.php');
$css = file_get_contents($root . '/public/assets/css/cronogramas.css');
$ui = file_get_contents($root . '/public/assets/css/ui-refresh.css');
$js = file_get_contents($root . '/public/assets/js/cronogramas.js');
$pt = file_get_contents($root . '/src/i18n/pt-BR.php');
$en = file_get_contents($root . '/src/i18n/en.php');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "Falhou: {$message}\n");
        exit(1);
    }
};

$assert(!str_contains($page, "require dirname(__DIR__, 2) . '/src/layout/planning_week.php'"), 'Cronogramas não renderiza planning_week antes das views');
$assert(!str_contains($page, 'planejamentoSemana('), 'Cronogramas não executa resumo semanal completo redundante');
$assert(str_contains($page, 'class="schedule-side-panel"'), 'sidebar desktop permanece');
$assert(str_contains($page, 'data-week-summary'), 'sidebar preserva resumo semanal');
$assert(str_contains($page, 'data-week-summary-compact'), 'resumo semanal compacto existe para viewport sem sidebar');
$assert(str_contains($page, 'data-view-context="week"'), 'Semana possui contexto compacto próprio');
$assert(str_contains($page, 'data-week-context-summary'), 'Semana possui resumo compacto sem lista de ocorrências');
$assert(substr_count($page, 'data-calendar-view="week"') === 1, 'grade semanal continua sendo única view semanal principal');
$assert(substr_count($page, 'data-calendar-view="month"') === 1, 'calendário mensal continua único');
$assert(substr_count($page, 'data-calendar-view="agenda"') === 1, 'Lista continua única representação principal');
$assert(!str_contains($page, 'schedule-month-hint'), 'copy explicativa permanente do mês foi removida');
$assert(!str_contains($page, "stridebr_t('schedule.month_projection_help')"), 'copy de projeção mensal não é mais renderizada');
$assert(!str_contains($page, "stridebr_t('schedule.month_overlay_help')"), 'copy de overlay mensal não é mais renderizada');
$assert(str_contains($page, 'schedule-period-nav') && str_contains($page, "stridebr_t('schedule.previous_week')") && str_contains($page, "stridebr_t('schedule.next_week')"), 'Semana usa setas de navegação temporal');
$assert(str_contains($page, "stridebr_t('schedule.previous_month')") && str_contains($page, "stridebr_t('schedule.next_month')"), 'Mês preserva setas de navegação temporal');
$assert(str_contains($page, "stridebr_t('common.today')"), 'Mês usa ação Hoje do design system');
$assert(str_contains($page, "stridebr_t('schedule.this_week')"), 'Semana usa ação Esta semana');
$assert(substr_count($page, 'aria-pressed="<?php echo $initialView') === 3, 'view switcher informa seleção inicial por aria-pressed');
$assert(str_contains($page, "planning.status.shifted"), 'realizado em outra data permanece contextual no card semanal');

$monthPos = strpos($page, 'data-calendar-view="month"');
$agendaPos = strpos($page, 'data-calendar-view="agenda"');
$weekPos = strpos($page, 'data-calendar-view="week"');
$compactPos = strpos($page, 'data-week-summary-compact');
$assert($compactPos !== false && $weekPos !== false && $monthPos !== false && $agendaPos !== false && $compactPos < $weekPos && $weekPos < $monthPos && $monthPos < $agendaPos, 'ordem estrutural é resumo auxiliar, Semana, Mês e Lista sem bloco completo intermediário');

$assert(str_contains($css, '.schedule-period-toolbar'), 'navegação de período compartilha estilo entre Mês e Semana');
$assert(str_contains($css, '.schedule-period-arrow:focus-visible'), 'setas possuem foco visível');
$assert(str_contains($css, '.schedule-week-context-summary'), 'resumo da Semana é compacto');
$assert(str_contains($css, '.schedule-compact-week-summary') && str_contains($css, '@media (max-width: 1100px)'), 'resumo compacto acompanha breakpoint em que sidebar desaparece');
$assert(str_contains($css, '.schedule-body[data-schedule-view="week"] .schedule-compact-week-summary'), 'Semana não duplica resumo mobile fora do próprio header');
$assert(str_contains($ui, '@media (max-width: 1100px)') && str_contains($ui, '.schedule-side-panel') && str_contains($ui, 'display: none;'), 'sidebar desktop continua desaparecendo no breakpoint existente');
$assert(str_contains($css, '.view-switch .view-button.is-active') && str_contains($css, 'box-shadow: inset 0 -2px 0 var(--ui-accent)'), 'view ativa tem indicação além de borda');

$assert(str_contains($js, "document.querySelectorAll('[data-view-context]').forEach(context =>"), 'JS consulta o contexto atual da view ao alternar');
$assert(str_contains($js, 'document.body.dataset.scheduleView = name'), 'JS sincroniza view no body para responsividade');
$assert(str_contains($js, "button.setAttribute('aria-pressed', active ? 'true' : 'false')"), 'JS sincroniza aria-pressed do switcher');
$assert(str_contains($js, "context.hidden = context.dataset.viewContext !== name"), 'JS alterna contexto semanal junto da view');
$assert(str_contains($js, 'const weekStatsFromCards = () =>'), 'resumo dinâmico reutiliza cards derivados das ocorrências');
$assert(str_contains($js, 'syncCompactWeekSummaries'), 'resumos compactos são sincronizados após mudanças locais');
$assert(str_contains($js, "occurrence.querySelector('[data-card-time]')"), 'troca de próximo treino usa seletor real do horário do card');

foreach (['schedule.week_navigation', 'schedule.previous_week', 'schedule.next_week', 'schedule.week_progress_compact', 'schedule.week_pending_compact.one', 'schedule.week_pending_compact.other', 'schedule.next_workout_short'] as $key) {
    $assert(str_contains($pt, "'{$key}' =>"), "PT-BR contém {$key}");
    $assert(str_contains($en, "'{$key}' =>"), "EN contém {$key}");
}
foreach (['schedule.month_projection_help', 'schedule.month_overlay_help', 'schedule.go_today'] as $key) {
    $assert(!str_contains($pt, "'{$key}' =>"), "PT-BR remove key sem uso {$key}");
    $assert(!str_contains($en, "'{$key}' =>"), "EN remove key sem uso {$key}");
}

printf("✓ schedule hierarchy static: %d assertions\n", $assertions);
