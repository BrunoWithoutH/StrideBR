<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$create = (string) file_get_contents($root . '/public/user/atividades.php');
$edit = (string) file_get_contents($root . '/public/user/editatividade.php');
$partial = (string) file_get_contents($root . '/src/layout/activity_log_details.php');
$pt = (string) file_get_contents($root . '/src/i18n/pt-BR.php');
$en = (string) file_get_contents($root . '/src/i18n/en.php');
$model = (string) file_get_contents($root . '/src/function/atividade_modelo.php');
$assertions = [
    ['CREATE usa preferência atual como default', str_contains($create, "\$activityDefaults['visibility'] ?? 'privado'")],
    ['EDIT usa visibilidade salva', str_contains($edit, "\$registro['visibilidade'] ?? 'privado'")],
    ['CREATE e EDIT usam partial compartilhado', substr_count($create . $edit, 'activity_log_details.php') >= 2],
    ['visibilidade é linha explícita', str_contains($partial, 'activity-visibility-row') && str_contains($partial, "stridebr_t('activity.activity_visibility')")],
    ['valores internos preservados', str_contains($partial, 'value="privado"') && str_contains($partial, 'value="amigos"') && str_contains($partial, 'value="publico"')],
    ['campo não depende de disclosure Mais opções', !str_contains($partial, '<details') && !str_contains($partial, 'activity.more_options')],
    ['backend aceita somente enum existente', str_contains($model, "['privado', 'amigos', 'publico']")],
    ['backend aplica preferência se payload vazio', str_contains($model, "if (\$visibilidade === '') \$visibilidade = \$userDefaults['visibility'];")],
    ['PT-BR tem label explícita', str_contains($pt, "'activity.activity_visibility' => 'Visibilidade da atividade'")],
    ['EN tem label explícita', str_contains($en, "'activity.activity_visibility' => 'Activity visibility'")],
];
foreach ($assertions as [$label, $ok]) {
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
}
echo '✓ activity visibility static: ' . count($assertions) . " assertions\n";
