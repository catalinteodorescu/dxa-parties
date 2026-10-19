// DXA {{ $label ?? 'Recepție' }} — service worker. Face aplicația instalabilă și ține în cache doar fișierele statice construite
// (/build/*, hash în nume). Restul cererilor merg mereu la server: fără internet nu se înregistrează nimic (mod offline
// exclus, ca la bar/recepție cash-ul și stocurile să nu se strice).
const CACHE = '{{ $cache ?? 'receptie-static-v1' }}';

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

// ---- Notificări push pentru personal (runda 77) -------------------------------------------------------------------------
// Push fără conținut: serverul trimite doar un semnal; aici cerem textul de la server (cu endpoint-ul abonării, care e secret) și afișăm
// notificarea. Fără rețea afișăm una generică: browserele cer o notificare la fiecare push.
self.addEventListener('push', (event) => {
    event.waitUntil((async () => {
        let items = [];
        try {
            const sub = await self.registration.pushManager.getSubscription();
            if (sub) {
                const res = await fetch('{{ route('app.push.pending', [], false) }}', {
                    method: 'POST', credentials: 'omit',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ endpoint: sub.endpoint }),
                });
                if (res.ok) items = (await res.json()).items || [];
            }
        } catch (e) { /* fără rețea: notificare generică */ }

        if (! items.length) {
            items = [{ id: 'generic', title: '{{ $label ?? 'Recepție' }}', body: 'Ai o notificare nouă.', url: '{{ $home ?? '/' }}' }];
        }
        await Promise.all(items.map((i) => self.registration.showNotification(i.title, {
            body: i.body,
            icon: '{{ $icon ?? '' }}',
            badge: '{{ $icon ?? '' }}',
            tag: 'app-staff-' + i.id,
            data: { url: i.url || '{{ $home ?? '/' }}' },
        })));
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL((event.notification.data && event.notification.data.url) || '{{ $home ?? '/' }}', self.location.origin).href;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async (list) => {
        for (const c of list) {
            if (new URL(c.url).origin === self.location.origin && 'focus' in c) {
                try { if ('navigate' in c) { await c.navigate(target); } } catch (e) { /* rămâne pe pagina curentă */ }
                return c.focus();
            }
        }
        return self.clients.openWindow(target);
    }));
});
