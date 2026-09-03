<?php

declare(strict_types=1);

function productAnalyticsPreferencias(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare('SELECT preferenciasusuario FROM usuarios WHERE idusuario = :usuario LIMIT 1');
    $stmt->execute([':usuario' => $idUsuario]);
    $raw = $stmt->fetchColumn();
    return is_array($raw) ? $raw : (json_decode((string) ($raw ?: '{}'), true) ?: []);
}

function productAnalyticsPermitido(PDO $pdo, string $idUsuario): bool
{
    if (!stridebr_feature_enabled($pdo, 'product_analytics.enabled', true)) return false;
    $prefs = productAnalyticsPreferencias($pdo, $idUsuario);
    return !isset($prefs['product_analytics']) || stridebr_db_bool($prefs['product_analytics']);
}

function productAnalyticsRegistrar(PDO $pdo, string $idUsuario, string $nome, array $dados = [], ?int $duracaoMs = null, ?string $contexto = null): void
{
    if (!productAnalyticsPermitido($pdo, $idUsuario)) return;
    $allowedNames = [
        'onboarding_started', 'onboarding_completed', 'activity_started', 'activity_saved',
        'activity_compared', 'schedule_created', 'schedule_shared', 'workout_started',
        'workout_completed', 'goal_created', 'dashboard_customized', 'flow_abandoned'
    ];
    if (!in_array($nome, $allowedNames, true)) return;
    $safe = [];
    foreach ($dados as $key => $value) {
        if (!is_string($key) || preg_match('/^[a-z0-9_]{1,40}$/', $key) !== 1) continue;
        if (is_bool($value) || is_int($value) || is_float($value) || (is_string($value) && strlen($value) <= 80)) $safe[$key] = $value;
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO eventos_produto (idusuario, nome, contexto, duracao_ms, dados) VALUES (:usuario, :nome, :contexto, :duracao, CAST(:dados AS jsonb))');
        $stmt->execute([
            ':usuario' => $idUsuario,
            ':nome' => $nome,
            ':contexto' => $contexto !== null ? substr($contexto, 0, 80) : null,
            ':duracao' => $duracaoMs !== null ? max(0, min(86400000, $duracaoMs)) : null,
            ':dados' => json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    } catch (PDOException $e) {
        if (!in_array($e->getCode(), ['42P01', '42703'], true)) throw $e;
    }
}
