<?php
require_once __DIR__ . '/activity_unit_route.php';
require_once __DIR__ . '/activity_unit_helpers.php';

$panel = is_array($activityPanel ?? null) ? $activityPanel : [];
$modelo = is_array($panel['modelo'] ?? null) ? $panel['modelo'] : [];
$panelId = (string) ($panel['panel_id'] ?? ($modelo['idmodelo'] ?? ''));
$namePrefix = (string) ($panel['name_prefix'] ?? '');
$htmlIdPrefix = (string) ($panel['html_id_prefix'] ?? ('unit_' . $panelId));
$dataUnitsModel = (string) ($panel['data_units_model'] ?? $panelId);
$initiallyHidden = !empty($panel['hidden']);
$enforceRequired = !empty($panel['enforce_required']);
$unitFields = is_array($panel['unit_fields'] ?? null) ? $panel['unit_fields'] : [];
$recordFields = is_array($panel['record_fields'] ?? null) ? $panel['record_fields'] : [];
$primaryUnitData = is_array($panel['primary_unit'] ?? null) ? $panel['primary_unit'] : [];
$primaryValues = is_array($primaryUnitData['values'] ?? null) ? $primaryUnitData['values'] : [];
$extraUnits = is_array($panel['extra_units'] ?? null) ? $panel['extra_units'] : [];
$recordValues = is_array($panel['record_values'] ?? null) ? $panel['record_values'] : [];
$segmentsActive = !empty($panel['segments_active']);
$segmentsSuggested = !empty($modelo['permite_multiplas_unidades']);
$attemptMode = (string) ($modelo['tipo_unidade_padrao'] ?? '') === 'tentativa';
$primarySport = trim((string) ($primaryUnitData['idmodalidade'] ?? '')) ?: (string) ($modelo['idmodalidade'] ?? '');
$primaryDerivedType = atividadeMetricaDerivadaModalidadeCatalogo($catalogo, $primarySport, (string) ($modelo['metrica_derivada'] ?? 'nenhuma'));
$primarySportContext = atividadeContextoModalidadeCatalogo($catalogo, $primarySport);
$modelSportContext = atividadeContextoEsportivo((string) ($modelo['modalidade_slug'] ?? ''), (string) ($modelo['familia_hub'] ?? ''));
$primaryUnitRoute = $primaryUnitData['rota_coordenadas'] ?? ($primaryUnitData['rota']['coordenadas'] ?? '');
$optionalFields = [];
foreach (array_merge($unitFields, $recordFields) as $field) {
    if (atividadeCampoOpcional($field)) $optionalFields[] = $field;
}
$unitOptionalFields = array_values(array_filter($unitFields, static fn(array $field): bool => atividadeCampoOpcional($field)));
$renderOptionalBar = static function (array $fields, string $scope): string {
    if ($fields === []) return '';
    $html = '<div class="optional-fields-bar" data-optional-fields-bar data-optional-scope="' . stridebr_e($scope) . '">';
    $html .= '<span>' . stridebr_e(stridebr_t('activity.optional_data')) . '</span>';
    foreach ($fields as $field) {
        $slug = (string) ($field['slug'] ?? '');
        $html .= '<button type="button" class="optional-field-chip" data-show-optional-field="' . stridebr_e($slug) . '">+ ' . stridebr_e(atividadeRotuloCampoEditor($field)) . '</button>';
    }
    return $html . '</div>';
};
$distanceUnit = '';
foreach ($unitFields as $field) {
    if (($field['slug'] ?? '') === 'distancia') {
        $distanceUnit = (string) ($field['unidade_simbolo'] ?? '');
        break;
    }
}
$unitName = static fn(string $index): string => $namePrefix === '' ? "unidades[{$index}]" : "{$namePrefix}[unidades][{$index}]";
$recordName = static fn(string $id): string => $namePrefix === '' ? "record_values[{$id}]" : "{$namePrefix}[record_values][{$id}]";
$segmentModeName = $namePrefix === '' ? 'usa_trechos' : "{$namePrefix}[usa_trechos]";
?>
<section
    class="activity-model-panel<?php echo $segmentsActive ? ' is-segmented' : ''; ?>"
    data-model-panel="<?php echo stridebr_e($panelId); ?>"
    data-derived-type="<?php echo stridebr_e((string) ($modelo['metrica_derivada'] ?? 'nenhuma')); ?>"
    data-distance-unit="<?php echo stridebr_e($distanceUnit); ?>"
    data-main-modality="<?php echo stridebr_e((string) ($modelo['idmodalidade'] ?? '')); ?>"
    data-sport-slug="<?php echo stridebr_e((string) ($modelo['modalidade_slug'] ?? '')); ?>"
    data-sport-family="<?php echo stridebr_e((string) ($modelo['familia_hub'] ?? '')); ?>"
    data-unit-kind="<?php echo stridebr_e((string) ($modelo['tipo_unidade_padrao'] ?? 'unidade')); ?>"
    data-segments-suggested="<?php echo $segmentsSuggested ? '1' : '0'; ?>"
    <?php echo $initiallyHidden ? 'hidden' : ''; ?>
>
    <input type="hidden" name="<?php echo stridebr_e($segmentModeName); ?>" value="<?php echo $segmentsActive ? '1' : '0'; ?>" data-segment-mode-input>

    <div class="activity-primary-unit-card" data-primary-unit-card>
        <div class="activity-unit-header activity-primary-unit-header" data-primary-unit-header<?php echo $segmentsActive ? '' : ' hidden'; ?>>
            <div><strong data-primary-unit-title><?php echo stridebr_e((string) ($modelo['rotulo_unidade'] ?? stridebr_t('activity.segment'))); ?> 1</strong><span><?php echo stridebr_e($attemptMode ? stridebr_t('activity.first_attempt') : stridebr_t('activity.first_segment')); ?></span></div>
            <button type="button" class="activity-link-danger" data-close-segments><?php echo stridebr_e(stridebr_t('activity.close_segments')); ?></button>
        </div>
        <?php if (!$attemptMode): ?>
            <div class="activity-unit-context" data-primary-unit-context<?php echo $segmentsActive ? '' : ' hidden'; ?>>
                <?php echo atividadeRenderizarSeletorModalidadeUnidade($unitName('0') . '[idmodalidade]', $catalogo, $primarySport, (string) ($modelo['idmodalidade'] ?? '')); ?>
                <div class="input-field"><label><?php echo stridebr_e(stridebr_t('activity.segment_name')); ?> <span class="field-hint"><?php echo stridebr_e(stridebr_t('common.optional')); ?></span></label><input type="text" name="<?php echo stridebr_e($unitName('0') . '[rotulo]'); ?>" maxlength="120" value="<?php echo stridebr_e((string) ($primaryUnitData['rotulo'] ?? '')); ?>" placeholder="<?php echo stridebr_e(stridebr_t('activity.segment_example_warmup')); ?>"></div>
            </div>
            <div data-primary-segment-core<?php echo $segmentsActive ? '' : ' hidden'; ?>>
                <?php echo atividadeRenderizarMetricasCanonicasTrecho($unitName('0'), $primaryUnitData, $primaryDerivedType, $unitFields, $primarySportContext); ?>
            </div>
        <?php endif; ?>
        <div class="activity-metrics-strip" data-primary-unit data-model-unit-fields>
            <?php foreach ($unitFields as $campo): ?>
                <?php echo atividadeRenderizarCampo($campo, $unitName('0') . "[values][{$campo['idcampo']}]", $htmlIdPrefix . "_0_{$campo['idcampo']}", $primaryValues[$campo['idcampo']] ?? null, $enforceRequired); ?>
            <?php endforeach; ?>
            <div class="input-field derived-metric-field" data-derived-field>
                <label><span data-derived-label><?php echo stridebr_e(stridebr_t('activity.pace')); ?></span> <span class="field-hint"><?php echo stridebr_e(stridebr_t('activity.calculated')); ?></span></label>
                <div class="derived-input-wrap derived-result-wrap">
                    <input type="text" inputmode="decimal" data-derived-input placeholder="--:--" readonly aria-readonly="true">
                    <span data-derived-unit>/km</span>
                    <button type="button" class="derived-edit-button" data-edit-derived><?php echo stridebr_e(stridebr_t('activity.edit')); ?></button>
                    <span class="auto-badge" data-derived-badge><?php echo stridebr_e(stridebr_t('activity.calculated')); ?></span>
                </div>
            </div>
        </div>

        <?php echo $renderOptionalBar($optionalFields, 'panel'); ?>
        <?php if (!$attemptMode): ?>
            <div data-primary-unit-optional-wrap<?php echo $segmentsActive ? '' : ' hidden'; ?>><?php echo $renderOptionalBar($unitOptionalFields, 'unit'); ?></div>
        <?php endif; ?>

        <div class="activity-segments-entry<?php echo $segmentsSuggested ? ' is-suggested' : ' is-subtle'; ?>" data-segments-entry<?php echo $segmentsActive ? ' hidden' : ''; ?>>
            <div><strong><?php echo stridebr_e($attemptMode ? stridebr_t('activity.attempts') : stridebr_t('activity.segments')); ?></strong><span><?php echo stridebr_e($attemptMode ? stridebr_t('activity.attempts_help') : ($segmentsSuggested ? stridebr_t('activity.segments_suggested_help') : stridebr_t('activity.segments_optional_help'))); ?></span></div>
            <button type="button" class="activity-inline-action activity-row-action" data-enable-segments>+ <?php echo stridebr_e(stridebr_t('common.add')); ?></button>
        </div>

        <?php if (!$attemptMode): ?>
            <div data-primary-unit-route-wrap<?php echo $segmentsActive ? '' : ' hidden'; ?>>
                <?php echo atividadeRenderizarRotaUnidade($unitName('0'), (string) ($modelo['rotulo_unidade'] ?? stridebr_t('activity.segment')) . ' 1', $primaryUnitRoute, $primaryUnitData); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($recordFields): ?>
        <div class="activity-record-fields">
            <?php foreach ($recordFields as $campo): ?>
                <?php echo atividadeRenderizarCampo($campo, $recordName((string) $campo['idcampo']), $htmlIdPrefix . "_record_{$campo['idcampo']}", $recordValues[$campo['idcampo']] ?? null, $enforceRequired); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="activity-segments-workspace" data-segments-workspace<?php echo $segmentsActive ? '' : ' hidden'; ?>>
        <div class="activity-extra-units" data-units data-model="<?php echo stridebr_e($dataUnitsModel); ?>">
            <?php foreach ($extraUnits as $rawIndex => $unitData): ?>
                <?php
                $unitIndex = max(1, (int) $rawIndex);
                $unitData = is_array($unitData) ? $unitData : [];
                $unitValues = is_array($unitData['values'] ?? null) ? $unitData['values'] : [];
                $unitSport = trim((string) ($unitData['idmodalidade'] ?? '')) ?: (string) ($modelo['idmodalidade'] ?? '');
                $unitDerivedType = atividadeMetricaDerivadaModalidadeCatalogo($catalogo, $unitSport, (string) ($modelo['metrica_derivada'] ?? 'nenhuma'));
                $unitSportContext = atividadeContextoModalidadeCatalogo($catalogo, $unitSport);
                ?>
                <div class="activity-unit" data-unit-index="<?php echo $unitIndex; ?>">
                    <div class="activity-unit-header"><div><strong data-unit-title><?php echo stridebr_e((string) ($modelo['rotulo_unidade'] ?? stridebr_t('activity.segment'))); ?> <?php echo $unitIndex + 1; ?></strong><span><?php echo stridebr_e($attemptMode ? stridebr_t('activity.attempt_registered') : stridebr_t('activity.session_part')); ?></span></div><button type="button" class="activity-link-danger" data-remove-unit><?php echo stridebr_e(stridebr_t('common.remove')); ?></button></div>
                    <?php if (!$attemptMode): ?>
                        <div class="activity-unit-context">
                            <?php echo atividadeRenderizarSeletorModalidadeUnidade($unitName((string) $unitIndex) . '[idmodalidade]', $catalogo, $unitSport, (string) ($modelo['idmodalidade'] ?? '')); ?>
                            <div class="input-field"><label><?php echo stridebr_e(stridebr_t('activity.segment_name')); ?> <span class="field-hint"><?php echo stridebr_e(stridebr_t('common.optional')); ?></span></label><input type="text" name="<?php echo stridebr_e($unitName((string) $unitIndex) . '[rotulo]'); ?>" maxlength="120" value="<?php echo stridebr_e((string) ($unitData['rotulo'] ?? '')); ?>" placeholder="<?php echo stridebr_e(stridebr_t('activity.segment_example', ['label' => stridebr_t('activity.segment'), 'number' => $unitIndex + 1])); ?>"></div>
                        </div>
                        <?php echo atividadeRenderizarMetricasCanonicasTrecho($unitName((string) $unitIndex), $unitData, $unitDerivedType, $unitFields, $unitSportContext); ?>
                    <?php endif; ?>
                    <div class="activity-unit-grid" data-model-unit-fields>
                        <?php foreach ($unitFields as $campo): ?><?php echo atividadeRenderizarCampo($campo, $unitName((string) $unitIndex) . "[values][{$campo['idcampo']}]", $htmlIdPrefix . "_{$unitIndex}_{$campo['idcampo']}", $unitValues[$campo['idcampo']] ?? null, $enforceRequired); ?><?php endforeach; ?>
                    </div>
                    <?php if (!$attemptMode) echo $renderOptionalBar($unitOptionalFields, 'unit'); ?>
                    <?php if (!$attemptMode) echo atividadeRenderizarRotaUnidade($unitName((string) $unitIndex), (string) ($modelo['rotulo_unidade'] ?? stridebr_t('activity.segment')) . ' ' . ($unitIndex + 1), $unitData['rota_coordenadas'] ?? ($unitData['rota']['coordenadas'] ?? ''), $unitData); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <template data-unit-template="<?php echo stridebr_e($dataUnitsModel); ?>">
            <div class="activity-unit" data-unit-index="__INDEX__">
                <div class="activity-unit-header"><div><strong data-unit-title><?php echo stridebr_e((string) ($modelo['rotulo_unidade'] ?? stridebr_t('activity.segment'))); ?> __NUMBER__</strong><span><?php echo stridebr_e($attemptMode ? stridebr_t('activity.attempt_registered') : stridebr_t('activity.session_part')); ?></span></div><button type="button" class="activity-link-danger" data-remove-unit><?php echo stridebr_e(stridebr_t('common.remove')); ?></button></div>
                <?php if (!$attemptMode): ?>
                    <div class="activity-unit-context">
                        <?php echo atividadeRenderizarSeletorModalidadeUnidade($unitName('__INDEX__') . '[idmodalidade]', $catalogo, (string) ($modelo['idmodalidade'] ?? ''), (string) ($modelo['idmodalidade'] ?? '')); ?>
                        <div class="input-field"><label><?php echo stridebr_e(stridebr_t('activity.segment_name')); ?> <span class="field-hint"><?php echo stridebr_e(stridebr_t('common.optional')); ?></span></label><input type="text" name="<?php echo stridebr_e($unitName('__INDEX__') . '[rotulo]'); ?>" maxlength="120" placeholder="<?php echo stridebr_e(stridebr_t('activity.segment_example', ['label' => (string) ($modelo['rotulo_unidade'] ?? stridebr_t('activity.segment')), 'number' => '__NUMBER__'])); ?>"></div>
                    </div>
                    <?php echo atividadeRenderizarMetricasCanonicasTrecho($unitName('__INDEX__'), [], (string) ($modelo['metrica_derivada'] ?? 'nenhuma'), $unitFields, $modelSportContext); ?>
                <?php endif; ?>
                <div class="activity-unit-grid" data-model-unit-fields>
                    <?php foreach ($unitFields as $campo): ?><?php echo atividadeRenderizarCampo($campo, $unitName('__INDEX__') . "[values][{$campo['idcampo']}]", $htmlIdPrefix . "___INDEX___{$campo['idcampo']}", null, $enforceRequired); ?><?php endforeach; ?>
                </div>
                <?php if (!$attemptMode) echo $renderOptionalBar($unitOptionalFields, 'unit'); ?>
                <?php if (!$attemptMode) echo atividadeRenderizarRotaUnidade($unitName('__INDEX__'), (string) ($modelo['rotulo_unidade'] ?? stridebr_t('activity.segment')) . ' __NUMBER__', ''); ?>
            </div>
        </template>
        <button type="button" class="add-unit-button" data-add-unit="<?php echo stridebr_e($dataUnitsModel); ?>"><?php echo stridebr_e(stridebr_t('activity.add_unit', ['unit' => stridebr_lower((string) ($modelo['rotulo_unidade'] ?? stridebr_t('activity.segment')))])); ?></button>
    </div>
</section>
