(() => {
    const script = document.currentScript || document.querySelector('script[data-stridebr-i18n]')
    const locale = String(script?.dataset.locale || document.documentElement.dataset.locale || document.documentElement.lang || 'pt-BR').toLowerCase().startsWith('en') ? 'en' : 'pt-BR'
    let dictionary = {}
    try {
        const encoded = String(script?.dataset.dictionary || '')
        if (encoded) {
            const binary = atob(encoded)
            const bytes = Uint8Array.from(binary, char => char.charCodeAt(0))
            dictionary = JSON.parse(new TextDecoder().decode(bytes)) || {}
        }
    } catch (_) { dictionary = {} }

    const localeTag = locale === 'en' ? 'en-US' : 'pt-BR'
    const parseDate = value => {
        if (value instanceof Date) return value
        const raw = String(value || '').trim()
        const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})/)
        if (match) return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
        const parsed = new Date(raw)
        return Number.isNaN(parsed.getTime()) ? null : parsed
    }
    const date = (value, options = {}) => { const parsed = parseDate(value); return parsed ? new Intl.DateTimeFormat(localeTag, options).format(parsed) : '' }
    const weekdayShort = index => date(new Date(2023, 0, 1 + Number(index || 0)), {weekday:'short'}).replace(/\.$/, '')
    const weekdayLong = index => date(new Date(2023, 0, 1 + Number(index || 0)), {weekday:'long'})
    const monthYear = value => date(value, {month:'long', year:'numeric'})

    const replace = (text, values = {}) => Object.entries(values || {}).reduce((result, [key, value]) => result.split(`{${key}}`).join(String(value ?? '')), String(text ?? ''))
    const t = (key, values = {}, fallback = null) => replace(dictionary[key] ?? fallback ?? key, values)
    const number = (value, decimals = 0, trimZeros = false) => {
        const numeric = Number(value)
        if (!Number.isFinite(numeric)) return ''
        const options = {minimumFractionDigits: Math.max(0, decimals), maximumFractionDigits: Math.max(0, decimals)}
        let formatted = new Intl.NumberFormat(locale === 'en' ? 'en-US' : 'pt-BR', options).format(numeric)
        if (trimZeros && decimals > 0) {
            const decimal = locale === 'en' ? '.' : ','
            formatted = formatted.replace(new RegExp(`${decimal}0+$`), '').replace(new RegExp(`(${decimal}\\d*?)0+$`), '$1')
        }
        return formatted
    }
    const tn = (oneKey, otherKey, count, values = {}) => t(Number(count) === 1 ? oneKey : otherKey, {...values, count: values.count ?? number(count, 0)})
    const sport = (slug, fallback = '') => t(`sport.${String(slug || '').trim().toLowerCase().replaceAll('-', '_')}`, {}, fallback || slug)
    const duration = (seconds, forceClock = false) => {
        const numeric = Number(seconds)
        if (!Number.isFinite(numeric)) return ''
        const totalMilliseconds = Math.max(0, Math.round(numeric * 1000))
        const hours = Math.floor(totalMilliseconds / 3600000)
        let remaining = totalMilliseconds % 3600000
        const minutes = Math.floor(remaining / 60000)
        remaining %= 60000
        const wholeSeconds = Math.floor(remaining / 1000)
        const milliseconds = remaining % 1000
        const fraction = milliseconds ? `${locale === 'en' ? '.' : ','}${String(milliseconds).padStart(3, '0')}` : ''
        if (hours > 0) return `${hours}:${String(minutes).padStart(2, '0')}:${String(wholeSeconds).padStart(2, '0')}${fraction}`
        if (minutes > 0 || forceClock) return `${minutes}:${String(wholeSeconds).padStart(2, '0')}${fraction}`
        return `${wholeSeconds}${fraction} s`
    }

    const api = {locale, dictionary, t, tn, number, sport, date, weekdayShort, weekdayLong, monthYear, duration}
    window.StrideBRI18n = api
    document.documentElement.dataset.locale = locale
    if (!document.documentElement.lang) document.documentElement.lang = locale === 'en' ? 'en' : 'pt-BR'

    const applyElement = element => {
        if (!(element instanceof Element)) return
        if (element.dataset.i18n) element.textContent = t(element.dataset.i18n)
        if (element.dataset.i18nPlaceholder && 'placeholder' in element) element.placeholder = t(element.dataset.i18nPlaceholder)
        if (element.dataset.i18nAriaLabel) element.setAttribute('aria-label', t(element.dataset.i18nAriaLabel))
        if (element.dataset.i18nTitle) element.setAttribute('title', t(element.dataset.i18nTitle))
        if (element.dataset.i18nLegacy !== undefined) {
            let text = element.textContent || ''
            const legacy = [
                [/\bHoje\b/g, t('common.today', {}, 'Hoje')],
                [/\bAmanhã\b/g, t('common.tomorrow', {}, 'Amanhã')],
                [/\b(\d+) atividades nesta semana\b/g, (_, count) => tn('home.activities_week.one', 'home.activities_week.other', Number(count))],
            ]
            legacy.forEach(([pattern, value]) => { text = text.replace(pattern, value) })
            element.textContent = text
        }
    }
    const translateTree = root => {
        if (!(root instanceof Element || root instanceof Document)) return
        if (root instanceof Element) applyElement(root)
        root.querySelectorAll?.('[data-i18n],[data-i18n-placeholder],[data-i18n-aria-label],[data-i18n-title],[data-i18n-legacy]').forEach(applyElement)
    }
    const start = () => {
        translateTree(document)
        const observer = new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
            if (node instanceof Element) translateTree(node)
        })))
        observer.observe(document.body, {childList: true, subtree: true})
        window.StrideBRI18n.observer = observer
        document.dispatchEvent(new CustomEvent('stridebr:i18n-ready', {detail: api}))
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true})
    else start()
})()
