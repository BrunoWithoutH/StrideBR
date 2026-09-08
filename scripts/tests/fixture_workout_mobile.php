<?php
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
if (PHP_SAPI !== 'cli' || getenv('STRIDEBR_DB_HOST') !== 'postgres' || getenv('STRIDEBR_APP_ENV') !== 'development') throw new RuntimeException('Local development CLI only');
$id = 'workout_mobile_test';
$pdo->beginTransaction();
$pdo->prepare("INSERT INTO usuarios (idusuario,nomeusuario,nome_exibicao,emailusuario,senhausuario,username,statususuario,onboarding_concluido) VALUES (:id,'Workout mobile','Workout mobile',:email,:password,:id,'Ativo',TRUE) ON CONFLICT (idusuario) DO NOTHING")
    ->execute([':id'=>$id, ':email'=>$id.'@alpha-test.invalid', ':password'=>stridebr_password_hash('Workout-mobile-123!')]);
$pdo->prepare('DELETE FROM sessoes_treino WHERE idusuario = :id')->execute([':id'=>$id]);
foreach (['history','active'] as $kind) {
    $session = 'wm_'.$kind;
    $pdo->prepare("INSERT INTO sessoes_treino (idsessao,idusuario,titulo_snapshot,status,data_inicio,data_fim) VALUES (:id,:user,'Academia',:status,NOW() - INTERVAL '20 minutes',:end)")
        ->execute([':id'=>$session, ':user'=>$id, ':status'=>$kind==='active'?'ativo':'concluido', ':end'=>$kind==='active'?null:'2026-08-27T13:00:00-03:00']);
    for ($i=1;$i<=10;$i++) {
        $ex=$session.'_'.$i;
        $name=['VOADOR DIRETO PEITORAL','LEVANTAMENTO TERRA ROMENO','DESENVOLVIMENTO COM HALTERES'][($i-1)%3];
        $count = $i === 10 ? 1 : 4;
        if ($i === 10) $name = 'Mobilidade';
        $pdo->prepare("INSERT INTO sessoes_treino_exercicios (idsessao_exercicio,idsessao,nome_snapshot,series_planejadas,repeticoes_snapshot,ordem) VALUES (:id,:session,:name,:count,:reps,:ord)")
            ->execute([':id'=>$ex,':session'=>$session,':name'=>$name,':ord'=>$i,':count'=>$count,':reps'=>$i===10?'20 min':'12']);
        for ($n=1;$n<=$count;$n++) $pdo->prepare('INSERT INTO sessoes_treino_series (idserie,idsessao_exercicio,numero,carga_realizada,concluida) VALUES (:id,:ex,:n,:load,:done)')
            ->execute([':id'=>$ex.'_'.$n,':ex'=>$ex,':n'=>$n,':load'=>$n===1?'10':null,':done'=>$kind==='history'?'true':'false']);
    }
}
$pdo->commit();
echo "Workout mobile fixture ready\n";
