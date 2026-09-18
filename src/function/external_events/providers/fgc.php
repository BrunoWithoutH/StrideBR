<?php

declare(strict_types=1);

final class ExternalEventsFgcProvider implements ExternalEventsProvider
{
    public function key(): string { return 'fgc'; }
    public function label(): string { return 'FGC'; }
    public function hosts(): array { return ['fgc.com.br', 'www.fgc.com.br']; }
    public function initialUrls(): array
    {
        return [
            'https://www.fgc.com.br/estrada/campeonato-2026/',
            'https://www.fgc.com.br/mountain-bike/campeonato-2026/',
            'https://www.fgc.com.br/downhill/campeonato-2026/',
        ];
    }
    public function discover(string $url, string $html): array { return []; }

    public function parse(string $url, string $html): array
    {
        $normalizedUrl = strtolower($url);
        $sport = str_contains($normalizedUrl, '/mountain-bike/') ? 'mountain-bike' : (str_contains($normalizedUrl, '/downhill/') ? 'downhill' : 'ciclismo-de-estrada');
        $eventType = $sport === 'ciclismo-de-estrada' ? 'estrada' : ($sport === 'mountain-bike' ? 'mtb' : 'downhill');
        $blocks = externalEventsHtmlBlocks($html);
        if ($blocks === []) throw new RuntimeException('fgc_structure_unrecognized');
        $events = [];
        foreach ($blocks as $block) {
            $parsed = $this->parseBlock($block);
            if ($parsed === null) continue;
            [$start, $end] = externalEventsParseDateRange($parsed['date_text'], 2026);
            if (!$start) continue;
            $events[] = externalEventsNormalized([
                'provider' => $this->key(),
                'source_url' => $url,
                'title' => $parsed['stage'],
                'start_at' => externalEventsDateIso($start),
                'end_at' => $end ? externalEventsDateIso($end) : null,
                'city' => $parsed['city'],
                'state' => 'RS',
                'country' => 'Brasil',
                'organizer' => 'FGC',
                'official_url' => $url,
                'status' => externalEventsStatusFromText($block),
                'primary_sport' => $sport,
                'sports' => [$sport],
                'event_type' => $eventType,
                'source_metadata' => ['calendar_url' => $url, 'raw_label' => $block],
            ]);
        }
        if ($events === []) throw new RuntimeException('fgc_structure_unrecognized');
        return $events;
    }

    private function parseBlock(string $block): ?array
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $block) ?? $block);
        if (preg_match('/^((?:\d+[ªº]?|Primeira|Segunda|Terceira|Quarta|Quinta|Sexta|S[eé]tima|Oitava)\s+[Ee]tapa)\s*[–-]\s*((?:\d{1,2}\s+e\s+)?\d{1,2}\s+de\s+[\p{L}]+(?:\s+de\s+\d{4})?)\s*,\s*(.*)$/iu', $clean, $m)) {
            $rest = trim((string) $m[3]);
            $rest = trim(preg_replace('/\s*:\s*(?:Guia T[eé]cnico|Informativo|Resultados?|Inscri[cç][oõ]es).*$/iu', '', $rest) ?? $rest);
            $city = $rest;
            if (preg_match('/^.*?\s+[–-]\s+(.+)$/u', $rest, $cityMatch)) $city = trim($cityMatch[1]);
            return ['stage' => trim((string) $m[1]), 'date_text' => trim((string) $m[2]), 'city' => $city !== '' ? $city : null];
        }
        if (preg_match('/^((?:\d+[ªº]?|Primeira|Segunda|Terceira|Quarta|Quinta|Sexta|S[eé]tima|Oitava)\s+[Ee]tapa)\s*:\s*([^,–-]+)\s*[,–-]\s*((?:\d{1,2}\s+e\s+)?\d{1,2}\s+de\s+[\p{L}]+(?:\s+de\s+\d{4})?)/iu', $clean, $m)) {
            return ['stage' => trim((string) $m[1]), 'date_text' => trim((string) $m[3]), 'city' => trim((string) $m[2]) ?: null];
        }
        return null;
    }
}
