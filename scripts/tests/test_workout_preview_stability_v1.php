<?php

declare(strict_types=1);

return function (PDO $pdo): void {
    $root = dirname(__DIR__, 2);
    $userId = alphaTestUser($pdo, 'preview_stability_guard');
    $pdo->prepare('UPDATE usuarios SET termos_versao=:terms, privacidade_versao=:privacy WHERE idusuario=:id')->execute([
        ':terms' => stridebr_terms_version(),
        ':privacy' => stridebr_privacy_version(),
        ':id' => $userId,
    ]);
    $versionStmt = $pdo->prepare('SELECT sessao_versao FROM usuarios WHERE idusuario=:id');
    $versionStmt->execute([':id' => $userId]);
    $sessionVersion = (int) $versionStmt->fetchColumn();
    $scheduleId = cronogramaCriar($pdo, $userId, 'Alpha preview stability');
    $workoutId = cronogramaSalvarTreino($pdo, $userId, [
        'idcronograma' => $scheduleId,
        'titulo' => 'Preview guard workout',
        'dia_semana' => 1,
        'hora_inicio' => '08:00',
        'hora_fim' => '09:00',
        'vigencia_inicio' => '2026-09-01',
    ]);

    $sessionDir = sys_get_temp_dir() . '/stridebr-preview-session-' . bin2hex(random_bytes(5));
    if (!mkdir($sessionDir, 0700, true) && !is_dir($sessionDir)) throw new RuntimeException('Unable to create preview session directory');
    $sessionId = 'alphapreview' . substr(sha1($userId . microtime(true)), 0, 20);

    $runPhp = static function (array $args, string $cwd = null): array {
        $stdout = tempnam(sys_get_temp_dir(), 'stridebr-preview-out-');
        $stderr = tempnam(sys_get_temp_dir(), 'stridebr-preview-err-');
        $proc = proc_open($args, [0 => ['pipe', 'r'], 1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']], $pipes, $cwd);
        if (!is_resource($proc)) throw new RuntimeException('Unable to spawn PHP session helper');
        fclose($pipes[0]);
        $code = proc_close($proc);
        $out = (string) @file_get_contents($stdout);
        $err = (string) @file_get_contents($stderr);
        @unlink($stdout); @unlink($stderr);
        return [$code, $out, $err];
    };

    $writeSession = static function (array $values) use ($runPhp, $sessionDir, $sessionId): void {
        $code = 'session_id(' . var_export($sessionId, true) . '); session_start(); $_SESSION=' . var_export($values, true) . '; session_write_close();';
        [$status,, $err] = $runPhp([PHP_BINARY, '-d', 'session.use_strict_mode=0', '-d', 'session.save_path=' . $sessionDir, '-r', $code]);
        if ($status !== 0) throw new RuntimeException('Unable to seed preview session: ' . trim($err));
    };
    $readSession = static function () use ($runPhp, $sessionDir, $sessionId): array {
        $code = 'session_id(' . var_export($sessionId, true) . '); session_start(); echo json_encode($_SESSION); session_write_close();';
        [$status, $out, $err] = $runPhp([PHP_BINARY, '-d', 'session.use_strict_mode=0', '-d', 'session.save_path=' . $sessionDir, '-r', $code]);
        if ($status !== 0) throw new RuntimeException('Unable to read preview session: ' . trim($err));
        $data = json_decode($out, true);
        return is_array($data) ? $data : [];
    };
    $writeSession([
        'IdUsuario' => $userId,
        'SessaoVersao' => $sessionVersion,
        'SessionGuardCheckedAt' => 0,
        'LegalPending' => false,
    ]);

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if (!$socket) throw new RuntimeException('Unable to reserve HTTP test port: ' . $errstr);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr((string) $address, strrpos((string) $address, ':') + 1);
    $serverOut = tempnam(sys_get_temp_dir(), 'stridebr-preview-server-out-');
    $serverErr = tempnam(sys_get_temp_dir(), 'stridebr-preview-server-err-');
    $previousTtl = getenv('STRIDEBR_SESSION_GUARD_TTL');
    putenv('STRIDEBR_SESSION_GUARD_TTL=0');
    $server = proc_open(
        [PHP_BINARY, '-d', 'session.save_path=' . $sessionDir, '-S', '127.0.0.1:' . $port, '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', $serverOut, 'w'], 2 => ['file', $serverErr, 'w']],
        $serverPipes,
        $root
    );
    if (!is_resource($server)) throw new RuntimeException('Unable to start preview HTTP test server');
    fclose($serverPipes[0]);

    $request = static function (string $path) use ($port, $sessionId): array {
        $headers = [
            'Accept: application/json',
            'Cookie: PHPSESSID=' . $sessionId,
            'Connection: close',
        ];
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 8,
        ]]);
        $body = @file_get_contents('http://127.0.0.1:' . $port . $path, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = 0;
        if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $match)) $status = (int) $match[1];
        $setCookies = array_values(array_filter($responseHeaders, static fn(string $line): bool => stripos($line, 'Set-Cookie:') === 0));
        return [$status, $body === false ? '' : $body, $responseHeaders, $setCookies];
    };

    try {
        $ready = false;
        for ($attempt = 0; $attempt < 40; $attempt++) {
            $fp = @fsockopen('127.0.0.1', $port, $e, $m, 0.1);
            if ($fp) { fclose($fp); $ready = true; break; }
            usleep(50000);
        }
        if (!$ready) throw new RuntimeException('Preview HTTP test server did not start: ' . trim((string) @file_get_contents($serverErr)));

        [$previewStatus, $previewBody,, $previewCookies] = $request('/api/cronograma-treino-preview.php?idtreino=' . rawurlencode($workoutId));
        AlphaTest::same(200, $previewStatus, 'Guard-due preview request did not remain authenticated');
        $previewJson = json_decode($previewBody, true);
        AlphaTest::assert(is_array($previewJson) && ($previewJson['ok'] ?? false) === true, 'Guard-due preview did not return semantic success');
        $deletedCookie = array_filter($previewCookies, static fn(string $line): bool => preg_match('/Max-Age=0|Expires=Thu, 01 Jan 1970|deleted/i', $line) === 1);
        AlphaTest::same([], array_values($deletedCookie), 'Valid preview request deleted the PHP session cookie');

        $storedSession = $readSession();
        AlphaTest::assert((int) ($storedSession['SessionGuardCheckedAt'] ?? 0) > 0, 'Session Guard timestamp was not persisted before session release');
        AlphaTest::same($userId, (string) ($storedSession['IdUsuario'] ?? ''), 'Preview request lost authenticated user session state');

        $hash = hash('sha256', $sessionId);
        $tracking = $pdo->prepare('SELECT idusuario, revogado_em FROM sessoes_usuario WHERE sessao_hash=:hash');
        $tracking->execute([':hash' => $hash]);
        $trackingRow = $tracking->fetch();
        AlphaTest::assert(is_array($trackingRow) && (string) $trackingRow['idusuario'] === $userId && $trackingRow['revogado_em'] === null, 'Guard-due preview revoked valid session tracking');

        [$nextStatus, $nextBody] = $request('/api/exercicio-resolver.php?name=ab');
        AlphaTest::same(200, $nextStatus, 'Authenticated request after preview no longer had a valid session');
        $nextJson = json_decode($nextBody, true);
        AlphaTest::assert(is_array($nextJson) && ($nextJson['ok'] ?? false) === true, 'Authenticated request after preview failed semantically');

        $storedSession['SessionGuardCheckedAt'] = 0;
        $writeSession($storedSession);
        $pdo->prepare('UPDATE sessoes_usuario SET revogado_em=NOW() WHERE sessao_hash=:hash')->execute([':hash' => $hash]);
        [$revokedStatus, $revokedBody] = $request('/api/cronograma-treino-preview.php?idtreino=' . rawurlencode($workoutId));
        AlphaTest::same(401, $revokedStatus, 'Revoked session was not rejected by preview Session Guard');
        AlphaTest::assert(str_contains($revokedBody, 'Sessão encerrada') || str_contains($revokedBody, 'Sessão inválida'), 'Revoked preview session did not return the expected auth failure');
    } finally {
        proc_terminate($server);
        proc_close($server);
        if ($previousTtl === false) putenv('STRIDEBR_SESSION_GUARD_TTL'); else putenv('STRIDEBR_SESSION_GUARD_TTL=' . $previousTtl);
        @unlink($serverOut); @unlink($serverErr);
        foreach (glob($sessionDir . '/*') ?: [] as $file) @unlink($file);
        @rmdir($sessionDir);
    }
};
