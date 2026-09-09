<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Usability static failed: {$message}\n");
        exit(1);
    }
};

$profile = $read('public/user/perfil.php');
$friends = $read('public/user/amigos.php');
$header = $read('src/layout/header.php');
$scheduleCss = $read('public/assets/css/cronogramas.css');
$refreshCss = $read('public/assets/css/ui-refresh.css');
$styleCss = $read('public/assets/css/style.css');
$settings = $read('public/user/settings.php');
$settingsNavigation = $read('src/layout/settings_workspace.php');
$integrationCards = $read('src/layout/integration_cards.php');
$library = $read('public/user/biblioteca.php');
$libraryJs = $read('public/assets/js/library.js');

$assert(str_contains($profile, '$publicProfilesEnabled = stridebr_feature_enabled'), 'feature flag de perfis públicos deve ter estado explícito.');
$assert(str_contains($profile, '!$isSelf && !$isFriend && !$publicProfilesEnabled'), 'amigo aceito não pode ser bloqueado antes da autorização.');
$assert(str_contains($profile, '$isFriend && in_array($visibility, [\'amigos\', \'publico\'], true)'), 'visibilidade privada deve continuar fora do acesso de amizade.');
$assert(substr_count($friends, 'class="person-identity-link"') >= 3, 'busca, solicitações e amigos devem ter alvo de identidade clicável.');
$assert(str_contains($refreshCss, '.person-identity-link:focus-visible'), 'link de identidade deve manter foco visível.');
$assert(str_contains($header, 'class="mobile-profile-link"') && str_contains($header, 'class="mobile-more-label"'), 'avatar mobile e menu Mais devem ser ações distintas.');
$assert(str_contains($styleCss, '.mobile-profile-link { display: inline-grid;') && str_contains($styleCss, '.user-menu summary > img { display: none; }'), 'avatar direto deve ser o alvo visual no mobile.');
$monthRule = strpos($scheduleCss, "@media (min-width: 761px) {\n    .schedule-month-calendar-wrap {");
$assert($monthRule !== false, 'regra desktop do calendário mensal deve permanecer localizada.');
$monthBlock = substr($scheduleCss, $monthRule, 420);
$assert(str_contains($monthBlock, 'max-height: none;') && str_contains($monthBlock, 'overflow-y: visible;'), 'Mês desktop deve delegar scroll vertical ao documento.');
$assert(str_contains($scheduleCss, 'background:var(--ui-surface-hover)') && str_contains($scheduleCss, 'color:var(--ui-faint)'), 'estados mensais devem usar tokens de tema.');
$assert(str_contains($settings, "in_array(\$settingsView, ['profile', 'connections'], true)") && str_contains($settings, 'data-settings-area="profile"') && str_contains($settings, 'data-settings-area="settings"'), 'Perfil e Preferências devem continuar separados sem alterar persistência.');
$assert(str_contains($settingsNavigation, "'connections'") && str_contains($settingsNavigation, '/user/settings.php?view=connections'), 'Conexões deve ser uma área encontrada no workspace de configurações.');
$assert(str_contains($integrationCards, '$returnTo = \'/user/settings.php?view=connections#conexoes\';'), 'ações de integração devem retornar ao contexto Conexões.');
$assert(str_contains($library, 'data-library-tab-help="treinos"') && str_contains($library, 'library.personalize_exercise') && str_contains($library, 'library.manage_exercises'), 'Biblioteca deve explicar treino reutilizável e personalização de exercício global.');
$assert(str_contains($libraryJs, 'tabHelp.forEach'), 'troca de aba da Biblioteca deve atualizar a ajuda contextual.');

printf("✓ usability final static: %d assertions\n", $checks);
