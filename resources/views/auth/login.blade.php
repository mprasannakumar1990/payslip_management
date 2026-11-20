<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ setting('company_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --ink: #0f172a; }
        * { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        html, body { height: 100%; }
        body { margin: 0; color: var(--ink); background: #0b1023; overflow-x: hidden; }

        /* ---------- animated background ---------- */
        .bg { position: fixed; inset: 0; z-index: 0; overflow: hidden;
            background: linear-gradient(125deg, #1e1b4b, #312e81, #4c1d95, #0e7490, #1e1b4b); background-size: 400% 400%; animation: hue 22s ease infinite; }
        @keyframes hue { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .blob { position: absolute; border-radius: 50%; filter: blur(60px); opacity: .55; mix-blend-mode: screen; animation: drift 18s ease-in-out infinite alternate; }
        .b1 { width: 460px; height: 460px; background: #ec4899; top: -120px; left: -80px; }
        .b2 { width: 520px; height: 520px; background: #06b6d4; bottom: -160px; left: 25%; animation-duration: 24s; }
        .b3 { width: 380px; height: 380px; background: #a855f7; top: 20%; right: -100px; animation-duration: 20s; }
        .b4 { width: 300px; height: 300px; background: #f59e0b; bottom: 10%; right: 30%; opacity: .35; animation-duration: 28s; }
        @keyframes drift { 0% { transform: translate(0, 0) scale(1); } 50% { transform: translate(80px, 40px) scale(1.12); } 100% { transform: translate(-60px, 90px) scale(.92); } }
        .grid { position: absolute; inset: 0; background-image: linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
            background-size: 48px 48px; mask-image: radial-gradient(circle at 30% 40%, #000 0%, transparent 70%); }
        .particle { position: absolute; bottom: -20px; width: 6px; height: 6px; border-radius: 50%; background: rgba(255,255,255,.6); animation: rise linear infinite; }
        @keyframes rise { from { transform: translateY(0); opacity: 0; } 10% { opacity: .8; } to { transform: translateY(-110vh); opacity: 0; } }

        /* ---------- layout ---------- */
        .wrap { position: relative; z-index: 1; min-height: 100vh; display: grid; grid-template-columns: 1.15fr .85fr; }
        .hero { color: #fff; padding: 56px 64px; display: flex; flex-direction: column; justify-content: space-between; }
        .logo { display: flex; align-items: center; gap: 12px; font-weight: 700; }
        .logo-mark { width: 48px; height: 48px; border-radius: 14px; display: grid; place-items: center; font-size: 1.5rem;
            background: linear-gradient(135deg, #22d3ee, #a855f7); box-shadow: 0 10px 30px rgba(168,85,247,.5); }
        .hero h1 { font-size: clamp(2rem, 3.4vw, 3.1rem); font-weight: 800; line-height: 1.12; letter-spacing: -.02em; margin: 0 0 16px; }
        .hero h1 span { background: linear-gradient(90deg, #67e8f9, #f0abfc, #fde68a); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .hero p.lead { color: rgba(255,255,255,.78); max-width: 520px; font-size: 1.05rem; }
        .features { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; max-width: 560px; margin-top: 28px; }
        .feature { display: flex; gap: 10px; align-items: center; padding: 12px 14px; border-radius: 14px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); backdrop-filter: blur(8px); font-size: .9rem; }
        .feature i { font-size: 1.15rem; width: 34px; height: 34px; display: grid; place-items: center; border-radius: 10px; background: rgba(255,255,255,.14); }

        /* floating payslip cards */
        .stage { position: relative; height: 210px; margin-top: 30px; max-width: 560px; }
        .slip { position: absolute; width: 230px; padding: 14px 16px; border-radius: 16px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.22);
            backdrop-filter: blur(12px); color: #fff; box-shadow: 0 20px 40px rgba(0,0,0,.25); animation: float 7s ease-in-out infinite; }
        .slip .bar { height: 7px; border-radius: 6px; background: rgba(255,255,255,.35); margin: 7px 0; }
        .slip .amt { font-weight: 800; font-size: 1.3rem; }
        .s1 { left: 0; top: 10px; transform: rotate(-6deg); }
        .s2 { left: 190px; top: 50px; animation-delay: -2.5s; background: rgba(34,211,238,.18); }
        .s3 { left: 360px; top: 0; animation-delay: -4.5s; width: 190px; background: rgba(236,72,153,.18); }
        @keyframes float { 0%, 100% { translate: 0 0; } 50% { translate: 0 -16px; } }

        /* ---------- form panel ---------- */
        .panel { display: flex; align-items: center; justify-content: center; padding: 32px; }
        .card-login { width: 100%; max-width: 430px; background: rgba(255,255,255,.96); border-radius: 24px; padding: 40px 36px; box-shadow: 0 30px 80px rgba(2,6,23,.45);
            animation: pop .6s cubic-bezier(.2,.8,.2,1) both; }
        @keyframes pop { from { opacity: 0; transform: translateY(18px) scale(.98); } to { opacity: 1; transform: none; } }
        .card-login h2 { font-weight: 800; font-size: 1.6rem; letter-spacing: -.01em; }
        .form-label { font-weight: 600; font-size: .82rem; color: #334155; }
        .field { position: relative; }
        .field > i.lead-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .field .form-control { height: 50px; padding-left: 42px; border-radius: 12px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-size: .95rem; }
        .field .form-control:focus { border-color: #818cf8; background: #fff; box-shadow: 0 0 0 4px rgba(99,102,241,.15); }
        .toggle-pw { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: #64748b; padding: 6px 10px; }
        .btn-signin { height: 50px; border: 0; border-radius: 12px; font-weight: 700; letter-spacing: .01em; color: #fff;
            background: linear-gradient(135deg, #6366f1, #8b5cf6 50%, #ec4899); background-size: 200% 200%; transition: background-position .4s, transform .15s, box-shadow .2s;
            box-shadow: 0 10px 25px rgba(99,102,241,.35); }
        .btn-signin:hover { background-position: 100% 0; transform: translateY(-1px); color: #fff; box-shadow: 0 14px 30px rgba(139,92,246,.45); }
        .roles { display: flex; gap: 8px; }
        .role { flex: 1; border-radius: 12px; padding: 10px; font-size: .78rem; background: #f1f5f9; display: flex; align-items: center; gap: 8px; color: #334155; }
        .role i { font-size: 1.1rem; }
        .caps { display: none; font-size: .78rem; color: #b45309; }
        .divider { height: 1px; background: linear-gradient(90deg, transparent, #e2e8f0, transparent); margin: 22px 0; }

        .hero, .panel { min-width: 0; }
        @media (max-width: 991.98px) {
            .wrap { grid-template-columns: minmax(0, 1fr); }
            .logo > div:last-child { min-width: 0; }
            .logo .small { white-space: normal; }
            .hero { padding: 28px 24px 0; }
            .hero .hide-sm, .stage, .features { display: none; }
            .hero h1 { font-size: 1.7rem; margin-top: 18px; }
            .panel { padding: 20px 16px 40px; align-items: flex-start; }
            .card-login { padding: 30px 22px; }
        }
        @media (prefers-reduced-motion: reduce) { *, .bg, .blob, .slip, .particle { animation: none !important; } }
    </style>
</head>
<body>
@include('layouts._loader')
<div class="bg" aria-hidden="true">
    <div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div><div class="blob b4"></div>
    <div class="grid"></div>
    <div id="particles"></div>
</div>

<div class="wrap">
    <section class="hero">
        <div class="logo">
            <div class="logo-mark"><i class="bi bi-receipt-cutoff"></i></div>
            <div><div>{{ setting('company_name') }}</div><div class="small fw-normal opacity-75">{{ setting('company_address') }}</div></div>
        </div>
        <div>
            <h1>Payroll, leave &amp; overtime —<br><span>all in one place.</span></h1>
            <p class="lead hide-sm">Generate monthly payslips between the 1st and the {{ setting('payroll_deadline_day', 10) }}th, track leave and OT plant-wise, and email every employee their payslip with one click.</p>
            <div class="features">
                <div class="feature"><i class="bi bi-cash-stack"></i> Payslips &amp; salary register</div>
                <div class="feature"><i class="bi bi-calendar2-check"></i> Leave, EL &amp; LOP tracking</div>
                <div class="feature"><i class="bi bi-clock-history"></i> Plant-wise OT management</div>
                <div class="feature"><i class="bi bi-envelope-paper"></i> Payslips by email</div>
            </div>
            <div class="stage" aria-hidden="true">
                <div class="slip s1"><div class="small opacity-75">Net pay · {{ now()->subMonthNoOverflow()->format('M Y') }}</div><div class="amt">₹ 30,772</div><div class="bar" style="width:80%"></div><div class="bar" style="width:55%"></div></div>
                <div class="slip s2"><div class="small opacity-75"><i class="bi bi-clock"></i> OT hours</div><div class="amt">1,854 h</div><div class="bar" style="width:65%"></div><div class="bar" style="width:40%"></div></div>
                <div class="slip s3"><div class="small opacity-75"><i class="bi bi-envelope-check"></i> Emailed</div><div class="amt">36 / 36</div><div class="bar" style="width:100%"></div></div>
            </div>
        </div>
        <div class="small opacity-50 hide-sm">© {{ now()->year }} {{ setting('company_name') }} · Payslip &amp; HR Suite</div>
    </section>

    <section class="panel">
        <div class="card-login">
            <div class="mb-4">
                <span class="badge rounded-pill text-bg-light border mb-3"><i class="bi bi-shield-lock text-primary"></i> Secure sign-in</span>
                <h2 class="mb-1">Welcome back</h2>
                <div class="text-muted">Sign in to manage payroll, leave and OT.</div>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger d-flex gap-2 align-items-center py-2 small rounded-3"><i class="bi bi-exclamation-octagon"></i> {{ $errors->first() }}</div>
            @endif
            @if (session('status'))
                <div class="alert alert-success py-2 small rounded-3">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" id="loginForm" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <div class="field">
                        <i class="bi bi-envelope lead-icon"></i>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="you@company.com" required autofocus autocomplete="username">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="password">Password</label>
                    <div class="field">
                        <i class="bi bi-lock lead-icon"></i>
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                        <button type="button" class="toggle-pw" id="togglePw" aria-label="Show password"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="caps mt-1" id="caps"><i class="bi bi-capslock"></i> Caps Lock is on</div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label small" for="remember">Keep me signed in</label>
                    </div>
                    <span class="small text-muted" title="Ask your Super Admin to reset your password">Forgot password?</span>
                </div>
                <button class="btn btn-signin w-100" id="signinBtn">
                    <span class="label">Sign in <i class="bi bi-arrow-right ms-1"></i></span>
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                </button>
            </form>

            <div class="divider"></div>
            <div class="roles">
                <div class="role"><i class="bi bi-shield-fill-check text-danger"></i><div><strong>Super Admin</strong><br>Full control</div></div>
                <div class="role"><i class="bi bi-person-badge-fill text-primary"></i><div><strong>Admin</strong><br>Payroll, leave &amp; OT</div></div>
            </div>
        </div>
    </section>
</div>

<script>
    // floating particles
    (function () {
        const box = document.getElementById('particles');
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        for (let i = 0; i < 28; i++) {
            const p = document.createElement('span');
            p.className = 'particle';
            const size = 2 + Math.random() * 5;
            p.style.left = Math.random() * 100 + 'vw';
            p.style.width = p.style.height = size + 'px';
            p.style.animationDuration = 10 + Math.random() * 16 + 's';
            p.style.animationDelay = -Math.random() * 20 + 's';
            box.appendChild(p);
        }
    })();
    // show / hide password
    document.getElementById('togglePw').addEventListener('click', function () {
        const pw = document.getElementById('password');
        const show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        this.innerHTML = `<i class="bi ${show ? 'bi-eye-slash' : 'bi-eye'}"></i>`;
        this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
    // caps lock warning
    document.getElementById('password').addEventListener('keyup', e => {
        document.getElementById('caps').style.display = e.getModifierState && e.getModifierState('CapsLock') ? 'block' : 'none';
    });
    // simple validation + loading state
    document.getElementById('loginForm').addEventListener('submit', function (e) {
        const email = document.getElementById('email'), pw = document.getElementById('password');
        email.classList.toggle('is-invalid', !email.checkValidity());
        pw.classList.toggle('is-invalid', !pw.value);
        if (!email.checkValidity() || !pw.value) { e.preventDefault(); return; }
        const b = document.getElementById('signinBtn');
        b.disabled = true; b.querySelector('.label').classList.add('d-none'); b.querySelector('.spinner-border').classList.remove('d-none');
    });
</script>
</body>
</html>
