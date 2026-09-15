<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) {
        fwrite(STDERR, "Web Pacer v1 static failed: {$message}\n");
        exit(1);
    }
};

$page = $read('public/user/pacer.php');
$js = $read('public/assets/js/pacer-web.js');
$css = $read('public/assets/css/pacer-web.css');
$previewApi = $read('public/api/pacer-preview.php');
$service = $read('src/function/pacer_service.php');
$cronograma = $read('src/function/cronograma.php');
$schedulePage = $read('public/user/cronogramatreinos.php');
$scheduleJs = $read('public/assets/js/cronogramas.js');
$header = $read('src/layout/header.php');

$assert(str_contains($page, 'pacerPlanList(') && str_contains($page, 'pacerPlanSave(') && str_contains($page, 'pacerPlanArchive('), 'Pacer Web deve operar no service canônico.');
$assert(str_contains($page, 'action" value="duplicate') && str_contains($page, 'Estratégia duplicada.'), 'Pacer Web precisa permitir duplicar planos sem domínio paralelo.');
$assert(str_contains($page, 'Ritmo constante') && str_contains($page, 'Negative split') && str_contains($page, 'Custom') && str_contains($page, 'Positive split'), 'editor precisa expor as estratégias suportadas pelo Core.');
$assert(str_contains($page, 'target_distance_km') && str_contains($page, 'target_time') && str_contains($page, 'tolerance_s_per_km'), 'editor precisa coletar distância, tempo e tolerância.');
$assert(str_contains($page, 'heart_rate_floor_bpm') && str_contains($page, 'heart_rate_ceiling_bpm') && str_contains($page, 'Não são recomendação médica.'), 'constraints de FC precisam ser opcionais e não médicas.');
$assert(str_contains($page, 'generated_options_changed') && str_contains($page, "'_preserve_segments'") && str_contains($page, "\$payload['segments'] = \$existing['segments']"), 'edições administrativas não podem regenerar silenciosamente segmentos gerados.');
$assert(str_contains($js, '/api/pacer-preview.php') && str_contains($previewApi, 'pacerBlueprint('), 'preview Web precisa usar a geração determinística canônica do Core.');
$assert(str_contains($js, 'pacer-preview-row') && str_contains($js, 'target_average_pace_s_per_km') && str_contains($js, 'calculated_target_time_s') === false, 'preview deve apresentar segmentos, pace médio e alvo sem algoritmo alternativo.');
$assert(str_contains($js, 'data-progressive-option') && str_contains($js, 'negative_split') && str_contains($js, 'positive_split'), 'progressão deve aparecer apenas em estratégias progressivas.');
$assert(str_contains($js, 'data-pacer-segment') && str_contains($js, 'data-add-pacer-segment') && str_contains($js, 'data-remove-pacer-segment'), 'Custom precisa ter editor por segmentos reutilizável.');
$assert(str_contains($previewApi, 'stridebr_verify_csrf()') && str_contains($previewApi, 'stridebr_require_login()') && str_contains($previewApi, 'Cache-Control: private, no-store'), 'preview deve ser privado, session-auth e protegido por CSRF.');
$assert(str_contains($service, "['even','negative_split','positive_split','custom']") && str_contains($service, 'pacerValidateSegments(') && str_contains($service, 'Segmentos precisam cobrir a distância sem gaps ou sobreposição.'), 'validação de estratégia e segmentos deve permanecer no service.');
$assert(str_contains($service, "'persistence_s' => 15") && str_contains($service, "'hysteresis_s_per_km' => 3.0") && str_contains($service, "'cooldown_s' => 20"), 'regras offline de guidance precisam permanecer versionadas no Core.');
$assert(str_contains($cronograma, 'pacerPlanValidateForWorkout(') && str_contains($cronograma, 'idpacerplan = :pacer') && str_contains($cronograma, 'idpacerplan, idrota_salva, descricao'), 'cronograma Web precisa persistir vínculo Pacer validado pelo Core.');
$assert(str_contains($cronograma, 'não pode ser alterada isoladamente') && str_contains($cronograma, "\$payload['pacer_plan_id'] = \$requestedPacerPlanId"), 'edição recorrente precisa preservar a semântica this/future do vínculo Pacer.');
$assert(str_contains($schedulePage, 'name="pacer_plan_id"') && str_contains($schedulePage, 'data-editor-pacer') && str_contains($schedulePage, '/user/pacer.php'), 'editor de Workout precisa oferecer seleção de Pacer e acesso ao gerenciador.');
$assert(str_contains($schedulePage, "'pacer_plan_id' => (string) (\$item['idpacerplan'] ?? '')"), 'payload do editor precisa carregar o Pacer já vinculado.');
$assert(str_contains($scheduleJs, "set('pacer_plan_id'") && str_contains($scheduleJs, 'syncEditorPacer') && str_contains($scheduleJs, 'option.dataset.sport === sport'), 'editor JS precisa restaurar o plano e filtrar por modalidade compatível.');
$assert(str_contains($header, '/user/pacer.php') && str_contains($header, 'Estratégias de pace para treinos e provas.'), 'Pacer deve estar acessível pela navegação de Treino.');
$assert(str_contains($css, '@media(max-width:980px)') && str_contains($css, '@media(max-width:620px)'), 'Pacer Web precisa ter layout responsivo.');
$assert(!str_contains($page, 'IA') && !str_contains($js, 'OpenAI'), 'Pacer Web não deve depender de IA generativa.');

printf("✓ Web Pacer v1 static: %d assertions\n", $count);
