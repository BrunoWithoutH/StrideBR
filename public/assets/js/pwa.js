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
    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(`/sw.js?build=${encodeURIComponent(build)}`, {scope: '/', updateViaCache: 'none'}).catch(() => {})
        }, {once: true})
    }
})()
