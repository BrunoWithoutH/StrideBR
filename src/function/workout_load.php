<?php

declare(strict_types=1);

/**
 * Only known defaults can be replaced. Unknown stored values are conservative
 * boundaries (including after losing the browser's authenticated PHP session).
 * Provenance is session-scoped; actual loads always persist in PostgreSQL.
 */
function stridebr_workout_load_targets(array $sets, string $sourceId, array $inherited): array
{
    $forward = false;
    $targets = [];
    foreach ($sets as $set) {
        $id = (string) $set['idserie'];
        if ($id === $sourceId) { $forward = true; continue; }
        if (!$forward) continue;
        if (array_key_exists($id, $inherited) && $inherited[$id] === null) break;
        $value = (string) ($set['carga_realizada'] ?? '');
        if ($value !== '' && (!array_key_exists($id, $inherited) || $inherited[$id] !== $value)) break;
        if ($set['concluida']) continue;
        $targets[] = $id;
    }
    return $targets;
}
