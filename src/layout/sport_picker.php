<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/function/sport_catalog.php';
require_once dirname(__DIR__) . '/includes/sport_icons.php';

function sportPickerRenderSportRow(array $sport, string $familyLabel, string $selectedId = '', bool $favoriteControls = false): string
{
    $id = (string) ($sport['idmodalidade'] ?? '');
    $name = (string) ($sport['nome'] ?? 'Atividade');
    $slug = (string) ($sport['slug'] ?? '');
    $family = sportCatalogFamilyKey((string) ($sport['familia_hub'] ?? ''), (string) ($sport['categoria'] ?? ''), $slug);
    $favorite = !empty($sport['favorita']);
    $search = stridebr_lower(trim($name . ' ' . $familyLabel . ' ' . (string) ($sport['categoria'] ?? '')));
    $rowClass = 'sport-option-row' . ($favoriteControls ? '' : ' sport-option-row-no-favorite');
    $html = '<div class="' . $rowClass . '" data-sport-row data-search-text="' . stridebr_e($search) . '">';
    $html .= '<button type="button" class="sport-option" role="option" data-sport-option data-sport-id="' . stridebr_e($id) . '" data-sport-name="' . stridebr_e($name) . '" data-sport-slug="' . stridebr_e($slug) . '" data-sport-family="' . stridebr_e($family) . '" data-sport-icon-id="' . stridebr_e(stridebr_sport_icon_id($slug)) . '" data-sport-favorite="' . ($favorite ? '1' : '0') . '" aria-selected="' . ($selectedId === $id ? 'true' : 'false') . '">';
    $html .= '<span class="sport-option-icon">' . stridebr_sport_icon_html($slug) . '</span><span>' . stridebr_e($name) . '</span></button>';
    if ($favoriteControls) {
        $html .= '<button type="button" class="sport-favorite-button' . ($favorite ? ' is-favorite' : '') . '" data-toggle-sport-favorite data-sport-id="' . stridebr_e($id) . '" aria-label="Favoritar ' . stridebr_e($name) . '">★</button>';
    }
    return $html . '</div>';
}

function sportPickerRenderFamilyBrowser(array $sports, string $selectedId = '', bool $favoriteControls = false): string
{
    $groups = sportCatalogGroups($sports);
    $html = '<div class="sport-family-browser" data-sport-family-browser>';
    $html .= '<div class="sport-family-grid" data-sport-family-grid>';
    foreach ($groups as $group) {
        $count = count($group['popular']) + count($group['more']);
        $html .= '<button type="button" class="sport-family-card" data-sport-family-open="' . stridebr_e((string) $group['key']) . '">';
        $html .= '<span class="sport-family-card-copy"><strong>' . stridebr_e((string) $group['label']) . '</strong><small>' . stridebr_e((string) $group['description']) . '</small></span>';
        $html .= '<span class="sport-family-card-count">' . $count . '</span><span class="sport-family-card-arrow" aria-hidden="true">›</span></button>';
    }
    $html .= '</div>';

    foreach ($groups as $group) {
        $key = (string) $group['key'];
        $label = (string) $group['label'];
        $html .= '<section class="sport-family-panel" data-sport-family-panel="' . stridebr_e($key) . '" hidden>';
        $html .= '<div class="sport-family-panel-head"><button type="button" class="sport-family-back" data-sport-family-back>← Categorias</button><div><strong>' . stridebr_e($label) . '</strong><small>' . stridebr_e((string) $group['description']) . '</small></div></div>';
        $html .= '<div class="sport-family-section"><div class="sport-family-section-title">Mais comuns</div>';
        foreach ($group['popular'] as $sport) $html .= sportPickerRenderSportRow($sport, $label, $selectedId, $favoriteControls);
        $html .= '</div>';
        if ($group['more'] !== []) {
            $html .= '<button type="button" class="sport-family-more-toggle" data-sport-family-more aria-expanded="false"><span>Mais esportes</span><span aria-hidden="true">⌄</span></button>';
            $html .= '<div class="sport-family-more-list" data-sport-family-more-list hidden>';
            foreach ($group['more'] as $sport) $html .= sportPickerRenderSportRow($sport, $label, $selectedId, $favoriteControls);
            $html .= '</div>';
        }
        $html .= '</section>';
    }
    return $html . '</div>';
}


function sportPickerRenderSelect(array $sports, array $options = []): string
{
    $name = trim((string) ($options['name'] ?? 'idmodalidade'));
    $selectedId = trim((string) ($options['selected'] ?? ''));
    $valueKey = trim((string) ($options['value_key'] ?? 'idmodalidade'));
    if (!preg_match('/^[A-Za-z0-9_]+$/', $valueKey)) $valueKey = 'idmodalidade';
    $placeholder = trim((string) ($options['placeholder'] ?? 'Escolha um esporte'));
    $emptyLabel = trim((string) ($options['empty_label'] ?? ''));
    $nativeAttributes = is_array($options['native_attributes'] ?? null) ? $options['native_attributes'] : [];
    $groups = sportCatalogGroups($sports);
    $selected = null;
    foreach ($sports as $sport) {
        if ((string) ($sport[$valueKey] ?? '') === $selectedId) {
            $selected = $sport;
            break;
        }
    }
    $selectedName = $selected ? (string) ($selected['nome'] ?? $placeholder) : ($selectedId === '' && $emptyLabel !== '' ? $emptyLabel : $placeholder);
    $selectedSlug = $selected ? (string) ($selected['slug'] ?? '') : '';
    $attrs = '';
    foreach ($nativeAttributes as $key => $value) {
        if (!preg_match('/^[A-Za-z_:][A-Za-z0-9_:.-]*$/', (string) $key)) continue;
        if ($value === true) $attrs .= ' ' . stridebr_e((string) $key);
        elseif ($value !== false && $value !== null) $attrs .= ' ' . stridebr_e((string) $key) . '="' . stridebr_e((string) $value) . '"';
    }
    $html = '<div class="sport-select-picker" data-generic-sport-picker>';
    $html .= '<select class="sport-select-native" name="' . stridebr_e($name) . '" data-generic-sport-native' . $attrs . ' tabindex="-1" aria-hidden="true">';
    if ($emptyLabel !== '') $html .= '<option value=""' . ($selectedId === '' ? ' selected' : '') . '>' . stridebr_e($emptyLabel) . '</option>';
    foreach ($sports as $sport) {
        $id = (string) ($sport[$valueKey] ?? '');
        $metricAttr = array_key_exists('metrica_derivada', $sport) ? ' data-metric="' . stridebr_e((string) ($sport['metrica_derivada'] ?? '')) . '" data-derived-type="' . stridebr_e((string) ($sport['metrica_derivada'] ?? 'nenhuma')) . '"' : '';
        $routeAttr = array_key_exists('permite_rota', $sport) ? ' data-permite-rota="' . (!empty($sport['permite_rota']) ? '1' : '0') . '"' : '';
        $family = sportCatalogFamilyKey((string) ($sport['familia_hub'] ?? ''), (string) ($sport['categoria'] ?? ''), (string) ($sport['slug'] ?? ''));
        $html .= '<option value="' . stridebr_e($id) . '" data-slug="' . stridebr_e((string) ($sport['slug'] ?? '')) . '" data-family="' . stridebr_e($family) . '"' . $metricAttr . $routeAttr . ($selectedId === $id ? ' selected' : '') . '>' . stridebr_e((string) ($sport['nome'] ?? 'Atividade')) . '</option>';
    }
    $html .= '</select>';
    $html .= '<button type="button" class="sport-select-trigger" data-generic-sport-trigger aria-expanded="false">';
    $html .= '<span class="sport-select-trigger-icon" data-generic-sport-trigger-icon>' . ($selectedSlug !== '' ? stridebr_sport_icon_html($selectedSlug) : '<span aria-hidden="true">◎</span>') . '</span>';
    $html .= '<span data-generic-sport-trigger-label>' . stridebr_e($selectedName) . '</span><span class="sport-select-trigger-arrow" aria-hidden="true">⌄</span></button>';
    $html .= '<div class="sport-select-popover" data-generic-sport-popover hidden>';
    $html .= '<label class="sport-select-search"><span>Buscar esporte</span><input type="search" data-generic-sport-search placeholder="Corrida, musculação, tênis..." autocomplete="off"></label>';
    if ($emptyLabel !== '') $html .= '<button type="button" class="sport-select-empty" data-generic-sport-empty data-sport-id=""><span aria-hidden="true">◎</span><span>' . stridebr_e($emptyLabel) . '</span></button>';
    $html .= '<div class="sport-family-browser" data-generic-sport-browser>';
    $html .= '<div class="sport-family-grid" data-generic-sport-family-grid>';
    foreach ($groups as $group) {
        $count = count($group['popular']) + count($group['more']);
        $html .= '<button type="button" class="sport-family-card" data-generic-sport-family-open="' . stridebr_e((string) $group['key']) . '"><span class="sport-family-card-copy"><strong>' . stridebr_e((string) $group['label']) . '</strong><small>' . stridebr_e((string) $group['description']) . '</small></span><span class="sport-family-card-count">' . $count . '</span><span class="sport-family-card-arrow" aria-hidden="true">›</span></button>';
    }
    $html .= '</div>';
    foreach ($groups as $group) {
        $key = (string) $group['key'];
        $label = (string) $group['label'];
        $html .= '<section class="sport-family-panel" data-generic-sport-family-panel="' . stridebr_e($key) . '" hidden>';
        $html .= '<div class="sport-family-panel-head"><button type="button" class="sport-family-back" data-generic-sport-family-back>← Categorias</button><div><strong>' . stridebr_e($label) . '</strong><small>' . stridebr_e((string) $group['description']) . '</small></div></div>';
        $html .= '<div class="sport-family-section"><div class="sport-family-section-title">Mais comuns</div>';
        foreach ($group['popular'] as $sport) $html .= sportPickerRenderGenericOption($sport, $label, $selectedId, $valueKey);
        $html .= '</div>';
        if ($group['more'] !== []) {
            $html .= '<button type="button" class="sport-family-more-toggle" data-generic-sport-more aria-expanded="false"><span>Mais esportes</span><span aria-hidden="true">⌄</span></button><div class="sport-family-more-list" data-generic-sport-more-list hidden>';
            foreach ($group['more'] as $sport) $html .= sportPickerRenderGenericOption($sport, $label, $selectedId, $valueKey);
            $html .= '</div>';
        }
        $html .= '</section>';
    }
    $html .= '<div class="sport-select-no-results" data-generic-sport-no-results hidden>Nenhum esporte encontrado.</div>';
    return $html . '</div></div></div>';
}

function sportPickerRenderGenericOption(array $sport, string $familyLabel, string $selectedId = '', string $valueKey = 'idmodalidade'): string
{
    $id = (string) ($sport[$valueKey] ?? '');
    $name = (string) ($sport['nome'] ?? 'Atividade');
    $slug = (string) ($sport['slug'] ?? '');
    $family = sportCatalogFamilyKey((string) ($sport['familia_hub'] ?? ''), (string) ($sport['categoria'] ?? ''), $slug);
    $search = stridebr_lower(trim($name . ' ' . $familyLabel . ' ' . (string) ($sport['categoria'] ?? '')));
    return '<button type="button" class="sport-option sport-generic-option" data-generic-sport-option data-sport-id="' . stridebr_e($id) . '" data-sport-name="' . stridebr_e($name) . '" data-sport-slug="' . stridebr_e($slug) . '" data-sport-family="' . stridebr_e($family) . '" data-search-text="' . stridebr_e($search) . '" aria-selected="' . ($selectedId === $id ? 'true' : 'false') . '"><span class="sport-option-icon">' . stridebr_sport_icon_html($slug) . '</span><span>' . stridebr_e($name) . '</span></button>';
}
