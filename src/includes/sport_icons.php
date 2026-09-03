<?php

declare(strict_types=1);

/**
 * Map StrideBR modalities to Player! Sporticon pictograms.
 *
 * Sports deliberately share pictograms when Sporticon does not provide a
 * more specific symbol. This keeps one visual language throughout the app.
 */
function stridebr_sport_icon_name(string $slug): string
{
    $groups = [
        'track_and_field' => [
            'corrida', 'corrida-em-trilha', 'corrida-em-esteira', 'caminhada', 'marcha-atletica', 'trilha',
            'atletismo', 'arremesso-de-peso', 'lancamento-de-disco', 'lancamento-de-dardo',
            'lancamento-de-martelo', 'salto-em-distancia', 'salto-em-altura', 'salto-com-vara',
            'cardio', 'eliptico', 'simulador-de-escada', 'pular-corda', 'outra-atividade',
        ],
        'wheelchair_rugby' => ['cadeira-de-rodas'],
        'cycling' => [
            'ciclismo', 'mountain-bike', 'downhill', 'bmx', 'gravel', 'bicicleta-eletrica',
            'e-mountain-bike', 'ciclismo-indoor', 'handcycle', 'velomovel',
        ],
        'swimming' => ['natacao'],
        'triathlon' => ['triatlo'],
        'rowing' => ['remo', 'remo-indoor'],
        'canoe_polo' => ['canoagem', 'caiaque'],
        'marine_sports' => ['stand-up-paddle', 'surfe', 'kitesurf', 'windsurf'],
        'sailing' => ['vela'],

        'tennis' => ['tenis'],
        'table_tennis' => ['tenis-de-mesa'],
        'badminton' => ['badminton'],
        'padel' => ['padel'],
        'beach_tennis' => ['beach-tennis'],
        'pickleball' => ['pickleball'],
        'squash' => ['squash'],
        'racquetball' => ['raquetebol'],

        'soccer' => ['futebol'],
        'futsal' => ['futsal'],
        'basketball' => ['basquete'],
        'volleyball' => ['volei'],
        'beach_volleyball' => ['volei-de-praia'],
        'handball' => ['handebol'],
        'rugby' => ['rugby'],
        'american_football' => ['futebol-americano'],
        'baseball' => ['criquete'],

        'weightlifting' => ['musculacao', 'academia', 'crossfit'],
        'gymnastics' => ['calistenia', 'hiit', 'treino-funcional', 'yoga', 'pilates', 'mobilidade'],
        'dance' => ['danca'],

        'combat' => ['boxe'],
        'judo' => ['judo', 'jiu-jitsu', 'karate', 'muay-thai', 'taekwondo', 'capoeira', 'kickboxing'],
        'wrestling' => ['luta-olimpica'],
        'fencing' => ['esgrima'],

        'climbing' => ['escalada', 'boulder'],
        'extreme_sports' => ['patinacao-inline', 'skate'],
        'ski_and_snowboard' => ['roller-ski', 'esqui-alpino', 'esqui-nordico', 'esqui-fora-de-pista', 'snowboard', 'raquete-de-neve'],
        'figure_skating' => ['patinacao-no-gelo'],
        'golf' => ['golfe'],
        'horse_racing' => ['equitacao'],
    ];

    foreach ($groups as $icon => $slugs) {
        if (in_array($slug, $slugs, true)) {
            return $icon;
        }
    }

    return 'track_and_field';
}

function stridebr_sport_icon_id(string $slug): string
{
    return stridebr_sport_icon_name($slug);
}

function stridebr_sport_icon_path(string $icon): string
{
    $root = dirname(__DIR__, 2) . '/public/assets/icons/sporticon';
    $path = $root . '/' . basename($icon) . '.svg';

    if (is_file($path)) {
        return $path;
    }

    return $root . '/track_and_field.svg';
}

function stridebr_sport_icon_html(string $slug, string $class = 'sport-icon'): string
{
    static $cache = [];

    $class = preg_replace('/[^a-zA-Z0-9 _-]/', '', $class) ?: 'sport-icon';
    $icon = stridebr_sport_icon_name($slug);
    $cacheKey = $icon . '|' . $class;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $path = stridebr_sport_icon_path($icon);
    if (!is_file($path)) {
        return '';
    }

    $url = '/assets/icons/sporticon/' . rawurlencode($icon) . '.svg';
    if (function_exists('stridebr_asset')) {
        $url = stridebr_asset($url);
    } else {
        $url .= '?v=' . ((int) (@filemtime($path) ?: 1));
    }

    $safeClass = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $html = '<span class="' . $safeClass . '" aria-hidden="true" style="--sport-icon-url:url(' . $safeUrl . ')"></span>';
    $cache[$cacheKey] = $html;
    return $html;
}

function stridebr_sport_icon_svg(string $slug, string $class = 'sport-icon'): string
{
    return stridebr_sport_icon_html($slug, $class);
}
