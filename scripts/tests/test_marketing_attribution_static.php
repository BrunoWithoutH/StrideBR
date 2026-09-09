<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/includes/app.php';
require_once $root . '/src/function/marketing.php';

$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$migration = $read('src/database/migrations/20260909_marketing_attribution.sql');
$helper = $read('src/function/marketing.php');
$entry = $read('public/api/marketing-entry.php');
$redirect = $read('public/marketing-redirect.php');
$htaccess = $read('public/.htaccess');
$footer = $read('src/layout/footer.php');
$signup = $read('public/signup.php');
$activity = $read('src/function/atividade_modelo.php');
$admin = $read('public/admin/marketing.php');
$adminInclude = $read('src/includes/admin.php');
$marketingJs = $read('public/assets/js/marketing.js');
$adminJs = $read('public/assets/js/admin-marketing.js');
$privacy = $read('public/pages/legal/privacy.php');
$cookies = $read('public/pages/legal/cookies.php');
$seoTest = $read('scripts/tests/test_seo.php');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "Falhou: {$message}\n");
        exit(1);
    }
};

$assert(stridebr_marketing_slug(' JIFSul 2026 Awareness ') === 'jifsul_2026_awareness', 'slug normaliza espaços/case');
$assert(stridebr_marketing_slug('Matéria Prima') === 'materia_prima', 'slug remove acento com segurança');
$assert(stridebr_marketing_validate_slug('fw_if_ginasio') === 'fw_if_ginasio', 'slug válido permanece estável');
$assert(stridebr_marketing_internal_destination('/') === '/', 'raiz é destino válido');
$assert(stridebr_marketing_internal_destination('/calendario.php?origem=campanha') === '/calendario.php?origem=campanha', 'rota interna com query é permitida');
foreach (['https://evil.example/', 'https:/evil.example/', '//evil.example/path', '/r/loop', '/%72/loop', 'javascript:alert(1)', '\\evil.example', '/%5C%5Cevil.example'] as $unsafe) {
    $threw = false;
    try { stridebr_marketing_internal_destination($unsafe); } catch (InvalidArgumentException) { $threw = true; }
    $assert($threw, 'destino inseguro rejeitado: ' . $unsafe);
}
$assert(strlen((string) stridebr_marketing_clean_text(str_repeat('x', 500), 80)) === 80, 'UTM/texto recebe limite de tamanho');
$assert(stridebr_marketing_campaign_types() === ['offline','paid_social','organic_social','event','other'], 'tipos de campanha mínimos');
$assert(stridebr_marketing_campaign_statuses() === ['planejada','ativa','encerrada'], 'status de campanha mínimos');
$assert(stridebr_marketing_date_or_null('2026-09-09', 'inválida') === '2026-09-09', 'data ISO válida é preservada');
$invalidDate = false;
try { stridebr_marketing_date_or_null('2026-02-31', 'inválida'); } catch (InvalidArgumentException) { $invalidDate = true; }
$assert($invalidDate, 'data impossível é rejeitada sem normalização silenciosa');
$directLandingKey = stridebr_marketing_event_key('landing_view', null, null);
$campaignLandingKey = stridebr_marketing_event_key('landing_view', null, str_repeat('a', 64));
$assert($directLandingKey !== $campaignLandingKey, 'landing explícita pode ser contabilizada após entrada direta na mesma sessão');

foreach (['marketing_campanhas','marketing_placements','marketing_atribuicoes','marketing_eventos_aquisicao'] as $table) {
    $assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS ' . $table), 'migration cria ' . $table);
}
$assert(str_contains($migration, "nome IN ('landing_view','signup_start','signup_complete','activation')"), 'allowlist de eventos de aquisição');
$assert(str_contains($migration, 'ux_marketing_atribuicoes_usuario'), 'uma atribuição first-touch por usuário');
$assert(str_contains($migration, "'fw_local_2026'"), 'campanha local inicial seedada');
foreach (['fw_if_ginasio','fw_if_central','fw_if_ti','fw_if_adm_ru','fw_sesc','fw_sesc_entrada','fw_sesc_saida','fw_vitoria_bike','fw_materia_prima','fw_rede_mestre','fw_garra','fw_ct','fw_vital','fw_imperio_fight','fw_raja_sul'] as $placement) {
    $assert(str_contains($migration, "'{$placement}'"), 'placement inicial ' . $placement);
}
$assert(substr_count($migration, "'offline_poster','/',TRUE") === 15, 'seed possui somente os 15 placements solicitados');

$assert(str_contains($htaccess, 'RewriteRule ^r/([a-z0-9_-]{2,80})/?$ marketing-redirect.php?code=$1'), 'redirect curto possui rewrite dedicado');
$assert(str_contains($redirect, "stridebr_marketing_lookup_placement(\$pdo, \$code, true)"), 'redirect só aceita placement elegível');
$assert(str_contains($redirect, 'X-Robots-Tag: noindex, nofollow, noarchive'), 'redirect curto é noindex');
$assert(str_contains($redirect, 'stridebr_marketing_internal_destination'), 'destino do redirect é revalidado');
$assert(!preg_match('/header\([\'\"]Location:\s*[\'\"]\s*\.\s*\$_GET/', $redirect), 'redirect não usa destino arbitrário da query');

$assert(str_contains($footer, '/assets/js/marketing.js') && str_contains($footer, 'if (!$footerLoggedIn)'), 'tracking público é first-party e não invade workspace autenticado');
$assert(str_contains($marketingJs, "['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term']"), 'frontend entende UTMs previstas');
$assert(str_contains($marketingJs, "fetch('/api/marketing-entry.php'"), 'entrada usa endpoint first-party');
$assert(!preg_match('/facebook|connect\.facebook|google-analytics|googletagmanager|hotjar|clarity/i', $marketingJs . $entry . $helper), 'tracking não adiciona terceiros');
$assert(!preg_match('/user[_ -]?agent|HTTP_USER_AGENT|REMOTE_ADDR|latitude|longitude|geolocation/i', $migration . $helper . $entry), 'schema de marketing não coleta IP/UA/localização');
$assert(str_contains($helper, "hash('sha256', \$token)"), 'cookie de atribuição é armazenado por hash');
$assert(str_contains($helper, "'httponly' => true") && str_contains($helper, "'samesite' => 'Lax'"), 'cookie usa HttpOnly e SameSite');
$assert(str_contains($helper, '30 * 86400'), 'atribuição expira em 30 dias');
$assert(str_contains($helper, 'if ($existing) return $existing;'), 'first-touch conhecido não é sobrescrito');
$assert(str_contains($helper, 'ON CONFLICT (chave_evento) DO NOTHING'), 'eventos são deduplicados');

$assert(str_contains($signup, 'stridebr_marketing_signup_start($pdo, $_GET);'), 'signup_start é registrado na abertura do fluxo');
$commitPos = strpos($signup, '$pdo->commit();');
$completePos = strpos($signup, 'stridebr_marketing_signup_complete($pdo, $id);');
$assert($commitPos !== false && $completePos !== false && $completePos > $commitPos, 'signup_complete acontece somente após conta persistida');
$assert(str_contains($activity, 'if (!$isUpdate)') && str_contains($activity, 'stridebr_marketing_activation($pdo, $idUsuario);'), 'activation é ligada somente a nova atividade');

$assert(str_contains($admin, "stridebr_require_role('admin')"), 'Marketing exige admin');
$assert(str_contains($adminInclude, "'marketing' => ['/admin/marketing.php'"), 'Marketing aparece na navegação Admin');
$assert(str_contains($admin, 'stridebr_marketing_metrics($pdo)') && str_contains($admin, 'stridebr_marketing_placements_with_metrics'), 'Admin usa agregados do funil');
$assert(str_contains($admin, 'data-copy-short-url') && str_contains($admin, 'data-download-qr'), 'Admin oferece copiar link e baixar QR');
$assert(str_contains($adminJs, 'image/svg+xml') && str_contains($adminJs, 'StrideBRQR'), 'QR é gerado localmente como SVG');
$assert(is_file($root . '/public/assets/vendor/qrcode-generator.js') && filesize($root . '/public/assets/vendor/qrcode-generator.js') < 40000, 'gerador QR vendorizado é pequeno');
$assert(is_file($root . '/public/assets/vendor/qrcode-generator.LICENSE.txt'), 'licença do QR vendorizado é preservada');

$assert(str_contains($privacy, 'Aquisição e campanhas') && str_contains($privacy, 'não usa fingerprinting'), 'Privacidade descreve atribuição minimizada');
$assert(str_contains($cookies, '<code>stridebr_acq</code>') && str_contains($cookies, '30 dias'), 'Cookies documenta atribuição first-party');
$assert(str_contains($seoTest, "'path' => '/?utm_source=test'"), 'regressão SEO cobre canonical com UTM');
$assert(!is_dir($root . '/docs/branding/app-icons/ios-liquid-glass'), 'exports gigantes de ícone iOS não são empacotados');
$assert(is_file($root . '/public/assets/img/branding/app-icons/ios/apple-touch-icon-180x180.png'), 'ícone iOS de produção permanece');

printf("✓ marketing attribution static: %d assertions\n", $assertions);
