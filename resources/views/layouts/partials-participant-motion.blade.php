{{-- DXA: adaugat (runda 61). Mișcare în aplicație: (1) loader între ecrane (inel degrade care se rotește), apare doar dacă schimbarea durează > 250 ms;
     (2) swipe „înapoi” de pe marginea stângă, ca în iOS, doar în aplicația instalată pe iPhone (în browser, iOS are gestul lui; pe Android există gestul de sistem).
     Rulează o singură dată (data-navigate-once): elementele se pun în <html>, ca să supraviețuiască schimbării <body> la wire:navigate. --}}
<script data-navigate-once>
(function () {
    if (window.__dxaMotion) return;
    window.__dxaMotion = true;

    /* ---------- Loader ---------- */
    var loader = null, showTimer = null, safetyTimer = null;
    function ensureLoader() {
        if (loader) return loader;
        loader = document.createElement('div');
        loader.className = 'dxa-loader';
        loader.setAttribute('role', 'status');
        loader.setAttribute('aria-label', 'Încărcare');
        loader.innerHTML = '<div class="dxa-ring"><i class="dxa-ring-arc"></i></div>';
        document.documentElement.appendChild(loader);
        return loader;
    }
    function hideLoader() {
        clearTimeout(showTimer); clearTimeout(safetyTimer);
        showTimer = safetyTimer = null;
        if (loader) loader.classList.remove('on');
    }
    function scheduleLoader() {
        clearTimeout(showTimer);
        showTimer = setTimeout(function () {
            ensureLoader();
            // un frame mai târziu, ca tranziția de opacitate să pornească
            requestAnimationFrame(function () { loader.classList.add('on'); });
            safetyTimer = setTimeout(hideLoader, 15000);   // plasă de siguranță: nu rămâne blocat dacă navigarea eșuează
        }, 250);
    }
    document.addEventListener('livewire:navigate', scheduleLoader);
    document.addEventListener('livewire:navigated', hideLoader);
    window.addEventListener('pageshow', hideLoader);
    window.addEventListener('popstate', function () { /* popstate trece tot prin livewire:navigate; dacă nu, loaderul nu apare */ });
    window.dxaLoader = { show: function () { ensureLoader(); loader.classList.add('on'); }, hide: hideLoader };

    /* ---------- Swipe înapoi (iOS, aplicație instalată) ---------- */
    // Activ pe orice ecran tactil. Dacă sistemul are propriul gest înapoi (iOS Safari, Android), el preia atingerea și browserul trimite
    // „touchcancel”: nu confirmăm nimic fără „touchend”, deci nu se întâmplă două „înapoi”.
    function enabled() {
        try { if (window.DXA_SWIPE_FORCE === true) return true; if (window.DXA_SWIPE_FORCE === false) return false; } catch (e) {}
        return window.navigator.standalone === true || 'ontouchstart' in window || (navigator.maxTouchPoints || 0) > 0;
    }

    /* Diagnostic: deschide aplicația cu ?dxdebug=1 (rămâne activ până cu ?dxdebug=0) ca să vezi pe ecran ce primește gestul. */
    var debugOn = false;
    try {
        var dq = /[?&]dxdebug=([01])/.exec(location.search);
        if (dq) { localStorage.setItem('dxa_debug', dq[1]); }
        debugOn = localStorage.getItem('dxa_debug') === '1';
    } catch (e) {}
    var dbgBox = null, dbgLines = [];
    function dbg(msg) {
        if (!debugOn) return;
        if (!dbgBox) {
            dbgBox = document.createElement('pre');
            dbgBox.style.cssText = 'position:fixed;left:6px;bottom:6px;z-index:99;margin:0;padding:6px 8px;max-width:92vw;font:11px/1.35 monospace;color:#9fffb0;background:rgba(0,0,0,.78);border-radius:8px;pointer-events:none;white-space:pre-wrap';
            document.documentElement.appendChild(dbgBox);
        }
        dbgLines.push(msg); if (dbgLines.length > 7) dbgLines.shift();
        dbgBox.textContent = 'standalone=' + (window.navigator.standalone === true) + ' touch=' + ('ontouchstart' in window) + ' hist=' + history.length + ' path=' + location.pathname + '\n' + dbgLines.join('\n');
    }
    dbg('gata (swipe ' + (enabled() ? 'activ' : 'inactiv') + ')');
    var EDGE = 30, COMMIT_DX = 90, COMMIT_V = 0.45;   // px de la margine; distanța care confirmă; viteza (px/ms) care confirmă
    var st = null, hint = null;

    function shell() { return document.querySelector('.pa-shell') || document.querySelector('.pa-guest'); }
    function modalOpen() {
        var m = document.querySelectorAll('.pa-modal-bg');
        for (var i = 0; i < m.length; i++) { if (m[i].offsetParent !== null || getComputedStyle(m[i]).display !== 'none') return true; }
        return false;
    }
    function canGoBack() { return history.length > 1 || location.pathname !== '/'; }
    function goBack() {
        if (history.length > 1) { history.back(); }
        else if (window.Livewire && Livewire.navigate) { Livewire.navigate('/'); }
        else { location.href = '/'; }
    }
    function ensureHint() {
        if (hint) return hint;
        hint = document.createElement('div');
        hint.className = 'dxa-swipe-hint';
        hint.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>';
        document.documentElement.appendChild(hint);
        return hint;
    }
    function paint(dx, animate) {
        var el = shell();
        var p = Math.max(0, Math.min(dx, window.innerWidth));
        if (el) {
            el.style.transition = animate ? 'transform .2s ease, opacity .2s ease' : 'none';
            el.style.transform = p ? 'translateX(' + p + 'px)' : '';
            el.style.opacity = p ? String(1 - Math.min(p / (window.innerWidth * 1.2), .5)) : '';
        }
        var h = ensureHint(), t = Math.min(p / COMMIT_DX, 1);
        h.style.transition = animate ? 'transform .2s ease, opacity .2s ease' : 'none';
        h.style.opacity = String(t);
        h.style.transform = 'translateX(' + (-48 + Math.min(p, 120) * 0.55 + t * 14) + 'px) scale(' + (0.7 + t * 0.3) + ')';
    }
    function reset() {
        paint(0, true);
        var el = shell();
        setTimeout(function () { if (el) { el.style.transition = ''; el.style.transform = ''; el.style.opacity = ''; } }, 260);
    }

    document.addEventListener('touchstart', function (e) {
        st = null;
        var t0 = e.touches[0];
        if (!enabled()) { dbg('start x=' + Math.round(t0.clientX) + ': inactiv'); return; }
        if (e.touches.length !== 1) { dbg('start: mai multe degete'); return; }
        if (t0.clientX > EDGE) { dbg('start x=' + Math.round(t0.clientX) + ': nu e pe margine (<=' + EDGE + ')'); return; }
        if (modalOpen()) { dbg('start x=' + Math.round(t0.clientX) + ': popup deschis'); return; }
        if (!canGoBack()) { dbg('start x=' + Math.round(t0.clientX) + ': nu există pagină anterioară'); return; }
        var t = t0;
        dbg('start x=' + Math.round(t.clientX) + ': ok');
        st = { x: t.clientX, y: t.clientY, t: Date.now(), active: false, dx: 0 };
    }, { passive: true });

    document.addEventListener('touchmove', function (e) {
        if (!st) return;
        var t = e.touches[0], dx = t.clientX - st.x, dy = t.clientY - st.y;
        if (!st.active) {
            if (Math.abs(dy) > Math.abs(dx) && Math.abs(dy) > 10) { st = null; return; }   // derulare verticală: nu e swipe
            if (dx > 10 && dx > Math.abs(dy) * 1.5) st.active = true; else return;
        }
        st.dx = dx;
        if (e.cancelable) e.preventDefault();
        if (dx % 7 < 1) dbg('trage dx=' + Math.round(dx));
        paint(dx, false);
    }, { passive: false });

    function finish() {
        if (!st) return;
        var s = st; st = null;
        if (!s.active) return;
        var v = s.dx / Math.max(Date.now() - s.t, 1);
        dbg('sfârșit dx=' + Math.round(s.dx) + ' v=' + v.toFixed(2) + (s.dx >= COMMIT_DX || (s.dx > 50 && v >= COMMIT_V) ? ' → înapoi' : ' → renunț'));
        if (s.dx >= COMMIT_DX || (s.dx > 50 && v >= COMMIT_V)) {
            paint(window.innerWidth, true);
            setTimeout(goBack, 120);
            setTimeout(reset, 900);   // dacă navigarea nu a schimbat pagina, ecranul revine
        } else {
            reset();
        }
    }
    document.addEventListener('touchend', finish, { passive: true });
    document.addEventListener('touchcancel', function () { dbg('touchcancel (sistemul a preluat gestul)'); if (st && st.active) reset(); st = null; }, { passive: true });
    document.addEventListener('livewire:navigated', function () { paint(0, false); if (hint) hint.style.opacity = '0'; });
})();
</script>
