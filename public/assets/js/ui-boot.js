(() => {
    const root = document.documentElement;
    const script = document.currentScript;
    const readStorage = (key) => {
        try {
            return localStorage.getItem(key) || '';
        } catch (_) {
            return '';
        }
    };
    const serverTheme = script?.dataset.themeMode || '';
    const serverLocaleMode = script?.dataset.localeMode || '';
    const serverLocale = script?.dataset.locale || 'pt-BR';
    let theme = serverTheme || readStorage('stridebr_theme_mode') || readStorage('stridebr_theme') || 'light';
    if (!['light', 'dark'].includes(theme)) theme = 'light';
    let localeMode = serverLocaleMode || readStorage('stridebr_locale_mode') || 'auto';
    if (!['auto', 'pt-BR', 'en'].includes(localeMode)) localeMode = 'auto';
    const locale = localeMode === 'auto' ? (serverLocale === 'pt-BR' ? 'pt-BR' : 'en') : localeMode;
    root.lang = locale === 'en' ? 'en' : 'pt-BR';
    root.dataset.locale = locale;
    root.dataset.localeMode = localeMode;
    root.dataset.themeMode = theme;
    root.dataset.theme = theme;
    root.style.colorScheme = theme;
})();
