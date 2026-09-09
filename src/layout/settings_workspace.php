<?php

declare(strict_types=1);

function stridebr_settings_workspace_navigation(string $active): void
{
    $items = [
        'profile' => ['label' => stridebr_t('settings.page_profile'), 'href' => '/user/edit-profile.php'],
        'preferences' => ['label' => stridebr_t('settings.preferences'), 'href' => '/user/settings.php'],
        'account' => ['label' => stridebr_t('account.page_title'), 'href' => '/user/account.php'],
    ];
    echo '<nav class="settings-tabs" aria-label="' . stridebr_e(stridebr_t('nav.settings')) . '">';
    foreach ($items as $key => $item) {
        $current = $active === $key;
        echo '<a' . ($current ? ' class="is-active" aria-current="page"' : '') . ' href="' . stridebr_e($item['href']) . '">' . stridebr_e($item['label']) . '</a>';
    }
    echo '</nav>';
}

function stridebr_settings_workspace_heading(string $section, string $description, ?string $backUrl = null): void
{
    echo '<div class="settings-heading-row">';
    if ($backUrl !== null && $backUrl !== '') {
        echo '<a class="context-back-button settings-profile-back" href="' . stridebr_e($backUrl) . '">← ' . stridebr_e(stridebr_t('settings.back_profile')) . '</a>';
    }
    echo '<div class="page-heading settings-workspace-heading"><span class="settings-workspace-kicker">' . stridebr_e(stridebr_t('nav.settings')) . '</span><h1>' . stridebr_e($section) . '</h1><p>' . stridebr_e($description) . '</p></div>';
    echo '</div>';
}
