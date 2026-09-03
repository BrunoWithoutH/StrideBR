<?php require_once __DIR__ . '/activity_unit_route.php'; ?>
<?php require_once __DIR__ . '/activity_unit_helpers.php'; ?>
<?php foreach ($modelosDetalhados as $idModelo => $modelo): ?>
    <?php
    $unitFields = $modelo['campos_agrupados']['unidade'];
    usort($unitFields, static fn(array $a, array $b): int => atividadeCampoOrdemVisual($a) <=> atividadeCampoOrdemVisual($b));
    $recordFields = $modelo['campos_agrupados']['registro'];
    $optionalFields = [];
    foreach (array_merge($unitFields, $recordFields) as $field) {
        if (atividadeCampoOpcional($field)) $optionalFields[] = $field;
    }
    $formModelPayload = is_array($_POST['models'][$idModelo] ?? null) ? $_POST['models'][$idModelo] : [];
    $baseUnits = $idModelo === $formModelo ? (array) ($repeatRecord['unidades'] ?? []) : [];
    $primaryUnitData = is_array($formModelPayload['unidades'][0] ?? null) ? $formModelPayload['unidades'][0] : (array) ($baseUnits[0] ?? []);
    $primaryValues = is_array($primaryUnitData['values'] ?? null) ? $primaryUnitData['values'] : [];
    $primaryUnitRoute = $primaryUnitData['rota_coordenadas'] ?? ($primaryUnitData['rota']['coordenadas'] ?? '');
    $extraUnits = is_array($formModelPayload['unidades'] ?? null) ? array_slice($formModelPayload['unidades'], 1, null, true) : array_slice($baseUnits, 1, null, true);
    $segmentsPosted = array_key_exists('usa_trechos', $formModelPayload) ? !empty($formModelPayload['usa_trechos']) : null;
    $segmentsActive = $segmentsPosted ?? ($idModelo === $formModelo && (!empty($repeatRecord['usa_trechos']) || count($baseUnits) > 1));
    $primarySport = trim((string) ($primaryUnitData['idmodalidade'] ?? '')) ?: (string) $modelo['idmodalidade'];
    $primaryDerivedType = atividadeMetricaDerivadaModalidadeCatalogo($catalogo, $primarySport, (string) $modelo['metrica_derivada']);
    $segmentsSuggested = !empty($modelo['permite_multiplas_unidades']);
    $attemptMode = (string) ($modelo['tipo_unidade_padrao'] ?? '') === 'tentativa';
    ?>
    <section
        class="activity-model-panel<?php echo $segmentsActive ? ' is-segmented' : ''; ?>"
        data-model-panel="<?php echo stridebr_e($idModelo); ?>"
        data-derived-type="<?php echo stridebr_e($modelo['metrica_derivada']); ?>"
        data-distance-unit="<?php
            $distanceUnit = '';
            foreach ($unitFields as $field) {
                if (($field['slug'] ?? '') === 'distancia') {
                    $distanceUnit = (string) ($field['unidade_simbolo'] ?? '');
                    break;
                }
            }
            echo stridebr_e($distanceUnit);
        ?>"
        data-main-modality="<?php echo stridebr_e((string) $modelo['idmodalidade']); ?>"
        data-unit-kind="<?php echo stridebr_e((string) ($modelo['tipo_unidade_padrao'] ?? 'unidade')); ?>"
        data-segments-suggested="<?php echo $segmentsSuggested ? '1' : '0'; ?>"
        hidden
    >
        <input type="hidden" name="models[<?php echo stridebr_e($idModelo); ?>][usa_trechos]" value="<?php echo $segmentsActive ? '1' : '0'; ?>" data-segment-mode-input>

        <div class="activity-primary-unit-card" data-primary-unit-card>
            <div class="activity-unit-header activity-primary-unit-header" data-primary-unit-header<?php echo $segmentsActive ? '' : ' hidden'; ?>>
                <div><strong data-primary-unit-title><?php echo stridebr_e($modelo['rotulo_unidade']); ?> 1</strong><span><?php echo $attemptMode ? 'Primeira tentativa' : 'Primeira parte da sessão'; ?></span></div>
                <button type="button" class="activity-link-danger" data-close-segments>Fechar trechos</button>
            </div>
            <?php if (!$attemptMode): ?>
                <div class="activity-unit-context" data-primary-unit-context<?php echo $segmentsActive ? '' : ' hidden'; ?>>
                    <?php echo atividadeRenderizarSeletorModalidadeUnidade("models[{$idModelo}][unidades][0][idmodalidade]", $catalogo, $primarySport, (string) $modelo['idmodalidade']); ?>
                    <div class="input-field"><label>Nome do trecho <span class="field-hint">opcional</span></label><input type="text" name="models[<?php echo stridebr_e($idModelo); ?>][unidades][0][rotulo]" maxlength="120" value="<?php echo stridebr_e((string) ($primaryUnitData['rotulo'] ?? '')); ?>" placeholder="Ex.: Aquecimento"></div>
                </div>
                <div data-primary-segment-core<?php echo $segmentsActive ? '' : ' hidden'; ?>>
                    <?php echo atividadeRenderizarMetricasCanonicasTrecho("models[{$idModelo}][unidades][0]", $primaryUnitData, $primaryDerivedType, $unitFields); ?>
                </div>
            <?php endif; ?>
            <div class="activity-metrics-strip" data-primary-unit data-model-unit-fields>
                <?php foreach ($unitFields as $campo): ?>
                    <?php echo atividadeRenderizarCampo($campo, "models[{$idModelo}][unidades][0][values][{$campo['idcampo']}]", "unit_{$idModelo}_0_{$campo['idcampo']}", $primaryValues[$campo['idcampo']] ?? null, false); ?>
                <?php endforeach; ?>

                    <div class="input-field derived-metric-field" data-derived-field>
                        <label><span data-derived-label>Ritmo</span> <span class="field-hint">calculado</span></label>
                        <div class="derived-input-wrap derived-result-wrap">
                            <input type="text" inputmode="decimal" data-derived-input placeholder="--:--" readonly aria-readonly="true">
                            <span data-derived-unit>/km</span>
                            <button type="button" class="derived-edit-button" data-edit-derived>Editar</button>
                            <span class="auto-badge" data-derived-badge>Calculado</span>
                        </div>
                    </div>
            </div>

            <?php if ($optionalFields): ?>
                <div class="optional-fields-bar" data-optional-fields-bar>
                    <span>Dados opcionais</span>
                    <?php foreach ($optionalFields as $campo): ?>
                        <button type="button" class="optional-field-chip" data-show-optional-field="<?php echo stridebr_e($campo['slug']); ?>">+ <?php echo stridebr_e($campo['rotulo']); ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="activity-segments-entry<?php echo $segmentsSuggested ? ' is-suggested' : ' is-subtle'; ?>" data-segments-entry<?php echo $segmentsActive ? ' hidden' : ''; ?>>
                <div><strong><?php echo $attemptMode ? 'Tentativas' : 'Trechos'; ?></strong><span><?php echo $attemptMode ? 'Registre cada salto, arremesso ou lançamento.' : ($segmentsSuggested ? 'Tiros, voltas ou etapas.' : 'Divida a atividade em partes, se precisar.'); ?></span></div>
                <button type="button" class="activity-inline-action activity-row-action" data-enable-segments>+ Adicionar</button>
            </div>

            <?php if (!$attemptMode): ?>
                <div data-primary-unit-route-wrap<?php echo $segmentsActive ? '' : ' hidden'; ?>>
                    <?php echo atividadeRenderizarRotaUnidade("models[{$idModelo}][unidades][0]", $modelo['rotulo_unidade'] . ' 1', $primaryUnitRoute); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($recordFields): ?>
            <div class="activity-record-fields">
                <?php $recordValues = is_array($formModelPayload['record_values'] ?? null) ? $formModelPayload['record_values'] : ($idModelo === $formModelo ? (array) ($repeatRecord['record_values'] ?? []) : []); ?>
                <?php foreach ($recordFields as $campo): ?>
                    <?php echo atividadeRenderizarCampo($campo, "models[{$idModelo}][record_values][{$campo['idcampo']}]", "record_{$idModelo}_{$campo['idcampo']}", $recordValues[$campo['idcampo']] ?? null, false); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="activity-segments-workspace" data-segments-workspace<?php echo $segmentsActive ? '' : ' hidden'; ?>>
            <div class="activity-extra-units" data-units data-model="<?php echo stridebr_e($idModelo); ?>">
                <?php foreach ($extraUnits as $unitIndex => $unitData): ?>
                    <?php
                    $unitIndex = max(1, (int) $unitIndex);
                    $unitValues = (array) ($unitData['values'] ?? []);
                    $unitSport = trim((string) ($unitData['idmodalidade'] ?? '')) ?: (string) $modelo['idmodalidade'];
                    $unitDerivedType = atividadeMetricaDerivadaModalidadeCatalogo($catalogo, $unitSport, (string) $modelo['metrica_derivada']);
                    ?>
                    <div class="activity-unit" data-unit-index="<?php echo $unitIndex; ?>">
                        <div class="activity-unit-header"><div><strong data-unit-title><?php echo stridebr_e($modelo['rotulo_unidade']); ?> <?php echo $unitIndex + 1; ?></strong><span><?php echo $attemptMode ? 'Tentativa registrada' : 'Parte da sessão'; ?></span></div><button type="button" class="activity-link-danger" data-remove-unit>Remover</button></div>
                        <?php if (!$attemptMode): ?>
                            <div class="activity-unit-context">
                                <?php echo atividadeRenderizarSeletorModalidadeUnidade("models[{$idModelo}][unidades][{$unitIndex}][idmodalidade]", $catalogo, $unitSport, (string) $modelo['idmodalidade']); ?>
                                <div class="input-field"><label>Nome do trecho <span class="field-hint">opcional</span></label><input type="text" name="models[<?php echo stridebr_e($idModelo); ?>][unidades][<?php echo $unitIndex; ?>][rotulo]" maxlength="120" value="<?php echo stridebr_e((string) ($unitData['rotulo'] ?? '')); ?>" placeholder="Ex.: Tiro forte"></div>
                            </div>
                            <?php echo atividadeRenderizarMetricasCanonicasTrecho("models[{$idModelo}][unidades][{$unitIndex}]", $unitData, $unitDerivedType, $unitFields); ?>
                        <?php endif; ?>
                        <div class="activity-unit-grid" data-model-unit-fields>
                            <?php foreach ($unitFields as $campo): ?><?php echo atividadeRenderizarCampo($campo, "models[{$idModelo}][unidades][{$unitIndex}][values][{$campo['idcampo']}]", "unit_{$idModelo}_{$unitIndex}_{$campo['idcampo']}", $unitValues[$campo['idcampo']] ?? null, false); ?><?php endforeach; ?>
                        </div>
                        <?php if (!$attemptMode) echo atividadeRenderizarRotaUnidade("models[{$idModelo}][unidades][{$unitIndex}]", $modelo['rotulo_unidade'] . ' ' . ($unitIndex + 1), $unitData['rota_coordenadas'] ?? ($unitData['rota']['coordenadas'] ?? '')); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <template data-unit-template="<?php echo stridebr_e($idModelo); ?>">
                <div class="activity-unit" data-unit-index="__INDEX__">
                    <div class="activity-unit-header"><div><strong data-unit-title><?php echo stridebr_e($modelo['rotulo_unidade']); ?> __NUMBER__</strong><span><?php echo $attemptMode ? 'Tentativa registrada' : 'Parte da sessão'; ?></span></div><button type="button" class="activity-link-danger" data-remove-unit>Remover</button></div>
                    <?php if (!$attemptMode): ?>
                        <div class="activity-unit-context">
                            <?php echo atividadeRenderizarSeletorModalidadeUnidade("models[{$idModelo}][unidades][__INDEX__][idmodalidade]", $catalogo, (string) $modelo['idmodalidade'], (string) $modelo['idmodalidade']); ?>
                            <div class="input-field"><label>Nome do trecho <span class="field-hint">opcional</span></label><input type="text" name="models[<?php echo stridebr_e($idModelo); ?>][unidades][__INDEX__][rotulo]" maxlength="120" placeholder="Ex.: <?php echo stridebr_e($modelo['rotulo_unidade']); ?> __NUMBER__"></div>
                        </div>
                        <?php echo atividadeRenderizarMetricasCanonicasTrecho("models[{$idModelo}][unidades][__INDEX__]", [], (string) $modelo['metrica_derivada'], $unitFields); ?>
                    <?php endif; ?>
                    <div class="activity-unit-grid" data-model-unit-fields>
                        <?php foreach ($unitFields as $campo): ?><?php echo atividadeRenderizarCampo($campo, "models[{$idModelo}][unidades][__INDEX__][values][{$campo['idcampo']}]", "unit_{$idModelo}___INDEX___{$campo['idcampo']}", null, false); ?><?php endforeach; ?>
                    </div>
                    <?php if (!$attemptMode) echo atividadeRenderizarRotaUnidade("models[{$idModelo}][unidades][__INDEX__]", $modelo['rotulo_unidade'] . ' __NUMBER__', ''); ?>
                </div>
            </template>
            <button type="button" class="add-unit-button" data-add-unit="<?php echo stridebr_e($idModelo); ?>">+ Adicionar <?php echo stridebr_lower(stridebr_e($modelo['rotulo_unidade'])); ?></button>
        </div>


    </section>
<?php endforeach; ?>
