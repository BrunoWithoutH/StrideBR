<?php

declare(strict_types=1);

require_once __DIR__ . '/sport_hub.php';

function stridebr_api_sports_catalog(PDO $pdo, string $userId, array $filters = []): array
{
    $query = trim((string) ($filters['q'] ?? ''));
    $familyFilter = stridebr_api_lower(trim((string) ($filters['family'] ?? '')));
    $params = [':user' => $userId];
    $where = 'ativo = TRUE AND (idusuario IS NULL OR idusuario = :user)';
    if ($query !== '') {
        $where .= ' AND (nome ILIKE :query OR slug ILIKE :query)';
        $params[':query'] = '%' . $query . '%';
    }
    $stmt = $pdo->prepare("SELECT idmodalidade,nome,slug,categoria,familia_hub,permite_rota,idusuario,ordem_catalogo
        FROM modalidades
        WHERE {$where}
        ORDER BY COALESCE(ordem_catalogo, 2147483647), CASE WHEN idusuario IS NULL THEN 0 ELSE 1 END, lower(nome), lower(slug), idmodalidade");
    $stmt->execute($params);
    $data = [];
    foreach ($stmt->fetchAll() as $row) {
        $family = sportHubBucket((string) ($row['categoria'] ?? ''), (string) ($row['slug'] ?? ''), (string) ($row['familia_hub'] ?? ''));
        if ($familyFilter !== '' && $family !== $familyFilter) continue;
        $data[] = [
            'id' => (string) $row['idmodalidade'],
            'slug' => (string) $row['slug'],
            'name' => (string) $row['nome'],
            'family' => $family,
            'route_capable' => stridebr_db_bool($row['permite_rota'] ?? false),
        ];
    }
    return $data;
}
