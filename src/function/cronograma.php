<?php

declare(strict_types=1);

use Hidehalo\Nanoid\Client;

function cronogramaGerarId(int $length = 21): string
{
    static $client = null;
    $client ??= new Client();
    return $client->generateId($length);
}

function cronogramaListar(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare('SELECT * FROM cronogramas WHERE idusuario = :usuario AND ativo = TRUE ORDER BY data_atualizacao DESC, nome');
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function cronogramaBuscar(PDO $pdo, string $idCronograma, string $idUsuario): array
{
    $stmt = $pdo->prepare('SELECT * FROM cronogramas WHERE idcronograma = :id AND idusuario = :usuario LIMIT 1');
    $stmt->execute([':id' => $idCronograma, ':usuario' => $idUsuario]);
    return $stmt->fetch() ?: [];
}

function cronogramaCriar(PDO $pdo, string $idUsuario, string $nome, ?string $descricao = null): string
{
    $nome = trim($nome);
    if ($nome === '' || stridebr_length($nome) > 120) {
        throw new InvalidArgumentException('Informe um nome válido para o cronograma.');
    }

    $stmt = $pdo->prepare('SELECT 1 FROM cronogramas WHERE idusuario = :usuario AND lower(nome) = lower(:nome) LIMIT 1');
    $stmt->execute([':usuario' => $idUsuario, ':nome' => $nome]);
    if ($stmt->fetchColumn()) {
        throw new InvalidArgumentException('Você já possui um cronograma com esse nome.');
    }

    $id = cronogramaGerarId();
    $stmt = $pdo->prepare('INSERT INTO cronogramas (idcronograma, idusuario, nome, descricao) VALUES (:id, :usuario, :nome, :descricao)');
    $stmt->execute([
        ':id' => $id,
        ':usuario' => $idUsuario,
        ':nome' => $nome,
        ':descricao' => $descricao !== null && trim($descricao) !== '' ? trim($descricao) : null,
    ]);
    $member = $pdo->prepare("INSERT INTO cronograma_membros (idcronograma, idusuario, papel) VALUES (:cronograma, :usuario, 'owner') ON CONFLICT (idcronograma, idusuario) DO NOTHING");
    $member->execute([':cronograma' => $id, ':usuario' => $idUsuario]);
    return $id;
}

function cronogramaExcluir(PDO $pdo, string $idCronograma, string $idUsuario): bool
{
    $stmt = $pdo->prepare('DELETE FROM cronogramas WHERE idcronograma = :id AND idusuario = :usuario');
    $stmt->execute([':id' => $idCronograma, ':usuario' => $idUsuario]);
    return $stmt->rowCount() === 1;
}

function cronogramaListarTreinos(PDO $pdo, string $idCronograma, string $idUsuario): array
{
    if (cronogramaBuscar($pdo, $idCronograma, $idUsuario) === []) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM treinos_cronograma WHERE idcronograma = :cronograma ORDER BY dia_semana, hora_inicio, ordem');
    $stmt->execute([':cronograma' => $idCronograma]);
    return $stmt->fetchAll();
}

function cronogramaBuscarTreino(PDO $pdo, string $idTreino, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        'SELECT t.*, c.nome AS cronograma_nome, c.idusuario FROM treinos_cronograma t JOIN cronogramas c ON c.idcronograma = t.idcronograma WHERE t.idtreino = :id AND c.idusuario = :usuario LIMIT 1'
    );
    $stmt->execute([':id' => $idTreino, ':usuario' => $idUsuario]);
    return $stmt->fetch() ?: [];
}

function cronogramaSalvarTreino(PDO $pdo, string $idUsuario, array $payload, ?string $idTreino = null): string
{
    $idCronograma = (string) ($payload['idcronograma'] ?? '');
    if (cronogramaBuscar($pdo, $idCronograma, $idUsuario) === []) {
        throw new RuntimeException('Cronograma não encontrado.');
    }

    $titulo = trim((string) ($payload['titulo'] ?? ''));
    $dia = filter_var($payload['dia_semana'] ?? null, FILTER_VALIDATE_INT);
    $inicio = (string) ($payload['hora_inicio'] ?? '');
    $fim = (string) ($payload['hora_fim'] ?? '');
    $nextDay = !empty($payload['termina_dia_seguinte']);

    if ($titulo === '' || stridebr_length($titulo) > 120) {
        throw new InvalidArgumentException('Informe um título válido para o treino.');
    }
    if ($dia === false || $dia < 0 || $dia > 6) {
        throw new InvalidArgumentException('Dia da semana inválido.');
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $inicio) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $fim)) {
        throw new InvalidArgumentException('Horário inválido.');
    }
    if (!$nextDay && $fim <= $inicio) {
        throw new InvalidArgumentException('O horário final precisa ser maior que o inicial, ou marque que o treino termina no dia seguinte.');
    }
    if ($nextDay && $fim > $inicio) {
        throw new InvalidArgumentException('Se o treino termina no dia seguinte, o horário final deve ser menor ou igual ao inicial.');
    }

    if ($idTreino !== null) {
        $existing = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
        if ($existing === [] || $existing['idcronograma'] !== $idCronograma) {
            throw new RuntimeException('Treino não encontrado.');
        }
        $stmt = $pdo->prepare('UPDATE treinos_cronograma SET titulo = :titulo, descricao = :descricao, dia_semana = :dia, hora_inicio = :inicio, hora_fim = :fim, termina_dia_seguinte = :seguinte, data_atualizacao = NOW() WHERE idtreino = :id');
        $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
        $stmt->bindValue(':descricao', trim((string) ($payload['descricao'] ?? '')) ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':dia', $dia, PDO::PARAM_INT);
        $stmt->bindValue(':inicio', $inicio, PDO::PARAM_STR);
        $stmt->bindValue(':fim', $fim, PDO::PARAM_STR);
        $stmt->bindValue(':seguinte', $nextDay, PDO::PARAM_BOOL);
        $stmt->bindValue(':id', $idTreino, PDO::PARAM_STR);
        $stmt->execute();
        $id = $idTreino;
    } else {
        $id = cronogramaGerarId();
        $stmt = $pdo->prepare('INSERT INTO treinos_cronograma (idtreino, idcronograma, titulo, descricao, dia_semana, hora_inicio, hora_fim, termina_dia_seguinte, ordem) VALUES (:id, :cronograma, :titulo, :descricao, :dia, :inicio, :fim, :seguinte, :ordem)');
        $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 FROM treinos_cronograma WHERE idcronograma = :cronograma AND dia_semana = :dia');
        $orderStmt->execute([':cronograma' => $idCronograma, ':dia' => $dia]);
        $ordem = (int) $orderStmt->fetchColumn();
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
        $stmt->bindValue(':cronograma', $idCronograma, PDO::PARAM_STR);
        $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
        $stmt->bindValue(':descricao', trim((string) ($payload['descricao'] ?? '')) ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':dia', $dia, PDO::PARAM_INT);
        $stmt->bindValue(':inicio', $inicio, PDO::PARAM_STR);
        $stmt->bindValue(':fim', $fim, PDO::PARAM_STR);
        $stmt->bindValue(':seguinte', $nextDay, PDO::PARAM_BOOL);
        $stmt->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $stmt->execute();
    }

    $pdo->prepare('UPDATE cronogramas SET data_atualizacao = NOW() WHERE idcronograma = :id')->execute([':id' => $idCronograma]);
    return $id;
}

function cronogramaExcluirTreino(PDO $pdo, string $idTreino, string $idUsuario): bool
{
    $treino = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
    if ($treino === []) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM treinos_cronograma WHERE idtreino = :id');
    $stmt->execute([':id' => $idTreino]);
    return $stmt->rowCount() === 1;
}

function cronogramaDuracaoMinutos(array $treino): int
{
    $inicio = ((int) substr($treino['hora_inicio'], 0, 2)) * 60 + (int) substr($treino['hora_inicio'], 3, 2);
    $fim = ((int) substr($treino['hora_fim'], 0, 2)) * 60 + (int) substr($treino['hora_fim'], 3, 2);
    if (stridebr_db_bool($treino['termina_dia_seguinte'])) {
        $fim += 1440;
    }
    return max(1, $fim - $inicio);
}

function cronogramaListarExerciciosBiblioteca(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        "SELECT e.idexercicio, e.nome, e.descricao, e.idusuario, e.imagem_url, e.video_url,
                COALESCE(string_agg(DISTINCT c.nome, ', ' ORDER BY c.nome), '') AS categorias,
                COALESCE(string_agg(DISTINCT m.nome, ', ' ORDER BY m.nome), '') AS modalidades
         FROM exercicios e
         LEFT JOIN exercicios_categorias ec ON ec.idexercicio = e.idexercicio
         LEFT JOIN categorias_exercicio c ON c.idcategoria = ec.idcategoria AND c.ativo = TRUE
         LEFT JOIN exercicios_modalidades em ON em.idexercicio = e.idexercicio
         LEFT JOIN modalidades m ON m.idmodalidade = em.idmodalidade AND m.ativo = TRUE
         WHERE e.ativo = TRUE AND (e.idusuario IS NULL OR e.idusuario = :usuario)
         GROUP BY e.idexercicio
         ORDER BY e.idusuario NULLS FIRST, e.nome"
    );
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function cronogramaListarCategorias(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare('SELECT * FROM categorias_exercicio WHERE ativo = TRUE AND (idusuario IS NULL OR idusuario = :usuario) ORDER BY idusuario NULLS FIRST, nome');
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function cronogramaCriarCategoria(PDO $pdo, string $idUsuario, string $nome): string
{
    $nome = trim($nome);
    $slug = stridebr_slug($nome);
    if ($nome === '' || stridebr_length($nome) > 80 || $slug === '') {
        throw new InvalidArgumentException('Informe um nome válido para a categoria.');
    }
    $stmt = $pdo->prepare('SELECT idcategoria, ativo FROM categorias_exercicio WHERE idusuario = :usuario AND lower(slug) = lower(:slug) LIMIT 1');
    $stmt->execute([':usuario' => $idUsuario, ':slug' => $slug]);
    $existing = $stmt->fetch();
    if ($existing) {
        if (!stridebr_db_bool($existing['ativo'])) {
            $pdo->prepare('UPDATE categorias_exercicio SET ativo = TRUE, nome = :nome WHERE idcategoria = :id AND idusuario = :usuario')->execute([
                ':nome' => $nome,
                ':id' => $existing['idcategoria'],
                ':usuario' => $idUsuario,
            ]);
        }
        return (string) $existing['idcategoria'];
    }
    $id = cronogramaGerarId();
    $pdo->prepare('INSERT INTO categorias_exercicio (idcategoria, idusuario, nome, slug) VALUES (:id, :usuario, :nome, :slug)')->execute([
        ':id' => $id,
        ':usuario' => $idUsuario,
        ':nome' => $nome,
        ':slug' => $slug,
    ]);
    return $id;
}

function cronogramaNormalizarUrlMidia(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '') return null;
    if (strlen($url) > 2000 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException('Informe uma URL de mídia válida.');
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('A URL de mídia precisa usar http ou https.');
    }
    return $url;
}

function cronogramaCriarExercicio(PDO $pdo, string $idUsuario, string $nome, ?string $descricao = null, array $categorias = [], ?string $imagemUrl = null, ?string $videoUrl = null): string
{
    $nome = trim($nome);
    $slug = stridebr_slug($nome);
    $imagemUrl = cronogramaNormalizarUrlMidia($imagemUrl);
    $videoUrl = cronogramaNormalizarUrlMidia($videoUrl);
    if ($nome === '' || stridebr_length($nome) > 120 || $slug === '') {
        throw new InvalidArgumentException('Informe um nome válido para o exercício.');
    }
    $stmt = $pdo->prepare('SELECT idexercicio, ativo FROM exercicios WHERE idusuario = :usuario AND lower(slug) = lower(:slug) LIMIT 1');
    $stmt->execute([':usuario' => $idUsuario, ':slug' => $slug]);
    $existing = $stmt->fetch();
    if ($existing) {
        if (!stridebr_db_bool($existing['ativo'])) {
            $pdo->prepare('UPDATE exercicios SET ativo = TRUE, nome = :nome, descricao = :descricao, imagem_url = :imagem, video_url = :video, data_atualizacao = NOW() WHERE idexercicio = :id AND idusuario = :usuario')->execute([
                ':nome' => $nome,
                ':descricao' => $descricao !== null && trim($descricao) !== '' ? trim($descricao) : null,
                ':imagem' => $imagemUrl,
                ':video' => $videoUrl,
                ':id' => $existing['idexercicio'],
                ':usuario' => $idUsuario,
            ]);
        }
        return (string) $existing['idexercicio'];
    }

    $id = cronogramaGerarId();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $pdo->prepare('INSERT INTO exercicios (idexercicio, idusuario, nome, slug, descricao, imagem_url, video_url) VALUES (:id, :usuario, :nome, :slug, :descricao, :imagem, :video)')->execute([
            ':id' => $id,
            ':usuario' => $idUsuario,
            ':nome' => $nome,
            ':slug' => $slug,
            ':descricao' => $descricao !== null && trim($descricao) !== '' ? trim($descricao) : null,
            ':imagem' => $imagemUrl,
            ':video' => $videoUrl,
        ]);
        $catStmt = $pdo->prepare('SELECT idcategoria FROM categorias_exercicio WHERE idcategoria = :categoria AND (idusuario IS NULL OR idusuario = :usuario)');
        $insertCat = $pdo->prepare('INSERT INTO exercicios_categorias (idexercicio, idcategoria) VALUES (:exercicio, :categoria) ON CONFLICT DO NOTHING');
        foreach ($categorias as $categoria) {
            $catStmt->execute([':categoria' => $categoria, ':usuario' => $idUsuario]);
            if ($catStmt->fetchColumn()) {
                $insertCat->execute([':exercicio' => $id, ':categoria' => $categoria]);
            }
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return $id;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function cronogramaListarTreinoExercicios(PDO $pdo, string $idTreino, string $idUsuario): array
{
    if (cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) === []) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM treinos_exercicios WHERE idtreino = :treino ORDER BY ordem');
    $stmt->execute([':treino' => $idTreino]);
    return $stmt->fetchAll();
}

function cronogramaListarCamposExtras(PDO $pdo, string $idTreino, string $idUsuario): array
{
    if (cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) === []) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM campos_treino_exercicio WHERE idtreino = :treino AND ativo = TRUE ORDER BY ordem');
    $stmt->execute([':treino' => $idTreino]);
    return $stmt->fetchAll();
}

function cronogramaCarregarValoresExtras(PDO $pdo, array $exercicios): array
{
    if ($exercicios === []) return [];
    $ids = array_column($exercicios, 'idtreino_exercicio');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM valores_treino_exercicio WHERE idtreino_exercicio IN ({$placeholders})");
    $stmt->execute($ids);
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $value = $row['valor_texto'] ?? $row['valor_inteiro'] ?? $row['valor_decimal'] ?? $row['valor_booleano'];
        $result[$row['idtreino_exercicio']][$row['idcampo']] = $value;
    }
    return $result;
}

function cronogramaSalvarExercicios(PDO $pdo, string $idTreino, string $idUsuario, array $rows, array $camposExtras): void
{
    if (cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) === []) {
        throw new RuntimeException('Treino não encontrado.');
    }

    $existingRows = cronogramaListarTreinoExercicios($pdo, $idTreino, $idUsuario);
    $existing = array_column($existingRows, null, 'idtreino_exercicio');
    $biblioteca = cronogramaListarExerciciosBiblioteca($pdo, $idUsuario);
    $bibliotecaIds = array_column($biblioteca, null, 'idexercicio');
    $extraById = array_column($camposExtras, null, 'idcampo');

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $pdo->prepare('UPDATE treinos_exercicios SET ordem = ordem + 1000000 WHERE idtreino = :treino')->execute([':treino' => $idTreino]);
        $seen = [];
        $order = 1;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $idOccurrence = trim((string) ($row['idtreino_exercicio'] ?? ''));
            $idExercise = trim((string) ($row['idexercicio'] ?? ''));
            $name = trim((string) ($row['nome'] ?? ''));

            if ($idExercise !== '' && !isset($bibliotecaIds[$idExercise])) {
                $idExercise = '';
            }
            if ($name === '' && $idExercise !== '') {
                $name = $bibliotecaIds[$idExercise]['nome'];
            }

            if ($name === '') continue;
            if (stridebr_length($name) > 120) {
                throw new InvalidArgumentException('O nome do exercício é muito longo.');
            }
            $repeticoes = trim((string) ($row['repeticoes'] ?? ''));
            $carga = trim((string) ($row['carga'] ?? ''));
            $bloco = trim((string) ($row['bloco'] ?? ''));
            $cluster = trim((string) ($row['cluster'] ?? ''));
            $descanso = trim((string) ($row['descanso'] ?? ''));
            if (stridebr_length($repeticoes) > 40 || stridebr_length($carga) > 40 || stridebr_length($bloco) > 40 || stridebr_length($cluster) > 80 || stridebr_length($descanso) > 40) {
                throw new InvalidArgumentException('Uma das informações do exercício ultrapassa o limite permitido.');
            }
            $seriesRaw = trim((string) ($row['series'] ?? ''));
            $series = $seriesRaw === '' ? null : filter_var($seriesRaw, FILTER_VALIDATE_INT);
            if ($seriesRaw !== '' && ($series === false || $series <= 0)) {
                throw new InvalidArgumentException('Séries precisa ser um número inteiro positivo.');
            }

            if ($idOccurrence !== '' && isset($existing[$idOccurrence])) {
                $stmt = $pdo->prepare('UPDATE treinos_exercicios SET idexercicio = :exercicio, nome_snapshot = :nome, series = :series, repeticoes = :repeticoes, carga = :carga, bloco = :bloco, cluster = :cluster, descanso = :descanso, observacoes = :observacoes, ordem = :ordem WHERE idtreino_exercicio = :id AND idtreino = :treino');
                $stmt->execute([
                    ':exercicio' => $idExercise !== '' ? $idExercise : null,
                    ':nome' => $name,
                    ':series' => $series,
                    ':repeticoes' => $repeticoes !== '' ? $repeticoes : null,
                    ':carga' => $carga !== '' ? $carga : null,
                    ':bloco' => $bloco !== '' ? $bloco : null,
                    ':cluster' => $cluster !== '' ? $cluster : null,
                    ':descanso' => $descanso !== '' ? $descanso : null,
                    ':observacoes' => trim((string) ($row['observacoes'] ?? '')) ?: null,
                    ':ordem' => $order,
                    ':id' => $idOccurrence,
                    ':treino' => $idTreino,
                ]);
            } else {
                $idOccurrence = cronogramaGerarId();
                $stmt = $pdo->prepare('INSERT INTO treinos_exercicios (idtreino_exercicio, idtreino, idexercicio, nome_snapshot, series, repeticoes, carga, bloco, cluster, descanso, observacoes, ordem) VALUES (:id, :treino, :exercicio, :nome, :series, :repeticoes, :carga, :bloco, :cluster, :descanso, :observacoes, :ordem)');
                $stmt->execute([
                    ':id' => $idOccurrence,
                    ':treino' => $idTreino,
                    ':exercicio' => $idExercise !== '' ? $idExercise : null,
                    ':nome' => $name,
                    ':series' => $series,
                    ':repeticoes' => $repeticoes !== '' ? $repeticoes : null,
                    ':carga' => $carga !== '' ? $carga : null,
                    ':bloco' => $bloco !== '' ? $bloco : null,
                    ':cluster' => $cluster !== '' ? $cluster : null,
                    ':descanso' => $descanso !== '' ? $descanso : null,
                    ':observacoes' => trim((string) ($row['observacoes'] ?? '')) ?: null,
                    ':ordem' => $order,
                ]);
            }

            $seen[] = $idOccurrence;
            $pdo->prepare('DELETE FROM valores_treino_exercicio WHERE idtreino_exercicio = :id')->execute([':id' => $idOccurrence]);
            $extras = is_array($row['extras'] ?? null) ? $row['extras'] : [];
            foreach ($extras as $idCampo => $raw) {
                if (!isset($extraById[$idCampo]) || $raw === '' || $raw === null) continue;
                $field = $extraById[$idCampo];
                $values = ['texto' => null, 'inteiro' => null, 'decimal' => null, 'booleano' => null];
                if ($field['tipo'] === 'inteiro') {
                    $parsed = filter_var($raw, FILTER_VALIDATE_INT);
                    if ($parsed === false) throw new InvalidArgumentException('Campo extra "' . $field['nome'] . '" precisa ser inteiro.');
                    $values['inteiro'] = $parsed;
                } elseif ($field['tipo'] === 'decimal') {
                    $parsed = str_replace(',', '.', trim((string) $raw));
                    if (!is_numeric($parsed)) throw new InvalidArgumentException('Campo extra "' . $field['nome'] . '" precisa ser numérico.');
                    $values['decimal'] = $parsed;
                } elseif ($field['tipo'] === 'booleano') {
                    if (!in_array($raw, [0, 1, '0', '1', false, true], true)) {
                        throw new InvalidArgumentException('Campo extra "' . $field['nome'] . '" precisa ser Sim ou Não.');
                    }
                    $values['booleano'] = in_array($raw, [1, '1', true], true);
                } else {
                    $values['texto'] = trim((string) $raw);
                }
                $pdo->prepare('INSERT INTO valores_treino_exercicio (idvalor, idtreino_exercicio, idcampo, valor_texto, valor_inteiro, valor_decimal, valor_booleano) VALUES (:id, :ocorrencia, :campo, :texto, :inteiro, :decimal, :booleano)')->execute([
                    ':id' => cronogramaGerarId(),
                    ':ocorrencia' => $idOccurrence,
                    ':campo' => $idCampo,
                    ':texto' => $values['texto'],
                    ':inteiro' => $values['inteiro'],
                    ':decimal' => $values['decimal'],
                    ':booleano' => $values['booleano'],
                ]);
            }
            $order++;
        }

        foreach ($existing as $id => $_) {
            if (!in_array($id, $seen, true)) {
                $pdo->prepare('DELETE FROM treinos_exercicios WHERE idtreino_exercicio = :id AND idtreino = :treino')->execute([':id' => $id, ':treino' => $idTreino]);
            }
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function cronogramaAdicionarCampoExtra(PDO $pdo, string $idTreino, string $idUsuario, string $nome, string $tipo): string
{
    if (cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) === []) {
        throw new RuntimeException('Treino não encontrado.');
    }
    $nome = trim($nome);
    $slug = stridebr_slug($nome);
    if ($nome === '' || stridebr_length($nome) > 80 || $slug === '' || !in_array($tipo, ['texto', 'inteiro', 'decimal', 'booleano'], true)) {
        throw new InvalidArgumentException('Campo extra inválido.');
    }
    $duplicateStmt = $pdo->prepare('SELECT idcampo, tipo, ativo FROM campos_treino_exercicio WHERE idtreino = :treino AND lower(slug) = lower(:slug) LIMIT 1');
    $duplicateStmt->execute([':treino' => $idTreino, ':slug' => $slug]);
    $existing = $duplicateStmt->fetch();
    if ($existing) {
        if (stridebr_db_bool($existing['ativo'])) {
            throw new InvalidArgumentException('Já existe uma coluna com esse nome neste treino.');
        }
        if ($existing['tipo'] !== $tipo) {
            throw new InvalidArgumentException('Uma coluna arquivada com esse nome usa outro tipo de dado.');
        }
        $pdo->prepare('UPDATE campos_treino_exercicio SET ativo = TRUE, nome = :nome WHERE idcampo = :id AND idtreino = :treino')->execute([
            ':nome' => $nome,
            ':id' => $existing['idcampo'],
            ':treino' => $idTreino,
        ]);
        return (string) $existing['idcampo'];
    }
    $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 FROM campos_treino_exercicio WHERE idtreino = :treino');
    $orderStmt->execute([':treino' => $idTreino]);
    $id = cronogramaGerarId();
    $pdo->prepare('INSERT INTO campos_treino_exercicio (idcampo, idtreino, nome, slug, tipo, ordem) VALUES (:id, :treino, :nome, :slug, :tipo, :ordem)')->execute([
        ':id' => $id,
        ':treino' => $idTreino,
        ':nome' => $nome,
        ':slug' => $slug,
        ':tipo' => $tipo,
        ':ordem' => (int) $orderStmt->fetchColumn(),
    ]);
    return $id;
}

function cronogramaDesativarCampoExtra(PDO $pdo, string $idTreino, string $idUsuario, string $idCampo): bool
{
    if (cronogramaBuscarTreino($pdo, $idTreino, $idUsuario) === []) return false;
    $stmt = $pdo->prepare('UPDATE campos_treino_exercicio SET ativo = FALSE WHERE idcampo = :campo AND idtreino = :treino');
    $stmt->execute([':campo' => $idCampo, ':treino' => $idTreino]);
    return $stmt->rowCount() === 1;
}

function cronogramaCopiarExercicio(PDO $pdo, string $idUsuario, string $idTreinoExercicio, string $idTreinoDestino): bool
{
    $destino = cronogramaBuscarTreino($pdo, $idTreinoDestino, $idUsuario);
    if ($destino === []) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT te.*, t.idtreino AS treino_origem FROM treinos_exercicios te JOIN treinos_cronograma t ON t.idtreino = te.idtreino JOIN cronogramas c ON c.idcronograma = t.idcronograma WHERE te.idtreino_exercicio = :id AND c.idusuario = :usuario');
    $stmt->execute([':id' => $idTreinoExercicio, ':usuario' => $idUsuario]);
    $source = $stmt->fetch();
    if (!$source) {
        return false;
    }

    $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 FROM treinos_exercicios WHERE idtreino = :treino');
    $orderStmt->execute([':treino' => $idTreinoDestino]);
    $newId = cronogramaGerarId();

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO treinos_exercicios (idtreino_exercicio, idtreino, idexercicio, nome_snapshot, series, repeticoes, carga, bloco, cluster, descanso, observacoes, ordem) VALUES (:id, :treino, :exercicio, :nome, :series, :repeticoes, :carga, :bloco, :cluster, :descanso, :observacoes, :ordem)')->execute([
            ':id' => $newId,
            ':treino' => $idTreinoDestino,
            ':exercicio' => $source['idexercicio'],
            ':nome' => $source['nome_snapshot'],
            ':series' => $source['series'],
            ':repeticoes' => $source['repeticoes'],
            ':carga' => $source['carga'],
            ':bloco' => $source['bloco'],
            ':cluster' => $source['cluster'],
            ':descanso' => $source['descanso'],
            ':observacoes' => $source['observacoes'],
            ':ordem' => (int) $orderStmt->fetchColumn(),
        ]);

        $extras = $pdo->prepare(
            'SELECT vo.valor_texto, vo.valor_inteiro, vo.valor_decimal, vo.valor_booleano, co.slug, co.tipo
             FROM valores_treino_exercicio vo
             JOIN campos_treino_exercicio co ON co.idcampo = vo.idcampo
             WHERE vo.idtreino_exercicio = :origem'
        );
        $extras->execute([':origem' => $idTreinoExercicio]);
        $destField = $pdo->prepare('SELECT idcampo FROM campos_treino_exercicio WHERE idtreino = :treino AND lower(slug) = lower(:slug) AND tipo = :tipo AND ativo = TRUE LIMIT 1');
        $insertValue = $pdo->prepare('INSERT INTO valores_treino_exercicio (idvalor, idtreino_exercicio, idcampo, valor_texto, valor_inteiro, valor_decimal, valor_booleano) VALUES (:id, :ocorrencia, :campo, :texto, :inteiro, :decimal, :booleano)');
        foreach ($extras->fetchAll() as $extra) {
            $destField->execute([':treino' => $idTreinoDestino, ':slug' => $extra['slug'], ':tipo' => $extra['tipo']]);
            $idCampo = $destField->fetchColumn();
            if (!$idCampo) {
                continue;
            }
            $insertValue->execute([
                ':id' => cronogramaGerarId(),
                ':ocorrencia' => $newId,
                ':campo' => $idCampo,
                ':texto' => $extra['valor_texto'],
                ':inteiro' => $extra['valor_inteiro'],
                ':decimal' => $extra['valor_decimal'],
                ':booleano' => $extra['valor_booleano'],
            ]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function cronogramaListarTreinosUsuario(PDO $pdo, string $idUsuario, ?string $ignorarTreino = null): array
{
    $sql = 'SELECT t.idtreino, t.titulo, t.dia_semana, t.hora_inicio, c.idcronograma, c.nome AS cronograma_nome FROM treinos_cronograma t JOIN cronogramas c ON c.idcronograma = t.idcronograma WHERE c.idusuario = :usuario AND c.ativo = TRUE';
    $params = [':usuario' => $idUsuario];
    if ($ignorarTreino !== null) {
        $sql .= ' AND t.idtreino <> :ignorar';
        $params[':ignorar'] = $ignorarTreino;
    }
    $sql .= ' ORDER BY c.nome, t.dia_semana, t.hora_inicio, t.titulo';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function cronogramaListarModalidadesExercicio(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare('SELECT idmodalidade, nome FROM modalidades WHERE ativo = TRUE AND (idusuario IS NULL OR idusuario = :usuario) ORDER BY idusuario NULLS FIRST, nome');
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function cronogramaAssociarExercicio(PDO $pdo, string $idExercicio, string $idUsuario, array $categorias, array $modalidades): void
{
    $stmt = $pdo->prepare('SELECT idexercicio FROM exercicios WHERE idexercicio = :id AND idusuario = :usuario LIMIT 1');
    $stmt->execute([':id' => $idExercicio, ':usuario' => $idUsuario]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException('Exercício pessoal não encontrado.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM exercicios_categorias WHERE idexercicio = :id')->execute([':id' => $idExercicio]);
        $catCheck = $pdo->prepare('SELECT idcategoria FROM categorias_exercicio WHERE idcategoria = :id AND ativo = TRUE AND (idusuario IS NULL OR idusuario = :usuario)');
        $catInsert = $pdo->prepare('INSERT INTO exercicios_categorias (idexercicio, idcategoria) VALUES (:exercicio, :categoria) ON CONFLICT DO NOTHING');
        foreach (array_unique($categorias) as $idCategoria) {
            $catCheck->execute([':id' => $idCategoria, ':usuario' => $idUsuario]);
            if ($catCheck->fetchColumn()) {
                $catInsert->execute([':exercicio' => $idExercicio, ':categoria' => $idCategoria]);
            }
        }

        $pdo->prepare('DELETE FROM exercicios_modalidades WHERE idexercicio = :id')->execute([':id' => $idExercicio]);
        $modCheck = $pdo->prepare('SELECT idmodalidade FROM modalidades WHERE idmodalidade = :id AND ativo = TRUE AND (idusuario IS NULL OR idusuario = :usuario)');
        $modInsert = $pdo->prepare('INSERT INTO exercicios_modalidades (idexercicio, idmodalidade) VALUES (:exercicio, :modalidade) ON CONFLICT DO NOTHING');
        foreach (array_unique($modalidades) as $idModalidade) {
            $modCheck->execute([':id' => $idModalidade, ':usuario' => $idUsuario]);
            if ($modCheck->fetchColumn()) {
                $modInsert->execute([':exercicio' => $idExercicio, ':modalidade' => $idModalidade]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function cronogramaCriarExercicioCompleto(PDO $pdo, string $idUsuario, string $nome, ?string $descricao, array $categorias, array $modalidades, ?string $imagemUrl = null, ?string $videoUrl = null): string
{
    $id = cronogramaCriarExercicio($pdo, $idUsuario, $nome, $descricao, [], $imagemUrl, $videoUrl);
    cronogramaAssociarExercicio($pdo, $id, $idUsuario, $categorias, $modalidades);
    return $id;
}

function cronogramaDuplicarExercicioSistema(PDO $pdo, string $idUsuario, string $idExercicio): string
{
    $stmt = $pdo->prepare('SELECT idexercicio, nome, slug, descricao, imagem_url, video_url FROM exercicios WHERE idexercicio = :id AND idusuario IS NULL AND ativo = TRUE LIMIT 1');
    $stmt->execute([':id' => $idExercicio]);
    $source = $stmt->fetch();
    if (!$source) {
        throw new RuntimeException('Exercício do StrideBR não encontrado.');
    }

    $baseName = $source['nome'];
    $name = $baseName;
    $suffix = 2;
    while (true) {
        $slug = stridebr_slug($name);
        $check = $pdo->prepare('SELECT 1 FROM exercicios WHERE idusuario = :usuario AND lower(slug) = lower(:slug) LIMIT 1');
        $check->execute([':usuario' => $idUsuario, ':slug' => $slug]);
        if (!$check->fetchColumn()) {
            break;
        }
        $name = $baseName . ' ' . $suffix;
        $suffix++;
    }

    $categories = $pdo->prepare('SELECT idcategoria FROM exercicios_categorias WHERE idexercicio = :id');
    $categories->execute([':id' => $idExercicio]);
    $modalities = $pdo->prepare('SELECT idmodalidade FROM exercicios_modalidades WHERE idexercicio = :id');
    $modalities->execute([':id' => $idExercicio]);
    return cronogramaCriarExercicioCompleto(
        $pdo,
        $idUsuario,
        $name,
        $source['descricao'],
        array_column($categories->fetchAll(), 'idcategoria'),
        array_column($modalities->fetchAll(), 'idmodalidade'),
        $source['imagem_url'] ?? null,
        $source['video_url'] ?? null
    );
}

function cronogramaDesativarExercicioPessoal(PDO $pdo, string $idUsuario, string $idExercicio): bool
{
    $stmt = $pdo->prepare('UPDATE exercicios SET ativo = FALSE, data_atualizacao = NOW() WHERE idexercicio = :id AND idusuario = :usuario');
    $stmt->execute([':id' => $idExercicio, ':usuario' => $idUsuario]);
    return $stmt->rowCount() === 1;
}


function cronogramaAtualizarExercicioPessoal(PDO $pdo, string $idUsuario, string $idExercicio, string $nome, ?string $descricao, array $categorias, array $modalidades, ?string $imagemUrl = null, ?string $videoUrl = null): bool
{
    $nome = trim($nome);
    $slug = stridebr_slug($nome);
    $imagemUrl = cronogramaNormalizarUrlMidia($imagemUrl);
    $videoUrl = cronogramaNormalizarUrlMidia($videoUrl);
    if ($nome === '' || stridebr_length($nome) > 120 || $slug === '') {
        throw new InvalidArgumentException('Informe um nome válido para o exercício.');
    }

    $check = $pdo->prepare('SELECT 1 FROM exercicios WHERE idusuario = :usuario AND lower(slug) = lower(:slug) AND idexercicio <> :id LIMIT 1');
    $check->execute([':usuario' => $idUsuario, ':slug' => $slug, ':id' => $idExercicio]);
    if ($check->fetchColumn()) {
        throw new InvalidArgumentException('Já existe outro exercício pessoal com esse nome.');
    }

    $stmt = $pdo->prepare('UPDATE exercicios SET nome = :nome, slug = :slug, descricao = :descricao, imagem_url = :imagem, video_url = :video, data_atualizacao = NOW() WHERE idexercicio = :id AND idusuario = :usuario');
    $stmt->execute([
        ':nome' => $nome,
        ':slug' => $slug,
        ':descricao' => $descricao !== null && trim($descricao) !== '' ? trim($descricao) : null,
        ':imagem' => $imagemUrl,
        ':video' => $videoUrl,
        ':id' => $idExercicio,
        ':usuario' => $idUsuario,
    ]);
    if ($stmt->rowCount() === 0) {
        $owner = $pdo->prepare('SELECT 1 FROM exercicios WHERE idexercicio = :id AND idusuario = :usuario');
        $owner->execute([':id' => $idExercicio, ':usuario' => $idUsuario]);
        if (!$owner->fetchColumn()) {
            return false;
        }
    }
    cronogramaAssociarExercicio($pdo, $idExercicio, $idUsuario, $categorias, $modalidades);
    return true;
}
