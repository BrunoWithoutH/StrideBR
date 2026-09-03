(() => {
    const root = document.documentElement;
    const supportedThemes = ['light', 'dark'];
    const supportedLocaleModes = ['auto', 'pt-BR', 'en'];

    const setCookie = (name, value) => {
        document.cookie = `${name}=${encodeURIComponent(value)}; Max-Age=31536000; Path=/; SameSite=Lax`;
    };

    const effectiveTheme = (mode) => mode === 'dark' ? 'dark' : 'light';

    const applyTheme = (theme, persist = true) => {
        if (!supportedThemes.includes(theme)) theme = 'light';
        const effective = effectiveTheme(theme);
        root.dataset.themeMode = theme;
        root.dataset.theme = effective;
        root.style.colorScheme = effective;
        if (persist) {
            try {
                localStorage.setItem('stridebr_theme_mode', theme);
                localStorage.removeItem('stridebr_theme');
            } catch (_) {}
            setCookie('stridebr_theme', theme);
        }
        document.querySelectorAll('[data-theme-select]').forEach((select) => {
            if (select.value !== theme) select.value = theme;
        });
    };

    const detectedLocale = () => {
        const language = String(navigator.language || (navigator.languages || [])[0] || '').replace('_', '-');
        return /^pt(?:-|$)/i.test(language) ? 'pt-BR' : 'en';
    };

    const setLocaleMode = (mode) => {
        if (!supportedLocaleModes.includes(mode)) mode = 'auto';
        try {
            localStorage.setItem('stridebr_locale_mode', mode);
            localStorage.removeItem('stridebr_locale');
        } catch (_) {}
        setCookie('stridebr_locale_mode', mode);
        if (mode !== 'auto') setCookie('stridebr_locale', mode);
        const url = new URL(window.location.href);
        url.searchParams.delete('lang');
        window.location.assign(url.toString());
    };

    document.addEventListener('change', (event) => {
        const themeSelect = event.target.closest('[data-theme-select]');
        if (themeSelect) applyTheme(themeSelect.value);
        const localeSelect = event.target.closest('[data-locale-select]');
        if (localeSelect) setLocaleMode(localeSelect.value);
    });

    applyTheme(root.dataset.themeMode || 'light', false);
    document.querySelectorAll('[data-locale-select]').forEach((select) => {
        const mode = root.dataset.localeMode || 'auto';
        if (supportedLocaleModes.includes(mode)) select.value = mode;
        select.dataset.detectedLocale = detectedLocale();
    });

})();
