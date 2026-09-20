<?php
$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path);
$assertions = 0;
$assert = static function (bool $ok, string $message) use (&$assertions): void {
    $assertions++;
    if (!$ok) {
        fwrite(STDERR, "Training Planning UX Polish V6: {$message}\n");
        exit(1);
    }
};

$home = $read('public/home.php');
$restStart = strpos($home, "elseif (\$contextoHoje['state'] === 'rest'):", strpos($home, 'dashboard-today-actions'));
$restEnd = strpos($home, '<?php else: ?>', $restStart);
$restBlock = $restStart !== false && $restEnd !== false ? substr($home, $restStart, $restEnd - $restStart) : '';
$assert($restBlock !== '', 'rest action block missing');
$assert(str_contains($restBlock, "stridebr_t('home.next_workout')"), 'rest state must use dedicated next-workout copy');
$assert(!str_contains($restBlock, "progress.log_activity") && !str_contains($restBlock, 'atividades.php?new=1'), 'rest state must not render activity CTA');
$assert(str_contains($home, "stridebr_t('home.log_activity')"), 'activity registration must remain elsewhere on Home');

foreach (['public/signup.php', 'public/user/onboarding.php', 'public/user/settings.php'] as $path) {
    $source = $read($path);
    $assert(str_contains($source, "'retornando'"), $path . ' must allow returning experience');
    $assert(str_contains($source, 'value="retornando"'), $path . ' must render returning option');
}
$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');
$assert(str_contains($pt, "'settings.returning' => 'Retornando aos treinos'"), 'PT settings returning copy');
$assert(str_contains($pt, "'onboarding.experience_returning' => 'Retornando aos treinos'"), 'PT onboarding returning copy');
$assert(str_contains($en, "'settings.returning' => 'Returning to training'"), 'EN settings returning copy');
$assert(str_contains($en, "'onboarding.experience_returning' => 'Returning to training'"), 'EN onboarding returning copy');
$assert(str_contains($read('public/signup.php'), "'experience' => \$values['experience']"), 'signup must persist returning experience in preferences');
$assert(str_contains($read('public/user/onboarding.php'), "\$preferences['experience'] = \$values['experience'];"), 'onboarding must persist returning experience');
$assert(str_contains($read('public/user/settings.php'), "\$preferences['experience'] = \$trainingExperience;"), 'settings must persist returning experience');
$onboardingJs = $read('public/assets/js/onboarding.js');
$assert(str_contains($onboardingJs, 'experienceSelect.selectedOptions'), 'onboarding summary must use localized option text');

$cronograma = $read('src/function/cronograma.php');
$page = $read('public/user/cronogramatreinos.php');
$assert(str_contains($cronograma, 'function cronogramaTreinoVigenteEmData'), 'effective-date helper missing');
$assert(str_contains($cronograma, 'function cronogramaFiltrarTreinosVigentes'), 'effective workout filter missing');
$assert(str_contains($cronograma, 'SELECT * FROM treinos_cronograma WHERE idcronograma = :cronograma'), 'domain list must continue preserving all phase rows');
$assert(str_contains($page, '$treinosVigentes = cronogramaFiltrarTreinosVigentes($treinos, $today);'), 'List view must derive current effective routine');
$assert(str_contains($page, 'array_filter($treinosVigentes'), 'agenda/list renderer must use effective routine');
$assert(!str_contains($cronograma, 'UNIQUE (idcronograma, dia_semana, hora_inicio)'), 'must not add unsafe time dedupe');

$css = $read('public/assets/css/cronogramas.css');
$ui = $read('public/assets/css/ui-refresh.css');
$assert(str_contains($css, '.quick-register-grid { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(168px, .75fr);'), 'Quick Register desktop composition missing');
$assert(str_contains($css, '.quick-register-dialog .time24-control { min-width: 168px; }'), 'Quick Register time control width missing');
$assert(str_contains($css, '.quick-register-duration-field { grid-column: 1 / -1;'), 'Quick Register duration must be one coherent row');
$assert(str_contains($css, '.quick-register-actions {') && str_contains($css, 'position: sticky;'), 'Quick Register footer must remain visible');
$assert(str_contains($css, '@media (max-width: 620px)') && str_contains($css, '.quick-register-grid { grid-template-columns: 1fr; }'), 'Quick Register mobile stack missing');
$assert(!str_contains($ui, '/* Registro rápido: duração opcional'), 'Quick Register owner styles must not be duplicated in ui-refresh');

$assert(str_contains($page, "stridebr_t('schedule.when')"), 'Add Workout must expose scheduling hierarchy');
$assert(str_contains($css, '.quick-create-schedule-fields {') && str_contains($css, 'grid-template-columns:minmax(150px,.9fr) minmax(0,1.4fr);'), 'Add Workout scheduling composition missing');
$assert(str_contains($css, '.quick-create-actions {') && str_contains($css, 'position:sticky;'), 'Add Workout actions must remain visible');
$assert(str_contains($css, '.quick-create-source-cards') && str_contains($css, 'overflow-x: auto;'), 'saved workout sources must not grow modal vertically');

$agenda = $read('public/user/agenda-mensal.php');
$agendaCss = $ui;
$assert(str_contains($agenda, 'monthly-add monthly-add-toolbar'), 'monthly add action must live in toolbar');
$assert(str_contains($agenda, "stridebr_t('agenda.add_workout_short')"), 'monthly add action needs compact copy');
$assert(str_contains($agenda, 'monthly-mobile-agenda'), 'monthly mobile agenda representation missing');
$assert(str_contains($agendaCss, '.monthly-add-toolbar .monthly-add-form{position:absolute'), 'monthly add popover layout missing');
$assert(str_contains($agendaCss, '.monthly-mobile-agenda{display:none}'), 'mobile agenda desktop default missing');
$assert(str_contains($agendaCss, '.monthly-scroll-hint,.monthly-calendar-scroll{display:none}'), 'mobile must not require desktop calendar scroll');
$assert(str_contains($agendaCss, '.monthly-mobile-agenda{display:grid'), 'mobile agenda must activate at phone width');
$assert(str_contains($agendaCss, 'font-size:.69rem') && str_contains($agendaCss, 'font-size:.76rem'), 'monthly event typography must be operationally legible');
$assert(str_contains($read('public/assets/js/agenda-mensal.js'), "event.key !== 'Escape'"), 'monthly controls need Escape handling');

$assert(str_contains($pt, "'home.next_workout' => 'Próximo treino'"), 'PT next-workout copy missing');
$assert(str_contains($en, "'home.next_workout' => 'Next workout'"), 'EN next-workout copy missing');
$assert(str_contains($pt, "'agenda.add_workout_short' => 'Adicionar treino'"), 'PT monthly add copy missing');
$assert(str_contains($en, "'agenda.add_workout_short' => 'Add workout'"), 'EN monthly add copy missing');

echo "✓ Training Planning UX Polish V6 static: {$assertions} assertions\n";
