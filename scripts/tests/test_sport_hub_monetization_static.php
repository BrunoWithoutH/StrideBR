<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Sport hub/monetization static failed: {$message}\n");
        exit(1);
    }
};

$hub = $read('src/function/sport_hub.php');
$catalog = $read('src/function/sport_catalog.php');
$progress = $read('public/user/progresso.php');
$hubCss = $read('public/assets/css/sport-hub.css');
$workoutApi = $read('src/function/treino_sessao_api.php');
$workoutJs = $read('public/assets/js/workout-session.js');
$muscleMigration = $read('src/database/migrations/20260903_v1_rc.sql');
$money = $read('src/function/monetization.php');
$ads = $read('src/layout/ads.php');
$footer = $read('src/layout/footer.php');
$donation = $read('public/pages/about/support-project.php');
$env = $read('.env.example');
$docs = $read('docs/MONETIZATION.md');
$activities = $read('public/user/atividades.php');
$activityJs = $read('public/assets/js/atividades.js');

$assert(str_contains($catalog, "'cardio' => ['label' => 'Cardio'") && str_contains($catalog, "'athletics' => ['label' => 'Atletismo'") && str_contains($catalog, "'team' => ['label' => 'Esportes em equipe'") && str_contains($progress, 'sportHubActiveFamilies') && str_contains($progress, "['all' => 'Visão geral']"), 'o hub precisa usar categorias intuitivas e mostrar apenas áreas praticadas.');
$assert(str_contains($hub, 'function sportHubStrengthDashboard') && str_contains($hub, "'best_e1rm'") && str_contains($hub, '$load * (1 + ($reps / 30))'), 'Força precisa calcular histórico, carga e e1RM secundário.');
$assert(str_contains($hub, "'volume_kg'") && str_contains($hub, "'calendar'") && str_contains($hub, "'muscles'"), 'Força precisa produzir volume, calendário e distribuição muscular.');
$assert(str_contains($hubCss, '.sport-hub-tabs') && str_contains($hubCss, '.strength-calendar') && str_contains($hubCss, '.muscle-bars'), 'o hub precisa ter estilos próprios.');
$assert(str_contains($muscleMigration, 'grupos_musculares_primarios') && str_contains($muscleMigration, 'e_supino') && str_contains($muscleMigration, 'e_agachamento'), 'metadados musculares iniciais precisam existir.');
$assert(str_contains($workoutApi, "\$action === 'update_set'") && str_contains($workoutApi, 'sessaoPersistirSeriesAtividade'), 'sessão precisa registrar carga/repetições e persistir séries na atividade.');
$assert(str_contains($workoutJs, 'data-session-set-load') && str_contains($workoutJs, 'data-session-set-reps') && str_contains($workoutJs, "action: 'update_set'"), 'interface da sessão precisa editar carga e repetições por série.');
$assert(str_contains($money, "STRIDEBR_ADS_ENABLED") && str_contains($money, "STRIDEBR_DONATION_ENABLED") && str_contains($money, "'/user/'"), 'monetização precisa ser opt-in e não carregar em páginas autenticadas.');
$assert(str_contains($ads, "|| !\$pageAllowsAds") && str_contains($docs, 'Dados recebidos de Fitbit') && str_contains($docs, 'não podem ser usados para publicidade'), 'anúncios não podem receber dados esportivos ou de integrações.');
$assert(str_contains($footer, 'stridebr_donation_enabled()') && str_contains($donation, 'stridebr_donation_pix_key()') && str_contains($env, 'STRIDEBR_DONATION_ENABLED=0'), 'doações precisam estar prontas, mas desligadas por padrão.');
$assert(str_contains($activities, 'data-post-save-share') && str_contains($activities, '?saved=') && str_contains($activityJs, 'openPostSaveShare') && str_contains($activityJs, 'data-post-save-native'), 'atividade recém-salva precisa abrir compartilhamento rápido.');
$assert(str_contains($activityJs, 'drawPostSaveShare') && str_contains($activityJs, 'drawShareCardSurface(context, config, token)') && str_contains($activityJs, 'Editar compartilhamento') === false && str_contains($activities, 'Editar compartilhamento'), 'prévia padrão precisa existir e encaminhar ao editor completo.');
$assert(str_contains($hub, 'function sportHubResolvePeriod') && str_contains($hub, "'3m' => 3") && str_contains($hub, "'6m' => 6") && str_contains($hub, "'12m' => 12"), 'Progresso precisa resolver janelas de 1, 3, 6 e 12 meses.');
$assert(str_contains($progress, 'progress-period-toolbar') && str_contains($progress, 'name="month"') && str_contains($progress, "'period' => \$periodView"), 'Progresso precisa permitir navegar por mês e preservar a janela selecionada.');
$assert(str_contains($progress, 'sportHubOverview($activities, $periodWindow)') && str_contains($progress, 'sportHubStrengthDashboard($pdo, $idUsuario, $activities, $periodWindow)') && str_contains($progress, '$activitiesForPeriod'), 'resumos e recortes por modalidade precisam respeitar o período escolhido.');
$assert(str_contains($hubCss, '.progress-period-toolbar') && str_contains($hubCss, '.progress-period-ranges') && str_contains($hubCss, '.progress-period-month'), 'controles de período precisam ter layout próprio e responsivo.');

printf("✓ sport hub/monetization static: %d assertions\n", $checks);
