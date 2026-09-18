<?php

declare(strict_types=1);

/**
 * Only known defaults can be replaced. Unknown stored values are conservative
 * boundaries (including after losing the browser's authenticated PHP session).
 * Provenance is session-scoped; actual loads always persist in PostgreSQL.
 */
function stridebr_workout_field_targets(array $sets, string $sourceId, array $inherited, string $field): array
{
    $columns = ['load'=>'carga_realizada','reps'=>'repeticoes_realizadas','duration'=>'duracao_realizada_s','distance'=>'distancia_realizada_m'];
    if (!isset($columns[$field])) throw new InvalidArgumentException('Invalid workout field');
    $forward = false;
    $targets = [];
    foreach ($sets as $set) {
        $id = (string) $set['idserie'];
        if ($id === $sourceId) { $forward = true; continue; }
        if (!$forward) continue;
        if (array_key_exists($id, $inherited) && $inherited[$id] === null) break;
        $value = (string) ($set[$columns[$field]] ?? '');
        if ($value !== '' && (!array_key_exists($id, $inherited) || $inherited[$id] !== $value)) break;
        if ($set['concluida']) continue;
        $targets[] = $id;
    }
    return $targets;
}

/** Backward-compatible load-only entry point. */
function stridebr_workout_load_targets(array $sets, string $sourceId, array $inherited): array
{
    return stridebr_workout_field_targets($sets, $sourceId, $inherited, 'load');
}
