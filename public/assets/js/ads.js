(() => {
    const configNode = document.getElementById('stridebr-ads-config')
    if (!configNode) return

    let config = {}
    try {
        config = JSON.parse(configNode.textContent || '{}')
    } catch {
        return
    }

    const consentKey = 'stridebr.ads.consent'
    const consentBox = document.querySelector('[data-ad-consent]')
    const allowButton = document.querySelector('[data-ad-consent-allow]')
    const essentialButton = document.querySelector('[data-ad-consent-essential]')
    const slots = Array.from(document.querySelectorAll('[data-ad-placement]'))
    let loadingPromise = null

    const readConsent = () => {
        try {
            return localStorage.getItem(consentKey) || ''
        } catch {
            return ''
        }
    }

    const saveConsent = value => {
        try {
            localStorage.setItem(consentKey, value)
        } catch {}
    }

    const loadAdSense = () => {
        if (!config.enabled || !config.client) return Promise.resolve(false)
        if (loadingPromise) return loadingPromise
        loadingPromise = new Promise(resolve => {
            const existing = document.querySelector('script[data-stridebr-adsense]')
            if (existing) {
                if (window.adsbygoogle) resolve(true)
                else existing.addEventListener('load', () => resolve(true), { once: true })
                return
            }
            const script = document.createElement('script')
            script.async = true
            script.crossOrigin = 'anonymous'
            script.dataset.stridebrAdsense = '1'
            script.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${encodeURIComponent(config.client)}`
            script.addEventListener('load', () => resolve(true), { once: true })
            script.addEventListener('error', () => resolve(false), { once: true })
            document.head.appendChild(script)
        })
        return loadingPromise
    }

    const activateAds = async () => {
        if (!config.enabled || readConsent() !== 'allowed') return
        const loaded = await loadAdSense()
        if (!loaded) return

        for (const container of slots) {
            if (container.dataset.adInitialized === '1') continue
            const slot = container.dataset.adSlot || ''
            if (!slot) continue
            const placeholder = container.querySelector('[data-ad-placeholder]')
            const ad = document.createElement('ins')
            ad.className = 'adsbygoogle'
            ad.style.display = 'block'
            ad.dataset.adClient = config.client
            ad.dataset.adSlot = slot
            ad.dataset.adFormat = 'auto'
            ad.dataset.fullWidthResponsive = 'true'
            if (placeholder) placeholder.replaceWith(ad)
            else container.appendChild(ad)
            container.dataset.adInitialized = '1'
            try {
                window.adsbygoogle = window.adsbygoogle || []
                window.adsbygoogle.push({})
            } catch {}
        }
    }

    const applyConsentState = () => {
        if (!config.enabled) {
            if (consentBox) consentBox.hidden = true
            return
        }
        const state = readConsent()
        if (consentBox) consentBox.hidden = state !== ''
        if (state === 'allowed') activateAds()
    }

    allowButton?.addEventListener('click', () => {
        saveConsent('allowed')
        if (consentBox) consentBox.hidden = true
        activateAds()
    })

    essentialButton?.addEventListener('click', () => {
        saveConsent('essential')
        if (consentBox) consentBox.hidden = true
    })

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-open-ad-preferences]')
        if (!trigger || !consentBox) return
        event.preventDefault()
        consentBox.hidden = false
    })

    applyConsentState()
})()
