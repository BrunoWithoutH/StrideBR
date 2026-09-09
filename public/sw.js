const STATIC_CACHE = 'stridebr-static-v3-' + (new URL(self.location.href).searchParams.get('build') || 'rc')
const OFFLINE_URL = '/offline.html'
const STATIC_ASSETS = [
    OFFLINE_URL,
    '/assets/js/offline.js',
    '/assets/css/style.css',
    '/assets/css/ui-refresh.css',
    '/assets/js/ui-boot.js',
    '/assets/js/pwa.js',
    '/assets/img/pwa/icon-192.png',
    '/assets/img/pwa/icon-512.png',
    '/assets/img/pwa/maskable-512.png',
    '/assets/img/branding/app-icons/ios/apple-touch-icon-180x180.png',
    '/assets/img/branding/app-icons/ios/apple-touch-icon-167x167.png',
    '/assets/img/branding/app-icons/ios/apple-touch-icon-152x152.png'
]

self.addEventListener('install', event => {
    event.waitUntil(caches.open(STATIC_CACHE).then(cache => cache.addAll(STATIC_ASSETS)).then(() => self.skipWaiting()))
})

self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('stridebr-static-') && key !== STATIC_CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim()))
})

self.addEventListener('fetch', event => {
    const request = event.request
    if (request.method !== 'GET') return
    const url = new URL(request.url)
    if (url.origin !== self.location.origin) return
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request, {cache: 'no-store'}).catch(() => caches.match(OFFLINE_URL)))
        return
    }
    const staticRequest = url.pathname.startsWith('/assets/') || url.pathname === '/manifest.webmanifest'
    if (!staticRequest) return
    event.respondWith(caches.open(STATIC_CACHE).then(async cache => {
        const cached = await cache.match(request)
        try {
            const response = await fetch(request)
            if (response.ok && response.type === 'basic') cache.put(request, response.clone())
            return response
        } catch (_) {
            if (cached) return cached
            throw _
        }
    }))
})
