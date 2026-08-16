<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link crossorigin href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <style>
        :root {
            --psu-navy: #001a70;
            --psu-navy-2: #0927d8;
            --psu-gold: #ffda27;
            --psu-gold-soft: #fff5bf;
            --psu-bg: #f7f9ff;
            --psu-line: #d6ddf5;
            --psu-muted: #5f6780;
            --psu-text: #1a1f2c;
        }

        body {
            min-height: 100vh;
            background: var(--psu-bg);
            color: var(--psu-text);
            font-family: "Inter", sans-serif;
            padding-bottom: 6rem;
        }

        h1, h2, h3, .brand-text {
            font-family: "Oswald", sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24;
        }

        .assessment-navbar {
            min-height: 64px;
            background:
                linear-gradient(118deg, rgba(5, 33, 171, 0.98) 0%, rgba(13, 49, 221, 0.96) 54%, rgba(226, 196, 48, 0.9) 145%),
                radial-gradient(circle at 100% 0%, rgba(255, 226, 76, 0.46) 0%, rgba(255, 226, 76, 0) 36%),
                linear-gradient(180deg, #021063 0%, #0828c9 58%, #d4b736 100%);
            color: #fff;
            border-bottom: 1px solid rgba(255, 218, 39, 0.32);
            box-shadow: 0 10px 24px rgba(0, 26, 112, 0.14);
        }

        .assessment-divider {
            width: 1px;
            height: 24px;
            background: rgba(255, 255, 255, 0.28);
        }

        .assessment-container {
            max-width: 1180px;
            padding: 1.5rem;
        }

        .btn-psu {
            --bs-btn-bg: var(--psu-navy-2);
            --bs-btn-border-color: var(--psu-navy-2);
            --bs-btn-color: #fff;
            --bs-btn-hover-bg: var(--psu-navy);
            --bs-btn-hover-border-color: var(--psu-navy);
            --bs-btn-hover-color: #fff;
            font-weight: 700;
        }

        @media (max-width: 575.98px) {
            body {
                padding-bottom: 7rem;
            }

            .assessment-container {
                width: 100%;
                padding: 1rem 0.75rem;
            }

            .assessment-navbar .container-fluid {
                padding-inline: 0.85rem !important;
                gap: 0.75rem !important;
                min-height: 58px;
            }

            .assessment-navbar .brand-text {
                font-size: 0.98rem;
            }

            .assessment-navbar-title {
                min-width: 0;
                gap: 0.5rem !important;
            }

            .assessment-attempt-label {
                max-width: 42vw;
                overflow: hidden;
                font-size: 0.68rem;
                letter-spacing: 0;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .assessment-navbar .btn {
                padding: 0.35rem 0.55rem;
                font-size: 0.78rem;
            }
        }
    </style>

    @stack('styles')
</head>
<body>
    <nav class="assessment-navbar sticky-top">
        <div class="container-fluid px-4 h-100 d-flex align-items-center justify-content-between gap-3">
            <div class="assessment-navbar-title d-flex align-items-center gap-3">
                <span class="brand-text h5 fw-bold mb-0">Assessment</span>
                <span class="assessment-divider d-none d-sm-block"></span>
                <span class="assessment-attempt-label small fw-bold text-white-50 text-uppercase">@yield('attempt-label', 'Quiz Attempt')</span>
            </div>
            @yield('navbar-actions')
        </div>
    </nav>

    <main class="assessment-container mx-auto">
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
