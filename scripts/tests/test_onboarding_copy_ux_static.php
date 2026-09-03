<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);

$signup = $read('public/signup.php');
$login = $read('public/login.php');
$onboardingJs = $read('public/assets/js/onboarding.js');
$uiCss = $read('public/assets/css/ui-refresh.css');
$progress = $read('public/user/progresso.php');
$compare = $read('public/user/comparar-atividades.php');
$activities = $read('public/user/atividades.php');
$activitiesJs = $read('public/assets/js/atividades.js');
$gps = $read('public/user/gravar-atividade.php');
$settings = $read('public/user/settings.php');

$checks = [
    'onboarding mantém área e ações estáveis' => str_contains($uiCss, '.signup-onboarding-card{') && str_contains($uiCss, 'height:min(720px,calc(100dvh - 88px))') && str_contains($uiCss, '.signup-onboarding-actions') && str_contains($uiCss, 'border-top:1px solid #e4e7ec'),
    'voltar não muda de lugar nem alcança pular personalização' => str_contains($onboardingJs, "prev.classList.toggle('is-invisible', index === 0)") && str_contains($signup, 'data-skip-to-account') && strpos($signup, 'data-skip-to-account') < strpos($signup, 'signup-onboarding-actions'),
    'onboarding não rola a página inteira ao trocar de etapa' => !str_contains($onboardingJs, 'root.scrollIntoView') && str_contains($onboardingJs, 'steps[index].scrollTop = 0'),
    'texto redundante principal foi removido do signup' => !str_contains($signup, 'Você escolhe o que importa e só cria a conta no final') && !str_contains($signup, 'Tudo desta personalização é opcional') && !str_contains($signup, 'São defaults, não regras') && !str_contains($signup, 'Nome, e-mail e senha entram só agora'),
    'privacidade de recorte de rota não aparece no onboarding' => !str_contains($signup, 'name="hide_route_start_m"') && !str_contains($signup, 'name="hide_route_end_m"'),
    'lista de esportes tem busca grupos e ícones do site' => str_contains($signup, 'data-signup-sport-search') && str_contains($signup, 'signupSportGroups') && str_contains($signup, 'Mais comuns') && str_contains($signup, 'data-signup-sport-more') && str_contains($signup, 'stridebr_sport_icon_html'),
    'objetivos e acompanhamento têm opções orientadas a uso' => str_contains($signup, 'Manter uma rotina') && str_contains($signup, 'Distância e ritmo') && str_contains($signup, 'Carga, séries e volume na musculação'),
    'resumo final mostra somente escolhas existentes' => str_contains($onboardingJs, 'if (sports.length)') && str_contains($onboardingJs, 'if (goals.length)') && str_contains($onboardingJs, 'summaryBlock.hidden = empty'),
    'login usa mesma família visual do cadastro' => str_contains($login, 'signup-onboarding-body') && str_contains($login, 'auth-unified-card') && str_contains($uiCss, '.auth-unified-card'),
    'progresso abre configurações reais' => str_contains($progress, '/user/settings.php#preferencias-treino') && !str_contains($progress, '/user/onboarding.php'),
    'configurações permitem editar preferências do onboarding' => str_contains($settings, 'id="preferencias-treino"') && str_contains($settings, 'name="training_goals[]"') && str_contains($settings, 'name="training_tracking[]"') && str_contains($settings, 'name="training_weekly_frequency"'),
    'compare saiu do rodapé e foi para o cabeçalho do detalhe' => str_contains($activities, 'data-detail-compare') && str_contains($activitiesJs, "detailDrawer.querySelector('[data-detail-compare]')") && substr_count($activitiesJs, 'href="/user/comparar-atividades.php?a=${encodeURIComponent(activity.id)}"') === 0,
    'atalhos GPS usam iconografia do site' => str_contains($gps, "stridebr_sport_icon_html((string) \$modalidade['slug'], 'gps-quick-sport-icon')") && !str_contains($gps, "\$modalidade['icone'] ?: '•'"),
    'copy de progresso e comparação ficou direta' => !str_contains($progress, 'não uma nota') && !str_contains($progress, 'Sem pontos, níveis ou ranking') && !str_contains($compare, 'não dá uma nota') && !str_contains($compare, 'Carga isolada não define'),
];

$failed = [];
foreach ($checks as $label => $ok) if (!$ok) $failed[] = $label;
if ($failed !== []) {
    fwrite(STDERR, "Falhas no pass de onboarding/copy UX:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo '✓ onboarding/copy UX static: ' . count($checks) . " assertions\n";
