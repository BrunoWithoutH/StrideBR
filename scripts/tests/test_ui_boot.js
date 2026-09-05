const fs = require('fs');
const vm = require('vm');
const code = fs.readFileSync('public/assets/js/ui-boot.js', 'utf8');

const runBoot = (dataset, storage = {}) => {
    const root = { dataset: {}, style: {}, lang: '' };
    const context = {
        document: { documentElement: root, currentScript: { dataset } },
        localStorage: { getItem: (key) => storage[key] || '' },
    };
    vm.runInNewContext(code, context);
    return root;
};

const dark = runBoot({ themeMode: 'dark', localeMode: 'auto', locale: 'pt-BR' });
if (dark.dataset.theme !== 'dark' || dark.dataset.themeMode !== 'dark' || dark.style.colorScheme !== 'dark' || dark.lang !== 'pt-BR') {
    throw new Error(`boot dark inválido: ${JSON.stringify(dark)}`);
}

const fallback = runBoot({ themeMode: '', localeMode: '', locale: 'en' }, { stridebr_theme_mode: 'dark', stridebr_locale_mode: 'en' });
if (fallback.dataset.theme !== 'dark' || fallback.dataset.locale !== 'en' || fallback.lang !== 'en') {
    throw new Error(`fallback do boot inválido: ${JSON.stringify(fallback)}`);
}

console.log('✓ UI boot CSP-safe: 2 scenarios');
