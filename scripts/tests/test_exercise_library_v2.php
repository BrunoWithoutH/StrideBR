<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/src/function/cronograma.php';
return function(PDO $pdo):void{
 $count=(int)$pdo->query("SELECT COUNT(*) FROM exercicios WHERE idusuario IS NULL AND ativo=TRUE")->fetchColumn();
 AlphaTest::assert($count>=652,'global catalog seeded');
 AlphaTest::same(0,(int)$pdo->query("SELECT COUNT(*) FROM (SELECT lower(slug),COUNT(*) c FROM exercicios WHERE idusuario IS NULL GROUP BY lower(slug) HAVING COUNT(*)>1) q")->fetchColumn(),'global slugs unique');
 AlphaTest::assert((bool)$pdo->query("SELECT to_regclass('stridebr.exercicios_aliases') IS NOT NULL")->fetchColumn(),'aliases table exists');
 AlphaTest::assert((int)$pdo->query("SELECT COUNT(*) FROM exercicios_aliases")->fetchColumn()>20,'aliases seeded');
 $user=alphaTestUser($pdo,'exercise_library_v2');
 $catalog=stridebr_exercise_catalog_for_user($pdo,$user);
 $result=stridebr_exercise_resolve_entry($catalog,['nome'=>'leg extension']);
 AlphaTest::same('matched',$result['status'],'English safe alias resolves');
 AlphaTest::same('Cadeira extensora',$result['match']['nome'],'English alias canonical');
 $suggest=stridebr_exercise_resolve_entry($catalog,['nome'=>'PUXADOR FRENT PR']);
 AlphaTest::same('suggest',$suggest['status'],'ambiguous abbreviation remains suggestion');
 $unknown=stridebr_exercise_resolve_entry($catalog,['nome'=>'TRICEPS COICE CR']);
 AlphaTest::same('unknown',$unknown['status'],'unknown abbreviation stays unknown');
 $personal=cronogramaCriarExercicioCompleto($pdo,$user,'Alpha Custom Curl',null,[],['m_musculacao'],null,null,['alpha curl'],'halteres','load_reps');
 $personalResolved=stridebr_exercise_resolve_entry(stridebr_exercise_catalog_for_user($pdo,$user),['nome'=>'alpha curl']);
 AlphaTest::same($personal,$personalResolved['match']['idexercicio'],'personal alias resolves without losing owner exercise');
};
