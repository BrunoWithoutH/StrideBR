<?php

declare(strict_types=1);

function stridebr_db_schema_bool(mixed $value): bool
{
    if (is_bool($value)) return $value;
    if (is_int($value)) return $value !== 0;
    return in_array(strtolower(trim((string) $value)), ['1', 't', 'true', 'y', 'yes', 'on'], true);
}

function stridebr_db_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    if (preg_match('/^[a-z_][a-z0-9_]*$/i', $table) !== 1) return $cache[$table] = false;
    try {
        $stmt = $pdo->prepare("SELECT to_regclass(:table) IS NOT NULL");
        $stmt->execute([':table' => 'stridebr.' . $table]);
        return $cache[$table] = stridebr_db_schema_bool($stmt->fetchColumn());
    } catch (Throwable) {
        return $cache[$table] = false;
    }
}

function stridebr_db_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    if (preg_match('/^[a-z_][a-z0-9_]*$/i', $table) !== 1 || preg_match('/^[a-z_][a-z0-9_]*$/i', $column) !== 1) return $cache[$key] = false;
    try {
        $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'stridebr' AND table_name = :table AND column_name = :column)");
        $stmt->execute([':table' => $table, ':column' => $column]);
        return $cache[$key] = stridebr_db_schema_bool($stmt->fetchColumn());
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}
