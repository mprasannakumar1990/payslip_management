{{-- Stand-alone animated page loader (used on pages that do not use the main layout, e.g. login) --}}
<style>
    #pageLoader { position: fixed; inset: 0; z-index: 3000; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 22px;
        background: radial-gradient(circle at 30% 20%, #4c1d95 0%, transparent 55%), radial-gradient(circle at 80% 80%, #0e7490 0%, transparent 50%), #1e1b4b;
        transition: opacity .45s ease, visibility .45s ease; animation: loaderFailsafe 0s linear 6s forwards; }
    #pageLoader.hide { opacity: 0; visibility: hidden; }
    @keyframes loaderFailsafe { to { opacity: 0; visibility: hidden; } }
    #pageLoader .loader-mark { position: relative; width: 96px; height: 96px; }
    #pageLoader .loader-ring { position: absolute; inset: 0; border-radius: 50%; padding: 4px; background: conic-gradient(from 0deg, #22d3ee, #a855f7, #ec4899, #f59e0b, #22d3ee);
        -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 5px), #000 calc(100% - 4px)); mask: radial-gradient(farthest-side, transparent calc(100% - 5px), #000 calc(100% - 4px));
        animation: ldSpin 1.1s linear infinite; }
    #pageLoader .r2 { inset: 14px; animation-duration: 1.6s; animation-direction: reverse; opacity: .7; }
    #pageLoader .loader-core { position: absolute; inset: 26px; border-radius: 14px; display: grid; place-items: center; color: #fff; font-size: 1.5rem;
        background: linear-gradient(135deg, #22d3ee, #a855f7); box-shadow: 0 0 30px rgba(168,85,247,.6); animation: ldPulse 1.4s ease-in-out infinite; }
    #pageLoader .loader-title { color: #fff; font-weight: 700; text-align: center; }
    #pageLoader .loader-title small { display: block; font-weight: 400; opacity: .6; font-size: .78rem; margin-top: 2px; }
    #pageLoader .loader-bar { width: 180px; height: 4px; border-radius: 4px; background: rgba(255,255,255,.15); overflow: hidden; }
    #pageLoader .loader-bar span { display: block; height: 100%; width: 40%; border-radius: 4px; background: linear-gradient(90deg, #22d3ee, #ec4899); animation: ldSlide 1.1s ease-in-out infinite; }
    @keyframes ldSpin { to { transform: rotate(360deg); } }
    @keyframes ldPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(.88); } }
    @keyframes ldSlide { 0% { transform: translateX(-110%); } 100% { transform: translateX(260%); } }
    @media (prefers-reduced-motion: reduce) { #pageLoader * { animation: none !important; } }
</style>
<div id="pageLoader" role="status" aria-label="Loading">
    <div class="loader-mark"><div class="loader-ring"></div><div class="loader-ring r2"></div><div class="loader-core"><i class="bi bi-receipt-cutoff"></i></div></div>
    <div class="loader-title">{{ setting('company_name') }}<small>Payslip &amp; HR Suite</small></div>
    <div class="loader-bar"><span></span></div>
</div>
<script>
    (function () {
        const hide = () => document.getElementById('pageLoader')?.classList.add('hide');
        if (document.readyState === 'complete') hide(); else window.addEventListener('load', hide);
        window.addEventListener('pageshow', hide);
        setTimeout(hide, 2500);
    })();
</script>
