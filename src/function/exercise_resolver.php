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
    $value = str_replace(["‐", "‑", "‒", "–", "—", "―", "_", "/", "\\"], ' ', $value);
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
    $distance = levenshtein($a, $b);
    $characterScore = max(0.0, 1.0 - ($distance / $max));
    $tokensA = array_values(array_unique(array_filter(explode(' ', $a))));
    $tokensB = array_values(array_unique(array_filter(explode(' ', $b))));
    $intersection = count(array_intersect($tokensA, $tokensB));
    $union = count(array_unique(array_merge($tokensA, $tokensB)));
    $tokenScore = $union > 0 ? $intersection / $union : 0.0;
    return max($characterScore, ($characterScore * 0.72) + ($tokenScore * 0.28));
}

function stridebr_exercise_resolve_catalog(array $catalog, array $input, array $aliases = []): array
{
    $requestedId = trim((string) ($input['idexercicio'] ?? $input['id'] ?? ''));
    $requestedName = trim((string) ($input['nome'] ?? $input['name'] ?? ''));
    $requestedSlug = trim((string) ($input['slug'] ?? ''));
    $byId = [];
    $byName = [];
    $bySlug = [];
    foreach ($catalog as $item) {
        if (!is_array($item)) continue;
        $id = trim((string) ($item['idexercicio'] ?? $item['id'] ?? ''));
        $name = trim((string) ($item['nome'] ?? $item['name'] ?? ''));
        $slug = trim((string) ($item['slug'] ?? ''));
        if ($id === '' || $name === '') continue;
        $row = ['idexercicio' => $id, 'nome' => $name, 'slug' => $slug];
        $byId[$id] = $row;
        $nameKey = stridebr_normalize_exercise_name($name);
        if ($nameKey !== '' && !isset($byName[$nameKey])) $byName[$nameKey] = $row;
        $slugKey = stridebr_exercise_slug_key($slug !== '' ? $slug : $name);
        if ($slugKey !== '' && !isset($bySlug[$slugKey])) $bySlug[$slugKey] = $row;
    }
    if ($requestedId !== '' && isset($byId[$requestedId])) {
        return ['status' => 'matched', 'reason' => 'id', 'match' => $byId[$requestedId], 'suggestions' => []];
    }
    $nameKey = stridebr_normalize_exercise_name($requestedName);
    if ($nameKey !== '' && isset($byName[$nameKey])) {
        return ['status' => 'matched', 'reason' => 'normalized_name', 'match' => $byName[$nameKey], 'suggestions' => []];
    }
    $slugKey = stridebr_exercise_slug_key($requestedSlug !== '' ? $requestedSlug : $requestedName);
    if ($slugKey !== '' && isset($bySlug[$slugKey])) {
        return ['status' => 'matched', 'reason' => 'slug', 'match' => $bySlug[$slugKey], 'suggestions' => []];
    }
    $normalizedAliases = [];
    foreach ($aliases as $alias => $targetName) {
        $normalizedAlias = stridebr_normalize_exercise_name((string) $alias);
        if ($normalizedAlias !== '') $normalizedAliases[$normalizedAlias] = (string) $targetName;
    }
    $aliasKey = $nameKey;
    if ($aliasKey !== '' && isset($normalizedAliases[$aliasKey])) {
        $target = stridebr_normalize_exercise_name($normalizedAliases[$aliasKey]);
        if ($target !== '' && isset($byName[$target])) {
            return ['status' => 'matched', 'reason' => 'alias', 'match' => $byName[$target], 'suggestions' => []];
        }
    }
    if ($nameKey === '') return ['status' => 'unknown', 'reason' => 'empty', 'match' => null, 'suggestions' => []];
    $scored = [];
    foreach ($byId as $row) {
        $score = stridebr_exercise_similarity($requestedName, $row['nome']);
        if ($score < 0.72) continue;
        $scored[] = ['score' => $score, 'item' => $row];
    }
    usort($scored, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    $suggestions = array_map(static fn(array $entry): array => $entry['item'] + ['score' => round((float) $entry['score'], 4)], array_slice($scored, 0, 3));
    $best = $scored[0]['score'] ?? 0.0;
    $second = $scored[1]['score'] ?? 0.0;
    if ($best >= 0.97 && ($second === 0.0 || ($best - $second) >= 0.08)) {
        return ['status' => 'matched', 'reason' => 'high_confidence', 'match' => $scored[0]['item'], 'suggestions' => $suggestions];
    }
    return ['status' => $suggestions !== [] ? 'suggest' : 'unknown', 'reason' => $suggestions !== [] ? 'fuzzy' : 'none', 'match' => null, 'suggestions' => $suggestions];
}

function stridebr_exercise_catalog_for_user(PDO $pdo, string $userId): array
{
    $stmt = $pdo->prepare('SELECT idexercicio, nome, slug FROM exercicios WHERE ativo = TRUE AND (idusuario IS NULL OR idusuario = :usuario) ORDER BY idusuario NULLS FIRST, nome');
    $stmt->execute([':usuario' => $userId]);
    return $stmt->fetchAll();
}
