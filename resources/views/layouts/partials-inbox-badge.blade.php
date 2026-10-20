{{-- Runda 91. Clopoțelul „Notificări”: numărul de necitite se reîmprospătează când aplicația revine în prim-plan (de exemplu după atingerea unei notificări push). Adresa e pe elementul `data-inbox-badge`. --}}
<script data-navigate-once>
(function () {
    if (window.__inboxBadge) return;
    window.__inboxBadge = true;
    function refresh() {
        var b = document.querySelector('[data-inbox-badge]');
        if (!b || !b.dataset.url) return;
        fetch(b.dataset.url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                if (!j) return;
                document.querySelectorAll('[data-inbox-badge]').forEach(function (el) {
                    el.textContent = j.n > 99 ? '99+' : String(j.n);
                    el.style.display = j.n > 0 ? '' : 'none';
                });
            }).catch(function () {});
    }
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') refresh(); });
    window.addEventListener('focus', refresh);
    window.addEventListener('pageshow', refresh);
})();
</script>
