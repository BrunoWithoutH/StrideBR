<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

try {
    $migration = $read('src/database/migrations/20260903_v1_rc.sql');
    $model = $read('src/function/atividade_modelo.php');
    $presenter = $read('src/function/atividade_presenter.php');
    $panels = $read('src/layout/activity_model_panels.php');
    $sharedPanel = $read('src/layout/activity_model_panel_shared.php');
    $helpers = $read('src/layout/activity_unit_helpers.php');
    $edit = $read('public/user/editatividade.php');
    $activitiesPage = $read('public/user/atividades.php');
    $js = $read('public/assets/js/atividades.js');
    $css = $read('public/assets/css/atividades.css');

    $assert(str_contains($migration, 'ADD COLUMN IF NOT EXISTS usa_trechos BOOLEAN NOT NULL DEFAULT FALSE'), 'Migration não cria o estado explícito de trechos');
    $assert(str_contains($migration, 'ADD COLUMN IF NOT EXISTS idmodalidade VARCHAR(21)') && str_contains($migration, 'distancia_metros NUMERIC') && str_contains($migration, 'duracao_segundos INTEGER') && str_contains($migration, 'elevacao_m NUMERIC'), 'Migration não cria métricas canônicas e modalidade por trecho');
    $assert(str_contains($migration, 'metricas_legadas AS') && str_contains($migration, 'valor_normalizado'), 'Migration não reaproveita métricas antigas dos trechos');
    $assert(str_contains($migration, 'FROM rotas_unidades_atividade ru') && str_contains($migration, 'COALESCE(ua.distancia_metros, ru.distancia_metros)'), 'Migration não usa rota individual como fallback de distância');
    $assert(str_contains($migration, 'HAVING COUNT(*) > 1'), 'Migration não reconhece sessões antigas com múltiplas unidades');

    $assert(str_contains($model, '$segmentsEnabled = !empty($payload[\'usa_trechos\'])'), 'Salvar atividade não lê o modo de trechos');
    $assert(str_contains($model, "['rota_coordenadas'] = " . '$generalRouteRaw') && str_contains($model, "['rota_coordenadas'] = ''"), 'Rota geral não é migrada para o primeiro trecho');
    $assert(str_contains($model, "in_array(" . '$slug' . ", ['distancia', 'duracao', 'elevacao', 'desnivel'], true)) continue;"), 'Métricas gerais antigas continuam sendo salvas como segunda fonte de verdade');
    $assert(str_contains($model, '$segmentDistanceM === null && $unitRouteDistance !== null'), 'Backend não usa distância da rota do trecho quando a distância não foi informada');
    $assert(str_contains($model, '$unitColumns = [\'idunidade_atividade\', \'idregistro\', \'ordem\'') && str_contains($model, '$unitColumns[] = \'idmodalidade\'') && str_contains($model, '$unitColumns[] = \'distancia_metros\'') && str_contains($model, '$unitColumns[] = \'duracao_segundos\'') && str_contains($model, '$unitColumns[] = \'elevacao_m\''), 'Trecho não persiste modalidade e métricas canônicas de forma compatível');

    $assert(str_contains($helpers, 'data-segment-distance') && str_contains($helpers, 'data-segment-duration') && str_contains($helpers, 'data-segment-elevation'), 'Editor não possui campos canônicos por trecho');
    $assert(str_contains($panels, 'activity_model_panel_shared.php') && str_contains($sharedPanel, 'data-enable-segments') && str_contains($sharedPanel, 'data-close-segments') && str_contains($sharedPanel, 'data-primary-segment-core'), 'Criação não possui fluxo de ativar e fechar trechos');
    $assert(str_contains($edit, 'activity_model_panel_shared.php') && str_contains($sharedPanel, 'data-enable-segments') && str_contains($sharedPanel, 'data-close-segments') && str_contains($sharedPanel, 'atividadeRenderizarMetricasCanonicasTrecho'), 'Edição não acompanha o fluxo de trechos');

    $assert(str_contains($js, 'function setSegmentMode(panel, active') && str_contains($js, 'transferGeneralRouteToPrimary(panel)') && str_contains($js, 'transferPrimaryRouteToGeneral(panel)'), 'Frontend não converte atividade simples e sessão nos dois sentidos');
    $assert(str_contains($js, "input = activeUnitContext.querySelector('[data-segment-distance]')") && str_contains($js, "input.dataset.routeAutoFilled = '1'"), 'Rota individual não preenche a distância canônica editável');
    $assert(str_contains($js, "distanceInput.dataset.routeAutoFilled = '0'"), 'Distância preenchida pela rota não respeita edição manual');
    $assert(str_contains($js, "label.textContent = tr('activity.session_summary')") && str_contains($js, "summaryWrap.hidden = parts.length === 0"), 'Resumo somente leitura da sessão não acompanha os trechos');
    $assert(str_contains($js, "remove.closest('[data-unit-index]')?.remove()") && !str_contains($js, "remove.closest('[data-unit-index]')?.remove(); setSegmentMode"), 'Remover o segundo trecho fecha o modo de sessão automaticamente');
    $assert(str_contains($js, "const scope = segmented ? SHARE_SCOPES.session : SHARE_SCOPES.activity") && str_contains($js, "shareMasterSwitch.dataset.shareScope = scope"), 'Compartilhamento não abre sessão inteira por padrão quando há trechos');

    $assert(str_contains($presenter, 'function atividadeTotaisCanonicosUnidades') && str_contains($presenter, "'distancia_m' => ") && str_contains($presenter, '$distanceCount > 0 ? $distanceM : null'), 'Presenter não agrega métricas canônicas parciais');
    $assert(str_contains($presenter, 'metricas_trechos AS') && str_contains($presenter, 'rp.usa_trechos AND COALESCE(st.distancia_m, 0) > 0'), 'Resumo semanal não considera métricas dos trechos');
    $assert(str_contains($presenter, 'if (count($types) > 1) return null;'), 'Presenter calcula uma métrica derivada enganosa para modalidades incompatíveis');
    $assert(str_contains($presenter, "'usa_trechos' => !empty(" . '$registro' . "['usa_trechos'])") && str_contains($presenter, "'rota' => " . '$unitRoute'), 'Payload de detalhe/compartilhamento não expõe a sessão e suas rotas individuais');

    $assert(str_contains($css, '.activity-segments-entry') && str_contains($css, '.activity-model-panel.is-segmented'), 'Trechos não possuem estado visual próprio');

    $assert(str_contains($activitiesPage, "'permitir_campos_vazios' => true"), 'Registro manual deve aceitar atividade parcial sem exigir todos os campos.');
    $assert(str_contains($panels, "'enforce_required' => false") && str_contains($sharedPanel, '$enforceRequired'), 'Campos do registro manual continuam exigindo required no navegador.');
    $assert(str_contains($css, '.activity-primary-unit-header[hidden]') && str_contains($css, '.activity-segments-workspace[hidden]'), 'Trecho 1 e workspace continuam visíveis quando o modo de trechos está fechado.');
    $assert(str_contains($js, "const openEditor = () =>") && str_contains($js, "loadEditorDetails(modelSelect?.value || '')"), 'Campos do registro não são pré-carregados antes da abertura do modal.');
    $assert(str_contains($model, '$allowPartialFields = !empty($payload[\'permitir_campos_vazios\'])') && str_contains($model, 'Mesmo sem o modo de trechos, mantenha as métricas canônicas da unidade'), 'Backend deve aceitar campos parciais e sincronizar métricas simples.');
    $assert(str_contains($model, 'stridebr_db_column_exists($pdo, \'registros_atividade\', \'usa_trechos\')') && str_contains($model, 'stridebr_db_column_exists($pdo, \'unidades_atividade\', \'distancia_metros\')'), 'Salvar atividade básica continua dependendo rigidamente das colunas novas.');
    $assert(str_contains($model, 'StrideBR activity preference after save') && str_contains($activitiesPage, 'StrideBR analytics after activity save'), 'Metadados auxiliares ainda podem transformar um salvamento concluído em erro para o usuário.');

    echo "✓ activity segments v2 static ($assertions assertions)\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "✗ activity segments v2 static\n  {$e->getMessage()}\n");
    exit(1);
}
