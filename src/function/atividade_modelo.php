<?php

declare(strict_types=1);

use Hidehalo\Nanoid\Client;

function atividadeGerarId(int $length = 21): string
{
    static $client = null;
    $client ??= new Client();
    return $client->generateId($length);
}

function atividadeListarCatalogo(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        "SELECT
            m.idmodalidade,
            m.nome AS modalidade_nome,
            m.slug AS modalidade_slug,
            mm.idmodelo,
            mm.nome AS modelo_nome,
            mm.slug AS modelo_slug,
            mm.versao,
            mm.padrao,
            mm.tipo_unidade_padrao,
            mm.rotulo_unidade,
            mm.permite_multiplas_unidades
        FROM modalidades m
        JOIN modelos_modalidade mm ON mm.idmodalidade = m.idmodalidade
        WHERE m.ativo = TRUE
          AND mm.ativo = TRUE
          AND (m.idusuario IS NULL OR m.idusuario = :usuario)
          AND (mm.idusuario IS NULL OR mm.idusuario = :usuario)
        ORDER BY m.nome, mm.padrao DESC, mm.nome, mm.versao DESC"
    );
    $stmt->execute([':usuario' => $idUsuario]);
    $rows = $stmt->fetchAll();

    $catalogo = [];
    foreach ($rows as $row) {
        $modalidadeId = $row['idmodalidade'];
        if (!isset($catalogo[$modalidadeId])) {
            $catalogo[$modalidadeId] = [
                'idmodalidade' => $modalidadeId,
                'nome' => $row['modalidade_nome'],
                'slug' => $row['modalidade_slug'],
                'modelos' => [],
            ];
        }
        $catalogo[$modalidadeId]['modelos'][] = [
            'idmodelo' => $row['idmodelo'],
            'nome' => $row['modelo_nome'],
            'slug' => $row['modelo_slug'],
            'versao' => (int) $row['versao'],
            'padrao' => stridebr_db_bool($row['padrao']),
            'tipo_unidade_padrao' => $row['tipo_unidade_padrao'],
            'rotulo_unidade' => $row['rotulo_unidade'],
            'permite_multiplas_unidades' => stridebr_db_bool($row['permite_multiplas_unidades']),
        ];
    }

    return array_values($catalogo);
}

function atividadeBuscarModelo(PDO $pdo, string $idModelo, string $idUsuario, bool $somenteAtivo = true): array
{
    $activeClause = $somenteAtivo ? ' AND mm.ativo = TRUE AND m.ativo = TRUE' : '';
    $stmt = $pdo->prepare(
        "SELECT
            mm.idmodelo,
            mm.idmodalidade,
            mm.nome,
            mm.slug,
            mm.versao,
            mm.tipo_unidade_padrao,
            mm.rotulo_unidade,
            mm.permite_multiplas_unidades,
            m.nome AS modalidade_nome,
            m.slug AS modalidade_slug
        FROM modelos_modalidade mm
        JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
        WHERE mm.idmodelo = :modelo
          AND (mm.idusuario IS NULL OR mm.idusuario = :usuario)
          AND (m.idusuario IS NULL OR m.idusuario = :usuario)" . $activeClause . "
        LIMIT 1"
    );
    $stmt->execute([':modelo' => $idModelo, ':usuario' => $idUsuario]);
    $modelo = $stmt->fetch();
    if (!$modelo) {
        return [];
    }
    $modelo['permite_multiplas_unidades'] = stridebr_db_bool($modelo['permite_multiplas_unidades']);
    return $modelo;
}

function atividadeBuscarCamposModelo(PDO $pdo, string $idModelo, bool $somenteAtivos = true): array
{
    $activeClause = $somenteAtivos ? ' AND cm.ativo = TRUE' : '';
    $stmt = $pdo->prepare(
        "SELECT
            cm.idcampo,
            cm.slug,
            cm.rotulo,
            cm.tipo_campo,
            cm.escopo,
            cm.obrigatorio,
            cm.ordem,
            cm.ativo,
            u.simbolo AS unidade_simbolo
        FROM campos_modelo cm
        LEFT JOIN unidades u ON u.idunidade = cm.idunidade
        WHERE cm.idmodelo = :modelo" . $activeClause . "
        ORDER BY cm.ordem, cm.rotulo"
    );
    $stmt->execute([':modelo' => $idModelo]);
    $campos = $stmt->fetchAll();

    $optionSql = 'SELECT idopcao, rotulo, valor, ativo FROM campos_modelo_opcoes WHERE idcampo = :campo';
    if ($somenteAtivos) {
        $optionSql .= ' AND ativo = TRUE';
    }
    $optionSql .= ' ORDER BY ordem, rotulo';
    $optionStmt = $pdo->prepare($optionSql);

    foreach ($campos as &$campo) {
        $campo['obrigatorio'] = stridebr_db_bool($campo['obrigatorio']);
        $campo['ativo'] = stridebr_db_bool($campo['ativo']);
        $campo['opcoes'] = [];
        if ($campo['tipo_campo'] === 'selecao') {
            $optionStmt->execute([':campo' => $campo['idcampo']]);
            $campo['opcoes'] = $optionStmt->fetchAll();
        }
    }
    unset($campo);

    return $campos;
}

function atividadeAgruparCampos(array $campos): array
{
    $resultado = ['registro' => [], 'unidade' => []];
    foreach ($campos as $campo) {
        $escopo = $campo['escopo'] === 'registro' ? 'registro' : 'unidade';
        $resultado[$escopo][] = $campo;
    }
    return $resultado;
}

function atividadeRenderizarCampo(array $campo, string $name, string $id, mixed $valor = null): string
{
    $label = stridebr_e($campo['rotulo'] ?? 'Campo');
    $required = !empty($campo['obrigatorio']) ? ' required' : '';
    $unit = !empty($campo['unidade_simbolo']) ? ' <span class="field-unit">(' . stridebr_e($campo['unidade_simbolo']) . ')</span>' : '';
    $type = $campo['tipo_campo'] ?? 'texto';
    $value = $valor === null ? '' : (string) $valor;
    $html = '<div class="input-field dynamic-field">';
    $html .= '<label for="' . stridebr_e($id) . '">' . $label . $unit . '</label>';

    if ($type === 'texto_longo') {
        $html .= '<textarea id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '" rows="3"' . $required . '>' . stridebr_e($value) . '</textarea>';
    } elseif ($type === 'inteiro') {
        $html .= '<input type="number" step="1" id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '" value="' . stridebr_e($value) . '"' . $required . '>';
    } elseif ($type === 'decimal') {
        $html .= '<input type="number" step="any" id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '" value="' . stridebr_e($value) . '"' . $required . '>';
    } elseif ($type === 'booleano') {
        $hasValue = $valor !== null && $valor !== '';
        $isTrue = $hasValue && stridebr_db_bool($valor);
        $html .= '<select id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '"' . $required . '>';
        $html .= '<option value="">Não informado</option>';
        $html .= '<option value="1"' . ($hasValue && $isTrue ? ' selected' : '') . '>Sim</option>';
        $html .= '<option value="0"' . ($hasValue && !$isTrue ? ' selected' : '') . '>Não</option>';
        $html .= '</select>';
    } elseif ($type === 'data') {
        $html .= '<input type="date" id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '" value="' . stridebr_e($value) . '"' . $required . '>';
    } elseif ($type === 'hora') {
        $html .= '<input type="time" id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '" value="' . stridebr_e($value) . '"' . $required . '>';
    } elseif ($type === 'intervalo') {
        $html .= '<input type="text" inputmode="numeric" placeholder="HH:MM:SS" pattern="(?:\\d+:)?[0-5]?\\d:[0-5]\\d" id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '" value="' . stridebr_e($value) . '"' . $required . '>';
    } elseif ($type === 'selecao') {
        $html .= '<select id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '"' . $required . '>';
        $html .= '<option value="">Selecione</option>';
        foreach ($campo['opcoes'] ?? [] as $opcao) {
            $selected = (string) $opcao['idopcao'] === $value ? ' selected' : '';
            $html .= '<option value="' . stridebr_e($opcao['idopcao']) . '"' . $selected . '>' . stridebr_e($opcao['rotulo']) . '</option>';
        }
        $html .= '</select>';
    } else {
        $html .= '<input type="text" id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '" value="' . stridebr_e($value) . '"' . $required . '>';
    }

    $html .= '</div>';
    return $html;
}

function atividadeNormalizarIntervalo(mixed $valor): ?string
{
    $valor = trim((string) $valor);
    if ($valor === '') {
        return null;
    }

    if (preg_match('/^(\d+):([0-5]\d):([0-5]\d)$/', $valor, $matches)) {
        $horas = (int) $matches[1];
        $minutos = (int) $matches[2];
        $segundos = (int) $matches[3];
    } elseif (preg_match('/^(\d+):([0-5]\d)$/', $valor, $matches)) {
        $horas = 0;
        $minutos = (int) $matches[1];
        $segundos = (int) $matches[2];
    } else {
        throw new InvalidArgumentException('Use o formato HH:MM:SS ou MM:SS para durações.');
    }

    $total = $horas * 3600 + $minutos * 60 + $segundos;
    return $total . ' seconds';
}

function atividadeFormatarIntervalo(mixed $valor): string
{
    if ($valor === null || $valor === '') {
        return '';
    }

    $texto = (string) $valor;
    if (preg_match('/^(\d+):([0-5]\d):([0-5]\d)(?:\.\d+)?$/', $texto, $matches)) {
        return sprintf('%02d:%02d:%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }
    if (preg_match('/^(\d+) days? (\d+):([0-5]\d):([0-5]\d)/', $texto, $matches)) {
        $horas = (int) $matches[1] * 24 + (int) $matches[2];
        return sprintf('%02d:%02d:%02d', $horas, (int) $matches[3], (int) $matches[4]);
    }
    return $texto;
}

function atividadePrepararValor(array $campo, mixed $raw): ?array
{
    $tipo = $campo['tipo_campo'];
    $missing = $raw === null || (is_string($raw) && trim($raw) === '');
    if ($missing) {
        if (!empty($campo['obrigatorio'])) {
            throw new InvalidArgumentException('O campo "' . $campo['rotulo'] . '" é obrigatório.');
        }
        return null;
    }

    $bind = [
        'valor_texto' => null,
        'valor_inteiro' => null,
        'valor_decimal' => null,
        'valor_booleano' => null,
        'valor_data' => null,
        'valor_hora' => null,
        'valor_intervalo' => null,
        'idopcao' => null,
    ];

    if ($tipo === 'texto' || $tipo === 'texto_longo') {
        $bind['valor_texto'] = trim((string) $raw);
    } elseif ($tipo === 'inteiro') {
        if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('O campo "' . $campo['rotulo'] . '" precisa ser um número inteiro.');
        }
        $bind['valor_inteiro'] = (int) $raw;
    } elseif ($tipo === 'decimal') {
        $normalizado = str_replace(',', '.', trim((string) $raw));
        if (!is_numeric($normalizado)) {
            throw new InvalidArgumentException('O campo "' . $campo['rotulo'] . '" precisa ser numérico.');
        }
        $bind['valor_decimal'] = $normalizado;
    } elseif ($tipo === 'booleano') {
        if (!in_array($raw, [0, 1, '0', '1', false, true], true)) {
            throw new InvalidArgumentException('Valor inválido no campo "' . $campo['rotulo'] . '".');
        }
        $bind['valor_booleano'] = in_array($raw, [1, '1', true], true);
    } elseif ($tipo === 'data') {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $raw);
        if (!$data || $data->format('Y-m-d') !== $raw) {
            throw new InvalidArgumentException('Data inválida no campo "' . $campo['rotulo'] . '".');
        }
        $bind['valor_data'] = $raw;
    } elseif ($tipo === 'hora') {
        $hora = DateTimeImmutable::createFromFormat('!H:i', (string) $raw);
        if (!$hora) {
            throw new InvalidArgumentException('Hora inválida no campo "' . $campo['rotulo'] . '".');
        }
        $bind['valor_hora'] = $hora->format('H:i:s');
    } elseif ($tipo === 'intervalo') {
        $bind['valor_intervalo'] = atividadeNormalizarIntervalo($raw);
    } elseif ($tipo === 'selecao') {
        $opcoes = array_column($campo['opcoes'] ?? [], 'idopcao');
        if (!in_array((string) $raw, $opcoes, true)) {
            throw new InvalidArgumentException('Opção inválida no campo "' . $campo['rotulo'] . '".');
        }
        $bind['idopcao'] = (string) $raw;
    }

    return $bind;
}

function atividadeInserirValor(PDO $pdo, string $idRegistro, ?string $idUnidade, array $campo, array $valor): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO valores_atividade (idvalor, idregistro, idunidade_atividade, idcampo, valor_texto, valor_inteiro, valor_decimal, valor_booleano, valor_data, valor_hora, valor_intervalo, idopcao)
         VALUES (:idvalor, :idregistro, :idunidade, :idcampo, :valor_texto, :valor_inteiro, :valor_decimal, :valor_booleano, :valor_data, :valor_hora, CAST(:valor_intervalo AS interval), :idopcao)'
    );
    $stmt->bindValue(':idvalor', atividadeGerarId());
    $stmt->bindValue(':idregistro', $idRegistro);
    $stmt->bindValue(':idunidade', $idUnidade, $idUnidade === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':idcampo', $campo['idcampo']);
    $stmt->bindValue(':valor_texto', $valor['valor_texto'], $valor['valor_texto'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':valor_inteiro', $valor['valor_inteiro'], $valor['valor_inteiro'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':valor_decimal', $valor['valor_decimal'], $valor['valor_decimal'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':valor_booleano', $valor['valor_booleano'], $valor['valor_booleano'] === null ? PDO::PARAM_NULL : PDO::PARAM_BOOL);
    $stmt->bindValue(':valor_data', $valor['valor_data'], $valor['valor_data'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':valor_hora', $valor['valor_hora'], $valor['valor_hora'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':valor_intervalo', $valor['valor_intervalo'], $valor['valor_intervalo'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':idopcao', $valor['idopcao'], $valor['idopcao'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->execute();
}

function atividadeSalvarRegistro(PDO $pdo, string $idUsuario, array $payload, ?string $idRegistro = null): string
{
    $modelo = atividadeBuscarModelo($pdo, (string) ($payload['idmodelo'] ?? ''), $idUsuario, $idRegistro === null);
    if ($modelo === []) {
        throw new InvalidArgumentException('Modelo de atividade inválido.');
    }

    $campos = atividadeBuscarCamposModelo($pdo, $modelo['idmodelo'], $idRegistro === null);
    $camposPorId = [];
    foreach ($campos as $campo) {
        $camposPorId[$campo['idcampo']] = $campo;
    }

    $inicioRaw = trim((string) ($payload['data_inicio'] ?? ''));
    $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $inicioRaw);
    if (!$inicio || $inicio->format('Y-m-d H:i') !== $inicioRaw) {
        throw new InvalidArgumentException('Data ou hora da atividade inválida.');
    }

    $status = (string) ($payload['status'] ?? 'concluido');
    if (!in_array($status, ['rascunho', 'ativo', 'concluido', 'cancelado'], true)) {
        throw new InvalidArgumentException('Status da atividade inválido.');
    }

    $fimSql = null;
    $fimRaw = trim((string) ($payload['data_fim'] ?? ''));
    if ($fimRaw !== '') {
        $fim = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $fimRaw);
        if (!$fim || $fim->format('Y-m-d H:i') !== $fimRaw || $fim < $inicio) {
            throw new InvalidArgumentException('Horário final da atividade inválido.');
        }
        $fimSql = $fim->format('Y-m-d H:i:s');
    }

    $origem = (string) ($payload['origem'] ?? 'manual');
    if (!in_array($origem, ['manual', 'gps', 'importacao', 'api'], true)) {
        throw new InvalidArgumentException('Origem da atividade inválida.');
    }
    $idCronograma = trim((string) ($payload['idcronograma'] ?? '')) ?: null;
    $idTreinoCronograma = trim((string) ($payload['idtreino_cronograma'] ?? '')) ?: null;

    $visibilidade = (string) ($payload['visibilidade'] ?? 'privado');
    if (!in_array($visibilidade, ['privado', 'amigos', 'publico'], true)) {
        throw new InvalidArgumentException('Visibilidade da atividade inválida.');
    }

    $titulo = trim((string) ($payload['titulo'] ?? ''));
    if ($titulo === '') {
        $titulo = $modelo['modalidade_nome'];
    }
    if (stridebr_length($titulo) > 255) {
        throw new InvalidArgumentException('O título da atividade é muito longo.');
    }

    $unidades = $payload['unidades'] ?? [];
    if (!is_array($unidades) || $unidades === []) {
        $unidades = [['values' => []]];
    }
    if (!$modelo['permite_multiplas_unidades']) {
        $unidades = [reset($unidades) ?: ['values' => []]];
    }

    $pdo->beginTransaction();
    try {
        if ($idRegistro !== null) {
            $owner = $pdo->prepare('SELECT idregistro, idmodelo FROM registros_atividade WHERE idregistro = :id AND idusuario = :usuario FOR UPDATE');
            $owner->execute([':id' => $idRegistro, ':usuario' => $idUsuario]);
            $existing = $owner->fetch();
            if (!$existing) {
                throw new RuntimeException('Atividade não encontrada.');
            }
            if ($existing['idmodelo'] !== $modelo['idmodelo']) {
                throw new InvalidArgumentException('O modelo de uma atividade existente não pode ser trocado.');
            }

            $stmt = $pdo->prepare('UPDATE registros_atividade SET titulo = :titulo, observacoes = :observacoes, data_inicio = :inicio, status = :status, visibilidade = :visibilidade, data_atualizacao = NOW() WHERE idregistro = :id AND idusuario = :usuario');
            $stmt->execute([
                ':titulo' => $titulo,
                ':observacoes' => trim((string) ($payload['observacoes'] ?? '')) ?: null,
                ':inicio' => $inicio->format('Y-m-d H:i:s'),
                ':status' => $status,
                ':visibilidade' => $visibilidade,
                ':id' => $idRegistro,
                ':usuario' => $idUsuario,
            ]);
            $pdo->prepare('DELETE FROM valores_atividade WHERE idregistro = :id')->execute([':id' => $idRegistro]);
            $pdo->prepare('DELETE FROM unidades_atividade WHERE idregistro = :id')->execute([':id' => $idRegistro]);
        } else {
            $idRegistro = atividadeGerarId();
            $stmt = $pdo->prepare('INSERT INTO registros_atividade (idregistro, idusuario, idmodalidade, idmodelo, idcronograma, idtreino_cronograma, titulo, observacoes, data_inicio, data_fim, status, visibilidade, origem) VALUES (:id, :usuario, :modalidade, :modelo, :cronograma, :treino, :titulo, :observacoes, :inicio, :fim, :status, :visibilidade, :origem)');
            $stmt->execute([
                ':id' => $idRegistro,
                ':usuario' => $idUsuario,
                ':modalidade' => $modelo['idmodalidade'],
                ':modelo' => $modelo['idmodelo'],
                ':cronograma' => $idCronograma,
                ':treino' => $idTreinoCronograma,
                ':titulo' => $titulo,
                ':observacoes' => trim((string) ($payload['observacoes'] ?? '')) ?: null,
                ':inicio' => $inicio->format('Y-m-d H:i:s'),
                ':fim' => $fimSql,
                ':status' => $status,
                ':visibilidade' => $visibilidade,
                ':origem' => $origem,
            ]);
        }

        $recordValues = is_array($payload['record_values'] ?? null) ? $payload['record_values'] : [];
        foreach ($campos as $campo) {
            if ($campo['escopo'] !== 'registro') {
                continue;
            }
            $prepared = atividadePrepararValor($campo, $recordValues[$campo['idcampo']] ?? null);
            if ($prepared !== null) {
                atividadeInserirValor($pdo, $idRegistro, null, $campo, $prepared);
            }
        }

        $ordem = 1;
        foreach ($unidades as $unidadePayload) {
            if (!is_array($unidadePayload)) {
                continue;
            }
            $rotuloUnidade = trim((string) ($unidadePayload['rotulo'] ?? ''));
            if (stridebr_length($rotuloUnidade) > 120) {
                throw new InvalidArgumentException('O rótulo da unidade de atividade é muito longo.');
            }
            $idUnidade = atividadeGerarId();
            $stmtUnit = $pdo->prepare('INSERT INTO unidades_atividade (idunidade_atividade, idregistro, ordem, tipo_unidade, rotulo, observacoes) VALUES (:id, :registro, :ordem, :tipo, :rotulo, :observacoes)');
            $stmtUnit->execute([
                ':id' => $idUnidade,
                ':registro' => $idRegistro,
                ':ordem' => $ordem,
                ':tipo' => $modelo['tipo_unidade_padrao'],
                ':rotulo' => $rotuloUnidade !== '' ? $rotuloUnidade : null,
                ':observacoes' => trim((string) ($unidadePayload['observacoes'] ?? '')) ?: null,
            ]);

            $values = is_array($unidadePayload['values'] ?? null) ? $unidadePayload['values'] : [];
            foreach ($campos as $campo) {
                if ($campo['escopo'] !== 'unidade') {
                    continue;
                }
                $prepared = atividadePrepararValor($campo, $values[$campo['idcampo']] ?? null);
                if ($prepared !== null) {
                    atividadeInserirValor($pdo, $idRegistro, $idUnidade, $campo, $prepared);
                }
            }
            $ordem++;
        }

        $pdo->commit();
        return $idRegistro;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function atividadeValorLinha(array $row): mixed
{
    foreach (['valor_texto', 'valor_inteiro', 'valor_decimal', 'valor_booleano', 'valor_data', 'valor_hora', 'valor_intervalo', 'idopcao'] as $coluna) {
        if ($row[$coluna] !== null) {
            if ($coluna === 'valor_booleano') {
                return stridebr_db_bool($row[$coluna]);
            }
            if ($coluna === 'valor_intervalo') {
                return atividadeFormatarIntervalo($row[$coluna]);
            }
            return $row[$coluna];
        }
    }
    return null;
}

function atividadeCarregarRegistro(PDO $pdo, string $idRegistro, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        "SELECT ra.*, m.nome AS modalidade_nome, m.slug AS modalidade_slug, mm.nome AS modelo_nome, mm.slug AS modelo_slug,
                mm.tipo_unidade_padrao, mm.rotulo_unidade, mm.permite_multiplas_unidades
         FROM registros_atividade ra
         JOIN modalidades m ON m.idmodalidade = ra.idmodalidade
         JOIN modelos_modalidade mm ON mm.idmodelo = ra.idmodelo
         WHERE ra.idregistro = :id AND ra.idusuario = :usuario LIMIT 1"
    );
    $stmt->execute([':id' => $idRegistro, ':usuario' => $idUsuario]);
    $registro = $stmt->fetch();
    if (!$registro) {
        return [];
    }

    $registro['permite_multiplas_unidades'] = stridebr_db_bool($registro['permite_multiplas_unidades']);
    $registro['campos'] = atividadeBuscarCamposModelo($pdo, $registro['idmodelo'], false);
    $registro['record_values'] = [];
    $registro['unidades'] = [];

    $valueStmt = $pdo->prepare(
        'SELECT va.*, cmo.rotulo AS opcao_rotulo FROM valores_atividade va LEFT JOIN campos_modelo_opcoes cmo ON cmo.idopcao = va.idopcao WHERE va.idregistro = :registro AND va.idunidade_atividade IS NULL'
    );
    $valueStmt->execute([':registro' => $idRegistro]);
    foreach ($valueStmt->fetchAll() as $row) {
        $registro['record_values'][$row['idcampo']] = atividadeValorLinha($row);
    }

    $unitStmt = $pdo->prepare('SELECT * FROM unidades_atividade WHERE idregistro = :registro ORDER BY ordem');
    $unitStmt->execute([':registro' => $idRegistro]);
    $unitValueStmt = $pdo->prepare(
        'SELECT va.*, cmo.rotulo AS opcao_rotulo FROM valores_atividade va LEFT JOIN campos_modelo_opcoes cmo ON cmo.idopcao = va.idopcao WHERE va.idunidade_atividade = :unidade'
    );
    foreach ($unitStmt->fetchAll() as $unit) {
        $unit['values'] = [];
        $unitValueStmt->execute([':unidade' => $unit['idunidade_atividade']]);
        foreach ($unitValueStmt->fetchAll() as $row) {
            $unit['values'][$row['idcampo']] = atividadeValorLinha($row);
        }
        $registro['unidades'][] = $unit;
    }

    return $registro;
}

function atividadeListarRegistros(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        "SELECT ra.idregistro, ra.titulo, ra.observacoes, ra.data_inicio, ra.status, ra.visibilidade,
                m.nome AS modalidade_nome, mm.nome AS modelo_nome,
                COUNT(DISTINCT ua.idunidade_atividade) AS total_unidades
         FROM registros_atividade ra
         JOIN modalidades m ON m.idmodalidade = ra.idmodalidade
         JOIN modelos_modalidade mm ON mm.idmodelo = ra.idmodelo
         LEFT JOIN unidades_atividade ua ON ua.idregistro = ra.idregistro
         WHERE ra.idusuario = :usuario
         GROUP BY ra.idregistro, m.nome, mm.nome
         ORDER BY ra.data_inicio DESC"
    );
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function atividadeExcluirRegistro(PDO $pdo, string $idRegistro, string $idUsuario): bool
{
    $stmt = $pdo->prepare('DELETE FROM registros_atividade WHERE idregistro = :id AND idusuario = :usuario');
    $stmt->execute([':id' => $idRegistro, ':usuario' => $idUsuario]);
    return $stmt->rowCount() === 1;
}
