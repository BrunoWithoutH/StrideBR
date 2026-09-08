<?php
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/function/planejamento.php';
function planningAssert($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$items = [];
foreach (['2026-09-07', '2026-09-09', '2026-09-11'] as $date) $items[] = ['titulo' => 'Academia', 'data_treino' => $date];
$activities = [];
foreach (['2026-09-08', '2026-09-10', '2026-09-12'] as $date) $activities[] = ['idregistro' => $date, 'titulo' => 'Corrida', 'data_inicio' => $date];
$summary = planejamentoResumir($items, $activities, '2026-09-07', '2026-09-13', '2026-09-14');
planningAssert($summary['completed'] === 0 && $summary['missed'] === 3 && count($summary['extra']) === 3, 'Corridas não concluem academia');
$items = [
 ['titulo'=>'Academia', 'data_treino'=>'2026-09-07', 'concluido'=>true, 'idregistro'=>'a'],
 ['titulo'=>'Corrida', 'data_treino'=>'2026-09-08', 'concluido'=>true, 'idregistro'=>'b'],
 ['titulo'=>'Academia', 'data_treino'=>'2026-09-10'],
 ['titulo'=>'Corrida', 'data_treino'=>'2026-09-12'],
];
$summary = planejamentoResumir($items, [['idregistro'=>'extra','data_inicio'=>'2026-09-11']], '2026-09-07','2026-09-13','2026-09-11');
planningAssert($summary['completed']===2 && $summary['missed']===1 && $summary['todo']===1 && count($summary['extra'])===1, 'Semana mista');
$shifted = ['titulo'=>'Academia','data_planejada'=>'2026-09-09','data_treino'=>'2026-09-10','data_realizada'=>'2026-09-10','concluido'=>true,'realizado_fora_planejado'=>true,'idregistro'=>'linked'];
$summary = planejamentoResumir([$shifted], [['idregistro'=>'linked','data_inicio'=>'2026-09-10']], '2026-09-07','2026-09-13','2026-09-14');
planningAssert($summary['completed']===1 && $summary['missed']===0 && !$summary['extra'] && $summary['items'][0]['planning_state']==='shifted','Realização em outra data não vira falta nem extra');
planningAssert(planejamentoEstado(['data_treino'=>'2026-09-11'],'2026-09-11')==='todo','Hoje não é falta');
planningAssert(planejamentoEstado(['data_treino'=>'2026-09-07','status'=>'cancelado'],'2026-09-14')==='cancelled','Cancelamento não é falta');
echo "✓ planning week: critical, mixed, shifted, today and cancelled scenarios\n";
