<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/activity_file_exchange.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "✗ activity exchange parser: {$message}\n");
        exit(1);
    }
};

$gpx = '<?xml version="1.0"?><gpx><trk><name>Corrida teste</name><type>running</type><trkseg>'
    . '<trkpt lat="-27.3600000" lon="-53.3900000"><ele>510</ele><time>2026-08-29T12:00:00Z</time><extensions><hr>150</hr></extensions></trkpt>'
    . '<trkpt lat="-27.3610000" lon="-53.3910000"><ele>514</ele><time>2026-08-29T12:01:00Z</time><extensions><hr>154</hr></extensions></trkpt>'
    . '</trkseg></trk></gpx>';
$parsedGpx = atividadeArquivoAnalisar('teste.gpx', $gpx);
$assert($parsedGpx['file_type'] === 'atividade', 'GPX track não detectado como atividade');
$assert($parsedGpx['modality_slug'] === 'corrida', 'modalidade GPX incorreta');
$assert(count($parsedGpx['series']) === 2, 'trackpoints GPX não foram lidos');
$assert(($parsedGpx['summary']['duration_s'] ?? null) === 60, 'duração GPX incorreta');

$gpxCourse = '<?xml version="1.0"?><gpx><trk><name>Percurso teste</name><trkseg>'
    . '<trkpt lat="-27.3600000" lon="-53.3900000"><ele>510</ele></trkpt>'
    . '<trkpt lat="-27.3610000" lon="-53.3910000"><ele>514</ele></trkpt>'
    . '</trkseg></trk></gpx>';
$parsedGpxCourse = atividadeArquivoAnalisar('percurso.gpx', $gpxCourse);
$assert($parsedGpxCourse['file_type'] === 'percurso', 'GPX sem timestamps deveria ser detectado como percurso');
$genericRun = str_replace('<type>running</type>', '', $gpx);
$parsedGenericRun = atividadeArquivoAnalisar('corrida.gpx', $genericRun);
$assert($parsedGenericRun['modality_slug'] === 'corrida', 'nome do arquivo GPX não ajudou a inferir corrida');

$tcx = '<?xml version="1.0"?><TrainingCenterDatabase><Activities><Activity Sport="Biking"><Id>2026-08-29T12:00:00Z</Id><Lap StartTime="2026-08-29T12:00:00Z"><TotalTimeSeconds>120</TotalTimeSeconds><DistanceMeters>1000</DistanceMeters><Track>'
    . '<Trackpoint><Time>2026-08-29T12:00:00Z</Time><Position><LatitudeDegrees>-27.36</LatitudeDegrees><LongitudeDegrees>-53.39</LongitudeDegrees></Position></Trackpoint>'
    . '<Trackpoint><Time>2026-08-29T12:02:00Z</Time><Position><LatitudeDegrees>-27.37</LatitudeDegrees><LongitudeDegrees>-53.40</LongitudeDegrees></Position></Trackpoint>'
    . '</Track></Lap></Activity></Activities></TrainingCenterDatabase>';
$parsedTcx = atividadeArquivoAnalisar('teste.tcx', $tcx);
$assert($parsedTcx['file_type'] === 'atividade', 'TCX não detectado como atividade');
$assert($parsedTcx['modality_slug'] === 'ciclismo', 'modalidade TCX incorreta');
$assert((int) ($parsedTcx['summary']['distance_m'] ?? 0) === 1000, 'distância TCX incorreta');

$fitRawTimestamp = strtotime('2026-08-29T12:00:00Z') - 631065600;
$definition = static function (int $local, int $global, array $fields): string {
    $data = chr(0x40 | ($local & 0x0F)) . chr(0) . chr(0) . pack('v', $global) . chr(count($fields));
    foreach ($fields as [$num, $size, $base]) $data .= chr($num) . chr($size) . chr($base);
    return $data;
};
$semicircle = static fn(float $degrees): int => (int) round($degrees * 2147483648.0 / 180.0);
$packSint32 = static fn(int $value): string => pack('V', $value & 0xFFFFFFFF);
$data = '';
$data .= $definition(0, 0, [[0,1,0],[1,2,0x84],[2,2,0x84],[3,4,0x8C],[4,4,0x86]]);
$data .= chr(0) . chr(4) . pack('v', 1) . pack('v', 123) . pack('V', 456) . pack('V', $fitRawTimestamp);
$data .= $definition(1, 18, [[2,4,0x86],[5,1,0],[6,1,0],[8,4,0x86],[9,4,0x86],[16,1,2],[22,2,0x84]]);
$data .= chr(1) . pack('V', $fitRawTimestamp) . chr(1) . chr(0) . pack('V', 600000) . pack('V', 200000) . chr(148) . pack('v', 35);
$data .= $definition(2, 20, [[0,4,0x85],[1,4,0x85],[253,4,0x86],[3,1,2]]);
$data .= chr(2) . $packSint32($semicircle(-27.36)) . $packSint32($semicircle(-53.39)) . pack('V', $fitRawTimestamp) . chr(145);
$data .= chr(2) . $packSint32($semicircle(-27.361)) . $packSint32($semicircle(-53.391)) . pack('V', $fitRawTimestamp + 60) . chr(151);
$header = chr(14) . chr(0x20) . pack('v', 0) . pack('V', strlen($data)) . '.FIT' . pack('v', 0);
$fit = $header . $data . pack('v', 0);
$parsedFit = atividadeArquivoAnalisar('teste.fit', $fit);
$assert($parsedFit['file_type'] === 'atividade', 'FIT file_id type não detectado');
$assert($parsedFit['modality_slug'] === 'corrida', 'modalidade FIT incorreta');
$assert((int) ($parsedFit['summary']['distance_m'] ?? 0) === 2000, 'distância FIT incorreta');
$assert((int) ($parsedFit['summary']['duration_s'] ?? 0) === 600, 'duração FIT incorreta');
$assert(count($parsedFit['series']) === 2, 'records FIT não foram lidos');

fwrite(STDOUT, "✓ activity exchange parser\n");
