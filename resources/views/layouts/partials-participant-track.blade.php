{{-- DXA: adaugat (runda 40). Contoare din aplicație: directiva Alpine `x-track="'p:12'"` (p = petrecere, a = anunț) numără o afișare când cardul e
     vizibil ≥ 50%, o dată pe sesiune; elementele cu `data-track-action="p:12"` numără un click pe acțiune. Evenimentele se trimit în grup
     (sendBeacon) către /m. Fără cookie și fără stocare persistentă: doar sessionStorage pentru „deja numărat în această sesiune”.
     Se încarcă ÎNAINTE de @livewireScripts (directiva se înregistrează la alpine:init). --}}
<script data-navigate-once>
    (() => {
        if (window.__paTrack) return;
        const URL_M = @js(route('app.metrics', [], false));
        const queue = [];
        const seen = new Set();
        let timer = null;
        try { JSON.parse(sessionStorage.getItem('pa_seen') || '[]').forEach((k) => seen.add(k)); } catch (e) {}

        const flush = () => {
            clearTimeout(timer); timer = null;
            if (! queue.length) return;
            const body = JSON.stringify({ e: queue.splice(0) });
            try {
                if (navigator.sendBeacon && navigator.sendBeacon(URL_M, new Blob([body], { type: 'application/json' }))) return;
                fetch(URL_M, { method: 'POST', body, headers: { 'Content-Type': 'application/json' }, keepalive: true }).catch(() => {});
            } catch (e) {}
        };

        // ref = „p:12”; ev = i (afișare) | a (click pe acțiune). Afișările se numără o dată pe sesiune.
        window.__paTrack = (ref, ev) => {
            const [t, id] = String(ref).split(':');
            if (! ['p', 'a'].includes(t) || ! (parseInt(id, 10) > 0)) return;
            if (ev === 'i') {
                const k = t + id;
                if (seen.has(k)) return;
                seen.add(k);
                try { sessionStorage.setItem('pa_seen', JSON.stringify([...seen])); } catch (e) {}
            }
            queue.push([t, parseInt(id, 10), ev]);
            if (queue.length >= 20) flush(); else if (! timer) timer = setTimeout(flush, 4000);
        };

        document.addEventListener('alpine:init', () => {
            window.Alpine.directive('track', (el, { expression }, { evaluate }) => {
                const ref = evaluate(expression);
                if (! ref || ! ('IntersectionObserver' in window)) return;
                const io = new IntersectionObserver((entries) => {
                    if (entries.some((e) => e.isIntersecting)) { window.__paTrack(ref, 'i'); io.disconnect(); }
                }, { threshold: 0.5 });
                io.observe(el);
            });
        });

        document.addEventListener('click', (ev) => {
            const a = ev.target.closest && ev.target.closest('[data-track-action]');
            if (a) { window.__paTrack(a.dataset.trackAction, 'a'); flush(); }
        }, true);
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') flush(); });
        window.addEventListener('pagehide', flush);
    })();
</script>
