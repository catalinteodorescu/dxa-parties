{{-- DXA: adaugat (Aplicația participanților). Înregistrează service worker-ul de la rădăcină (scope „/”); el ignoră /admin, /receptie și /bar (vezi participant/sw.blade.php). --}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('{{ route('app.sw', [], false) }}', { scope: '/' }).catch(() => {});
        });
    }
</script>
{{-- DXA: adaugat (runda 70). Butonul „Notificări” din Cont: abonare la Web Push din browser (cardul e _push-card.blade.php, cu wire:ignore, pornit prin x-init="dxaPushInit($el)"). --}}
<script>
    window.dxaPushInit = async function (root) {
        const $ = (s) => root.querySelector(s);
        const state = (name, msg) => {
            root.dataset.state = name;
            root.querySelectorAll('[data-show]').forEach((el) => { el.style.display = el.dataset.show.split(' ').includes(name) ? '' : 'none'; });
            const m = $('[data-msg]'); if (m) m.textContent = msg || '';
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

        const reg = await navigator.serviceWorker.ready;
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
