<?php

declare(strict_types=1);

function sportCatalogFamilies(): array
{
    return [
        'strength' => ['label' => 'Força', 'description' => 'Musculação, calistenia e treinos de força.', 'popular' => ['musculacao','calistenia','treino-funcional','crossfit','powerlifting','levantamento-olimpico']],
        'cardio' => ['label' => 'Cardio', 'description' => 'Corrida, caminhada, ciclismo, natação e atividades contínuas.', 'popular' => ['corrida','caminhada','ciclismo','natacao','corrida-em-esteira','ciclismo-indoor','trilha','mountain-bike']],
        'athletics' => ['label' => 'Atletismo', 'description' => 'Pista, saltos, lançamentos, arremessos e provas combinadas.', 'popular' => ['atletismo-100m','atletismo-200m','atletismo-400m','atletismo-800m','atletismo-1500m','salto-em-distancia','salto-em-altura','arremesso-de-peso','lancamento-de-dardo']],
        'racket' => ['label' => 'Raquetes', 'description' => 'Tênis, padel, badminton, squash e esportes semelhantes.', 'popular' => ['tenis','padel','beach-tennis','badminton','tenis-de-mesa','squash','pickleball']],
        'team' => ['label' => 'Esportes em equipe', 'description' => 'Futebol, vôlei, basquete, handebol e outros esportes coletivos.', 'popular' => ['futebol','futsal','volei','basquete','handebol','volei-de-praia','futebol-society']],
        'combat' => ['label' => 'Lutas', 'description' => 'Artes marciais, boxe, grappling e esportes de combate.', 'popular' => ['jiu-jitsu','boxe','muay-thai','judo','karate','taekwondo','mma','capoeira']],
        'movement' => ['label' => 'Ginástica & movimento', 'description' => 'Ginástica, yoga, pilates, mobilidade e habilidades corporais.', 'popular' => ['yoga','pilates','mobilidade','alongamento','ginastica-artistica','parkour','ginastica-ritmica']],
        'outdoor' => ['label' => 'Outdoor & aventura', 'description' => 'Escalada, montanha, prancha e atividades de aventura.', 'popular' => ['hiking','trekking','escalada','boulder','surfe','stand-up-paddle','skate','orientacao']],
        'precision' => ['label' => 'Precisão', 'description' => 'Golfe, tiro com arco, boliche, sinuca e modalidades de precisão.', 'popular' => ['golfe','tiro-com-arco','boliche','sinuca','dardos','bocha']],
        'winter' => ['label' => 'Inverno', 'description' => 'Esqui, snowboard, patinação e esportes de neve ou gelo.', 'popular' => ['esqui-alpino','snowboard','esqui-cross-country','patinacao-no-gelo','hoquei-no-gelo','curling']],
        'dance' => ['label' => 'Dança', 'description' => 'Dança social, artística e fitness.', 'popular' => ['zumba','forro','samba','danca-de-salao','hip-hop','ballet','salsa']],
        'equestrian' => ['label' => 'Equestres', 'description' => 'Equitação, hipismo, enduro e outros esportes equestres.', 'popular' => ['equitacao','hipismo-salto','adestramento-equestre','enduro-equestre','polo']],
        'motorsport' => ['label' => 'Motores', 'description' => 'Automobilismo, kart e motociclismo esportivo.', 'popular' => ['kart','automobilismo-de-pista','rally','motocross','enduro-de-moto']],
        'other' => ['label' => 'Outros', 'description' => 'Atividades que não se encaixam nas demais áreas.', 'popular' => ['outra-atividade']],
    ];
}

function sportCatalogFamilyKey(string $family, string $category = '', string $slug = ''): string
{
    $families = sportCatalogFamilies();
    $family = stridebr_lower(trim($family));
    if ($family !== '' && isset($families[$family])) return $family;
    $category = stridebr_lower(trim($category));
    $slug = stridebr_lower(trim($slug));
    if (str_contains($category, 'força') || in_array($slug, ['musculacao','calistenia','crossfit','hiit','treino-funcional'], true)) return 'strength';
    if (str_contains($category, 'atletismo') || str_starts_with($slug, 'atletismo-') || in_array($slug, ['marcha-atletica','salto-em-distancia','salto-em-altura','salto-com-vara','salto-triplo','arremesso-de-peso','lancamento-de-disco','lancamento-de-dardo','lancamento-de-martelo'], true)) return 'athletics';
    if (str_contains($category, 'raquete')) return 'racket';
    if (str_contains($category, 'equipe') || str_contains($category, 'coletiv')) return 'team';
    if (str_contains($category, 'luta')) return 'combat';
    if (str_contains($category, 'ginástica') || str_contains($category, 'movimento')) return 'movement';
    if (str_contains($category, 'outdoor') || str_contains($category, 'aventura') || str_contains($category, 'escalada')) return 'outdoor';
    if (str_contains($category, 'precisão')) return 'precision';
    if (str_contains($category, 'inverno')) return 'winter';
    if (str_contains($category, 'dança')) return 'dance';
    if (str_contains($category, 'equestre')) return 'equestrian';
    if (str_contains($category, 'motor')) return 'motorsport';
    if (in_array($category, ['cardio','corrida e caminhada','ciclismo','aquáticos','multiesporte'], true) || in_array($slug, ['triatlo','duatlo','aquatlo','swimrun'], true)) return 'cardio';
    return 'other';
}

function sportCatalogGroups(array $sports): array
{
    $families = sportCatalogFamilies();
    $groups = [];
    foreach ($families as $key => $meta) {
        $groups[$key] = ['key' => $key, 'label' => $meta['label'], 'description' => $meta['description'], 'popular' => [], 'more' => []];
    }
    foreach ($sports as $sport) {
        $family = sportCatalogFamilyKey((string) ($sport['familia_hub'] ?? ''), (string) ($sport['categoria'] ?? ''), (string) ($sport['slug'] ?? ''));
        $sport['familia_hub'] = $family;
        $popular = $families[$family]['popular'] ?? [];
        if (in_array((string) ($sport['slug'] ?? ''), $popular, true)) $groups[$family]['popular'][] = $sport;
        else $groups[$family]['more'][] = $sport;
    }
    foreach ($groups as $key => &$group) {
        $rank = array_flip($families[$key]['popular'] ?? []);
        usort($group['popular'], static function (array $a, array $b) use ($rank): int {
            $ra = $rank[(string) ($a['slug'] ?? '')] ?? 9999;
            $rb = $rank[(string) ($b['slug'] ?? '')] ?? 9999;
            return $ra <=> $rb ?: strnatcasecmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));
        });
        usort($group['more'], static fn(array $a, array $b): int => strnatcasecmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? '')));
        if ($group['popular'] === [] && $group['more'] !== []) {
            $group['popular'] = array_splice($group['more'], 0, min(6, count($group['more'])));
        }
        if ($group['popular'] === [] && $group['more'] === []) unset($groups[$key]);
    }
    unset($group);
    return $groups;
}
