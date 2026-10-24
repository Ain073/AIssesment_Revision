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

    <x-page-style path="css/pages/layouts/assessment.css" />

    @stack('styles')
</head>
<body class="@yield('body_class')">
    <nav class="assessment-navbar sticky-top">
        <div class="assessment-navbar-inner">
            <div class="assessment-navbar-title">
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
