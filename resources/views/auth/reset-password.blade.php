<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset Password | AIssessment</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">

    <x-page-style path="css/pages/auth/recovery.css" />
    <x-page-style path="css/pages/auth/reset-password.css" />
</head>
<body>
    <header class="topbar fixed-top d-flex align-items-center justify-content-between px-3 px-md-5">
        <span class="brand-title h4 mb-0 fw-bold text-white d-none d-lg-inline">AISSESSMENT</span>
        <a class="fw-bold text-white text-decoration-none" href="#">Help Desk</a>
    </header>

    <main class="auth-shell">
        <img class="auth-bg" src="{{ asset('images/psu-campus-background.jpg') }}" alt="PSU Building">
        <div class="auth-overlay"></div>

        <section class="auth-card p-4">
            <div class="text-center mb-4">
                <img class="auth-logo mb-3" src="{{ asset('images/psu-logo-transparent.png') }}" alt="PSU Seal">
                <h1 class="auth-title h3 mb-2 fw-bold text-uppercase" style="color: var(--psu-navy);">
                    Create New Password
                </h1>
                <p class="text-secondary mb-0">Use at least 8 characters for your new password.</p>
            </div>

            <form action="{{ route('password.update') }}" id="resetPasswordForm" method="POST">
                @csrf
                <input name="token" type="hidden" value="{{ $token }}">

                @if ($errors->any())
                    <div class="alert alert-danger py-2">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label fw-bold" for="email">Email Address</label>
                    <div class="position-relative">
                        <span class="material-symbols-outlined form-icon">mail</span>
                        <input autocomplete="email" class="form-control form-control-lg with-icon" id="email" name="email" placeholder="Enter your email address" required type="email" value="{{ old('email', $email) }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold" for="password">New Password</label>
                    <div class="position-relative">
                        <span class="material-symbols-outlined form-icon">lock</span>
                        <input autocomplete="new-password" class="form-control form-control-lg with-icon pe-5" id="password" name="password" placeholder="New password" required type="password">
                        <button aria-label="Show password" class="password-toggle" data-toggle-password="password" type="button">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold" for="password_confirmation">Confirm Password</label>
                    <div class="position-relative">
                        <span class="material-symbols-outlined form-icon">lock_reset</span>
                        <input autocomplete="new-password" class="form-control form-control-lg with-icon pe-5" id="password_confirmation" name="password_confirmation" placeholder="Confirm password" required type="password">
                        <button aria-label="Show password confirmation" class="password-toggle" data-toggle-password="password_confirmation" type="button">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                </div>

                <button class="btn btn-auth btn-lg w-100 d-flex align-items-center justify-content-center gap-2 py-3" type="submit">
                    Reset Password
                    <span class="material-symbols-outlined">lock_reset</span>
                </button>

                <div class="text-center mt-3">
                    <a class="auth-link fw-semibold text-decoration-none" href="{{ route('login') }}">Back to login</a>
                </div>
            </form>
        </section>
    </main>

    <footer class="login-footer d-flex flex-column flex-md-row align-items-center justify-content-between gap-2 px-3 px-md-5 py-2 fixed-bottom">
        <div class="d-flex align-items-center gap-3">
            <span class="footer-brand h4 mb-0" style="color: var(--psu-navy);">PSU</span>
            <p class="mb-0 small">&copy; 2024 Pangasinan State University. All Rights Reserved.</p>
        </div>
        <div class="d-flex flex-wrap justify-content-center gap-3 small">
            <a class="text-dark" href="#">Privacy Policy</a>
            <a class="text-dark" href="#">Terms of Service</a>
            <a class="text-dark" href="#">Contact Us</a>
            <a class="text-dark" href="#">PSU Main Website</a>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/pages/auth/reset-password.js') }}?v={{ filemtime(public_path('js/pages/auth/reset-password.js')) }}"></script>
</body>
</html>
