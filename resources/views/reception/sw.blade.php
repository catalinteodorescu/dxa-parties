// DXA Recepție — service worker. Face aplicația instalabilă și ține în cache doar fișierele statice construite
// (/build/*, hash în nume). Restul cererilor merg mereu la server: fără internet nu se înregistrează nimic (mod offline
// exclus, ca la bar/recepție cash-ul și stocurile să nu se strice).
const CACHE = 'dxa-receptie-static-v1';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);

    if (req.method !== 'GET' || url.origin !== self.location.origin || ! url.pathname.startsWith('/build/')) {
        return; // rețea, fără interceptare
    }

    event.respondWith(
        caches.open(CACHE).then((cache) =>
            cache.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok) cache.put(req, res.clone());
                return res;
            }))
        )
    );
});
