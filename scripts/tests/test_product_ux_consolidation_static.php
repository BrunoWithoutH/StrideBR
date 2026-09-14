<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Product UX consolidation static failed: {$message}\n");
        exit(1);
    }
};

$integrations = $read('src/function/integrations.php');
$cards = $read('src/layout/integration_cards.php');
$ui = $read('public/assets/css/ui-refresh.css');
$activities = $read('public/assets/css/atividades.css');
$friends = $read('public/user/amigos.php');
$trainerPage = $read('public/user/treinador.php');
$trainerFn = $read('src/function/treinador.php');
$trainerAthlete = $read('src/layout/trainer_as_athlete.php');
$trainerJs = $read('public/assets/js/trainer.js');
$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');

foreach (['garmin'=>'G','strava'=>'S','polar'=>'PF','google_health'=>'GH','coros'=>'C','suunto'=>'SU','halo'=>'H','health_connect'=>'HC','samsung_health'=>'SH','apple_health'=>'AH'] as $provider => $mark) {
    $assert((bool) preg_match("/'" . preg_quote($provider, '/') . "'\\s*=>\\s*\\[[\\s\\S]*?'mark'\\s*=>\\s*'" . preg_quote($mark, '/') . "'/", $integrations), "provider {$provider} precisa de mark curta controlada.");
}
$assert(str_contains($cards, "'ready' => []") && str_contains($cards, "'app' => []") && str_contains($cards, "'external' => []"), 'Connections precisa separar ready/app/external.');
$assert(str_contains($cards, "integrations.available_android") && str_contains($cards, "integrations.available_ios") && str_contains($cards, "integrations.awaiting_external") && str_contains($cards, "integrations.coming_soon"), 'Connections precisa diferenciar estados de disponibilidade.');
$assert(str_contains($cards, "\$provider['mark']") && !str_contains($cards, "\$provider['label'], 0, 2"), 'Provider mark deve usar contrato dedicado, não nome completo truncado na UI.');
$assert(str_contains($ui, '.integration-provider-mark{overflow:hidden;white-space:nowrap') && str_contains($ui, '.integration-group:not([data-integration-group="ready"]) .integration-card{min-height:0;height:auto'), 'Cards compactos precisam de mark defensiva e densidade própria.');
$assert(str_contains($activities, '@media (min-width:901px)') && str_contains($activities, 'overscroll-behavior-y:auto;') && !str_contains($activities, 'html.activity-detail-open,html.activity-detail-open body{overflow:auto}') && !str_contains($activities, "html.activity-detail-open,\nhtml.activity-detail-open body {\n    overflow: hidden;"), 'Preview desktop precisa encadear wheel com a página e não aplicar body lock.');
$assert(str_contains($activities, "@media (max-width:900px){\n  html.activity-detail-open,\n  html.activity-detail-open body{overflow:hidden!important}") && str_contains($activities, '.activity-detail-drawer{z-index:var(--z-drawer)}'), 'Mobile precisa preservar body lock e contrato de drawer.');
$assert(str_contains($trainerPage, "stridebr_t('trainer.as_athlete')") && str_contains($trainerPage, "stridebr_t('trainer.as_coach')") && str_contains($trainerPage, 'class="ux-context-nav'), 'Trainer precisa expor contextos atleta/treinador de forma explícita.');
$assert(str_contains($trainerAthlete, 'trainer-permission-summary') && str_contains($trainerAthlete, 'trainer-permissions-editor'), 'Permissões do atleta precisam de resumo e edição progressiva.');
$assert(str_contains($trainerAthlete, 'trainer-search-person') && str_contains($trainerAthlete, "treinadorBuscarPessoas") === false, 'Busca do treinador deve renderizar pessoas sem misturar SQL no layout.');
$assert(str_contains($trainerFn, 'function treinadorBuscarPessoas') && str_contains($trainerFn, 'ILIKE :username') && str_contains($trainerFn, 'ILIKE :nome'), 'Busca de treinador precisa aceitar nome e username no domínio.');
$assert(str_contains($trainerFn, 'function treinadorEditarPrescricao') && str_contains($trainerFn, "['rascunho', 'publicado']") && str_contains($trainerFn, "(string) (\$current['data_treino'] ?? '') < date('Y-m-d')"), 'Edição de prescrição precisa restringir estados e histórico.');
$assert(str_contains($trainerFn, 'idcriador=:treinador') && str_contains($trainerFn, "(string) (\$current['idvinculo'] ?? '') !== (string) (\$link['idvinculo'] ?? '')"), 'Edição precisa validar ownership e vínculo aceito correspondente.');
$assert(str_contains($trainerFn, 'function treinadorAtividadeReadOnly') && str_contains($trainerFn, "pode_ver_atividades") && str_contains($trainerFn, 'atividadeCarregarRegistro($pdo, $idRegistro, $idAtleta)'), 'Atividade read-only precisa validar permissão e carregar a atividade pelo ownership do atleta.');
$assert(str_contains($trainerFn, 'function treinadorCronogramaReadOnly') && str_contains($trainerFn, "pode_ver_cronograma") && str_contains($trainerFn, 'idusuario=:atleta'), 'Cronograma read-only precisa validar permissão e ownership do atleta.');
$assert(str_contains($trainerJs, 'dataset.autoOpen') && str_contains($trainerJs, 'requestAnimationFrame'), 'Editor de prescrição precisa abrir de forma progressiva quando solicitado pelo servidor.');
$assert(str_contains($friends, "\$friendsView =") && str_contains($friends, "'people'") && str_contains($friends, "'sharing'"), 'Friends precisa separar pessoas e compartilhamentos.');
$assert(str_contains($friends, "stridebr_t('friends.incoming_requests')") && str_contains($friends, "stridebr_t('friends.your_friends')") && str_contains($friends, "stridebr_t('friends.search_people')") && str_contains($friends, "stridebr_t('friends.outgoing_requests')"), 'IA de Pessoas precisa priorizar incoming, amigos, busca e outgoing.');
$assert(str_contains($friends, "\$action === 'cancel_request'") && str_contains($friends, 'idusuario_solicitante = :me') && str_contains($friends, "status = 'pendente'"), 'Cancelamento outgoing precisa ficar restrito ao remetente e a pedido pendente.');
$assert(str_contains($friends, 'class="person-action-menu"') && str_contains($friends, "stridebr_t('friends.remove_confirm')"), 'Remover amigo precisa ficar em ação contextual com confirmação.');
$assert(str_contains($ui, '.ux-context-nav{') && str_contains($ui, '.person-action-menu{') && str_contains($ui, '.trainer-permission-summary{') && str_contains($ui, '.ui-icon{'), 'Primitivas tocadas precisam compartilhar linguagem visual.');
$assert(str_contains($ui, '@media(max-width:760px)') && str_contains($ui, '.ux-context-nav{display:grid;grid-template-columns:1fr 1fr;width:100%}') && str_contains($ui, '.person-card{padding:10px}'), 'Consolidação precisa manter layout de uma coluna/densidade no mobile.');

foreach (['integrations.group.ready','integrations.group.app','integrations.group.external','integrations.available_android','integrations.available_ios','integrations.awaiting_external','friends.people','friends.sharing','friends.incoming_requests','friends.outgoing_requests','friends.cancel_request','trainer.context_navigation','trainer.manage_permissions','trainer.search_coach','trainer.prescription_edit_forbidden','trainer.activity_readonly_forbidden','trainer.schedule_readonly_forbidden'] as $key) {
    $assert(str_contains($pt, "'{$key}'") && str_contains($en, "'{$key}'"), "{$key} precisa existir em PT-BR e EN.");
}

printf("✓ product UX consolidation static: %d assertions\n", $checks);
