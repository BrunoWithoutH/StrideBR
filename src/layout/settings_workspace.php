<?php

declare(strict_types=1);

function stridebr_settings_workspace_navigation(string $active): void
{
    $items = [
        'profile' => ['label' => 'Perfil', 'href' => '/user/edit-profile.php'],
        'preferences' => ['label' => 'Preferências', 'href' => '/user/settings.php'],
        'account' => ['label' => 'Conta e segurança', 'href' => '/user/account.php'],
    ];
    echo '<nav class="settings-tabs" aria-label="Configurações">';
    foreach ($items as $key => $item) {
        $current = $active === $key;
        echo '<a' . ($current ? ' class="is-active" aria-current="page"' : '') . ' href="' . stridebr_e($item['href']) . '">' . stridebr_e($item['label']) . '</a>';
    }
    echo '</nav>';
}

function stridebr_settings_workspace_heading(string $section, string $description, ?string $backUrl = null): void
{
    echo '<div class="settings-heading-row">';
    echo '<div class="page-heading settings-workspace-heading"><span class="settings-workspace-kicker">Configurações</span><h1>' . stridebr_e($section) . '</h1><p>' . stridebr_e($description) . '</p></div>';
    if ($backUrl !== null && $backUrl !== '') {
        echo '<a class="secondary-button settings-profile-back" href="' . stridebr_e($backUrl) . '">← Voltar ao perfil</a>';
    }
    echo '</div>';
}
