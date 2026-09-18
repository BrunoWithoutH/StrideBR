<?php

declare(strict_types=1);

final class ExternalEventsCbtriProvider implements ExternalEventsProvider
{
    public function key(): string { return 'cbtri'; }
    public function label(): string { return 'CBTri'; }
    public function hosts(): array { return ['cbtri.org.br', 'www.cbtri.org.br']; }
    public function initialUrls(): array { return ['https://cbtri.org.br/calendario_2026/']; }

    public function discover(string $url, string $html): array
    {
        $urls = [];
        foreach (externalEventsProviderLinkCandidates($html, $url) as $candidate) {
            $host = strtolower((string) parse_url($candidate, PHP_URL_HOST));
            if (!in_array($host, $this->hosts(), true)) continue;
            $path = strtolower((string) parse_url($candidate, PHP_URL_PATH));
            if (str_contains($path, 'calendario') && $candidate !== $url) $urls[] = $candidate;
        }
        return array_slice(array_values(array_unique($urls)), 0, 12);
    }

    public function parse(string $url, string $html): array
    {
        $pageText = externalEventsText($html);
        $normalizedPage = externalEventsNormalizeComparable($pageText);
        if (!str_contains($normalizedPage, 'calendario') && !str_contains($normalizedPage, 'campeonato')) return [];
        $defaultSport = $this->sportFromText($pageText);
        $events = [];
        $header = [];
        foreach (externalEventsHtmlRows($html, $url) as $row) {
            $cells = $row['cells'];
            if ($header === []) {
                $candidate = externalEventsHeaderMap($cells);
                if (externalEventsHeaderIndex($candidate, ['data']) !== null && (externalEventsHeaderIndex($candidate, ['local']) !== null || externalEventsHeaderIndex($candidate, ['cidade']) !== null)) {
                    $header = $candidate;
                    continue;
                }
            }
            if ($header === []) continue;
            $dateIndex = externalEventsHeaderIndex($header, ['data']);
            $cityIndex = externalEventsHeaderIndex($header, ['local', 'cidade']);
            $stateIndex = externalEventsHeaderIndex($header, ['uf']);
            $categoryIndex = externalEventsHeaderIndex($header, ['categorias', 'categoria']);
            $statusIndex = externalEventsHeaderIndex($header, ['status', 'situacao', 'inscricao']);
            $eventIndex = externalEventsHeaderIndex($header, ['evento', 'etapa']);
            if ($dateIndex === null) continue;
            $date = externalEventsParseDate((string) ($cells[$dateIndex] ?? ''));
            if (!$date) continue;
            $cityRaw = $cityIndex !== null ? trim((string) ($cells[$cityIndex] ?? '')) : '';
            $state = $stateIndex !== null ? trim((string) ($cells[$stateIndex] ?? '')) : '';
            if ($state === '') {
                $loc = externalEventsLocationParts($cityRaw);
                $city = $loc['city'];
                $state = (string) ($loc['state'] ?? '');
            } else {
                $city = $cityRaw ?: null;
            }
            $title = $eventIndex !== null ? trim((string) ($cells[$eventIndex] ?? '')) : '';
            if ($title === '') {
                $heading = trim((string) preg_replace('/\s+/', ' ', $pageText));
                $title = $heading !== '' ? substr($heading, 0, 160) : 'Competição CBTri';
            }
            $rowText = implode(' ', array_map('strval', $cells));
            $sport = $this->sportFromText($title . ' ' . $rowText) ?: $defaultSport ?: 'triatlo';
            $category = $categoryIndex !== null ? trim((string) ($cells[$categoryIndex] ?? '')) : '';
            $statusText = $statusIndex !== null ? trim((string) ($cells[$statusIndex] ?? '')) : $rowText;
            $registrationUrl = null;
            foreach ($row['links'] as $link) {
                $linkText = externalEventsNormalizeComparable((string) ($link['text'] ?? ''));
                if (str_contains($linkText, 'inscri')) $registrationUrl = (string) ($link['url'] ?? '');
            }
            $events[] = externalEventsNormalized([
                'provider' => $this->key(),
                'source_url' => $url,
                'title' => $title,
                'start_at' => externalEventsDateIso($date),
                'city' => $city,
                'state' => $state !== '' ? strtoupper($state) : null,
                'country' => 'Brasil',
                'organizer' => 'CBTri',
                'official_url' => $url,
                'registration_url' => $registrationUrl,
                'status' => externalEventsStatusFromText($statusText),
                'primary_sport' => $sport,
                'sports' => [$sport],
                'event_type' => $sport,
                'source_metadata' => ['category' => $category ?: null, 'status_text' => $statusText ?: null, 'calendar_url' => $url],
            ]);
        }
        if ($events === [] && $this->discover($url, $html) === []) throw new RuntimeException('cbtri_structure_unrecognized');
        return $events;
    }

    private function sportFromText(string $value): ?string
    {
        $n = externalEventsNormalizeComparable($value);
        if (str_contains($n, 'cross duathlon') || str_contains($n, 'cross duatlo') || str_contains($n, 'duathlon') || str_contains($n, 'duatlo')) return 'duatlo';
        if (str_contains($n, 'aquathlon') || str_contains($n, 'aquatlo')) return 'aquatlo';
        if (str_contains($n, 'aquabike')) return 'multiesporte';
        if (str_contains($n, 'cross triathlon') || str_contains($n, 'triathlon') || str_contains($n, 'triatlo') || str_contains($n, 'para triathlon')) return 'triatlo';
        if (str_contains($n, 'multisport')) return 'multiesporte';
        return null;
    }
}
