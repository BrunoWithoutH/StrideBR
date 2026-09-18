<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/src/includes/app.php';
require_once dirname(__DIR__,2).'/src/function/workout_session_service.php';
$checks=0;
$assert=static function(bool $ok,string $message) use (&$checks):void { ++$checks; if (!$ok) throw new RuntimeException($message); };
foreach ([['20 min','duration',1200],['90 s','duration',90],['01:30','duration',90],['1 h','duration',3600],['1,5 km','distance','1500.000'],['500 m','distance','500.000']] as [$value,$field,$expected]) $assert(sessaoSerieMetrica($value,$field)===$expected,'Metric parser '.$value);
foreach (['duration','distance'] as $field) foreach (['invalid','-1','8–12','999999999999 h'] as $value) { $caught=false; try { sessaoSerieMetrica($value,$field); } catch (InvalidArgumentException) { $caught=true; } $assert($caught,'Invalid metric rejected'); }
$defaults=sessaoDefaultsPlanejados(['repeticoes_snapshot'=>'12','carga_snapshot'=>'10 kg','duracao_snapshot'=>'20 min','distancia_snapshot'=>'1,5 km']);
$assert($defaults===['reps'=>'12','load'=>'10 kg','duration'=>1200,'distance'=>'1500.000'],'Unequivocal completion defaults');
$assert(sessaoDefaultsPlanejados(['repeticoes_snapshot'=>'12 REPS'])['reps']==='12','Case-only reps unit parse');
$assert(sessaoDefaultsPlanejados(['repeticoes_snapshot'=>'8–12'])['reps']===null,'Range never becomes actual');
$assert(sessaoDefaultsPlanejados(['repeticoes_snapshot'=>'20 min'])['duration']===1200,'Legacy duration in reps uses same meaning');
$assert(sessaoDefaultsPlanejados(['repeticoes_snapshot'=>'500 metros'])['distance']==='500.000','Legacy distance in reps uses same meaning');
$columns=['load'=>'carga_realizada','reps'=>'repeticoes_realizadas','duration'=>'duracao_realizada_s','distance'=>'distancia_realizada_m'];
foreach ($columns as $field=>$column) {
 $sets=array_map(static fn($n)=>['idserie'=>(string)$n,$column=>'12','concluida'=>false],range(1,4));$defaults=['2'=>'12','3'=>'12','4'=>'12'];
 $assert(stridebr_workout_field_targets($sets,'1',$defaults,$field)===['2','3','4'],'Generic forward');
 $assert(stridebr_workout_field_targets($sets,'2',$defaults,$field)===['3','4'],'Previous sets excluded');
 $sets[2]['concluida']=true;$assert(stridebr_workout_field_targets($sets,'1',$defaults,$field)===['2','4'],'Completed preserved');
 $defaults['3']=null;$assert(stridebr_workout_field_targets($sets,'1',$defaults,$field)===['2'],'Manual boundary');
 $assert(stridebr_workout_field_targets($sets,'1',[],$field)===[],'Lost provenance conservative');
}
class WorkoutV2NoQueries extends PDO { public function __construct() {} public function prepare(string $query,array $options=[]):PDOStatement|false { throw new RuntimeException('Unexpected per-exercise catalog query'); } }
$catalog=[['idexercicio'=>'supino','nome'=>'Supino reto','slug'=>'supino-reto'],['idexercicio'=>'scott','nome'=>'Rosca Scott','slug'=>'rosca-scott']];
$rows=cronogramaHidratarExerciciosPlanejados(new WorkoutV2NoQueries(),'test',[['idexercicio'=>'supino','nome_snapshot'=>'OLD CAPS'],['nome_snapshot'=>'SUPINO RETO'],['nome_snapshot'=>'Rosca scut'],['nome_snapshot'=>'PUXADOR FRENT PR']],$catalog);
$assert($rows[0]['nome_snapshot']==='Supino reto'&&$rows[1]['idexercicio']==='supino','Linked and safe unlinked hydration');
$assert($rows[2]['nome_snapshot']==='Rosca scut'&&$rows[2]['nome_revisao']==='fuzzy','Fuzzy does not rewrite');
$assert($rows[3]['nome_snapshot']==='PUXADOR FRENT PR','Unknown abbreviations preserved');
$js=file_get_contents(dirname(__DIR__,2).'/public/assets/js/workout-session.js');
$assert(str_contains($js,'loggingFields(prescription)')&&str_contains($js,'data-session-set-${field}')&&!str_contains($js,'session-set-planned'),'Live fields are editable');
$assert(str_contains($js,'field !== document.activeElement && !field.dataset.pendingEdit'),'Sync preserves focused and pending edits');
echo "Workout Execution V2 static/domain passed ({$checks} assertions).\n";
