<?php

declare(strict_types=1);

final class ExternalEventsCbcProvider implements ExternalEventsProvider
{
    public function key(): string { return 'cbc'; }
    public function label(): string { return 'CBC'; }
    public function hosts(): array { return ['cbc.esp.br', 'www.cbc.esp.br']; }
    public function initialUrls(): array
    {
        return [
            'https://www.cbc.esp.br/modalidades/calendario/busca/estrada',
            'https://www.cbc.esp.br/modalidades/calendario/busca/pista',
            'https://www.cbc.esp.br/modalidades/calendario/busca/mtb',
        ];
    }
    public function discover(string $url, string $html): array { return []; }

    public function parse(string $url, string $html): array
    {
        $normalizedUrl = strtolower($url);
        $slug = str_ends_with($normalizedUrl, '/pista') ? 'ciclismo' : (str_ends_with($normalizedUrl, '/mtb') ? 'mountain-bike' : 'ciclismo-de-estrada');
        $eventType = str_ends_with($normalizedUrl, '/pista') ? 'pista' : (str_ends_with($normalizedUrl, '/mtb') ? 'mtb' : 'estrada');
        $text = externalEventsNormalizeComparable(externalEventsText($html));
        if (!str_contains($text, 'calendario')) throw new RuntimeException('cbc_structure_unrecognized');
        $events = [];
        $header = [];
        foreach (externalEventsHtmlRows($html, $url) as $row) {
            $cells = $row['cells'];
            if ($header === []) {
                $candidate = externalEventsHeaderMap($cells);
                if (externalEventsHeaderIndex($candidate, ['evento']) !== null && externalEventsHeaderIndex($candidate, ['data']) !== null) {
                    $header = $candidate;
                    continue;
                }
            }
            if ($header === []) continue;
            $dateIndex = externalEventsHeaderIndex($header, ['data']);
            $titleIndex = externalEventsHeaderIndex($header, ['evento']);
            $cityIndex = externalEventsHeaderIndex($header, ['cidade']);
            $categoryIndex = externalEventsHeaderIndex($header, ['categoria']);
            $classIndex = externalEventsHeaderIndex($header, ['classe']);
            if ($dateIndex === null || $titleIndex === null) continue;
            $datesText = (string) ($cells[$dateIndex] ?? '');
            $dates = [];
            if (preg_match_all('/\b\d{1,2}\/\d{1,2}\/\d{4}\b/u', $datesText, $m)) $dates = $m[0];
            $start = isset($dates[0]) ? externalEventsParseDate($dates[0]) : null;
            $end = isset($dates[1]) ? externalEventsParseDate($dates[1]) : $start;
            $title = trim((string) ($cells[$titleIndex] ?? ''));
            if (!$start || $title === '') continue;
            $location = externalEventsLocationParts($cityIndex !== null ? (string) ($cells[$cityIndex] ?? '') : '');
            $officialUrl = $url;
            $externalId = null;
            foreach ($row['links'] as $link) {
                $linkUrl = (string) ($link['url'] ?? '');
                $linkHost = strtolower((string) parse_url($linkUrl, PHP_URL_HOST));
                if (!in_array($linkHost, $this->hosts(), true)) continue;
                if ($officialUrl === $url && $linkUrl !== '') $officialUrl = $linkUrl;
                if (preg_match('/[?&](?:id|codigo|cod)=([^&#]+)/i', $linkUrl, $mId)) $externalId = rawurldecode($mId[1]);
            }
            $category = $categoryIndex !== null ? trim((string) ($cells[$categoryIndex] ?? '')) : '';
            $class = $classIndex !== null ? trim((string) ($cells[$classIndex] ?? '')) : '';
            $events[] = externalEventsNormalized([
                'provider' => $this->key(),
                'external_id' => $externalId,
                'source_url' => $officialUrl,
                'title' => $title,
                'start_at' => externalEventsDateIso($start),
                'end_at' => $end ? externalEventsDateIso($end) : null,
                'city' => $location['city'],
                'state' => $location['state'],
                'country' => $location['country'],
                'organizer' => 'CBC',
                'official_url' => $officialUrl,
                'status' => externalEventsStatusFromText($title . ' ' . $class),
                'primary_sport' => $slug,
                'sports' => [$slug],
                'event_type' => $eventType,
                'source_metadata' => ['category' => $category ?: null, 'class' => $class ?: null, 'calendar_url' => $url],
            ]);
        }
        if ($events === []) throw new RuntimeException('cbc_structure_unrecognized');
        return $events;
    }
}
