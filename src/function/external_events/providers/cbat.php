<?php

declare(strict_types=1);

final class ExternalEventsCbatProvider implements ExternalEventsProvider
{
    public function key(): string { return 'cbat'; }
    public function label(): string { return 'CBAt'; }
    public function hosts(): array { return ['competicoes.cbat.org.br']; }
    public function initialUrls(): array
    {
        return [
            'https://competicoes.cbat.org.br/novo/index.php?pagina=calendario_oficial',
            'https://competicoes.cbat.org.br/novo/index.php?pagina=calendario_brasil',
            'https://competicoes.cbat.org.br/novo/index.php?pagina=calendario_estadual',
        ];
    }

    public function discover(string $url, string $html): array
    {
        return [];
    }

    public function parse(string $url, string $html): array
    {
        $text = externalEventsNormalizeComparable(externalEventsText($html));
        if (!str_contains($text, 'calendario')) throw new RuntimeException('cbat_structure_unrecognized');
        $events = [];
        $header = [];
        foreach (externalEventsHtmlRows($html, $url) as $row) {
            $cells = $row['cells'];
            if ($header === []) {
                $candidate = externalEventsHeaderMap($cells);
                if (externalEventsHeaderIndex($candidate, ['evento']) !== null && externalEventsHeaderIndex($candidate, ['data inicial', 'data']) !== null) {
                    $header = $candidate;
                    continue;
                }
            }
            if ($header === []) continue;
            $titleIndex = externalEventsHeaderIndex($header, ['evento']);
            $startIndex = externalEventsHeaderIndex($header, ['data inicial', 'data']);
            $endIndex = externalEventsHeaderIndex($header, ['data final']);
            $cityIndex = externalEventsHeaderIndex($header, ['cidade']);
            $stateIndex = externalEventsHeaderIndex($header, ['uf']);
            $countryIndex = externalEventsHeaderIndex($header, ['pais']);
            $organizerIndex = externalEventsHeaderIndex($header, ['organizador', 'entidade']);
            if ($titleIndex === null || $startIndex === null) continue;
            $title = trim((string) ($cells[$titleIndex] ?? ''));
            $start = externalEventsParseDate((string) ($cells[$startIndex] ?? ''));
            if ($title === '' || !$start) continue;
            $end = $endIndex !== null ? externalEventsParseDate((string) ($cells[$endIndex] ?? '')) : null;
            $city = $cityIndex !== null ? trim((string) ($cells[$cityIndex] ?? '')) : null;
            $state = $stateIndex !== null ? trim((string) ($cells[$stateIndex] ?? '')) : null;
            $country = $countryIndex !== null ? trim((string) ($cells[$countryIndex] ?? '')) : 'Brasil';
            $officialUrl = $url;
            $externalId = null;
            $programUrl = null;
            foreach ($row['links'] as $link) {
                $linkUrl = (string) ($link['url'] ?? '');
                $linkHost = strtolower((string) parse_url($linkUrl, PHP_URL_HOST));
                if (!in_array($linkHost, $this->hosts(), true)) continue;
                if (preg_match('/[?&](?:id|codigo|cod|competicao)=([^&#]+)/i', $linkUrl, $m)) $externalId = rawurldecode($m[1]);
                if (str_contains(strtolower($linkUrl), '/competicoes/') || str_contains(strtolower($linkUrl), 'program')) $programUrl = $linkUrl;
                if ($officialUrl === $url) $officialUrl = $linkUrl;
            }
            $events[] = externalEventsNormalized([
                'provider' => $this->key(),
                'external_id' => $externalId,
                'source_url' => $officialUrl,
                'title' => $title,
                'start_at' => externalEventsDateIso($start),
                'end_at' => $end ? externalEventsDateIso($end) : null,
                'city' => $city ?: null,
                'state' => $state ?: null,
                'country' => $country ?: 'Brasil',
                'organizer' => $organizerIndex !== null ? trim((string) ($cells[$organizerIndex] ?? '')) ?: 'CBAt' : 'CBAt',
                'official_url' => $officialUrl,
                'primary_sport' => 'atletismo',
                'sports' => ['atletismo'],
                'event_type' => 'atletismo',
                'status' => externalEventsStatusFromText($title),
                'source_metadata' => ['calendar_url' => $url, 'program_url' => $programUrl],
            ]);
        }
        if ($events === []) throw new RuntimeException('cbat_structure_unrecognized');
        return $events;
    }

    public function parseProgram(string $url, string $html): array
    {
        $program = [];
        $header = [];
        foreach (externalEventsHtmlRows($html, $url) as $row) {
            $cells = $row['cells'];
            if ($header === []) {
                $candidate = externalEventsHeaderMap($cells);
                if (externalEventsHeaderIndex($candidate, ['prova', 'event']) !== null) {
                    $header = $candidate;
                    continue;
                }
            }
            if ($header === []) continue;
            $nameIndex = externalEventsHeaderIndex($header, ['prova', 'event']);
            if ($nameIndex === null) continue;
            $name = trim((string) ($cells[$nameIndex] ?? ''));
            if ($name === '' || externalEventsNormalizeComparable($name) === 'prova') continue;
            $timeIndex = externalEventsHeaderIndex($header, ['hora', 'time']);
            $categoryIndex = externalEventsHeaderIndex($header, ['categoria']);
            $sexIndex = externalEventsHeaderIndex($header, ['sexo', 'gender']);
            $phaseIndex = externalEventsHeaderIndex($header, ['fase', 'round']);
            $group = externalEventsAthleticsProgramGroup($name);
            if ($group === null) continue;
            $program[] = [
                'name' => $name,
                'group' => $group,
                'category' => $categoryIndex !== null ? trim((string) ($cells[$categoryIndex] ?? '')) ?: null : null,
                'sex' => $sexIndex !== null ? trim((string) ($cells[$sexIndex] ?? '')) ?: null : null,
                'distance_m' => externalEventsDistanceMeters($name),
                'time' => $timeIndex !== null ? trim((string) ($cells[$timeIndex] ?? '')) ?: null : null,
                'phase' => $phaseIndex !== null ? trim((string) ($cells[$phaseIndex] ?? '')) ?: null : null,
                'metadata' => ['source_url' => $url],
            ];
        }
        if ($program === [] && !str_contains(externalEventsNormalizeComparable(externalEventsText($html)), 'prova')) throw new RuntimeException('cbat_program_structure_unrecognized');
        return $program;
    }
}
