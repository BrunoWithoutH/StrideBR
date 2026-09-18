<?php

declare(strict_types=1);

interface ExternalEventsProvider
{
    public function key(): string;
    public function label(): string;
    public function hosts(): array;
    public function initialUrls(): array;
    public function parse(string $url, string $html): array;
    public function discover(string $url, string $html): array;
}

function externalEventsText(string $html): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function externalEventsAbsoluteUrl(string $base, string $href): ?string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:') || str_starts_with(strtolower($href), 'mailto:')) return null;
    if (preg_match('#^https://#i', $href) === 1) return $href;
    if (preg_match('#^http://#i', $href) === 1) return null;
    $parts = parse_url($base);
    if (!is_array($parts) || empty($parts['host'])) return null;
    $origin = 'https://' . $parts['host'] . (!empty($parts['port']) ? ':' . $parts['port'] : '');
    if (str_starts_with($href, '//')) return 'https:' . $href;
    if (str_starts_with($href, '/')) return $origin . $href;
    $path = (string) ($parts['path'] ?? '/');
    $dir = preg_replace('#/[^/]*$#', '/', $path) ?: '/';
    return $origin . $dir . $href;
}

function externalEventsHtmlRows(string $html, string $baseUrl = ''): array
{
    $rows = [];
    if (class_exists('DOMDocument') && class_exists('DOMXPath')) {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($loaded) {
            $xpath = new DOMXPath($dom);
            foreach ($xpath->query('//tr') ?: [] as $tr) {
                $cells = [];
                $links = [];
                foreach ($xpath->query('./th|./td', $tr) ?: [] as $cell) {
                    $cells[] = externalEventsText($dom->saveHTML($cell) ?: $cell->textContent);
                    foreach ($xpath->query('.//a[@href]', $cell) ?: [] as $link) {
                        $absolute = externalEventsAbsoluteUrl($baseUrl, (string) $link->getAttribute('href'));
                        if ($absolute !== null) $links[] = ['text' => trim((string) $link->textContent), 'url' => $absolute];
                    }
                }
                if ($cells !== []) $rows[] = ['cells' => $cells, 'links' => $links];
            }
            return $rows;
        }
    }
    if (preg_match_all('#<tr\b[^>]*>(.*?)</tr>#isu', $html, $matches)) {
        foreach ($matches[1] as $rowHtml) {
            $cells = [];
            $links = [];
            if (preg_match_all('#<(?:td|th)\b[^>]*>(.*?)</(?:td|th)>#isu', $rowHtml, $cellMatches)) {
                foreach ($cellMatches[1] as $cellHtml) {
                    $cells[] = externalEventsText($cellHtml);
                    if (preg_match_all('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#isu', $cellHtml, $linkMatches, PREG_SET_ORDER)) {
                        foreach ($linkMatches as $link) {
                            $absolute = externalEventsAbsoluteUrl($baseUrl, (string) $link[1]);
                            if ($absolute !== null) $links[] = ['text' => externalEventsText((string) $link[2]), 'url' => $absolute];
                        }
                    }
                }
            }
            if ($cells !== []) $rows[] = ['cells' => $cells, 'links' => $links];
        }
    }
    return $rows;
}

function externalEventsHtmlBlocks(string $html): array
{
    $blocks = [];
    if (preg_match_all('#<(?:p|li|h1|h2|h3|h4|h5|h6|div)\b[^>]*>(.*?)</(?:p|li|h1|h2|h3|h4|h5|h6|div)>#isu', $html, $matches)) {
        foreach ($matches[1] as $block) {
            $text = externalEventsText((string) $block);
            if ($text !== '') $blocks[] = $text;
        }
    }
    return array_values(array_unique($blocks));
}

function externalEventsLower(string $value): string
{
    if (function_exists('mb_strtolower')) return mb_strtolower($value, 'UTF-8');
    return strtolower(strtr($value, ['Á'=>'á','À'=>'à','Â'=>'â','Ã'=>'ã','Ä'=>'ä','É'=>'é','È'=>'è','Ê'=>'ê','Ë'=>'ë','Í'=>'í','Ì'=>'ì','Î'=>'î','Ï'=>'ï','Ó'=>'ó','Ò'=>'ò','Ô'=>'ô','Õ'=>'õ','Ö'=>'ö','Ú'=>'ú','Ù'=>'ù','Û'=>'û','Ü'=>'ü','Ç'=>'ç']));
}

function externalEventsParseDate(string $value, ?int $fallbackYear = null): ?DateTimeImmutable
{
    $value = trim($value);
    if ($value === '') return null;
    $tz = new DateTimeZone('America/Sao_Paulo');
    if (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/u', $value, $m)) {
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', (int) $m[3], (int) $m[2], (int) $m[1]), $tz);
    }
    $months = [
        'janeiro' => 1, 'fevereiro' => 2, 'marco' => 3, 'março' => 3, 'abril' => 4, 'maio' => 5, 'junho' => 6,
        'julho' => 7, 'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12,
    ];
    if (preg_match('/\b(\d{1,2})\s+de\s+([\p{L}]+)(?:\s+de\s+(\d{4}))?/iu', $value, $m)) {
        $monthName = externalEventsLower($m[2]);
        $month = $months[$monthName] ?? null;
        $year = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : $fallbackYear;
        if ($month !== null && $year !== null) return new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, (int) $m[1]), $tz);
    }
    return null;
}

function externalEventsParseDateRange(string $value, ?int $fallbackYear = null): array
{
    $value = trim($value);
    if ($value === '') return [null, null];
    $months = [
        'janeiro' => 1, 'fevereiro' => 2, 'marco' => 3, 'março' => 3, 'abril' => 4, 'maio' => 5, 'junho' => 6,
        'julho' => 7, 'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12,
    ];
    if (preg_match('/\b(\d{1,2})\s+e\s+(\d{1,2})\s+de\s+([\p{L}]+)(?:\s+de\s+(\d{4}))?/iu', $value, $m)) {
        $monthName = externalEventsLower($m[3]);
        $month = $months[$monthName] ?? null;
        $year = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : $fallbackYear;
        if ($month !== null && $year !== null) {
            $tz = new DateTimeZone('America/Sao_Paulo');
            $start = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, (int) $m[1]), $tz);
            $end = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, (int) $m[2]), $tz);
            if ($end >= $start) return [$start, $end];
        }
    }
    $single = externalEventsParseDate($value, $fallbackYear);
    return [$single, null];
}

function externalEventsDateIso(DateTimeImmutable $date): string
{
    return $date->format(DATE_ATOM);
}

function externalEventsLocationParts(string $value): array
{
    $raw = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    $raw = preg_replace('/\s+-\s+Brasil\s*$/iu', '', $raw) ?? $raw;
    $city = $raw;
    $state = null;
    if (preg_match('/^(.*?)[\s]*[\/,-][\s]*([A-Z]{2})(?:\b|\s|$)/u', $raw, $m)) {
        $city = trim($m[1]);
        $state = strtoupper($m[2]);
    } elseif (preg_match('/^(.*?)\s+-\s+([A-Z]{2})\b/u', $raw, $m)) {
        $city = trim($m[1]);
        $state = strtoupper($m[2]);
    }
    return ['city' => $city !== '' ? $city : null, 'state' => $state, 'country' => 'Brasil'];
}

function externalEventsNormalizeComparable(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    if (class_exists('Transliterator')) {
        $trans = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        if ($trans) $value = (string) $trans->transliterate($value);
    } else {
        $map = ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','Ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','Í'=>'i','Ì'=>'i','Î'=>'i','Ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','Ó'=>'o','Ò'=>'o','Ô'=>'o','Õ'=>'o','Ö'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','Ú'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u','ç'=>'c','Ç'=>'c'];
        $value = strtr($value, $map);
    }
    $value = strtolower($value);
    $value = preg_replace('/\b\d{1,3}[oa]?[ªº]\b/u', ' ', $value) ?? $value;
    $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function externalEventsComparableTitle(string $title, ?string $primarySport = null): string
{
    $value = externalEventsNormalizeComparable($title);
    $tokens = preg_split('/\s+/', $value) ?: [];
    $drop = ['de', 'da', 'do', 'das', 'dos'];
    if ($primarySport === 'atletismo') $drop[] = 'atletismo';
    $tokens = array_values(array_filter($tokens, static fn(string $token): bool => $token !== '' && !in_array($token, $drop, true)));
    return implode(' ', $tokens);
}

function externalEventsFingerprint(array $event): string
{
    $date = substr((string) ($event['start_at'] ?? ''), 0, 10);
    $sport = trim((string) ($event['primary_sport'] ?? ''));
    $parts = [
        externalEventsComparableTitle((string) ($event['title'] ?? ''), $sport !== '' ? $sport : null),
        $date,
        externalEventsNormalizeComparable((string) ($event['city'] ?? '')),
        strtoupper(trim((string) ($event['state'] ?? ''))),
        $sport,
    ];
    return hash('sha256', implode('|', $parts));
}

function externalEventsAthleticsProgramGroup(string $name): ?string
{
    $n = externalEventsNormalizeComparable($name);
    if ($n === '') return null;
    if (str_contains($n, 'barreira')) return 'barreiras';
    if (str_contains($n, 'obstaculo')) return 'obstaculos';
    if (str_contains($n, 'marcha')) return 'marcha';
    if (str_contains($n, 'revez') || preg_match('/\b4x\d+\b/u', $n) === 1) return 'revezamento';
    if (str_contains($n, 'salto')) return 'salto';
    if (str_contains($n, 'arremesso') || str_contains($n, 'lancamento')) return 'arremesso_lancamento';
    if (str_contains($n, 'decatlo') || str_contains($n, 'heptatlo') || str_contains($n, 'pentatlo') || str_contains($n, 'tetratlo') || str_contains($n, 'octatlo')) return 'combinadas';
    if (preg_match('/\b\d[\d\.]*\s*(m|metros?)\b/u', $n) === 1) return 'corrida';
    return null;
}

function externalEventsDistanceMeters(string $name): ?float
{
    if (preg_match('/\b(\d{1,3}(?:[.\s]\d{3})+|\d+)\s*(?:m|metros?)\b/iu', $name, $m) !== 1) return null;
    $meters = (float) preg_replace('/[.\s]/u', '', $m[1]);
    if ($meters <= 0 || $meters > 100000) return null;
    return $meters;
}

function externalEventsNormalized(array $data): array
{
    $defaults = [
        'provider' => '', 'external_id' => null, 'source_url' => '', 'title' => '', 'start_at' => '', 'end_at' => null,
        'city' => null, 'state' => null, 'country' => 'Brasil', 'venue' => null, 'address' => null, 'organizer' => null,
        'official_url' => null, 'registration_url' => null, 'registration_deadline' => null, 'status' => 'scheduled',
        'primary_sport' => null, 'sports' => [], 'event_type' => null, 'distances' => [], 'program' => [], 'source_metadata' => [],
        'time_known' => false,
    ];
    $event = array_replace($defaults, $data);
    $event['sports'] = array_values(array_unique(array_filter(array_map('strval', (array) $event['sports']))));
    $event['distances'] = array_values(array_unique(array_filter(array_map('strval', (array) $event['distances']))));
    $event['program'] = array_values(array_filter((array) $event['program'], 'is_array'));
    $event['source_metadata'] = is_array($event['source_metadata']) ? $event['source_metadata'] : [];
    $event['fingerprint'] = externalEventsFingerprint($event);
    return $event;
}

function externalEventsProviderLinkCandidates(string $html, string $baseUrl): array
{
    $urls = [];
    if (preg_match_all('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>#isu', $html, $matches)) {
        foreach ($matches[1] as $href) {
            $absolute = externalEventsAbsoluteUrl($baseUrl, (string) $href);
            if ($absolute !== null) $urls[] = $absolute;
        }
    }
    return array_values(array_unique($urls));
}

function externalEventsHeaderMap(array $cells): array
{
    $map = [];
    foreach ($cells as $index => $cell) {
        $key = externalEventsNormalizeComparable((string) $cell);
        if ($key !== '') $map[$key] = (int) $index;
    }
    return $map;
}

function externalEventsHeaderIndex(array $map, array $aliases): ?int
{
    foreach ($aliases as $alias) {
        $needle = externalEventsNormalizeComparable($alias);
        foreach ($map as $key => $index) {
            if ($key === $needle || str_contains($key, $needle)) return $index;
        }
    }
    return null;
}

function externalEventsIdentityKey(array $event): string
{
    $externalId = trim((string) ($event['external_id'] ?? ''));
    if ($externalId !== '') return 'id:' . substr($externalId, 0, 180);
    $sourceUrl = trim((string) ($event['source_url'] ?? ''));
    $calendarUrl = trim((string) (($event['source_metadata']['calendar_url'] ?? '')));
    if ($sourceUrl !== '' && ($calendarUrl === '' || $sourceUrl !== $calendarUrl)) return 'url:' . substr(hash('sha256', $sourceUrl), 0, 64);
    return 'fp:' . substr((string) ($event['fingerprint'] ?? externalEventsFingerprint($event)), 0, 180);
}

function externalEventsStatusFromText(string $value): string
{
    $normalized = externalEventsNormalizeComparable($value);
    if (str_contains($normalized, 'cancelad')) return 'cancelled';
    if (str_contains($normalized, 'adiad')) return 'postponed';
    if (str_contains($normalized, 'confirmad')) return 'confirmed';
    if (str_contains($normalized, 'previst')) return 'planned';
    return 'scheduled';
}
