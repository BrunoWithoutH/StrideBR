<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};

$domain = $read('src/function/notificacoes.php');
$header = $read('src/layout/header.php');
$page = $read('public/user/notificacoes.php');
$style = $read('public/assets/css/style.css');
$ui = $read('public/assets/css/ui-refresh.css');

$assert(str_contains($domain, 'function notificacaoCopy') && str_contains($domain, "'amizade_aceita' => ['notification.friend_accepted.title', null]") && str_contains($domain, "'treinador_vinculo_aceito' => ['notification.coach_link_accepted.title', null]"), 'Descrições redundantes precisam ser suprimidas no domínio de apresentação.');
$assert(str_contains($domain, 'notification.schedule_updated.message_named') && str_contains($domain, "'schedule_name' => \$nome"), 'Atualização de cronograma só deve exibir descrição quando acrescenta o objeto afetado.');
$assert(str_contains($domain, 'function notificacaoBuscar') && str_contains($domain, 'function notificacaoDestinoSeguro') && str_contains($domain, 'function notificacaoUrlAbertura'), 'Clique com destino precisa usar resolução segura e ownership da notificação.');
$assert(str_contains($page, '$_GET[\'open\']') && str_contains($page, 'notificacaoMarcarLida($pdo, $idUsuario, $openNotificationId)') && str_contains($page, "header('Location: ' . \$destination, true, 303)"), 'Abrir uma notificação com destino precisa marcar somente ela e redirecionar.');
$assert(str_contains($header, 'notificacaoUrlAbertura($notification)') && str_contains($header, 'href="/user/notificacoes.php"'), 'Popover deve marcar no clique do item, enquanto Ver todas permanece navegação neutra.');
$assert(!str_contains(substr($page, strpos($page, '$items = notificacaoListar')), 'notificacaoMarcarTodasLidas($pdo, $idUsuario);'), 'Renderizar a lista de notificações não pode marcar itens implicitamente.');
$assert(str_contains($page, 'value="read"') && str_contains($page, 'notifications.mark_read'), 'A ação explícita Marcar como lida precisa permanecer disponível.');
$selector = '.site-header:has(.user-menu[open], .header-notification-menu[open], .mobile-global-menu[open])';
$assert(str_contains($style, $selector) && str_contains($ui, $selector), 'Header global precisa subir quando notificações ou menus globais estiverem abertos.');
$assert(str_contains($style, '--z-drawer: 5200') && str_contains($style, '--z-header-menu: 5300') && str_contains($style, '--z-modal: 7000') && str_contains($style, '--z-toast: 8000') && str_contains($style, '--z-confirm: 9000'), 'Ordem de camadas deve ser drawer < header global < modal < toast < confirmação.');

echo "✓ notification contract static: {$checks} assertions\n";
