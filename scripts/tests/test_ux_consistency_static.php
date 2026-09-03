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
$footer = $read('src/layout/footer.php');
$landing = $read('public/index.php');
$calendar = $read('public/calendario.php');
$goals = $read('public/user/metas.php');

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

$assert(str_contains($account, '<h1>Conta e segurança</h1>'), 'Conta e segurança precisa ter H1 coerente com o title/destino.');
$assert(!str_contains($calendar, 'migration de eventos'), 'estado público de Eventos não deve expor migration.');
$assert(!str_contains($goals, 'Aplique as migrations'), 'estado público de Metas não deve expor migration.');
$assert(str_contains($footer, "stridebr_t('nav.activities')"), 'label mobile de Atividades deve caber sem depender de fonte minúscula.');
$assert(str_contains($footer, '>Importar e exportar<'), 'navegação deve usar “Importar e exportar”.');
$assert(str_contains($activities, "stridebr_t('activity.import_export')") || str_contains($activities, '>Importar e exportar<'), 'toolbar de atividades deve usar “Importar e exportar”.');
$assert(str_contains($landing, 'Cronogramas, atividades, progresso, rotas e privacidade no mesmo lugar.'), 'landing deve listar capacidades concretas.');
$assert(!is_file($root . '/docs/design/UX_WRITING_GUIDE.md'), 'guia interno do assistente não deve ficar no projeto.');
$assert(!is_file($root . '/docs/design/UX_CONSISTENCY_AUDIT_20260830.md'), 'auditoria interna do assistente não deve ficar no projeto.');

printf("✓ ux/copy consistency static: %d assertions\n", $checks);
