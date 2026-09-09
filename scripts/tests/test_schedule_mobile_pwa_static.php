<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$cronCss = $read('public/assets/css/cronogramas.css');
$uiCss = $read('public/assets/css/ui-refresh.css');
$cronJs = $read('public/assets/js/cronogramas.js');
$pwaJs = $read('public/assets/js/pwa.js');
$agenda = $read('public/user/agenda-mensal.php');
$pt = require $root . '/src/i18n/pt-BR.php';
$en = require $root . '/src/i18n/en.php';

$checks = [
    'mobile weekly calendar drops viewport height cap' => str_contains($cronCss, '.calendar-view[data-calendar-view="week"] {') && str_contains($cronCss, 'height:auto; min-height:0; max-height:none;'),
    'mobile weekly calendar has no vertical auto scroll' => str_contains($cronCss, 'overflow-x:auto; overflow-y:hidden;') && !str_contains($cronCss, 'height:62dvh; min-height:340px; max-height:640px; overflow:auto;'),
    'mobile weekly calendar preserves horizontal overscroll containment only' => str_contains($cronCss, 'overscroll-behavior-x:contain; overscroll-behavior-y:auto;'),
    'mobile week does not programmatically create vertical scroll position' => str_contains($cronJs, 'if (!isMobileWeek()) {') && str_contains($cronJs, 'calendar.scrollTop = targetHour * calendarHourHeight;'),
    'desktop weekly calendar keeps bounded internal vertical scroll' => str_contains($cronCss, 'height: clamp(470px, 67dvh, 740px);') && str_contains($cronCss, 'overflow-y: auto;'),
    'desktop month can use bounded internal scroll' => str_contains($cronCss, '.schedule-month-calendar-wrap {') && str_contains($cronCss, 'max-height: clamp(500px, 68dvh, 760px);'),
    'mobile month returns to natural page height' => str_contains($cronCss, '.schedule-month-calendar-wrap { height:auto; min-height:0; max-height:none; }') && str_contains($cronCss, 'overflow-x:auto; overflow-y:visible;'),
    'schedule month cells are compact' => str_contains($uiCss, '.schedule-month-calendar .monthly-day {') && str_contains($uiCss, 'min-height: 94px !important;'),
    'generic monthly cells are compact' => str_contains($uiCss, '.monthly-day { min-height: 104px; padding: 5px;'),
    'month recurrence no longer renders focus in compact card' => !preg_match('/occurrenceMarkup[\s\S]{0,4500}item\.foco/', $cronJs),
    'month schedule no longer renders author/exercise totals in compact card' => !preg_match('/scheduledMarkup[\s\S]{0,2500}(criador_nome|exercicios_total)/', $cronJs),
    'month cards separate time title and status' => str_contains($cronJs, 'schedule-month-event-meta') && str_contains($cronJs, 'schedule-month-event-title') && str_contains($cronJs, 'schedule-month-event-status'),
    'special shifted state remains available in compact month status' => str_contains($cronJs, "item.realizado_fora_planejado ? 'shifted' : 'completed'"),
    'agenda monthly hides detail actions in contextual menu' => str_contains($agenda, 'class="monthly-event-more"') && str_contains($agenda, 'class="monthly-event-more-menu"'),
    'agenda shifted planned date remains available in contextual menu' => str_contains($agenda, 'class="monthly-plan-note"') && str_contains($agenda, "stridebr_t('agenda.adjust_dates')"),
    'agenda monthly removes focus from always-visible recurring card' => !str_contains(substr($agenda, strpos($agenda, '<div class="monthly-event is-recurring'), 9000), "!empty(\$workout['foco'])"),
    'quick create header uses theme tokens' => str_contains($cronCss, 'background: var(--ui-panel-soft);') && str_contains($cronCss, 'border-bottom: 1px solid var(--ui-border-soft);'),
    'quick create controls use theme tokens' => str_contains($cronCss, '.calendar-quick-create :is(input,select,textarea){border-color:var(--ui-border);background:var(--ui-panel);color:var(--ui-text-strong)}'),
    'quick create dark mode has explicit token-safe coverage' => str_contains($uiCss, ':root[data-theme="dark"] .calendar-quick-create-popover'),
    'quick create keeps time fields two-column on small phones' => !preg_match('/@media \(max-width: 430px\)[\s\S]{0,300}\.quick-create-time-row[^\{]*\{[^\}]*grid-template-columns:\s*1fr/', $cronCss),
    'PWA hint listens to beforeinstallprompt' => str_contains($pwaJs, "window.addEventListener('beforeinstallprompt'"),
    'PWA hint never auto-prompts' => str_contains($pwaJs, "shell.querySelector('[data-pwa-install]')?.addEventListener('click'") && !preg_match('/beforeinstallprompt[\s\S]{0,500}\.prompt\(\)/', $pwaJs),
    'PWA hint handles iOS human instruction' => str_contains($pwaJs, 'pwa.ios_instruction') && str_contains($pwaJs, 'isIos()'),
    'PWA hint suppresses standalone' => str_contains($pwaJs, 'if (isStandalone()) return false'),
    'PWA hint suppresses active workout and modal states' => str_contains($pwaJs, ".global-tools.has-active-workout") && str_contains($pwaJs, "[aria-modal=\"true\"]:not([hidden])"),
    'PWA hint suppresses auth onboarding and GPS recording pages' => str_contains($pwaJs, 'suppressedPage') && str_contains($pwaJs, '/user/onboarding.php') && str_contains($pwaJs, '/user/gravar-atividade.php'),
    'PWA hint dismisses for a bounded period' => str_contains($pwaJs, 'const DISMISS_MS = 30 * 24 * 60 * 60 * 1000'),
    'PWA hint uses appinstalled cleanup' => str_contains($pwaJs, "window.addEventListener('appinstalled'"),
    'PWA hint CSS is mobile-only and respects standalone/modal suppression' => str_contains($uiCss, '.pwa-install-hint-shell.is-visible') && str_contains($uiCss, 'html.is-standalone .pwa-install-hint-shell') && str_contains($uiCss, 'html.calendar-quick-open .pwa-install-hint-shell'),
    'PT-BR PWA copy is human' => ($pt['pwa.add_to_home'] ?? '') === 'Adicionar à tela inicial' && !str_contains(strtolower((string)($pt['pwa.add_to_home_help'] ?? '')), 'pwa'),
    'EN PWA copy parity exists' => isset($en['pwa.add_to_home'], $en['pwa.add_to_home_help'], $en['pwa.ios_instruction']),
];

$failed = [];
foreach ($checks as $label => $ok) if (!$ok) $failed[] = $label;
if ($failed !== []) {
    fwrite(STDERR, "Falhas no acabamento mobile/PWA:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo '✓ Mobile/PWA schedule polish static: ' . count($checks) . " assertions\n";
