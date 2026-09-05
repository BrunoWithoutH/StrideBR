<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/includes/app.php';

$assertions = 0;
$ok = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "✗ i18n coverage: {$message}\n");
        exit(1);
    }
};

$pt = require $root . '/src/i18n/pt-BR.php';
$en = require $root . '/src/i18n/en.php';
$ptKeys = array_keys($pt);
$enKeys = array_keys($en);
sort($ptKeys);
sort($enKeys);
$ok($ptKeys === $enKeys, 'dicionários possuem as mesmas chaves');
$ok(array_filter($pt, static fn(mixed $value): bool => trim((string) $value) === '') === [], 'PT-BR não possui valores vazios');
$ok(array_filter($en, static fn(mixed $value): bool => trim((string) $value) === '') === [], 'English não possui valores vazios');

$ok(stridebr_weekday_name('2026-09-03', 'pt-BR') === 'quinta-feira', 'weekday completo PT-BR');
$ok(stridebr_weekday_name('2026-09-03', 'en') === 'Thursday', 'weekday completo English');
$ok(stridebr_weekday_short('2026-09-04', 'pt-BR') === 'sex', 'weekday curto PT-BR');
$ok(stridebr_weekday_short('2026-09-04', 'en') === 'Fri', 'weekday curto English');
$ok(stridebr_month_name(9, 'pt-BR') === 'setembro', 'mês PT-BR');
$ok(stridebr_month_name(9, 'en') === 'September', 'mês English');
$ok(stridebr_format_month_year('2026-09-03', 'pt-BR') === 'setembro de 2026', 'mês + ano PT-BR');
$ok(stridebr_format_month_year('2026-09-03', 'en') === 'September 2026', 'mês + ano English');
$ok(stridebr_format_date_weekday('2026-09-03', 'pt-BR') === 'quinta-feira, 03/09', 'data com weekday PT-BR');
$ok(stridebr_format_date_weekday('2026-09-03', 'en') === 'Thursday, Sep 3', 'data com weekday English');
$ok(stridebr_format_number(6.4, 1, false, 'pt-BR') === '6,4', 'número PT-BR');
$ok(stridebr_format_number(6.4, 1, false, 'en') === '6.4', 'número English');
$ok(stridebr_format_number(5.055, 3, false, 'pt-BR') === '5,055', 'precisão numérica PT-BR');
$ok(stridebr_format_number(5.055, 3, false, 'en') === '5.055', 'precisão numérica English');
$ok(stridebr_tn_locale('pt-BR', 'home.activities_week.one', 'home.activities_week.other', 1) === '1 atividade nesta semana', 'plural one PT-BR');
$ok(stridebr_tn_locale('pt-BR', 'home.activities_week.one', 'home.activities_week.other', 2) === '2 atividades nesta semana', 'plural other PT-BR');
$ok(stridebr_tn_locale('en', 'home.activities_week.one', 'home.activities_week.other', 1) === '1 activity this week', 'plural one English');
$ok(stridebr_tn_locale('en', 'home.activities_week.one', 'home.activities_week.other', 2) === '2 activities this week', 'plural other English');
$ok(stridebr_sport_name('corrida', null, 'pt-BR') === 'Corrida', 'corrida PT-BR');
$ok(stridebr_sport_name('corrida', null, 'en') === 'Running', 'corrida English');
$ok(stridebr_sport_name('musculacao', null, 'pt-BR') === 'Musculação', 'musculação PT-BR');
$ok(stridebr_sport_name('musculacao', null, 'en') === 'Strength training', 'musculação English');
$ok(stridebr_auto_activity_title('corrida', '2026-09-03 03:00:00', null, 'pt-BR') === 'Corrida de madrugada', 'título automático PT-BR');
$ok(stridebr_auto_activity_title('corrida', '2026-09-03 03:00:00', null, 'en') === 'Early-morning run', 'título automático English');
$ok(stridebr_auto_activity_title('corrida', '2026-09-03 08:00:00', null, 'en') === 'Morning run', 'título automático manhã English');
$ok(stridebr_present_activity_title('Corrida de madrugada', 'corrida', 'en') === 'Early-morning run', 'título automático antigo traduzido somente na apresentação');
$ok(stridebr_present_activity_title('Corrida com o Boligon', 'corrida', 'en') === 'Corrida com o Boligon', 'título manual preservado');

$pages = [
    'public/home.php' => ['home.page_title', 'home.open_schedule', 'home.recent_activities'],
    'public/user/cronogramatreinos.php' => ['schedule.page_title', 'schedule.library', 'schedule.open_month'],
    'public/user/agenda-mensal.php' => ['agenda.page_title'],
    'public/user/biblioteca.php' => ['library.page_title'],
    'public/user/atividades.php' => ['activity.page_title', 'activity.history'],
    'public/user/progresso.php' => ['progress.page_title'],
    'public/user/metas.php' => ['goals.page_title'],
    'public/calendario.php' => ['events.page_title'],
    'public/user/perfil.php' => ['profile.sports'],
    'public/user/settings.php' => ['settings.personalization'],
    'public/user/account.php' => ['account.page_title'],
];
foreach ($pages as $path => $keys) {
    $content = (string) file_get_contents($root . '/' . $path);
    foreach ($keys as $key) $ok(str_contains($content, "stridebr_t('{$key}'") || str_contains($content, "stridebr_t(\"{$key}\""), "{$path} usa {$key}");
}


$quickToolsLayout = (string) file_get_contents($root . '/src/layout/quick_tools.php');
$quickToolsJs = (string) file_get_contents($root . '/public/assets/js/quick-tools.js');
$workoutSessionJs = (string) file_get_contents($root . '/public/assets/js/workout-session.js');
$footer = (string) file_get_contents($root . '/src/layout/footer.php');
$ok(str_contains($quickToolsLayout, "stridebr_t('quick_tools.timer_help')") && str_contains($quickToolsLayout, "stridebr_t('workout_session.how_was')"), 'ferramentas rápidas usam chaves no server-rendered');
$ok(str_contains($quickToolsJs, "const t = (key, values = {}, fallback = key) => window.StrideBRI18n") && str_contains($quickToolsJs, "t('quick_tools.running'"), 'ferramentas rápidas usam API semântica por chave');
$ok(str_contains($workoutSessionJs, "t('workout_session.reopen_exercise'") && str_contains($workoutSessionJs, "t('workout_session.repetitions'"), 'sessão ativa localiza labels dinâmicos');
$ok(str_contains($footer, 'stridebr_i18n_runtime_script(false)') && strpos($footer, 'stridebr_i18n_runtime_script(false)') < strpos($footer, '/assets/js/quick-tools.js'), 'runtime i18n carrega antes dos consumidores globais');

$globalJs = (string) file_get_contents($root . '/public/assets/js/scripts.js');
$libraryJs = (string) file_get_contents($root . '/public/assets/js/library.js');
$exchangeJs = (string) file_get_contents($root . '/public/assets/js/activity-exchange.js');
$activitiesJs = (string) file_get_contents($root . '/public/assets/js/atividades.js');
$progressJs = (string) file_get_contents($root . '/public/assets/js/progresso.js');
$scheduleJs = (string) file_get_contents($root . '/public/assets/js/cronogramas.js');
$dashboardJs = (string) file_get_contents($root . '/public/assets/js/dashboard.js');
$goalsJs = (string) file_get_contents($root . '/public/assets/js/goals.js');
$gpsRecorderJs = (string) file_get_contents($root . '/public/assets/js/gps-recorder.js');
$supportProjectJs = (string) file_get_contents($root . '/public/assets/js/support-project.js');
$agendaJs = (string) file_get_contents($root . '/public/assets/js/agenda-mensal.js');
$ok(str_contains($globalJs, 'const stridebrCommonT =') && str_contains($globalJs, "stridebrCommonT('common.confirm'"), 'scripts.js possui helper i18n acessível aos módulos globais');
$ok(!str_contains($globalJs, "'Fechar aviso'") && !str_contains($globalJs, "'Link copiado'"), 'scripts.js não mantém hardcodes globais conhecidos');
$ok(str_contains($libraryJs, 'const t = (key, values = {}, fallback = key) => window.StrideBRI18n') && str_contains($libraryJs, "t('library.edit_workout'"), 'Biblioteca possui helper local e títulos semânticos');
$ok(str_contains($exchangeJs, "t('exchange.loading_activities'") && str_contains($exchangeJs, "tn('exchange.imported_notice.one'"), 'Importação usa chaves semânticas nos estados dinâmicos');
$ok(!str_contains($exchangeJs, "'Carregando atividades…'") && !str_contains($exchangeJs, "'Adicionar rota'") && !str_contains($exchangeJs, "'Atividade importada'"), 'Importação não mantém hardcodes PT-BR conhecidos');
$ok(str_contains($activitiesJs, "tr('activity.share.loading_photo'") && str_contains($activitiesJs, "trn('activity.history.deleted.one'"), 'Atividades localiza share e ações em lote dinâmicas');
$ok(str_contains($progressJs, "t('progress.incomplete_response'"), 'Progresso localiza resposta parcial inválida');
$ok(str_contains($scheduleJs, "tr('schedule.imported_fallback')") && str_contains($scheduleJs, "tr('schedule.workout_updated_success')") && str_contains($scheduleJs, "tr('schedule.plan_actual_fixed_success')"), 'Cronogramas localiza fallbacks e toasts dinâmicos');
$ok(!str_contains($scheduleJs, "new Intl.DateTimeFormat('pt-BR'") && !str_contains($scheduleJs, "toLocaleLowerCase('pt-BR')") && str_contains($scheduleJs, 'StrideBRI18n?.monthYear'), 'Cronogramas não fixa locale PT-BR em datas e busca');
$ok(str_contains($dashboardJs, "const t = (key, values = {}, fallback = key) => window.StrideBRI18n") && substr_count($dashboardJs, "const t = (key, values = {}, fallback = key)") >= 2, 'Dashboard possui helper i18n em ambos os módulos DOMContentLoaded');
$ok(str_contains($dashboardJs, "t('goals.dashboard_updated'") && !str_contains($dashboardJs, "'Painel atualizado.'"), 'Dashboard localiza confirmação de preferências');
$ok(str_contains($globalJs, "stridebrCommonT('draft.saved_device_only'") && str_contains($globalJs, "stridebrCommonT('draft.restored_continue'") && !str_contains($globalJs, "toLocaleTimeString('pt-BR'"), 'Rascunhos globais usam chaves e locale central');
$ok(str_contains($globalJs, "stridebrCommonT('sport_picker.search_results_all_categories'") && !str_contains($globalJs, "'Resultados em todas as categorias.'"), 'Busca global de esportes localiza estados dinâmicos');

$ok(str_contains($goalsJs, "t('goals.success.reactivated'") && !str_contains($goalsJs, "'Meta reativada.'"), 'Metas localiza mensagens AJAX de sucesso');
$ok(str_contains($gpsRecorderJs, "t('gps.try_again')") && !str_contains($gpsRecorderJs, "'▶ Tentar novamente'"), 'GPS localiza ação de tentar novamente');
$ok(str_contains($supportProjectJs, "t('common.copied'") && !str_contains($supportProjectJs, "'Copiado'"), 'Página de apoio localiza confirmação de cópia');
$ok(str_contains($agendaJs, "agendaT('agenda.page_title')") && !str_contains($agendaJs, "|| 'Agenda mensal'"), 'Agenda mensal localiza fallback do título dinâmico');
$ok(substr_count($activitiesJs, "trn('activity.history.loaded.one', 'activity.history.loaded.other', historyTotal)") >= 3, 'Histórico de atividades usa plural central ao restaurar, carregar e iniciar');

$runtime = (string) file_get_contents($root . '/public/assets/js/i18n-runtime.js');
$ok(!str_contains($runtime, chr(8)), 'runtime não contém backspace literal');
$ok(str_contains($runtime, '/\\bHoje\\b/g'), 'regex legado Hoje usa word boundary real');
$ok(str_contains($runtime, '/\\bAmanhã\\b/g'), 'regex legado Amanhã usa word boundary real');
$ok(str_contains($runtime, '/\\b(\\d+) atividades nesta semana\\b/g'), 'regex de plural semanal usa word boundary real');
$ok(str_contains($runtime, 'data-i18n-legacy'), 'fallback legado exige marcação explícita');
$ok(!str_contains($runtime, 'const translations = {') && !str_contains($runtime, 'const map = {'), 'runtime não mantém segundo dicionário de frases');

$header = (string) file_get_contents($root . '/src/layout/header.php');
$ok(str_contains($header, "stridebr_t('common.open_profile_menu')"), 'aria do perfil usa chave');
$ok(str_contains($header, "stridebr_t('nav.create')") || str_contains($header, "stridebr_t('common.create')"), 'Criar do header usa chave');

printf("✓ i18n coverage: %d assertions; %d keys/locale\n", $assertions, count($pt));
