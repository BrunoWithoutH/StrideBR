<?php
$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path);
$assertions = 0;
$assert = static function (bool $ok, string $message) use (&$assertions): void {
    $assertions++;
    if (!$ok) {
        fwrite(STDERR, "Training Planning UX Polish V6.1: {$message}\n");
        exit(1);
    }
};

$home = $read('public/home.php');
$dashboard = $read('src/function/dashboard.php');
$dashboardCss = $read('public/assets/css/dashboard-v2.css');
$assert(str_contains($dashboard, 'function dashboardTreinoSeguinteContexto'), 'following-workout selector missing');
$assert(str_contains($dashboard, "['idtreino'] ?? '') . ':' . (string) (\$row['data_original'] ?? '')"), 'following identity must use workout plus original date');
$assert(str_contains($home, '$followingWorkout = dashboardTreinoSeguinteContexto'), 'Home must derive follow-up from the existing upcoming list');
$assert(str_contains($home, 'dashboard-follow-up'), 'rest state follow-up context missing');
$assert(!str_contains($home, '<span class="dashboard-eyebrow">Amanhã</span>'), 'Tomorrow copy must not stay hardcoded');
$assert(str_contains($home, "stridebr_t('home.last_28_days')"), '28-day copy must be localized');
$assert(str_contains($home, "stridebr_t('home.view_progress')"), 'progress action copy must be localized');
$assert(str_contains($home, 'dashboard-period-action'), 'progress action needs compact control affordance');
$assert(str_contains($home, 'dashboard-goal-empty-action'), 'new-goal empty action needs explicit button affordance');
$assert(!str_contains($home, 'class="dashboard-link-button" type="button" data-goal-open'), 'new-goal action must not use low-contrast link styling');
$assert(str_contains($dashboardCss, '.dashboard-follow-up{'), 'follow-up context styling missing');
$assert(str_contains($dashboardCss, '.dashboard-period-action{'), 'progress control styling missing');
$assert(str_contains($dashboardCss, '.dashboard-goal-empty-action{'), 'new-goal contrast styling missing');
$assert(str_contains($dashboardCss, 'color:var(--ui-text)'), 'dashboard actions must use semantic text token');
$assert(str_contains($dashboardCss, ':focus-visible'), 'dashboard actions need visible keyboard focus');

$page = $read('public/user/cronogramatreinos.php');
$js = $read('public/assets/js/cronogramas.js');
$css = $read('public/assets/css/cronogramas.css');
$api = $read('public/api/cronograma-ocorrencias.php');
$assert(str_contains($page, 'data-schedule-agenda-timeline'), 'agenda timeline root missing');
$assert(str_contains($page, 'data-schedule-id='), 'agenda timeline must carry selected schedule identity');
$assert(str_contains($page, 'data-initial-date='), 'agenda timeline must preserve date context');
$assert(!str_contains($page, 'array_filter($treinosVigentes'), 'agenda must not render weekly templates');
$assert(!str_contains($page, '$dayWorkouts = array_values(array_filter($treinosVigentes'), 'legacy weekday-template agenda loop remains');
$assert(str_contains($js, '/api/cronograma-ocorrencias.php?start='), 'timeline must reuse occurrence API');
$assert(str_contains($js, 'data.ocorrencias || []'), 'timeline must consume recurring occurrences');
$assert(str_contains($js, 'data.agendados || []'), 'timeline must merge scheduled workouts');
$assert(str_contains($js, 'const agendaMonths = new Map()'), 'loaded month registry missing');
$assert(str_contains($js, 'const agendaRequests = new Map()'), 'in-flight month registry missing');
$assert(str_contains($js, 'new IntersectionObserver'), 'automatic progressive loading missing');
$assert(str_contains($js, 'new AbortController()'), 'timeline fetch cancellation missing');
$assert(str_contains($js, 'generation !== agendaGeneration'), 'stale response generation guard missing');
$assert(str_contains($js, "schedule !== (agendaRoot.dataset.scheduleId || '')"), 'stale schedule response guard missing');
$assert(str_contains($js, 'agendaRequests.has(key)'), 'duplicate month requests must be blocked');
$assert(str_contains($js, 'agendaMaxMonths = 12'), 'timeline DOM safety cap missing');
$assert(str_contains($js, "[data-print-schedule]')?.addEventListener('click', async") && str_contains($js, 'await agendaFetchMonth(key)'), 'print must wait for the asynchronous agenda chunk');
$assert(str_contains($js, 'data-agenda-load-previous'), 'previous-days control missing');
$assert(str_contains($js, 'data-agenda-load-more'), 'load-more fallback missing');
$assert(str_contains($js, 'data-agenda-retry'), 'chunk retry control missing');
$assert(str_contains($js, 'schedule-agenda-empty-day'), 'real empty days must render compactly');
$assert(str_contains($js, 'agendaWeekStart'), 'timeline week grouping missing');
$assert(str_contains($js, "openQuickCreate({mode:'schedule', date:add.dataset.agendaAddDate"), 'empty-day add action must prefill the real date');
$agendaOccurrenceStart = strpos($js, 'const agendaOccurrenceMarkup');
$agendaScheduledStart = strpos($js, 'const agendaScheduledMarkup', $agendaOccurrenceStart);
$agendaOccurrence = $agendaOccurrenceStart !== false && $agendaScheduledStart !== false ? substr($js, $agendaOccurrenceStart, $agendaScheduledStart - $agendaOccurrenceStart) : '';
$assert($agendaOccurrence !== '', 'agenda occurrence renderer missing');
$assert(!str_contains($agendaOccurrence, "'missed'"), 'timeline must not auto-label historical pending workouts as missed');
$assert(str_contains($agendaOccurrence, "item.data_original !== item.data_treino"), 'moved occurrence metadata missing');
$assert(str_contains($api, "'ocorrencias' => \$output, 'agendados' => \$scheduledOutput"), 'occurrence API must continue returning both timeline sources');
$assert(str_contains($css, '.schedule-agenda-timeline{'), 'timeline owner CSS missing');
$assert(str_contains($css, '.schedule-agenda-day{'), 'real-day layout missing');
$assert(str_contains($css, '.schedule-agenda-occurrence{'), 'occurrence row styling missing');
$assert(str_contains($css, '.schedule-agenda-empty-day{'), 'empty-day styling missing');
$assert(str_contains($css, '@media(max-width:760px)') && str_contains($css, '.schedule-agenda-day{grid-template-columns:1fr;'), 'mobile timeline stack missing');
$assert(!str_contains($css, 'min-width:760px') || str_contains($css, '.schedule-agenda-timeline'), 'timeline must not depend on desktop horizontal width');

$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');
foreach (['home.following_workout','home.last_28_days','home.view_progress','schedule.agenda_load_previous','schedule.agenda_load_more','schedule.agenda_load_error','schedule.agenda_no_workout','schedule.agenda_week'] as $key) {
    $assert(str_contains($pt, "'{$key}' =>"), "PT key {$key} missing");
    $assert(str_contains($en, "'{$key}' =>"), "EN key {$key} missing");
}

echo "✓ Training Planning UX Polish V6.1 static: {$assertions} assertions\n";
