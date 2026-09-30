// DXA participanți — service worker (scope „/”). Face aplicația instalabilă și ține în cache doar fișierele statice construite
// (/build/*, hash în nume). Restul cererilor merg mereu la server (fără offline pentru date: prețuri, bilete și credite trebuie să fie reale).
//
// IMPORTANT: scope-ul „/” acoperă tot domeniul, deci acest service worker NU trebuie să intervină pe /admin, /receptie și /bar
// (au PWA-uri proprii sau lucrează cu date live). Ignoră orice cerere făcută de o pagină din aceste zone (după referrer) și
// orice cerere către ele; pentru restul interceptează doar GET către /build/*.
const CACHE = '{{ $cache ?? 'dxa-app-static-v1' }}';
const EXCLUDED = ['/admin', '/receptie', '/bar', '/livewire', '/sesiune'];

const inExcludedArea = (path) => EXCLUDED.some((p) => path === p || path.startsWith(p + '/'));

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
        return; // rețea, fără interceptare (navigări, Livewire, API)
    }

    if (inExcludedArea(url.pathname)) {
        return;
    }

    // Fișier static cerut de o pagină din admin / recepție / bar: nu-l atinge.
    if (req.referrer) {
        try {
            if (inExcludedArea(new URL(req.referrer).pathname)) {
                return;
            }
        } catch (e) { /* referrer invalid: continuă */ }
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
