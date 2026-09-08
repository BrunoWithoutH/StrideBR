<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$home = file_get_contents($root . '/public/home.php');
$dashboard = file_get_contents($root . '/src/function/dashboard.php');
$js = file_get_contents($root . '/public/assets/js/dashboard.js');
$css = file_get_contents($root . '/public/assets/css/dashboard.css');
$pt = file_get_contents($root . '/src/i18n/pt-BR.php');
$en = file_get_contents($root . '/src/i18n/en.php');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$assert(str_contains($dashboard, "json_agg(json_build_object("), 'consulta semanal carrega atividades individuais na mesma query');
$assert(str_contains($dashboard, "'idregistro', idregistro") && str_contains($dashboard, "'data_inicio', data_inicio"), 'atividade semanal carrega id e horário');
$assert(str_contains($dashboard, "'modalidade_familia_hub', modalidade_familia_hub"), 'atividade semanal carrega contexto esportivo');
$assert(str_contains($dashboard, 'atividadeContextoFormatarDistancia') && str_contains($dashboard, 'atividadeContextoFormatarTempo'), 'apresentação semanal reutiliza formatters centrais');
$assert(str_contains($home, 'data-week-popover-trigger') && str_contains($home, 'data-week-popover-template'), 'marcadores expõem popover contextual');
$assert(str_contains($home, 'aria-controls="dashboard-week-popover"') && str_contains($home, 'id="dashboard-week-popover"'), 'marcadores associam semanticamente o popover');
$assert(str_contains($home, '/user/atividades.php#atividade-'), 'contrato de abertura de atividade continua alinhado ao histórico');
$assert(str_contains($js, "document.body.append(popover)"), 'popover sai do container para evitar clipping');
$assert(str_contains($js, "trigger.addEventListener('focus'") && str_contains($js, "isTouchContext"), 'popover cobre teclado e toque');
$assert(str_contains($css, '.dashboard-week-popover[hidden]{display:none!important}'), 'hidden contract do popover é estrutural');
$assert(!str_contains($home, "stridebr_t('home.week_consistency_help')"), 'copy explicativa da semana saiu da Home');
$assert(!str_contains($home, "stridebr_t('home.subtitle')"), 'subtitle decorativo da Home saiu');
foreach (['home.subtitle', 'home.week_consistency_help', 'library.subtitle', 'progress.product_subtitle', 'equipment.subtitle', 'goals.subtitle', 'admin.feedback.subtitle'] as $key) {
    $assert(!str_contains($pt, "'{$key}'"), "key removida de pt-BR: {$key}");
    $assert(!str_contains($en, "'{$key}'"), "key removida de en: {$key}");
}
foreach (['events.subtitle', 'friends.subtitle', 'trainer.subtitle'] as $key) {
    $assert(str_contains($pt, "'{$key}'") && str_contains($en, "'{$key}'"), "copy funcional preservada: {$key}");
}

echo "✓ home week interactions static: {$assertions} assertions\n";
