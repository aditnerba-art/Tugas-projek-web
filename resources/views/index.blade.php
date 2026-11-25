<!DOCTYPE html>
<html lang="id">

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
    (() => {
        const nativeFetch = window.fetch.bind(window);
        window.fetch = (input, init = {}) => {
            const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            if (token) headers.set('X-CSRF-TOKEN', token);
            return nativeFetch(input, { ...init, headers });
        };
    })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Pelanggan TECKFO Service | Lacak Servis</title>
    @include('partials.realtime-scripts', ['withStyles' => true])
    <link rel="icon" type="image/png" href="{{ asset('teckfo-logo.png') }}">
    <style>
        :root {
            color-scheme: light;
            --portal-ink: var(--color-portal-ink-on-light);
            --portal-muted: var(--color-portal-muted-on-light);
            --portal-line: var(--color-portal-line-on-light);
            --portal-paper: var(--color-portal-surface-light);
            --portal-bg: var(--color-portal-bg);
            --portal-blue: var(--color-portal-accent-dark);
            --portal-blue-dark: #1d4ed8;
        }

        body.bg-slate-900 {
            position: relative;
            isolation: isolate;
            margin: 0;
            background:
                radial-gradient(ellipse at 15% 5%, rgba(37, 99, 235, .22), transparent 42%),
                radial-gradient(ellipse at 95% 55%, rgba(14, 116, 144, .17), transparent 38%),
                var(--portal-bg);
            color: #f4f8ff;
            font-family: var(--font-sans);
        }

        #loginPage, #resultPage {
            position: relative;
            z-index: 1;
        }

        .portal-nav {
            position: relative;
            z-index: 1;
            width: min(1120px, 100% - 48px);
            margin: 0 auto;
            padding: 22px 0 10px;
        }

        .portal-brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            color: #f4f8ff;
            text-decoration: none;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: -.02em;
            text-shadow: 0 1px 12px #020b1eaa;
        }

        .portal-brand-logo {
            display: block;
            width: 76px;
            height: auto;
            flex: 0 0 auto;
            padding: 4px;
            border-radius: 12px;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 8px 24px #020b1e66;
            object-fit: contain;
        }

        .portal-brand small {
            display: block;
            color: #bdd2ed;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .01em;
        }

        .login-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(340px, 430px);
            align-items: center;
            gap: clamp(40px, 8vw, 112px);
            width: min(100%, 1040px);
            margin: auto;
            padding: 48px 24px 76px;
        }

        .login-intro { max-width: 500px; }
        .login-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #9bd2ff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        .login-eyebrow::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #3b82f6;
            box-shadow: 0 0 0 4px #3b82f61c;
        }
        .login-intro h1 {
            max-width: 480px;
            margin: 18px 0 14px;
            color: #f4f8ff;
            font-size: clamp(38px, 5vw, 58px);
            font-weight: 780;
            letter-spacing: -.055em;
            line-height: 1.04;
            text-wrap: balance;
        }
        .login-intro > p {
            max-width: 440px;
            margin: 0;
            color: #c2d4ed;
            font-size: 16px;
            line-height: 1.7;
        }
        .login-benefits {
            display: grid;
            gap: 18px;
            margin: 34px 0 0;
            padding: 0;
            list-style: none;
        }
        .login-benefit {
            display: grid;
            grid-template-columns: 34px 1fr;
            align-items: start;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid #d5e3f7;
            border-radius: 14px;
            background: linear-gradient(135deg, #ffffff, #f2f7ff);
            box-shadow: 0 8px 22px rgba(1, 10, 27, .16), inset 0 1px 0 #ffffff;
            transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
        }
        .login-benefit:hover {
            border-color: #8bb9f2;
            background: linear-gradient(135deg, #ffffff, #eaf3ff);
            box-shadow: 0 12px 28px rgba(1, 10, 27, .22), inset 0 1px 0 #ffffff;
        }
        .login-benefit-number {
            display: grid;
            width: 32px;
            height: 32px;
            place-items: center;
            border: 1px solid #c6d9f5;
            border-radius: 10px;
            background: linear-gradient(145deg, #eff6ff, #dceaff);
            color: #1d4ed8;
            font: 700 11px/1 ui-monospace, monospace;
        }
        .login-benefit strong { display: block; color: #17365f; font-size: 13px; }
        .login-benefit span:last-child { display: block; margin-top: 3px; color: #627895; font-size: 12px; line-height: 1.5; }

        .login-card {
            padding: 30px;
            border: 1px solid var(--portal-line);
            border-radius: 20px;
            background: var(--portal-paper);
            box-shadow: 0 18px 50px #24435812, 0 2px 8px #24435808;
        }
        .login-card h2 { margin: 0; color: var(--portal-ink); font-size: 21px; letter-spacing: -.03em; }
        .login-card .card-description { margin: 7px 0 22px; color: var(--portal-muted); font-size: 13px; line-height: 1.55; }
        .login-card label { display: block; margin-bottom: 8px; color: #344c5a; font-size: 13px; font-weight: 700; }
        .login-card input {
            width: 100%;
            min-height: 48px;
            padding: 0 14px;
            border: 1px solid #cbd8df;
            border-radius: 10px;
            outline: none;
            background: #fbfdfe;
            color: var(--portal-ink);
            font: 500 14px/1.4 ui-monospace, "Cascadia Code", monospace;
            letter-spacing: .02em;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }
        .login-card input::placeholder { color: #91a1aa; font: 400 13px/1.4 var(--font-sans); letter-spacing: 0; }
        .login-card input:focus { border-color: var(--portal-blue); background: white; box-shadow: 0 0 0 4px #2563eb1c; }
        .login-card .field-help { margin: 8px 0 0; color: #748691; font-size: 11px; line-height: 1.5; }
        #loginBtn {
            min-height: 48px;
            margin-top: 4px;
            border: 0;
            border-radius: 10px;
            background: var(--portal-blue);
            color: white;
            font-size: 14px;
            font-weight: 750;
            box-shadow: 0 6px 16px #2563eb26;
            transition: background .18s ease, transform .18s ease, box-shadow .18s ease;
        }
        #loginBtn:hover { background: var(--portal-blue-dark); box-shadow: 0 8px 20px #2563eb36; transform: translateY(-1px); }
        #loginBtn:focus-visible, .portal-nav a:focus-visible, #resultPage button:focus-visible { outline: 3px solid #60a5fa; outline-offset: 3px; }
        #loginBtn:disabled { cursor: wait; opacity: .7; transform: none; }
        #loginError { margin: 0 0 16px; border-radius: 10px; padding: 11px 13px; background: #fff1ef; color: #ad392c; font-size: 13px; }
        .login-support { margin: 19px 0 0; padding-top: 17px; border-top: 1px solid #edf1f3; color: var(--portal-muted); font-size: 12px; line-height: 1.6; }
        .login-support strong { color: #314a59; }

        @keyframes portal-rise {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes portal-card-in {
            from { opacity: 0; transform: translateY(18px) scale(.985); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes portal-page-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes portal-step-in {
            from { opacity: 0; transform: scale(.72); }
            to { opacity: 1; transform: scale(1); }
        }

        #loginPage:not(.hidden) .login-eyebrow,
        #loginPage:not(.hidden) .login-intro h1,
        #loginPage:not(.hidden) .login-intro > p,
        #loginPage:not(.hidden) .login-benefit {
            animation: portal-rise .55s cubic-bezier(.2, .7, .2, 1) both;
        }
        #loginPage:not(.hidden) .login-eyebrow { animation-delay: .04s; }
        #loginPage:not(.hidden) .login-intro h1 { animation-delay: .1s; }
        #loginPage:not(.hidden) .login-intro > p { animation-delay: .16s; }
        #loginPage:not(.hidden) .login-benefit:nth-child(1) { animation-delay: .22s; }
        #loginPage:not(.hidden) .login-benefit:nth-child(2) { animation-delay: .29s; }
        #loginPage:not(.hidden) .login-benefit:nth-child(3) { animation-delay: .36s; }
        #loginPage:not(.hidden) .login-card {
            animation: portal-card-in .65s cubic-bezier(.2, .75, .25, 1) .16s both;
        }
        #loginPage:not(.hidden) #loginError:not(.hidden) { animation: portal-rise .25s ease-out both; }

        #resultPage:not(.hidden) { animation: portal-page-in .35s ease-out both; }
        #resultPage:not(.hidden) .max-w-3xl > div:not(.hidden) {
            animation: portal-rise .55s cubic-bezier(.2, .7, .2, 1) both;
        }
        #resultPage:not(.hidden) .max-w-3xl > div:nth-child(1) { animation-delay: .03s; }
        #resultPage:not(.hidden) .max-w-3xl > div:nth-child(2) { animation-delay: .09s; }
        #resultPage:not(.hidden) .max-w-3xl > div:nth-child(3) { animation-delay: .15s; }
        #resultPage:not(.hidden) .max-w-3xl > div:nth-child(4) { animation-delay: .21s; }
        #resultPage:not(.hidden) .max-w-3xl > div:nth-child(5) { animation-delay: .27s; }
        #resultPage:not(.hidden) .grid.grid-cols-5 > div > div {
            animation: portal-step-in .38s cubic-bezier(.2, .75, .25, 1) both;
        }
        #resultPage:not(.hidden) .grid.grid-cols-5 > div:nth-child(1) > div { animation-delay: .12s; }
        #resultPage:not(.hidden) .grid.grid-cols-5 > div:nth-child(2) > div { animation-delay: .19s; }
        #resultPage:not(.hidden) .grid.grid-cols-5 > div:nth-child(3) > div { animation-delay: .26s; }
        #resultPage:not(.hidden) .grid.grid-cols-5 > div:nth-child(4) > div { animation-delay: .33s; }
        #resultPage:not(.hidden) .grid.grid-cols-5 > div:nth-child(5) > div { animation-delay: .4s; }
        #persetujuanCard:not(.hidden) { animation: portal-card-in .45s ease-out both; }

        #resultPage .portal-feature-card {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            border: 1px solid rgba(101, 164, 244, .34) !important;
            background: linear-gradient(145deg, #102b4d, #0b2340);
            box-shadow: 0 18px 42px rgba(1, 9, 26, .28), inset 0 1px 0 rgba(255, 255, 255, .045);
            backdrop-filter: blur(14px);
            transition: border-color .22s ease, box-shadow .22s ease, background .22s ease;
        }
        #resultPage .portal-feature-card:hover, .portal-feature-card:hover {
            border-color: rgba(117, 190, 255, .68) !important;
            background: linear-gradient(145deg, #102b4d, #0b2340) !important;
            box-shadow: 0 22px 48px rgba(1, 9, 26, .36), inset 0 1px 0 rgba(255, 255, 255, .07);
        }
        #resultPage .portal-feature-card::before {
            position: absolute;
            top: 0;
            right: 26px;
            left: 26px;
            height: 1px;
            background: radial-gradient(circle, rgba(119, 192, 255, .78), transparent);
            content: "";
            pointer-events: none;
        }
        #resultPage .portal-feature-card::after {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
        }
        .portal-card-icon {
            display: inline-flex;
        }
        .timeline-entry {
            position: relative;
        }
        #resultPage .portal-feature-card > * { position: relative; z-index: 1; }
        #resultPage .portal-feature-card h2 { color: #eff6ff !important; }
        #resultPage .portal-feature-card [class~="text-slate-100"] { color: #eff6ff !important; }
        #resultPage .portal-feature-card [class~="text-slate-200"] { color: #d6e6fa !important; }
        #resultPage .portal-feature-card [class~="text-slate-300"] { color: #c1d5ef !important; }
        #resultPage .portal-feature-card [class~="text-slate-400"] { color: #9fb8d8 !important; }
        #resultPage .portal-feature-card [class~="text-slate-500"] { color: #8ca7ca !important; }
        #resultPage .portal-feature-card [class~="bg-slate-900/60"],
        #resultPage .portal-feature-card [class~="bg-slate-900/80"],
        #resultPage .portal-feature-card [class~="bg-slate-900/90"] {
            border-color: rgba(89, 145, 213, .25) !important;
            background: rgba(3, 16, 38, .6) !important;
        }
        #resultPage .portal-info-grid {
            gap: 12px !important;
            border-color: rgba(86, 142, 210, .28) !important;
            background: linear-gradient(135deg, rgba(5, 21, 47, .8), rgba(8, 29, 61, .76)) !important;
        }
        #resultPage .portal-info-grid > div {
            padding: 12px;
            border: 1px solid rgba(89, 145, 213, .22);
            border-radius: 12px;
            background: rgba(15, 40, 76, .7);
        }
        #resultPage .portal-payment-panel {
            border-color: rgba(89, 145, 213, .3) !important;
            background: linear-gradient(135deg, rgba(4, 18, 42, .8), rgba(8, 29, 61, .8)) !important;
        }
        #resultPage .portal-card-icon {
            border: 1px solid rgba(94, 166, 249, .32);
            background: rgba(37, 99, 235, .18) !important;
            color: #9bd2ff !important;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .07);
        }
        #resultPage .portal-feature-card table thead {
            border-color: rgba(90, 150, 222, .32) !important;
            background: rgba(24, 61, 111, .55) !important;
            color: #c5e0ff !important;
        }
        #resultPage .portal-feature-card table tbody tr { transition: background .18s ease; }
        #resultPage .portal-feature-card table tbody tr:hover { background: rgba(57, 117, 194, .12); }
        #resultPage .portal-feature-card table tfoot {
            border-color: rgba(90, 150, 222, .35) !important;
            background: rgba(4, 17, 40, .66) !important;
        }
        #resultPage .grid.grid-cols-5 { position: relative; padding: 8px 0; }
        #resultPage .grid.grid-cols-5::before {
            position: absolute;
            top: 22px;
            right: 9%;
            left: 9%;
            height: 2px;
            background: linear-gradient(90deg, rgba(51, 116, 198, .16), rgba(69, 145, 235, .55), rgba(51, 116, 198, .16));
            content: "";
        }
        #resultPage .grid.grid-cols-5 > div { position: relative; z-index: 1; }
        #resultPage .grid.grid-cols-5 > div > div {
            width: 32px;
            height: 32px;
            border: 2px solid #0b2b53;
            box-shadow: 0 0 0 1px rgba(105, 168, 239, .38), 0 4px 12px rgba(0, 8, 24, .25);
        }
        #resultPage .grid.grid-cols-5 > div > span { max-width: 76px; line-height: 1.35; }
        #resultPage table { border-collapse: separate; border-spacing: 0; }
        #resultPage th, #resultPage td { padding: 12px 10px; }
        #resultPage #persetujuanCard {
            border-color: rgba(245, 158, 11, .55) !important;
            background: linear-gradient(135deg, rgba(32, 22, 10, .96), rgba(13, 26, 48, .98)) !important;
            box-shadow: 0 18px 44px rgba(0, 0, 0, .4), 0 0 24px rgba(245, 158, 11, .12), inset 0 1px 0 rgba(251, 191, 36, .25) !important;
        }
        #resultPage #persetujuanCard::before {
            background: linear-gradient(90deg, transparent, rgba(245, 158, 11, .75), transparent) !important;
        }
        #resultPage #persetujuanCard .portal-card-icon {
            border: 1px solid rgba(245, 158, 11, .38) !important;
            background: rgba(245, 158, 11, .18) !important;
            color: #fcd34d !important;
        }
        #resultPage #persetujuanCard h2 { color: #fef3c7 !important; }
        #resultPage #persetujuanCard [class~="text-slate-200"] { color: #fde68a !important; }
        #resultPage #persetujuanCard [class~="text-slate-400"] { color: #d1d5db !important; }

        @media (max-width: 760px) {
            .portal-nav { width: calc(100% - 36px); padding-top: 16px; }
            .login-layout { grid-template-columns: 1fr; gap: 28px; max-width: 520px; padding: 34px 20px 48px; }
            .login-intro h1 { max-width: 420px; font-size: clamp(35px, 10vw, 46px); }
            .login-intro > p { font-size: 14px; }
            .login-benefits { grid-template-columns: 1fr; gap: 12px; margin-top: 22px; }
            .login-benefit:nth-child(n+3) { display: none; }
            .login-card { padding: 24px; }
            #resultPage { padding: 18px 14px; }
            #resultPage .max-w-3xl > div { padding: 20px; }
            #resultPage .grid.grid-cols-5 { gap: 0; }
            #resultPage .grid.grid-cols-5 > div > span { font-size: 10px; }
        }
        @media (max-width: 420px) {
            .login-layout { padding-inline: 16px; }
            .login-card { padding: 21px; }
            #resultPage .grid.grid-cols-5 > div > div { width: 28px; height: 28px; }
            #resultPage .grid.grid-cols-5::before { top: 20px; }
            #resultPage table { min-width: 0; width: 100%; table-layout: fixed; }
            #resultPage table th:nth-child(3), #resultPage table td:nth-child(3) { display: none; }
            #resultPage th, #resultPage td { padding: 8px 4px; overflow-wrap: anywhere; }
            #resultPage th:first-child, #resultPage td:first-child { width: 62%; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>
</head>

<body class="customer-portal bg-slate-900 text-white min-h-screen flex flex-col font-sans">

    <header class="portal-nav">
        <a class="portal-brand" href="/" aria-label="TECKFO Service - Portal Pelanggan">
            <img class="portal-brand-logo" src="{{ asset('teckfo-logo.png') }}" alt="Logo Teckfo Komputer">
            <span>Servis Komputer<small>Portal Pelanggan</small></span>
        </a>
    </header>

    <!-- ============ HALAMAN LOGIN ============ -->
    <div id="loginPage" class="flex-1 flex items-center justify-center px-4 py-10">
        <div class="login-layout">
            <section class="login-intro" aria-labelledby="loginIntroTitle">
                <span class="login-eyebrow">Informasi servis Anda</span>
                <h1 id="loginIntroTitle">Selalu tahu kabar perangkat Anda.</h1>
                <p>Masuk dengan kode akses untuk melihat progres perbaikan, rincian biaya, dan catatan pengerjaan dalam satu tempat.</p>
                <ul class="login-benefits" aria-label="Informasi yang tersedia di portal">
                    <li class="login-benefit"><span class="login-benefit-number">01</span><span><strong>Pantau progres servis</strong><span>Lihat tahap pengerjaan terbaru secara berurutan.</span></span></li>
                    <li class="login-benefit"><span class="login-benefit-number">02</span><span><strong>Periksa estimasi dan biaya</strong><span>Rincian jasa, sparepart, serta status pembayaran.</span></span></li>
                    <li class="login-benefit"><span class="login-benefit-number">03</span><span><strong>Konfirmasi dari portal</strong><span>Setujui estimasi sebelum teknisi melanjutkan perbaikan.</span></span></li>
                </ul>
            </section>

            <x-ui.card class="login-card" aria-labelledby="loginTitle">
                <h2 id="loginTitle">Masuk ke portal</h2>
                <p class="card-description">Masukkan nama sesuai data servis dan kode akses yang diberikan bengkel.</p>
                <x-ui.alert id="loginContext" tone="info" class="hidden" role="status" aria-live="polite"></x-ui.alert>
                <p id="portalLoadStatus" class="portal-status hidden" role="status" aria-live="polite">Memeriksa sesi tersimpan…</p>
                <x-ui.alert id="loginError" class="hidden" role="alert" aria-live="assertive"></x-ui.alert>
                <form id="loginForm" onsubmit="doLogin(event)" class="space-y-4">
                    <x-ui.input
                        id="loginName"
                        label="Nama pelanggan"
                        help="Ketik nama yang digunakan saat servis didaftarkan."
                        name="customer_name"
                        placeholder="Nama sesuai data servis"
                        autocomplete="name"
                        maxlength="100"
                        required
                        autofocus
                    />
                    <x-ui.input
                        id="loginToken"
                        label="Kode akses pelanggan"
                        help="Masukkan 5 angka yang dikirim bengkel. Kode lama tetap dapat digunakan."
                        name="token"
                        placeholder="Contoh: 01234"
                        autocomplete="one-time-code"
                        inputmode="numeric"
                        pattern="[0-9]{5}"
                        maxlength="5"
                        enterkeyhint="go"
                        required
                    />
                    <x-ui.button type="submit" id="loginBtn" class="w-full">Masuk & cek status servis</x-ui.button>
                </form>
                <p class="login-support"><strong>Belum menerima kode akses?</strong><br>Hubungi bengkel tempat Anda mendaftarkan perangkat.</p>
            </x-ui.card>
        </div>
    </div>

    <!-- ============ HALAMAN HASIL (tersembunyi sampai login) ============ -->
    <div id="resultPage" class="hidden flex-1 py-8 px-4 sm:px-6">
        <div class="max-w-3xl mx-auto space-y-6">

            <!-- Header Selamat Datang -->
            <div class="portal-feature-card portal-welcome-card flex flex-wrap items-center justify-between gap-3 rounded-2xl px-5 py-4 border">
                <div>
                    <p class="text-xs text-slate-400">Selamat datang,</p>
                    <p id="welcomeName" class="font-bold text-white text-base"></p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <p id="realtimeStatus" class="portal-status" role="status" aria-live="polite">Diperbarui otomatis</p>
                    <x-ui.button id="logoutBtn" variant="secondary" onclick="doLogout()">Keluar</x-ui.button>
                </div>
            </div>

            <x-ui.alert id="sessionExpiredNotice" tone="info" class="hidden" role="status" aria-live="polite">
                Sesi portal berakhir. Masuk kembali dengan nama dan kode akses servis untuk melanjutkan.
                <x-ui.button id="btnReturnLogin" variant="secondary" class="mt-3">Masuk kembali</x-ui.button>
            </x-ui.alert>

            <!-- Card Banner Konfirmasi Persetujuan Pelanggan -->
            <div id="persetujuanCard" class="hidden portal-feature-card rounded-2xl p-6 border shadow-2xl space-y-4">
                <div class="flex items-start gap-4">
                    <div class="portal-card-icon w-12 h-12 rounded-xl flex items-center justify-center text-2xl flex-shrink-0 shadow-inner">
                        🔔
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-amber-300 uppercase tracking-wider bg-amber-500/20 px-2.5 py-0.5 rounded-full border border-amber-500/40">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                            Menunggu Persetujuan Anda
                        </span>
                        <h2 class="text-base sm:text-lg font-bold text-white mt-1.5">Konfirmasi Estimasi Biaya Perbaikan</h2>
                        <p class="text-sm text-slate-200 mt-1">
                            Apakah Anda menyetujui estimasi perbaikan sebesar <span id="persetujuanTotalBiaya" class="font-bold text-amber-300 text-base">Rp 0</span> ini?
                        </p>
                        <p class="text-xs text-slate-400 mt-1">
                            Silakan berikan keputusan Anda agar teknisi kami dapat segera melanjutkan proses pengerjaan unit atau membatalkannya.
                        </p>
                    </div>
                </div>

                <div id="approvalCostBreakdown" class="grid gap-2 rounded-xl border border-amber-500/30 bg-slate-950/30 p-4 text-sm"></div>
                <p id="approvalFeedback" class="hidden" role="status" aria-live="polite"></p>
                <div id="approvalActions" class="flex flex-col gap-3 border-t border-amber-500/20 pt-3 sm:flex-row">
                    <x-ui.button type="button" id="btnSetujui" variant="success" class="flex-1" onclick="mintaPersetujuan('dikerjakan')">
                        Setujui perbaikan
                    </x-ui.button>
                    <x-ui.button type="button" id="btnBatal" variant="danger" class="flex-1" onclick="mintaPersetujuan('batal')">
                        Batalkan servis
                    </x-ui.button>
                </div>
                <div id="approvalLoginPrompt" class="hidden border-t border-amber-500/20 pt-4">
                    <p class="mb-3 text-sm text-amber-100">Untuk menyetujui atau membatalkan, masuk dengan nama dan kode akses servis ini.</p>
                    <x-ui.button type="button" id="btnPortalLogin" variant="secondary">Masuk untuk memberi persetujuan</x-ui.button>
                </div>
            </div>

            <!-- 1. Unit & Customer Overview Card -->
            <div class="portal-feature-card rounded-2xl p-6 border shadow-xl space-y-5">
                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 border-b border-slate-700/80 pb-4">
                    <div>
                        <span class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Nomor nota</span>
                        <div id="resNota" class="text-xl font-mono font-extrabold text-blue-400 tracking-tight"></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.badge id="resStatusBadge" tone="neutral" aria-live="polite"></x-ui.badge>
                    </div>
                </div>

                <!-- Progress Stepper Tracker -->
                <div class="py-2">
                    <div id="serviceStepper" class="grid grid-cols-5 gap-2 text-center text-xs" role="list" aria-label="Tahapan servis">
                        <div id="step_masuk" class="flex flex-col items-center" role="listitem">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs mb-1 bg-slate-700 text-slate-400">1</div>
                            <span class="text-[11px] font-medium text-slate-400">Masuk</span>
                        </div>
                        <div id="step_diperiksa" class="flex flex-col items-center" role="listitem">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs mb-1 bg-slate-700 text-slate-400">2</div>
                            <span class="text-[11px] font-medium text-slate-400">Diperiksa</span>
                        </div>
                        <div id="step_dikerjakan" class="flex flex-col items-center" role="listitem">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs mb-1 bg-slate-700 text-slate-400">3</div>
                            <span class="text-[11px] font-medium text-slate-400">Dikerjakan</span>
                        </div>
                        <div id="step_selesai" class="flex flex-col items-center" role="listitem">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs mb-1 bg-slate-700 text-slate-400">4</div>
                            <span class="text-[11px] font-medium text-slate-400">Selesai</span>
                        </div>
                        <div id="step_diambil" class="flex flex-col items-center" role="listitem">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs mb-1 bg-slate-700 text-slate-400">5</div>
                            <span class="text-[11px] font-medium text-slate-400">Diambil</span>
                        </div>
                    </div>
                </div>

                <!-- Data Info Grid -->
                <div class="portal-info-grid grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm p-4 rounded-xl border">
                    <div>
                        <span class="text-xs text-slate-400">Nama Pelanggan</span>
                        <div id="resNama" class="font-semibold text-slate-100 mt-0.5"></div>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400">Perangkat / Unit</span>
                        <div id="resUnit" class="font-semibold text-slate-100 mt-0.5"></div>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400">👨‍🔧 Teknisi Penanggung Jawab (PIC)</span>
                        <div id="resTeknisi" class="font-bold text-blue-400 flex items-center gap-1.5 mt-0.5"></div>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400">Keluhan Kerusakan</span>
                        <div id="resKeluhan" class="font-medium text-rose-300 mt-0.5"></div>
                    </div>
                </div>
            </div>

            <x-ui.card class="portal-feature-card rounded-2xl p-6 border shadow-xl space-y-4">
                <h2 class="text-base font-bold text-slate-100">Estimasi, garansi &amp; kondisi unit</h2>
                <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <p>Estimasi biaya: <strong id="resEstimatedCost">-</strong></p>
                    <p>Estimasi selesai: <strong id="resEstimatedCompletion">-</strong></p>
                    <p>Garansi: <strong id="resWarranty">-</strong></p>
                    <p>Kelengkapan: <strong id="resAccessories">-</strong></p>
                    <p class="sm:col-span-2">Kondisi fisik saat diterima: <strong id="resPhysicalCondition">-</strong></p>
                </div>
                <div>
                    <h3 class="mb-2 text-sm font-bold text-slate-100">Foto kondisi unit</h3>
                    <div id="servicePhotos" class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-live="polite"></div>
                </div>
            </x-ui.card>

            <!-- 2. Rincian Biaya -->
            <div class="portal-feature-card rounded-2xl p-6 border shadow-xl space-y-4">
                <div class="flex items-center gap-2.5 border-b border-slate-700/80 pb-3">
                    <span class="portal-card-icon p-1.5 rounded-lg text-base">💰</span>
                    <div>
                        <h2 class="text-base font-bold text-slate-100">Rincian Detail Biaya Servis</h2>
                        <p class="text-xs text-slate-400">Transparansi tarif pengerjaan jasa dan onderdil yang diganti</p>
                    </div>
                </div>

                <div class="portal-table-wrap" role="region" aria-label="Rincian biaya, geser tabel ke samping bila diperlukan" tabindex="0">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr>
                                <th class="p-2.5">Item Deskripsi</th>
                                <th class="p-2.5 text-center">Jumlah</th>
                                <th class="p-2.5 text-right">Harga Satuan</th>
                                <th class="p-2.5 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="rincianBiayaTable" class="divide-y divide-slate-700/50 text-slate-200"></tbody>
                        <tfoot class="font-bold border-t">
                            <tr>
                                <td colspan="3" class="p-3 text-right text-slate-300">Total Biaya Servis:</td>
                                <td id="resTotalBiaya" class="p-3 text-right text-emerald-400 text-sm font-extrabold">Rp 0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Status Pembayaran -->
                <div class="portal-payment-panel p-4 rounded-xl border space-y-2.5 text-xs">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Status Pembayaran:</span>
                        <x-ui.badge id="resStatusBayarBadge" tone="neutral"></x-ui.badge>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Metode Pembayaran:</span>
                        <span id="resMetodeBayar" class="font-semibold text-slate-200"></span>
                    </div>
                    <div id="row_dp" class="flex justify-between items-center hidden">
                        <span class="text-slate-400">Uang Muka (DP) Dibayar:</span>
                        <span id="resDP" class="font-bold text-blue-400"></span>
                    </div>
                    <div id="row_sisa" class="flex justify-between items-center hidden">
                        <span class="text-slate-400">Sisa Tagihan yang Harus Dibayar:</span>
                        <span id="resSisa" class="font-bold text-rose-400"></span>
                    </div>
                    <div id="row_kembalian" class="flex justify-between items-center hidden">
                        <span class="text-slate-400">Kembalian Diterima:</span>
                        <span id="resKembalian" class="font-bold text-emerald-400"></span>
                    </div>
                </div>
            </div>

            <!-- 3. Timeline Pengerjaan -->
            <div class="portal-feature-card rounded-2xl p-6 border shadow-xl space-y-4">
                <div class="flex items-center gap-2.5 border-b border-slate-700/80 pb-3">
                    <span class="portal-card-icon p-1.5 rounded-lg text-base">⏱️</span>
                    <div>
                        <h2 class="text-base font-bold text-slate-100">Riwayat Timeline Pengerjaan</h2>
                        <p class="text-xs text-slate-400">Catatan kronologis diagnosa dan perbaikan dari teknisi</p>
                    </div>
                </div>
                <ul id="timelineList" class="relative border-l-2 border-blue-500/40 ml-3 space-y-5 pl-5 text-xs" aria-live="polite"></ul>
            </div>
        </div>
    </div>

    <dialog id="approvalDialog" aria-labelledby="approvalDialogTitle" aria-describedby="approvalDialogDescription">
        <h2 id="approvalDialogTitle" class="text-lg font-bold">Konfirmasi keputusan</h2>
        <p id="approvalDialogDescription" class="my-3 text-sm text-slate-200"></p>
        <div class="mt-5 flex flex-col-reverse justify-end gap-3 sm:flex-row">
            <x-ui.button id="approvalDialogCancel" variant="secondary">Kembali</x-ui.button>
            <x-ui.button id="approvalDialogConfirm" variant="success">Ya, lanjutkan</x-ui.button>
        </div>
    </dialog>

    <dialog id="photoDialog" aria-label="Pratinjau foto servis">
        <div class="flex items-center justify-between gap-3">
            <p id="photoDialogCaption" class="font-semibold"></p>
            <x-ui.button id="photoDialogClose" variant="secondary" aria-label="Tutup pratinjau foto">Tutup</x-ui.button>
        </div>
        <div id="photoDialogContent" class="mt-4"></div>
    </dialog>

    <script>
        window.initialTrackingKey = @json($trackingKey ?? null);
        let currentServiceData = null;
        let pendingApprovalAction = null;
        const customerSessionFlag = 'teckfo.customer-session-active';

        function setLoginMessage(message) {
            const error = document.getElementById('loginError');
            error.textContent = message;
            error.classList.toggle('hidden', !message);
        }

        function setLoginContext(message) {
            const context = document.getElementById('loginContext');
            context.textContent = message;
            context.classList.toggle('hidden', !message);
        }

        function setRealtimeStatus(state) {
            const status = document.getElementById('realtimeStatus');
            const expiredNotice = document.getElementById('sessionExpiredNotice');
            if (!expiredNotice.classList.contains('hidden') && state !== 'session-expired') return;
            const labels = {
                realtime: 'Diperbarui otomatis · langsung',
                polling: 'Diperbarui otomatis · tiap 12 detik',
                offline: 'Terputus, mencoba lagi…',
                'session-expired': 'Sesi berakhir',
                manual: 'Masuk dengan kode untuk pembaruan otomatis',
            };
            status.textContent = labels[state] || 'Diperbarui otomatis';
            status.dataset.state = state;
            if (state === 'session-expired') {
                document.getElementById('sessionExpiredNotice').classList.remove('hidden');
            }
        }

        function formatDateWib(value, includeTime = false) {
            if (!value) return '-';
            const rawDate = String(value);
            const date = /^\d{4}-\d{2}-\d{2}$/.test(rawDate)
                ? new Date(`${rawDate}T00:00:00+07:00`)
                : new Date(rawDate);
            return date.toLocaleString('id-ID', {
                timeZone: 'Asia/Jakarta',
                dateStyle: 'medium',
                ...(includeTime ? { timeStyle: 'short' } : {}),
            });
        }

        function showLogin(message = '') {
            if (window.customerRealtimeStop) window.customerRealtimeStop();
            window.customerRealtimeStop = null;
            window.customerRealtimeKey = null;
            document.getElementById('resultPage').classList.add('hidden');
            document.getElementById('loginPage').classList.remove('hidden');
            setLoginMessage(message);
            document.getElementById('loginName').focus({ preventScroll: true });
        }

        // ===================== LOGIN DENGAN KODE AKSES =====================
        async function doLogin(e) {
            e.preventDefault();
            const token = document.getElementById('loginToken').value.trim();
            const customerName = document.getElementById('loginName').value.trim();
            const btn = document.getElementById('loginBtn');
            if (!/^\d{5}$/.test(token)) {
                setLoginMessage('Kode akses harus terdiri dari tepat 5 angka.');
                document.getElementById('loginToken').focus();
                return;
            }
            if (!customerName) {
                setLoginMessage('Masukkan nama sesuai data servis.');
                document.getElementById('loginName').focus();
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Memverifikasi…';
            setLoginMessage('');
            try {
                const loginRes = await fetch('/api/customer/login', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token, customer_name: customerName })
                });
                const loginResult = await loginRes.json();
                if (!loginRes.ok) {
                    if (loginRes.status === 429) {
                        const seconds = Number(loginRes.headers.get('Retry-After'));
                        const wait = seconds > 0
                            ? ` Coba kembali dalam ${Math.ceil(seconds / 60)} menit.`
                            : '';
                        throw new Error(`Terlalu banyak percobaan kode akses.${wait}`);
                    }
                    throw new Error(loginResult.message || 'Nama dan kode akses tidak cocok. Periksa kembali keduanya.');
                }

                const dataRes = await fetch('/api/customer/me', {
                    headers: { 'Accept': 'application/json' }
                });
                const dataResult = await dataRes.json();
                if (!dataRes.ok) throw new Error(dataResult.message || 'Data servis tidak dapat dimuat.');
                if (window.trackingServiceId && Number(dataResult.data.unit.id) !== Number(window.trackingServiceId)) {
                    await fetch('/api/customer/logout', { method: 'POST' });
                    throw new Error('Kode akses tersebut bukan untuk servis pada tautan ini. Periksa kembali kode yang diberikan bengkel.');
                }
                sessionStorage.setItem(customerSessionFlag, '1');
                setLoginContext('');
                document.getElementById('sessionExpiredNotice').classList.add('hidden');
                tampilkanHasil(dataResult.data);
            } catch (err) {
                const message = ['TypeError', 'SyntaxError'].includes(err.name)
                    ? 'Tidak dapat terhubung ke server. Periksa koneksi, lalu coba lagi.'
                    : err.message;
                setLoginMessage(message || 'Tidak dapat memverifikasi kode akses. Coba kembali.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Masuk & Cek Status Servis';
            }
        }

        async function doLogout() {
            if (window.customerRealtimeStop) window.customerRealtimeStop();
            window.customerRealtimeKey = null;
            sessionStorage.removeItem(customerSessionFlag);
            try {
                await fetch('/api/customer/logout', { method: 'POST' });
            } catch (error) {
                console.error('Sesi portal gagal ditutup di server', error);
            }
            document.getElementById('loginForm').reset();
            document.getElementById('sessionExpiredNotice').classList.add('hidden');
            setLoginMessage('');
            showLogin();
        }

        // ===================== TAMPILKAN HASIL =====================
        function tampilkanHasil(data) {
            const { unit, onderdil, timeline, photos } = data;
            currentServiceData = unit;

            document.getElementById('loginPage').classList.add('hidden');
            document.getElementById('resultPage').classList.remove('hidden');
            document.getElementById('sessionExpiredNotice').classList.add('hidden');

            // Sambutan
            document.getElementById('welcomeName').textContent = unit.nama_pelanggan;

            // Overview
            document.getElementById('resNota').innerText = unit.no_nota;
            document.getElementById('resNama').innerText = unit.nama_pelanggan;
            document.getElementById('resUnit').innerText = `${unit.jenis_unit} • ${unit.merek_tipe} ${unit.no_seri ? '(' + unit.no_seri + ')' : ''}`;
            document.getElementById('resKeluhan').innerText = unit.keluhan;
            document.getElementById('resEstimatedCost').textContent = `Rp ${Number(unit.total_biaya || 0).toLocaleString('id-ID')}`;
            document.getElementById('resEstimatedCompletion').textContent = unit.estimated_completion_at
                ? formatDateWib(String(unit.estimated_completion_at).slice(0, 10))
                : 'Belum ditentukan';
            document.getElementById('resWarranty').textContent = unit.warranty_expires_at
                ? `${unit.warranty_active ? 'Aktif' : 'Berakhir'} sampai ${formatDateWib(String(unit.warranty_expires_at).slice(0, 10))}`
                : `${Number(unit.warranty_days || 0)} hari setelah unit diambil`;
            const accessories = [...new Set([...(unit.accessories || []), unit.kelengkapan].filter(Boolean))].join(', ');
            document.getElementById('resAccessories').textContent = accessories || 'Tidak dicatat';
            document.getElementById('resPhysicalCondition').textContent = unit.physical_condition || 'Tidak dicatat';
            const photosWrap = document.getElementById('servicePhotos');
            photosWrap.replaceChildren();
            (photos || []).forEach(photo => {
                const trigger = document.createElement('button');
                trigger.type = 'button';
                trigger.className = 'photo-trigger block w-full overflow-hidden border border-slate-600';
                trigger.setAttribute('aria-label', `Lihat foto ${photo.kind === 'intake' ? 'saat unit masuk' : 'setelah servis'}`);
                const skeleton = document.createElement('span');
                skeleton.className = 'photo-skeleton block';
                skeleton.setAttribute('aria-hidden', 'true');
                const image = document.createElement('img');
                image.src = photo.thumbnail_url;
                image.alt = photo.kind === 'intake' ? 'Kondisi unit saat masuk' : 'Kondisi unit setelah selesai';
                image.loading = 'lazy';
                image.className = 'h-32 w-full object-cover';
                const caption = document.createElement('span');
                caption.className = 'block p-2 text-xs text-slate-200';
                caption.textContent = photo.kind === 'intake' ? 'Saat masuk' : 'Setelah selesai';
                image.addEventListener('load', () => skeleton.remove(), { once: true });
                image.addEventListener('error', () => {
                    image.remove();
                    skeleton.remove();
                    const failed = document.createElement('span');
                    failed.className = 'block p-4 text-sm text-rose-200';
                    failed.textContent = 'Foto tidak dapat dimuat. Coba muat ulang halaman.';
                    trigger.prepend(failed);
                }, { once: true });
                trigger.addEventListener('click', () => bukaFoto(photo.url, caption.textContent));
                trigger.append(skeleton, image, caption);
                photosWrap.append(trigger);
            });
            if (!photosWrap.childElementCount) {
                const empty = document.createElement('p');
                empty.className = 'text-xs text-slate-400';
                empty.textContent = 'Belum ada foto kondisi unit.';
                photosWrap.append(empty);
            }

            // Teknisi PIC
            const teknisiEl = document.getElementById('resTeknisi');
            teknisiEl.replaceChildren();
            if (unit.nama_teknisi) {
                const icon = document.createElement('span');
                icon.textContent = '👤';
                teknisiEl.append(icon, document.createTextNode(` ${unit.nama_teknisi}`));
                teknisiEl.className = 'font-bold text-blue-400 flex items-center gap-1.5 mt-0.5';
            } else {
                teknisiEl.innerText = 'Menunggu penugasan teknisi';
                teknisiEl.className = 'font-semibold text-slate-500 italic mt-0.5';
            }

            // Status Badge & Stepper
            const statusBadge = document.getElementById('resStatusBadge');
            const rawStatus = (unit.status || 'masuk').toString().trim();
            const st = rawStatus.toLowerCase();
            const statusDetails = {
                masuk: ['📥', 'Servis diterima'],
                diperiksa: ['🔎', 'Sedang diperiksa'],
                dikerjakan: ['🔧', 'Sedang dikerjakan'],
                menunggu_persetujuan: ['📝', 'Menunggu persetujuan'],
                'menunggu persetujuan': ['📝', 'Menunggu persetujuan'],
                menunggu_sparepart: ['📦', 'Menunggu sparepart'],
                selesai: ['✅', 'Selesai'],
                diambil: ['📤', 'Unit diambil'],
                batal: ['✖️', 'Servis dibatalkan'],
            };
            const [statusIcon, statusLabel] = statusDetails[st] || ['ℹ️', rawStatus.replace(/_/g, ' ')];
            let badgeClass = 'bg-blue-500/20 text-blue-400 border-blue-500/30';
            if (st === 'selesai') badgeClass = 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
            else if (st === 'diambil') badgeClass = 'bg-purple-500/20 text-purple-400 border-purple-500/30';
            else if (st === 'batal') badgeClass = 'bg-rose-500/20 text-rose-400 border-rose-500/30';
            else if (st === 'menunggu_sparepart' || st === 'menunggu_persetujuan' || st === 'menunggu persetujuan') badgeClass = 'bg-amber-500/20 text-amber-400 border-amber-500/30';
            statusBadge.className = `ui-badge ${badgeClass}`;
            statusBadge.textContent = `${statusIcon} ${statusLabel}`;
            updateStepper(st);

            // Banner Konfirmasi Persetujuan Pelanggan
            const persetujuanCard = document.getElementById('persetujuanCard');
            const totalBiaya = parseFloat(unit.total_biaya || 0);
            const approvalFeedback = document.getElementById('approvalFeedback');
            if (persetujuanCard) {
                if (st === 'menunggu_persetujuan' || st === 'menunggu persetujuan') {
                    persetujuanCard.classList.remove('hidden');
                    document.getElementById('persetujuanTotalBiaya').innerText = `Rp ${totalBiaya.toLocaleString('id-ID')}`;
                    isiRingkasanPersetujuan(onderdil, Number(unit.biaya_jasa || 0), totalBiaya);
                    document.getElementById('approvalActions').classList.toggle('hidden', !unit.status_channel_key);
                    document.getElementById('approvalLoginPrompt').classList.toggle('hidden', Boolean(unit.status_channel_key));
                    approvalFeedback.classList.add('hidden');
                } else {
                    persetujuanCard.classList.add('hidden');
                }
            }

            // Rincian Biaya
            const tbody = document.getElementById('rincianBiayaTable');
            tbody.innerHTML = '';
            const biayaJasa = parseFloat(unit.biaya_jasa || 0);
            const addCostRow = ({ name, detail = '', quantity, unitPrice, subtotal, nameClass, subtotalClass }) => {
                const row = document.createElement('tr');
                const itemCell = document.createElement('td');
                itemCell.className = 'p-2.5';
                const itemName = document.createElement('div');
                itemName.className = nameClass;
                itemName.textContent = name;
                itemCell.append(itemName);
                if (detail) {
                    const itemDetail = document.createElement('div');
                    itemDetail.className = 'text-xs text-slate-400';
                    itemDetail.textContent = detail;
                    itemCell.append(itemDetail);
                }
                const quantityCell = document.createElement('td');
                quantityCell.className = 'p-2.5 text-center font-semibold';
                quantityCell.textContent = String(quantity);
                const priceCell = document.createElement('td');
                priceCell.className = 'p-2.5 text-right';
                priceCell.textContent = `Rp ${Number(unitPrice || 0).toLocaleString('id-ID')}`;
                const subtotalCell = document.createElement('td');
                subtotalCell.className = `p-2.5 text-right font-bold ${subtotalClass}`;
                subtotalCell.textContent = `Rp ${Number(subtotal || 0).toLocaleString('id-ID')}`;
                row.append(itemCell, quantityCell, priceCell, subtotalCell);
                tbody.append(row);
            };

            addCostRow({
                name: '🔧 Biaya Jasa Servis & Analisa',
                detail: 'Pengerjaan teknis, diagnosa, dan perbaikan perangkat',
                quantity: 1,
                unitPrice: biayaJasa,
                subtotal: biayaJasa,
                nameClass: 'font-bold text-slate-100',
                subtotalClass: 'text-blue-300',
            });

            if (onderdil && onderdil.length > 0) {
                onderdil.forEach(item => {
                    addCostRow({
                        name: `📦 ${item.nama_barang || 'Sparepart'}`,
                        detail: item.kode_barang ? `Kode: ${item.kode_barang}` : '',
                        quantity: item.jumlah,
                        unitPrice: item.harga_satuan,
                        subtotal: item.subtotal,
                        nameClass: 'font-semibold text-slate-200',
                        subtotalClass: 'text-emerald-300',
                    });
                });
            } else {
                const emptyRow = document.createElement('tr');
                const emptyCell = document.createElement('td');
                emptyCell.colSpan = 4;
                emptyCell.className = 'p-2 text-center text-xs text-slate-400 italic';
                emptyCell.textContent = 'Tidak ada penggantian onderdil atau sparepart.';
                emptyRow.append(emptyCell);
                tbody.append(emptyRow);
            }

            document.getElementById('resTotalBiaya').innerText = `Rp ${totalBiaya.toLocaleString('id-ID')}`;

            // Status Pembayaran
            const payBadge = document.getElementById('resStatusBayarBadge');
            document.getElementById('resMetodeBayar').innerText = unit.metode_pembayaran || 'Belum Ditentukan';
            const rowDp = document.getElementById('row_dp');
            const rowSisa = document.getElementById('row_sisa');
            const rowKembalian = document.getElementById('row_kembalian');
            rowDp.classList.add('hidden'); rowSisa.classList.add('hidden'); rowKembalian.classList.add('hidden');

            if (unit.status_pembayaran === 'lunas') {
                payBadge.className = 'ui-badge bg-emerald-500/20 text-emerald-300 border border-emerald-500/40';
                payBadge.innerText = '✅ Lunas';
                if (parseFloat(unit.kembalian) > 0) {
                    rowKembalian.classList.remove('hidden');
                    document.getElementById('resKembalian').innerText = `Rp ${parseFloat(unit.kembalian).toLocaleString('id-ID')}`;
                }
            } else if (unit.status_pembayaran === 'sebagian') {
                payBadge.className = 'ui-badge bg-blue-500/20 text-blue-200 border border-blue-500/40';
                payBadge.innerText = '⏳ Dibayar Sebagian';
                rowDp.classList.remove('hidden'); rowSisa.classList.remove('hidden');
                const dp = parseFloat(unit.dp_dibayar || 0);
                const sisa = Math.max(0, totalBiaya - dp);
                document.getElementById('resDP').innerText = `Rp ${dp.toLocaleString('id-ID')}`;
                document.getElementById('resSisa').innerText = `Rp ${sisa.toLocaleString('id-ID')}`;
            } else {
                payBadge.className = 'ui-badge bg-amber-500/20 text-amber-100 border border-amber-500/40';
                payBadge.innerText = '⚠️ Belum Bayar';
                rowSisa.classList.remove('hidden');
                document.getElementById('resSisa').innerText = `Rp ${totalBiaya.toLocaleString('id-ID')}`;
            }

            // Timeline
            const timelineList = document.getElementById('timelineList');
            timelineList.innerHTML = '';
            if (timeline && timeline.length > 0) {
                timeline.forEach(log => {
                    const item = document.createElement('li');
                    item.className = 'relative timeline-entry';
                    const dot = document.createElement('div');
                    dot.className = 'absolute -left-[27px] top-1 w-3 h-3 bg-blue-500 rounded-full border-2 border-slate-900 ring-2 ring-blue-500/30';
                    const heading = document.createElement('div');
                    heading.className = 'flex items-center justify-between gap-3';
                    const status = document.createElement('span');
                    status.className = 'font-bold text-slate-100 uppercase tracking-wide text-xs';
                    const timelineStatus = (log.status_baru || 'UPDATE').toLowerCase();
                    const [timelineIcon, timelineLabel] = statusDetails[timelineStatus] || ['ℹ️', timelineStatus.replaceAll('_', ' ')];
                    status.textContent = `${timelineIcon} ${timelineLabel}`;
                    const timestamp = document.createElement('time');
                    timestamp.className = 'text-xs text-slate-400';
                    timestamp.dateTime = log.created_at || '';
                    timestamp.textContent = formatDateWib(log.created_at, true);
                    heading.append(status, timestamp);

                    const actor = document.createElement('div');
                    actor.className = 'mt-1 text-[10px] text-blue-200';
                    actor.textContent = log.user
                        ? `${log.user}${log.user_role ? ` · ${log.user_role}` : ''}`
                        : 'Pelanggan / sistem';
                    const note = document.createElement('div');
                    note.className = 'mt-1 break-words text-xs text-slate-300 bg-slate-900/60 p-2.5 rounded-lg border border-slate-700/60';
                    note.textContent = log.catatan || log.catatan_teknis || '-';
                    item.append(dot, heading, actor, note);
                    timelineList.appendChild(item);
                });
            } else {
                timelineList.innerHTML = '<li class="text-slate-500 italic">Belum ada riwayat pengerjaan tercatat.</li>';
            }

            if (unit.status_channel_key && window.ServiceRealtime && window.customerRealtimeKey !== unit.status_channel_key) {
                if (window.customerRealtimeStop) window.customerRealtimeStop();
                window.customerRealtimeKey = unit.status_channel_key;
                window.customerRealtimeStop = window.ServiceRealtime.startCustomer(unit.status_channel_key, (update, source) => {
                    if (update?.data?.unit) {
                        tampilkanHasil(update.data);
                    } else if (source === 'reverb') {
                        fetch('/api/customer/me')
                            .then(response => {
                                if (response.status === 401) {
                                    setRealtimeStatus('session-expired');
                                    return null;
                                }
                                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                                return response.json();
                            })
                            .then(result => {
                                if (result?.status === 'success') tampilkanHasil(result.data);
                            })
                            .catch(error => {
                                setRealtimeStatus('offline');
                                console.error('Gagal memperbarui status servis', error);
                            });
                    }
                }, setRealtimeStatus);
            } else if (!unit.status_channel_key) {
                setRealtimeStatus('manual');
            }
        }

        function isiRingkasanPersetujuan(onderdil, biayaJasa, total) {
            const container = document.getElementById('approvalCostBreakdown');
            container.replaceChildren();
            const addRow = (label, amount, emphasis = false) => {
                const row = document.createElement('div');
                row.className = `flex items-start justify-between gap-4 ${emphasis ? 'border-t border-amber-500/30 pt-2 font-bold text-amber-100' : 'text-amber-50'}`;
                const name = document.createElement('span');
                name.textContent = label;
                const price = document.createElement('span');
                price.className = 'shrink-0 text-right';
                price.textContent = `Rp ${Number(amount || 0).toLocaleString('id-ID')}`;
                row.append(name, price);
                container.append(row);
            };

            addRow('Biaya jasa', biayaJasa);
            (onderdil || []).forEach(item => {
                addRow(item.nama_barang || 'Sparepart', item.subtotal);
            });
            addRow('Total estimasi', total, true);
        }

        function bukaFoto(url, caption) {
            const dialog = document.getElementById('photoDialog');
            const content = document.getElementById('photoDialogContent');
            content.replaceChildren();
            document.getElementById('photoDialogCaption').textContent = caption;
            const loading = document.createElement('p');
            loading.className = 'portal-status';
            loading.textContent = 'Memuat foto…';
            const image = document.createElement('img');
            image.src = url;
            image.alt = caption;
            image.className = 'mx-auto max-h-[72dvh] w-auto max-w-full rounded-lg object-contain';
            image.addEventListener('load', () => loading.remove(), { once: true });
            image.addEventListener('error', () => {
                loading.textContent = 'Foto tidak dapat dimuat. Coba muat ulang halaman.';
                loading.classList.add('text-rose-200');
            }, { once: true });
            content.append(loading, image);
            dialog.showModal();
        }

        function updateStepper(status) {
            const s = (status || '').toLowerCase().trim();
            const steps = ['masuk', 'diperiksa', 'dikerjakan', 'selesai', 'diambil'];
            let currentIndex = steps.indexOf(s);
            if (currentIndex === -1) {
                if (s === 'menunggu_persetujuan' || s === 'menunggu persetujuan') {
                    currentIndex = 1; // Tahap diagnosa/diperiksa selesai, menunggu persetujuan pelanggan
                } else if (s === 'menunggu_sparepart') {
                    currentIndex = 2; // Sedang dikerjakan menunggu sparepart
                }
            }
            steps.forEach((st, idx) => {
                const el = document.getElementById(`step_${st}`);
                if (!el) return;
                const circle = el.querySelector('div');
                const label = el.querySelector('span');
                const baseLabel = label.dataset.label || label.textContent.replace(' · saat ini', '');
                label.dataset.label = baseLabel;
                if (idx === currentIndex) {
                    el.setAttribute('aria-current', 'step');
                    label.textContent = `${baseLabel} · saat ini`;
                } else {
                    el.removeAttribute('aria-current');
                    label.textContent = baseLabel;
                }
                if (currentIndex >= idx && s !== 'batal') {
                    circle.className = 'w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs mb-1 bg-blue-600 text-white shadow-md shadow-blue-500/30';
                    label.className = 'text-[11px] font-bold text-blue-400';
                } else {
                    circle.className = 'w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs mb-1 bg-slate-700 text-slate-400';
                    label.className = 'text-[11px] font-medium text-slate-500';
                }
            });
        }

        // ===================== HANDLER PERSETUJUAN / PEMBATALAN =====================
        function mintaPersetujuan(aksi) {
            if (!currentServiceData) {
                tampilkanFeedbackPersetujuan('Data servis tidak ditemukan. Muat ulang halaman dan coba kembali.', 'error');
                return;
            }
            if (!currentServiceData.status_channel_key) {
                document.getElementById('approvalLoginPrompt').classList.remove('hidden');
                return;
            }

            pendingApprovalAction = aksi;
            const isSetuju = aksi === 'dikerjakan';
            document.getElementById('approvalDialogTitle').textContent = isSetuju
                ? 'Setujui estimasi perbaikan?'
                : 'Batalkan servis?';
            document.getElementById('approvalDialogDescription').textContent = isSetuju
                ? `Anda menyetujui estimasi total Rp ${Number(currentServiceData.total_biaya || 0).toLocaleString('id-ID')}. Teknisi akan melanjutkan pengerjaan.`
                : 'Servis akan dibatalkan. Keputusan ini akan dikirim ke bengkel.';
            const confirmButton = document.getElementById('approvalDialogConfirm');
            confirmButton.textContent = isSetuju ? 'Ya, setujui' : 'Ya, batalkan servis';
            confirmButton.className = `ui-button ${isSetuju ? 'ui-button--success' : 'ui-button--danger'}`;
            document.getElementById('approvalDialog').showModal();
        }

        function tampilkanFeedbackPersetujuan(message, tone = 'info') {
            const feedback = document.getElementById('approvalFeedback');
            feedback.textContent = message;
            feedback.className = `ui-alert ui-alert--${tone}`;
            feedback.setAttribute('role', tone === 'error' ? 'alert' : 'status');
        }

        async function kirimPersetujuan(aksi) {
            if (!currentServiceData || !currentServiceData.status_channel_key) {
                tampilkanFeedbackPersetujuan('Masuk dengan kode akses servis sebelum mengirim keputusan.', 'error');
                return;
            }

            const isSetuju = aksi === 'dikerjakan';
            const statusTarget = isSetuju ? 'DIKERJAKAN' : 'BATAL';
            const buttons = [
                document.getElementById('btnSetujui'),
                document.getElementById('btnBatal'),
                document.getElementById('approvalDialogConfirm'),
            ];
            buttons.forEach(button => { button.disabled = true; });
            document.getElementById('approvalDialog').close();
            tampilkanFeedbackPersetujuan('Mengirim keputusan Anda…');

            try {
                const response = await fetch('/api/customer/approval', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        status_baru: statusTarget,
                        service_id: currentServiceData.id,
                        no_nota: currentServiceData.no_nota,
                    }),
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'success') {
                    if (response.status === 401) {
                        setRealtimeStatus('session-expired');
                        tampilkanFeedbackPersetujuan('Sesi portal berakhir. Masuk kembali untuk mengirim persetujuan.', 'error');
                    } else {
                        tampilkanFeedbackPersetujuan(result.message || 'Keputusan tidak dapat disimpan. Coba kembali.', 'error');
                    }
                    return;
                }

                tampilkanFeedbackPersetujuan(isSetuju
                    ? 'Terima kasih, persetujuan Anda sudah diterima bengkel.'
                    : 'Permintaan pembatalan servis sudah dikirim ke bengkel.');
                const reload = await fetch('/api/customer/me');
                if (reload.status === 401) {
                    setRealtimeStatus('session-expired');
                    return;
                }
                if (!reload.ok) {
                    tampilkanFeedbackPersetujuan('Keputusan tersimpan, tetapi status terbaru belum dapat dimuat. Muat ulang halaman untuk memperbarui.', 'info');
                    return;
                }
                const refreshed = await reload.json();
                if (refreshed.status === 'success') tampilkanHasil(refreshed.data);
            } catch (error) {
                console.error('Gagal mengirim keputusan persetujuan', error);
                tampilkanFeedbackPersetujuan('Tidak dapat terhubung ke server. Periksa koneksi, lalu coba kembali.', 'error');
            } finally {
                buttons.forEach(button => { button.disabled = false; });
            }
        }

        document.getElementById('btnPortalLogin').addEventListener('click', () => {
            const note = currentServiceData
                ? `Masuk untuk servis ${currentServiceData.no_nota}. Gunakan nama dan kode akses yang diterima untuk servis ini.`
                : 'Masukkan nama dan kode akses servis untuk melanjutkan.';
            setLoginContext(note);
            showLogin();
        });
        document.getElementById('btnReturnLogin').addEventListener('click', () => {
            sessionStorage.removeItem(customerSessionFlag);
            showLogin('Sesi portal berakhir. Masuk kembali dengan nama dan kode akses servis.');
        });

        document.getElementById('approvalDialogCancel').addEventListener('click', () => {
            pendingApprovalAction = null;
            document.getElementById('approvalDialog').close();
        });
        document.getElementById('approvalDialogConfirm').addEventListener('click', () => {
            const action = pendingApprovalAction;
            pendingApprovalAction = null;
            if (action) kirimPersetujuan(action);
        });
        document.getElementById('photoDialogClose').addEventListener('click', () => {
            document.getElementById('photoDialog').close();
        });
        document.getElementById('photoDialog').addEventListener('click', event => {
            if (event.target === event.currentTarget) event.currentTarget.close();
        });
        const loginNameInput = document.getElementById('loginName');
        const loginTokenInput = document.getElementById('loginToken');
        loginNameInput.addEventListener('invalid', () => {
            loginNameInput.setCustomValidity('Masukkan nama sesuai data servis.');
            setLoginMessage('Masukkan nama sesuai data servis.');
        });
        loginNameInput.addEventListener('input', () => {
            loginNameInput.setCustomValidity('');
            setLoginMessage('');
        });
        loginTokenInput.addEventListener('invalid', () => {
            loginTokenInput.setCustomValidity('Kode akses harus terdiri dari tepat 5 angka.');
            setLoginMessage('Kode akses harus terdiri dari tepat 5 angka.');
        });
        loginTokenInput.addEventListener('input', () => {
            loginTokenInput.setCustomValidity('');
            setLoginMessage('');
        });

        // Pulihkan sesi dari cookie HttpOnly atau muat tautan tracking acak.
        window.addEventListener('load', async () => {
            const loading = document.getElementById('portalLoadStatus');
            loading.classList.remove('hidden');
            let loadError = '';
            try {
                if (window.initialTrackingKey) {
                    try {
                        const response = await fetch(`/api/tracking-link/${encodeURIComponent(window.initialTrackingKey)}`);
                        const tracked = await response.json();
                        if (response.ok && tracked.status === 'success') {
                            window.trackingServiceId = tracked.data.unit.id;
                            tampilkanHasil(tracked.data);
                            return;
                        }
                        loadError = response.status === 404
                            ? 'Tautan pelacakan tidak ditemukan. Periksa kembali tautan dari bengkel.'
                            : 'Tautan pelacakan tidak dapat dimuat. Periksa koneksi internet, lalu coba lagi.';
                    } catch (error) {
                        console.error('Gagal memuat tautan pelacakan', error);
                        loadError = 'Tautan pelacakan tidak dapat dimuat. Periksa koneksi internet, lalu coba lagi.';
                    }
                }

                try {
                    const response = await fetch('/api/customer/me');
                    if (response.ok) {
                        const result = await response.json();
                        if (result.status === 'success') {
                            sessionStorage.setItem(customerSessionFlag, '1');
                            tampilkanHasil(result.data);
                            return;
                        }
                    } else if (response.status === 401 && sessionStorage.getItem(customerSessionFlag)) {
                        loadError = 'Sesi portal telah berakhir. Masuk kembali dengan nama dan kode akses servis.';
                        sessionStorage.removeItem(customerSessionFlag);
                    }
                } catch (error) {
                    console.error('Gagal memulihkan sesi portal pelanggan', error);
                    loadError = 'Tidak dapat menghubungi server. Periksa koneksi internet, lalu coba lagi.';
                }

                const storedNota = sessionStorage.getItem('pelanggan_nota');
                if (storedNota) {
                    try {
                        const response = await fetch(`/api/tracking/${encodeURIComponent(storedNota)}`);
                        if (response.ok) {
                            const result = await response.json();
                            if (result.status === 'success') {
                                tampilkanHasil(result.data);
                                return;
                            }
                        }
                    } catch (error) {
                        console.error('Gagal memulihkan pelacakan servis tersimpan', error);
                    }
                }

                setLoginMessage(loadError);
            } finally {
                loading.classList.add('hidden');
            }
        });
    </script>
</body>

</html>