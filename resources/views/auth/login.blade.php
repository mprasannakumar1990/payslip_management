<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0a1630">
    <title>Sign in · {{ setting('company_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Noto+Sans+KR:wght@400;500;700;900&family=Noto+Serif+KR:wght@700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- same colour theme as inside the app, applied before the page paints --}}
    <script>
        (function () { var t = 'seoul'; try { t = localStorage.getItem('app-theme') || t; } catch (e) {} document.documentElement.setAttribute('data-theme', t); })();
    </script>
    <style>
        :root, html[data-theme="seoul"] { --d1: #07122b; --d2: #102a63; --a: #c8102e; --b: #f2b632; --c: #2f6fd6; --soft: #fdf2f4; }
        html[data-theme="purple-orange"] { --d1: #1e0b4b; --d2: #4c1d95; --a: #7c3aed; --b: #f97316; --c: #ec4899; --soft: #f5f3ff; }
        html[data-theme="purple"] { --d1: #1e1b4b; --d2: #3b0764; --a: #7c3aed; --b: #c084fc; --c: #e879f9; --soft: #f5f3ff; }
        html[data-theme="orange"] { --d1: #431407; --d2: #9a3412; --a: #ea580c; --b: #facc15; --c: #f43f5e; --soft: #fff7ed; }
        html[data-theme="ocean"] { --d1: #082f49; --d2: #0c4a6e; --a: #0284c7; --b: #22d3ee; --c: #6366f1; --soft: #f0f9ff; }
        html[data-theme="emerald"] { --d1: #022c22; --d2: #064e3b; --a: #059669; --b: #a3e635; --c: #14b8a6; --soft: #f0fdf4; }
        * { font-family: 'Inter', 'Noto Sans KR', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; box-sizing: border-box; }
        /* one screen, never scrolls */
        html, body { height: 100%; overflow: hidden; }
        body { margin: 0; height: 100vh; height: 100dvh; color: #0f172a; background: var(--d1); transition: background .6s; }

        /* ================= animated scene ================= */
        .scene { position: fixed; inset: 0; z-index: 0; overflow: hidden; background: radial-gradient(120% 90% at 15% 10%, var(--d2), var(--d1) 62%); transition: background .6s; }
        .mesh { position: absolute; border-radius: 50%; filter: blur(70px); opacity: .55; mix-blend-mode: screen; animation: drift 20s ease-in-out infinite alternate; }
        .m1 { width: 54vmax; height: 54vmax; background: var(--a); top: -22vmax; left: -16vmax; }
        .m2 { width: 46vmax; height: 46vmax; background: var(--b); bottom: -24vmax; left: 28%; animation-duration: 26s; opacity: .36; }
        .m3 { width: 40vmax; height: 40vmax; background: var(--c); top: 22%; right: -18vmax; animation-duration: 22s; opacity: .45; }
        @keyframes drift { 0% { transform: translate(0, 0) scale(1); } 50% { transform: translate(6vw, 4vh) scale(1.15); } 100% { transform: translate(-5vw, 8vh) scale(.9); } }
        .dots { position: absolute; inset: 0; background-image: radial-gradient(rgba(255,255,255,.12) 1px, transparent 1.5px); background-size: 26px 26px;
            mask-image: radial-gradient(circle at 30% 45%, #000, transparent 72%); animation: pan 40s linear infinite; }
        @keyframes pan { to { background-position: 260px 260px; } }
        .cursor-glow { position: absolute; width: 440px; height: 440px; border-radius: 50%; pointer-events: none; left: -220px; top: -220px;
            background: radial-gradient(circle, rgba(255,255,255,.15), transparent 65%); transform: translate(var(--mx, 30vw), var(--my, 40vh)); transition: transform .25s ease-out; }
        .spark { position: absolute; bottom: -10px; width: 4px; height: 4px; border-radius: 50%; background: #fff; opacity: 0; animation: up linear infinite; box-shadow: 0 0 8px #fff; }
        @keyframes up { 0% { transform: translateY(0) scale(.6); opacity: 0; } 15% { opacity: .9; } 100% { transform: translateY(-105vh) scale(1.2); opacity: 0; } }
        .star { position: absolute; width: 150px; height: 2px; border-radius: 2px; background: linear-gradient(90deg, rgba(255,255,255,.9), transparent); opacity: 0; transform: rotate(-28deg); animation: shoot 8s linear infinite; }
        .star.s2 { animation-delay: 4s; }
        @keyframes shoot { 0% { opacity: 0; translate: 0 0; } 3% { opacity: 1; } 12% { opacity: 0; translate: -42vw 22vh; } 100% { opacity: 0; translate: -42vw 22vh; } }
        .waves { position: absolute; left: 0; right: 0; bottom: 0; height: 16vh; opacity: .5; }
        .waves svg { position: absolute; bottom: 0; width: 200%; height: 100%; }
        .waves .w1 { animation: waveX 18s linear infinite; fill: color-mix(in srgb, var(--a) 45%, transparent); }
        .waves .w2 { animation: waveX 11s linear infinite reverse; fill: color-mix(in srgb, var(--b) 28%, transparent); }
        @keyframes waveX { to { transform: translateX(-50%); } }

        /* ================= Korean motifs ================= */
        .ko { font-family: 'Noto Sans KR', 'Inter', sans-serif; }
        .ko-serif { font-family: 'Noto Serif KR', serif; }
        /* 무궁화 (rose of Sharon) petals drifting down */
        .petal { position: absolute; top: -40px; width: var(--s, 16px); height: var(--s, 16px); pointer-events: none; opacity: .85; animation: fall var(--d, 14s) linear infinite; animation-delay: var(--dl, 0s); }
        .petal svg { width: 100%; height: 100%; animation: sway var(--sw, 3s) ease-in-out infinite alternate; filter: drop-shadow(0 2px 4px rgba(0,0,0,.25)); }
        @keyframes fall { 0% { transform: translate(0, -40px) rotate(0); opacity: 0; } 8% { opacity: .9; } 100% { transform: translate(var(--x, 60px), 110vh) rotate(var(--r, 540deg)); opacity: .2; } }
        @keyframes sway { from { transform: translateX(-14px) rotate(-20deg); } to { transform: translateX(14px) rotate(25deg); } }
        /* 한옥 rooftops + full moon (달), drawn line by line */
        .hanok { position: absolute; right: 1vw; bottom: 3vh; width: min(40vw, 720px); opacity: .32; }
        .hanok path, .hanok line { fill: none; stroke: #fff; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 1400; stroke-dashoffset: 1400; animation: draw 4.5s .4s cubic-bezier(.6,.1,.3,1) forwards; }
        .hanok .r2 { animation-delay: 1.2s; } .hanok .r3 { animation-delay: 2s; }
        @keyframes draw { to { stroke-dashoffset: 0; } }
        .moon { position: absolute; right: 31vw; top: 5vh; width: clamp(70px, 11vh, 120px); height: clamp(70px, 11vh, 120px); border-radius: 50%;
            background: radial-gradient(circle at 38% 38%, #fff8e1, #f2d27a 55%, #e0a93b); box-shadow: 0 0 60px 16px rgba(242,182,50,.35); opacity: .9; animation: moon 6s ease-in-out infinite; }
        @keyframes moon { 50% { box-shadow: 0 0 80px 26px rgba(242,182,50,.45); transform: translateY(-6px); } }
        /* 오방색 dancheong band */
        .dancheong { position: absolute; left: 0; right: 0; top: 0; height: 7px; z-index: 2;
            background: repeating-linear-gradient(90deg, #0047a0 0 18px, #c8102e 18px 36px, #f2b632 36px 54px, #ffffff 54px 62px, #1d1d1d 62px 70px, #0f8a5f 70px 88px);
            background-size: 88px 100%; animation: band 6s linear infinite; }
        .dancheong::after { content: ''; position: absolute; left: 0; right: 0; top: 7px; height: 5px; background: radial-gradient(circle at 6px 0, #f2b632 3px, transparent 3.5px) 0 0 / 22px 5px repeat-x; opacity: .9; }
        @keyframes band { to { background-position: 88px 0; } }
        /* hanji paper texture */
        .hanji { position: absolute; inset: 0; z-index: -2; pointer-events: none; opacity: .5;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2'/%3E%3CfeColorMatrix values='0 0 0 0 .55 0 0 0 0 .45 0 0 0 0 .35 0 0 0 .08 0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E"); }
        .card-login > .dancheong, .card-login > .hanji { opacity: 1; animation: band 6s linear infinite; }
        .card-login > .hanji { animation: none; opacity: .5; }
        /* red seal (도장) */
        .card-login > .seal, .seal { position: absolute; right: clamp(14px, 2.4vw, 26px); top: clamp(18px, 3vh, 30px); width: 46px; height: 46px; border-radius: 9px; display: grid; place-items: center;
            border: 2.5px solid #c8102e; color: #c8102e; font-family: 'Noto Serif KR', serif; font-weight: 900; font-size: .78rem; line-height: 1.05; text-align: center;
            transform: rotate(-10deg) scale(1.9); opacity: 0; animation: sealIn .5s 1.3s cubic-bezier(.2,.9,.3,1.4) forwards !important; background: rgba(200,16,46,.04); }
        @keyframes sealIn { to { transform: rotate(-10deg) scale(1); opacity: .9; } }
        .ko-line { font-family: 'Noto Serif KR', serif; font-weight: 700; font-size: clamp(1.05rem, 2.8vh, 1.6rem); letter-spacing: .02em; color: var(--b); margin: 0 0 clamp(6px, 1.2vh, 12px); opacity: 0; animation: fadeIn 1s .9s forwards; }
        .ko-line .dot { color: rgba(255,255,255,.55); margin: 0 .4em; }
        @keyframes fadeIn { to { opacity: 1; } }
        .mod b small, .step small, .ko-tag { font-family: 'Noto Sans KR', sans-serif; font-weight: 500; opacity: .7; }
        .mod b small { font-size: .72em; margin-left: 6px; }
        .step small { display: block; font-size: .85em; margin-top: -2px; }
        .field-ko { font-family: 'Noto Sans KR', sans-serif; font-weight: 500; opacity: .75; margin-left: 4px; }

        /* ================= layout ================= */
        .wrap { position: relative; z-index: 1; height: 100vh; height: 100dvh; display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, .75fr);
            padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left); }
        .hero, .panel { min-width: 0; min-height: 0; }
        .hero { color: #fff; height: 100%; overflow: hidden; padding: clamp(16px, 3.6vh, 42px) clamp(20px, 4vw, 64px);
            display: flex; flex-direction: column; justify-content: space-between; gap: clamp(10px, 2.2vh, 24px); }

        /* brand */
        .brand { display: flex; align-items: center; gap: 14px; animation: inL .7s cubic-bezier(.2,.8,.2,1) both; }
        .brand-mark { position: relative; width: clamp(46px, 7vh, 60px); height: clamp(46px, 7vh, 60px); border-radius: 18px; display: grid; place-items: center;
            font-size: clamp(1.3rem, 3vh, 1.7rem); color: #fff; flex: 0 0 auto; background: linear-gradient(135deg, var(--b), var(--a)); box-shadow: 0 10px 30px rgba(0,0,0,.35); }
        .brand-mark::after { content: ''; position: absolute; inset: -6px; border-radius: 22px; border: 2px solid rgba(255,255,255,.35); animation: ping 2.4s cubic-bezier(0,0,.2,1) infinite; }
        @keyframes ping { 75%, 100% { transform: scale(1.3); opacity: 0; } }
        .brand-name { font-size: clamp(1.05rem, 2.6vh, 1.45rem); font-weight: 900; letter-spacing: -.01em; line-height: 1.15; }
        .brand-addr { font-size: clamp(.78rem, 1.7vh, .92rem); opacity: .75; display: flex; align-items: center; gap: 6px; }
        .clock-chip { margin-left: auto; display: flex; align-items: center; gap: 10px; padding: 9px 14px; border-radius: 14px; background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.18); backdrop-filter: blur(10px); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .clock-chip .t { font-weight: 800; font-size: clamp(.95rem, 2.2vh, 1.15rem); }
        .clock-chip .d { font-size: .78rem; opacity: .75; line-height: 1.1; } .clock-chip .tz { color: var(--b); font-weight: 800; }
        .colon { animation: blink 1s steps(1) infinite; color: var(--b); } @keyframes blink { 50% { opacity: .2; } }

        /* headline */
        .eyebrow { display: inline-flex; align-items: center; gap: 10px; padding: 7px 14px; border-radius: 999px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18);
            font-size: clamp(.8rem, 1.8vh, .95rem); font-weight: 600; animation: inL .7s .05s both; }
        .wave { display: inline-block; animation: hand 2.2s ease-in-out infinite; transform-origin: 70% 70%; }
        @keyframes hand { 0%, 60%, 100% { transform: rotate(0); } 10%, 30% { transform: rotate(14deg); } 20%, 40% { transform: rotate(-8deg); } }
        .hero h1 { font-size: clamp(2rem, min(4.8vw, 7.6vh), 4.8rem); font-weight: 900; line-height: 1.02; letter-spacing: -.035em; margin: clamp(8px, 1.6vh, 18px) 0 clamp(6px, 1.4vh, 14px); }
        .hero h1 .w { display: inline-block; opacity: 0; transform: translateY(.6em) rotate(4deg); animation: word .7s cubic-bezier(.2,.8,.2,1) forwards; }
        @keyframes word { to { opacity: 1; transform: none; } }
        .hero h1 .grad { background: linear-gradient(90deg, #fff, var(--b), var(--c), #fff); background-size: 300% 100%; -webkit-background-clip: text; background-clip: text; color: transparent;
            animation: word .7s cubic-bezier(.2,.8,.2,1) forwards, shine 6s linear infinite; }
        @keyframes shine { to { background-position: 300% 0; } }
        .lead { font-size: clamp(1rem, 2.4vh, 1.35rem); line-height: 1.45; color: rgba(255,255,255,.88); max-width: 760px; margin: 0; animation: inL .8s .35s both; }
        .typer { font-size: clamp(.92rem, 2vh, 1.1rem); color: var(--b); font-weight: 600; min-height: 1.5em; margin-top: 6px; }
        .typer .caret { display: inline-block; width: 2px; height: 1.05em; background: var(--b); vertical-align: -3px; margin-left: 2px; animation: blink .9s steps(1) infinite; }

        /* modules */
        .modules { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: clamp(8px, 1.4vh, 14px); }
        .mod { position: relative; display: flex; gap: 12px; align-items: flex-start; padding: clamp(10px, 1.7vh, 16px); border-radius: 18px; overflow: hidden;
            background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15); backdrop-filter: blur(10px); opacity: 0; animation: modIn .6s cubic-bezier(.2,.8,.2,1) forwards;
            transition: transform .25s cubic-bezier(.2,.9,.3,1.3), background .25s, border-color .25s; }
        .mod:hover { transform: translateY(-4px) scale(1.02); background: rgba(255,255,255,.15); border-color: rgba(255,255,255,.3); }
        .mod::before { content: ''; position: absolute; inset: 0; background: linear-gradient(115deg, transparent 40%, rgba(255,255,255,.14), transparent 60%); transform: translateX(-100%); animation: modShine 7s ease-in-out infinite; animation-delay: var(--sd, 0s); }
        @keyframes modShine { 0%, 70% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        @keyframes modIn { from { opacity: 0; transform: translateY(18px) scale(.96); } to { opacity: 1; transform: none; } }
        .mod .ic { width: clamp(38px, 5.6vh, 48px); height: clamp(38px, 5.6vh, 48px); border-radius: 14px; display: grid; place-items: center; flex: 0 0 auto;
            font-size: clamp(1.1rem, 2.6vh, 1.35rem); color: #fff; background: linear-gradient(135deg, var(--g1), var(--g2)); box-shadow: 0 8px 18px rgba(0,0,0,.25); animation: bob 4s ease-in-out infinite; animation-delay: var(--sd, 0s); }
        @keyframes bob { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
        .mod b { display: block; font-size: clamp(.92rem, 2.1vh, 1.08rem); font-weight: 800; margin-bottom: 2px; }
        .mod span { display: block; font-size: clamp(.74rem, 1.65vh, .86rem); line-height: 1.35; color: rgba(255,255,255,.78); }

        /* monthly workflow strip */
        .flow { position: relative; display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 6px; padding: clamp(10px, 1.6vh, 14px) clamp(12px, 1.6vw, 18px); border-radius: 18px;
            background: rgba(0,0,0,.18); border: 1px solid rgba(255,255,255,.12); animation: inL 1s .5s both; }
        .flow-title { grid-column: 1 / -1; display: flex; justify-content: space-between; font-size: clamp(.74rem, 1.6vh, .85rem); font-weight: 700; letter-spacing: .06em; text-transform: uppercase; opacity: .8; margin-bottom: 4px; }
        .flow-title em { font-style: normal; color: var(--b); text-transform: none; letter-spacing: 0; }
        .step { display: flex; flex-direction: column; align-items: center; gap: 5px; text-align: center; font-size: clamp(.7rem, 1.55vh, .82rem); font-weight: 600; color: rgba(255,255,255,.6); position: relative; transition: color .3s; }
        .step i { width: clamp(30px, 4.4vh, 38px); height: clamp(30px, 4.4vh, 38px); border-radius: 50%; display: grid; place-items: center; font-size: clamp(.9rem, 2vh, 1.05rem);
            background: rgba(255,255,255,.1); border: 2px solid rgba(255,255,255,.2); transition: all .35s cubic-bezier(.2,.9,.3,1.4); position: relative; z-index: 1; }
        .step:not(:last-child)::after { content: ''; position: absolute; top: calc(clamp(30px, 4.4vh, 38px) / 2); left: 60%; width: 80%; height: 2px; background: rgba(255,255,255,.18); transition: background .35s; }
        .step.on { color: #fff; } .step.on i { background: linear-gradient(135deg, var(--b), var(--c)); border-color: transparent; transform: scale(1.15); box-shadow: 0 0 0 6px rgba(255,255,255,.08); }
        .step.done i { background: #22c55e; border-color: transparent; } .step.done { color: rgba(255,255,255,.9); }
        .step.done::after { background: #22c55e; }
        .foot { display: flex; flex-wrap: wrap; gap: 14px; font-size: clamp(.72rem, 1.5vh, .82rem); opacity: .65; }
        .foot i { color: var(--b); }
        @keyframes inL { from { opacity: 0; transform: translateX(-24px); } }

        /* mobile ticker */
        .ticker { display: none; overflow: hidden; mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent); }
        .ticker-track { display: flex; gap: 8px; width: max-content; animation: tick 26s linear infinite; }
        .ticker-track > span { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; font-size: .8rem; font-weight: 600; white-space: nowrap; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); }
        .ticker i { color: var(--b); } .ticker .ko-tag { margin-left: 2px; }
        @keyframes tick { to { transform: translateX(-50%); } }

        /* ================= sign-in card ================= */
        .panel { height: 100%; display: flex; align-items: center; justify-content: center; padding: clamp(10px, 2.4vh, 30px) clamp(12px, 3vw, 36px) clamp(10px, 2.4vh, 30px) 0; perspective: 1200px; }
        .card-zone { position: relative; width: 100%; max-width: 450px; max-height: 100%; display: flex; }
        .deco { position: absolute; pointer-events: none; z-index: 0; }
        .deco.ring { width: 120px; height: 120px; border-radius: 50%; border: 3px dashed color-mix(in srgb, var(--b) 70%, transparent); top: -40px; right: -36px; animation: spin 18s linear infinite; }
        .deco.blob { width: 110px; height: 110px; border-radius: 50%; background: radial-gradient(circle at 35% 35%, var(--b), var(--c) 60%, transparent 72%); bottom: -46px; left: -50px; opacity: .55; filter: blur(4px); animation: float 6s ease-in-out infinite, spin 30s linear infinite; }
        .deco.dotgrid { width: 110px; height: 70px; background-image: radial-gradient(rgba(255,255,255,.55) 1.6px, transparent 2px); background-size: 14px 14px; top: 38%; left: -60px; animation: float 7s ease-in-out infinite reverse; }
        @keyframes spin { to { transform: rotate(360deg); } } @keyframes float { 50% { translate: 0 -14px; } }
        .card-shell { position: relative; z-index: 1; flex: 1; border-radius: 30px; padding: 2px; animation: rise .8s .15s cubic-bezier(.2,.8,.2,1) both;
            transform: rotateY(var(--ty, 0deg)) rotateX(var(--tx, 0deg)); transition: transform .35s ease-out; }
        .card-shell::before { content: ''; position: absolute; inset: 0; border-radius: 30px; padding: 2px; background: conic-gradient(from var(--ang, 0deg), var(--a), var(--b), var(--c), var(--a));
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0); -webkit-mask-composite: xor; mask-composite: exclude; animation: rot 5s linear infinite; }
        .card-shell::after { content: ''; position: absolute; inset: -30px; z-index: -1; border-radius: 50px; background: radial-gradient(circle at 50% 40%, color-mix(in srgb, var(--a) 55%, transparent), transparent 70%); filter: blur(30px); animation: glowP 4s ease-in-out infinite; }
        @keyframes glowP { 50% { opacity: .55; transform: scale(1.05); } }
        @property --ang { syntax: '<angle>'; initial-value: 0deg; inherits: false; }
        @keyframes rot { to { --ang: 360deg; } }
        @keyframes rise { from { opacity: 0; transform: translateY(30px) scale(.96) rotateX(8deg); } }
        .card-login { position: relative; height: 100%; border-radius: 28px; padding: clamp(16px, 3.4vh, 36px) clamp(18px, 2.6vw, 34px); background: rgba(255,255,255,.97);
            box-shadow: 0 40px 90px rgba(0,0,0,.45); max-height: calc(100dvh - 24px); overflow: auto; scrollbar-width: none; isolation: isolate; }
        .card-login::-webkit-scrollbar { display: none; }
        .card-login::before { content: ''; position: absolute; top: 0; left: -80%; width: 50%; height: 100%; z-index: -1; pointer-events: none;
            background: linear-gradient(100deg, transparent, color-mix(in srgb, var(--a) 9%, transparent), transparent); transform: skewX(-18deg); animation: glass 7s ease-in-out infinite; }
        @keyframes glass { 0%, 60% { left: -80%; } 100% { left: 140%; } }
        .card-shell.shake { animation: shake .5s cubic-bezier(.36,.07,.19,.97); }
        @keyframes shake { 10%, 90% { transform: translateX(-2px); } 20%, 80% { transform: translateX(4px); } 30%, 50%, 70% { transform: translateX(-8px); } 40%, 60% { transform: translateX(8px); } }

        .top-row { display: flex; align-items: center; gap: 14px; padding-right: 50px; margin-bottom: clamp(10px, 2.2vh, 22px); }
        .lock-badge { position: relative; width: clamp(50px, 7.4vh, 62px); height: clamp(50px, 7.4vh, 62px); border-radius: 19px; display: grid; place-items: center; font-size: clamp(1.4rem, 3.2vh, 1.7rem); color: #fff; flex: 0 0 auto;
            background: linear-gradient(135deg, var(--a), var(--c), var(--b)); background-size: 200% 200%; box-shadow: 0 12px 26px rgba(0,0,0,.2); animation: pop .6s .4s cubic-bezier(.2,.9,.3,1.4) both, grad 5s ease infinite; }
        @keyframes grad { 50% { background-position: 100% 100%; } }
        .lock-badge::after { content: ''; position: absolute; inset: -6px; border-radius: 23px; border: 2px solid var(--a); opacity: .5; animation: ping 2.6s cubic-bezier(0,0,.2,1) infinite; }
        .lock-badge i { animation: wiggle 4s ease-in-out infinite; } @keyframes wiggle { 0%, 88%, 100% { transform: rotate(0); } 92% { transform: rotate(-12deg); } 96% { transform: rotate(10deg); } }
        @keyframes pop { from { transform: scale(.4) rotate(-20deg); opacity: 0; } }
        .card-login h2 { font-weight: 900; font-size: clamp(1.4rem, 3.6vh, 1.95rem); letter-spacing: -.025em; margin: 0; line-height: 1.1; }
        .card-login h2 .hl { background: linear-gradient(90deg, var(--a), var(--c), var(--b)); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .sub { color: #64748b; font-size: clamp(.84rem, 1.9vh, .95rem); margin-top: 3px; }
        .stagger > * { opacity: 0; animation: fadeUp .55s cubic-bezier(.2,.8,.2,1) forwards; }
        .stagger > *:nth-child(1) { animation-delay: .35s; } .stagger > *:nth-child(2) { animation-delay: .45s; } .stagger > *:nth-child(3) { animation-delay: .55s; }
        .stagger > *:nth-child(4) { animation-delay: .65s; } .stagger > *:nth-child(5) { animation-delay: .75s; } .stagger > *:nth-child(6) { animation-delay: .85s; }
        .stagger > *:nth-child(7) { animation-delay: .95s; } .stagger > *:nth-child(n+8) { animation-delay: 1.05s; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }

        .fl { position: relative; margin-bottom: clamp(10px, 1.9vh, 16px); }
        .fl input { width: 100%; height: clamp(50px, 7vh, 58px); padding: 20px 48px 4px 52px; border-radius: 15px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-size: 1rem; outline: 0;
            transition: border-color .2s, background .2s, box-shadow .2s; }
        .fl label { position: absolute; left: 52px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: .95rem; pointer-events: none; transition: all .18s ease; }
        .fl input:focus + label, .fl input:not(:placeholder-shown) + label { top: 12px; transform: none; font-size: .7rem; font-weight: 700; color: var(--a); letter-spacing: .03em; }
        .fl .ico { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 30px; height: 30px; border-radius: 10px; display: grid; place-items: center; color: #94a3b8; font-size: 1.05rem; transition: all .25s; z-index: 1; }
        .fl:focus-within .ico { color: #fff; background: linear-gradient(135deg, var(--a), var(--c)); transform: translateY(-50%) rotate(-8deg) scale(1.05); }
        .fl .bar { position: absolute; left: 14px; right: 14px; bottom: 0; height: 3px; border-radius: 3px; background: linear-gradient(90deg, var(--a), var(--c), var(--b)); transform: scaleX(0); transition: transform .35s cubic-bezier(.2,.8,.2,1); }
        .fl:focus-within .bar { transform: scaleX(1); }
        .fl input:focus { border-color: color-mix(in srgb, var(--a) 60%, #e2e8f0); background: #fff; box-shadow: 0 10px 24px color-mix(in srgb, var(--a) 14%, transparent); }
        .fl input.invalid { border-color: #ef4444; background: #fef2f2; }
        .fl .ok { position: absolute; right: 16px; top: 50%; transform: translateY(-50%) scale(0); color: #16a34a; font-size: 1.1rem; transition: transform .3s cubic-bezier(.2,.9,.3,1.5); }
        .fl.valid .ok { transform: translateY(-50%) scale(1); }
        .eye { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: #64748b; padding: 8px 10px; border-radius: 10px; }
        .eye:hover { background: #f1f5f9; color: var(--a); }
        .hint { font-size: .78rem; margin: -6px 0 10px; display: none; }
        .hint.show { display: flex; gap: 6px; align-items: center; animation: fadeUp .25s ease both; }
        .note { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border-radius: 13px; font-size: .86rem; margin-bottom: 12px; animation: fadeUp .35s ease both; }
        .note.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; } .note.ok { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

        .switch { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; font-size: .88rem; color: #334155; }
        .switch input { display: none; }
        .switch .track { width: 42px; height: 24px; border-radius: 999px; background: #cbd5e1; position: relative; transition: background .2s; flex: 0 0 42px; }
        .switch .track::after { content: ''; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2); transition: transform .22s cubic-bezier(.2,.9,.3,1.3); }
        .switch input:checked + .track { background: linear-gradient(90deg, var(--a), var(--c)); } .switch input:checked + .track::after { transform: translateX(18px); }
        .row-opts { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: clamp(12px, 2.3vh, 22px); }

        .btn-go { position: relative; width: 100%; height: clamp(50px, 7vh, 58px); border: 0; border-radius: 15px; color: #fff; font-weight: 800; font-size: 1.05rem; overflow: hidden;
            background: linear-gradient(135deg, var(--a), var(--c) 55%, var(--b)); background-size: 200% 200%; box-shadow: 0 14px 30px color-mix(in srgb, var(--a) 40%, transparent);
            transition: transform .15s, box-shadow .2s; animation: grad 6s ease infinite; }
        .btn-go:hover { transform: translateY(-2px); box-shadow: 0 18px 36px color-mix(in srgb, var(--a) 50%, transparent); }
        .btn-go:active { transform: translateY(0) scale(.98); }
        .btn-go::after { content: ''; position: absolute; top: 0; left: -60%; width: 40%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,.45), transparent); transform: skewX(-20deg); animation: sweep 3.2s ease-in-out infinite; }
        @keyframes sweep { 0%, 55% { left: -60%; } 100% { left: 130%; } }
        .btn-go .idle { display: inline-flex; align-items: center; gap: 10px; transition: opacity .2s, transform .2s; }
        .btn-go .arrow { transition: transform .25s; font-size: 1.2rem; } .btn-go:hover .arrow { transform: translateX(6px) rotate(-10deg); }
        .btn-go .busy { position: absolute; inset: 0; display: grid; place-items: center; opacity: 0; transform: translateY(10px); transition: all .2s; }
        .btn-go.loading .idle { opacity: 0; transform: translateY(-10px); } .btn-go.loading .busy { opacity: 1; transform: none; }
        .ripple { position: absolute; border-radius: 50%; background: rgba(255,255,255,.5); transform: scale(0); animation: rip .6s ease-out forwards; pointer-events: none; }
        @keyframes rip { to { transform: scale(3); opacity: 0; } }

        .trust { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: clamp(12px, 2.2vh, 20px); }
        .trust div { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 9px 6px; border-radius: 14px; background: #f8fafc; border: 1px solid #eef2f7; font-size: .72rem; color: #475569; text-align: center; transition: transform .2s, box-shadow .2s; }
        .trust div:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(15,23,42,.08); }
        .trust i { width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; font-size: .95rem; background: var(--soft); color: var(--a); }
        .trust b { color: #0f172a; font-size: .78rem; }
        .bottom { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: clamp(10px, 2vh, 18px); font-size: .74rem; color: #94a3b8; }
        .themes { display: flex; gap: 7px; }
        .themes button { width: 21px; height: 21px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 1px #e2e8f0; padding: 0; transition: transform .2s; }
        .themes button:hover { transform: scale(1.25); } .themes button.on { box-shadow: 0 0 0 2px var(--a); transform: scale(1.15); }

        /* ----- responsive: always one screen ----- */
        @media (max-height: 860px) { .mod span { display: none; } .mod { align-items: center; } }
        @media (max-height: 760px) { .flow { display: none; } .trust { display: none; } }
        @media (max-height: 640px) { .modules { display: none; } .lead { font-size: 1rem; } .sub { display: none; } }
        @media (max-width: 1279.98px) { .modules { grid-template-columns: repeat(2, minmax(0, 1fr)); } .modules .mod:nth-child(n+5) { display: none; } .deco.dotgrid { display: none; } }
        @media (max-width: 1100px) { .flow-title em { display: none; } }

        /* tablets & phones: compact header on top, sign-in card fills the rest */
        @media (max-width: 991.98px) {
            .wrap { grid-template-columns: minmax(0, 1fr); grid-template-rows: auto minmax(0, 1fr); }
            .hero { height: auto; padding: clamp(12px, 2.4vh, 22px) 18px 0; gap: 10px; justify-content: flex-start; }
            .modules, .flow, .foot, .lead, .eyebrow, .typer { display: none; }
            .ticker { display: block; }
            .hero h1 { font-size: clamp(1.45rem, 3.8vh, 2.3rem); margin: 4px 0 0; }
            .clock-chip .d { display: none; }
            .panel { padding: 10px 14px 14px; align-items: center; perspective: none; }
            .card-zone { max-width: 470px; }
            .deco { display: none; }
            .card-shell { transform: none !important; }
            .waves { height: 10vh; }
        }
        @media (max-width: 991.98px) and (min-height: 900px) { .lead, .typer { display: block; } .lead { font-size: 1.1rem; } .hero h1 { font-size: 2.6rem; } }
        @media (max-width: 991.98px) and (max-height: 700px) { .ticker { display: none; } .trust { display: none; } }
        @media (max-width: 991.98px) and (max-height: 600px) { .hero h1 { display: none; } .top-row { display: none; } }
        @media (max-width: 420px) { .brand-addr { display: none; } .brand-name { font-size: .98rem; } .brand-mark { width: 44px; height: 44px; } .clock-chip { padding: 6px 10px; } .trust div:last-child { display: none; } .trust { grid-template-columns: 1fr 1fr; } }
        @media (max-height: 480px) and (orientation: landscape) {
            .wrap { grid-template-columns: minmax(0, .8fr) minmax(0, 1.2fr); grid-template-rows: none; }
            .hero { height: 100%; justify-content: center; } .ticker { display: none; } .hero h1 { display: block; font-size: 1.35rem; }
            .top-row, .trust, .bottom { display: none; } .panel { padding: 8px; }
        }
        @media (max-width: 991.98px) { .hanok { right: 0; width: 100vw; bottom: 2vh; opacity: .18; } .moon { top: 8px; right: 38%; width: 34px; height: 34px; box-shadow: 0 0 30px 8px rgba(242,182,50,.35); } .card-login > .seal { width: 38px; height: 38px; font-size: .64rem; right: 12px; top: 16px; } .top-row { padding-right: 40px; } .ko-line { font-size: 1rem; margin: 2px 0 0; } }
        @media (max-width: 991.98px) and (max-height: 760px) { .ko-line { display: none; } }
        @media (max-height: 760px) { .step small { display: none; } }
        @media (max-width: 420px) { .field-ko { display: none; } .card-login > .seal { display: none; } .top-row { padding-right: 0; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; transition: none !important; } .stagger > *, .hero h1 .w, .mod, .ko-line { opacity: 1; transform: none; } .hanok path { stroke-dashoffset: 0; } .seal { opacity: .9; transform: rotate(-10deg); } }
    </style>
</head>
<body>
@include('layouts._loader')
<div class="scene" aria-hidden="true">
    <div class="mesh m1"></div><div class="mesh m2"></div><div class="mesh m3"></div>
    <div class="dots"></div>
    <span class="star" style="top:10%;left:82%"></span><span class="star s2" style="top:34%;left:64%"></span>
    <div class="moon"></div>
    <svg class="hanok" viewBox="0 0 980 170" preserveAspectRatio="xMidYMax meet">
        <path d="M40 108 Q 70 104 92 92 Q 170 64 260 64 Q 350 64 428 92 Q 450 104 480 108 M110 108 L410 108 M140 108 L140 160 M380 108 L380 160 M120 160 L400 160 M210 64 Q 260 52 310 64"/>
        <path class="r2" d="M470 128 Q 500 124 520 114 Q 590 90 670 90 Q 750 90 820 114 Q 840 124 870 128 M535 128 L805 128 M560 128 L560 165 M780 128 L780 165 M545 165 L795 165 M630 90 Q 670 80 710 90"/>
        <path class="r3" d="M0 168 L980 168 M860 140 Q 885 136 900 128 Q 930 116 960 116 Q 975 116 980 118"/>
    </svg>
    <div class="cursor-glow" id="glow"></div>
    <div id="petals"></div>
    <div id="sparks"></div>
    <div class="waves">
        <svg class="w1" viewBox="0 0 1440 120" preserveAspectRatio="none"><path d="M0 60 Q 180 10 360 60 T 720 60 T 1080 60 T 1440 60 V120 H0Z"/><path transform="translate(1440 0)" d="M0 60 Q 180 10 360 60 T 720 60 T 1080 60 T 1440 60 V120 H0Z"/></svg>
        <svg class="w2" viewBox="0 0 1440 120" preserveAspectRatio="none"><path d="M0 80 Q 240 40 480 80 T 960 80 T 1440 80 V120 H0Z"/><path transform="translate(1440 0)" d="M0 80 Q 240 40 480 80 T 960 80 T 1440 80 V120 H0Z"/></svg>
    </div>
</div>

@php
    $modules = [
        ['bi-people-fill', 'Employees', '직원 관리', 'Photos, salary details, birthdays & work anniversaries', '#60a5fa', '#2563eb'],
        ['bi-calendar2-check-fill', 'Yearly leave', '연차 휴가', 'CL · SL · EL created for everyone every January', '#34d399', '#059669'],
        ['bi-clock-history', 'OT & shifts', '초과근무', 'Plant-wise hours, approvals and OT pay', '#fbbf24', '#ea580c'],
        ['bi-cash-stack', 'Payroll & payslips', '급여 명세서', 'Salary-sheet layout, Form-T payslips, PDF', '#f472b6', '#db2777'],
        ['bi-file-earmark-excel-fill', 'Excel import', '엑셀 업로드', 'Salary, OT, leave & employee sheets checked row by row', '#4ade80', '#15803d'],
        ['bi-envelope-paper-fill', 'Email & backups', '이메일 · 백업', 'Payslips to every inbox, daily SQL + Excel backup', '#a78bfa', '#7c3aed'],
    ];
    $steps = [['bi-people', 'Employees', '직원'], ['bi-calendar2-check', 'Leave', '휴가'], ['bi-clock', 'OT', '초과근무'], ['bi-gear-wide-connected', 'Generate', '생성'], ['bi-lock', 'Finalize', '확정'], ['bi-send', 'Email', '발송']];
    $deadline = (int) setting('payroll_deadline_day', 10);
@endphp

<div class="wrap">
    {{-- ===== left / top: everything about the system ===== --}}
    <section class="hero">
        <div class="brand">
            <div class="brand-mark"><i class="bi bi-receipt-cutoff"></i></div>
            <div class="min-w-0">
                <div class="brand-name">{{ setting('company_name') }}</div>
                <div class="brand-addr"><i class="bi bi-geo-alt-fill"></i> {{ setting('company_address') ?: 'Payslip & HR Suite' }}</div>
            </div>
            <div class="clock-chip" id="clock" data-server-ms="{{ now()->getTimestampMs() }}" title="India Standard Time">
                <i class="bi bi-clock-fill" style="color:var(--b)"></i>
                <div><div class="t" data-t>--:--</div><div class="d"><span data-d></span> · <span class="tz">IST</span></div></div>
            </div>
        </div>

        <div>
            <span class="eyebrow"><span class="wave">👋</span><span><b class="ko" id="greetKo">안녕하세요</b> · <span id="greet">Hello</span> — welcome to the Payslip &amp; HR Suite</span></span>
            <h1 id="headline"><span class="w">Pay</span> <span class="w">your</span> <span class="w">team</span> <span class="w">right,</span><br><span class="w grad">every single month.</span></h1>
            <div class="ko-line">매달 정확하게<span class="dot">·</span>모두에게 공정하게</div>
            <p class="lead">Employees, yearly leave, overtime and payroll in one place — from the Excel salary sheet to a payslip in every employee's inbox, ready by the {{ $deadline }}th.</p>
            <div class="typer"><span id="typer"></span><span class="caret"></span></div>
        </div>

        <div class="ticker" aria-hidden="true">
            <div class="ticker-track">
                @foreach (array_merge($modules, $modules) as [$icon, $title, $ko])
                    <span><i class="bi {{ $icon }}"></i> {{ $title }} <span class="ko-tag">{{ $ko }}</span></span>
                @endforeach
            </div>
        </div>

        <div class="modules">
            @foreach ($modules as $i => [$icon, $title, $ko, $text, $g1, $g2])
                <div class="mod" style="--g1: {{ $g1 }}; --g2: {{ $g2 }}; --sd: {{ $i * 0.6 }}s; animation-delay: {{ 0.55 + $i * 0.09 }}s">
                    <span class="ic"><i class="bi {{ $icon }}"></i></span>
                    <div class="min-w-0"><b>{{ $title }}<small>{{ $ko }}</small></b><span>{{ $text }}</span></div>
                </div>
            @endforeach
        </div>

        <div class="flow" aria-label="Monthly payslip routine">
            <div class="flow-title"><span><i class="bi bi-arrow-repeat"></i> Every month · <span class="ko">매월 업무 흐름</span></span><em>1st – {{ $deadline }}th of the next month</em></div>
            @foreach ($steps as $i => [$icon, $label, $ko])
                <div class="step"><i class="bi {{ $icon }}"></i>{{ $i + 1 }}. {{ $label }}<small>{{ $ko }}</small></div>
            @endforeach
        </div>

        <div class="foot">
            <span><i class="bi bi-shield-lock-fill"></i> Private — only authorised admins</span>
            <span><i class="bi bi-clock-fill"></i> Every record saved in IST</span>
            <span>© {{ now()->year }} {{ setting('company_name') }}</span>
        </div>
    </section>

    {{-- ===== right / bottom: sign-in ===== --}}
    <section class="panel">
        <div class="card-zone">
            <span class="deco ring" aria-hidden="true"></span><span class="deco blob" aria-hidden="true"></span><span class="deco dotgrid" aria-hidden="true"></span>
            <div class="card-shell {{ $errors->any() ? 'shake' : '' }}" id="card">
                <div class="card-login stagger">
                    <span class="dancheong" aria-hidden="true"></span><span class="hanji" aria-hidden="true"></span>
                    <span class="seal ko-serif" aria-hidden="true" title="태성">태성<br>급여</span>
                    <div class="top-row">
                        <div class="lock-badge"><i class="bi bi-person-lock"></i></div>
                        <div class="min-w-0">
                            <h2>Welcome <span class="hl">back!</span></h2>
                            <div class="sub"><span class="ko">다시 오신 것을 환영합니다</span> · Sign in to your workspace</div>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="note err" role="alert"><i class="bi bi-exclamation-octagon-fill mt-1"></i><div>{{ $errors->first() }}</div></div>
                    @elseif (session('status'))
                        <div class="note ok" role="status"><i class="bi bi-check-circle-fill mt-1"></i><div>{{ session('status') }}</div></div>
                    @endif

                    <form method="POST" action="{{ route('login.attempt') }}" id="loginForm" novalidate>
                        @csrf
                        <div class="fl" id="flEmail">
                            <span class="ico"><i class="bi bi-at"></i></span>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" placeholder=" " required autofocus autocomplete="username" class="@error('email') invalid @enderror">
                            <label for="email">Work email <span class="field-ko">이메일</span></label>
                            <i class="bi bi-check-circle-fill ok"></i>
                            <span class="bar"></span>
                        </div>
                        <div class="hint text-danger" id="emailHint"><i class="bi bi-info-circle"></i> That doesn't look like an email address</div>

                        <div class="fl">
                            <span class="ico"><i class="bi bi-key-fill"></i></span>
                            <input type="password" name="password" id="password" placeholder=" " required autocomplete="current-password">
                            <label for="password">Password <span class="field-ko">비밀번호</span></label>
                            <button type="button" class="eye" id="togglePw" aria-label="Show password"><i class="bi bi-eye"></i></button>
                            <span class="bar"></span>
                        </div>
                        <div class="hint text-warning-emphasis" id="caps"><i class="bi bi-capslock-fill"></i> Caps Lock is on</div>
                        <div class="hint text-danger" id="pwHint"><i class="bi bi-info-circle"></i> Please type your password</div>

                        <div class="row-opts">
                            <label class="switch"><input type="checkbox" name="remember" id="remember"><span class="track"></span> Remember me <span class="field-ko">· 로그인 유지</span></label>
                            <a href="#" class="small text-decoration-none fw-semibold" id="forgot" style="color:var(--a)">Forgot password?</a>
                        </div>
                        <div class="hint text-secondary" id="forgotHint"><i class="bi bi-person-gear"></i> Your Super Admin can reset it under Admin Accounts.</div>

                        <button class="btn-go" id="signinBtn">
                            <span class="idle">Sign in securely <span class="ko" style="opacity:.85;font-weight:700">· 로그인</span> <i class="bi bi-arrow-right-circle-fill arrow"></i></span>
                            <span class="busy"><span class="d-inline-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span> Opening your workspace…</span></span>
                        </button>
                    </form>

                    <div class="trust">
                        <div><i class="bi bi-stars" style="background:#fee2e2;color:#dc2626"></i><b>Super Admin</b>Everything + settings</div>
                        <div><i class="bi bi-person-workspace"></i><b>Admin</b>Payroll, leave &amp; OT</div>
                        <div><i class="bi bi-shield-check" style="background:#dcfce7;color:#16a34a"></i><b>Secure</b>Every action logged</div>
                    </div>

                    <div class="bottom">
                        <span><i class="bi bi-palette2"></i> Pick your colours</span>
                        <div class="themes" role="group" aria-label="Colour theme">
                            @foreach (['seoul' => 'linear-gradient(135deg,#0a1630 33%,#c8102e 33% 66%,#f2b632 66%)', 'purple-orange' => 'linear-gradient(135deg,#4c1d95 50%,#f97316 50%)', 'purple' => 'linear-gradient(135deg,#3b0764,#a855f7)', 'orange' => 'linear-gradient(135deg,#c2410c,#f59e0b)', 'ocean' => 'linear-gradient(135deg,#0c4a6e,#06b6d4)', 'emerald' => 'linear-gradient(135deg,#064e3b,#34d399)'] as $k => $bg)
                                <button type="button" data-theme-set="{{ $k }}" style="background:{{ $bg }}" title="{{ $k === 'seoul' ? 'Seoul Night (서울의 밤)' : ucwords(str_replace('-', ' & ', $k)) }}" aria-label="{{ $k }} theme"></button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
(function () {
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const $ = id => document.getElementById(id);

    // headline words rise in one after another
    document.querySelectorAll('#headline .w').forEach((w, i) => w.style.animationDelay = (0.2 + i * 0.12) + 's' + (w.classList.contains('grad') ? ', 0s' : ''));

    // ---------- IST clock + greeting (server time, so a wrong PC clock does not matter)
    const clock = $('clock'), skew = Number(clock.dataset.serverMs) - Date.now();
    const fmt = new Intl.DateTimeFormat('en-IN', {timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true, weekday: 'short', day: '2-digit', month: 'short', year: 'numeric'});
    const hourFmt = new Intl.DateTimeFormat('en-IN', {timeZone: 'Asia/Kolkata', hour: 'numeric', hour12: false});
    const tick = () => {
        const now = new Date(Date.now() + skew);
        const p = Object.fromEntries(fmt.formatToParts(now).map(x => [x.type, x.value]));
        clock.querySelector('[data-t]').innerHTML = `${p.hour}<span class="colon">:</span>${p.minute}<span class="colon">:</span>${p.second} ${(p.dayPeriod || '').toUpperCase()}`;
        clock.querySelector('[data-d]').textContent = `${p.weekday}, ${p.day} ${p.month} ${p.year}`;
        const h = Number(hourFmt.format(now));
        $('greet').textContent = h < 5 ? 'Working late' : h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
        $('greetKo').textContent = h < 5 ? '수고 많으십니다' : h < 12 ? '좋은 아침입니다' : h < 17 ? '좋은 오후입니다' : '좋은 저녁입니다';
        setTimeout(tick, 1000 - ((Date.now() + skew) % 1000) + 5);
    };
    tick();

    // ---------- typewriter
    const lines = ['Upload the salary sheet → every payslip calculated.', 'Leave balances and LOP worked out for you.',
        'Pick any year — every page shows that year only.', 'Birthday wishes and work anniversaries on the dashboard.', 'One click: payslips emailed to all employees.'];
    const typer = $('typer');
    if (reduce) { typer.textContent = lines[0]; } else {
        let li = 0, ci = 0, del = false;
        (function type() {
            const l = lines[li];
            typer.textContent = l.slice(0, ci);
            if (!del && ci < l.length) { ci++; setTimeout(type, 36); }
            else if (!del) { del = true; setTimeout(type, 2200); }
            else if (ci > 0) { ci--; setTimeout(type, 12); }
            else { del = false; li = (li + 1) % lines.length; setTimeout(type, 280); }
        })();
    }

    // ---------- monthly workflow: steps light up one after another
    const steps = [...document.querySelectorAll('.step')];
    if (reduce) { steps.forEach(s => s.classList.add('done')); } else {
        let k = 0;
        setInterval(() => {
            steps.forEach((s, i) => { s.classList.toggle('done', i < k); s.classList.toggle('on', i === k); });
            k = (k + 1) % (steps.length + 2);
        }, 1100);
    }

    // ---------- 무궁화 petals
    if (!reduce) {
        const box = $('petals'), count = innerWidth < 700 ? 8 : 16;
        const petal = (c1, c2) => `<svg viewBox="0 0 40 40"><defs><radialGradient id="g${c1.slice(1)}" cx="30%" cy="80%" r="90%"><stop offset="0" stop-color="#b3124b"/><stop offset=".28" stop-color="${c2}"/><stop offset="1" stop-color="${c1}"/></radialGradient></defs><path d="M20 38 C6 30 2 14 12 6 C16 3 19 6 20 9 C21 6 24 3 28 6 C38 14 34 30 20 38Z" fill="url(#g${c1.slice(1)})"/></svg>`;
        const kinds = [['#fde7ef', '#f6a5c0'], ['#ffffff', '#f9c6d6'], ['#fbd3e0', '#ee7fa5']];
        for (let i = 0; i < count; i++) {
            const p = document.createElement('span'), k = kinds[i % kinds.length];
            p.className = 'petal';
            p.innerHTML = petal(k[0], k[1]);
            p.style.left = Math.random() * 100 + 'vw';
            p.style.setProperty('--s', 12 + Math.random() * 14 + 'px');
            p.style.setProperty('--d', 11 + Math.random() * 12 + 's');
            p.style.setProperty('--dl', -Math.random() * 20 + 's');
            p.style.setProperty('--x', (Math.random() * 160 - 40) + 'px');
            p.style.setProperty('--r', (Math.random() > .5 ? 1 : -1) * (360 + Math.random() * 360) + 'deg');
            p.style.setProperty('--sw', 2 + Math.random() * 2.5 + 's');
            box.appendChild(p);
        }
    }

    // ---------- sparks, cursor glow, 3D tilt of the sign-in card
    if (!reduce) {
        const box = $('sparks'), n = innerWidth < 700 ? 12 : 28;
        for (let i = 0; i < n; i++) {
            const s = document.createElement('span');
            s.className = 'spark';
            s.style.left = Math.random() * 100 + 'vw';
            const z = 2 + Math.random() * 3; s.style.width = s.style.height = z + 'px';
            s.style.animationDuration = 9 + Math.random() * 14 + 's';
            s.style.animationDelay = -Math.random() * 20 + 's';
            box.appendChild(s);
        }
        const glow = $('glow'), card = $('card');
        addEventListener('pointermove', e => {
            glow.style.setProperty('--mx', e.clientX + 'px'); glow.style.setProperty('--my', e.clientY + 'px');
            if (innerWidth < 992) return;
            const r = card.getBoundingClientRect();
            const dx = (e.clientX - (r.left + r.width / 2)) / innerWidth, dy = (e.clientY - (r.top + r.height / 2)) / innerHeight;
            card.style.setProperty('--ty', (dx * 8).toFixed(2) + 'deg'); card.style.setProperty('--tx', (-dy * 6).toFixed(2) + 'deg');
        }, {passive: true});
    }

    // ---------- form helpers
    const email = $('email'), pw = $('password'), card = $('card'), btn = $('signinBtn');
    const validEmail = () => email.checkValidity() && email.value.trim() !== '';
    email.addEventListener('input', () => { $('flEmail').classList.toggle('valid', validEmail()); email.classList.remove('invalid'); $('emailHint').classList.remove('show'); });
    $('flEmail').classList.toggle('valid', validEmail());
    pw.addEventListener('input', () => { pw.classList.remove('invalid'); $('pwHint').classList.remove('show'); });
    $('togglePw').addEventListener('click', function () {
        const show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        this.innerHTML = `<i class="bi ${show ? 'bi-eye-slash' : 'bi-eye'}"></i>`;
        this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        pw.focus();
    });
    ['keyup', 'keydown'].forEach(ev => pw.addEventListener(ev, e => $('caps').classList.toggle('show', !!(e.getModifierState && e.getModifierState('CapsLock')))));
    $('forgot').addEventListener('click', e => { e.preventDefault(); $('forgotHint').classList.toggle('show'); });
    if (email.value) pw.focus();

    btn.addEventListener('pointerdown', e => {
        if (reduce) return;
        const r = btn.getBoundingClientRect(), s = Math.max(r.width, r.height), sp = document.createElement('span');
        sp.className = 'ripple'; sp.style.cssText = `width:${s}px;height:${s}px;left:${e.clientX - r.left - s / 2}px;top:${e.clientY - r.top - s / 2}px`;
        btn.appendChild(sp); setTimeout(() => sp.remove(), 650);
    });
    $('loginForm').addEventListener('submit', function (e) {
        const okE = validEmail(), okP = pw.value !== '';
        email.classList.toggle('invalid', !okE); $('emailHint').classList.toggle('show', !okE);
        pw.classList.toggle('invalid', !okP); $('pwHint').classList.toggle('show', !okP);
        if (!okE || !okP) {
            e.preventDefault();
            card.classList.remove('shake'); void card.offsetWidth; card.classList.add('shake');
            (okE ? pw : email).focus();
            return;
        }
        btn.classList.add('loading'); btn.disabled = true;
    });
    addEventListener('pageshow', () => { btn.classList.remove('loading'); btn.disabled = false; });

    // ---------- colour theme (shared with the app)
    const mark = () => document.querySelectorAll('[data-theme-set]').forEach(b => b.classList.toggle('on', b.dataset.themeSet === document.documentElement.getAttribute('data-theme')));
    document.querySelectorAll('[data-theme-set]').forEach(b => b.addEventListener('click', () => {
        document.documentElement.setAttribute('data-theme', b.dataset.themeSet);
        try { localStorage.setItem('app-theme', b.dataset.themeSet); } catch (e) {}
        mark();
    }));
    mark();
})();
</script>
</body>
</html>
