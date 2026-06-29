// Service worker mínimo para Poorman's Budget (PWA).
// Estrategia: cache del shell estático + network-first para el resto.
const CACHE = 'pmb-v1';
const SHELL = ['/icons/icon-192.png', '/icons/icon-512.png', '/manifest.json'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Solo GET y mismo origen; nunca cacheamos POST/Livewire updates.
    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;
    }

    event.respondWith(
        fetch(request)
            .then((response) => {
                // Cachea assets estáticos (build, icons).
                const url = new URL(request.url);
                if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }
                return response;
            })
            .catch(() => caches.match(request))
    );
});
