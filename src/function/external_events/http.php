<?php

declare(strict_types=1);

function externalEventsHttpValidateUrl(string $url, array $allowedHosts): void
{
    if (strlen($url) > 2000 || filter_var($url, FILTER_VALIDATE_URL) === false) throw new InvalidArgumentException('external_url_invalid');
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $allowed = array_map('strtolower', $allowedHosts);
    if ($scheme !== 'https' || $host === '' || !in_array($host, $allowed, true)) throw new InvalidArgumentException('external_url_not_allowed');
    if (parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) throw new InvalidArgumentException('external_url_credentials_not_allowed');
}

function externalEventsHttpFetch(string $url, array $allowedHosts, array $conditional = [], ?float $deadlineAt = null): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('external_http_unavailable');
    $current = $url;
    $redirects = 0;
    $maxBytes = 2 * 1024 * 1024;
    while (true) {
        externalEventsHttpValidateUrl($current, $allowedHosts);
        if ($deadlineAt !== null && microtime(true) >= $deadlineAt) throw new RuntimeException('external_sync_deadline');
        $responseHeaders = [];
        $body = '';
        $tooLarge = false;
        $remaining = $deadlineAt !== null ? max(1, (int) floor($deadlineAt - microtime(true))) : 12;
        $timeout = min(12, $remaining);
        $connectTimeout = min(5, $timeout);
        $requestHeaders = ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.1'];
        if (!empty($conditional['etag'])) $requestHeaders[] = 'If-None-Match: ' . trim((string) $conditional['etag']);
        if (!empty($conditional['last_modified'])) $requestHeaders[] = 'If-Modified-Since: ' . trim((string) $conditional['last_modified']);
        $ch = curl_init($current);
        if ($ch === false) throw new RuntimeException('external_http_init_failed');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'StrideBR External Events/1.0 (+https://stridebr.com.br/)',
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_ENCODING => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $trim = trim($line);
                if ($trim !== '' && str_contains($trim, ':')) {
                    [$name, $value] = explode(':', $trim, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = trim((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        $error = curl_error($ch);
        curl_close($ch);
        if ($ok === false) {
            if ($tooLarge) throw new RuntimeException('external_http_response_too_large');
            throw new RuntimeException('external_http_failed:' . ($error !== '' ? $error : 'unknown'));
        }
        if ($status >= 300 && $status < 400 && isset($responseHeaders['location'])) {
            if ($redirects >= 3) throw new RuntimeException('external_http_too_many_redirects');
            $next = externalEventsAbsoluteUrl($current, (string) $responseHeaders['location']);
            if ($next === null) throw new RuntimeException('external_http_redirect_invalid');
            externalEventsHttpValidateUrl($next, $allowedHosts);
            $current = $next;
            $redirects++;
            continue;
        }
        return [
            'status' => $status,
            'body' => $body,
            'content_type' => $contentType,
            'etag' => $responseHeaders['etag'] ?? null,
            'last_modified' => $responseHeaders['last-modified'] ?? null,
            'retry_after' => $responseHeaders['retry-after'] ?? null,
            'final_url' => $current,
        ];
    }
}
