{{-- DXA: adaugat (runda 71). Comportamente de dispozitiv pentru Recepție / Bar: ecran mereu aprins (Wake Lock) și fără zoom cu două degete (iOS).
     Runda 72: window.dxaFeedback(kind) = sunet + vibrație la scanare (ok / used / cancelled / error); window.dxaSound = oprire/pornire sunet (reținut pe telefon). --}}
<script data-navigate-once>
    (function () {
        if (window.__dxaDevice) { return; }
        window.__dxaDevice = true;

        var lock = null;
        function keepAwake() {
            if (!('wakeLock' in navigator) || document.visibilityState !== 'visible' || lock) { return; }
            navigator.wakeLock.request('screen').then(function (sentinel) {
                lock = sentinel;
                sentinel.addEventListener('release', function () { lock = null; });
            }).catch(function () { lock = null; });
        }
        keepAwake();
        document.addEventListener('visibilitychange', keepAwake);
        // Unele browsere cer un gest înainte de prima cerere.
        document.addEventListener('pointerdown', keepAwake, { passive: true });

        // ---- Sunet + vibrație la scanare (runda 72) ----------------------------------------------
        var ctx = null;
        function audio() {
            try {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) { return null; }
                ctx = ctx || new AC();
                if (ctx.state === 'suspended') { ctx.resume(); }
                return ctx;
            } catch (e) { return null; }
        }
        function tone(freq, start, dur, type, vol) {
            var a = audio();
            if (!a) { return; }
            var o = a.createOscillator(), g = a.createGain(), t = a.currentTime + start;
            o.type = type || 'sine'; o.frequency.value = freq;
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(vol || 0.25, t + 0.01);
            g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            o.connect(g); g.connect(a.destination);
            o.start(t); o.stop(t + dur + 0.02);
        }
        var KEY = 'dxa.sound';
        window.dxaSound = {
            enabled: function () { try { return localStorage.getItem(KEY) !== '0'; } catch (e) { return true; } },
            set: function (on) { try { localStorage.setItem(KEY, on ? '1' : '0'); } catch (e) {} if (on) { audio(); } },
            // Deblochează sunetul (iOS cere un gest): se cheamă la apăsarea „Scanează”.
            prime: function () { audio(); }
        };
        var PATTERNS = {
            ok:        { vibrate: [60],             tones: [[880, 0, 0.12, 'sine'], [1320, 0.12, 0.14, 'sine']] },
            used:      { vibrate: [90, 70, 90],     tones: [[520, 0, 0.14, 'square'], [520, 0.22, 0.14, 'square']] },
            cancelled: { vibrate: [400],            tones: [[200, 0, 0.45, 'sawtooth']] },
            error:     { vibrate: [200, 80, 200],   tones: [[300, 0, 0.18, 'square'], [220, 0.2, 0.28, 'square']] }
        };
        window.dxaFeedback = function (kind) {
            var p = PATTERNS[kind] || PATTERNS.error;
            try { if (navigator.vibrate) { navigator.vibrate(p.vibrate); } } catch (e) {}
            if (window.dxaSound.enabled()) { p.tones.forEach(function (t) { tone(t[0], t[1], t[2], t[3]); }); }
        };

        // iOS Safari ignoră user-scalable=no la pinch; gestul se oprește aici.
        document.addEventListener('gesturestart', function (e) { e.preventDefault(); });
    })();
</script>
