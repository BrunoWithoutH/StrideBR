<?php

declare(strict_types=1);

require_once __DIR__ . '/cronograma.php';

function compartilhamentoCronogramaSnapshot(PDO $pdo, string $idUsuario, string $idCronograma): array
{
    $cronograma = cronogramaBuscar($pdo, $idCronograma, $idUsuario);
    if ($cronograma === []) throw new RuntimeException('Cronograma não encontrado.');
    $treinos = cronogramaListarTreinos($pdo, $idCronograma, $idUsuario);
    foreach ($treinos as &$treino) {
        $treino['exercicios'] = cronogramaListarTreinoExercicios($pdo, (string) $treino['idtreino'], $idUsuario);
    }
    unset($treino);
    return [
        'format' => 'stridebr-schedule',
        'version' => 1,
        'cronograma' => ['nome' => $cronograma['nome'], 'descricao' => $cronograma['descricao'] ?? null],
        'treinos' => $treinos,
    ];
}

function compartilhamentoCronogramaNomeLivre(PDO $pdo, string $idUsuario, string $base): string
{
    $base = trim($base) !== '' ? trim($base) : 'Cronograma compartilhado';
    $candidate = $base;
    for ($n = 2; ; $n++) {
        $stmt = $pdo->prepare('SELECT 1 FROM cronogramas WHERE idusuario = :usuario AND lower(nome) = lower(:nome) LIMIT 1');
        $stmt->execute([':usuario' => $idUsuario, ':nome' => $candidate]);
        if (!$stmt->fetchColumn()) return $candidate;
        $candidate = $base . ' (' . $n . ')';
    }
}

function compartilhamentoImportarSnapshot(PDO $pdo, string $idUsuario, array $data): string
{
    if (($data['format'] ?? '') !== 'stridebr-schedule' || (int) ($data['version'] ?? 0) !== 1 || !is_array($data['cronograma'] ?? null) || !is_array($data['treinos'] ?? null)) {
        throw new InvalidArgumentException('Cronograma compartilhado inválido.');
    }
    $nome = compartilhamentoCronogramaNomeLivre($pdo, $idUsuario, (string) ($data['cronograma']['nome'] ?? 'Cronograma compartilhado'));
    $idCronograma = cronogramaCriar($pdo, $idUsuario, $nome, $data['cronograma']['descricao'] ?? null);
    try {
        foreach ($data['treinos'] as $treino) {
            if (!is_array($treino)) continue;
            $payload = [
                'idcronograma' => $idCronograma,
                'titulo' => (string) ($treino['titulo'] ?? ''),
                'descricao' => $treino['descricao'] ?? null,
                'dia_semana' => $treino['dia_semana'] ?? 1,
                'hora_inicio' => substr((string) ($treino['hora_inicio'] ?? '18:00'), 0, 5),
                'hora_fim' => substr((string) ($treino['hora_fim'] ?? '19:00'), 0, 5),
            ];
            if (stridebr_db_bool($treino['termina_dia_seguinte'] ?? false)) $payload['termina_dia_seguinte'] = '1';
            $idTreino = cronogramaSalvarTreino($pdo, $idUsuario, $payload);
            $rows = [];
            foreach (($treino['exercicios'] ?? []) as $exercicio) {
                if (!is_array($exercicio)) continue;
                $rows[] = [
                    'idexercicio' => (string) ($exercicio['idexercicio'] ?? ''),
                    'nome' => (string) ($exercicio['nome_snapshot'] ?? $exercicio['nome'] ?? ''),
                    'series' => $exercicio['series'] ?? '',
                    'repeticoes' => $exercicio['repeticoes'] ?? '',
                    'carga' => $exercicio['carga'] ?? '',
                    'bloco' => $exercicio['bloco'] ?? '',
                    'cluster' => $exercicio['cluster'] ?? '',
                    'descanso' => $exercicio['descanso'] ?? '',
                    'observacoes' => $exercicio['observacoes'] ?? '',
                ];
            }
            if ($rows !== []) cronogramaSalvarExercicios($pdo, $idTreino, $idUsuario, $rows, []);
        }
        return $idCronograma;
    } catch (Throwable $e) {
        cronogramaExcluir($pdo, $idCronograma, $idUsuario);
        throw $e;
    }
}
