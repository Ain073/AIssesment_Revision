<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Set Password | AIssessment</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">

    <x-page-style path="css/pages/auth/password-setup.css" />
</head>
<body>
    <header class="topbar fixed-top d-flex align-items-center px-3 px-md-5">
        <span class="brand-title h4 mb-0 fw-bold text-white">AISSESSMENT</span>
    </header>

    <main class="setup-shell">
        <img class="setup-bg" src="{{ asset('images/psu-campus-background.jpg') }}" alt="PSU Building">
        <div class="setup-overlay"></div>

        <section class="setup-card p-4">
            <div class="text-center mb-4">
                <img class="setup-logo mb-3" src="{{ asset('images/psu-logo-transparent.png') }}" alt="PSU Seal">
                <h1 class="setup-title h3 mb-2 fw-bold text-uppercase" style="color: var(--psu-navy);">Set New Password</h1>
                <p class="text-secondary mb-0">{{ $user->email }}</p>
            </div>

            <form action="{{ route('password.setup.update') }}" method="POST">
                @csrf
                @method('PUT')

                @if ($errors->any())
                    <div class="alert alert-danger py-2">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label fw-bold" for="current_password">Current Password</label>
                    <div class="position-relative">
                        <span class="material-symbols-outlined form-icon">lock</span>
                        <input autocomplete="current-password" autofocus class="form-control form-control-lg with-icon" id="current_password" name="current_password" required type="password">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold" for="password">New Password</label>
                    <div class="position-relative">
                        <span class="material-symbols-outlined form-icon">lock_reset</span>
                        <input autocomplete="new-password" class="form-control form-control-lg with-icon" id="password" name="password" required type="password">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold" for="password_confirmation">Confirm New Password</label>
                    <div class="position-relative">
                        <span class="material-symbols-outlined form-icon">verified_user</span>
                        <input autocomplete="new-password" class="form-control form-control-lg with-icon" id="password_confirmation" name="password_confirmation" required type="password">
                    </div>
                </div>

                <button class="btn btn-setup btn-lg w-100 d-flex align-items-center justify-content-center gap-2 py-3" type="submit">
                    Save Password
                    <span class="material-symbols-outlined">check_circle</span>
                </button>
            </form>
        </section>
    </main>

    <footer class="login-footer d-flex align-items-center justify-content-center px-3 py-2 fixed-bottom">
        <div class="d-flex flex-wrap align-items-center justify-content-center gap-3 text-center">
            <span class="footer-brand h4 mb-0" style="color: var(--psu-navy);">PSU</span>
            <p class="mb-0 small">&copy; 2024 Pangasinan State University. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
