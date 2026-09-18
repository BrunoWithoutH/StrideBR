<?php

declare(strict_types=1);

function stridebr_normalize_exercise_name(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($value, Normalizer::FORM_KD);
        if (is_string($normalized)) $value = $normalized;
    } elseif (function_exists('iconv')) {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') $value = $transliterated;
    }
    $value = preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
    $value = strtr($value, [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
        'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','ç'=>'c','Ç'=>'C','ñ'=>'n','Ñ'=>'N',
    ]);
    $value = str_replace(["‐","‑","‒","–","—","―","_","/","\\"], ' ', $value);
    $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function stridebr_exercise_slug_key(string $value): string
{
    $normalized = stridebr_normalize_exercise_name($value);
    return $normalized === '' ? '' : str_replace(' ', '-', $normalized);
}

function stridebr_exercise_similarity(string $left, string $right): float
{
    $a = stridebr_normalize_exercise_name($left);
    $b = stridebr_normalize_exercise_name($right);
    if ($a === '' || $b === '') return 0.0;
    if ($a === $b) return 1.0;
    $max = max(strlen($a), strlen($b));
    if ($max === 0) return 1.0;
    $characterScore = max(0.0, 1.0 - (levenshtein($a, $b) / $max));
    $tokensA = array_values(array_unique(array_filter(explode(' ', $a))));
    $tokensB = array_values(array_unique(array_filter(explode(' ', $b))));
    $intersection = count(array_intersect($tokensA, $tokensB));
    $union = count(array_unique(array_merge($tokensA, $tokensB)));
    $tokenScore = $union > 0 ? $intersection / $union : 0.0;
    return max($characterScore, ($characterScore * 0.72) + ($tokenScore * 0.28));
}

function stridebr_exercise_alias_rows(array $item): array
{
    $aliases = $item['aliases'] ?? $item['aliases_json'] ?? [];
    if (is_string($aliases)) {
        $decoded = json_decode($aliases, true);
        $aliases = is_array($decoded) ? $decoded : [];
    }
    $out = [];
    foreach (is_array($aliases) ? $aliases : [] as $alias) {
        if (is_string($alias)) {
            $out[] = ['alias'=>$alias,'safe'=>true,'language'=>'und'];
            continue;
        }
        if (!is_array($alias)) continue;
        $name = trim((string) ($alias['alias'] ?? $alias['name'] ?? ''));
        if ($name === '') continue;
        $out[] = [
            'alias'=>$name,
            'safe'=>(bool) ($alias['safe'] ?? $alias['seguro'] ?? false),
            'language'=>(string) ($alias['language'] ?? $alias['idioma'] ?? 'und'),
        ];
    }
    return $out;
}

function stridebr_exercise_row(array $item): array
{
    $primary = $item['grupos_musculares_primarios'] ?? $item['primary_muscles'] ?? [];
    $secondary = $item['grupos_musculares_secundarios'] ?? $item['secondary_muscles'] ?? [];
    if (is_string($primary)) $primary = json_decode($primary, true) ?: [];
    if (is_string($secondary)) $secondary = json_decode($secondary, true) ?: [];
    return [
        'idexercicio'=>(string) ($item['idexercicio'] ?? $item['id'] ?? ''),
        'nome'=>(string) ($item['nome'] ?? $item['name'] ?? ''),
        'slug'=>(string) ($item['slug'] ?? ''),
        'idusuario'=>$item['idusuario'] ?? null,
        'descricao'=>$item['descricao'] ?? null,
        'imagem_url'=>$item['imagem_url'] ?? null,
        'video_url'=>$item['video_url'] ?? null,
        'equipamento'=>$item['equipamento'] ?? $item['equipment'] ?? null,
        'tipo_registro'=>(string) ($item['tipo_registro'] ?? $item['tracking_mode'] ?? 'load_reps'),
        'grupos_musculares_primarios'=>is_array($primary) ? $primary : [],
        'grupos_musculares_secundarios'=>is_array($secondary) ? $secondary : [],
        'categorias'=>(string) ($item['categorias'] ?? ''),
        'modalidades'=>(string) ($item['modalidades'] ?? ''),
        'aliases'=>stridebr_exercise_alias_rows($item),
    ];
}

function stridebr_exercise_resolve_catalog(array $catalog, array $input, array $aliases = []): array
{
    $requestedId = trim((string) ($input['idexercicio'] ?? $input['id'] ?? ''));
    $requestedName = trim((string) ($input['nome'] ?? $input['name'] ?? ''));
    $requestedSlug = trim((string) ($input['slug'] ?? ''));
    $byId = [];
    $byName = [];
    $bySlug = [];
    $byAlias = [];
    foreach ($catalog as $item) {
        if (!is_array($item)) continue;
        $row = stridebr_exercise_row($item);
        if ($row['idexercicio'] === '' || $row['nome'] === '') continue;
        $byId[$row['idexercicio']] = $row;
        $nameKey = stridebr_normalize_exercise_name($row['nome']);
        if ($nameKey !== '' && !isset($byName[$nameKey])) $byName[$nameKey] = $row;
        $slugKey = stridebr_exercise_slug_key($row['slug'] !== '' ? $row['slug'] : $row['nome']);
        if ($slugKey !== '' && !isset($bySlug[$slugKey])) $bySlug[$slugKey] = $row;
        foreach ($row['aliases'] as $alias) {
            $key = stridebr_normalize_exercise_name((string) $alias['alias']);
            if ($key === '') continue;
            $byAlias[$key][] = ['row'=>$row,'safe'=>(bool) $alias['safe']];
        }
    }
    foreach ($aliases as $alias => $targetName) {
        $key = stridebr_normalize_exercise_name((string) $alias);
        $target = stridebr_normalize_exercise_name((string) $targetName);
        if ($key !== '' && isset($byName[$target])) $byAlias[$key][] = ['row'=>$byName[$target],'safe'=>true];
    }
    if ($requestedId !== '' && isset($byId[$requestedId])) return ['status'=>'matched','reason'=>'id','match'=>$byId[$requestedId],'suggestions'=>[]];
    $nameKey = stridebr_normalize_exercise_name($requestedName);
    if ($nameKey !== '' && isset($byName[$nameKey])) return ['status'=>'matched','reason'=>'normalized_name','match'=>$byName[$nameKey],'suggestions'=>[]];
    $slugKey = stridebr_exercise_slug_key($requestedSlug !== '' ? $requestedSlug : $requestedName);
    if ($slugKey !== '' && isset($bySlug[$slugKey])) return ['status'=>'matched','reason'=>'slug','match'=>$bySlug[$slugKey],'suggestions'=>[]];
    if ($nameKey !== '' && isset($byAlias[$nameKey])) {
        $candidates = [];
        foreach ($byAlias[$nameKey] as $candidate) $candidates[$candidate['row']['idexercicio']] = $candidate;
        $candidates = array_values($candidates);
        if (count($candidates) === 1 && $candidates[0]['safe']) {
            return ['status'=>'matched','reason'=>'alias','match'=>$candidates[0]['row'],'suggestions'=>[]];
        }
        $suggestions = array_map(static fn(array $candidate): array => $candidate['row'] + ['score'=>1.0], array_slice($candidates,0,3));
        return ['status'=>'suggest','reason'=>count($candidates) > 1 ? 'alias_ambiguous' : 'alias_suggestion','match'=>null,'suggestions'=>$suggestions];
    }
    if ($nameKey === '') return ['status'=>'unknown','reason'=>'empty','match'=>null,'suggestions'=>[]];
    $scored = [];
    foreach ($byId as $row) {
        $score = stridebr_exercise_similarity($requestedName, $row['nome']);
        if ($score < 0.72) continue;
        $scored[] = ['score'=>$score,'item'=>$row];
    }
    usort($scored, static fn(array $a,array $b): int => $b['score'] <=> $a['score']);
    $suggestions = array_map(static fn(array $entry): array => $entry['item'] + ['score'=>round((float) $entry['score'],4)], array_slice($scored,0,3));
    $best = $scored[0]['score'] ?? 0.0;
    $second = $scored[1]['score'] ?? 0.0;
    if ($best >= 0.97 && ($second === 0.0 || ($best - $second) >= 0.08)) {
        return ['status'=>'matched','reason'=>'high_confidence','match'=>$scored[0]['item'],'suggestions'=>$suggestions];
    }
    return ['status'=>$suggestions !== [] ? 'suggest' : 'unknown','reason'=>$suggestions !== [] ? 'fuzzy' : 'none','match'=>null,'suggestions'=>$suggestions];
}

function stridebr_exercise_catalog_for_user(PDO $pdo, string $userId): array
{
    $hasAliases = function_exists('stridebr_db_table_exists') ? stridebr_db_table_exists($pdo, 'exercicios_aliases') : true;
    $aliasSelect = $hasAliases
        ? "COALESCE(jsonb_agg(jsonb_build_object('alias',ea.alias,'safe',ea.seguro,'language',ea.idioma) ORDER BY ea.alias) FILTER (WHERE ea.idalias IS NOT NULL),'[]'::jsonb) AS aliases_json"
        : "'[]'::jsonb AS aliases_json";
    $join = $hasAliases ? 'LEFT JOIN exercicios_aliases ea ON ea.idexercicio=e.idexercicio' : '';
    $sql = "SELECT e.idexercicio,e.nome,e.slug,e.idusuario,e.descricao,e.imagem_url,e.video_url,e.equipamento,e.tipo_registro,e.grupos_musculares_primarios,e.grupos_musculares_secundarios,{$aliasSelect}
            FROM exercicios e {$join}
            WHERE e.ativo=TRUE AND (e.idusuario IS NULL OR e.idusuario=:usuario)
            GROUP BY e.idexercicio
            ORDER BY e.idusuario NULLS LAST,e.nome";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':usuario'=>$userId]);
    return array_map('stridebr_exercise_row', $stmt->fetchAll());
}

function stridebr_exercise_search_catalog(array $catalog, string $query, array $filters = [], int $limit = 80): array
{
    $needle = stridebr_normalize_exercise_name($query);
    $source = (string) ($filters['source'] ?? 'all');
    $muscle = trim((string) ($filters['muscle'] ?? ''));
    $equipment = trim((string) ($filters['equipment'] ?? ''));
    $tracking = trim((string) ($filters['tracking'] ?? ''));
    $ranked = [];
    foreach ($catalog as $item) {
        $row = stridebr_exercise_row($item);
        if ($source === 'global' && $row['idusuario'] !== null) continue;
        if ($source === 'personal' && $row['idusuario'] === null) continue;
        $primary = $row['grupos_musculares_primarios'];
        if (is_string($primary)) $primary = json_decode($primary,true) ?: [];
        if ($muscle !== '' && !in_array($muscle, is_array($primary) ? $primary : [], true)) continue;
        if ($equipment !== '' && (string) $row['equipamento'] !== $equipment) continue;
        if ($tracking !== '' && $row['tipo_registro'] !== $tracking) continue;
        $score = 0;
        if ($needle !== '') {
            $name = stridebr_normalize_exercise_name($row['nome']);
            if ($name === $needle) $score = 100;
            elseif (str_starts_with($name,$needle)) $score = 80;
            elseif (str_contains($name,$needle)) $score = 60;
            foreach ($row['aliases'] as $alias) {
                $aliasKey = stridebr_normalize_exercise_name((string) $alias['alias']);
                if ($aliasKey === $needle) $score = max($score,95);
                elseif (str_starts_with($aliasKey,$needle)) $score = max($score,75);
                elseif (str_contains($aliasKey,$needle)) $score = max($score,55);
            }
            if ($score === 0 && $row['equipamento'] !== null && str_contains(stridebr_normalize_exercise_name((string) $row['equipamento']), $needle)) $score = 35;
            if ($score === 0) continue;
        }
        $ranked[] = ['score'=>$score,'row'=>$row];
    }
    usort($ranked, static function(array $a,array $b): int {
        if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];
        if (($a['row']['idusuario'] === null) !== ($b['row']['idusuario'] === null)) return $a['row']['idusuario'] === null ? 1 : -1;
        return strnatcasecmp($a['row']['nome'],$b['row']['nome']);
    });
    return array_slice(array_map(static fn(array $entry): array => $entry['row'], $ranked),0,max(1,$limit));
}

function stridebr_exercise_parse_legacy_duration(string $value): ?int
{
    $value = trim($value);
    if ($value === '') return null;
    if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(?:mn|min|mins|minuto|minutos)$/iu',$value,$m) === 1) return (int) round((float) str_replace(',','.',$m[1]) * 60);
    if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(?:sg|s|seg|segs|segundo|segundos)$/iu',$value,$m) === 1) return (int) round((float) str_replace(',','.',$m[1]));
    return null;
}

function stridebr_exercise_repair_legacy_metrics(array $row, array $resolution): array
{
    $match = is_array($resolution['match'] ?? null) ? $resolution['match'] : null;
    if ($match === null && is_array($resolution['suggestions'][0] ?? null) && ($resolution['reason'] ?? '') === 'alias_suggestion') $match = $resolution['suggestions'][0];
    $tracking = (string) ($match['tipo_registro'] ?? '');
    if (!in_array($tracking,['duration','duration_distance'],true)) return $row;
    if (trim((string) ($row['duracao'] ?? '')) !== '') return $row;
    $seconds = stridebr_exercise_parse_legacy_duration((string) ($row['repeticoes'] ?? ''));
    if ($seconds === null) return $row;
    $row['repeticoes'] = null;
    $row['duracao'] = $seconds . ' s';
    $row['duracao_segundos_legacy'] = $seconds;
    return $row;
}

function stridebr_exercise_resolve_entry(array $catalog, array $input, array $aliases = []): array
{
    $result = stridebr_exercise_resolve_catalog($catalog,$input,$aliases);
    if (($result['reason'] ?? '') === 'high_confidence') {
        $result['status']='suggest';
        $result['reason']='fuzzy';
        $result['match']=null;
    }
    return $result;
}
