<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forgot Password | AIssessment</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --psu-navy: #030351;
            --psu-gold: #ffda27;
            --psu-blue: #2ea3f2;
            --psu-text: #001550;
        }

        body {
            min-height: 100vh;
            font-family: "Open Sans", sans-serif;
            color: var(--psu-text);
            background: #000;
        }

        .material-symbols-outlined {
            font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24;
        }

        .topbar {
            height: 56px;
            background: var(--psu-navy);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.22);
            z-index: 20;
        }

        .brand-title,
        .auth-title,
        .btn-auth,
        .footer-brand {
            font-family: "Oswald", sans-serif;
        }

        .auth-shell {
            min-height: calc(100vh - 56px);
            padding: 80px 16px 72px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .auth-bg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .auth-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
        }

        .auth-card {
            position: relative;
            width: min(100%, 430px);
            background: rgba(255, 255, 255, 0.95);
            border-top: 4px solid var(--psu-navy);
            border-radius: 0.5rem;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }

        .auth-logo {
            width: 88px;
            height: 88px;
            object-fit: contain;
        }

        .form-icon {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #777681;
        }

        .form-control.with-icon {
            padding-left: 2.65rem;
        }

        .form-control:focus {
            border-color: var(--psu-blue);
            box-shadow: 0 0 0 0.2rem rgba(46, 163, 242, 0.18);
        }

        .btn-auth {
            background: var(--psu-navy);
            color: var(--psu-gold);
            border: 0;
            font-size: 1.35rem;
            line-height: 1.2;
        }

        .btn-auth:hover,
        .btn-auth:focus {
            background: #0d0f58;
            color: var(--psu-gold);
        }

        .auth-link {
            color: var(--psu-blue);
        }

        .login-footer {
            background: #ebedff;
            border-top: 1px solid #c7c5d2;
        }

        .spinner-icon {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
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
                    Reset Password
                </h1>
                <p class="text-secondary mb-0">Enter your account email to receive a password reset link.</p>
            </div>

            <form action="{{ route('password.email') }}" id="forgotPasswordForm" method="POST">
                @csrf

                @if ($errors->any())
                    <div class="alert alert-danger py-2">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if (session('status'))
                    <div class="alert alert-success py-2">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('mail_warning'))
                    <div class="alert alert-warning py-2">
                        {{ session('mail_warning') }}
                    </div>
                @endif

                <div class="mb-4">
                    <label class="form-label fw-bold" for="email">Email Address</label>
                    <div class="position-relative">
                        <span class="material-symbols-outlined form-icon">mail</span>
                        <input autocomplete="email" autofocus class="form-control form-control-lg with-icon" id="email" name="email" placeholder="Enter your email address" required type="email" value="{{ old('email') }}">
                    </div>
                </div>

                <button class="btn btn-auth btn-lg w-100 d-flex align-items-center justify-content-center gap-2 py-3" type="submit">
                    Send Reset Link
                    <span class="material-symbols-outlined">outgoing_mail</span>
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
    <script>
        document.getElementById("forgotPasswordForm").addEventListener("submit", function (event) {
            const button = event.target.querySelector('button[type="submit"]');
            button.innerHTML = '<span class="material-symbols-outlined spinner-icon">progress_activity</span> Sending...';
            button.disabled = true;
        });
    </script>
</body>
</html>
