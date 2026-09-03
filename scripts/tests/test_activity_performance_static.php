<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$page = file_get_contents($root . '/public/user/atividades.php');
$js = file_get_contents($root . '/public/assets/js/atividades.js');
$presenter = file_get_contents($root . '/src/function/atividade_presenter.php');
$historyApi = file_get_contents($root . '/public/api/atividades-historico.php');
$detailApi = file_get_contents($root . '/public/api/atividade-detalhe.php');

$assertions = [
    'histórico inicial renderizado no servidor' => str_contains($page, 'data-initial-state=') && str_contains($page, 'atividadeHistoricoLinhaHtml($initialHistoryItem)'),
    'histórico SSR tem limite próprio de espera' => str_contains($page, '$historyStatementTimeout') && str_contains($page, "SET statement_timeout TO '{\$historyStatementTimeout}ms'"),
    'skeleton inicial não bloqueia o conteúdo' => str_contains($page, 'data-history-skeleton aria-label="Carregando histórico" hidden'),
    'resumo do histórico tem seletor próprio' => str_contains($page, 'data-history-summary') && str_contains($js, "document.querySelector('[data-history-summary]')"),
    'bootstrap reutiliza o histórico SSR' => str_contains($js, "if (initialHistoryState === 'ready' || initialHistoryState === 'empty')"),
    'fallback de histórico não usa retry automático' => str_contains($js, '{timeout: 7500, retries: 0}'),
    'detalhes não fazem prefetch por hover' => !str_contains($js, "addEventListener('pointerover'") && !str_contains($js, "addEventListener('focusin'"),
    'API de histórico falha rápido' => str_contains($historyApi, "statement_timeout TO '6500ms'") && str_contains($historyApi, "lock_timeout TO '1500ms'"),
    'API de detalhe falha rápido' => str_contains($detailApi, "statement_timeout TO '6500ms'") && str_contains($detailApi, "lock_timeout TO '1500ms'"),
    'resumo filtra registros antes de agregar métricas' => str_contains($presenter, 'registros_periodo AS MATERIALIZED') && str_contains($presenter, 'FROM registros_periodo rp'),
];

$failed = [];
foreach ($assertions as $name => $passed) {
    if (!$passed) $failed[] = $name;
}

if ($failed !== []) {
    fwrite(STDERR, "Falhas em performance de atividades:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo '✓ activity performance static: ' . count($assertions) . " assertions\n";
