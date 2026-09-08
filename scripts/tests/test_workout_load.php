<?php
require_once dirname(__DIR__, 2) . '/src/function/workout_load.php';
$sets = array_map(fn($n)=>['idserie'=>(string)$n,'carga_realizada'=>$n===1?'10':'','concluida'=>false],range(1,4));
$check = static function($actual,$expected) { if ($actual !== $expected) throw new RuntimeException(json_encode([$actual,$expected])); };
$check(stridebr_workout_load_targets($sets,'1',[]),['2','3','4']);
foreach ($sets as &$s) $s['carga_realizada']='15'; unset($s);
$defaults=['2'=>'15','3'=>'15','4'=>'15'];
$check(stridebr_workout_load_targets($sets,'2',$defaults),['3','4']);
$defaults['3']=null;
$check(stridebr_workout_load_targets($sets,'1',$defaults),['2']);
$defaults['3']='15';$sets[2]['concluida']=true;
$check(stridebr_workout_load_targets($sets,'1',$defaults),['2','4']);
$check(stridebr_workout_load_targets($sets,'1',[]),[]);
$sets[2]['carga_realizada']='25';
$check(stridebr_workout_load_targets($sets,'1',$defaults),['2']);
$sets[2]['carga_realizada']='';$defaults['3']=null;
$check(stridebr_workout_load_targets($sets,'1',$defaults),['2']);
$check(stridebr_workout_load_targets($sets,'missing',$defaults),[]);
echo "✓ workout load: forward, manual boundaries (including empty), completed, unknown and stale defaults\n";
require_once dirname(__DIR__, 2) . '/src/includes/i18n.php';
foreach (['pt-BR'=>['exercício','exercícios','série','séries'],'en'=>['exercise','exercises','set','sets']] as $locale=>$words) {
    $dict = stridebr_js_i18n_dictionary($locale);
    foreach (['exercise_unit.one','exercise_unit.other','set_unit.one','set_unit.other'] as $i=>$key) $check($dict['workout_session.'.$key] ?? null,$words[$i]);
}
echo "✓ workout plural keys exported in PT/EN\n";
