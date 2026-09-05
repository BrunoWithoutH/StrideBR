<?php

require_once __DIR__ . '/sport_picker.php';

if (!function_exists('atividadeRenderizarSeletorModalidadeUnidade')) {
    function atividadeRenderizarSeletorModalidadeUnidade(string $name, array $catalogo, string $selected, string $fallback): string
    {
        $selected = $selected !== '' ? $selected : $fallback;
        return '<div class="input-field activity-unit-sport-field"><span class="form-field-label">Modalidade</span>'
            . sportPickerRenderSelect($catalogo, [
                'name' => $name,
                'selected' => $selected,
                'placeholder' => stridebr_t('sport_picker.choose'),
                'native_attributes' => ['data-unit-sport-select' => true],
            ])
            . '</div>';
    }
}

if (!function_exists('atividadeRenderizarMetricaDerivadaUnidade')) {
    function atividadeRenderizarMetricaDerivadaUnidade(): string
    {
        return '<div class="input-field derived-metric-field" data-unit-derived-field><label data-unit-derived-label>' . stridebr_e(stridebr_t('activity.pace')) . '</label><div class="derived-input-wrap"><input type="text" inputmode="decimal" data-unit-derived-input placeholder="--:--"><span data-unit-derived-unit>/km</span><span class="auto-badge" data-unit-derived-badge>AUTO</span></div></div>';
    }
}

if (!function_exists('atividadeMetricaDerivadaModalidadeCatalogo')) {
    function atividadeMetricaDerivadaModalidadeCatalogo(array $catalogo, string $idModalidade, string $fallback = 'nenhuma'): string
    {
        foreach ($catalogo as $modalidade) {
            if ((string) ($modalidade['idmodalidade'] ?? '') === $idModalidade) return (string) ($modalidade['metrica_derivada'] ?? $fallback);
        }
        return $fallback;
    }
}

if (!function_exists('atividadeRenderizarMetricasCanonicasTrecho')) {
    function atividadeRenderizarMetricasCanonicasTrecho(string $namePrefix, array $unitData, string $derivedType, array $unitFields = []): string
    {
        $posted = is_array($unitData['metricas'] ?? null) ? $unitData['metricas'] : [];
        $legacyValues = is_array($unitData['values'] ?? null) ? $unitData['values'] : [];
        if (!is_numeric($unitData['distancia_metros'] ?? null) || !is_numeric($unitData['duracao_segundos'] ?? null) || !is_numeric($unitData['elevacao_m'] ?? null)) {
            foreach ($unitFields as $field) {
                $id = (string) ($field['idcampo'] ?? '');
                $slug = stridebr_lower((string) ($field['slug'] ?? ''));
                $value = $legacyValues[$id] ?? null;
                if (!is_numeric($unitData['distancia_metros'] ?? null) && $slug === 'distancia' && is_numeric($value)) $unitData['distancia_metros'] = (($field['unidade_simbolo'] ?? 'km') === 'm') ? (float) $value : (float) $value * 1000;
                if (!is_numeric($unitData['elevacao_m'] ?? null) && in_array($slug, ['elevacao', 'desnivel'], true) && is_numeric($value)) $unitData['elevacao_m'] = (($field['unidade_simbolo'] ?? 'm') === 'km') ? (float) $value * 1000 : (float) $value;
                if (!is_numeric($unitData['duracao_segundos'] ?? null) && $slug === 'duracao' && trim((string) $value) !== '') {
                    $secondsValue = atividadeIntervaloParaSegundos(atividadeFormatarIntervalo($value));
                    if ($secondsValue !== null) $unitData['duracao_segundos'] = $secondsValue;
                }
            }
        }
        $distanceUnit = (string) ($posted['distancia_unidade'] ?? ($derivedType === 'pace_100m' ? 'm' : 'km'));
        if (!in_array($distanceUnit, ['m', 'km'], true)) $distanceUnit = $derivedType === 'pace_100m' ? 'm' : 'km';
        $distance = $posted['distancia'] ?? null;
        if (($distance === null || $distance === '') && is_numeric($unitData['distancia_metros'] ?? null)) {
            $distance = $distanceUnit === 'm' ? (float) $unitData['distancia_metros'] : (float) $unitData['distancia_metros'] / 1000;
        }
        $duration = (string) ($posted['duracao'] ?? '');
        if ($duration === '' && is_numeric($unitData['duracao_segundos'] ?? null)) $duration = atividadeSegundosParaIntervalo((float) $unitData['duracao_segundos']);
        $hours = $minutes = $secondsText = $milliseconds = '';
        if ($duration !== '') {
            $duration = atividadeFormatarIntervalo($duration);
            if (preg_match('/^(\d+):([0-5]\d):([0-5]\d)(?:\.(\d{3}))?$/', $duration, $parts)) {
                $hours = (string) ((int) $parts[1]);
                $minutes = (string) ((int) $parts[2]);
                $secondsText = (string) ((int) $parts[3]);
                $milliseconds = isset($parts[4]) ? $parts[4] : '';
            }
        }
        $elevation = $posted['elevacao'] ?? ($unitData['elevacao_m'] ?? '');
        $distanceExibicao = atividadeFormatarValorDecimalEdicao($distance, $distanceUnit);
        $elevationExibicao = atividadeFormatarValorDecimalEdicao($elevation, 'm');
        $html = '<div class="activity-segment-core-metrics" data-segment-core-metrics>';
        $html .= '<div class="input-field" data-segment-distance-field><label>' . stridebr_e(stridebr_t('activity.distance')) . ' <span class="field-unit" data-segment-distance-unit>' . stridebr_e($distanceUnit) . '</span></label><input type="number" step="any" inputmode="decimal" name="' . stridebr_e($namePrefix) . '[metricas][distancia]" value="' . stridebr_e($distanceExibicao) . '" data-segment-distance><input type="hidden" name="' . stridebr_e($namePrefix) . '[metricas][distancia_unidade]" value="' . stridebr_e($distanceUnit) . '" data-segment-distance-unit-value></div>';
        $html .= '<div class="input-field" data-segment-duration-field><label>' . stridebr_e(stridebr_t('activity.duration')) . '</label><div class="duration-segments" data-duration-field><label><span>h</span><input type="text" inputmode="numeric" maxlength="3" value="' . stridebr_e($hours) . '" data-duration-hours aria-label="' . stridebr_e(stridebr_t('activity.hours')) . '" autocomplete="off"></label><span aria-hidden="true">:</span><label><span>min</span><input type="text" inputmode="numeric" maxlength="2" value="' . stridebr_e($minutes) . '" data-duration-minutes aria-label="' . stridebr_e(stridebr_t('activity.minutes')) . '" autocomplete="off"></label><span aria-hidden="true">:</span><label><span>s</span><input type="text" inputmode="numeric" maxlength="2" value="' . stridebr_e($secondsText) . '" data-duration-seconds aria-label="' . stridebr_e(stridebr_t('activity.seconds')) . '" autocomplete="off"></label><span class="duration-ms-separator" data-duration-ms-separator aria-hidden="true"' . ($milliseconds === '' ? ' hidden' : '') . '>.</span><label class="duration-ms-field" data-duration-ms-wrap' . ($milliseconds === '' ? ' hidden' : '') . '><span>ms</span><input type="text" inputmode="numeric" maxlength="3" value="' . stridebr_e($milliseconds) . '" data-duration-milliseconds aria-label="' . stridebr_e(stridebr_t('activity.milliseconds')) . '" autocomplete="off"></label><input type="hidden" name="' . stridebr_e($namePrefix) . '[metricas][duracao]" value="' . stridebr_e($duration) . '" data-duration-value data-segment-duration></div></div>';
        $html .= '<div class="input-field" data-segment-elevation-field><label>' . stridebr_e(stridebr_t('activity.elevation')) . ' <span class="field-unit">m</span></label><input type="number" step="any" inputmode="decimal" name="' . stridebr_e($namePrefix) . '[metricas][elevacao]" value="' . stridebr_e($elevationExibicao) . '" data-segment-elevation></div>';
        $html .= atividadeRenderizarMetricaDerivadaUnidade();
        $html .= '</div>';
        return $html;
    }
}
