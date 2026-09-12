<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';
require_once $root . '/src/includes/app.php';
require_once $root . '/src/config/pg_config.php';
require_once $root . '/src/function/atividade_modelo.php';
require_once $root . '/src/function/strength_activity.php';

function demoArg(array $argv, string $name, ?string $default = null): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $prefix)) return substr($arg, strlen($prefix));
    }
    return $default;
}

function demoHasFlag(array $argv, string $name): bool
{
    return in_array('--' . $name, $argv, true);
}

function demoId(string $prefix, string $key): string
{
    return substr($prefix . substr(md5($key), 0, 19), 0, 21);
}

function demoColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='stridebr' AND table_name=:table AND column_name=:column)");
    $stmt->execute([':table' => $table, ':column' => $column]);
    return stridebr_db_bool($stmt->fetchColumn());
}

function demoTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT to_regclass(:table) IS NOT NULL");
    $stmt->execute([':table' => 'stridebr.' . $table]);
    return stridebr_db_bool($stmt->fetchColumn());
}

function demoSport(PDO $pdo, string $slug): array
{
    $stmt = $pdo->prepare(
        "SELECT m.idmodalidade, m.nome, m.slug, m.familia_hub, m.categoria, mm.idmodelo
         FROM modalidades m
         JOIN modelos_modalidade mm ON mm.idmodalidade = m.idmodalidade
         WHERE m.slug = :slug AND m.ativo = TRUE AND mm.ativo = TRUE AND mm.padrao = TRUE
         ORDER BY mm.versao DESC
         LIMIT 1"
    );
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException("Modalidade '{$slug}' não encontrada ou sem modelo padrão ativo.");
    $row['fields'] = atividadeBuscarCamposModelo($pdo, (string) $row['idmodelo']);
    $row['fields_by_slug'] = [];
    foreach ($row['fields'] as $field) $row['fields_by_slug'][strtolower((string) $field['slug'])] = $field;
    return $row;
}

function demoOptionId(array $field, string $value): ?string
{
    foreach ((array) ($field['opcoes'] ?? []) as $option) {
        if (strtolower((string) ($option['valor'] ?? '')) === strtolower($value)) return (string) $option['idopcao'];
    }
    return null;
}

function demoPutField(array $sport, array &$recordValues, array &$unitValues, string $slug, mixed $value): void
{
    $field = $sport['fields_by_slug'][strtolower($slug)] ?? null;
    if (!$field || $value === null || $value === '') return;
    if (($field['tipo_campo'] ?? '') === 'selecao' && !str_starts_with((string) $value, 'o_') && !str_starts_with((string) $value, 'so') && !str_starts_with((string) $value, 'do') && !str_starts_with((string) $value, 'po')) {
        $resolved = demoOptionId($field, (string) $value);
        if ($resolved === null) return;
        $value = $resolved;
    }
    if (($field['escopo'] ?? '') === 'registro') $recordValues[(string) $field['idcampo']] = $value;
    else $unitValues[(string) $field['idcampo']] = $value;
}

function demoFormatInterval(float $seconds): string
{
    $milliseconds = (int) round($seconds * 1000);
    $hours = intdiv($milliseconds, 3600000);
    $milliseconds -= $hours * 3600000;
    $minutes = intdiv($milliseconds, 60000);
    $milliseconds -= $minutes * 60000;
    $secs = intdiv($milliseconds, 1000);
    $ms = $milliseconds - $secs * 1000;
    if ($ms > 0) return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $secs, $ms);
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

function demoDefaultFields(array $sport, array $spec): array
{
    $recordValues = [];
    $unitValues = [];
    $durationSeconds = isset($spec['duration_s']) ? (float) $spec['duration_s'] : ((float) ($spec['duration_min'] ?? 0) * 60.0);
    if ($durationSeconds > 0) demoPutField($sport, $recordValues, $unitValues, 'duracao', demoFormatInterval($durationSeconds));
    if (isset($spec['distance_km'])) demoPutField($sport, $recordValues, $unitValues, 'distancia', (float) $spec['distance_km']);
    if (isset($spec['elevation_m'])) demoPutField($sport, $recordValues, $unitValues, 'elevacao', (float) $spec['elevation_m']);
    if (isset($spec['avg_hr'])) demoPutField($sport, $recordValues, $unitValues, 'fc-media', (int) $spec['avg_hr']);
    if (isset($spec['max_hr'])) demoPutField($sport, $recordValues, $unitValues, 'fc-maxima', (int) $spec['max_hr']);
    if (isset($spec['cadence'])) demoPutField($sport, $recordValues, $unitValues, 'cadencia', (int) $spec['cadence']);
    if (isset($spec['power'])) demoPutField($sport, $recordValues, $unitValues, 'potencia', (int) $spec['power']);
    foreach ((array) ($spec['record'] ?? []) as $slug => $value) demoPutField($sport, $recordValues, $unitValues, (string) $slug, $value);
    foreach ((array) ($spec['unit'] ?? []) as $slug => $value) demoPutField($sport, $recordValues, $unitValues, (string) $slug, $value);
    return [$recordValues, $unitValues];
}

function demoCreateUser(PDO $pdo, string $id, string $email, string $username, string $password, DateTimeImmutable $registeredAt): void
{
    $data = [
        'idusuario' => $id,
        'nomeusuario' => 'Atleta Demo 6M',
        'emailusuario' => $email,
        'senhausuario' => function_exists('stridebr_password_hash') ? stridebr_password_hash($password) : password_hash($password, PASSWORD_DEFAULT),
    ];
    $optional = [
        'nome_exibicao' => 'Atleta Demo 6M',
        'username' => $username,
        'statususuario' => 'Ativo',
        'onboarding_concluido' => true,
        'papelusuario' => 'user',
        'pesousuario' => 76.0,
        'alturausuario' => 178,
        'objetivousuario' => 'Fixture local para validar Progresso, histórico, metas e modalidades.',
        'visibilidadeperfil' => 'privado',
        'preferenciasusuario' => json_encode(['progress' => ['period' => '12w']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'dataregistrousuario' => $registeredAt->format('Y-m-d H:i:sP'),
    ];
    foreach ($optional as $column => $value) if (demoColumnExists($pdo, 'usuarios', $column)) $data[$column] = $value;
    $columns = array_keys($data);
    $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
    $sql = 'INSERT INTO usuarios (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    foreach ($data as $column => $value) {
        if ($column === 'preferenciasusuario') $stmt->bindValue(':' . $column, $value, PDO::PARAM_STR);
        elseif (is_bool($value)) $stmt->bindValue(':' . $column, $value, PDO::PARAM_BOOL);
        else $stmt->bindValue(':' . $column, $value);
    }
    $stmt->execute();
}

function demoActivateSport(PDO $pdo, string $userId, array $sport, int $order): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO modalidades_usuario (idusuario, idmodalidade, idmodelo_ativo, ativo, favorita, ordem_preferencia, ultimo_uso, data_ativacao, data_desativacao)
         VALUES (:usuario, :modalidade, :modelo, TRUE, CAST(:favorita AS boolean), :ordem, NULL, NOW(), NULL)
         ON CONFLICT (idusuario, idmodalidade) DO UPDATE SET idmodelo_ativo=EXCLUDED.idmodelo_ativo, ativo=TRUE, favorita=EXCLUDED.favorita, ordem_preferencia=EXCLUDED.ordem_preferencia, data_desativacao=NULL"
    );
    $stmt->execute([
        ':usuario' => $userId,
        ':modalidade' => $sport['idmodalidade'],
        ':modelo' => $sport['idmodelo'],
        ':favorita' => in_array($sport['slug'], ['corrida', 'musculacao', 'ciclismo', 'natacao'], true) ? 'true' : 'false',
        ':ordem' => $order,
    ]);
}

function demoActivitySpec(string $slug, int $occurrence, int $week): array
{
    $titles = [
        'corrida' => 'Corrida contínua',
        'musculacao' => 'Treino de força',
        'ciclismo' => 'Pedal de endurance',
        'natacao' => 'Sessão de natação',
        'powerlifting' => 'Treino de powerlifting',
        'triatlo' => 'Treino multiesporte',
        'atletismo-100m' => 'Treino de 100 m',
        'tenis' => 'Partida de tênis',
        'futebol' => 'Jogo de futebol',
        'karate' => 'Treino de karatê',
        'yoga' => 'Sessão de yoga',
        'escalada-indoor' => 'Sessão de escalada indoor',
        'tiro-com-arco' => 'Treino de tiro com arco',
        'esqui-alpino' => 'Sessão de esqui alpino',
        'forro' => 'Aula de forró',
        'hipismo-salto' => 'Treino de hipismo',
        'kart' => 'Sessão de kart',
        'lancamento-de-dardo' => 'Treino de lançamento de dardo',
        'trilha' => 'Trilha de fim de semana',
        'dardos' => 'Treino de dardos',
    ];
    $spec = [
        'title' => $titles[$slug] ?? ucfirst(str_replace('-', ' ', $slug)),
        'duration_min' => 45,
        'rpe' => 6,
        'notes' => 'Dado sintético da fixture de seis meses.',
    ];
    if ($slug === 'corrida') {
        $distance = 4.4 + $occurrence * 0.32 + (($occurrence % 3) - 1) * 0.15;
        $pace = max(330, 392 - $occurrence * 4.5 + (($occurrence % 4) - 1.5) * 4);
        $duration = $distance * $pace;
        return array_replace($spec, [
            'duration_s' => $duration,
            'distance_km' => round($distance, 2),
            'elevation_m' => 45 + ($occurrence % 5) * 18,
            'avg_hr' => 154 - min(9, intdiv($occurrence, 2)) + ($occurrence % 2) * 2,
            'max_hr' => 174 - min(6, intdiv($occurrence, 3)),
            'cadence' => 166 + min(8, intdiv($occurrence, 2)),
            'rpe' => 5 + ($occurrence % 4),
            'title' => $occurrence % 4 === 3 ? 'Corrida progressiva' : 'Corrida contínua',
        ]);
    }
    if ($slug === 'musculacao') {
        return array_replace($spec, [
            'duration_min' => 58 + ($occurrence % 4) * 4,
            'avg_hr' => 118 + ($occurrence % 4) * 3,
            'rpe' => 6 + ($occurrence % 3),
            'strength_index' => $occurrence,
        ]);
    }
    if ($slug === 'ciclismo') {
        $distance = 22 + $occurrence * 2.8;
        $speed = 22.5 + $occurrence * 0.65;
        $duration = ($distance / $speed) * 3600;
        return array_replace($spec, [
            'duration_s' => $duration,
            'distance_km' => round($distance, 1),
            'elevation_m' => 180 + $occurrence * 35,
            'avg_hr' => 142 + min(7, $occurrence),
            'max_hr' => 166 + min(8, $occurrence),
            'cadence' => 78 + min(10, $occurrence),
            'power' => 155 + $occurrence * 7,
            'rpe' => 5 + ($occurrence % 4),
        ]);
    }
    if ($slug === 'natacao') {
        $distance = 1.2 + $occurrence * 0.16;
        $pace100 = max(105, 132 - $occurrence * 3.1);
        $duration = ($distance * 1000 / 100) * $pace100;
        return array_replace($spec, [
            'duration_s' => $duration,
            'distance_km' => round($distance, 2),
            'avg_hr' => 136 + ($occurrence % 3) * 3,
            'max_hr' => 158 + ($occurrence % 3) * 2,
            'cadence' => 30 + min(6, $occurrence),
            'rpe' => 5 + ($occurrence % 4),
        ]);
    }
    if ($slug === 'atletismo-100m') {
        $seconds = max(12.62, 14.38 - $occurrence * 0.21);
        return array_replace($spec, [
            'duration_s' => $seconds,
            'distance_km' => 0.1,
            'avg_hr' => 168,
            'max_hr' => 186,
            'cadence' => 218 + $occurrence * 3,
            'rpe' => 9,
            'record' => ['vento' => round(-0.4 + ($occurrence % 5) * 0.55, 1), 'tempo-reacao' => round(0.205 - min(0.035, $occurrence * 0.006), 3)],
        ]);
    }
    if ($slug === 'lancamento-de-dardo') {
        $base = 28.0 + $occurrence * 1.45;
        $attempts = [];
        for ($i = 0; $i < 6; $i++) $attempts[] = ['marca' => round($base + [0.2, 1.1, -0.7, 2.0, 0.6, 1.5][$i], 2), 'tentativa-nula' => ($i === 2 && $occurrence % 2 === 0) ? 1 : 0];
        return array_replace($spec, ['duration_min' => 70, 'avg_hr' => 118, 'rpe' => 7, 'attempts' => $attempts]);
    }
    if ($slug === 'tenis') {
        $win = $occurrence % 3 !== 0;
        return array_replace($spec, [
            'duration_min' => 74 + ($occurrence % 4) * 9,
            'avg_hr' => 146,
            'max_hr' => 179,
            'rpe' => 7,
            'record' => ['tipo-sessao' => 'partida', 'formato-jogo' => $occurrence % 4 === 3 ? 'duplas' : 'simples', 'resultado' => $win ? 'vitoria' : 'derrota', 'adversario' => 'Adversário Demo ' . ($occurrence + 1), 'placar' => $win ? '6-4 6-3' : '4-6 6-3 4-6'],
        ]);
    }
    if ($slug === 'futebol') {
        $results = [['vitoria', 3, 1], ['empate', 2, 2], ['derrota', 1, 2]];
        [$result, $for, $against] = $results[$occurrence % count($results)];
        return array_replace($spec, [
            'duration_min' => 78 + ($occurrence % 3) * 6,
            'avg_hr' => 151,
            'max_hr' => 184,
            'rpe' => 7 + ($occurrence % 2),
            'record' => ['tipo-sessao' => 'jogo', 'resultado' => $result, 'adversario' => 'Equipe Demo ' . ($occurrence + 1), 'placar-favor' => $for, 'placar-contra' => $against, 'posicao' => $occurrence % 2 === 0 ? 'Lateral-direito' : 'Volante'],
        ]);
    }
    if ($slug === 'karate') {
        return array_replace($spec, [
            'duration_min' => 82,
            'avg_hr' => 141,
            'rpe' => 6 + ($occurrence % 3),
            'record' => ['tipo-sessao' => $occurrence % 3 === 2 ? 'sparring' : 'tecnica', 'rounds' => 5 + $occurrence],
        ]);
    }
    if ($slug === 'dardos') return array_replace($spec, ['duration_min' => 52, 'rpe' => 3, 'record' => ['pontuacao' => 46 + $occurrence * 3.5, 'rodadas' => 12 + $occurrence * 2]]);
    if ($slug === 'tiro-com-arco') return array_replace($spec, ['duration_min' => 66, 'rpe' => 4, 'record' => ['pontuacao' => 212 + $occurrence * 8, 'rodadas' => 10]]);
    if ($slug === 'trilha') return array_replace($spec, ['duration_min' => 125 + $occurrence * 8, 'distance_km' => 8.5 + $occurrence * 1.1, 'elevation_m' => 420 + $occurrence * 80, 'avg_hr' => 139, 'max_hr' => 171, 'rpe' => 6]);
    if ($slug === 'kart') return array_replace($spec, ['duration_min' => 30, 'distance_km' => 22 + $occurrence * 2, 'avg_hr' => 143, 'rpe' => 6]);
    if ($slug === 'esqui-alpino') return array_replace($spec, ['duration_min' => 110, 'distance_km' => 18.5, 'elevation_m' => 1600, 'avg_hr' => 137, 'max_hr' => 170, 'rpe' => 6]);
    if ($slug === 'triatlo') return array_replace($spec, ['duration_min' => 95, 'distance_km' => 26.0, 'avg_hr' => 148, 'max_hr' => 177, 'rpe' => 7]);
    if ($slug === 'powerlifting') return array_replace($spec, ['duration_min' => 80, 'avg_hr' => 121, 'rpe' => 8]);
    if ($slug === 'yoga') return array_replace($spec, ['duration_min' => 48, 'avg_hr' => 88, 'rpe' => 3]);
    if ($slug === 'escalada-indoor') return array_replace($spec, ['duration_min' => 92, 'avg_hr' => 128, 'rpe' => 7]);
    if ($slug === 'forro') return array_replace($spec, ['duration_min' => 75, 'avg_hr' => 126, 'rpe' => 5]);
    if ($slug === 'hipismo-salto') return array_replace($spec, ['duration_min' => 64, 'avg_hr' => 112, 'rpe' => 5]);
    return $spec;
}

function demoStrengthSets(int $index): array
{
    $bench = 42.5 + $index * 1.75;
    $squat = 60 + $index * 2.75;
    $row = 37.5 + $index * 1.5;
    return [
        ['nome' => 'Supino reto', 'series' => [
            ['tipo' => 'aquecimento', 'carga_kg' => max(20, $bench - 17.5), 'repeticoes' => 10, 'rpe' => 4],
            ['tipo' => 'trabalho', 'carga_kg' => $bench, 'repeticoes' => 8, 'rir' => max(1, 3 - intdiv($index, 6)), 'rpe' => min(9, 7 + $index / 10)],
            ['tipo' => 'trabalho', 'carga_kg' => $bench, 'repeticoes' => 8, 'rir' => 2, 'rpe' => 8],
            ['tipo' => 'trabalho', 'carga_kg' => max(20, $bench - 5), 'repeticoes' => 10, 'rir' => 2, 'rpe' => 8],
        ]],
        ['nome' => 'Agachamento livre', 'series' => [
            ['tipo' => 'aquecimento', 'carga_kg' => max(20, $squat - 30), 'repeticoes' => 8, 'rpe' => 4],
            ['tipo' => 'trabalho', 'carga_kg' => $squat, 'repeticoes' => 6, 'rir' => 2, 'rpe' => 8],
            ['tipo' => 'trabalho', 'carga_kg' => $squat, 'repeticoes' => 6, 'rir' => 2, 'rpe' => 8],
            ['tipo' => 'trabalho', 'carga_kg' => max(20, $squat - 7.5), 'repeticoes' => 8, 'rir' => 2, 'rpe' => 8],
        ]],
        ['nome' => 'Remada curvada', 'series' => [
            ['tipo' => 'trabalho', 'carga_kg' => $row, 'repeticoes' => 10, 'rir' => 2, 'rpe' => 7.5],
            ['tipo' => 'trabalho', 'carga_kg' => $row, 'repeticoes' => 10, 'rir' => 2, 'rpe' => 8],
            ['tipo' => 'trabalho', 'carga_kg' => $row, 'repeticoes' => 9, 'rir' => 1, 'rpe' => 8.5],
        ]],
    ];
}

function demoSaveActivity(PDO $pdo, string $userId, array $sport, DateTimeImmutable $start, array $spec): string
{
    [$recordValues, $unitValues] = demoDefaultFields($sport, $spec);
    $durationSeconds = isset($spec['duration_s']) ? (float) $spec['duration_s'] : ((float) ($spec['duration_min'] ?? 0) * 60.0);
    $units = [['rotulo' => 'Sessão', 'values' => $unitValues]];
    if (!empty($spec['attempts']) && is_array($spec['attempts'])) {
        $units = [];
        foreach ($spec['attempts'] as $index => $attempt) {
            $values = [];
            foreach ($attempt as $slug => $value) demoPutField($sport, $recordValues, $values, (string) $slug, $value);
            $units[] = ['rotulo' => 'Tentativa ' . ($index + 1), 'values' => $values];
        }
    }
    $end = $durationSeconds > 0 ? $start->modify('+' . max(60, (int) round($durationSeconds)) . ' seconds') : null;
    $payload = [
        'idmodelo' => (string) $sport['idmodelo'],
        'titulo' => (string) $spec['title'],
        'observacoes' => (string) ($spec['notes'] ?? ''),
        'data_inicio' => $start->format('Y-m-d H:i'),
        'data_fim' => $end ? $end->format('Y-m-d H:i') : '',
        'status' => 'concluido',
        'visibilidade' => 'privado',
        'origem' => 'manual',
        'esforco_percebido' => isset($spec['rpe']) ? (int) $spec['rpe'] : '',
        'record_values' => $recordValues,
        'unidades' => $units,
        'usa_trechos' => count($units) > 1,
        'permitir_campos_vazios' => true,
    ];
    $id = atividadeSalvarRegistro($pdo, $userId, $payload);
    if (isset($spec['strength_index'])) atividadeForcaPersistirSeriesManuais($pdo, $userId, $id, demoStrengthSets((int) $spec['strength_index']));
    return $id;
}

function demoSeedWeightHistory(PDO $pdo, string $userId, DateTimeImmutable $start): void
{
    if (!demoTableExists($pdo, 'historico_peso_usuario')) return;
    $stmt = $pdo->prepare('INSERT INTO historico_peso_usuario (idpesagem, idusuario, peso_kg, data_medicao, origem) VALUES (:id, :usuario, :peso, :data, :origem) ON CONFLICT (idusuario, data_medicao) DO UPDATE SET peso_kg=EXCLUDED.peso_kg, origem=EXCLUDED.origem');
    for ($month = 0; $month < 7; $month++) {
        $date = $start->modify('+' . $month . ' months')->modify('first day of this month');
        $stmt->execute([':id' => demoId('wp', $userId . ':' . $date->format('Y-m-d')), ':usuario' => $userId, ':peso' => round(78.4 - $month * 0.38, 2), ':data' => $date->format('Y-m-d'), ':origem' => 'fixture-demo']);
    }
}

function demoSeedGoals(PDO $pdo, string $userId, array $sports): void
{
    if (!demoTableExists($pdo, 'metas_usuario')) return;
    $goals = [
        ['corrida', 'distancia', 'mensal', 30, 'Correr 30 km no mês'],
        ['ciclismo', 'distancia', 'mensal', 90, 'Pedalar 90 km no mês'],
        [null, 'dias_ativos', 'mensal', 10, 'Treinar em 10 dias no mês'],
    ];
    $stmt = $pdo->prepare('INSERT INTO metas_usuario (idmeta, idusuario, idmodalidade, nome, metrica, periodo, valor_alvo, ativa, data_criacao, data_atualizacao) VALUES (:id, :usuario, :modalidade, :nome, :metrica, :periodo, :valor, TRUE, NOW(), NOW())');
    foreach ($goals as $index => [$slug, $metric, $period, $target, $name]) {
        $modalityId = $slug !== null && isset($sports[$slug]) ? (string) $sports[$slug]['idmodalidade'] : null;
        $stmt->execute([':id' => demoId('gm', $userId . ':' . $index), ':usuario' => $userId, ':modalidade' => $modalityId, ':nome' => $name, ':metrica' => $metric, ':periodo' => $period, ':valor' => $target]);
    }
}

$userId = demoArg($argv, 'user-id', 'demo_atleta_6m') ?? 'demo_atleta_6m';
$email = demoArg($argv, 'email', 'demo.atleta@stridebr.local') ?? 'demo.atleta@stridebr.local';
$username = demoArg($argv, 'username', 'demo_atleta_6m') ?? 'demo_atleta_6m';
$password = demoArg($argv, 'password', 'StrideBRDemo123!') ?? 'StrideBRDemo123!';
$anchorRaw = demoArg($argv, 'anchor', 'today') ?? 'today';
$reset = demoHasFlag($argv, 'reset');
$remove = demoHasFlag($argv, 'remove');
$allowProduction = demoHasFlag($argv, 'allow-production');
$appEnv = strtolower(trim((string) (getenv('STRIDEBR_APP_ENV') ?: 'development')));
if ($appEnv === 'production' && !$allowProduction) {
    fwrite(STDERR, "Esta fixture é para ambiente local. Em produção ela é bloqueada por padrão.\n");
    exit(3);
}

$tz = new DateTimeZone('America/Sao_Paulo');
$anchor = strtolower($anchorRaw) === 'today' ? new DateTimeImmutable('now', $tz) : new DateTimeImmutable($anchorRaw . ' 12:00:00', $tz);
$currentMonday = $anchor->modify('monday this week')->setTime(0, 0);
$startMonday = $currentMonday->modify('-26 weeks');

$existing = $pdo->prepare('SELECT idusuario FROM usuarios WHERE idusuario=:id OR lower(emailusuario)=lower(:email) LIMIT 1');
$existing->execute([':id' => $userId, ':email' => $email]);
$existingId = $existing->fetchColumn();

if ($remove) {
    if ($existingId) {
        $pdo->prepare('DELETE FROM usuarios WHERE idusuario=:id')->execute([':id' => $existingId]);
        echo "Fixture removida: {$existingId}\n";
    } else {
        echo "Nenhuma fixture encontrada.\n";
    }
    exit(0);
}

if ($existingId && !$reset) {
    fwrite(STDERR, "A fixture já existe. Rode novamente com --reset para recriar ou --remove para excluir.\n");
    exit(2);
}

$pdo->beginTransaction();
try {
    if ($existingId) $pdo->prepare('DELETE FROM usuarios WHERE idusuario=:id')->execute([':id' => $existingId]);
    demoCreateUser($pdo, $userId, $email, $username, $password, $startMonday);

    $slugs = [
        'corrida', 'musculacao', 'ciclismo', 'natacao', 'powerlifting', 'triatlo', 'atletismo-100m', 'lancamento-de-dardo',
        'tenis', 'futebol', 'karate', 'yoga', 'escalada-indoor', 'tiro-com-arco', 'esqui-alpino', 'forro', 'hipismo-salto', 'kart', 'trilha', 'dardos'
    ];
    $sports = [];
    foreach ($slugs as $order => $slug) {
        $sports[$slug] = demoSport($pdo, $slug);
        demoActivateSport($pdo, $userId, $sports[$slug], $order + 1);
    }

    $oddThursday = ['lancamento-de-dardo', 'tenis', 'futebol', 'karate', 'trilha', 'dardos', 'atletismo-100m', 'tenis', 'futebol', 'karate', 'lancamento-de-dardo', 'atletismo-100m', 'trilha'];
    $evenSaturday = ['powerlifting', 'triatlo', 'atletismo-100m', 'tenis', 'futebol', 'karate', 'yoga', 'escalada-indoor', 'tiro-com-arco', 'esqui-alpino', 'forro', 'hipismo-salto', 'kart'];
    $occurrences = [];
    $created = [];

    $create = static function (string $slug, DateTimeImmutable $date) use (&$occurrences, &$created, $sports, $pdo, $userId): void {
        $index = $occurrences[$slug] ?? 0;
        $spec = demoActivitySpec($slug, $index, 0);
        $id = demoSaveActivity($pdo, $userId, $sports[$slug], $date, $spec);
        $occurrences[$slug] = $index + 1;
        $created[] = ['date' => $date->format('Y-m-d H:i'), 'sport' => $slug, 'title' => $spec['title'], 'id' => $id];
    };

    for ($week = 0; $week < 26; $week++) {
        $monday = $startMonday->modify('+' . $week . ' weeks');
        if ($week % 2 === 0) {
            $create('corrida', $monday->modify('+1 day')->setTime(18, 10));
            $create('musculacao', $monday->modify('+3 days')->setTime(7, 15));
            $create($evenSaturday[intdiv($week, 2)], $monday->modify('+5 days')->setTime(9, 0));
        } else {
            $cardioSlug = intdiv($week, 2) % 2 === 0 ? 'ciclismo' : 'natacao';
            $create($cardioSlug, $monday->modify('+1 day')->setTime(18, 20));
            $create($oddThursday[intdiv($week, 2)], $monday->modify('+3 days')->setTime(19, 0));
        }
    }

    demoSeedWeightHistory($pdo, $userId, $startMonday);
    demoSeedGoals($pdo, $userId, $sports);

    $pdo->commit();

    echo "Fixture criada com sucesso.\n";
    echo "Usuário: {$email}\n";
    echo "Username: {$username}\n";
    echo "Senha: {$password}\n";
    echo "Período: {$startMonday->format('d/m/Y')} até " . $startMonday->modify('+25 weeks +5 days')->format('d/m/Y') . "\n";
    echo 'Atividades: ' . count($created) . "\n";
    echo 'Modalidades ativas: ' . count($sports) . "\n\n";
    foreach ($occurrences as $slug => $count) echo str_pad($slug, 24) . " {$count}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Falha ao criar fixture: {$e->getMessage()}\n");
    exit(1);
}
