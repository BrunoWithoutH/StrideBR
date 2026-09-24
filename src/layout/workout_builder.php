<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/function/workout_prescription_v2.php';

function workoutBuilderName(string|int $index, string $suffix): string
{
    return 'rows[' . $index . '][' . $suffix . ']';
}

function workoutBuilderValue(array $row, string $key, mixed $fallback = ''): mixed
{
    return array_key_exists($key, $row) ? $row[$key] : $fallback;
}

function workoutBuilderTrackingLabel(string $mode): string
{
    return match ($mode) {
        'reps' => stridebr_t('common.repetitions'),
        'duration' => stridebr_t('common.duration'),
        'distance' => stridebr_t('common.distance'),
        'duration_distance' => stridebr_t('common.duration') . ' + ' . stridebr_t('common.distance'),
        default => stridebr_t('common.load') . ' + ' . stridebr_t('common.repetitions'),
    };
}

function workoutBuilderMethodLabel(string $method): string
{
    return stridebr_t('workout_builder.method.' . $method);
}

function workoutBuilderStepLabel(string $step): string
{
    return stridebr_t('workout_builder.step.' . $step);
}

function workoutBuilderRepValues(array $config): array
{
    $reps = is_array($config['reps'] ?? null) ? $config['reps'] : [];
    return [
        'mode' => (string) ($reps['mode'] ?? 'fixed'),
        'value' => $reps['value'] ?? '',
        'min' => $reps['min'] ?? '',
        'max' => $reps['max'] ?? '',
        'text' => $reps['text'] ?? '',
    ];
}

function workoutBuilderDurationDisplay(array $config): array
{
    $seconds = isset($config['duration_s']) && is_numeric($config['duration_s']) ? (int) $config['duration_s'] : null;
    if ($seconds === null) return ['value' => '', 'unit' => 'min'];
    if ($seconds >= 3600 && $seconds % 3600 === 0) return ['value' => $seconds / 3600, 'unit' => 'h'];
    if ($seconds >= 60 && $seconds % 60 === 0) return ['value' => $seconds / 60, 'unit' => 'min'];
    return ['value' => $seconds, 'unit' => 's'];
}

function workoutBuilderDistanceDisplay(array $config, string $sportSlug, string $trackingMode, string $stepType): array
{
    $meters = isset($config['distance_m']) && is_numeric($config['distance_m']) ? (float) $config['distance_m'] : null;
    $unit = (string) ($config['distance_display_unit'] ?? workoutPrescriptionDefaultDistanceUnit($sportSlug, $trackingMode, $stepType));
    if (!in_array($unit, ['m','km'], true)) $unit = 'm';
    $value = $meters === null ? '' : ($unit === 'km' ? $meters / 1000 : $meters);
    return ['value' => $value, 'unit' => $unit];
}

function workoutBuilderHiddenLegacy(string|int $index, array $row): string
{
    $fields = ['repeticoes','carga','descanso','duracao','distancia','rpe','rir'];
    $html = '';
    foreach ($fields as $field) {
        $html .= '<input type="hidden" name="' . stridebr_e(workoutBuilderName($index, $field)) . '" value="' . stridebr_e((string) ($row[$field] ?? '')) . '" data-wb-legacy="' . stridebr_e($field) . '">';
    }
    return $html;
}

function workoutBuilderGroupHidden(string|int $index, array $row): string
{
    $values = [
        'grupo_chave' => $row['grupo_chave'] ?? '',
        'grupo_tipo' => $row['grupo_tipo'] ?? '',
        'grupo_voltas' => $row['grupo_voltas'] ?? '',
        'grupo_descanso_entre_exercicios_s' => $row['grupo_descanso_entre_exercicios_s'] ?? '',
        'grupo_descanso_pos_volta_s' => $row['grupo_descanso_pos_volta_s'] ?? '',
    ];
    $html = '';
    foreach ($values as $field => $value) {
        $html .= '<input type="hidden" name="' . stridebr_e(workoutBuilderName($index, $field)) . '" value="' . stridebr_e((string) $value) . '" data-wb-group-field="' . stridebr_e($field) . '">';
    }
    return $html;
}

function workoutBuilderRepTargetInputs(string|int $index, array $config, string $scope = ''): string
{
    $rep = workoutBuilderRepValues($config);
    $prefix = $scope === '' ? 'prescription][reps' : $scope . '][reps';
    $base = 'rows[' . $index . '][' . $prefix . ']';
    ob_start();
    ?>
    <label class="wb-field wb-field-reps wb-rep-target">
        <span><?php echo stridebr_e(stridebr_t('workout_builder.rep_target')); ?></span>
        <select name="<?php echo stridebr_e($base . '[mode]'); ?>" data-wb-rep-mode>
            <?php if ($rep['mode'] === 'legacy'): ?><option value="legacy" selected><?php echo stridebr_e(stridebr_t('workout_builder.legacy_config')); ?></option><?php endif; ?>
            <?php foreach (['fixed','range','amrap','failure'] as $mode): ?>
                <option value="<?php echo $mode; ?>"<?php echo $rep['mode'] === $mode ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('workout_builder.rep.' . $mode)); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($rep['mode'] === 'legacy'): ?><input type="hidden" name="<?php echo stridebr_e($base . '[text]'); ?>" value="<?php echo stridebr_e((string) $rep['text']); ?>" data-wb-rep-legacy><?php endif; ?>
        <span class="wb-rep-fixed" data-wb-rep-panel="fixed"><input type="number" min="1" max="999" step="1" inputmode="numeric" name="<?php echo stridebr_e($base . '[value]'); ?>" value="<?php echo stridebr_e((string) $rep['value']); ?>" aria-label="<?php echo stridebr_e(stridebr_t('common.repetitions')); ?>"></span>
        <span class="wb-rep-range" data-wb-rep-panel="range"><input type="number" min="1" max="999" step="1" inputmode="numeric" name="<?php echo stridebr_e($base . '[min]'); ?>" value="<?php echo stridebr_e((string) $rep['min']); ?>" aria-label="<?php echo stridebr_e(stridebr_t('common.minimum')); ?>"><span>–</span><input type="number" min="1" max="999" step="1" inputmode="numeric" name="<?php echo stridebr_e($base . '[max]'); ?>" value="<?php echo stridebr_e((string) $rep['max']); ?>" aria-label="<?php echo stridebr_e(stridebr_t('common.maximum')); ?>"></span>
        <?php if ($rep['mode'] === 'legacy'): ?><span class="wb-rep-legacy" data-wb-rep-panel="legacy"><?php echo stridebr_e((string) $rep['text']); ?></span><?php endif; ?>
    </label>
    <?php
    return (string) ob_get_clean();
}

function workoutBuilderStandardFields(string|int $index, array $row, array $config, string $trackingMode, string $sportSlug, string $stepType): string
{
    $rep = workoutBuilderRepValues($config);
    $load = is_array($config['load'] ?? null) ? $config['load'] : [];
    $duration = workoutBuilderDurationDisplay($config);
    $distance = workoutBuilderDistanceDisplay($config, $sportSlug, $trackingMode, $stepType);
    $effort = is_array($config['effort'] ?? null) ? $config['effort'] : [];
    ob_start();
    ?>
    <div class="wb-method-panel" data-wb-method-panel="standard">
        <div class="wb-primary-grid">
            <label class="wb-field wb-field-sets"><span><?php echo stridebr_e(stridebr_t('common.series')); ?></span><input type="number" min="1" max="99" step="1" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][sets')); ?>" value="<?php echo stridebr_e((string) ($config['sets'] ?? $row['series'] ?? '')); ?>"></label>
            <?php echo workoutBuilderRepTargetInputs($index, $config); ?>
            <label class="wb-field wb-field-load"><span><?php echo stridebr_e(stridebr_t('common.load')); ?></span><span class="wb-unit-input"><input type="number" min="0" max="99999.999" step="0.01" inputmode="decimal" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][load][value')); ?>" value="<?php echo stridebr_e((string) ($load['value'] ?? '')); ?>"><span>kg</span></span><input type="hidden" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][load][unit')); ?>" value="kg"></label>
            <label class="wb-field wb-field-duration"><span><?php echo stridebr_e(stridebr_t('common.duration')); ?></span><span class="wb-unit-input"><input type="number" min="0" step="0.01" inputmode="decimal" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][duration][value')); ?>" value="<?php echo stridebr_e((string) $duration['value']); ?>"><select name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][duration][unit')); ?>"><option value="s"<?php echo $duration['unit'] === 's' ? ' selected' : ''; ?>>s</option><option value="min"<?php echo $duration['unit'] === 'min' ? ' selected' : ''; ?>>min</option><option value="h"<?php echo $duration['unit'] === 'h' ? ' selected' : ''; ?>>h</option></select></span></label>
            <label class="wb-field wb-field-distance"><span><?php echo stridebr_e(stridebr_t('common.distance')); ?></span><span class="wb-unit-input"><input type="number" min="0" step="<?php echo $distance['unit'] === 'm' ? '0.01' : '0.001'; ?>" inputmode="decimal" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][distance][value')); ?>" value="<?php echo stridebr_e((string) $distance['value']); ?>" data-wb-distance-value><select name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][distance][unit')); ?>" data-wb-distance-unit><option value="m"<?php echo $distance['unit'] === 'm' ? ' selected' : ''; ?>>m</option><option value="km"<?php echo $distance['unit'] === 'km' ? ' selected' : ''; ?>>km</option></select></span></label>
            <label class="wb-field wb-field-rest"><span><?php echo stridebr_e(stridebr_t('home.rest')); ?></span><span class="wb-unit-input"><input type="number" min="0" max="86400" step="1" inputmode="numeric" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][rest_after_s')); ?>" value="<?php echo stridebr_e((string) ($config['rest_after_s'] ?? '')); ?>"><span>s</span></span></label>
        </div>
        <details class="wb-more"><summary><?php echo stridebr_e(stridebr_t('workout_builder.more')); ?></summary><div class="wb-advanced-grid">
            <label><span><?php echo stridebr_e(stridebr_t('workout_builder.effort')); ?></span><select name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][effort][type')); ?>" data-wb-effort-type><option value=""><?php echo stridebr_e(stridebr_t('workout_builder.none')); ?></option><option value="rir"<?php echo ($effort['type'] ?? '') === 'rir' ? ' selected' : ''; ?>>RIR</option><option value="rpe"<?php echo ($effort['type'] ?? '') === 'rpe' ? ' selected' : ''; ?>>RPE</option></select></label>
            <label data-wb-effort-value><span>RIR/RPE</span><input type="number" min="0" max="10" step="0.5" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][effort][value')); ?>" value="<?php echo stridebr_e((string) ($effort['value'] ?? '')); ?>"></label>
            <label><span><?php echo stridebr_e(stridebr_t('schedule.intensity')); ?></span><input type="text" name="<?php echo stridebr_e(workoutBuilderName($index, 'intensidade')); ?>" value="<?php echo stridebr_e((string) ($row['intensidade'] ?? '')); ?>" maxlength="80"></label>
            <label><span><?php echo stridebr_e(stridebr_t('common.execution_time')); ?></span><input type="text" name="<?php echo stridebr_e(workoutBuilderName($index, 'tempo_execucao')); ?>" value="<?php echo stridebr_e((string) ($row['tempo_execucao'] ?? '')); ?>" maxlength="40"></label>
            <label><span><?php echo stridebr_e(stridebr_t('common.cadence')); ?></span><input type="text" name="<?php echo stridebr_e(workoutBuilderName($index, 'cadencia')); ?>" value="<?php echo stridebr_e((string) ($row['cadencia'] ?? '')); ?>" maxlength="40"></label>
        </div></details>
    </div>
    <?php
    return (string) ob_get_clean();
}

function workoutBuilderClusterFields(string|int $index, array $row, array $config): string
{
    $clusters = is_array($config['clusters'] ?? null) ? array_values($config['clusters']) : [];
    if (count($clusters) < 2) $clusters = [['reps'=>2],['reps'=>2],['reps'=>2]];
    $load = is_array($config['load'] ?? null) ? $config['load'] : [];
    ob_start();
    ?>
    <div class="wb-method-panel" data-wb-method-panel="cluster">
        <div class="wb-primary-grid wb-cluster-grid">
            <label><span><?php echo stridebr_e(stridebr_t('workout_builder.blocks')); ?></span><input type="number" min="1" max="99" step="1" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][blocks')); ?>" value="<?php echo stridebr_e((string) ($config['blocks'] ?? $row['series'] ?? 1)); ?>"></label>
            <fieldset class="wb-cluster-pieces"><legend><?php echo stridebr_e(stridebr_t('workout_builder.structure')); ?></legend><div data-wb-cluster-pieces><?php foreach ($clusters as $pieceIndex => $piece): ?><span data-wb-cluster-piece><input type="number" min="1" max="999" step="1" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][clusters][' . $pieceIndex . '][reps')); ?>" value="<?php echo stridebr_e((string) ($piece['reps'] ?? '')); ?>" aria-label="<?php echo stridebr_e(stridebr_t('common.repetitions')); ?>"><button type="button" data-wb-remove-cluster-piece aria-label="<?php echo stridebr_e(stridebr_t('common.remove')); ?>">×</button></span><?php endforeach; ?></div><button type="button" class="secondary-button compact-button" data-wb-add-cluster-piece>+</button></fieldset>
            <label class="wb-field-load"><span><?php echo stridebr_e(stridebr_t('common.load')); ?></span><span class="wb-unit-input"><input type="number" min="0" max="99999.999" step="0.01" inputmode="decimal" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][load][value')); ?>" value="<?php echo stridebr_e((string) ($load['value'] ?? '')); ?>"><span>kg</span></span><input type="hidden" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][load][unit')); ?>" value="kg"></label>
            <label><span><?php echo stridebr_e(stridebr_t('workout_builder.intra_rest')); ?></span><span class="wb-unit-input"><input type="number" min="0" max="86400" step="1" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][intra_cluster_rest_s')); ?>" value="<?php echo stridebr_e((string) ($config['intra_cluster_rest_s'] ?? '')); ?>"><span>s</span></span></label>
            <label><span><?php echo stridebr_e(stridebr_t('workout_builder.between_blocks')); ?></span><span class="wb-unit-input"><input type="number" min="0" max="86400" step="1" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][between_blocks_rest_s')); ?>" value="<?php echo stridebr_e((string) ($config['between_blocks_rest_s'] ?? '')); ?>"><span>s</span></span></label>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

function workoutBuilderDropFields(string|int $index, array $row, array $config): string
{
    $stages = is_array($config['stages'] ?? null) ? array_values($config['stages']) : [];
    if (count($stages) < 2) $stages = [['reps'=>['mode'=>'fixed','value'=>10]],['reps'=>['mode'=>'amrap']]];
    ob_start();
    ?>
    <div class="wb-method-panel" data-wb-method-panel="drop_set">
        <div class="wb-primary-grid"><label><span><?php echo stridebr_e(stridebr_t('workout_builder.rounds')); ?></span><input type="number" min="1" max="20" step="1" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][rounds')); ?>" value="<?php echo stridebr_e((string) ($config['rounds'] ?? $row['series'] ?? 1)); ?>"></label></div>
        <div class="wb-drop-stages" data-wb-drop-stages>
            <?php foreach ($stages as $stageIndex => $stage): $load=is_array($stage['load'] ?? null)?$stage['load']:[]; ?>
                <section class="wb-drop-stage" data-wb-drop-stage><div class="wb-drop-stage-head"><strong><?php echo stridebr_e(stridebr_t('workout_builder.drop_stage', ['number'=>$stageIndex+1])); ?></strong><button type="button" data-wb-remove-drop-stage aria-label="<?php echo stridebr_e(stridebr_t('common.remove')); ?>">×</button></div><div class="wb-drop-stage-grid">
                    <label><span><?php echo stridebr_e(stridebr_t('common.load')); ?></span><span class="wb-unit-input"><input type="number" min="0" max="99999.999" step="0.01" inputmode="decimal" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][stages][' . $stageIndex . '][load][value')); ?>" value="<?php echo stridebr_e((string) ($load['value'] ?? '')); ?>"><span>kg</span></span><input type="hidden" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][stages][' . $stageIndex . '][load][unit')); ?>" value="kg"></label>
                    <?php echo workoutBuilderRepTargetInputs($index, ['reps'=>$stage['reps'] ?? []], 'prescription][stages][' . $stageIndex); ?>
                    <label><span><?php echo stridebr_e(stridebr_t('home.rest')); ?></span><span class="wb-unit-input"><input type="number" min="0" max="86400" step="1" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][stages][' . $stageIndex . '][rest_after_s')); ?>" value="<?php echo stridebr_e((string) ($stage['rest_after_s'] ?? 0)); ?>"><span>s</span></span></label>
                </div></section>
            <?php endforeach; ?>
        </div>
        <button type="button" class="secondary-button compact-button" data-wb-add-drop-stage><?php echo stridebr_e(stridebr_t('workout_builder.add_drop')); ?></button>
        <label class="wb-round-rest"><span><?php echo stridebr_e(stridebr_t('workout_builder.round_rest')); ?></span><span class="wb-unit-input"><input type="number" min="0" max="86400" step="1" name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][round_rest_s')); ?>" value="<?php echo stridebr_e((string) ($config['round_rest_s'] ?? '')); ?>"><span>s</span></span></label>
    </div>
    <?php
    return (string) ob_get_clean();
}

function workoutBuilderEnduranceFields(string|int $index, array $row): string
{
    ob_start();
    ?>
    <div class="wb-endurance-panel" data-wb-endurance-panel>
        <div class="wb-primary-grid">
            <label><span><?php echo stridebr_e(stridebr_t('common.duration')); ?></span><input type="text" name="<?php echo stridebr_e(workoutBuilderName($index, 'duracao')); ?>" value="<?php echo stridebr_e((string) ($row['duracao'] ?? '')); ?>" maxlength="40" placeholder="10 min"></label>
            <label><span><?php echo stridebr_e(stridebr_t('common.distance')); ?></span><input type="text" name="<?php echo stridebr_e(workoutBuilderName($index, 'distancia')); ?>" value="<?php echo stridebr_e((string) ($row['distancia'] ?? '')); ?>" maxlength="40" placeholder="400 m"></label>
            <label><span><?php echo stridebr_e(stridebr_t('schedule.intensity')); ?></span><input type="text" name="<?php echo stridebr_e(workoutBuilderName($index, 'intensidade')); ?>" value="<?php echo stridebr_e((string) ($row['intensidade'] ?? '')); ?>" maxlength="80"></label>
            <label><span><?php echo stridebr_e(stridebr_t('workout_builder.rounds')); ?></span><input type="number" min="1" max="99" name="<?php echo stridebr_e(workoutBuilderName($index, 'repeticoes_bloco')); ?>" value="<?php echo stridebr_e((string) ($row['repeticoes_bloco'] ?? '')); ?>"></label>
        </div>
        <details class="wb-more"><summary><?php echo stridebr_e(stridebr_t('workout_builder.more')); ?></summary><div class="wb-advanced-grid">
            <label><span>Alvo</span><select name="<?php echo stridebr_e(workoutBuilderName($index, 'alvo_tipo')); ?>"><option value="">—</option><?php foreach (['pace'=>'Pace','speed'=>'Velocidade','heart_rate'=>'FC','rpe'=>'RPE','duration'=>'Duração','distance'=>'Distância'] as $key=>$label): ?><option value="<?php echo $key; ?>"<?php echo ($row['alvo_tipo'] ?? '') === $key ? ' selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
            <label><span>Mín.</span><input type="number" step="any" name="<?php echo stridebr_e(workoutBuilderName($index, 'alvo_min')); ?>" value="<?php echo stridebr_e((string) ($row['alvo_min'] ?? '')); ?>"></label>
            <label><span>Máx.</span><input type="number" step="any" name="<?php echo stridebr_e(workoutBuilderName($index, 'alvo_max')); ?>" value="<?php echo stridebr_e((string) ($row['alvo_max'] ?? '')); ?>"></label>
            <label><span><?php echo stridebr_e(stridebr_t('workout_builder.distance_unit')); ?></span><select name="<?php echo stridebr_e(workoutBuilderName($index, 'alvo_unidade')); ?>"><option value="">—</option><?php foreach (['s_per_km'=>'s/km','km_h'=>'km/h','bpm'=>'bpm','rpe_1_10'=>'RPE','s'=>'s','m'=>'m'] as $key=>$label): ?><option value="<?php echo $key; ?>"<?php echo ($row['alvo_unidade'] ?? '') === $key ? ' selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
            <label><span>Recuperação (s)</span><input type="number" min="0" name="<?php echo stridebr_e(workoutBuilderName($index, 'recuperacao_duracao_s')); ?>" value="<?php echo stridebr_e((string) ($row['recuperacao_duracao_s'] ?? '')); ?>"></label>
            <label><span>Recuperação (m)</span><input type="number" min="0" step="any" name="<?php echo stridebr_e(workoutBuilderName($index, 'recuperacao_distancia_m')); ?>" value="<?php echo stridebr_e((string) ($row['recuperacao_distancia_m'] ?? '')); ?>"></label>
        </div></details>
    </div>
    <?php
    return (string) ob_get_clean();
}

function workoutBuilderExtraFields(string|int $index, array $row, array $options): string
{
    $renderer = $options['extra_renderer'] ?? null;
    $fields = is_array($options['extra_fields'] ?? null) ? $options['extra_fields'] : [];
    if (!is_callable($renderer) || $fields === []) return '';
    $values = is_array($options['extra_values'] ?? null) ? $options['extra_values'] : [];
    $rowId = (string) ($row['idtreino_exercicio'] ?? '');
    ob_start();
    ?><details class="wb-more wb-custom-fields"><summary><?php echo stridebr_e(stridebr_t('workout_builder.custom_fields')); ?></summary><div class="wb-advanced-grid"><?php foreach ($fields as $field): ?><label><span><?php echo stridebr_e((string) $field['nome']); ?></span><?php echo $renderer($field, $values[$rowId][$field['idcampo']] ?? null, workoutBuilderName($index, 'extras][' . $field['idcampo'])); ?></label><?php endforeach; ?></div></details><?php
    return (string) ob_get_clean();
}

function workoutBuilderRenderCard(array $row, string|int $index, array $options): string
{
    $stepType = trim((string) ($row['tipo_passo'] ?? 'exercise')) ?: 'exercise';
    $trackingMode = trim((string) ($row['tracking_mode'] ?? 'load_reps')) ?: 'load_reps';
    $sportSlug = (string) ($options['sport_slug'] ?? '');
    try {
        $normalized = $stepType === 'exercise' ? workoutPrescriptionNormalize($row) : ['method'=>'standard','config'=>[]];
    } catch (Throwable) {
        $normalized = ['method'=>'standard','config'=>[]];
    }
    $method = (string) $normalized['method'];
    $config = is_array($normalized['config'] ?? null) ? $normalized['config'] : [];
    $summary = $stepType === 'exercise' ? trim((string) ($row['prescription_summary'] ?? '')) : trim(implode(' · ', array_filter([(string) ($row['duracao'] ?? ''), (string) ($row['distancia'] ?? ''), (string) ($row['intensidade'] ?? '')])));
    if ($summary === '' && $stepType === 'exercise') {
        try { $summary = workoutPrescriptionSummary($row); } catch (Throwable) { $summary = ''; }
    }
    $name = trim((string) ($row['nome_snapshot'] ?? $row['nome'] ?? ''));
    if ($name === '' && $stepType !== 'exercise') $name = workoutBuilderStepLabel($stepType);
    $idOccurrence = (string) ($row['idtreino_exercicio'] ?? $row['idtreino_modelo_exercicio'] ?? '');
    $legacyCluster = trim((string) ($row['cluster'] ?? ''));
    $legacyClusterUnknown = $legacyCluster !== '' && $method === 'standard';
    ob_start();
    ?>
    <article class="workout-builder-card" data-wb-card data-tracking-mode="<?php echo stridebr_e($trackingMode); ?>" data-step-type="<?php echo stridebr_e($stepType); ?>" data-method="<?php echo stridebr_e($method); ?>" data-exercise-row>
        <input type="hidden" name="<?php echo stridebr_e(workoutBuilderName($index, 'idtreino_exercicio')); ?>" value="<?php echo stridebr_e($idOccurrence); ?>">
        <input type="hidden" name="<?php echo stridebr_e(workoutBuilderName($index, 'idexercicio')); ?>" value="<?php echo stridebr_e((string) ($row['idexercicio'] ?? '')); ?>" data-exercise-id>
        <input type="hidden" name="<?php echo stridebr_e(workoutBuilderName($index, 'tracking_mode')); ?>" value="<?php echo stridebr_e($trackingMode); ?>" data-wb-tracking-input>
        <?php echo workoutBuilderHiddenLegacy($index, $row); ?>
        <?php echo workoutBuilderGroupHidden($index, $row); ?>
        <div class="wb-card-head">
            <button type="button" class="wb-drag-handle" data-wb-drag aria-label="<?php echo stridebr_e(stridebr_t('workout_builder.select_group')); ?>" title="<?php echo stridebr_e(stridebr_t('workout_builder.select_group')); ?>">≡</button>
            <span class="wb-order" data-wb-order><?php echo ((int) $index) + 1; ?></span>
            <button type="button" class="wb-card-toggle" data-wb-toggle aria-expanded="false">
                <span class="wb-card-copy"><strong data-wb-name-output><?php echo stridebr_e($name !== '' ? $name : stridebr_t('home.exercise')); ?></strong><span class="wb-card-meta"><span data-wb-method-output><?php echo stridebr_e($stepType === 'exercise' ? workoutBuilderMethodLabel($method) : workoutBuilderStepLabel($stepType)); ?></span><span data-wb-summary><?php echo stridebr_e($summary); ?></span></span></span>
            </button>
            <label class="wb-group-selector"><input type="checkbox" data-wb-select-group><span><?php echo stridebr_e(stridebr_t('workout_builder.select_group')); ?></span></label>
            <details class="wb-card-menu"><summary aria-label="Menu">⋮</summary><div><button type="button" data-wb-move="up"><?php echo stridebr_e(stridebr_t('workout_builder.move_up')); ?></button><button type="button" data-wb-move="down"><?php echo stridebr_e(stridebr_t('workout_builder.move_down')); ?></button><button type="button" data-wb-ungroup><?php echo stridebr_e(stridebr_t('workout_builder.ungroup')); ?></button><button type="button" class="danger-link" data-wb-remove><?php echo stridebr_e(stridebr_t('workout_builder.remove')); ?></button></div></details>
        </div>
        <div class="wb-card-editor" data-wb-editor hidden>
            <div class="wb-editor-topline">
                <label class="wb-name-field"><span><?php echo stridebr_e(stridebr_t('home.exercise')); ?></span><input type="text" name="<?php echo stridebr_e(workoutBuilderName($index, 'nome')); ?>" value="<?php echo stridebr_e($name); ?>" data-exercise-name maxlength="120"<?php echo $stepType === 'exercise' ? ' required' : ''; ?>></label>
                <label><span><?php echo stridebr_e(stridebr_t('workout_builder.endurance')); ?></span><select name="<?php echo stridebr_e(workoutBuilderName($index, 'tipo_passo')); ?>" data-wb-step-type><?php foreach (['exercise','warmup','work','interval_group','recovery','cooldown'] as $type): ?><option value="<?php echo $type; ?>"<?php echo $stepType === $type ? ' selected' : ''; ?>><?php echo stridebr_e(workoutBuilderStepLabel($type)); ?></option><?php endforeach; ?></select></label>
                <div class="wb-tracking-badge"><span><?php echo stridebr_e(stridebr_t('workout_builder.tracking')); ?></span><strong data-wb-tracking-label><?php echo stridebr_e(workoutBuilderTrackingLabel($trackingMode)); ?></strong></div>
            </div>
            <div data-wb-exercise-prescription<?php echo $stepType === 'exercise' ? '' : ' hidden'; ?>>
                <label class="wb-method-select"><span><?php echo stridebr_e(stridebr_t('workout_builder.method')); ?></span><select name="<?php echo stridebr_e(workoutBuilderName($index, 'prescription][method')); ?>" data-wb-method><?php foreach (['standard','cluster','drop_set'] as $choice): ?><option value="<?php echo $choice; ?>"<?php echo $method === $choice ? ' selected' : ''; ?>><?php echo stridebr_e(workoutBuilderMethodLabel($choice)); ?></option><?php endforeach; ?></select></label>
                <?php echo workoutBuilderStandardFields($index, $row, $config, $trackingMode, $sportSlug, $stepType); ?>
                <?php echo workoutBuilderClusterFields($index, $row, $method === 'cluster' ? $config : []); ?>
                <?php echo workoutBuilderDropFields($index, $row, $method === 'drop_set' ? $config : []); ?>
            </div>
            <div<?php echo $stepType === 'exercise' ? ' hidden' : ''; ?> data-wb-structured-step><?php echo workoutBuilderEnduranceFields($index, $row); ?></div>
            <?php if ($legacyClusterUnknown): ?><div class="wb-legacy-note"><strong><?php echo stridebr_e(stridebr_t('workout_builder.legacy_config')); ?></strong><span><?php echo stridebr_e($legacyCluster); ?></span></div><?php endif; ?>
            <details class="wb-more"><summary><?php echo stridebr_e(stridebr_t('workout_builder.more')); ?></summary><div class="wb-advanced-grid">
                <label><span><?php echo stridebr_e(stridebr_t('common.block')); ?></span><input type="text" name="<?php echo stridebr_e(workoutBuilderName($index, 'bloco')); ?>" value="<?php echo stridebr_e((string) ($row['bloco'] ?? '')); ?>" maxlength="40"></label>
                <label><span><?php echo stridebr_e(stridebr_t('common.cluster')); ?></span><input type="text" name="<?php echo stridebr_e(workoutBuilderName($index, 'cluster')); ?>" value="<?php echo stridebr_e($legacyCluster); ?>" maxlength="80"></label>
                <label><span><?php echo stridebr_e(stridebr_t('activity.notes')); ?></span><textarea name="<?php echo stridebr_e(workoutBuilderName($index, 'observacoes')); ?>" rows="2"><?php echo stridebr_e((string) ($row['observacoes'] ?? '')); ?></textarea></label>
            </div></details>
            <?php echo workoutBuilderExtraFields($index, $row, $options); ?>
        </div>
    </article>
    <?php
    return (string) ob_get_clean();
}

function workoutBuilderGroupHeader(array $row): string
{
    $key = (string) ($row['grupo_chave'] ?? '');
    $type = (string) ($row['grupo_tipo'] ?? 'superset');
    $rounds = (int) ($row['grupo_voltas'] ?? 1);
    $between = $row['grupo_descanso_entre_exercicios_s'] ?? '';
    $after = $row['grupo_descanso_pos_volta_s'] ?? '';
    ob_start();
    ?>
    <div class="wb-group-head"><button type="button" class="wb-drag-handle" data-wb-group-drag aria-label="<?php echo stridebr_e(stridebr_t('workout_builder.group')); ?>">≡</button><div><strong data-wb-group-title><?php echo stridebr_e(stridebr_t('workout_builder.group.' . $type)); ?></strong><span><?php echo stridebr_e(stridebr_t('workout_builder.group_rounds')); ?> · <?php echo $rounds; ?></span></div><div class="wb-group-controls"><label><span><?php echo stridebr_e(stridebr_t('workout_builder.group_rounds')); ?></span><input type="number" min="1" max="99" value="<?php echo $rounds; ?>" data-wb-group-setting="grupo_voltas"></label><label><span><?php echo stridebr_e(stridebr_t('workout_builder.group_rest_between')); ?></span><input type="number" min="0" max="86400" value="<?php echo stridebr_e((string) $between); ?>" data-wb-group-setting="grupo_descanso_entre_exercicios_s"></label><label><span><?php echo stridebr_e(stridebr_t('workout_builder.group_rest_round')); ?></span><input type="number" min="0" max="86400" value="<?php echo stridebr_e((string) $after); ?>" data-wb-group-setting="grupo_descanso_pos_volta_s"></label></div><div class="wb-group-actions"><button type="button" data-wb-group-move="up" aria-label="<?php echo stridebr_e(stridebr_t('workout_builder.move_up')); ?>">↑</button><button type="button" data-wb-group-move="down" aria-label="<?php echo stridebr_e(stridebr_t('workout_builder.move_down')); ?>">↓</button><button type="button" data-wb-dissolve-group><?php echo stridebr_e(stridebr_t('workout_builder.ungroup')); ?></button></div><input type="hidden" value="<?php echo stridebr_e($key); ?>" data-wb-group-key><input type="hidden" value="<?php echo stridebr_e($type); ?>" data-wb-group-type></div>
    <?php
    return (string) ob_get_clean();
}

function workoutBuilderRender(array $rows, array $options = []): void
{
    $groups = [];
    foreach ($rows as $row) {
        $key = trim((string) ($row['grupo_chave'] ?? ''));
        if ($key !== '') $groups[$key][] = $row;
    }
    $renderedGroups = [];
    $index = 0;
    foreach ($rows as $row) {
        $key = trim((string) ($row['grupo_chave'] ?? ''));
        if ($key !== '' && count($groups[$key] ?? []) >= 2) {
            if (isset($renderedGroups[$key])) continue;
            $renderedGroups[$key] = true;
            echo '<section class="workout-builder-group" data-wb-group data-group-key="' . stridebr_e($key) . '">';
            echo workoutBuilderGroupHeader($row);
            echo '<div class="wb-group-members" data-wb-group-members>';
            foreach ($groups[$key] as $member) echo workoutBuilderRenderCard($member, $index++, $options);
            echo '</div></section>';
            continue;
        }
        echo workoutBuilderRenderCard($row, $index++, $options);
    }
}
