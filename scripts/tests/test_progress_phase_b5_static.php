<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/includes/app.php';
require_once $root . '/src/function/combat_progress.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Progress Phase B5 failed: {$message}\n");
        exit(1);
    }
};
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$migration = $read('src/database/migrations/20260914_progress_b5_combat.sql');
$domain = $read('src/function/combat_progress.php');
$api = $read('public/api/progress-combat.php');
$progress = $read('public/user/progresso.php');
$css = $read('public/assets/css/sport-hub.css');
$account = $read('src/function/account_data.php');
$catalog = $read('src/function/sport_catalog.php');
$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');

$assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS graduacoes_usuario'), 'Migration deve criar histórico user-owned de graduação.');
$assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS tecnicas_usuario'), 'Migration deve criar repertório técnico user-owned.');
$assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS praticas_tecnica'), 'Migration deve separar prática/evidência da autoavaliação.');
$assert(substr_count($migration, 'REFERENCES usuarios(idusuario) ON DELETE CASCADE') >= 3, 'Dados B5 devem cair com exclusão da conta.');
$assert(str_contains($migration, 'sistema_normalizado') && str_contains($migration, 'data_graduacao DESC'), 'Graduação atual deve ser derivável por sistema e data.');
$assert(!str_contains($migration, 'is_current') && !str_contains($migration, 'rank_order') && !str_contains($migration, 'belt_order'), 'B5 não pode persistir graduação atual ou ordem universal de faixas.');
$assert(str_contains($migration, 'nome_normalizado') && str_contains($migration, 'UNIQUE (idusuario, idmodalidade, nome_normalizado)'), 'Duplicata equivalente de técnica deve ser impedida por identidade normalizada.');
$assert(str_contains($migration, "estado IN ('learning', 'practicing', 'consolidated', 'archived')"), 'Autoavaliação deve usar estados simples e explícitos.');
$assert(!preg_match('/mastery|skill_score|xp|badge|nivel_percent|percentual_dominio/i', $migration), 'Schema não pode criar gamificação ou percentual de domínio.');
$assert(str_contains($migration, "origem IN ('manual', 'activity')"), 'Prática deve distinguir evidência manual e ligada a atividade.');
$assert(str_contains($migration, 'idregistro VARCHAR(21) REFERENCES registros_atividade(idregistro) ON DELETE SET NULL'), 'Prática pode referenciar atividade sem duplicá-la.');
$assert(str_contains($migration, 'ix_praticas_tecnica_tecnica_data') && str_contains($migration, 'ix_praticas_tecnica_atividade'), 'Índices devem cobrir técnica/data e atividade.');

$assert(str_contains($domain, "sportCatalogFamilyKey") && str_contains($domain, "=== 'combat'"), 'Domínio deve consumir taxonomia canônica combat.');
$assert(!str_contains($domain, "['jiu-jitsu','judo'") && !str_contains($domain, 'jiu-jitsu, judo'), 'Domínio não deve manter lista paralela de modalidades combat.');
$assert(str_contains($domain, 'combatRankSummary') && str_contains($domain, 'current_by_system'), 'Graduação atual deve ser derivada por sistema.');
$assert(str_contains($domain, "data_graduacao DESC") && !str_contains($domain, 'ORDER BY graduacao'), 'Histórico deve ordenar por data, nunca pelo nome da graduação.');
$assert(str_contains($domain, "new DateTimeImmutable('today'") && str_contains($domain, 'combat.error.invalid_rank_date'), 'Graduação futura deve ser rejeitada.');
$assert(combatProgressNormalizeKey('  Armbar  ') === 'armbar', 'Normalização deve remover espaço e caixa sem fuzzy matching.');
$assert(combatProgressNormalizeKey('Arm bar') !== combatProgressNormalizeKey('Armbar'), 'Nomes distintos não podem ser fundidos por heurística agressiva.');
$assert(combatTechniqueStates() === ['learning','practicing','consolidated','archived'], 'Estados técnicos devem permanecer estáveis.');
$assert(in_array('striking', combatTechniqueCategories(), true) && in_array('guard', combatTechniqueCategories(), true) && in_array('kata_form', combatTechniqueCategories(), true), 'Categorias devem atender modalidades combat diferentes.');
$assert(str_contains($domain, 'COUNT(p.idpratica)') && str_contains($domain, 'MIN(p.data_pratica)') && str_contains($domain, 'MAX(p.data_pratica)'), 'Lista deve derivar contagem/primeira/última prática em consulta agregada.');
$assert(str_contains($domain, 'combatPracticeValidateActivity') && str_contains($domain, "idusuario=:usuario") && str_contains($domain, "idmodalidade") , 'Vínculo de atividade precisa validar ownership e modalidade.');
$assert(!preg_match('/mastery|skill score|skill_score|next.?rank|next.?belt|xp|badge/i', $domain . $progress), 'B5 não pode inferir domínio, próxima graduação ou gamificação.');

$assert(str_contains($api, 'stridebr_verify_csrf()'), 'Endpoint B5 deve preservar CSRF.');
foreach (['rank_create','rank_update','rank_delete','technique_create','technique_update','technique_archive','technique_reactivate','practice_create'] as $action) $assert(str_contains($api, "'{$action}'"), "Endpoint deve suportar {$action}.");
$assert(str_contains($progress, "if (\$renderer === 'combat' && \$selectedModalityId !== '')"), 'Progresso deve carregar B5 apenas para renderer combat e modalidade específica.');
$assert(str_contains($progress, 'data-combat-rank-section') && str_contains($progress, 'data-combat-technique-section') && str_contains($progress, 'data-combat-technique-detail'), 'UI combat deve cobrir graduação, repertório e detalhe técnico.');
$assert(str_contains($progress, 'combat.practice.empty') && str_contains($progress, 'combat.rank.empty') && str_contains($progress, 'combat.technique.empty'), 'UI deve ter estados vazios honestos.');
$assert(str_contains($progress, '/user/atividades.php?activity=') && str_contains($progress, 'idregistro'), 'Prática ligada deve apontar para atividade existente.');
$assert(str_contains($css, '.combat-technique-summary') && str_contains($css, '@media(max-width:760px)'), 'B5 deve ter layout responsivo compacto.');

$assert(str_contains($account, "'graduacoes' =>") && str_contains($account, "'tecnicas' =>") && str_contains($account, "'praticas_tecnicas' =>"), 'Exportação da conta deve incluir dados B5.');
$assert(str_contains($catalog, "'combat' =>") && str_contains($catalog, "return 'combat'"), 'B5 deve continuar apoiado na taxonomia combat existente.');
foreach (['combat.rank.title','combat.technique.title','combat.practice.register','combat.state.learning','combat.state.consolidated','combat.category.submission','combat.error.invalid_modality'] as $key) {
    $assert(str_contains($pt, "'{$key}'") && str_contains($en, "'{$key}'"), "i18n PT/EN deve conter {$key}.");
}
$assert(!str_contains($pt, '73%') && !str_contains($en, '73%'), 'Copy não pode sugerir percentual fictício de domínio.');

echo "Progress Phase B5 static passed ({$checks} assertions).\n";
