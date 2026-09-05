<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$activity = $read('public/assets/js/atividades.js');
$utils = $read('public/assets/js/activity-route-utils.js');
$layout = $read('src/layout/activity_route_editor.php');
$unitLayout = $read('src/layout/activity_unit_route.php');
$draft = $read('public/assets/js/scripts.js');
$model = $read('src/function/atividade_modelo.php');
$exchange = $read('src/function/activity_file_exchange.php');
$activitiesPage = $read('public/user/atividades.php');
$editPage = $read('public/user/editatividade.php');

$checks = [
    'utilitário de rota carregado em criar e editar' => str_contains($activitiesPage, '/assets/js/activity-route-utils.js') && str_contains($editPage, '/assets/js/activity-route-utils.js'),
    'modo Livre e Circuito existem na rota principal' => str_contains($layout, 'data-route-mode="free"') && str_contains($layout, 'data-route-mode="circuit"'),
    'rotas de trechos reutilizam Livre e Circuito do workspace principal' => !str_contains($unitLayout, 'data-unit-route-mode=') && str_contains($activity, "activity:route-open-target") && str_contains($layout, 'data-route-mode="free"') && str_contains($layout, 'data-route-mode="circuit"'),
    'draft possui estado auxiliar nomeado da volta-base' => str_contains($layout, 'name="route_editor_laps"') && str_contains($layout, 'name="route_editor_base"') && str_contains($unitLayout, '[route_editor_laps]') && str_contains($unitLayout, '[route_editor_base]'),
    'serializador de draft preserva campos nomeados' => str_contains($draft, "form.querySelectorAll('[name]')") && str_contains($draft, "values[field.name] = field.value"),
    'circuito usa fechamento e expansão sem duplicar junções' => str_contains($utils, 'closeCircuit') && str_contains($utils, 'coordinates = [closed[0]]') && str_contains($utils, 'closed.slice(1)'),
    'limite frontend coincide com backend' => str_contains($utils, 'MAX_POINTS = 2000') && str_contains($model, 'ATIVIDADE_ROTA_MAX_PONTOS = 2000'),
    'circuito bloqueia excesso sem truncamento silencioso' => str_contains($utils, "reason: 'point_limit'") && str_contains($activity, "tr('route.point_limit'") && str_contains($activity, "'route.base_point_limit'") && str_contains($activity, "'route.free_point_limit'"),
    'editar volta-base mantém circuito fechado e regenera repetições' => str_contains($activity, 'circuitEditing') && str_contains($activity, 'route.finish_base_edit') && str_contains($activity, 'routeUtil.closeCircuit([...openBase, nextPoint])'),
    'reabrir rota concluída não entra sozinho na edição da volta-base' => !str_contains($activity, "if (mode === 'circuit' && circuitClosed) circuitEditing = true") && str_contains($activity, "tr(circuitEditing ? 'route.editing_base' : 'route.closed')"),
    'Undo atua sobre a volta-base' => str_contains($activity, "if (mode === 'circuit' && circuitClosed && circuitEditing)") && str_contains($activity, 'openBase.pop()'),
    'markers intermediários somem após concluir circuito' => str_contains($activity, "circuitClosed && !circuitEditing") && str_contains($activity, "? (points.length ? [0] : [])"),
    'início e último vértice são distinguíveis durante edição' => str_contains($activity, "' is-start'") && str_contains($activity, "' is-end'"),
    'elevação consulta a volta-base e multiplica ganho/perda' => str_contains($activity, "mode === 'circuit' && circuitClosed ? points : finalPoints()") && str_contains($activity, 'lossBase * multiplier'),
    'recalcular elevação limpa métricas antigas do circuito antes da nova resposta' => str_contains($activity, 'clearElevationMetrics()'),
    'troca entre rota geral e trecho preserva metadados do circuito' => str_contains($activity, 'transferRouteEditorState') && str_contains($activity, '[data-${sourcePrefix}-laps-value]') && str_contains($activity, '[data-${sourcePrefix}-base-value]'),
    'POST com erro preserva metadados auxiliares do circuito' => str_contains($activitiesPage, "\$_POST['route_editor_mode']") && str_contains($editPage, "\$_POST['route_editor_base']") && str_contains($unitLayout, "\$editorState['route_editor_laps']"),
    'rota persistida continua LineString comum' => str_contains($utils, "{type: 'LineString', coordinates: list}") && str_contains($model, "['type' => 'LineString', 'coordinates' => \$validated]"),
    'exportação consome rota persistida em vez de metadado auxiliar' => str_contains($exchange, "(\$route['type'] ?? '') === 'LineString'") && !str_contains($exchange, 'route_editor_laps'),
    'detalhe desenha linha sem dezenas de markers' => str_contains($activity, 'window.L.polyline(latLngs') && !str_contains($activity, 'circleMarker('),
    'controles e status de voltas têm feedback acessível no workspace compartilhado' => str_contains($layout, "route.decrease_laps") && str_contains($layout, "route.increase_laps") && str_contains($layout, 'aria-live="polite"') && str_contains($unitLayout, 'data-unit-route-open'),
];

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
if ($failed !== []) {
    fwrite(STDERR, "Falhas no circuito de rotas:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}
printf("✓ activity route circuit static: %d assertions\n", count($checks));
