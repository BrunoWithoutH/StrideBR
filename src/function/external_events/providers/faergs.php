<?php

declare(strict_types=1);

final class ExternalEventsFaergsProvider implements ExternalEventsProvider
{
    public function key(): string { return 'faergs'; }
    public function label(): string { return 'FAERGS'; }
    public function hosts(): array { return ['faergs.com.br', 'www.faergs.com.br']; }
    public function initialUrls(): array { return ['https://www.faergs.com.br/']; }
    public function discover(string $url, string $html): array { return []; }

    public function parse(string $url, string $html): array
    {
        $pageText = externalEventsNormalizeComparable(externalEventsText($html));
        if (!str_contains($pageText, 'calendario de competicoes')) throw new RuntimeException('faergs_structure_unrecognized');
        $events = [];
        foreach (externalEventsHtmlRows($html, $url) as $row) {
            $cells = $row['cells'];
            if (count($cells) < 3) continue;
            $date = externalEventsParseDate((string) $cells[0]);
            if (!$date) continue;
            $title = trim((string) $cells[1]);
            if ($title === '') continue;
            $location = externalEventsLocationParts((string) $cells[2]);
            $events[] = externalEventsNormalized([
                'provider' => $this->key(),
                'source_url' => $url,
                'title' => $title,
                'start_at' => externalEventsDateIso($date),
                'city' => $location['city'],
                'state' => $location['state'],
                'country' => $location['country'],
                'organizer' => 'FAERGS',
                'official_url' => $url,
                'primary_sport' => 'atletismo',
                'sports' => ['atletismo'],
                'event_type' => 'atletismo',
                'status' => 'scheduled',
                'source_metadata' => ['calendar' => 'faergs', 'calendar_url' => $url],
            ]);
        }
        if ($events === []) throw new RuntimeException('faergs_structure_unrecognized');
        return $events;
    }
}
