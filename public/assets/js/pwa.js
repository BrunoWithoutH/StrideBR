(() => {
    const build = document.currentScript?.dataset.build || 'rc'
    const media = window.matchMedia?.('(display-mode: standalone)') || null
    const isStandalone = () => Boolean(media?.matches || window.navigator.standalone === true)
    const applyDisplayMode = () => {
        const standalone = isStandalone()
        document.documentElement.classList.toggle('is-standalone', standalone)
        document.documentElement.dataset.displayMode = standalone ? 'standalone' : 'browser'
        if (document.body) {
            document.body.classList.toggle('is-standalone', standalone)
            document.body.dataset.displayMode = standalone ? 'standalone' : 'browser'
        }
        window.StrideBRPWA = {...(window.StrideBRPWA || {}), isStandalone: standalone}
    }
    applyDisplayMode()
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', applyDisplayMode, {once: true})
    media?.addEventListener?.('change', applyDisplayMode)
    window.addEventListener('pageshow', applyDisplayMode)

    const DISMISS_KEY = 'stridebr:pwa-install-hint-dismissed-until'
    const DISMISS_MS = 30 * 24 * 60 * 60 * 1000
    let installPrompt = null
    let hintShell = null
    const t = (key, fallback) => window.StrideBRI18n?.t?.(key, {}, fallback) ?? fallback
    const isIos = () => /iPhone|iPad|iPod/i.test(navigator.userAgent || '') || (/Macintosh/i.test(navigator.userAgent || '') && Number(navigator.maxTouchPoints || 0) > 1)
    const isAndroid = () => /Android/i.test(navigator.userAgent || '')
    const isMobileBrowser = () => {
        if (isStandalone()) return false
        const narrow = window.matchMedia?.('(max-width: 900px)')?.matches ?? window.innerWidth <= 900
        return Boolean(narrow && (isIos() || isAndroid() || /Mobile/i.test(navigator.userAgent || '')))
    }
    const dismissed = () => {
        try { return Number(localStorage.getItem(DISMISS_KEY) || 0) > Date.now() } catch (_) { return false }
    }
    const rememberDismiss = (duration = DISMISS_MS) => {
        try { localStorage.setItem(DISMISS_KEY, String(Date.now() + duration)) } catch (_) {}
    }
    const suppressedPage = () => /\/(?:login|signup|forgot-password|reset-password|verify-email)\.php$/.test(location.pathname) || location.pathname === '/user/onboarding.php' || location.pathname === '/user/gravar-atividade.php'
    const busySurface = () => Boolean(
        document.documentElement.classList.contains('ui-modal-scroll-locked') ||
        document.documentElement.classList.contains('calendar-quick-open') ||
        document.querySelector('.global-tools.has-active-workout') ||
        document.querySelector('[aria-modal="true"]:not([hidden])')
    )
    const eligible = () => isMobileBrowser() && !suppressedPage() && !dismissed() && !busySurface() && (Boolean(installPrompt) || isIos())
    const hideHint = () => hintShell?.classList.remove('is-visible')
    const syncHint = () => {
        if (!hintShell) return
        hintShell.classList.toggle('is-visible', eligible())
    }
    const ensureHint = () => {
        if (hintShell || !document.body) return hintShell
        const shell = document.createElement('aside')
        shell.className = 'pwa-install-hint-shell'
        shell.setAttribute('data-pwa-install-hint', '')
        shell.setAttribute('aria-label', t('pwa.add_to_home', 'Adicionar à tela inicial'))
        const ios = isIos()
        shell.innerHTML = `<div class="pwa-install-hint${ios ? ' is-ios' : ''}"><div class="pwa-install-hint-copy"><strong>${t('pwa.add_to_home', 'Adicionar à tela inicial')}</strong><span>${ios ? t('pwa.ios_instruction', 'No iPhone: Compartilhar → Adicionar à Tela de Início.') : t('pwa.add_to_home_help', 'Acesse o StrideBR direto da tela inicial do celular.')}</span></div><div class="pwa-install-hint-actions">${ios ? '' : `<button type="button" data-pwa-install>${t('pwa.add_action', 'Adicionar')}</button>`}<button type="button" data-pwa-dismiss>${t('pwa.dismiss', 'Agora não')}</button></div></div>`
        const header = document.querySelector('.site-header')
        const main = document.querySelector('main')
        const footer = document.querySelector('.site-footer')
        if (header?.parentNode) header.insertAdjacentElement('afterend', shell)
        else if (main?.parentNode) main.parentNode.insertBefore(shell, main)
        else if (footer?.parentNode) footer.parentNode.insertBefore(shell, footer)
        else document.body.appendChild(shell)
        shell.querySelector('[data-pwa-dismiss]')?.addEventListener('click', () => { rememberDismiss(); hideHint() })
        shell.querySelector('[data-pwa-install]')?.addEventListener('click', async () => {
            if (!installPrompt || !eligible()) return
            try {
                await installPrompt.prompt()
                const choice = await installPrompt.userChoice
                if (choice?.outcome === 'accepted') rememberDismiss(180 * 24 * 60 * 60 * 1000)
                else rememberDismiss()
            } catch (_) {
                rememberDismiss(7 * 24 * 60 * 60 * 1000)
            }
            installPrompt = null
            hideHint()
        })
        hintShell = shell
        syncHint()
        return shell
    }
    const startInstallHint = () => {
        ensureHint()
        const observer = new MutationObserver(syncHint)
        observer.observe(document.documentElement, {attributes: true, attributeFilter: ['class']})
        if (document.body) observer.observe(document.body, {attributes: true, subtree: true, attributeFilter: ['class', 'hidden']})
        window.addEventListener('resize', syncHint, {passive: true})
        document.addEventListener('visibilitychange', syncHint)
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startInstallHint, {once: true})
    else startInstallHint()
    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault()
        installPrompt = event
        ensureHint()
        syncHint()
    })
    window.addEventListener('appinstalled', () => {
        installPrompt = null
        rememberDismiss(180 * 24 * 60 * 60 * 1000)
        applyDisplayMode()
        hideHint()
    })

    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(`/sw.js?build=${encodeURIComponent(build)}`, {scope: '/', updateViaCache: 'none'}).catch(() => {})
        }, {once: true})
    }
})()
