<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$create = $read('public/user/atividades.php');
$edit = $read('public/user/editatividade.php');
$panels = $read('src/layout/activity_model_panels.php');
$shared = $read('src/layout/activity_model_panel_shared.php');
$unitRoute = $read('src/layout/activity_unit_route.php');
$unitHelpers = $read('src/layout/activity_unit_helpers.php');
$model = $read('src/function/atividade_modelo.php');
$js = $read('public/assets/js/atividades.js');
$css = $read('public/assets/css/atividades.css');
$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Activity editor parity failed: {$message}\n");
        exit(1);
    }
};

$assert(str_contains($panels, "activity_model_panel_shared.php") && str_contains($edit, "activity_model_panel_shared.php"), 'CREATE e EDIT precisam renderizar o mesmo painel compartilhado.');
$assert(str_contains($create, "activity_smart_title.php") && str_contains($edit, "activity_smart_title.php"), 'título inteligente precisa ser compartilhado.');
$assert(str_contains($create, "activity_route_privacy.php") && str_contains($edit, "activity_route_privacy.php"), 'privacidade de rota precisa ser compartilhada.');
$assert(str_contains($create, "activity_log_details.php") && str_contains($edit, "activity_log_details.php"), 'esforço/equipamento/observações precisam ser compartilhados.');
$assert(str_contains($shared, "'name_prefix'") && str_contains($shared, '$unitName') && str_contains($shared, '$recordName'), 'partial compartilhado precisa preservar prefixos POST distintos.');
$assert(str_contains($edit, "'name_prefix' => ''") && str_contains($panels, '\'name_prefix\' => "models[{$idModelo}]"'), 'EDIT e CREATE precisam preservar seus contratos de name atuais.');

$assert(substr_count($js, 'button.dataset.durationPrecisionToggle') === 1, 'toggle de ms deve ser criado uma única vez pelo editor.');
$assert(str_contains($js, "form?.querySelectorAll('[data-duration-ms-wrap], [data-duration-ms-separator]')"), 'precisão de ms precisa ser global ao formulário.');
$assert(str_contains($js, 'preferredDurationPrecisionField') && str_contains($js, "panel.querySelector('[data-primary-segment-core] [data-duration-field]')") && str_contains($js, "panel?.querySelector('[data-primary-unit] [data-duration-field]')"), '+ms precisa acompanhar a duração principal visível, inclusive no modo Trechos.');
$assert(!str_contains($shared, 'data-duration-precision-toggle') && !str_contains($unitRoute, 'data-duration-precision-toggle'), 'Trechos não podem renderizar toggle próprio de ms.');
$assert(str_contains($js, "Number.parseInt(milliseconds.value, 10) !== 0) durationPrecisionEnabled = true"), 'qualquer duração fracionária existente precisa ativar ms automaticamente.');

$assert(str_contains($shared, 'data-optional-scope="') && str_contains($shared, '$renderOptionalBar($optionalFields, \'panel\')') && str_contains($shared, '$renderOptionalBar($unitOptionalFields, \'unit\')'), 'optional fields precisam usar barras com escopo explícito.');
$assert(str_contains($js, 'optionalFieldForChip') && str_contains($js, 'chip.hidden =') && str_contains($js, "field.classList.add('is-optional-hidden')"), 'chips precisam acompanhar visibilidade real do campo.');
$assert(str_contains($css, '.optional-field-chip[hidden]') && str_contains($css, 'display: none !important'), 'CSS não pode sobrescrever hidden dos chips.');

$assert(str_contains($model, 'if ($slug === \'cadencia\') return stridebr_t(\'activity.average_cadence\')') && str_contains($model, 'if ($slug === \'potencia\') return stridebr_t(\'activity.average_power\')'), 'Cadência e Potência precisam usar labels médios no editor.');
$assert(str_contains($model, 'if ($slug === \'potencia\') $unitSymbol = \'W\'') && str_contains($js, "? 'rpm'") && str_contains($js, "tr('activity.steps_per_minute')"), 'unidades contextuais de Cadência/Potência precisam existir.');
$assert(str_contains($pt, "'activity.average_cadence'") && str_contains($en, "'activity.average_cadence'") && str_contains($pt, "'activity.steps_per_minute'") && str_contains($en, "'activity.steps_per_minute'"), 'labels/unidades contextuais precisam existir em PT/EN.');

$assert(str_contains($unitHelpers, 'activity-segment-core-metrics') && str_contains($shared, 'activity-unit-grid'), 'Trechos precisam usar estruturas compactas compartilhadas.');
$assert(str_contains($css, 'grid-template-columns: minmax(130px, .85fr) minmax(250px, 1.45fr)') && str_contains($css, 'repeat(3, minmax(150px, 1fr))'), 'desktop de Trechos precisa ter grid denso.');
$assert(str_contains($css, '@media (max-width: 900px)') && str_contains($css, 'repeat(2, minmax(0, 1fr))') && str_contains($css, '@media (max-width: 520px)'), 'Trechos precisam cair para 2/1 colunas no mobile.');

$assert(!str_contains($unitRoute, 'data-unit-route-map') && !str_contains($unitRoute, 'leaflet') && !str_contains($unitRoute, '<details'), 'rota de Trecho não pode manter mapa/editor inline próprio.');
$assert(str_contains($unitRoute, 'data-unit-route-open') && str_contains($unitRoute, 'data-unit-route-value'), 'card do Trecho precisa manter apenas resumo/ação e draft.');
$assert(str_contains($js, "activity:route-open-target") && str_contains($js, 'useUnitRouteTarget') && str_contains($js, 'useGeneralRouteTarget'), 'rotas geral/Trecho precisam compartilhar o mesmo workspace por target.');
$assert(str_contains($js, '`${tr(\'route.title\')} · ${String(target.dataset.unitRouteLabel || \'\').trim()}`'), 'header da subview precisa identificar o Trecho.');
$assert(str_contains($js, 'routeSubviewScrollTop') && str_contains($js, 'focusTarget?.focus'), 'retorno da rota do Trecho precisa preservar posição/foco.');
$assert(str_contains($js, 'StrideBRBasemaps?.attach(map') && substr_count($js, 'StrideBRBasemaps?.attach(map') === 1, 'deve existir um único attach de basemap para o editor compartilhado.');

$assert(str_contains($shared, 'atividadeCampoOpcional($field)') && str_contains($shared, '$unitOptionalFields'), 'Intensidade e demais dados opcionais de unidade precisam continuar disponíveis por Trecho.');
$assert(str_contains($create, 'activityEditorEffort') && str_contains($edit, 'activityEditorEffort') && str_contains($read('src/layout/activity_log_details.php'), 'name="esforco_percebido"'), 'Esforço percebido precisa continuar no nível geral da sessão.');
$assert(!str_contains($shared, 'name="esforco_percebido"'), 'Trechos não devem ganhar RPE geral duplicado.');

printf("✓ activity editor parity static: %d assertions\n", $checks);
