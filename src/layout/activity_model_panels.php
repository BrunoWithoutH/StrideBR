<?php
require_once __DIR__ . '/activity_unit_route.php';
require_once __DIR__ . '/activity_unit_helpers.php';
foreach ($modelosDetalhados as $idModelo => $modelo):
    $unitFields = $modelo['campos_agrupados']['unidade'];
    usort($unitFields, static fn(array $a, array $b): int => atividadeCampoOrdemVisual($a) <=> atividadeCampoOrdemVisual($b));
    $recordFields = $modelo['campos_agrupados']['registro'];
    $formModelPayload = is_array($_POST['models'][$idModelo] ?? null) ? $_POST['models'][$idModelo] : [];
    $baseUnits = $idModelo === $formModelo ? (array) ($repeatRecord['unidades'] ?? []) : [];
    $primaryUnitData = is_array($formModelPayload['unidades'][0] ?? null) ? $formModelPayload['unidades'][0] : (array) ($baseUnits[0] ?? []);
    $extraUnits = is_array($formModelPayload['unidades'] ?? null) ? array_slice($formModelPayload['unidades'], 1, null, true) : array_slice($baseUnits, 1, null, true);
    $segmentsPosted = array_key_exists('usa_trechos', $formModelPayload) ? !empty($formModelPayload['usa_trechos']) : null;
    $segmentsActive = $segmentsPosted ?? ($idModelo === $formModelo && (!empty($repeatRecord['usa_trechos']) || count($baseUnits) > 1));
    $recordValues = is_array($formModelPayload['record_values'] ?? null) ? $formModelPayload['record_values'] : ($idModelo === $formModelo ? (array) ($repeatRecord['record_values'] ?? []) : []);
    $activityPanel = [
        'modelo' => $modelo,
        'panel_id' => $idModelo,
        'name_prefix' => "models[{$idModelo}]",
        'html_id_prefix' => "unit_{$idModelo}",
        'data_units_model' => $idModelo,
        'hidden' => true,
        'enforce_required' => false,
        'unit_fields' => $unitFields,
        'record_fields' => $recordFields,
        'primary_unit' => $primaryUnitData,
        'extra_units' => $extraUnits,
        'record_values' => $recordValues,
        'segments_active' => $segmentsActive,
    ];
    require __DIR__ . '/activity_model_panel_shared.php';
endforeach;
