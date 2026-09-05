<?php

if (!function_exists('atividadeRenderizarRotaUnidade')) {
    function atividadeRenderizarRotaUnidade(string $namePrefix, string $label, mixed $rawValue = '', array $editorState = []): string
    {
        $value = is_array($rawValue) ? (json_encode($rawValue, JSON_UNESCAPED_SLASHES) ?: '') : (string) $rawValue;
        $mode = (string) ($editorState['route_editor_mode'] ?? '') === 'circuit' ? 'circuit' : 'free';
        $laps = max(1, (int) ($editorState['route_editor_laps'] ?? 1));
        $baseRaw = $editorState['route_editor_base'] ?? '';
        $base = is_array($baseRaw) ? (json_encode($baseRaw, JSON_UNESCAPED_SLASHES) ?: '') : (string) $baseRaw;
        $hasRoute = trim($value) !== '';
        $savedRoute = is_array($editorState['rota'] ?? null) ? $editorState['rota'] : [];
        $savedMetrics = is_array($editorState['rota_metricas'] ?? null) ? $editorState['rota_metricas'] : [];
        $title = stridebr_t('route.unit_title', ['label' => $label]);
        $html = '<section class="activity-unit-route-editor' . ($hasRoute ? ' has-route' : '') . '" data-unit-route-editor data-unit-route-label="' . stridebr_e($label) . '" data-route-has-points="' . ($hasRoute ? '1' : '0') . '">';
        $html .= '<input type="hidden" name="' . stridebr_e($namePrefix) . '[rota_coordenadas]" value="' . stridebr_e($value) . '" data-unit-route-value>';
        $html .= '<input type="hidden" name="' . stridebr_e($namePrefix) . '[route_editor_mode]" value="' . stridebr_e($mode) . '" data-unit-route-mode-value>';
        $html .= '<input type="hidden" name="' . stridebr_e($namePrefix) . '[route_editor_laps]" value="' . $laps . '" data-unit-route-laps-value>';
        $html .= '<input type="hidden" name="' . stridebr_e($namePrefix) . '[route_editor_base]" value="' . stridebr_e($base) . '" data-unit-route-base-value>';
        foreach (['distancia_metros', 'ganho_elevacao_m', 'perda_elevacao_m', 'elevacao_min_m', 'elevacao_max_m', 'fonte_elevacao'] as $metric) {
            $metricValue = $savedMetrics[$metric] ?? ($savedRoute[$metric] ?? ($metric === 'ganho_elevacao_m' ? ($editorState['elevacao_m'] ?? '') : ''));
            $enabled = $mode === 'circuit' && $hasRoute;
            $html .= '<input type="hidden" name="' . stridebr_e($namePrefix) . '[rota_metricas][' . $metric . ']" value="' . stridebr_e((string) $metricValue) . '"' . ($enabled ? '' : ' disabled') . ' data-unit-route-metric="' . $metric . '">';
        }
        $html .= '<div class="activity-unit-route-summary-row">';
        $html .= '<div><strong data-unit-route-title>' . stridebr_e($title) . '</strong><small data-unit-route-summary>' . stridebr_e(stridebr_t($hasRoute ? 'route.added' : 'common.optional')) . '</small></div>';
        $html .= '<button type="button" class="activity-inline-action activity-row-action" data-unit-route-open>' . stridebr_e(stridebr_t($hasRoute ? 'route.edit' : 'route.add')) . '</button>';
        $html .= '</div></section>';
        return $html;
    }
}
