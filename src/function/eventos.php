<?php

declare(strict_types=1);

function eventosDisponiveis(PDO $pdo): bool
{
    static $available = null;
    if ($available !== null) return $available;
    try {
        $available = stridebr_db_bool($pdo->query("SELECT to_regclass('stridebr.eventos_esportivos') IS NOT NULL")->fetchColumn());
    } catch (Throwable) {
        $available = false;
    }
    return $available;
}

function eventosListarModalidades(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT idmodalidade, nome, slug, categoria, familia_hub FROM modalidades WHERE ativo = TRUE AND idusuario IS NULL ORDER BY categoria, ordem_catalogo, nome");
    return $stmt->fetchAll();
}

function eventosUrl(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    if (strlen($value) > 2000 || filter_var($value, FILTER_VALIDATE_URL) === false || !in_array(stridebr_lower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)) {
        throw new InvalidArgumentException('Use URLs completas começando com https:// ou http://.');
    }
    return $value;
}

function eventosDataHora(string $value, string $label, bool $required = true): ?string
{
    $value = trim($value);
    if ($value === '') {
        if ($required) throw new InvalidArgumentException('Informe ' . $label . '.');
        return null;
    }
    $tz = new DateTimeZone('America/Sao_Paulo');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $tz);
    if (!$date || $date->format('Y-m-d\TH:i') !== $value) throw new InvalidArgumentException('Informe ' . $label . ' válida.');
    return $date->format('Y-m-d H:i:sP');
}

function eventosDistancias(string $raw): array
{
    $parts = preg_split('/[\r\n,;]+/u', $raw) ?: [];
    $result = [];
    foreach ($parts as $part) {
        $value = trim($part);
        if ($value === '') continue;
        if (stridebr_length($value) > 40) throw new InvalidArgumentException('Cada distância deve ter no máximo 40 caracteres.');
        $result[] = $value;
        if (count($result) > 20) throw new InvalidArgumentException('Informe no máximo 20 opções de distância.');
    }
    return array_values(array_unique($result));
}

function eventosSlugUnico(PDO $pdo, string $title, ?string $eventId = null): string
{
    $base = stridebr_slug($title);
    if ($base === '') $base = 'evento';
    $base = substr($base, 0, 170);
    $slug = $base;
    for ($i = 1; $i <= 999; $i++) {
        if ($eventId === null) {
            $stmt = $pdo->prepare('SELECT 1 FROM eventos_esportivos WHERE lower(slug) = lower(:slug) LIMIT 1');
            $stmt->execute([':slug' => $slug]);
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM eventos_esportivos WHERE lower(slug) = lower(:slug) AND idevento <> :evento LIMIT 1');
            $stmt->execute([':slug' => $slug, ':evento' => $eventId]);
        }
        if (!$stmt->fetchColumn()) return $slug;
        $slug = $base . '-' . ($i + 1);
    }
    return $base . '-' . substr(stridebr_generate_id(8), 0, 8);
}

function eventosValidarPayload(PDO $pdo, array $payload, ?string $eventId = null): array
{
    $title = trim((string) ($payload['titulo'] ?? ''));
    if (stridebr_length($title) < 3 || stridebr_length($title) > 160) throw new InvalidArgumentException('O título deve ter entre 3 e 160 caracteres.');
    $description = trim((string) ($payload['descricao'] ?? '')) ?: null;
    if ($description !== null && stridebr_length($description) > 12000) throw new InvalidArgumentException('A descrição deve ter no máximo 12.000 caracteres.');
    $type = trim((string) ($payload['tipo'] ?? '')) ?: null;
    if ($type !== null && stridebr_length($type) > 40) throw new InvalidArgumentException('O tipo do evento é muito longo.');
    $city = trim((string) ($payload['cidade'] ?? '')) ?: null;
    $state = trim((string) ($payload['estado'] ?? '')) ?: null;
    $country = trim((string) ($payload['pais'] ?? 'Brasil')) ?: 'Brasil';
    $local = trim((string) ($payload['local_nome'] ?? '')) ?: null;
    $address = trim((string) ($payload['endereco'] ?? '')) ?: null;
    $organizer = trim((string) ($payload['organizador'] ?? '')) ?: null;
    foreach ([['cidade', $city, 100], ['estado', $state, 80], ['país', $country, 80], ['local', $local, 160], ['organizador', $organizer, 160]] as [$label, $value, $max]) {
        if ($value !== null && stridebr_length($value) > $max) throw new InvalidArgumentException('O campo ' . $label . ' é muito longo.');
    }
    if ($address !== null && stridebr_length($address) > 1200) throw new InvalidArgumentException('O endereço é muito longo.');

    $start = eventosDataHora((string) ($payload['data_inicio'] ?? ''), 'a data e hora de início');
    $end = eventosDataHora((string) ($payload['data_fim'] ?? ''), 'a data e hora de término', false);
    $deadline = eventosDataHora((string) ($payload['inscricoes_ate'] ?? ''), 'o prazo de inscrição', false);
    if ($end !== null && strtotime($end) < strtotime((string) $start)) throw new InvalidArgumentException('O término não pode vir antes do início.');

    $sportId = trim((string) ($payload['idmodalidade'] ?? '')) ?: null;
    if ($sportId !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM modalidades WHERE idmodalidade = :id AND ativo = TRUE AND idusuario IS NULL LIMIT 1');
        $stmt->execute([':id' => $sportId]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Modalidade inválida.');
    }

    $status = trim((string) ($payload['status'] ?? 'rascunho'));
    if (!in_array($status, ['rascunho', 'publicado', 'cancelado', 'encerrado'], true)) throw new InvalidArgumentException('Status inválido.');

    return [
        'titulo' => $title,
        'slug' => eventosSlugUnico($pdo, $title, $eventId),
        'idmodalidade' => $sportId,
        'tipo' => $type,
        'descricao' => $description,
        'data_inicio' => $start,
        'data_fim' => $end,
        'cidade' => $city,
        'estado' => $state,
        'pais' => $country,
        'local_nome' => $local,
        'endereco' => $address,
        'organizador' => $organizer,
        'distancias' => eventosDistancias((string) ($payload['distancias'] ?? '')),
        'url_oficial' => eventosUrl($payload['url_oficial'] ?? null),
        'url_inscricao' => eventosUrl($payload['url_inscricao'] ?? null),
        'inscricoes_ate' => $deadline,
        'status' => $status,
        'destaque' => isset($payload['destaque']),
    ];
}

function eventosSalvar(PDO $pdo, string $actorId, array $payload, ?string $eventId = null): string
{
    $data = eventosValidarPayload($pdo, $payload, $eventId);
    if ($eventId === null) {
        $eventId = stridebr_generate_id();
        $stmt = $pdo->prepare(
            'INSERT INTO eventos_esportivos (idevento, titulo, slug, idmodalidade, tipo, descricao, data_inicio, data_fim, cidade, estado, pais, local_nome, endereco, organizador, distancias, url_oficial, url_inscricao, inscricoes_ate, status, destaque, criado_por)
             VALUES (:id, :titulo, :slug, :modalidade, :tipo, :descricao, :inicio, :fim, :cidade, :estado, :pais, :local, :endereco, :organizador, CAST(:distancias AS jsonb), :oficial, :inscricao, :limite, :status, :destaque, :actor)'
        );
        $stmt->execute([
            ':id' => $eventId, ':actor' => $actorId,
            ':titulo' => $data['titulo'], ':slug' => $data['slug'], ':modalidade' => $data['idmodalidade'], ':tipo' => $data['tipo'], ':descricao' => $data['descricao'],
            ':inicio' => $data['data_inicio'], ':fim' => $data['data_fim'], ':cidade' => $data['cidade'], ':estado' => $data['estado'], ':pais' => $data['pais'], ':local' => $data['local_nome'], ':endereco' => $data['endereco'], ':organizador' => $data['organizador'],
            ':distancias' => json_encode($data['distancias'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':oficial' => $data['url_oficial'], ':inscricao' => $data['url_inscricao'], ':limite' => $data['inscricoes_ate'], ':status' => $data['status'], ':destaque' => $data['destaque'],
        ]);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE eventos_esportivos SET titulo=:titulo, slug=:slug, idmodalidade=:modalidade, tipo=:tipo, descricao=:descricao, data_inicio=:inicio, data_fim=:fim, cidade=:cidade, estado=:estado, pais=:pais, local_nome=:local, endereco=:endereco, organizador=:organizador, distancias=CAST(:distancias AS jsonb), url_oficial=:oficial, url_inscricao=:inscricao, inscricoes_ate=:limite, status=:status, destaque=:destaque, data_atualizacao=NOW() WHERE idevento=:id'
        );
        $stmt->execute([
            ':id' => $eventId, ':titulo' => $data['titulo'], ':slug' => $data['slug'], ':modalidade' => $data['idmodalidade'], ':tipo' => $data['tipo'], ':descricao' => $data['descricao'],
            ':inicio' => $data['data_inicio'], ':fim' => $data['data_fim'], ':cidade' => $data['cidade'], ':estado' => $data['estado'], ':pais' => $data['pais'], ':local' => $data['local_nome'], ':endereco' => $data['endereco'], ':organizador' => $data['organizador'],
            ':distancias' => json_encode($data['distancias'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':oficial' => $data['url_oficial'], ':inscricao' => $data['url_inscricao'], ':limite' => $data['inscricoes_ate'], ':status' => $data['status'], ':destaque' => $data['destaque'],
        ]);
        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT 1 FROM eventos_esportivos WHERE idevento = :id');
            $check->execute([':id' => $eventId]);
            if (!$check->fetchColumn()) throw new InvalidArgumentException('Evento não encontrado.');
        }
    }
    return $eventId;
}

function eventosSalvarFontes(PDO $pdo, string $eventId, array $names, array $urls): void
{
    $items = [];
    $max = min(10, max(count($names), count($urls)));
    for ($i = 0; $i < $max; $i++) {
        $name = trim((string) ($names[$i] ?? ''));
        $urlRaw = trim((string) ($urls[$i] ?? ''));
        if ($name === '' && $urlRaw === '') continue;
        if ($name === '' || $urlRaw === '') throw new InvalidArgumentException('Preencha nome e URL de cada fonte.');
        if (stridebr_length($name) > 120) throw new InvalidArgumentException('O nome de uma fonte é muito longo.');
        $items[] = ['nome' => $name, 'url' => eventosUrl($urlRaw)];
    }
    $pdo->prepare('DELETE FROM eventos_fontes WHERE idevento = :evento')->execute([':evento' => $eventId]);
    $insert = $pdo->prepare('INSERT INTO eventos_fontes (idfonte, idevento, nome, url, ordem) VALUES (:id, :evento, :nome, :url, :ordem)');
    foreach ($items as $index => $item) {
        $insert->execute([':id' => stridebr_generate_id(), ':evento' => $eventId, ':nome' => $item['nome'], ':url' => $item['url'], ':ordem' => $index + 1]);
    }
}

function eventosBuscarAdmin(PDO $pdo, string $eventId): ?array
{
    $stmt = $pdo->prepare('SELECT e.*, m.nome AS modalidade_nome, m.slug AS modalidade_slug FROM eventos_esportivos e LEFT JOIN modalidades m ON m.idmodalidade=e.idmodalidade WHERE e.idevento=:id LIMIT 1');
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch();
    if (!$event) return null;
    $event['fontes'] = eventosFontes($pdo, $eventId);
    $event['imagens'] = eventosImagens($pdo, $eventId);
    return $event;
}

function eventosFontes(PDO $pdo, string $eventId): array
{
    $stmt = $pdo->prepare('SELECT idfonte, nome, url, ordem FROM eventos_fontes WHERE idevento=:evento ORDER BY ordem, idfonte');
    $stmt->execute([':evento' => $eventId]);
    return $stmt->fetchAll();
}

function eventosImagens(PDO $pdo, string $eventId): array
{
    $stmt = $pdo->prepare('SELECT idimagem, caminho, texto_alternativo, principal, ordem FROM eventos_imagens WHERE idevento=:evento ORDER BY principal DESC, ordem, idimagem');
    $stmt->execute([':evento' => $eventId]);
    return $stmt->fetchAll();
}

function eventosListarPublicados(PDO $pdo, array $filters = [], ?string $userId = null, int $limit = 60): array
{
    if (!eventosDisponiveis($pdo)) return [];
    $where = ["e.status IN ('publicado','cancelado')"];
    $params = [];
    $q = trim((string) ($filters['q'] ?? ''));
    $sport = trim((string) ($filters['modalidade'] ?? ''));
    $state = trim((string) ($filters['estado'] ?? ''));
    $month = trim((string) ($filters['mes'] ?? ''));
    $saved = !empty($filters['salvos']) && $userId !== null;
    if ($q !== '') {
        $where[] = "(e.titulo ILIKE :q OR COALESCE(e.cidade,'') ILIKE :q OR COALESCE(e.organizador,'') ILIKE :q OR COALESCE(e.tipo,'') ILIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }
    if ($sport !== '') { $where[] = 'e.idmodalidade = :modalidade'; $params[':modalidade'] = $sport; }
    if ($state !== '') { $where[] = 'lower(e.estado) = lower(:estado)'; $params[':estado'] = $state; }
    if (preg_match('/^\d{4}-\d{2}$/', $month)) {
        $start = new DateTimeImmutable($month . '-01 00:00:00', new DateTimeZone('America/Sao_Paulo'));
        $end = $start->modify('+1 month');
        $where[] = 'e.data_inicio >= :mes_inicio AND e.data_inicio < :mes_fim';
        $params[':mes_inicio'] = $start->format('Y-m-d H:i:sP');
        $params[':mes_fim'] = $end->format('Y-m-d H:i:sP');
    } else {
        $where[] = "e.data_inicio >= NOW() - INTERVAL '1 day'";
    }
    if ($saved) $where[] = 'EXISTS (SELECT 1 FROM eventos_salvos es WHERE es.idevento=e.idevento AND es.idusuario=:saved_user)';
    if ($saved) $params[':saved_user'] = $userId;

    $sql = "SELECT e.*, m.nome AS modalidade_nome, m.slug AS modalidade_slug,
                   (SELECT caminho FROM eventos_imagens i WHERE i.idevento=e.idevento ORDER BY i.principal DESC, i.ordem, i.idimagem LIMIT 1) AS imagem_principal";
    if ($userId !== null) {
        $sql .= ', EXISTS (SELECT 1 FROM eventos_salvos s WHERE s.idevento=e.idevento AND s.idusuario=:viewer) AS salvo';
        $params[':viewer'] = $userId;
    } else {
        $sql .= ', FALSE AS salvo';
    }
    $sql .= ' FROM eventos_esportivos e LEFT JOIN modalidades m ON m.idmodalidade=e.idmodalidade WHERE ' . implode(' AND ', $where) . ' ORDER BY e.destaque DESC, e.data_inicio ASC LIMIT :limite';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) $stmt->bindValue($key, $value);
    $stmt->bindValue(':limite', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function eventosBuscarPublico(PDO $pdo, string $identifier, ?string $userId = null): ?array
{
    if (!eventosDisponiveis($pdo)) return null;
    $sql = "SELECT e.*, m.nome AS modalidade_nome, m.slug AS modalidade_slug";
    if ($userId !== null) $sql .= ', EXISTS (SELECT 1 FROM eventos_salvos s WHERE s.idevento=e.idevento AND s.idusuario=:viewer) AS salvo';
    else $sql .= ', FALSE AS salvo';
    $sql .= " FROM eventos_esportivos e LEFT JOIN modalidades m ON m.idmodalidade=e.idmodalidade WHERE (e.idevento=:identifier OR lower(e.slug)=lower(:identifier)) AND e.status IN ('publicado','cancelado') LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $params = [':identifier' => $identifier];
    if ($userId !== null) $params[':viewer'] = $userId;
    $stmt->execute($params);
    $event = $stmt->fetch();
    if (!$event) return null;
    $event['fontes'] = eventosFontes($pdo, (string) $event['idevento']);
    $event['imagens'] = eventosImagens($pdo, (string) $event['idevento']);
    return $event;
}

function eventosAlternarSalvo(PDO $pdo, string $userId, string $eventId): bool
{
    $check = $pdo->prepare("SELECT 1 FROM eventos_esportivos WHERE idevento=:evento AND status IN ('publicado','cancelado') LIMIT 1");
    $check->execute([':evento' => $eventId]);
    if (!$check->fetchColumn()) throw new InvalidArgumentException('Evento não encontrado.');
    $exists = $pdo->prepare('SELECT 1 FROM eventos_salvos WHERE idusuario=:usuario AND idevento=:evento LIMIT 1');
    $exists->execute([':usuario' => $userId, ':evento' => $eventId]);
    if ($exists->fetchColumn()) {
        $pdo->prepare('DELETE FROM eventos_salvos WHERE idusuario=:usuario AND idevento=:evento')->execute([':usuario' => $userId, ':evento' => $eventId]);
        return false;
    }
    $pdo->prepare('INSERT INTO eventos_salvos (idusuario, idevento) VALUES (:usuario, :evento) ON CONFLICT DO NOTHING')->execute([':usuario' => $userId, ':evento' => $eventId]);
    return true;
}

function eventosImportacaoData(mixed $value, array &$warnings): string
{
    $raw = trim((string) $value);
    if ($raw === '') return '';
    $timezone = new DateTimeZone('America/Sao_Paulo');
    $formats = ['Y-m-d\TH:i', 'Y-m-d H:i', 'd/m/Y H:i', 'd/m/Y H:i:s', 'Y-m-d\TH:i:sP', DateTimeInterface::ATOM];
    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $raw, $timezone);
        if ($date instanceof DateTimeImmutable) return $date->setTimezone($timezone)->format('Y-m-d\TH:i');
    }
    foreach (['Y-m-d', 'd/m/Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $raw, $timezone);
        if ($date instanceof DateTimeImmutable && $date->format($format) === $raw) {
            $warnings[] = 'Horário ausente; revise antes de publicar.';
            return $date->format('Y-m-d\T00:00');
        }
    }
    try {
        return (new DateTimeImmutable($raw, $timezone))->setTimezone($timezone)->format('Y-m-d\TH:i');
    } catch (Throwable) {
        return $raw;
    }
}

function eventosImportacaoValor(array $row, array $aliases, mixed $default = ''): mixed
{
    $normalized = [];
    foreach ($row as $key => $value) $normalized[stridebr_lower(trim((string) $key))] = $value;
    foreach ($aliases as $alias) {
        $key = stridebr_lower($alias);
        if (array_key_exists($key, $normalized) && $normalized[$key] !== null && $normalized[$key] !== '') return $normalized[$key];
    }
    return $default;
}

function eventosImportacaoResolverModalidade(array $modalidades, mixed $raw, array &$warnings): ?string
{
    $value = stridebr_lower(trim((string) $raw));
    if ($value === '') return null;
    foreach ($modalidades as $modalidade) {
        if ($value === stridebr_lower((string) $modalidade['idmodalidade']) || $value === stridebr_lower((string) $modalidade['slug']) || $value === stridebr_lower((string) $modalidade['nome'])) {
            return (string) $modalidade['idmodalidade'];
        }
    }
    $warnings[] = 'Modalidade não reconhecida; importe sem modalidade e revise depois.';
    return null;
}

function eventosImportacaoNormalizarLinha(PDO $pdo, array $row, array $modalidades): array
{
    $warnings = [];
    $distancesRaw = eventosImportacaoValor($row, ['distancias', 'distances'], '');
    if (is_array($distancesRaw)) $distancesRaw = implode(', ', array_map('strval', $distancesRaw));
    $payload = [
        'titulo' => trim((string) eventosImportacaoValor($row, ['titulo', 'title', 'nome', 'name'])),
        'idmodalidade' => eventosImportacaoResolverModalidade($modalidades, eventosImportacaoValor($row, ['idmodalidade', 'modalidade', 'esporte', 'sport']), $warnings),
        'tipo' => trim((string) eventosImportacaoValor($row, ['tipo', 'type'])),
        'descricao' => trim((string) eventosImportacaoValor($row, ['descricao', 'description'])),
        'data_inicio' => eventosImportacaoData(eventosImportacaoValor($row, ['data_inicio', 'inicio', 'data', 'start', 'start_date']), $warnings),
        'data_fim' => eventosImportacaoData(eventosImportacaoValor($row, ['data_fim', 'fim', 'end', 'end_date']), $warnings),
        'cidade' => trim((string) eventosImportacaoValor($row, ['cidade', 'city'])),
        'estado' => trim((string) eventosImportacaoValor($row, ['estado', 'uf', 'state'])),
        'pais' => trim((string) eventosImportacaoValor($row, ['pais', 'country'], 'Brasil')) ?: 'Brasil',
        'local_nome' => trim((string) eventosImportacaoValor($row, ['local_nome', 'local', 'venue'])),
        'endereco' => trim((string) eventosImportacaoValor($row, ['endereco', 'address'])),
        'organizador' => trim((string) eventosImportacaoValor($row, ['organizador', 'organizer'])),
        'distancias' => (string) $distancesRaw,
        'url_oficial' => trim((string) eventosImportacaoValor($row, ['url_oficial', 'site', 'url', 'official_url'])),
        'url_inscricao' => trim((string) eventosImportacaoValor($row, ['url_inscricao', 'inscricao', 'registration_url'])),
        'inscricoes_ate' => eventosImportacaoData(eventosImportacaoValor($row, ['inscricoes_ate', 'prazo_inscricao', 'registration_deadline']), $warnings),
        'status' => 'rascunho',
    ];
    $featured = eventosImportacaoValor($row, ['destaque', 'featured'], false);
    if (stridebr_db_bool($featured)) $payload['destaque'] = '1';
    $sourceName = trim((string) eventosImportacaoValor($row, ['fonte_nome', 'source_name'], 'Importação')) ?: 'Importação';
    $sourceUrl = trim((string) eventosImportacaoValor($row, ['fonte_url', 'source_url', 'source']));
    $errors = [];
    $validated = null;
    try {
        $validated = eventosValidarPayload($pdo, $payload);
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível validar este evento.';
    }
    $duplicate = false;
    if ($validated !== null) {
        $date = substr((string) $validated['data_inicio'], 0, 10);
        $dup = $pdo->prepare("SELECT 1 FROM eventos_esportivos WHERE lower(titulo) = lower(:titulo) AND data_inicio::date = CAST(:data AS date) AND lower(COALESCE(cidade,'')) = lower(:cidade) LIMIT 1");
        $dup->execute([':titulo' => $validated['titulo'], ':data' => $date, ':cidade' => (string) ($validated['cidade'] ?? '')]);
        $duplicate = (bool) $dup->fetchColumn();
        if ($duplicate) $warnings[] = 'Possível duplicado: mesmo título, data e cidade.';
    }
    return [
        'payload' => $payload,
        'source_name' => $sourceName,
        'source_url' => $sourceUrl,
        'warnings' => array_values(array_unique($warnings)),
        'errors' => $errors,
        'duplicate' => $duplicate,
        'valid' => $validated !== null && !$duplicate,
    ];
}

function eventosImportacaoLer(string $raw, string $format, PDO $pdo, array $modalidades): array
{
    if (strlen($raw) > 1024 * 1024) throw new InvalidArgumentException('O arquivo de importação deve ter no máximo 1 MB.');
    $raw = trim($raw);
    if ($raw === '') throw new InvalidArgumentException('Cole dados ou selecione um arquivo JSON/CSV.');
    $format = stridebr_lower(trim($format));
    $rows = [];
    if ($format === 'json' || str_starts_with($raw, '[') || str_starts_with($raw, '{')) {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (isset($decoded['eventos']) && is_array($decoded['eventos'])) $decoded = $decoded['eventos'];
        if (!is_array($decoded)) throw new InvalidArgumentException('O JSON precisa conter uma lista de eventos.');
        if ($decoded !== [] && array_is_list($decoded) === false) $decoded = [$decoded];
        $rows = $decoded;
    } else {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $first = (string) ($lines[0] ?? '');
        $delimiters = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($delimiters);
        $delimiter = (string) array_key_first($delimiters);
        if (($delimiters[$delimiter] ?? 0) < 1) throw new InvalidArgumentException('Não foi possível detectar as colunas do CSV.');
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $raw);
        rewind($stream);
        $headers = fgetcsv($stream, 0, $delimiter);
        if (!is_array($headers)) throw new InvalidArgumentException('CSV sem cabeçalho válido.');
        $headers = array_map(static fn($value): string => trim((string) $value), $headers);
        while (($values = fgetcsv($stream, 0, $delimiter)) !== false) {
            if ($values === [null] || $values === []) continue;
            $values = array_pad($values, count($headers), '');
            $rows[] = array_combine($headers, array_slice($values, 0, count($headers))) ?: [];
            if (count($rows) > 200) break;
        }
        fclose($stream);
    }
    if (count($rows) > 200) throw new InvalidArgumentException('Importe no máximo 200 eventos por vez.');
    $result = [];
    $seen = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            $result[] = ['payload' => [], 'source_name' => '', 'source_url' => '', 'warnings' => [], 'errors' => ['Linha inválida.'], 'duplicate' => false, 'valid' => false];
            continue;
        }
        $normalized = eventosImportacaoNormalizarLinha($pdo, $row, $modalidades);
        $payload = (array) ($normalized['payload'] ?? []);
        $key = stridebr_lower(trim((string) ($payload['titulo'] ?? ''))) . '|' . substr((string) ($payload['data_inicio'] ?? ''), 0, 10) . '|' . stridebr_lower(trim((string) ($payload['cidade'] ?? '')));
        if ($key !== '||' && isset($seen[$key])) {
            $normalized['duplicate'] = true;
            $normalized['valid'] = false;
            $normalized['warnings'][] = 'Duplicado dentro desta própria importação.';
            $normalized['warnings'] = array_values(array_unique($normalized['warnings']));
        } else {
            $seen[$key] = true;
        }
        $result[] = $normalized;
    }
    return $result;
}
