<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$i18n = (string) file_get_contents($root . '/src/includes/i18n.php');
$boot = (string) file_get_contents($root . '/public/assets/js/ui-boot.js');
require_once $root . '/src/includes/http_headers.php';
$htaccess = stridebr_content_security_policy();
$diagnostics = (string) file_get_contents($root . '/public/admin/diagnostics.php');
$rc = (string) file_get_contents($root . '/src/database/migrations/20260903_v1_rc.sql');

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "Falha produção/theme/migrations: {$message}\n");
        exit(1);
    }
};

$assert(str_contains($htaccess, "script-src 'self' https://unpkg.com"), 'CSP de produção deve continuar sem liberar script inline.');
$assert(str_contains($i18n, "stridebr_asset('/assets/js/ui-boot.js')"), 'boot de tema deve usar arquivo JS same-origin.');
$assert(!str_contains($i18n, '<script data-stridebr-ui-boot>(function()'), 'boot inline antigo não pode voltar.');
$assert(str_contains($boot, 'document.currentScript'), 'boot externo deve ler as preferências renderizadas pelo servidor.');
$assert(str_contains($boot, "root.dataset.theme = theme"), 'boot externo deve aplicar data-theme.');
$assert(str_contains($boot, "root.dataset.themeMode = theme"), 'boot externo deve aplicar data-theme-mode.');
$assert(str_contains($diagnostics, "preg_match_all('/^-- Consolidated from:"), 'diagnóstico deve reconhecer migrations consolidadas pela RC.');
$assert(str_contains($diagnostics, '$currentApplied'), 'diagnóstico deve contar apenas migrations atuais no indicador.');
$assert(str_contains($diagnostics, '$orphanHistory'), 'diagnóstico deve manter alerta para histórico realmente órfão.');
$assert(str_contains($diagnostics, '$orphanHistory === []'), 'histórico órfão deve impedir readiness verde.');
preg_match_all('/^-- Consolidated from:\s*([^\r\n]+\.sql)\s*$/m', $rc, $matches);
$consolidated = array_values(array_map('basename', $matches[1] ?? []));
$assert(count($consolidated) === 36, 'RC deve expor as 36 migrations intermediárias consolidadas desta release.');
$current = array_values(array_map('basename', glob($root . '/src/database/migrations/*.sql') ?: []));
$appliedSimulation = array_values(array_unique(array_merge($current, $consolidated)));
$unknownSimulation = array_values(array_diff($appliedSimulation, $current));
$consolidatedSimulation = array_values(array_intersect($unknownSimulation, $consolidated));
$orphanSimulation = array_values(array_diff($unknownSimulation, $consolidated));
$assert(count($current) === 7, 'deploy atual deve possuir as migrations consolidadas e as migrations aditivas de precisão de duração e providers de integração.');
$assert(count($appliedSimulation) === 43, 'histórico após as migrations aditivas deve totalizar 43 registros.');
$assert(count($consolidatedSimulation) === 36, 'os 36 registros sem arquivo devem ser reconhecidos como consolidados.');
$assert($orphanSimulation === [], 'o histórico consolidado mais a migration aditiva não deve produzir registros órfãos.');

printf("✓ production theme/migrations static: %d assertions\n", $checks);
