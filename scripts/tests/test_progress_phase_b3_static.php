<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/includes/app.php';
require_once $root . '/src/function/competitions.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Progress Phase B3 failed: {$message}\n");
        exit(1);
    }
};
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$migration = $read('src/database/migrations/20260911_user_competitions.sql');
$domain = $read('src/function/competitions.php');
$benchmarks = $read('src/function/benchmarks.php');
$benchmarkApi = $read('public/api/progress-benchmarks.php');
$progress = $read('public/user/progresso.php');
$progressJs = $read('public/assets/js/progresso.js');
$competitionPage = $read('public/user/competicoes.php');
$competitionCss = $read('public/assets/css/competitions.css');
$activityModel = $read('src/function/atividade_modelo.php');
$activityLayout = $read('src/layout/activity_log_details.php');
$activityJs = $read('public/assets/js/atividades.js');
$activityCreate = $read('public/user/atividades.php');
$activityEdit = $read('public/user/editatividade.php');
$eventPage = $read('public/evento.php');
$account = $read('src/function/account_data.php');
$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');
$b1Migration = $read('src/database/migrations/20260911_progress_benchmarks.sql');
$b2Migration = $read('src/database/migrations/20260911_typed_sport_goals.sql');

$assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS competicoes_usuario'), 'B3 deve criar entidade pessoal de competição própria.');
$assert(str_contains($migration, 'idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE'), 'Competição deve pertencer ao usuário e ser removida com a conta.');
$assert(str_contains($migration, 'nome VARCHAR(160) NOT NULL') && str_contains($migration, 'BETWEEN 3 AND 160'), 'Nome deve ter faixa de tamanho segura.');
$assert(str_contains($migration, 'data_inicio DATE NOT NULL') && str_contains($migration, 'data_fim DATE'), 'Competição deve usar datas esportivas, não timestamps desnecessários.');
$assert(str_contains($migration, 'idmodalidade_principal VARCHAR(21) REFERENCES modalidades') && str_contains($migration, 'ON DELETE SET NULL'), 'Modalidade principal deve ser opcional e segura à exclusão.');
$assert(str_contains($migration, 'idevento VARCHAR(21) REFERENCES eventos_esportivos(idevento) ON DELETE SET NULL'), 'Evento público deve ser vínculo opcional com SET NULL.');
$assert(str_contains($migration, "status IN ('planejada', 'realizada', 'cancelada')"), 'B3 deve manter apenas os três status previstos.');
$assert(str_contains($migration, "origem IN ('manual', 'catalogo', 'importacao', 'api')"), 'Origem deve permanecer separada da oficialidade.');
$assert(str_contains($migration, "oficialidade IN ('nao_informada', 'nao_oficial', 'informado_oficial', 'verificado')"), 'Oficialidade deve preservar filosofia da B1.');
$assert(str_contains($migration, "NOT (origem = 'manual' AND oficialidade = 'verificado')"), 'Entrada manual nunca pode marcar competição como verificada.');
$assert(str_contains($migration, 'ADD COLUMN IF NOT EXISTS idcompeticao') && substr_count($migration, 'ON DELETE SET NULL') >= 4, 'Atividades e benchmarks devem ganhar vínculo nullable com SET NULL.');
$assert(str_contains($migration, 'ix_registros_atividade_competicao') && str_contains($migration, 'ix_benchmarks_usuario_competicao'), 'Consultas por competição precisam de índices nas fontes originais.');
$assert(str_contains($migration, 'ix_competicoes_usuario_data') && str_contains($migration, 'ix_competicoes_usuario_status_data') && str_contains($migration, 'ix_competicoes_usuario_evento') && str_contains($migration, 'ix_competicoes_usuario_modalidade'), 'Entidade deve ter índices para data/status/evento/modalidade.');
$assert(!str_contains($migration, 'UNIQUE (idusuario, idevento)') && !str_contains($migration, 'ux_competicoes_usuario_evento'), 'B3 não deve bloquear participações legítimas com unique rígido usuário+evento.');
$assert(!str_contains($migration, 'INSERT INTO competicoes_usuario') && !str_contains($migration, 'UPDATE registros_atividade SET idcompeticao'), 'Migration não pode fabricar participação ou backfill falso.');
$assert(str_contains($migration, 'SET search_path TO stridebr, public;') && str_contains($migration, 'ADD COLUMN IF NOT EXISTS'), 'Migration deve seguir padrão incremental/idempotente do projeto.');
$assert(!str_contains($b1Migration, 'competicoes_usuario') && !str_contains($b2Migration, 'competicoes_usuario'), 'B3 não deve editar/reimplementar migrations B1/B2.');

$assert(competitionStatusLabel('planejada') === stridebr_t('competitions.status.planejada'), 'Status planejada deve ser traduzido pelo domínio.');
$assert(competitionStatusLabel('realizada') === stridebr_t('competitions.status.realizada'), 'Status realizada deve ser traduzido pelo domínio.');
$assert(competitionStatusLabel('cancelada') === stridebr_t('competitions.status.cancelada'), 'Status cancelada deve ser traduzido pelo domínio.');
$assert(competitionDateRelation(['data_inicio'=>'2026-10-12','data_fim'=>'2026-10-16'], '2026-10-14') === 'inside', 'Data dentro da competição deve ser detectada.');
$assert(competitionDateRelation(['data_inicio'=>'2026-10-12','data_fim'=>'2026-10-16'], '2026-10-18') === 'near', 'Diferença pequena deve ser tratada como próxima, não rejeição rígida.');
$assert(competitionDateRelation(['data_inicio'=>'2026-10-12','data_fim'=>'2026-10-16'], '2026-11-20') === 'outside', 'Discrepância grande de data deve ser detectável.');
$assert(str_contains($domain, "['planejada', 'realizada', 'cancelada']"), 'Normalização deve validar status no backend.');
$assert(str_contains($domain, "['manual', 'catalogo', 'importacao', 'api']"), 'Normalização deve validar origem no backend.');
$assert(str_contains($domain, "['nao_informada', 'nao_oficial', 'informado_oficial', 'verificado']"), 'Normalização deve validar oficialidade no backend.');
$assert(str_contains($domain, "\$origin === 'manual' && \$officiality === 'verificado'"), 'Domínio deve rejeitar verificado manual independentemente da UI.');
$assert(str_contains($domain, 'competitionModalityRow') && str_contains($domain, 'competitionPublicEventRow'), 'Backend deve validar modalidade acessível e evento público existente.');
$assert(str_contains($domain, 'competitionValidateOwned') && str_contains($domain, 'c.idusuario=:usuario'), 'Ownership da competição deve ser validado server-side.');
$assert(str_contains($domain, 'function competitionLinkActivity') && str_contains($domain, 'WHERE idregistro=:registro AND idusuario=:usuario'), 'Link de atividade deve checar ownership.');
$assert(str_contains($domain, 'function competitionLinkBenchmark') && str_contains($domain, 'activity_competition'), 'Link de benchmark deve respeitar competição herdada da atividade.');
$assert(str_contains($domain, 'benchmark_activity_competition') && str_contains($domain, 'benchmark_conflict'), 'Domínio deve rejeitar segunda fonte de verdade para benchmark ligado a atividade.');
$assert(str_contains($domain, 'function competitionNearby') && str_contains($domain, "INTERVAL '365 days'"), 'Select contextual deve limitar competições por proximidade, sem listar histórico infinito.');
$assert(str_contains($domain, 'function competitionFindByEvent'), 'Criação por catálogo deve conseguir reutilizar participação já existente.');
$assert(str_contains($domain, 'function competitionPrefillFromEvent') && str_contains($domain, "'origem'=>'catalogo'"), 'Evento público deve apenas pré-preencher snapshot pessoal e origem catálogo.');
$assert(str_contains($domain, 'LEFT JOIN eventos_esportivos e') && str_contains($domain, 'evento_titulo'), 'Leitura deve manter evento relacionado como referência separada.');
$assert(str_contains($domain, 'competitionBenchmarks') && str_contains($domain, 'ra.idcompeticao=:competicao'), 'Detalhe deve incluir benchmark inferido por atividade da competição.');

$assert(str_contains($activityModel, "'idcompeticao'") && str_contains($activityModel, 'competitionValidateOwned'), 'Salvar atividade deve aceitar competição e validar ownership.');
$assert(str_contains($activityModel, "idcompeticao = :competicao") && str_contains($activityModel, 'idcompeticao'), 'Create/update de atividade devem persistir FK da competição.');
$assert(str_contains($activityLayout, 'data-toggle-log-detail="competition"') && str_contains($activityLayout, 'name="idcompeticao"'), 'Competição deve ficar em Detalhes da atividade, não como select gigante permanente.');
$assert(str_contains($activityLayout, 'data-competition-start') && str_contains($activityLayout, 'data-competition-date-warning') && str_contains($activityJs, 'syncCompetitionDateWarning'), 'Atividade deve avisar quando a data ficar fora da janela da competição sem associar automaticamente.');
$assert(str_contains($activityLayout, "stridebr_t('competitions.none')") && str_contains($activityLayout, '/user/competicoes.php?new=1'), 'Editor deve permitir remover vínculo e registrar nova competição.');
$assert(str_contains($activityCreate, 'competitionNearby') && str_contains($activityEdit, 'competitionNearby'), 'Criação/edição deve oferecer somente competições próximas/recentes.');
$assert(str_contains($activityEdit, "\$registro['idcompeticao']") && str_contains($activityLayout, 'selected'), 'Edição deve preservar competição atual e permitir troca/remoção.');

$assert(str_contains($benchmarks, "'competition_id'") && str_contains($benchmarks, 'COALESCE(ac.nome,dc.nome)'), 'Benchmark deve expor competição efetiva, herdando atividade primeiro.');
$assert(str_contains($benchmarks, '$hasActivityEvidence') && str_contains($benchmarks, "\$competitionId = '';"), 'Benchmark com atividade não deve persistir competição duplicada.');
$assert(str_contains($benchmarks, 'benchmark_activity_competition') && str_contains($benchmarks, 'benchmark_conflict'), 'Benchmark deve rejeitar vínculo direto conflitante com evidência.');
$assert(str_contains($benchmarks, 'LEFT JOIN competicoes_usuario ac') && str_contains($benchmarks, 'LEFT JOIN competicoes_usuario dc'), 'Histórico B1 deve conseguir mostrar competição efetiva.');
$assert(str_contains($benchmarkApi, 'official_competition_required'), 'Novos resultados oficiais manuais devem exigir competição estruturada.');
$assert(str_contains($benchmarkApi, 'benchmarkActivityEvidence') && str_contains($benchmarkApi, "existingEvidence['competition_id']"), 'Oficial ligado a atividade deve aceitar a competição herdada sem FK duplicada.');
$assert(str_contains($progress, 'data-progress-benchmark-competition-field') && str_contains($progress, 'benchmark_activity_help'), 'Form B1 deve mostrar competição contextual e explicar herança da atividade.');
$assert(str_contains($progress, "disabled aria-disabled=\"true\"") && str_contains($progress, 'edit_related_activity'), 'Benchmark ligado à atividade deve impedir seleção direta concorrente na UI.');
$assert(str_contains($progressJs, 'competition.required = !competition.disabled'), 'JS deve manter oficialidade acessível sem exigir select desabilitado.');
$assert(str_contains($benchmarks, 'benchmarkValidateOfficialContext($officiality, $context)'), 'B3 não pode regredir hotfix oficial=Competição da B1.');

$assert(str_contains($competitionPage, "stridebr_t('competitions.title')") && str_contains($competitionPage, "stridebr_t('competitions.register')"), 'B3 deve criar central Minhas competições.');
$assert(str_contains($competitionPage, "stridebr_t('competitions.upcoming')") && str_contains($competitionPage, "stridebr_t('competitions.recent')"), 'Lista deve separar próximas/recentes sem dashboard gigante.');
$assert(str_contains($competitionPage, 'competition-detail') && str_contains($competitionPage, 'competitionActivities') && str_contains($competitionPage, 'competitionBenchmarks'), 'Detalhe deve mostrar atividades e marcas sem duplicar resultados.');
$assert(str_contains($competitionPage, "stridebr_t('competitions.delete_confirm')") && str_contains($competitionPage, "stridebr_t('competitions.delete_help')"), 'Excluir competição deve explicar que evidências permanecem salvas.');
$assert(str_contains($competitionPage, 'competitionFindByEvent') && str_contains($competitionPage, 'competitionPrefillFromEvent'), 'Fluxo por evento público deve reaproveitar existente ou pré-preencher.');
$assert(str_contains($eventPage, "stridebr_t('competitions.register_participation')") && str_contains($eventPage, '/user/competicoes.php?new=1&event='), 'Evento público deve oferecer Registrar minha participação, sem fingir inscrição.');
$assert(str_contains($eventPage, "stridebr_t(stridebr_db_bool(\$event['salvo']) ? 'event.saved' : 'event.save')"), 'Ação de participação não pode substituir comportamento de evento salvo.');
$assert(!str_contains($competitionPage, 'ranking') && !str_contains($competitionPage, 'medalha') && !str_contains($competitionPage, 'colocacao'), 'B3 não deve abrir ranking/medalha/colocação.');
$assert(str_contains($competitionCss, '.competitions-page') && str_contains($competitionCss, '@media(max-width:800px)') && str_contains($competitionCss, '@media(max-width:520px)'), 'Página de competições deve ser densa e responsiva.');

$assert(str_contains($progress, 'competitionRecentForProgress') && str_contains($progress, 'progress-competitions-section'), 'Progresso deve integrar competições recentes de forma discreta.');
$assert(str_contains($progress, "stridebr_t('competitions.recent_progress')") && str_contains($progress, "stridebr_t('competitions.view_all')"), 'Seção compacta deve ter copy traduzida e link para drill-down.');
$assert(str_contains($progress, "['idmodalidade']") && str_contains($progress, '$athleticsEvents'), 'Atletismo deve filtrar competições pelas modalidades/provas reais, sem filtro global novo.');
$assert(!str_contains($progress, 'name="competition_filter"') && !str_contains($progress, 'data-progress-competition-filter'), 'B3 não deve adicionar select global de competição ao topo de Progresso.');
$assert(str_contains($progress, "record['competicao_nome']") && str_contains($progress, '$competitionName'), 'Históricos B1 devem exibir competição quando houver.');

$assert(str_contains($account, "'competicoes' =>") && str_contains($account, 'competicoes_usuario'), 'Exportação deve incluir competições pessoais.');
$assert(str_contains($account, "SELECT c.*") && str_contains($account, 'evento_titulo'), 'Exportação deve preservar dados próprios e referência ao catálogo.');
$assert(str_contains($migration, 'ON DELETE CASCADE') && str_contains($migration, 'ON DELETE SET NULL'), 'Exclusão de conta deve limpar competição sem apagar evento/atividade/benchmark.');
$assert(!str_contains($competitionPage, 'publicar') && !str_contains($competitionPage, 'visibilidade'), 'Competição pessoal deve permanecer privada nesta B3.');

foreach (['competitions.title','competitions.mine','competitions.register','competitions.edit','competitions.status.planejada','competitions.status.realizada','competitions.status.cancelada','competitions.report_as_official','competitions.report_as_official_help','competitions.reported_official','competitions.related_event','competitions.activities','competitions.marks_tests','competitions.register_participation','competitions.delete_confirm','competitions.benchmark_activity_help','competitions.edit_related_activity'] as $key) {
    $assert(str_contains($pt, "'{$key}'") && str_contains($en, "'{$key}'"), "Tradução {$key} deve existir em PT-BR e EN.");
}

$assert(!str_contains($migration, 'temporada') && !str_contains($migration, 'ranking') && !str_contains($migration, 'medalha'), 'Migration B3 não pode antecipar B4.');
$assert(!str_contains($domain, 'PB') && !str_contains($domain, 'SB'), 'Domínio de competição não pode criar engine de recordes PB/SB.');

printf("✓ progress phase B3 static/domain: %d assertions\n", $checks);
