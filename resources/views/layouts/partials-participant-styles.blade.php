{{-- DXA: adaugat (Aplicația participanților). Stilul aplicației (întunecat, gradient, „sticlă”), autonom: nu depinde de tema panoului admin. Clase cu prefix `pa-`. --}}
<style>
    :root { --pa-bg: #120810; --pa-ink: #F7EFEA; --pa-soft: #B9A9A6; --pa-amber: #FFB36B; --pa-glass: rgba(255,255,255,.07); --pa-line: rgba(255,255,255,.14); }
    html { background: var(--pa-bg); color-scheme: dark; }
    body.pa-body { margin: 0; min-height: 100vh; color: var(--pa-ink); font-family: 'Manrope', system-ui, sans-serif; -webkit-font-smoothing: antialiased;
        background: radial-gradient(120% 45% at 0% 0%, rgba(221,100,65,.35), transparent 60%), radial-gradient(90% 40% at 100% 25%, rgba(224,72,154,.26), transparent 60%), radial-gradient(90% 40% at 50% 100%, rgba(124,77,188,.28), transparent 70%), var(--pa-bg);
        background-attachment: fixed; }
    .pa-shell { max-width: 30rem; margin: 0 auto; padding: 0 1.25rem calc(7rem + env(safe-area-inset-bottom)); }
    .pa-top { display: flex; align-items: center; justify-content: space-between; padding: calc(1rem + env(safe-area-inset-top)) 0 .5rem; }
    .pa-top img { height: 2.4rem; width: auto; display: block; }
    .pa-a { color: inherit; text-decoration: none; }
    .pa-h1 { margin: 0; font-size: 1.9rem; line-height: 1.1; font-weight: 800; }
    .pa-h2 { margin: 0; font-size: 1.15rem; font-weight: 800; }
    .pa-eyebrow { font-size: .78rem; font-weight: 800; letter-spacing: .14em; color: var(--pa-amber); text-transform: uppercase; }
    .pa-soft { color: var(--pa-soft); }
    .pa-glass { background: var(--pa-glass); border: 1px solid var(--pa-line); border-radius: 1.4rem; }
    .pa-pad { padding: 1rem; }
    .pa-stack { display: flex; flex-direction: column; gap: .75rem; }
    .pa-section { margin-top: 1.75rem; display: flex; flex-direction: column; gap: .75rem; }
    .pa-row { display: flex; align-items: center; gap: .75rem; }
    .pa-between { display: flex; align-items: baseline; justify-content: space-between; gap: .75rem; }
    .pa-btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; min-height: 3rem; padding: 0 1.4rem; border: 0; border-radius: 1.6rem; cursor: pointer; text-decoration: none;
        font: 800 .95rem 'Manrope', system-ui, sans-serif; color: #fff; background: linear-gradient(135deg, #C4512F, #B02E7C); box-shadow: 0 10px 28px rgba(221,100,65,.35); }
    .pa-btn[disabled], .pa-btn.is-off { opacity: .55; box-shadow: none; cursor: not-allowed; }
    .pa-btn-block { width: 100%; }
    .pa-btn-ghost { background: var(--pa-glass); border: 1px solid var(--pa-line); box-shadow: none; color: var(--pa-ink); }
    .pa-btn-sm { min-height: 2.75rem; padding: 0 1.1rem; font-size: .85rem; }
    .pa-link { color: var(--pa-amber); font-weight: 800; text-decoration: none; background: none; border: 0; padding: 0; cursor: pointer; font-family: inherit; font-size: inherit; }
    .pa-label { display: block; margin-bottom: .35rem; font-size: .85rem; font-weight: 700; color: var(--pa-soft); }
    .pa-input { width: 100%; box-sizing: border-box; min-height: 3rem; padding: 0 1rem; border-radius: 1rem; font: 600 1rem 'Manrope', system-ui, sans-serif; color: var(--pa-ink); background: rgba(255,255,255,.08); border: 1px solid var(--pa-line); }
    .pa-input::placeholder { color: rgba(185,169,166,.7); }
    .pa-input:focus { outline: 2px solid var(--pa-amber); outline-offset: 1px; }
    .pa-code { text-align: center; letter-spacing: .5em; font-size: 1.6rem; font-weight: 800; }
    .pa-err { margin: .35rem 0 0; font-size: .85rem; font-weight: 700; color: #ffb4a8; }
    /* Cardul de fidelitate */
    .pa-lcard { position: relative; overflow: hidden; border-radius: 1.6rem; padding: 1.25rem; color: #fff; display: flex; flex-direction: column; gap: 1.1rem; background: linear-gradient(135deg, #7C4DBC 0%, #E0489A 48%, #DD6441 100%); box-shadow: 0 18px 44px rgba(224,72,154,.32), inset 0 1px 0 rgba(255,255,255,.35); border: 1px solid rgba(255,255,255,.25); }
    .pa-lcard::before { content: ''; position: absolute; width: 15rem; height: 15rem; right: -5rem; top: -7rem; border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,.4), transparent 68%); pointer-events: none; }
    .pa-lcard::after { content: ''; position: absolute; width: 12rem; height: 12rem; left: -5rem; bottom: -7rem; border-radius: 50%; background: radial-gradient(circle, rgba(255,179,107,.55), transparent 70%); pointer-events: none; }
    .pa-lcard > * { position: relative; z-index: 1; }
    .pa-lcard-top, .pa-lcard-bottom { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; }
    .pa-lcard-brand { font-weight: 800; font-size: 1.05rem; letter-spacing: .02em; }
    .pa-lcard-kicker { font-size: .7rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; opacity: .8; margin-top: .15rem; }
    .pa-lcard-count { display: flex; align-items: baseline; gap: .25rem; }
    .pa-lcard-count b { font-size: 2.4rem; line-height: 1; font-weight: 800; }
    .pa-lcard-count small { font-size: .9rem; font-weight: 700; opacity: .8; }
    .pa-lcard-stamps { display: flex; flex-wrap: wrap; gap: .55rem; }
    .pa-lstamp { width: 2.55rem; height: 2.55rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .85rem; border: 2px solid rgba(255,255,255,.45); background: rgba(255,255,255,.1); color: rgba(255,255,255,.75); }
    .pa-lstamp svg { width: 1.2rem; height: 1.2rem; }
    .pa-lstamp.on { background: #fff; border-color: #fff; color: #C2287E; box-shadow: 0 4px 12px rgba(0,0,0,.25); }
    .pa-lstamp.gift { border-style: dashed; border-color: rgba(255,255,255,.75); color: #fff; }
    .pa-lstamp.gift.ready { background: #FFB36B; border-style: solid; border-color: #FFB36B; color: #3a1608; box-shadow: 0 0 0 4px rgba(255,179,107,.35); }
    .pa-lcard-name { font-weight: 800; font-size: 1.05rem; }
    .pa-lcard-note { font-size: .82rem; font-weight: 700; opacity: .9; margin-top: .15rem; }
    .pa-lcard-no { font-size: .85rem; font-weight: 800; letter-spacing: .12em; opacity: .85; white-space: nowrap; }
    /* Popup-uri de mesaje (succes / eroare): apar sus, centrat, dispar singure */
    .pa-toasts { position: fixed; left: 0; right: 0; top: calc(.75rem + env(safe-area-inset-top)); z-index: 60; display: flex; flex-direction: column; align-items: center; gap: .5rem; padding: 0 1rem; pointer-events: none; }
    .pa-toast { position: relative; overflow: hidden; pointer-events: auto; display: flex; align-items: center; gap: .7rem; max-width: 26rem; width: 100%; box-sizing: border-box; padding: .85rem 1rem; border-radius: 1.2rem; font-weight: 800; font-size: .92rem; color: #fff; cursor: pointer; backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); box-shadow: 0 14px 40px rgba(0,0,0,.45); border: 1px solid rgba(255,255,255,.22); }
    .pa-toast-ok { background: linear-gradient(135deg, rgba(21,128,61,.92), rgba(16,185,129,.88)); }
    .pa-toast-err { background: linear-gradient(135deg, rgba(190,30,45,.94), rgba(224,72,154,.88)); }
    .pa-toast-ico { flex: none; width: 1.9rem; height: 1.9rem; border-radius: 50%; background: rgba(255,255,255,.22); display: flex; align-items: center; justify-content: center; }
    .pa-toast-ico svg { width: 1.05rem; height: 1.05rem; }
    .pa-toast-msg { flex: 1; min-width: 0; line-height: 1.3; }
    .pa-toast-bar { position: absolute; left: 0; bottom: 0; height: 3px; width: 100%; background: rgba(255,255,255,.55); transform-origin: left; animation: pa-toast-bar linear forwards; }
    @keyframes pa-toast-bar { from { transform: scaleX(1); } to { transform: scaleX(0); } }
    .pa-toast-in { animation: pa-toast-in .28s cubic-bezier(.2,.9,.3,1.2); }
    .pa-toast-out { animation: pa-toast-out .22s ease-in forwards; }
    @keyframes pa-toast-in { from { opacity: 0; transform: translateY(-1rem) scale(.96); } to { opacity: 1; transform: none; } }
    @keyframes pa-toast-out { to { opacity: 0; transform: translateY(-.6rem) scale(.97); } }
    /* Dialog centrat (editare profil / parolă) */
    .pa-modal-bg { position: fixed; inset: 0; z-index: 55; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(8,4,4,.72); backdrop-filter: blur(4px); }
    .pa-modal { width: 100%; max-width: 24rem; max-height: 90vh; overflow-y: auto; box-sizing: border-box; padding: 1.25rem; border-radius: 1.6rem; background: #241614; border: 1px solid var(--pa-line); box-shadow: 0 24px 60px rgba(0,0,0,.55); }
    [x-cloak] { display: none !important; }
    .pa-alert { padding: .8rem 1rem; border-radius: 1rem; font-size: .9rem; font-weight: 700; }
    .pa-alert-ok { background: rgba(21,128,61,.28); color: #86efac; }
    .pa-alert-err { background: rgba(220,38,38,.25); color: #ffb4a8; }
    .pa-chip { display: inline-flex; align-items: center; gap: .35rem; height: 1.9rem; padding: 0 .8rem; border-radius: 1rem; font-size: .72rem; font-weight: 800; letter-spacing: .04em; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); color: #fff; }
    .pa-chip-amber { background: var(--pa-amber); border-color: var(--pa-amber); color: #2a1208; }
    .pa-hero { position: relative; overflow: hidden; border-radius: 1.9rem; min-height: 22rem; display: flex; flex-direction: column; justify-content: flex-end; color: #fff; text-decoration: none;
        background: radial-gradient(60% 50% at 25% 30%, #FFB36B, transparent 62%), radial-gradient(70% 60% at 85% 20%, #E0489A, transparent 64%), radial-gradient(80% 70% at 55% 95%, #7C4DBC, transparent 68%), linear-gradient(160deg, #DD6441, #4a1740); }
    /* DXA: modificat (runda 20). Fără box-shadow pe cardul hero: caruselul defilează (overflow), iar umbra era tăiată în dreptunghi = „fundal diferit” sub carusel. */
    .pa-hero > img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .pa-hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(18,8,16,0) 30%, rgba(18,8,16,.9) 100%); }
    .pa-hero-in { position: relative; z-index: 1; padding: 1.25rem; display: flex; flex-direction: column; gap: .6rem; }
    .pa-hero-top { position: absolute; z-index: 1; top: 1rem; left: 1rem; right: 1rem; display: flex; justify-content: space-between; }
    .pa-carousel { display: flex; gap: .9rem; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; margin: 0 -1.25rem; padding: 0 1.25rem; }
    .pa-carousel::-webkit-scrollbar { display: none; }
    .pa-carousel > * { flex: 0 0 100%; scroll-snap-align: center; }
    .pa-dots { display: flex; justify-content: center; gap: .5rem; margin-top: .75rem; }
    .pa-dots span { width: .5rem; height: .5rem; border-radius: .25rem; background: rgba(255,255,255,.3); transition: all .2s; }
    .pa-dots span.on { width: 1.6rem; background: var(--pa-amber); }
    .pa-date { width: 3.5rem; height: 4rem; flex: none; border-radius: 1rem; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; background: linear-gradient(160deg, #C4512F, #8E2A6B); }
    .pa-date b { font-size: 1.35rem; line-height: 1; }
    .pa-date small { font-size: .68rem; font-weight: 800; letter-spacing: .08em; }
    .pa-price { color: var(--pa-amber); font-weight: 800; }
    .pa-tier { display: flex; align-items: center; gap: .75rem; padding: .85rem 1rem; border-radius: 1.2rem; background: var(--pa-glass); border: 1px solid var(--pa-line); }
    .pa-tier.on { border: 1.5px solid var(--pa-amber); background: rgba(255,179,107,.12); }
    .pa-avatar { width: 4rem; height: 4rem; border-radius: 50%; object-fit: cover; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; color: #fff; background: linear-gradient(135deg, #DD6441, #E0489A); border: 2px solid rgba(255,255,255,.3); }
    .pa-avatar-sm { width: 2.5rem; height: 2.5rem; font-size: .95rem; }
    .pa-avatar-lg { width: 6rem; height: 6rem; font-size: 1.9rem; }
    .pa-nav { position: fixed; left: .9rem; right: .9rem; bottom: calc(.9rem + env(safe-area-inset-bottom)); max-width: 28rem; margin: 0 auto; display: flex; gap: .25rem; padding: .4rem; border-radius: 1.7rem; background: rgba(28,14,26,.88); border: 1px solid var(--pa-line); box-shadow: 0 12px 40px rgba(0,0,0,.5); backdrop-filter: blur(12px); z-index: 20; }
    .pa-nav a, .pa-nav button { flex: 1; border: 0; background: none; font-family: inherit; cursor: pointer; padding: 0; min-height: 3.3rem; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .15rem; border-radius: 1.2rem; text-decoration: none; font-size: .7rem; font-weight: 800; color: var(--pa-soft); }
    .pa-nav a.on { color: var(--pa-amber); background: rgba(255,179,107,.14); }
    .pa-nav svg { width: 1.4rem; height: 1.4rem; }
    .pa-guest { min-height: 100vh; display: flex; flex-direction: column; justify-content: center; padding: 1.5rem 1.25rem; max-width: 26rem; margin: 0 auto; gap: 1.5rem; }
    .pa-guest .logo { display: flex; justify-content: center; }
    .pa-guest .logo img { height: 3.4rem; width: auto; }
    .pa-prose { white-space: pre-line; line-height: 1.55; }
    @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
</style>
