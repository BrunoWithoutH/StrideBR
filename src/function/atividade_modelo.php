<?php

use Hidehalo\Nanoid\Client;

function atividadeGerarId(): string
{
    static $client = null;

    if ($client === null) {
        $client = new Client();
    }

    return $client->generateId(16);
}

function atividadeGarantirModelosPadrao(PDO $pdo): array
{
    try {
        $pdo->exec("SELECT 1");
    } catch (Throwable $e) {
        return [];
    }

    $modalidadeSlug = 'atividade-fisica';
    $modalidadeNome = 'Atividade física';

    $stmt = $pdo->prepare("SELECT idmodalidade FROM modalidades WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $modalidadeSlug]);
    $modalidade = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$modalidade) {
        $idModalidade = atividadeGerarId();
        $stmtInsert = $pdo->prepare("INSERT INTO modalidades (idmodalidade, nome, slug, descricao, ativo, data_criacao) VALUES (:idmodalidade, :nome, :slug, :descricao, TRUE, NOW())");
        $stmtInsert->execute([
            ':idmodalidade' => $idModalidade,
            ':nome' => $modalidadeNome,
            ':slug' => $modalidadeSlug,
            ':descricao' => 'Modelos padrão para registrar atividades físicas',
        ]);
    } else {
        $idModalidade = $modalidade['idmodalidade'];
    }

    $stmtVersion = $pdo->prepare("SELECT COALESCE(MAX(versao), 0) + 1 AS proxima_versao FROM modelos_modalidade WHERE idmodalidade = :idmodalidade AND idusuario IS NULL");
    $stmtVersion->execute([':idmodalidade' => $idModalidade]);
    $proximaVersao = (int) ($stmtVersion->fetch(PDO::FETCH_ASSOC)['proxima_versao'] ?? 1);

    $models = [
        'corrida-caminhada' => [
            'nome' => 'Corrida e caminhada',
            'slug' => 'corrida-caminhada',
            'descricao' => 'Distância, duração, elevação e ritmo',
            'padrao' => true,
            'fields' => [
                ['slug' => 'distancia', 'nome' => 'distancia', 'rotulo' => 'Distância', 'tipo_campo' => 'decimal', 'ordem' => 1, 'obrigatorio' => false],
                ['slug' => 'duracao', 'nome' => 'duracao', 'rotulo' => 'Duração (segundos)', 'tipo_campo' => 'inteiro', 'ordem' => 2, 'obrigatorio' => false],
                ['slug' => 'elevacao', 'nome' => 'elevacao', 'rotulo' => 'Elevação', 'tipo_campo' => 'decimal', 'ordem' => 3, 'obrigatorio' => false],
                ['slug' => 'ritmo', 'nome' => 'ritmo', 'rotulo' => 'Ritmo', 'tipo_campo' => 'texto', 'ordem' => 4, 'obrigatorio' => false],
                ['slug' => 'peso', 'nome' => 'peso', 'rotulo' => 'Peso', 'tipo_campo' => 'decimal', 'ordem' => 5, 'obrigatorio' => false],
                ['slug' => 'calorias', 'nome' => 'calorias', 'rotulo' => 'Calorias', 'tipo_campo' => 'inteiro', 'ordem' => 6, 'obrigatorio' => false],
            ],
        ],
        'ciclismo' => [
            'nome' => 'Ciclismo',
            'slug' => 'ciclismo',
            'descricao' => 'Distância, duração e elevação',
            'padrao' => false,
            'fields' => [
                ['slug' => 'distancia', 'nome' => 'distancia', 'rotulo' => 'Distância', 'tipo_campo' => 'decimal', 'ordem' => 1, 'obrigatorio' => false],
                ['slug' => 'duracao', 'nome' => 'duracao', 'rotulo' => 'Duração (segundos)', 'tipo_campo' => 'inteiro', 'ordem' => 2, 'obrigatorio' => false],
                ['slug' => 'elevacao', 'nome' => 'elevacao', 'rotulo' => 'Elevação', 'tipo_campo' => 'decimal', 'ordem' => 3, 'obrigatorio' => false],
                ['slug' => 'ritmo', 'nome' => 'ritmo', 'rotulo' => 'Ritmo', 'tipo_campo' => 'texto', 'ordem' => 4, 'obrigatorio' => false],
            ],
        ],
        'natacao' => [
            'nome' => 'Natação',
            'slug' => 'natacao',
            'descricao' => 'Distância e duração',
            'padrao' => false,
            'fields' => [
                ['slug' => 'distancia', 'nome' => 'distancia', 'rotulo' => 'Distância', 'tipo_campo' => 'decimal', 'ordem' => 1, 'obrigatorio' => false],
                ['slug' => 'duracao', 'nome' => 'duracao', 'rotulo' => 'Duração (segundos)', 'tipo_campo' => 'inteiro', 'ordem' => 2, 'obrigatorio' => false],
                ['slug' => 'ritmo', 'nome' => 'ritmo', 'rotulo' => 'Ritmo', 'tipo_campo' => 'texto', 'ordem' => 3, 'obrigatorio' => false],
            ],
        ],
        'raquete' => [
            'nome' => 'Raquete',
            'slug' => 'raquete',
            'descricao' => 'Sessão e intensidade',
            'padrao' => false,
            'fields' => [
                ['slug' => 'duracao', 'nome' => 'duracao', 'rotulo' => 'Duração (segundos)', 'tipo_campo' => 'inteiro', 'ordem' => 1, 'obrigatorio' => false],
                ['slug' => 'ritmo', 'nome' => 'ritmo', 'rotulo' => 'Intensidade', 'tipo_campo' => 'texto', 'ordem' => 2, 'obrigatorio' => false],
            ],
        ],
        'lancamento' => [
            'nome' => 'Lançamento',
            'slug' => 'lancamento',
            'descricao' => 'Duração e observações',
            'padrao' => false,
            'fields' => [
                ['slug' => 'duracao', 'nome' => 'duracao', 'rotulo' => 'Duração (segundos)', 'tipo_campo' => 'inteiro', 'ordem' => 1, 'obrigatorio' => false],
                ['slug' => 'observacoes', 'nome' => 'observacoes', 'rotulo' => 'Observações', 'tipo_campo' => 'texto_longo', 'ordem' => 2, 'obrigatorio' => false],
            ],
        ],
        'geral' => [
            'nome' => 'Geral',
            'slug' => 'geral',
            'descricao' => 'Modelo genérico para atividades diversas',
            'padrao' => false,
            'fields' => [
                ['slug' => 'duracao', 'nome' => 'duracao', 'rotulo' => 'Duração (segundos)', 'tipo_campo' => 'inteiro', 'ordem' => 1, 'obrigatorio' => false],
                ['slug' => 'ritmo', 'nome' => 'ritmo', 'rotulo' => 'Ritmo', 'tipo_campo' => 'texto', 'ordem' => 2, 'obrigatorio' => false],
                ['slug' => 'observacoes', 'nome' => 'observacoes', 'rotulo' => 'Observações', 'tipo_campo' => 'texto_longo', 'ordem' => 3, 'obrigatorio' => false],
            ],
        ],
    ];

    $created = [];

    foreach ($models as $modelDefinition) {
        $stmtModel = $pdo->prepare("SELECT idmodelo FROM modelos_modalidade WHERE idmodalidade = :idmodalidade AND slug = :slug LIMIT 1");
        $stmtModel->execute([':idmodalidade' => $idModalidade, ':slug' => $modelDefinition['slug']]);
        $model = $stmtModel->fetch(PDO::FETCH_ASSOC);

        if (!$model) {
            $idModelo = atividadeGerarId();
            $stmtInsertModel = $pdo->prepare("INSERT INTO modelos_modalidade (idmodelo, idmodalidade, nome, slug, descricao, versao, padrao, ativo, data_criacao) VALUES (:idmodelo, :idmodalidade, :nome, :slug, :descricao, :versao, :padrao, TRUE, NOW())");
            $stmtInsertModel->execute([
                ':idmodelo' => $idModelo,
                ':idmodalidade' => $idModalidade,
                ':nome' => $modelDefinition['nome'],
                ':slug' => $modelDefinition['slug'],
                ':descricao' => $modelDefinition['descricao'],
                ':versao' => $proximaVersao,
                ':padrao' => !empty($modelDefinition['padrao']) ? 1 : 0,
            ]);
            $versaoModelo = $proximaVersao;
            $proximaVersao++;
        } else {
            $idModelo = $model['idmodelo'];
            $versaoModelo = (int) ($model['versao'] ?? 1);
        }

        foreach ($modelDefinition['fields'] as $fieldDefinition) {
            $stmtField = $pdo->prepare("SELECT idcampo FROM campos_modelo WHERE idmodelo = :idmodelo AND slug = :slug LIMIT 1");
            $stmtField->execute([':idmodelo' => $idModelo, ':slug' => $fieldDefinition['slug']]);
            $field = $stmtField->fetch(PDO::FETCH_ASSOC);

            if (!$field) {
                $stmtInsertField = $pdo->prepare("INSERT INTO campos_modelo (idcampo, idmodelo, nome, slug, rotulo, tipo_campo, obrigatorio, ordem, ativo, data_criacao) VALUES (:idcampo, :idmodelo, :nome, :slug, :rotulo, :tipo_campo, :obrigatorio, :ordem, TRUE, NOW())");
                $stmtInsertField->execute([
                    ':idcampo' => atividadeGerarId(),
                    ':idmodelo' => $idModelo,
                    ':nome' => $fieldDefinition['nome'],
                    ':slug' => $fieldDefinition['slug'],
                    ':rotulo' => $fieldDefinition['rotulo'],
                    ':tipo_campo' => $fieldDefinition['tipo_campo'],
                    ':obrigatorio' => $fieldDefinition['obrigatorio'] ? 1 : 0,
                    ':ordem' => $fieldDefinition['ordem'],
                ]);
            }
        }

        $created[$modelDefinition['slug']] = [
            'idmodalidade' => $idModalidade,
            'idmodelo' => $idModelo,
            'versao' => $versaoModelo,
            'nome' => $modelDefinition['nome'],
            'slug' => $modelDefinition['slug'],
            'fields' => $modelDefinition['fields'],
        ];
    }

    return $created;
}

function atividadeModeloPorEsporte(PDO $pdo, string $esporte): array
{
    $models = atividadeGarantirModelosPadrao($pdo);
    if ($models === []) {
        return [];
    }

    $valor = mb_strtolower(trim($esporte), 'UTF-8');

    $mapeamento = [
        'caminhada' => 'corrida-caminhada',
        'corrida' => 'corrida-caminhada',
        'marcha atlética' => 'corrida-caminhada',
        'trilha' => 'corrida-caminhada',
        'ciclismo' => 'ciclismo',
        'mountain bike' => 'ciclismo',
        'downhill' => 'ciclismo',
        'bmx' => 'ciclismo',
        'nado de peito' => 'natacao',
        'nado de costas' => 'natacao',
        'nado borboleta' => 'natacao',
        'tênis' => 'raquete',
        'tênis de mesa' => 'raquete',
        'badminton' => 'raquete',
        'padel' => 'raquete',
        'beach tennis' => 'raquete',
        'arremesso de peso' => 'lancamento',
        'lançamento de disco' => 'lancamento',
        'lançamento de dardo' => 'lancamento',
        'lançamento de martelo' => 'lancamento',
    ];

    $slug = $mapeamento[$valor] ?? 'geral';

    return $models[$slug] ?? $models['geral'];
}

function atividadeBuscarCamposModelo(PDO $pdo, string $idModelo): array
{
    $stmt = $pdo->prepare("SELECT idcampo, slug, rotulo, tipo_campo, obrigatorio, ordem FROM campos_modelo WHERE idmodelo = :idmodelo AND ativo = TRUE ORDER BY ordem, rotulo");
    $stmt->execute([':idmodelo' => $idModelo]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function atividadeValorParaCampo(array $campo, ?array $valores): string
{
    if (!is_array($valores)) {
        return '';
    }

    $slug = $campo['slug'] ?? '';
    $valor = $valores[$slug] ?? '';

    if ($valor === null || $valor === '') {
        return '';
    }

    return (string) $valor;
}

function atividadeRenderizarCampo(array $campo, string $valor = '', string $prefixo = 'field_'): string
{
    $id = htmlspecialchars($prefixo . ($campo['slug'] ?? 'campo'));
    $name = htmlspecialchars($campo['slug'] ?? 'campo');
    $label = htmlspecialchars($campo['rotulo'] ?? ucfirst($campo['slug'] ?? 'campo'));
    $valorEscapado = htmlspecialchars((string) $valor);

    $html = '<div class="input-field" data-field-group="' . htmlspecialchars($campo['slug'] ?? 'campo') . '">';
    $html .= '<label for="' . $id . '">' . $label . '</label>';

    switch ($campo['tipo_campo'] ?? 'texto') {
        case 'texto_longo':
            $html .= '<textarea id="' . $id . '" name="' . $name . '" rows="3">' . $valorEscapado . '</textarea>';
            break;
        case 'inteiro':
            $html .= '<input type="number" id="' . $id . '" name="' . $name . '" step="1" value="' . $valorEscapado . '">';
            break;
        case 'decimal':
            $html .= '<input type="number" id="' . $id . '" name="' . $name . '" step="0.01" value="' . $valorEscapado . '">';
            break;
        case 'booleano':
            $checked = $valorEscapado === '1' || strtolower($valorEscapado) === 'true' ? 'checked' : '';
            $html .= '<input type="checkbox" id="' . $id . '" name="' . $name . '" value="1" ' . $checked . '>';
            break;
        case 'data':
            $html .= '<input type="date" id="' . $id . '" name="' . $name . '" value="' . $valorEscapado . '">';
            break;
        case 'hora':
            $html .= '<input type="time" id="' . $id . '" name="' . $name . '" value="' . $valorEscapado . '">';
            break;
        case 'selecao':
            $html .= '<select id="' . $id . '" name="' . $name . '">';
            $html .= '<option value="">Selecione</option>';
            $html .= '</select>';
            break;
        default:
            $html .= '<input type="text" id="' . $id . '" name="' . $name . '" value="' . $valorEscapado . '">';
            break;
    }

    $html .= '</div>';
    return $html;
}

function atividadeSalvarRegistro(PDO $pdo, array $payload, ?string $idRegistro = null): array
{
    $pdo->beginTransaction();

    try {
        if ($idRegistro) {
            $stmtDeleteValues = $pdo->prepare("DELETE FROM valores_unidade WHERE idunidade_atividade IN (SELECT idunidade_atividade FROM unidades_atividade WHERE idregistro = :idregistro)");
            $stmtDeleteValues->execute([':idregistro' => $idRegistro]);

            $stmtDeleteUnits = $pdo->prepare("DELETE FROM unidades_atividade WHERE idregistro = :idregistro");
            $stmtDeleteUnits->execute([':idregistro' => $idRegistro]);

            $stmtUpdate = $pdo->prepare("UPDATE registros_atividade SET idusuario = :idusuario, idmodalidade = :idmodalidade, idmodelo = :idmodelo, titulo = :titulo, observacoes = :observacoes, data_inicio = :data_inicio, data_fim = :data_fim, status = :status WHERE idregistro = :idregistro");
            $stmtUpdate->execute([
                ':idregistro' => $idRegistro,
                ':idusuario' => $payload['idusuario'],
                ':idmodalidade' => $payload['idmodalidade'],
                ':idmodelo' => $payload['idmodelo'],
                ':titulo' => $payload['titulo'] ?? null,
                ':observacoes' => $payload['observacoes'] ?? null,
                ':data_inicio' => $payload['data_inicio'] ?? date('Y-m-d H:i:s'),
                ':data_fim' => $payload['data_fim'] ?? null,
                ':status' => $payload['status'] ?? 'ativo',
            ]);

            $registroId = $idRegistro;
        } else {
            $registroId = atividadeGerarId();
            $stmtInsert = $pdo->prepare("INSERT INTO registros_atividade (idregistro, idusuario, idmodalidade, idmodelo, titulo, observacoes, data_inicio, data_fim, status, data_criacao) VALUES (:idregistro, :idusuario, :idmodalidade, :idmodelo, :titulo, :observacoes, :data_inicio, :data_fim, :status, NOW())");
            $stmtInsert->execute([
                ':idregistro' => $registroId,
                ':idusuario' => $payload['idusuario'],
                ':idmodalidade' => $payload['idmodalidade'],
                ':idmodelo' => $payload['idmodelo'],
                ':titulo' => $payload['titulo'] ?? null,
                ':observacoes' => $payload['observacoes'] ?? null,
                ':data_inicio' => $payload['data_inicio'] ?? date('Y-m-d H:i:s'),
                ':data_fim' => $payload['data_fim'] ?? null,
                ':status' => $payload['status'] ?? 'ativo',
            ]);
        }

        $unitId = atividadeGerarId();
        $stmtUnit = $pdo->prepare("INSERT INTO unidades_atividade (idunidade_atividade, idregistro, ordem, observacoes, data_criacao) VALUES (:idunidade_atividade, :idregistro, :ordem, :observacoes, NOW())");
        $stmtUnit->execute([
            ':idunidade_atividade' => $unitId,
            ':idregistro' => $registroId,
            ':ordem' => 1,
            ':observacoes' => $payload['unit_observacoes'] ?? null,
        ]);

        $fields = $payload['field_list'] ?? [];
        $unitValues = $payload['unit_values'] ?? [];

        foreach ($fields as $field) {
            $slug = $field['slug'] ?? '';
            $rawValue = $unitValues[$slug] ?? null;
            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            $bind = [
                ':idvalor' => atividadeGerarId(),
                ':idunidade_atividade' => $unitId,
                ':idcampo' => $field['idcampo'],
                ':valor_texto' => null,
                ':valor_inteiro' => null,
                ':valor_decimal' => null,
                ':valor_booleano' => null,
                ':valor_data' => null,
                ':valor_hora' => null,
                ':valor_intervalo' => null,
                ':idopcao' => null,
            ];

            switch ($field['tipo_campo']) {
                case 'texto':
                case 'texto_longo':
                    $bind[':valor_texto'] = (string) $rawValue;
                    break;
                case 'inteiro':
                    $bind[':valor_inteiro'] = (int) $rawValue;
                    break;
                case 'decimal':
                    $bind[':valor_decimal'] = (float) $rawValue;
                    break;
                case 'booleano':
                    $bind[':valor_booleano'] = (bool) filter_var($rawValue, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'data':
                    $bind[':valor_data'] = date('Y-m-d', strtotime((string) $rawValue));
                    break;
                case 'hora':
                    $bind[':valor_hora'] = date('H:i:s', strtotime((string) $rawValue));
                    break;
                case 'selecao':
                    $bind[':idopcao'] = (string) $rawValue;
                    break;
                default:
                    $bind[':valor_texto'] = (string) $rawValue;
                    break;
            }

            $stmtValue = $pdo->prepare("INSERT INTO valores_unidade (idvalor, idunidade_atividade, idcampo, valor_texto, valor_inteiro, valor_decimal, valor_booleano, valor_data, valor_hora, valor_intervalo, idopcao, data_criacao) VALUES (:idvalor, :idunidade_atividade, :idcampo, :valor_texto, :valor_inteiro, :valor_decimal, :valor_booleano, :valor_data, :valor_hora, :valor_intervalo, :idopcao, NOW())");
            $stmtValue->execute($bind);
        }

        $pdo->commit();

        return ['idregistro' => $registroId, 'status' => 'ok'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function atividadeListarRegistros(PDO $pdo, string $idUsuario): array
{
    try {
        $stmt = $pdo->prepare("SELECT ra.idregistro, ra.idmodelo, ra.titulo, ra.observacoes, ra.data_inicio, ra.data_fim, ra.status, mm.nome AS nome_modelo, mm.slug AS slug_modelo FROM registros_atividade ra LEFT JOIN modelos_modalidade mm ON mm.idmodelo = ra.idmodelo WHERE ra.idusuario = :idusuario ORDER BY ra.data_inicio DESC");
        $stmt->execute([':idusuario' => $idUsuario]);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }

    $resultado = [];

    foreach ($registros as $registro) {
        $unitStmt = $pdo->prepare("SELECT idunidade_atividade FROM unidades_atividade WHERE idregistro = :idregistro ORDER BY ordem LIMIT 1");
        $unitStmt->execute([':idregistro' => $registro['idregistro']]);
        $unit = $unitStmt->fetch(PDO::FETCH_ASSOC);

        $valores = [];
        if ($unit) {
            $valueStmt = $pdo->prepare("SELECT cm.slug, vu.valor_texto, vu.valor_inteiro, vu.valor_decimal, vu.valor_booleano, vu.valor_data, vu.valor_hora, vu.valor_intervalo, vu.idopcao FROM valores_unidade vu JOIN campos_modelo cm ON cm.idcampo = vu.idcampo WHERE vu.idunidade_atividade = :idunidade ORDER BY cm.ordem");
            $valueStmt->execute([':idunidade' => $unit['idunidade_atividade']]);
            foreach ($valueStmt->fetchAll(PDO::FETCH_ASSOC) as $valor) {
                if ($valor['valor_texto'] !== null) {
                    $valores[$valor['slug']] = $valor['valor_texto'];
                } elseif ($valor['valor_inteiro'] !== null) {
                    $valores[$valor['slug']] = (int) $valor['valor_inteiro'];
                } elseif ($valor['valor_decimal'] !== null) {
                    $valores[$valor['slug']] = (float) $valor['valor_decimal'];
                } elseif ($valor['valor_booleano'] !== null) {
                    $valores[$valor['slug']] = (bool) $valor['valor_booleano'];
                } elseif ($valor['valor_data'] !== null) {
                    $valores[$valor['slug']] = $valor['valor_data'];
                } elseif ($valor['valor_hora'] !== null) {
                    $valores[$valor['slug']] = $valor['valor_hora'];
                } elseif ($valor['valor_intervalo'] !== null) {
                    $valores[$valor['slug']] = $valor['valor_intervalo'];
                } elseif ($valor['idopcao'] !== null) {
                    $valores[$valor['slug']] = $valor['idopcao'];
                }
            }
        }

        $resultado[] = [
            'idregistro' => $registro['idregistro'],
            'titulo' => $registro['titulo'],
            'observacoes' => $registro['observacoes'],
            'data_inicio' => $registro['data_inicio'],
            'nome_modelo' => $registro['nome_modelo'] ?? 'Atividade',
            'slug_modelo' => $registro['slug_modelo'] ?? 'geral',
            'values' => $valores,
        ];
    }

    return $resultado;
}

function atividadeCarregarRegistro(PDO $pdo, string $idRegistro, string $idUsuario): array
{
    $stmt = $pdo->prepare("SELECT ra.idregistro, ra.idmodelo, ra.idmodalidade, ra.titulo, ra.observacoes, ra.data_inicio, ra.data_fim, ra.status, mm.nome AS nome_modelo, mm.slug AS slug_modelo FROM registros_atividade ra LEFT JOIN modelos_modalidade mm ON mm.idmodelo = ra.idmodelo WHERE ra.idregistro = :idregistro AND ra.idusuario = :idusuario LIMIT 1");
    $stmt->execute([':idregistro' => $idRegistro, ':idusuario' => $idUsuario]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        return [];
    }

    $unitStmt = $pdo->prepare("SELECT idunidade_atividade FROM unidades_atividade WHERE idregistro = :idregistro ORDER BY ordem LIMIT 1");
    $unitStmt->execute([':idregistro' => $registro['idregistro']]);
    $unit = $unitStmt->fetch(PDO::FETCH_ASSOC);

    $valores = [];
    if ($unit) {
        $valueStmt = $pdo->prepare("SELECT cm.slug, vu.valor_texto, vu.valor_inteiro, vu.valor_decimal, vu.valor_booleano, vu.valor_data, vu.valor_hora, vu.valor_intervalo, vu.idopcao FROM valores_unidade vu JOIN campos_modelo cm ON cm.idcampo = vu.idcampo WHERE vu.idunidade_atividade = :idunidade ORDER BY cm.ordem");
        $valueStmt->execute([':idunidade' => $unit['idunidade_atividade']]);
        foreach ($valueStmt->fetchAll(PDO::FETCH_ASSOC) as $valor) {
            if ($valor['valor_texto'] !== null) {
                $valores[$valor['slug']] = $valor['valor_texto'];
            } elseif ($valor['valor_inteiro'] !== null) {
                $valores[$valor['slug']] = (int) $valor['valor_inteiro'];
            } elseif ($valor['valor_decimal'] !== null) {
                $valores[$valor['slug']] = (float) $valor['valor_decimal'];
            } elseif ($valor['valor_booleano'] !== null) {
                $valores[$valor['slug']] = (bool) $valor['valor_booleano'];
            } elseif ($valor['valor_data'] !== null) {
                $valores[$valor['slug']] = $valor['valor_data'];
            } elseif ($valor['valor_hora'] !== null) {
                $valores[$valor['slug']] = $valor['valor_hora'];
            } elseif ($valor['valor_intervalo'] !== null) {
                $valores[$valor['slug']] = $valor['valor_intervalo'];
            } elseif ($valor['idopcao'] !== null) {
                $valores[$valor['slug']] = $valor['idopcao'];
            }
        }
    }

    $registro['values'] = $valores;
    return $registro;
}
