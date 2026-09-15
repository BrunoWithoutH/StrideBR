<?php

declare(strict_types=1);


function stridebr_marketing_schema_available(PDO $pdo): bool
{
    static $cache = null;
    if (is_bool($cache)) return $cache;
    try {
        $cache = (bool) $pdo->query("SELECT to_regclass('stridebr.marketing_campanhas') IS NOT NULL AND to_regclass('stridebr.marketing_eventos_aquisicao') IS NOT NULL")->fetchColumn();
    } catch (Throwable) {
        $cache = false;
    }
    return $cache;
}

function stridebr_marketing_clean_text(mixed $value, int $limit): ?string
{
    $text = trim((string) $value);
    if ($text === '') return null;
    $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '';
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    if (function_exists('mb_substr')) return mb_substr($text, 0, $limit, 'UTF-8');
    return substr($text, 0, $limit);
}

function stridebr_marketing_attribution_for_user(PDO $pdo, string $userId): ?array
{
    if (!stridebr_marketing_schema_available($pdo) || trim($userId) === '') return null;
    $stmt = $pdo->prepare('SELECT * FROM marketing_atribuicoes WHERE idusuario = :usuario LIMIT 1');
    $stmt->execute([':usuario' => $userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function stridebr_marketing_activation(PDO $pdo, string $userId, string $path = '/user/atividades.php'): void
{
    try {
        if (!stridebr_marketing_schema_available($pdo) || trim($userId) === '') return;
        $attribution = stridebr_marketing_attribution_for_user($pdo, $userId);
        $cleanPath = stridebr_marketing_clean_text($path, 300);
        $stmt = $pdo->prepare(
            "INSERT INTO marketing_eventos_aquisicao (chave_evento, chave_atribuicao, idusuario, nome, caminho)
             VALUES (:event_key, :attribution, :usuario, 'activation', :caminho)
             ON CONFLICT (chave_evento) DO NOTHING"
        );
        $stmt->execute([
            ':event_key' => hash('sha256', 'user|' . $userId . '|activation'),
            ':attribution' => $attribution['chave_hash'] ?? null,
            ':usuario' => $userId,
            ':caminho' => $cleanPath,
        ]);
    } catch (Throwable $e) {
        error_log('StrideBR acquisition activation failed: ' . get_class($e));
    }
}
