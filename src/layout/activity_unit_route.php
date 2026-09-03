<?php

if (!function_exists('atividadeRenderizarRotaUnidade')) {
    function atividadeRenderizarRotaUnidade(string $namePrefix, string $label, mixed $rawValue = ''): string
    {
        $value = '';
        if (is_array($rawValue)) $value = json_encode($rawValue, JSON_UNESCAPED_SLASHES) ?: '';
        else $value = (string) $rawValue;
        $hasRoute = trim($value) !== '';
        $html = '<details class="activity-unit-route-editor" data-unit-route-editor>';
        $html .= '<summary><span><strong>Rota de ' . stridebr_e($label) . '</strong><small>Opcional · útil para compartilhar este trecho separadamente</small></span><span data-unit-route-summary>' . ($hasRoute ? 'Rota adicionada' : 'Adicionar rota') . '</span></summary>';
        $html .= '<input type="hidden" name="' . stridebr_e($namePrefix) . '[rota_coordenadas]" value="' . stridebr_e($value) . '" data-unit-route-value>';
        $html .= '<div class="activity-unit-route-workspace">';
        $html .= '<div class="activity-unit-route-toolbar"><button type="button" data-unit-route-locate>Minha localização</button><button type="button" data-unit-route-undo>Desfazer</button><button type="button" data-unit-route-clear>Limpar</button></div>';
        $html .= '<div class="activity-unit-route-map" data-unit-route-map aria-label="Mapa para desenhar a rota deste trecho"></div>';
        $html .= '<div class="activity-unit-route-status"><span data-unit-route-status>Toque no mapa para adicionar pontos.</span><strong data-unit-route-distance>' . ($hasRoute ? 'Calculando…' : 'Sem rota') . '</strong><small data-unit-route-elevation>' . ($hasRoute ? 'Elevação será estimada.' : '') . '</small></div>';
        $html .= '<small>Em uma sessão com trechos, esta rota pertence somente a este trecho.</small>';
        $html .= '</div></details>';
        return $html;
    }
}
