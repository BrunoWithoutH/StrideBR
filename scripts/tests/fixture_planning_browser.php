<?php
// Local Docker only. Never use the configured remote database for browser fixtures.
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
require_once dirname(__DIR__, 2) . '/src/function/treinador.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
if (getenv('STRIDEBR_DB_HOST') !== 'postgres' || PHP_SAPI !== 'cli') throw new RuntimeException('Local CLI only');
$ids = ['round_ux_coach', 'round_ux_athlete', 'round_ux_empty', 'round_ux_unlinked', 'round_ux_pending'];
foreach ($ids as $id) {
    $stmt = $pdo->prepare('INSERT INTO usuarios (idusuario,nomeusuario,nome_exibicao,emailusuario,senhausuario,username,statususuario,onboarding_concluido,modo_treinador) VALUES (:id,:nome,:nome,:email,:password,:username,\'Ativo\',TRUE,:trainer) ON CONFLICT (idusuario) DO UPDATE SET nomeusuario = EXCLUDED.nomeusuario, nome_exibicao = EXCLUDED.nome_exibicao');
    $stmt->execute([':id'=>$id, ':nome'=>str_replace('_', ' ', $id), ':email'=>$id.'@alpha-test.invalid', ':password'=>stridebr_password_hash('Planning-browser-123!'), ':username'=>$id, ':trainer'=>$id === 'round_ux_coach' ? 'true':'false']);
}
if (!treinadorVinculoAceito($pdo,$ids[0],$ids[1])) {
    $link = treinadorCriarConvite($pdo,$ids[0],$ids[1],'treinador');
    treinadorResponderVinculo($pdo,$ids[1],$link,'aceitar');
}
$schedules = cronogramaListar($pdo,$ids[1]);
if (!$schedules) {
    $schedule = cronogramaCriar($pdo,$ids[1],'Academia · plano semanal');
    foreach ([1,3,5] as $day) cronogramaSalvarTreino($pdo,$ids[1],['idcronograma'=>$schedule,'titulo'=>'Academia','dia_semana'=>$day,'hora_inicio'=>'08:00','hora_fim'=>'09:00','vigencia_inicio'=>'2026-09-01']);
} else $schedule = $schedules[0]['idcronograma'];
$pdo->prepare("UPDATE treinos_cronograma SET vigencia_inicio = '2026-08-01' WHERE idcronograma = :id")->execute([':id'=>$schedule]);
if (!treinadorVinculoAceito($pdo,$ids[0],$ids[2])) {
    $secondLink = treinadorCriarConvite($pdo,$ids[0],$ids[2],'treinador');
    treinadorResponderVinculo($pdo,$ids[2],$secondLink,'aceitar');
    treinadorAtualizarPermissoes($pdo,$ids[2],$secondLink,[]);
}
$model = $pdo->query("SELECT mm.idmodelo FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade WHERE m.slug = 'corrida' AND mm.ativo = TRUE ORDER BY mm.padrao DESC LIMIT 1")->fetchColumn();
if ($model) foreach ([1,3,5] as $offset) {
    $date = (new DateTimeImmutable('monday last week'))->modify('+' . $offset . ' days')->format('Y-m-d');
    $exists = $pdo->prepare("SELECT 1 FROM registros_atividade WHERE idusuario=:usuario AND titulo=:titulo AND excluido_em IS NULL");
    $title = 'Corrida extra ' . $date;
    $exists->execute([':usuario'=>$ids[1], ':titulo'=>$title]);
    if (!$exists->fetchColumn()) atividadeSalvarRegistro($pdo,$ids[1],['idmodelo'=>$model,'titulo'=>$title,'data_inicio'=>$date . ' 17:00','status'=>'concluido','visibilidade'=>'privado']);
}
$existing = $pdo->prepare("SELECT 1 FROM treinos_agendados WHERE idcriador=:coach AND idatleta=:athlete AND titulo='Intervalado 6 × 400 m'");
$existing->execute([':coach'=>$ids[0], ':athlete'=>$ids[1]]);
if (!$existing->fetchColumn()) treinadorCriarPrescricao($pdo,$ids[0],$ids[1],['titulo'=>'Intervalado 6 × 400 m','data_treino'=>(new DateTimeImmutable('tomorrow'))->format('Y-m-d'),'hora_inicio'=>'18:00','status'=>'publicado'],[['nome'=>'Corrida','series'=>6,'repeticoes'=>'400 m']]);
$pending = $pdo->prepare("SELECT 1 FROM vinculos_treinador_atleta WHERE idtreinador=:coach AND idatleta=:athlete AND status IN ('pendente','aceito')");
$pending->execute([':coach'=>$ids[0],':athlete'=>$ids[4]]);
if (!$pending->fetchColumn()) treinadorCriarConvite($pdo,$ids[0],$ids[4],'treinador');
echo json_encode(['coach'=>$ids[0], 'athlete'=>$ids[1], 'schedule'=>$schedule]);
