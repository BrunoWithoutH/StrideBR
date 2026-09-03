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
    if (count($data['treinos']) > 100) {
        throw new InvalidArgumentException('O cronograma compartilhado excede o limite de 100 treinos.');
    }
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $nome = compartilhamentoCronogramaNomeLivre($pdo, $idUsuario, (string) ($data['cronograma']['nome'] ?? 'Cronograma compartilhado'));
        $idCronograma = cronogramaCriar($pdo, $idUsuario, $nome, $data['cronograma']['descricao'] ?? null);
        foreach ($data['treinos'] as $treino) {
            if (!is_array($treino)) {
                throw new InvalidArgumentException('Um dos treinos compartilhados é inválido.');
            }
            $payload = [
                'idcronograma' => $idCronograma,
                'titulo' => (string) ($treino['titulo'] ?? ''),
                'codigo' => (string) ($treino['codigo'] ?? ''),
                'foco' => (string) ($treino['foco'] ?? ''),
                'descricao' => $treino['descricao'] ?? null,
                'dia_semana' => $treino['dia_semana'] ?? 1,
                'hora_inicio' => substr((string) ($treino['hora_inicio'] ?? '18:00'), 0, 5),
                'hora_fim' => substr((string) ($treino['hora_fim'] ?? '19:00'), 0, 5),
            ];
            if (stridebr_db_bool($treino['termina_dia_seguinte'] ?? false)) $payload['termina_dia_seguinte'] = '1';
            $idTreino = cronogramaSalvarTreino($pdo, $idUsuario, $payload);
            $rows = [];
            $exercicios = $treino['exercicios'] ?? [];
            if (!is_array($exercicios) || count($exercicios) > 200) {
                throw new InvalidArgumentException('Um treino compartilhado possui exercícios inválidos ou excede o limite de 200 itens.');
            }
            foreach ($exercicios as $exercicio) {
                if (!is_array($exercicio)) {
                    throw new InvalidArgumentException('Um dos exercícios compartilhados é inválido.');
                }
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
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return $idCronograma;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function compartilhamentoUsuariosSaoAmigos(PDO $pdo, string $idUsuario, string $idDestino): bool
{
    if ($idDestino === '' || $idUsuario === $idDestino) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM amizades WHERE status = 'aceita' AND ((idusuario_solicitante = :me1 AND idusuario_destino = :dest1) OR (idusuario_solicitante = :dest2 AND idusuario_destino = :me2)) LIMIT 1");
    $stmt->execute([':me1' => $idUsuario, ':dest1' => $idDestino, ':dest2' => $idDestino, ':me2' => $idUsuario]);
    return (bool) $stmt->fetchColumn();
}

function compartilhamentoEnviarSnapshot(PDO $pdo, string $idUsuario, string $idCronograma, string $idDestino): string
{
    if (!compartilhamentoUsuariosSaoAmigos($pdo, $idUsuario, $idDestino)) {
        throw new RuntimeException('Esse usuário não está na sua lista de amigos.');
    }

    $snapshot = compartilhamentoCronogramaSnapshot($pdo, $idUsuario, $idCronograma);
    $idCompartilhamento = stridebr_generate_id();
    $stmt = $pdo->prepare("INSERT INTO cronograma_compartilhamentos (idcompartilhamento, idcronograma_origem, idusuario_origem, idusuario_destino, tipo, snapshot) VALUES (:id, :cronograma, :origem, :destino, 'snapshot', CAST(:snapshot AS jsonb))");
    try {
        $stmt->execute([
            ':id' => $idCompartilhamento,
            ':cronograma' => $idCronograma,
            ':origem' => $idUsuario,
            ':destino' => $idDestino,
            ':snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23505') {
            throw new RuntimeException('Esse cronograma já está aguardando resposta desse amigo.');
        }
        throw $e;
    }
    return $idCompartilhamento;
}

function compartilhamentoAceitarSnapshot(PDO $pdo, string $idUsuario, string $idCompartilhamento): string
{
    if ($idCompartilhamento === '') throw new InvalidArgumentException('Compartilhamento inválido.');
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("SELECT snapshot FROM cronograma_compartilhamentos WHERE idcompartilhamento = :id AND idusuario_destino = :usuario AND tipo = 'snapshot' AND status = 'pendente' FOR UPDATE");
        $stmt->execute([':id' => $idCompartilhamento, ':usuario' => $idUsuario]);
        $raw = $stmt->fetchColumn();
        if ($raw === false) throw new RuntimeException('Compartilhamento não encontrado.');
        $snapshot = is_array($raw) ? $raw : json_decode((string) $raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($snapshot)) throw new RuntimeException('Compartilhamento inválido.');

        $idNovo = compartilhamentoImportarSnapshot($pdo, $idUsuario, $snapshot);
        $update = $pdo->prepare("UPDATE cronograma_compartilhamentos SET status = 'aceito', data_atualizacao = NOW() WHERE idcompartilhamento = :id AND idusuario_destino = :usuario AND status = 'pendente'");
        $update->execute([':id' => $idCompartilhamento, ':usuario' => $idUsuario]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Compartilhamento não encontrado.');

        if ($ownsTransaction) $pdo->commit();
        return $idNovo;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function compartilhamentoRecusarSnapshot(PDO $pdo, string $idUsuario, string $idCompartilhamento): void
{
    if ($idCompartilhamento === '') throw new InvalidArgumentException('Compartilhamento inválido.');
    $stmt = $pdo->prepare("UPDATE cronograma_compartilhamentos SET status = 'recusado', data_atualizacao = NOW() WHERE idcompartilhamento = :id AND idusuario_destino = :usuario AND tipo = 'snapshot' AND status = 'pendente'");
    $stmt->execute([':id' => $idCompartilhamento, ':usuario' => $idUsuario]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Compartilhamento não encontrado.');
}

function compartilhamentoEnviarSincronizado(PDO $pdo, string $idUsuario, string $idCronograma, string $idDestino): string
{
    if (!compartilhamentoUsuariosSaoAmigos($pdo, $idUsuario, $idDestino)) throw new RuntimeException('Esse usuário não está na sua lista de amigos.');
    if (cronogramaBuscar($pdo, $idCronograma, $idUsuario) === []) throw new RuntimeException('Cronograma não encontrado.');
    $accepted = $pdo->prepare("SELECT 1 FROM cronograma_compartilhamentos WHERE idcronograma_origem = :cronograma AND idusuario_origem = :origem AND idusuario_destino = :destino AND tipo = 'sincronizado' AND status = 'aceito' LIMIT 1");
    $accepted->execute([':cronograma'=>$idCronograma, ':origem'=>$idUsuario, ':destino'=>$idDestino]);
    if ($accepted->fetchColumn()) throw new RuntimeException('Esse amigo já acompanha este cronograma.');
    $id = stridebr_generate_id();
    $stmt = $pdo->prepare("INSERT INTO cronograma_compartilhamentos (idcompartilhamento,idcronograma_origem,idusuario_origem,idusuario_destino,tipo,snapshot) VALUES (:id,:cronograma,:origem,:destino,'sincronizado',NULL)");
    try {
        $stmt->execute([':id'=>$id, ':cronograma'=>$idCronograma, ':origem'=>$idUsuario, ':destino'=>$idDestino]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23505') throw new RuntimeException('Já existe um convite pendente para este cronograma.');
        throw $e;
    }
    return $id;
}

function compartilhamentoAceitarSincronizado(PDO $pdo, string $idUsuario, string $idCompartilhamento): string
{
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT cs.idcronograma_origem, cs.idusuario_origem FROM cronograma_compartilhamentos cs JOIN cronogramas c ON c.idcronograma = cs.idcronograma_origem AND c.idusuario = cs.idusuario_origem WHERE cs.idcompartilhamento = :id AND cs.idusuario_destino = :usuario AND cs.tipo = 'sincronizado' AND cs.status = 'pendente' FOR UPDATE OF cs");
        $stmt->execute([':id'=>$idCompartilhamento, ':usuario'=>$idUsuario]);
        $share = $stmt->fetch();
        if (!$share) throw new RuntimeException('Compartilhamento sincronizado não encontrado.');
        $member = $pdo->prepare("INSERT INTO cronograma_membros (idcronograma,idusuario,papel) VALUES (:cronograma,:usuario,'viewer') ON CONFLICT (idcronograma,idusuario) DO UPDATE SET papel = CASE WHEN cronograma_membros.papel = 'owner' THEN 'owner' ELSE 'viewer' END");
        $member->execute([':cronograma'=>$share['idcronograma_origem'], ':usuario'=>$idUsuario]);
        $update = $pdo->prepare("UPDATE cronograma_compartilhamentos SET status='aceito',data_atualizacao=NOW() WHERE idcompartilhamento=:id AND status='pendente'");
        $update->execute([':id'=>$idCompartilhamento]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Compartilhamento sincronizado não encontrado.');
        if ($owns) $pdo->commit();
        return (string) $share['idcronograma_origem'];
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function compartilhamentoRecusarSincronizado(PDO $pdo, string $idUsuario, string $idCompartilhamento): void
{
    $stmt = $pdo->prepare("UPDATE cronograma_compartilhamentos SET status='recusado',data_atualizacao=NOW() WHERE idcompartilhamento=:id AND idusuario_destino=:usuario AND tipo='sincronizado' AND status='pendente'");
    $stmt->execute([':id'=>$idCompartilhamento, ':usuario'=>$idUsuario]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Compartilhamento sincronizado não encontrado.');
}

function compartilhamentoRevogarSincronizado(PDO $pdo, string $idUsuario, string $idCompartilhamento): void
{
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT idcronograma_origem,idusuario_origem,idusuario_destino,status FROM cronograma_compartilhamentos WHERE idcompartilhamento=:id AND tipo='sincronizado' AND (idusuario_origem=:usuario OR idusuario_destino=:usuario) AND status IN ('pendente','aceito') FOR UPDATE");
        $stmt->execute([':id'=>$idCompartilhamento, ':usuario'=>$idUsuario]);
        $share = $stmt->fetch();
        if (!$share) throw new RuntimeException('Compartilhamento sincronizado não encontrado.');
        $pdo->prepare("UPDATE cronograma_compartilhamentos SET status='revogado',data_atualizacao=NOW() WHERE idcompartilhamento=:id")->execute([':id'=>$idCompartilhamento]);
        if ((string)$share['status'] === 'aceito' && !empty($share['idcronograma_origem'])) {
            $pdo->prepare("DELETE FROM cronograma_membros WHERE idcronograma=:cronograma AND idusuario=:destino AND papel='viewer'")->execute([':cronograma'=>$share['idcronograma_origem'], ':destino'=>$share['idusuario_destino']]);
        }
        if ($owns) $pdo->commit();
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function compartilhamentoSincronizados(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare("SELECT cs.*, c.nome AS cronograma_nome,
        COALESCE(NULLIF(origem.nome_exibicao,''),origem.nomeusuario) AS origem_nome, origem.username AS origem_username,
        COALESCE(NULLIF(destino.nome_exibicao,''),destino.nomeusuario) AS destino_nome, destino.username AS destino_username
        FROM cronograma_compartilhamentos cs
        JOIN cronogramas c ON c.idcronograma=cs.idcronograma_origem
        JOIN usuarios origem ON origem.idusuario=cs.idusuario_origem
        JOIN usuarios destino ON destino.idusuario=cs.idusuario_destino
        WHERE cs.tipo='sincronizado' AND (cs.idusuario_origem=:usuario OR cs.idusuario_destino=:usuario) AND cs.status IN ('pendente','aceito')
        ORDER BY cs.status='pendente' DESC, cs.data_atualizacao DESC");
    $stmt->execute([':usuario'=>$idUsuario]);
    return $stmt->fetchAll();
}

function compartilhamentoCronogramaLeitura(PDO $pdo, string $idUsuario, string $idCronograma): array
{
    $stmt = $pdo->prepare("SELECT c.*, cm.papel, COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario) AS dono_nome, u.username AS dono_username
        FROM cronogramas c JOIN cronograma_membros cm ON cm.idcronograma=c.idcronograma AND cm.idusuario=:usuario
        JOIN usuarios u ON u.idusuario=c.idusuario
        WHERE c.idcronograma=:cronograma AND c.ativo=TRUE LIMIT 1");
    $stmt->execute([':usuario'=>$idUsuario, ':cronograma'=>$idCronograma]);
    return $stmt->fetch() ?: [];
}

function compartilhamentoTreinosLeitura(PDO $pdo, string $idUsuario, string $idCronograma): array
{
    if (compartilhamentoCronogramaLeitura($pdo,$idUsuario,$idCronograma) === []) return [];
    $stmt = $pdo->prepare("SELECT t.* FROM treinos_cronograma t WHERE t.idcronograma=:cronograma ORDER BY t.dia_semana,t.hora_inicio,t.ordem");
    $stmt->execute([':cronograma'=>$idCronograma]);
    $rows = $stmt->fetchAll();
    if (!$rows) return [];
    $ids = array_column($rows,'idtreino');
    $params=[];$ph=[];
    foreach($ids as $i=>$id){$k=':t'.$i;$ph[]=$k;$params[$k]=$id;}
    $ex = $pdo->prepare("SELECT te.idtreino,te.ordem,COALESCE(NULLIF(te.nome_snapshot,''),e.nome,'Exercício') AS nome,te.series,te.repeticoes,te.carga,te.descanso FROM treinos_exercicios te LEFT JOIN exercicios e ON e.idexercicio=te.idexercicio WHERE te.idtreino IN (".implode(',',$ph).") ORDER BY te.idtreino,te.ordem");
    $ex->execute($params);$by=[];foreach($ex->fetchAll() as $r)$by[(string)$r['idtreino']][]=$r;
    foreach($rows as &$row)$row['exercicios']=$by[(string)$row['idtreino']]??[];unset($row);
    return $rows;
}
