<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';

final class ActivitySaveFakeStatement extends PDOStatement
{
    public array $bound = [];
    public array $rows = [];
    public mixed $column = false;

    public function __construct(private ActivitySaveFakePDO $pdo, private string $sql) {}

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bound[(string) $param] = $value;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $params = array_merge($this->bound, $params ?? []);
        $sql = $this->sql;
        $this->rows = [];
        $this->column = false;
        $this->pdo->queries[] = [$sql, $params];

        if (str_contains($sql, 'FROM modelos_modalidade mm') && str_contains($sql, 'WHERE mm.idmodelo = :modelo')) {
            $this->rows = [[
                'idmodelo' => 'model_run',
                'idmodalidade' => 'sport_run',
                'nome' => 'Corrida',
                'slug' => 'corrida',
                'versao' => 1,
                'tipo_unidade_padrao' => 'trecho',
                'rotulo_unidade' => 'Trecho',
                'permite_multiplas_unidades' => false,
                'modalidade_nome' => 'Corrida',
                'modalidade_slug' => 'corrida',
                'permite_rota' => true,
            ]];
        } elseif (str_contains($sql, 'FROM campos_modelo cm')) {
            $this->rows = [
                [
                    'idmodelo' => 'model_run', 'idcampo' => 'field_distance', 'slug' => 'distancia', 'rotulo' => 'Distância',
                    'tipo_campo' => 'decimal', 'escopo' => 'unidade', 'obrigatorio' => true, 'ordem' => 1, 'ativo' => true,
                    'exibicao_padrao' => true, 'grupo_ui' => 'principal', 'unidade_simbolo' => 'km',
                ],
                [
                    'idmodelo' => 'model_run', 'idcampo' => 'field_duration', 'slug' => 'duracao', 'rotulo' => 'Duração',
                    'tipo_campo' => 'intervalo', 'escopo' => 'unidade', 'obrigatorio' => true, 'ordem' => 2, 'ativo' => true,
                    'exibicao_padrao' => true, 'grupo_ui' => 'principal', 'unidade_simbolo' => '',
                ],
            ];
        } elseif (str_contains($sql, 'FROM modalidades') && str_contains($sql, 'idmodalidade IN')) {
            $this->rows = [[
                'idmodalidade' => 'sport_run', 'nome' => 'Corrida', 'slug' => 'corrida',
                'metrica_derivada' => 'ritmo', 'permite_rota' => true,
            ]];
        } elseif (str_contains($sql, 'SELECT preferenciasusuario FROM usuarios')) {
            $this->column = '{}';
        } elseif (str_contains($sql, 'information_schema.columns')) {
            $table = (string) ($params[':table'] ?? '');
            $column = (string) ($params[':column'] ?? '');
            $this->column = $this->pdo->modernSchema ? in_array($table . '.' . $column, [
                'registros_atividade.usa_trechos',
                'unidades_atividade.idmodalidade',
                'unidades_atividade.distancia_metros',
                'unidades_atividade.duracao_segundos',
                'unidades_atividade.elevacao_m',
                'modalidades_usuario.ultimo_uso',
            ], true) : false;
        } elseif (str_contains($sql, 'SELECT to_regclass(:table) IS NOT NULL')) {
            $table = str_replace('stridebr.', '', (string) ($params[':table'] ?? ''));
            $this->column = in_array($table, [
                'rotas_unidades_atividade', 'rotas_atividade', 'registros_atividade_equipamentos', 'modalidades_usuario'
            ], true);
        } elseif (str_starts_with(trim($sql), 'INSERT INTO registros_atividade')) {
            $this->pdo->savedRecords[] = $params;
        } elseif (str_starts_with(trim($sql), 'INSERT INTO unidades_atividade')) {
            $this->pdo->savedUnits[] = $params;
        } elseif (str_starts_with(trim($sql), 'INSERT INTO valores_atividade')) {
            $this->pdo->savedValues[] = $params;
        } elseif (str_starts_with(trim($sql), 'INSERT INTO modalidades_usuario')) {
            if ($this->pdo->failPreference) throw new PDOException('simulated preference failure');
        }
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?: false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $rows = $this->rows;
        $this->rows = [];
        return $rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if ($this->column !== false) return $this->column;
        if ($this->rows !== []) {
            $row = $this->rows[0];
            return array_values($row)[$column] ?? false;
        }
        return false;
    }
}

final class ActivitySaveFakePDO extends PDO
{
    public bool $tx = false;
    public array $queries = [];
    public array $savedRecords = [];
    public array $savedUnits = [];
    public array $savedValues = [];

    public function __construct(public bool $modernSchema = true, public bool $failPreference = false) {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new ActivitySaveFakeStatement($this, $query);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $stmt = new ActivitySaveFakeStatement($this, $query);
        $stmt->execute();
        return $stmt;
    }

    public function beginTransaction(): bool { $this->tx = true; return true; }
    public function commit(): bool { $this->tx = false; return true; }
    public function rollBack(): bool { $this->tx = false; return true; }
    public function inTransaction(): bool { return $this->tx; }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function runScenario(string $name, array $payload, bool $modernSchema = true, bool $failPreference = false): void
{
    $pdo = new ActivitySaveFakePDO($modernSchema, $failPreference);
    $id = atividadeSalvarRegistro($pdo, 'user_test', array_merge([
        'idmodelo' => 'model_run',
        'data_inicio' => '2026-08-31 16:30',
        'status' => 'concluido',
        'visibilidade' => 'privado',
        'origem' => 'manual',
        'permitir_campos_vazios' => true,
    ], $payload));

    assertTrue($id !== '', "$name não retornou id");
    assertTrue(count($pdo->savedRecords) === 1, "$name não inseriu registro");
    assertTrue(count($pdo->savedUnits) === 1, "$name não inseriu unidade base");
    assertTrue(!$pdo->inTransaction(), "$name deixou transação aberta");

    $values = $payload['unidades'][0]['values'] ?? [];
    $hasDistance = array_key_exists('field_distance', $values);
    $hasDuration = array_key_exists('field_duration', $values);
    if ($modernSchema && $hasDistance) {
        assertTrue(abs((float) ($pdo->savedUnits[0][':distancia'] ?? -1) - 2000.0) < 0.001, "$name não converteu 2 km para 2000 m");
    }
    if ($modernSchema && $hasDuration) {
        assertTrue((int) ($pdo->savedUnits[0][':duracao'] ?? -1) === 1200, "$name não converteu 20 min para 1200 s");
    }
    assertTrue(count($pdo->savedValues) === (int) $hasDistance + (int) $hasDuration, "$name não persistiu exatamente os valores informados");
}

try {
    $legacy = getenv('STRIDEBR_FAKE_LEGACY') === '1';
    if ($legacy) {
        runScenario('atividade vazia em schema legado', ['unidades' => [['values' => []]]], false);
        runScenario('só distância em schema legado', ['unidades' => [['values' => ['field_distance' => '2']]]], false);
        runScenario('só duração em schema legado', ['unidades' => [['values' => ['field_duration' => '00:20:00']]]], false);
        runScenario('distância + duração em schema legado', ['unidades' => [['values' => ['field_distance' => '2', 'field_duration' => '00:20:00']]]], false);
        echo "✓ manual activity save fake integration legacy (empty, partial)\n";
    } else {
        runScenario('atividade vazia', ['unidades' => [['values' => []]]]);
        runScenario('só distância', ['unidades' => [['values' => ['field_distance' => '2']]]]);
        runScenario('só duração', ['unidades' => [['values' => ['field_duration' => '00:20:00']]]]);
        runScenario('distância + duração', ['unidades' => [['values' => ['field_distance' => '2', 'field_duration' => '00:20:00']]]]);
        runScenario('atividade vazia com falha de preferência auxiliar', ['unidades' => [['values' => []]]], true, true);
        echo "✓ manual activity save fake integration (empty, partial, auxiliary failure)\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "✗ manual activity save fake integration\n  {$e->getMessage()}\n");
    exit(1);
}
