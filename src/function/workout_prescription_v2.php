<?php

declare(strict_types=1);

function workoutPrescriptionJson(mixed $value): ?string
{
    if ($value === null) return null;
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function workoutPrescriptionDecode(mixed $value): array
{
    if (is_array($value)) return $value;
    if ($value === null || trim((string) $value) === '') return [];
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? $decoded : [];
}

function workoutPrescriptionAssertKeys(array $value, array $allowed, string $context): void
{
    $unknown = array_values(array_diff(array_keys($value), $allowed));
    if ($unknown !== []) throw new InvalidArgumentException($context . ' contém campos desconhecidos.');
}

function workoutPrescriptionInt(mixed $value, int $min, int $max, string $field, bool $nullable = true): ?int
{
    if ($value === null || trim((string) $value) === '') {
        if ($nullable) return null;
        throw new InvalidArgumentException($field . ' é obrigatório.');
    }
    if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new InvalidArgumentException($field . ' precisa ser inteiro.');
    $number = (int) $value;
    if ($number < $min || $number > $max) throw new InvalidArgumentException($field . ' está fora do intervalo permitido.');
    return $number;
}

function workoutPrescriptionFloat(mixed $value, float $min, float $max, string $field, bool $nullable = true): ?float
{
    if ($value === null || trim((string) $value) === '') {
        if ($nullable) return null;
        throw new InvalidArgumentException($field . ' é obrigatório.');
    }
    $raw = str_replace(',', '.', trim((string) $value));
    if (!is_numeric($raw)) throw new InvalidArgumentException($field . ' precisa ser numérico.');
    $number = (float) $raw;
    if (!is_finite($number) || $number < $min || $number > $max) throw new InvalidArgumentException($field . ' está fora do intervalo permitido.');
    return $number;
}

function workoutPrescriptionRepTargetFromLegacy(mixed $value): array
{
    $raw = trim((string) $value);
    if ($raw === '') return ['mode' => 'fixed', 'value' => null];
    $lower = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
    if (preg_match('/\bamrap\b/i', $raw) === 1) return ['mode' => 'amrap'];
    if (preg_match('/(?:falha|failure)/iu', $raw) === 1) return ['mode' => 'failure'];
    if (preg_match('/^(\d{1,3})\s*(?:-|–|—|a)\s*(\d{1,3})(?:\s*(?:rep|reps|repetições|repeticoes))?$/iu', $raw, $m) === 1) {
        $min = max(1, min(999, (int) $m[1]));
        $max = max(1, min(999, (int) $m[2]));
        if ($max < $min) [$min, $max] = [$max, $min];
        return ['mode' => 'range', 'min' => $min, 'max' => $max];
    }
    if (preg_match('/^(\d{1,3})(?:\s*(?:rep|reps|repetições|repeticoes))?$/iu', $raw, $m) === 1) return ['mode' => 'fixed', 'value' => (int) $m[1]];
    return ['mode' => 'legacy', 'text' => $raw, 'normalized' => $lower];
}

function workoutPrescriptionNormalizeRepTarget(mixed $source, mixed $legacy = null): array
{
    $data = is_array($source) ? $source : [];
    if ($data !== []) workoutPrescriptionAssertKeys($data, ['mode','value','min','max','text','normalized'], 'Meta de repetições');
    $mode = trim((string) ($data['mode'] ?? ''));
    if ($mode === '') return workoutPrescriptionRepTargetFromLegacy($legacy);
    if ($mode === 'legacy') {
        $text = trim((string) ($data['text'] ?? $legacy ?? ''));
        if ($text === '' || strlen($text) > 80) throw new InvalidArgumentException('Meta legacy inválida.');
        return ['mode' => 'legacy', 'text' => $text];
    }
    if (!in_array($mode, ['fixed','range','amrap','failure'], true)) throw new InvalidArgumentException('Meta de repetições inválida.');
    if ($mode === 'fixed') return ['mode' => 'fixed', 'value' => workoutPrescriptionInt($data['value'] ?? null, 1, 999, 'Repetições', true)];
    if ($mode === 'range') {
        $min = workoutPrescriptionInt($data['min'] ?? null, 1, 999, 'Repetições mínimas', false);
        $max = workoutPrescriptionInt($data['max'] ?? null, 1, 999, 'Repetições máximas', false);
        if ($max < $min) throw new InvalidArgumentException('A faixa de repetições está invertida.');
        return ['mode' => 'range', 'min' => $min, 'max' => $max];
    }
    return ['mode' => $mode];
}

function workoutPrescriptionNormalizeLoad(mixed $source, mixed $legacy = null): ?array
{
    if (is_array($source)) {
        workoutPrescriptionAssertKeys($source, ['value','unit','text'], 'Carga');
        if (array_key_exists('text', $source) && trim((string) $source['text']) !== '') {
            $text = trim((string) $source['text']);
            if (strlen($text) > 80) throw new InvalidArgumentException('Carga legacy inválida.');
            return ['text' => $text];
        }
        $value = workoutPrescriptionFloat($source['value'] ?? null, 0, 99999.999, 'Carga', true);
        if ($value !== null) {
            $unit = trim((string) ($source['unit'] ?? 'kg'));
            if (!in_array($unit, ['kg'], true)) throw new InvalidArgumentException('Unidade de carga inválida.');
            return ['value' => $value, 'unit' => $unit];
        }
    }
    $raw = trim((string) $legacy);
    if ($raw === '') return null;
    if (preg_match('/^([0-9]+(?:[.,][0-9]+)?)\s*(kg)?$/iu', $raw, $m) !== 1) return ['text' => $raw];
    return ['value' => (float) str_replace(',', '.', $m[1]), 'unit' => 'kg'];
}

function workoutPrescriptionRestSeconds(mixed $value, mixed $legacy = null): ?int
{
    if ($value !== null && trim((string) $value) !== '') return workoutPrescriptionInt($value, 0, 86400, 'Descanso', true);
    $raw = trim((string) $legacy);
    if ($raw === '') return null;
    if (preg_match('/^(\d{1,5})\s*(s|seg|segs|segundo|segundos)?$/iu', $raw, $m) === 1) return min(86400, (int) $m[1]);
    if (preg_match('/^(\d{1,4})\s*(m|min|mins|minuto|minutos)$/iu', $raw, $m) === 1) return min(86400, (int) $m[1] * 60);
    return null;
}

function workoutPrescriptionDurationSeconds(mixed $value): ?int
{
    if ($value === null || trim((string) $value) === '') return null;
    if (is_int($value) || is_float($value) || preg_match('/^\d+(?:[.,]\d+)?$/', trim((string) $value)) === 1) {
        $seconds = workoutPrescriptionFloat($value, 0, 604800, 'Duração', false);
        return (int) round($seconds);
    }
    $raw = function_exists('stridebr_lower') ? stridebr_lower(trim((string) $value)) : strtolower(trim((string) $value));
    $raw = str_replace(',', '.', $raw);
    if (preg_match('/^(\d{1,3}):([0-5]\d)(?::([0-5]\d))?$/', $raw, $m) === 1) {
        return isset($m[3]) ? ((int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3]) : ((int) $m[1] * 60 + (int) $m[2]);
    }
    if (preg_match('/^(\d+(?:\.\d+)?)\s*(h|hr|hrs|hora|horas)$/u', $raw, $m) === 1) return (int) round((float) $m[1] * 3600);
    if (preg_match('/^(\d+(?:\.\d+)?)\s*(m|min|mins|minuto|minutos|mn)$/u', $raw, $m) === 1) return (int) round((float) $m[1] * 60);
    if (preg_match('/^(\d+(?:\.\d+)?)\s*(s|seg|segs|segundo|segundos)$/u', $raw, $m) === 1) return (int) round((float) $m[1]);
    return null;
}

function workoutPrescriptionLegacyDistance(mixed $value): array
{
    $raw = trim((string) $value);
    if ($raw === '') return ['meters' => null, 'unit' => null];
    $normalized = str_replace(',', '.', function_exists('stridebr_lower') ? stridebr_lower($raw) : strtolower($raw));
    if (preg_match('/^(\d+(?:\.\d+)?)\s*(km|quil[oô]metros?)$/u', $normalized, $m) === 1) return ['meters' => (float) $m[1] * 1000, 'unit' => 'km'];
    if (preg_match('/^(\d+(?:\.\d+)?)\s*(m|metros?)$/u', $normalized, $m) === 1) return ['meters' => (float) $m[1], 'unit' => 'm'];
    return ['meters' => null, 'unit' => null];
}

function workoutPrescriptionFormatDuration(?int $seconds): ?string
{
    if ($seconds === null) return null;
    if ($seconds >= 3600 && $seconds % 3600 === 0) return ($seconds / 3600) . ' h';
    if ($seconds >= 60 && $seconds % 60 === 0) return ($seconds / 60) . ' min';
    return $seconds . ' s';
}

function workoutPrescriptionFormatDistance(?float $meters, string $unit): ?string
{
    if ($meters === null) return null;
    $value = $unit === 'km' ? $meters / 1000 : $meters;
    $precision = $unit === 'm' ? 2 : 3;
    $text = rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.');
    return $text . ' ' . $unit;
}

function workoutPrescriptionDurationFromSource(array $source, mixed $legacy = null): ?int
{
    if (array_key_exists('duration_s', $source)) return workoutPrescriptionDurationSeconds($source['duration_s']);
    if (is_array($source['duration'] ?? null)) {
        workoutPrescriptionAssertKeys($source['duration'], ['value','unit'], 'Duração');
        $value = workoutPrescriptionFloat($source['duration']['value'] ?? null, 0, 604800, 'Duração', true);
        if ($value === null) return workoutPrescriptionDurationSeconds($legacy);
        $unit = trim((string) ($source['duration']['unit'] ?? 's'));
        if (!in_array($unit, ['s','min','h'], true)) throw new InvalidArgumentException('Unidade de duração inválida.');
        return (int) round($value * ($unit === 'h' ? 3600 : ($unit === 'min' ? 60 : 1)));
    }
    return workoutPrescriptionDurationSeconds($legacy);
}

function workoutPrescriptionDistanceFromSource(array $source, mixed $legacy = null): array
{
    if (array_key_exists('distance_m', $source)) {
        $meters = workoutPrescriptionFloat($source['distance_m'], 0, 999999999.999, 'Distância', true);
        $unit = trim((string) ($source['distance_display_unit'] ?? 'm'));
        if (!in_array($unit, ['m','km'], true)) throw new InvalidArgumentException('Unidade de distância inválida.');
        return ['meters' => $meters, 'unit' => $meters === null ? null : $unit];
    }
    if (is_array($source['distance'] ?? null)) {
        workoutPrescriptionAssertKeys($source['distance'], ['value','unit'], 'Distância');
        $unit = trim((string) ($source['distance']['unit'] ?? 'm'));
        $meters = workoutPrescriptionDistanceMeters($source['distance']['value'] ?? null, $unit);
        if ($meters !== null) return ['meters' => $meters, 'unit' => $unit];
        return workoutPrescriptionLegacyDistance($legacy);
    }
    return workoutPrescriptionLegacyDistance($legacy);
}

function workoutPrescriptionNormalize(array $row): array
{
    $stored = workoutPrescriptionDecode($row['config_prescricao'] ?? null);
    $method = trim((string) ($row['metodo_prescricao'] ?? $stored['method'] ?? 'standard'));
    if (!in_array($method, ['standard','cluster','drop_set'], true)) throw new InvalidArgumentException('Método de prescrição inválido.');
    $posted = is_array($row['prescription'] ?? null) ? $row['prescription'] : [];
    if ($posted !== []) $method = trim((string) ($posted['method'] ?? $method));
    if (!in_array($method, ['standard','cluster','drop_set'], true)) throw new InvalidArgumentException('Método de prescrição inválido.');
    $source = $posted !== [] ? $posted : $stored;
    if ($posted !== []) {
        $allowed = $method === 'standard'
            ? ['version','method','sets','reps','load','rest_after_s','duration_s','duration','distance_m','distance_display_unit','distance','effort']
            : ($method === 'cluster'
                ? ['version','method','blocks','clusters','load','intra_cluster_rest_s','between_blocks_rest_s']
                : ['version','method','rounds','stages','round_rest_s']);
        workoutPrescriptionAssertKeys($posted, $allowed, 'Prescrição');
    }
    if ($method === 'standard') {
        $sets = workoutPrescriptionInt($source['sets'] ?? $row['series'] ?? null, 1, 99, 'Séries', true);
        $reps = workoutPrescriptionNormalizeRepTarget($source['reps'] ?? null, $row['repeticoes'] ?? null);
        $load = workoutPrescriptionNormalizeLoad($source['load'] ?? null, $row['carga'] ?? null);
        $rest = workoutPrescriptionRestSeconds($source['rest_after_s'] ?? null, $row['descanso'] ?? null);
        $duration = workoutPrescriptionDurationFromSource($source, $row['duracao'] ?? null);
        $distanceData = workoutPrescriptionDistanceFromSource($source, $row['distancia'] ?? null);
        $distance = $distanceData['meters'];
        $distanceUnit = $distanceData['unit'];
        $effort = null;
        if (is_array($source['effort'] ?? null)) {
            workoutPrescriptionAssertKeys($source['effort'], ['type','value'], 'Esforço');
            $type = trim((string) ($source['effort']['type'] ?? ''));
            if ($type !== '') {
                if (!in_array($type, ['rir','rpe'], true)) throw new InvalidArgumentException('Meta de esforço inválida.');
                $value = workoutPrescriptionFloat($source['effort']['value'] ?? null, 0, 10, strtoupper($type), false);
                $effort = ['type' => $type, 'value' => $value];
            } elseif (($row['rir'] ?? null) !== null && trim((string) ($row['rir'] ?? '')) !== '') {
                $effort = ['type' => 'rir', 'value' => (float) $row['rir']];
            } elseif (($row['rpe'] ?? null) !== null && trim((string) ($row['rpe'] ?? '')) !== '') {
                $effort = ['type' => 'rpe', 'value' => (float) $row['rpe']];
            }
        } elseif (($row['rir'] ?? null) !== null && trim((string) ($row['rir'] ?? '')) !== '') {
            $effort = ['type' => 'rir', 'value' => (float) $row['rir']];
        } elseif (($row['rpe'] ?? null) !== null && trim((string) ($row['rpe'] ?? '')) !== '') {
            $effort = ['type' => 'rpe', 'value' => (float) $row['rpe']];
        }
        return ['method' => 'standard', 'config' => array_filter([
            'version' => 1,
            'method' => 'standard',
            'sets' => $sets,
            'reps' => $reps,
            'load' => $load,
            'rest_after_s' => $rest,
            'duration_s' => $duration,
            'distance_m' => $distance,
            'distance_display_unit' => $distance !== null ? $distanceUnit : null,
            'effort' => $effort,
        ], static fn(mixed $value): bool => $value !== null)];
    }
    if ($method === 'cluster') {
        $blocks = workoutPrescriptionInt($source['blocks'] ?? $row['series'] ?? null, 1, 99, 'Blocos', false);
        $clusters = [];
        $sourceClusters = is_array($source['clusters'] ?? null) ? $source['clusters'] : [];
        if ($sourceClusters === []) {
            $pattern = trim((string) ($row['cluster'] ?? ''));
            if ($pattern !== '' && preg_match('/^\s*\d{1,3}(?:\s*\+\s*\d{1,3})+\s*$/', $pattern) === 1) {
                foreach (preg_split('/\s*\+\s*/', $pattern) ?: [] as $piece) $sourceClusters[] = ['reps' => (int) $piece];
            }
        }
        if (count($sourceClusters) < 2 || count($sourceClusters) > 12) throw new InvalidArgumentException('Cluster precisa ter entre 2 e 12 partes.');
        foreach ($sourceClusters as $piece) {
            if (!is_array($piece)) throw new InvalidArgumentException('Estrutura de cluster inválida.');
            workoutPrescriptionAssertKeys($piece, ['reps'], 'Parte do cluster');
            $clusters[] = ['reps' => workoutPrescriptionInt($piece['reps'] ?? null, 1, 999, 'Repetições do cluster', false)];
        }
        return ['method' => 'cluster', 'config' => array_filter([
            'version' => 1,
            'method' => 'cluster',
            'blocks' => $blocks,
            'clusters' => $clusters,
            'load' => workoutPrescriptionNormalizeLoad($source['load'] ?? null, $row['carga'] ?? null),
            'intra_cluster_rest_s' => workoutPrescriptionRestSeconds($source['intra_cluster_rest_s'] ?? null),
            'between_blocks_rest_s' => workoutPrescriptionRestSeconds($source['between_blocks_rest_s'] ?? null, $row['descanso'] ?? null),
        ], static fn(mixed $value): bool => $value !== null)];
    }
    $rounds = workoutPrescriptionInt($source['rounds'] ?? $row['series'] ?? 1, 1, 20, 'Rodadas', false);
    $stages = is_array($source['stages'] ?? null) ? array_values($source['stages']) : [];
    if (count($stages) < 2 || count($stages) > 10) throw new InvalidArgumentException('Drop set precisa ter entre 2 e 10 etapas.');
    $normalizedStages = [];
    foreach ($stages as $index => $stage) {
        if (!is_array($stage)) throw new InvalidArgumentException('Etapa do drop set inválida.');
        workoutPrescriptionAssertKeys($stage, ['load','reps','rest_after_s'], 'Etapa do drop set');
        $normalizedStages[] = array_filter([
            'load' => workoutPrescriptionNormalizeLoad($stage['load'] ?? null),
            'reps' => workoutPrescriptionNormalizeRepTarget($stage['reps'] ?? null),
            'rest_after_s' => workoutPrescriptionRestSeconds($stage['rest_after_s'] ?? 0),
        ], static fn(mixed $value): bool => $value !== null);
    }
    return ['method' => 'drop_set', 'config' => array_filter([
        'version' => 1,
        'method' => 'drop_set',
        'rounds' => $rounds,
        'stages' => $normalizedStages,
        'round_rest_s' => workoutPrescriptionRestSeconds($source['round_rest_s'] ?? null, $row['descanso'] ?? null),
    ], static fn(mixed $value): bool => $value !== null)];
}


function workoutPrescriptionText(string $key, string $fallback, array $replace = []): string
{
    return function_exists('stridebr_t') ? stridebr_t($key, $replace, $fallback) : $fallback;
}

function workoutPrescriptionRepText(array $reps): ?string
{
    return match ($reps['mode'] ?? '') {
        'fixed' => isset($reps['value']) && $reps['value'] !== null ? (string) $reps['value'] : null,
        'range' => (string) ($reps['min'] ?? '') . '–' . (string) ($reps['max'] ?? ''),
        'amrap' => 'AMRAP',
        'failure' => workoutPrescriptionText('workout_builder.rep.failure', 'Até a falha'),
        'legacy' => trim((string) ($reps['text'] ?? '')) ?: null,
        default => null,
    };
}

function workoutPrescriptionLoadText(?array $load): ?string
{
    if (!$load) return null;
    if (isset($load['text'])) return trim((string) $load['text']) ?: null;
    if (!isset($load['value'])) return null;
    $value = rtrim(rtrim(number_format((float) $load['value'], 3, '.', ''), '0'), '.');
    return $value . ' ' . ($load['unit'] ?? 'kg');
}

function workoutPrescriptionLegacyFields(array $row, array $normalized): array
{
    $method = $normalized['method'];
    $config = $normalized['config'];
    $result = $row;
    if ($method === 'standard') {
        $result['series'] = $config['sets'] ?? ($row['series'] ?? null);
        $result['repeticoes'] = workoutPrescriptionRepText($config['reps'] ?? []) ?? ($row['repeticoes'] ?? null);
        $result['carga'] = workoutPrescriptionLoadText($config['load'] ?? null) ?? ($row['carga'] ?? null);
        $result['descanso'] = isset($config['rest_after_s']) ? ((int) $config['rest_after_s']) . ' s' : ($row['descanso'] ?? null);
        $result['duracao'] = workoutPrescriptionFormatDuration(isset($config['duration_s']) ? (int) $config['duration_s'] : null) ?? ($row['duracao'] ?? null);
        $result['distancia'] = workoutPrescriptionFormatDistance(isset($config['distance_m']) ? (float) $config['distance_m'] : null, (string) ($config['distance_display_unit'] ?? 'm')) ?? ($row['distancia'] ?? null);
        if (($config['effort']['type'] ?? null) === 'rir') $result['rir'] = $config['effort']['value'];
        if (($config['effort']['type'] ?? null) === 'rpe') $result['rpe'] = $config['effort']['value'];
    } elseif ($method === 'cluster') {
        $result['series'] = $config['blocks'];
        $pattern = implode('+', array_map(static fn(array $piece): string => (string) $piece['reps'], $config['clusters']));
        $result['cluster'] = $pattern;
        $result['repeticoes'] = $pattern;
        $result['carga'] = workoutPrescriptionLoadText($config['load'] ?? null) ?? ($row['carga'] ?? null);
        $result['descanso'] = isset($config['between_blocks_rest_s']) ? ((int) $config['between_blocks_rest_s']) . ' s' : ($row['descanso'] ?? null);
    } else {
        $result['series'] = $config['rounds'];
        $parts = [];
        foreach ($config['stages'] as $stage) {
            $load = workoutPrescriptionLoadText($stage['load'] ?? null);
            $reps = workoutPrescriptionRepText($stage['reps'] ?? []);
            $parts[] = trim(($load ? preg_replace('/\s*kg$/i', '', $load) : '') . ($load && $reps ? '×' : '') . ($reps ?? ''));
        }
        $result['repeticoes'] = implode(' → ', array_filter($parts));
        $result['carga'] = workoutPrescriptionLoadText($config['stages'][0]['load'] ?? null) ?? ($row['carga'] ?? null);
        $result['descanso'] = isset($config['round_rest_s']) ? ((int) $config['round_rest_s']) . ' s' : ($row['descanso'] ?? null);
    }
    $result['metodo_prescricao'] = $method;
    $result['config_prescricao'] = workoutPrescriptionJson($config);
    return $result;
}

function workoutPrescriptionSummary(array $row): string
{
    $normalized = workoutPrescriptionNormalize($row);
    $method = $normalized['method'];
    $config = $normalized['config'];
    if ($method === 'cluster') {
        $pattern = implode('+', array_map(static fn(array $piece): string => (string) $piece['reps'], $config['clusters']));
        $parts = [workoutPrescriptionText('workout_builder.summary.blocks', '{count} blocks', ['count' => (string) ($config['blocks'] ?? 1)]), $pattern, workoutPrescriptionLoadText($config['load'] ?? null)];
        if (isset($config['intra_cluster_rest_s'])) $parts[] = workoutPrescriptionText('workout_builder.summary.intra_rest', '{seconds} s intra', ['seconds' => (string) $config['intra_cluster_rest_s']]);
        if (isset($config['between_blocks_rest_s'])) $parts[] = workoutPrescriptionText('workout_builder.summary.rest', '{seconds} s rest', ['seconds' => (string) $config['between_blocks_rest_s']]);
        return implode(' · ', array_filter($parts));
    }
    if ($method === 'drop_set') {
        $stages = [];
        foreach ($config['stages'] as $stage) {
            $load = workoutPrescriptionLoadText($stage['load'] ?? null);
            $load = $load ? preg_replace('/\s*kg$/i', '', $load) : '';
            $reps = workoutPrescriptionRepText($stage['reps'] ?? []);
            $stages[] = trim(($load ?: '') . ($load && $reps ? '×' : '') . ($reps ?? ''));
        }
        $prefix = ($config['rounds'] ?? 1) > 1 ? (workoutPrescriptionText('workout_builder.summary.rounds', '{count} rounds', ['count' => (string) $config['rounds']]) . ' · ') : '';
        return $prefix . implode(' → ', array_filter($stages));
    }
    $parts = [];
    if (isset($config['sets'])) $parts[] = $config['sets'] . ' × ' . (workoutPrescriptionRepText($config['reps'] ?? []) ?? '—');
    elseif (($reps = workoutPrescriptionRepText($config['reps'] ?? [])) !== null) $parts[] = $reps;
    if (($load = workoutPrescriptionLoadText($config['load'] ?? null)) !== null) $parts[] = $load;
    if (isset($config['duration_s'])) $parts[] = workoutPrescriptionFormatDuration((int) $config['duration_s']);
    if (isset($config['distance_m'])) $parts[] = workoutPrescriptionFormatDistance((float) $config['distance_m'], (string) ($config['distance_display_unit'] ?? 'm'));
    if (isset($config['rest_after_s'])) $parts[] = $config['rest_after_s'] . ' s';
    return implode(' · ', array_filter($parts));
}

function workoutPrescriptionMaterializeSets(array $row): array
{
    $normalized = workoutPrescriptionNormalize($row);
    $method = $normalized['method'];
    $config = $normalized['config'];
    $sets = [];
    if ($method === 'standard') {
        $count = max(1, (int) ($config['sets'] ?? $row['series'] ?? 1));
        for ($number = 1; $number <= $count; $number++) {
            $sets[] = [
                'segment_type' => 'set',
                'block_index' => null,
                'stage_index' => null,
                'planned_repetitions' => workoutPrescriptionRepText($config['reps'] ?? []),
                'planned_load' => workoutPrescriptionLoadText($config['load'] ?? null),
                'planned_duration_s' => $config['duration_s'] ?? null,
                'planned_distance_m' => $config['distance_m'] ?? null,
                'rep_target' => $config['reps'] ?? null,
                'rest_after_s' => $config['rest_after_s'] ?? null,
            ];
        }
        return $sets;
    }
    if ($method === 'cluster') {
        $number = 0;
        $blocks = (int) $config['blocks'];
        foreach (range(1, $blocks) as $block) {
            foreach ($config['clusters'] as $stageIndex => $piece) {
                $number++;
                $sets[] = [
                    'segment_type' => 'cluster',
                    'block_index' => $block,
                    'stage_index' => $stageIndex + 1,
                    'planned_repetitions' => (string) $piece['reps'],
                    'planned_load' => workoutPrescriptionLoadText($config['load'] ?? null),
                    'planned_duration_s' => null,
                    'planned_distance_m' => null,
                    'rep_target' => ['mode' => 'fixed', 'value' => (int) $piece['reps']],
                    'rest_after_s' => $stageIndex + 1 < count($config['clusters']) ? ($config['intra_cluster_rest_s'] ?? 0) : ($config['between_blocks_rest_s'] ?? null),
                ];
            }
        }
        return $sets;
    }
    $number = 0;
    foreach (range(1, (int) $config['rounds']) as $round) {
        foreach ($config['stages'] as $stageIndex => $stage) {
            $number++;
            $sets[] = [
                'segment_type' => 'drop_stage',
                'block_index' => $round,
                'stage_index' => $stageIndex + 1,
                'planned_repetitions' => workoutPrescriptionRepText($stage['reps'] ?? []),
                'planned_load' => workoutPrescriptionLoadText($stage['load'] ?? null),
                'planned_duration_s' => null,
                'planned_distance_m' => null,
                'rep_target' => $stage['reps'] ?? null,
                'rest_after_s' => $stageIndex + 1 < count($config['stages']) ? ($stage['rest_after_s'] ?? 0) : ($config['round_rest_s'] ?? null),
            ];
        }
    }
    return $sets;
}

function workoutPrescriptionGroupFromRow(array $row): ?array
{
    $key = trim((string) ($row['grupo_chave'] ?? $row['idgrupo_prescricao'] ?? ''));
    $type = trim((string) ($row['grupo_tipo'] ?? ''));
    if ($key === '' || $type === '') return null;
    if (!in_array($type, ['superset','circuit'], true)) throw new InvalidArgumentException('Tipo de grupo inválido.');
    return [
        'key' => $key,
        'type' => $type,
        'rounds' => workoutPrescriptionInt($row['grupo_voltas'] ?? 1, 1, 99, 'Voltas do grupo', false),
        'rest_between_exercises_s' => workoutPrescriptionInt($row['grupo_descanso_entre_exercicios_s'] ?? null, 0, 86400, 'Descanso entre exercícios', true),
        'rest_after_round_s' => workoutPrescriptionInt($row['grupo_descanso_pos_volta_s'] ?? null, 0, 86400, 'Descanso após volta', true),
    ];
}

function workoutPrescriptionHydrateGroups(PDO $pdo, array $rows): array
{
    $ids = [];
    foreach ($rows as $row) {
        $id = trim((string) ($row['idgrupo_prescricao'] ?? ''));
        if ($id !== '') $ids[$id] = $id;
    }
    if ($ids === []) return $rows;
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM grupos_prescricao WHERE idgrupo IN ({$marks})");
    $stmt->execute(array_values($ids));
    $groups = [];
    foreach ($stmt->fetchAll() as $group) $groups[(string) $group['idgrupo']] = $group;
    foreach ($rows as &$row) {
        $id = trim((string) ($row['idgrupo_prescricao'] ?? ''));
        if ($id === '' || !isset($groups[$id])) continue;
        $group = $groups[$id];
        $row['grupo_chave'] = $id;
        $row['grupo_tipo'] = (string) $group['tipo'];
        $row['grupo_voltas'] = (int) $group['voltas'];
        $row['grupo_descanso_entre_exercicios_s'] = $group['descanso_entre_exercicios_s'] !== null ? (int) $group['descanso_entre_exercicios_s'] : null;
        $row['grupo_descanso_pos_volta_s'] = $group['descanso_pos_volta_s'] !== null ? (int) $group['descanso_pos_volta_s'] : null;
    }
    unset($row);
    return $rows;
}

function workoutPrescriptionReplaceGroupsBatch(PDO $pdo, string $parentType, array &$rowsByParent): void
{
    $column = match ($parentType) {
        'workout' => 'idtreino',
        'model' => 'idtreino_modelo',
        'scheduled' => 'idagendamento',
        'session' => 'idsessao',
        default => throw new InvalidArgumentException('Parent de grupo inválido.'),
    };
    $parents = [];
    $definitions = [];
    foreach ($rowsByParent as $parentId => $rows) {
        $parentId = trim((string) $parentId);
        if ($parentId === '') continue;
        $parents[$parentId] = $parentId;
        foreach ((array) $rows as $row) {
            if (!is_array($row)) continue;
            $group = workoutPrescriptionGroupFromRow($row);
            if (!$group) continue;
            $key = $group['key'];
            if (isset($definitions[$parentId][$key]) && $definitions[$parentId][$key] !== $group) throw new InvalidArgumentException('Os exercícios do grupo precisam usar a mesma configuração.');
            $definitions[$parentId][$key] = $group;
        }
    }
    if ($parents === []) return;
    $parentIds = array_values($parents);
    $marks = implode(',', array_fill(0, count($parentIds), '?'));
    $pdo->prepare("DELETE FROM grupos_prescricao WHERE {$column} IN ({$marks})")->execute($parentIds);
    $maps = [];
    $insertRows = [];
    $params = [];
    foreach ($definitions as $parentId => $groups) {
        $order = 1;
        foreach ($groups as $key => $group) {
            $id = function_exists('stridebr_generate_id') ? stridebr_generate_id() : bin2hex(random_bytes(10));
            $maps[$parentId][$key] = $id;
            $insertRows[] = '(?,?,?,?,?,?,?)';
            array_push($params, $id, $parentId, $group['type'], $group['rounds'], $group['rest_between_exercises_s'], $group['rest_after_round_s'], $order++);
        }
    }
    if ($insertRows !== []) {
        $pdo->prepare("INSERT INTO grupos_prescricao (idgrupo,{$column},tipo,voltas,descanso_entre_exercicios_s,descanso_pos_volta_s,ordem) VALUES " . implode(',', $insertRows))->execute($params);
    }
    foreach ($rowsByParent as $parentId => &$rows) {
        $parentId = trim((string) $parentId);
        foreach ($rows as &$row) {
            if (!is_array($row)) continue;
            $key = trim((string) ($row['grupo_chave'] ?? $row['idgrupo_prescricao'] ?? ''));
            $row['idgrupo_prescricao'] = $key !== '' && isset($maps[$parentId][$key]) ? $maps[$parentId][$key] : null;
        }
        unset($row);
    }
    unset($rows);
}

function workoutPrescriptionReplaceGroups(PDO $pdo, string $parentType, string $parentId, array &$rows): void
{
    $batch = [];
    $batch[$parentId] =& $rows;
    workoutPrescriptionReplaceGroupsBatch($pdo, $parentType, $batch);
}

function workoutPrescriptionCloneGroups(PDO $pdo, string $sourceParentType, string $sourceParentId, string $targetParentType, string $targetParentId): array
{
    $sourceColumn = match ($sourceParentType) {
        'workout' => 'idtreino',
        'model' => 'idtreino_modelo',
        'scheduled' => 'idagendamento',
        'session' => 'idsessao',
        default => throw new InvalidArgumentException('Parent de grupo inválido.'),
    };
    $targetColumn = match ($targetParentType) {
        'workout' => 'idtreino',
        'model' => 'idtreino_modelo',
        'scheduled' => 'idagendamento',
        'session' => 'idsessao',
        default => throw new InvalidArgumentException('Parent de grupo inválido.'),
    };
    $stmt = $pdo->prepare("SELECT * FROM grupos_prescricao WHERE {$sourceColumn}=:id ORDER BY ordem");
    $stmt->execute([':id' => $sourceParentId]);
    $insert = $pdo->prepare("INSERT INTO grupos_prescricao (idgrupo,{$targetColumn},tipo,voltas,descanso_entre_exercicios_s,descanso_pos_volta_s,ordem) VALUES (:id,:parent,:type,:rounds,:between,:after,:order)");
    $map = [];
    foreach ($stmt->fetchAll() as $group) {
        $newId = function_exists('stridebr_generate_id') ? stridebr_generate_id() : bin2hex(random_bytes(10));
        $insert->execute([
            ':id' => $newId,
            ':parent' => $targetParentId,
            ':type' => $group['tipo'],
            ':rounds' => $group['voltas'],
            ':between' => $group['descanso_entre_exercicios_s'],
            ':after' => $group['descanso_pos_volta_s'],
            ':order' => $group['ordem'],
        ]);
        $map[(string) $group['idgrupo']] = $newId;
    }
    return $map;
}

function workoutPrescriptionDefaultDistanceUnit(string $sportSlug, string $trackingMode = '', string $stepType = ''): string
{
    $sportSlug = function_exists('stridebr_lower') ? stridebr_lower(trim($sportSlug)) : strtolower(trim($sportSlug));
    $meters = [
        'natacao','natacao-em-piscina','natacao-aguas-abertas',
        'salto-em-distancia','salto-triplo','salto-em-altura','salto-com-vara',
        'arremesso-de-peso','lancamento-de-disco','lancamento-de-dardo','lancamento-de-martelo',
    ];
    if (in_array($sportSlug, $meters, true)) return 'm';
    if ($stepType === 'interval_group' || $trackingMode === 'distance') return 'm';
    return 'km';
}

function workoutPrescriptionDistanceMeters(mixed $value, string $unit): ?float
{
    if ($value === null || trim((string) $value) === '') return null;
    $number = workoutPrescriptionFloat($value, 0, 999999999.999, 'Distância', false);
    if (!in_array($unit, ['m','km'], true)) throw new InvalidArgumentException('Unidade de distância inválida.');
    return $unit === 'km' ? $number * 1000 : $number;
}
