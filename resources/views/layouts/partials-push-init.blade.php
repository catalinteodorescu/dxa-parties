{{-- DXA: adaugat (runda 70, mutat aici în runda 77). Butonul „Notificări”: abonare la Web Push din browser, folosit de aplicația participanților (_push-card.blade.php) și de personal (components/staff-push-card.blade.php), cu wire:ignore, pornit prin x-init="appPushInit($el)". Cu `data-sw` (panoul admin) înregistrează întâi service worker-ul și așteaptă să fie activ. --}}
<script>
    window.appPushInit = async function (root) {
        const $ = (s) => root.querySelector(s);
        const state = (name, msg) => {
            root.dataset.state = name;
            root.querySelectorAll('[data-show]').forEach((el) => { el.style.display = el.dataset.show.split(' ').includes(name) ? '' : 'none'; });
            const m = $('[data-msg]'); if (m) m.textContent = msg || '';
            root.dispatchEvent(new CustomEvent('push-state', { bubbles: true, detail: name }));
        };
        const post = (url, body) => fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body || {}),
        });
        const toKey = (b64) => { const p = '='.repeat((4 - b64.length % 4) % 4); const raw = atob((b64 + p).replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from([...raw].map((c) => c.charCodeAt(0))); };
        const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

        if (! ('serviceWorker' in navigator) || ! ('PushManager' in window) || ! ('Notification' in window)) {
            return state('unsupported', ios && ! standalone
                ? 'Pe iPhone, adaugă mai întâi aplicația pe ecranul principal (Distribuie → Adaugă pe ecranul principal), deschide-o de acolo și activează notificările.'
                : 'Acest browser nu poate primi notificări.');
        }

        let reg;
        if (root.dataset.sw) {
            reg = await navigator.serviceWorker.register(root.dataset.sw, { scope: root.dataset.scope || '/' });
            if (! reg.active) {
                await new Promise((resolve) => {
                    const w = reg.installing || reg.waiting;
                    if (! w) return resolve();
                    w.addEventListener('statechange', () => { if (w.state === 'activated') resolve(); });
                });
            }
        } else {
            reg = await navigator.serviceWorker.ready;
        }
        const current = async () => (Notification.permission === 'granted' ? await reg.pushManager.getSubscription() : null);
        const refresh = async () => {
            if (Notification.permission === 'denied') return state('denied', 'Notificările sunt blocate pentru această aplicație. Le poți permite din setările telefonului sau ale browserului.');
            const sub = await current();
            if (sub) { post(root.dataset.subscribe, sub.toJSON()).catch(() => {}); return state('on'); }
            return state('off');
        };

        $('[data-act="enable"]')?.addEventListener('click', async () => {
            try {
                if (await Notification.requestPermission() !== 'granted') return refresh();
                const opts = { userVisibleOnly: true, applicationServerKey: toKey(root.dataset.key) };
                let sub = await reg.pushManager.getSubscription();
                if (! sub) { sub = await reg.pushManager.subscribe(opts); }
                const res = await post(root.dataset.subscribe, sub.toJSON());
                if (! res.ok) throw new Error('subscribe');
                state('on', 'Notificările sunt active pe acest telefon.');
            } catch (e) {
                // cheia serverului s-a schimbat față de abonarea veche: o refacem
                try { const old = await reg.pushManager.getSubscription(); if (old) { await old.unsubscribe(); } } catch (_) {}
                state('off', 'Nu am putut activa notificările. Încearcă din nou.');
            }
        });
        $('[data-act="disable"]')?.addEventListener('click', async () => {
            try {
                const sub = await reg.pushManager.getSubscription();
                if (sub) { await post(root.dataset.unsubscribe, { endpoint: sub.endpoint }); await sub.unsubscribe(); }
            } catch (e) {}
            state('off', 'Notificările sunt oprite pe acest telefon.');
        });
        $('[data-act="test"]')?.addEventListener('click', async () => {
            const res = await post(root.dataset.test).catch(() => null);
            const m = $('[data-msg]'); if (m) m.textContent = res && res.ok ? 'Trimis. Ar trebui să apară în câteva secunde.' : 'Nu am putut trimite testul.';
        });

        refresh();
    };
</script>
