<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/src/function/exercise_resolver.php';
$checks=0;
$assert=static function(bool $ok,string $message) use (&$checks):void{$checks++;if(!$ok)throw new RuntimeException($message);};
$catalogFile=dirname(__DIR__,2).'/src/data/exercise_catalog_v2.json';
$data=json_decode((string)file_get_contents($catalogFile),true,512,JSON_THROW_ON_ERROR);
$raw=$data['exercises']??[];
$catalog=array_map(static function(array $item):array{return ['idexercicio'=>$item['id'],'nome'=>$item['name'],'slug'=>$item['slug'],'idusuario'=>null,'equipamento'=>$item['equipment']??null,'tipo_registro'=>$item['tracking_mode']??'load_reps','grupos_musculares_primarios'=>$item['primary_muscles']??[],'grupos_musculares_secundarios'=>$item['secondary_muscles']??[],'aliases'=>$item['aliases']??[]];},$raw);
$assert(count($catalog)>=600&&count($catalog)<=900,'catálogo deve permanecer entre 600 e 900 exercícios úteis');
$assert(count($catalog)===(int)($data['count']??0),'count do catálogo deve refletir os registros');
$slugs=[];$names=[];$groups=[];$tracking=[];$equipment=[];
foreach($catalog as $row){$slug=(string)$row['slug'];$name=stridebr_normalize_exercise_name((string)$row['nome']);$assert($slug!==''&&!isset($slugs[$slug]),'slug global duplicado: '.$slug);$assert($name!==''&&!isset($names[$name]),'nome global duplicado: '.$row['nome']);$slugs[$slug]=true;$names[$name]=true;foreach($row['grupos_musculares_primarios'] as $group)$groups[$group]=($groups[$group]??0)+1;$tracking[$row['tipo_registro']]=($tracking[$row['tipo_registro']]??0)+1;if($row['equipamento'])$equipment[$row['equipamento']]=true;}
foreach(['peito'=>30,'costas'=>30,'ombros'=>20,'biceps'=>20,'triceps'=>20,'quadriceps'=>25,'posteriores'=>20,'gluteos'=>15,'panturrilha'=>12,'core'=>30] as $group=>$minimum)$assert(($groups[$group]??0)>=$minimum,'cobertura insuficiente: '.$group);
foreach(['load_reps','reps','duration','distance','duration_distance'] as $mode)$assert(($tracking[$mode]??0)>0,'tracking ausente: '.$mode);
foreach(['barra','halteres','maquina','cabo','smith','peso-corporal','kettlebell','elastico','trx','medicine-ball','hack-squat','leg-press'] as $item)$assert(isset($equipment[$item]),'equipamento ausente: '.$item);
$fixture=json_decode((string)file_get_contents(__DIR__.'/fixtures/exercise_library/academia_abc_legacy.json'),true,512,JSON_THROW_ON_ERROR);
foreach($fixture['labels'] as $case){$result=stridebr_exercise_resolve_entry($catalog,['nome'=>$case['input']]);$assert(($result['status']??'')===$case['status'],'classificação legacy incorreta: '.$case['input']);if($case['canonical']!==null){$candidate=$result['match']['nome']??$result['suggestions'][0]['nome']??null;$assert($candidate===$case['canonical'],'canonical legacy incorreto: '.$case['input']);}}
foreach($fixture['duration_cases'] as $case){$result=stridebr_exercise_resolve_entry($catalog,['nome'=>$case['exercise']]);$row=stridebr_exercise_repair_legacy_metrics(['repeticoes'=>$case['repeticoes'],'duracao'=>null],$result);$assert(($row['duracao_segundos_legacy']??null)===$case['seconds'],'duração legacy incorreta: '.$case['repeticoes']);$assert(array_key_exists('repeticoes',$row)&&$row['repeticoes']===null,'reps temporal deve ser limpo');}
$assert(stridebr_exercise_parse_legacy_duration('20 min')===1200,'20 min');$assert(stridebr_exercise_parse_legacy_duration('45s')===45,'45s');$assert(stridebr_exercise_parse_legacy_duration('lixo')===null,'input inválido');
$search=stridebr_exercise_search_catalog($catalog,'leg extension',[],8);$assert(($search[0]['nome']??'')==='Cadeira extensora','busca por alias EN');
$search=stridebr_exercise_search_catalog($catalog,'cad ext',[],8);$assert(in_array('Cadeira extensora',array_column($search,'nome'),true),'busca parcial por alias');
$library=(string)file_get_contents(dirname(__DIR__,2).'/public/user/biblioteca.php');
$picker=(string)file_get_contents(dirname(__DIR__,2).'/public/user/exercicioscronograma.php').(string)file_get_contents(dirname(__DIR__,2).'/public/user/exerciciostreinomodelo.php').(string)file_get_contents(dirname(__DIR__,2).'/src/layout/workout_builder.php');
$pickerJs=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/workout-builder.js');
$assert(str_contains($library,'$exercisePerPage = 80'),'biblioteca deve paginar 80 itens');$assert(str_contains($library,'data-exercise-library-filters'),'biblioteca deve ter toolbar de filtros');$assert(str_contains($library,'data-exercise-detail-dialog'),'biblioteca deve ter detalhe sob demanda');$assert(!str_contains($picker,'data-library-select'),'picker não deve renderizar select com catálogo completo');$assert(str_contains($picker,'data-exercise-id')&&str_contains($pickerJs,'/api/exercicio-resolver.php?name=')&&str_contains($pickerJs,"q(card, '[data-exercise-id]')"),'picker deve resolver busca remota e manter identidade canônica oculta separada do nome');
printf("Exercise Library V2 static/domain: %d assertions; %d exercises\n",$checks,count($catalog));
