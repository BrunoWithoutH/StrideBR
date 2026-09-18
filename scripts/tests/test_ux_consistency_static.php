<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "UX consistency static failed: {$message}\n");
        exit(1);
    }
};

$read = static function (string $relative) use ($root): string {
    $data = file_get_contents($root . '/' . $relative);
    if ($data === false) {
        fwrite(STDERR, "Unable to read {$relative}\n");
        exit(1);
    }
    return $data;
};

$home = $read('public/home.php');
$settings = $read('public/user/settings.php');
$activities = $read('public/user/atividades.php');
$activityJs = $read('public/assets/js/atividades.js');
$notifications = $read('public/user/notificacoes.php');
$account = $read('public/user/account.php');
$settingsWorkspace = $read('src/layout/settings_workspace.php');
$footer = $read('src/layout/footer.php');
$landing = $read('public/index.php');
$calendar = $read('public/calendario.php');
$goals = $read('public/user/metas.php');
$header = $read('src/layout/header.php');
$quickTools = $read('public/user/ferramentastreino.php');
$quickToolsJs = $read('public/assets/js/quick-tools.js');
$routes = $read('public/user/rotas.php');
$zonesJs = $read('public/assets/js/zone-profiles.js');
$zones = $read('public/user/zonas.php');
$library = $read('public/user/biblioteca.php');
$libraryJs = $read('public/assets/js/library.js');
$activityDetailJs = $read('public/assets/js/activity-detail-v3.js');
$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');

$removedPhrases = [
    'Sem pontuação artificial',
    'Não precisa configurar esportes antes',
    'Você pode compartilhar a atividade só com os dados',
    'do jeito que funciona melhor para você',
    'Métricas em destaque, sem depender de rota',
    'Progresso real, privacidade',
];
foreach ($removedPhrases as $phrase) {
    $assert(!str_contains($home . $settings . $activities . $activityJs . $notifications . $landing, $phrase), "copy de justificativa voltou: {$phrase}");
}

$assert(str_contains($account, "stridebr_settings_workspace_heading(stridebr_t('account.page_title')") && str_contains($settingsWorkspace, '<h1>'), 'Conta e segurança precisa ter H1 coerente pelo workspace compartilhado.');
$assert(!str_contains($calendar, 'migration de eventos'), 'estado público de Eventos não deve expor migration.');
$assert(!str_contains($goals, 'Aplique as migrations'), 'estado público de Metas não deve expor migration.');
$assert(str_contains($footer, "stridebr_t('nav.activities')"), 'label mobile de Atividades deve caber sem depender de fonte minúscula.');
$assert(!str_contains($footer, "stridebr_t('nav.import_export')"), 'Importar e exportar pertence ao contexto de Atividades, não ao menu global mobile.');
$assert(str_contains($activities, "stridebr_t('activity.import_activity')") && str_contains($activities, '/user/importar-exportar.php#importar') && str_contains($activities, 'data-activity-tool="exchange"'), 'toolbar de atividades deve abrir importação dentro da própria superfície de Activities.');
$assert(str_contains($header, "stridebr_t('tools.pacer')") && str_contains($header, "stridebr_t('tools.quick_help')"), 'dropdown Ferramentas deve usar i18n para os novos recursos.');
$assert(str_contains($quickTools, 'data-quick-tools-standalone') && str_contains($quickTools, 'data-quick-tools-slot="timer"') && str_contains($quickTools, 'data-quick-tools-slot="stopwatch"') && str_contains($quickTools, 'data-quick-tools-slot="sets"') && str_contains($quickToolsJs, "slot.appendChild(view)"), 'Ferramentas rápidas standalone deve mostrar os três views funcionais da engine compartilhada.');
$assert(str_contains($zones, 'zoneProfileReturnTo') && str_contains($zones, 'name="return_to"'), 'fluxo de Zonas deve validar e propagar return_to no servidor.');
$assert(str_contains($zones, 'data-zones-page-help') && str_contains($zones, "stridebr_t('zones.help_title')") && str_contains($zonesJs, "data-zones-help-dialog"), 'Zonas standalone deve oferecer Como funciona com o conteúdo traduzido existente.');
$assert(str_contains($routes, "stridebr_t('nav.tools')") && !str_contains($routes, '>Como funciona</a>'), 'Rotas deve refletir a hierarquia Ferramentas e não usar CTA de ajuda que apenas navega para Activities.');
$assert(str_contains($activityDetailJs, "t('zones.help_title'") && str_contains($activityDetailJs, "t('zones.setup_help'"), 'Zonas no detalhe deve usar as keys i18n novas.');
$assert(str_contains($library, "stridebr_t('library.review_names')") && str_contains($library, 'type="button" data-name-review-keep') && str_contains($libraryJs, "closest('[data-name-review-item]')?.remove()"), 'Revisar nomes deve ter ações traduzidas e dismiss apenas local, sem POST de alteração.');
foreach (['activity.import_activity', 'activity.export', 'activity.stats_include', 'activity.splits.distance', 'activity.analysis.negative_split', 'activity.analysis.drift_context', 'zones.help_title', 'zones.configure', 'library.review_names', 'library.keep_as_is', 'tools.quick_title', 'tools.pacer_help'] as $key) {
    $assert(str_contains($pt, "'{$key}'") && str_contains($en, "'{$key}'"), "key i18n nova ausente em PT-BR ou EN: {$key}");
}
$assert(str_contains($landing, 'Cronogramas, atividades, progresso, rotas e privacidade no mesmo lugar.'), 'landing deve listar capacidades concretas.');
$assert(!is_file($root . '/docs/design/UX_WRITING_GUIDE.md'), 'guia interno do assistente não deve ficar no projeto.');
$assert(!is_file($root . '/docs/design/UX_CONSISTENCY_AUDIT_20260830.md'), 'auditoria interna do assistente não deve ficar no projeto.');

printf("✓ ux/copy consistency static: %d assertions\n", $checks);
