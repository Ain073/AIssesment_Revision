<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login | AIssessment</title>

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
            min-height: 100dvh;
            font-family: "Open Sans", sans-serif;
            color: var(--psu-text);
            background: #ebedff;
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
        .login-title,
        .btn-login,
        .footer-brand {
            font-family: "Oswald", sans-serif;
        }

        .login-shell {
            min-height: calc(100vh - 56px);
            padding: 80px 16px 72px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .login-bg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .login-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
        }

        .login-card {
            position: relative;
            width: min(100%, 390px);
            background: rgba(255, 255, 255, 0.95);
            border-top: 4px solid var(--psu-navy);
            border-radius: 0.5rem;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }

        .login-logo {
            width: 96px;
            height: 96px;
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

        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #777681;
            padding: 0;
        }

        .form-control:focus,
        .form-check-input:focus {
            border-color: var(--psu-blue);
            box-shadow: 0 0 0 0.2rem rgba(46, 163, 242, 0.18);
        }

        .btn-login {
            background: var(--psu-navy);
            color: var(--psu-gold);
            border: 0;
            font-size: 1.5rem;
            line-height: 1.2;
        }

        .btn-login:hover,
        .btn-login:focus {
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

        @media (max-width: 575.98px) {
            .topbar {
                height: 48px;
                justify-content: center;
            }

            .brand-title {
                display: inline !important;
                font-size: 1.05rem;
                letter-spacing: 0.04em;
            }

            .login-shell {
                min-height: calc(100dvh - 92px);
                padding: 56px 14px 10px;
                align-items: center;
            }

            .login-card {
                width: 100%;
                max-width: 360px;
                padding: 1.15rem !important;
                border-top-width: 3px;
                border-radius: 0.65rem;
                box-shadow: 0 16px 36px rgba(0, 0, 0, 0.22);
            }

            .login-logo {
                width: 64px;
                height: 64px;
                margin-bottom: 0.7rem !important;
            }

            .login-title {
                font-size: 1.28rem;
                line-height: 1.12;
            }

            .login-card .text-center.mb-4 {
                margin-bottom: 1rem !important;
            }

            .login-card .mb-3 {
                margin-bottom: 0.85rem !important;
            }

            .login-card form > .d-flex {
                margin-bottom: 1rem !important;
                gap: 0.5rem;
            }

            .form-label {
                font-size: 0.9rem;
                margin-bottom: 0.4rem;
            }

            .form-control.form-control-lg {
                font-size: 0.95rem;
                min-height: 44px;
                padding-top: 0.55rem;
                padding-bottom: 0.55rem;
            }

            .form-control.with-icon {
                padding-left: 2.45rem;
            }

            .form-icon {
                left: 0.8rem;
                font-size: 1.25rem;
            }

            .form-check-label,
            .auth-link {
                font-size: 0.9rem;
            }

            .btn-login {
                font-size: 1.15rem;
                padding-top: 0.75rem !important;
                padding-bottom: 0.75rem !important;
            }

            .login-footer {
                min-height: 44px;
                padding: 0.35rem 0.75rem !important;
            }

            .login-footer > div {
                gap: 0.45rem !important;
            }

            .login-footer .footer-brand {
                font-size: 1rem;
            }

            .login-footer p {
                max-width: none;
                font-size: 0.72rem;
                line-height: 1.2;
            }
        }
    </style>
</head>
<body>
    <header class="topbar fixed-top d-flex align-items-center px-3 px-md-5">
        <span class="brand-title h4 mb-0 fw-bold text-white">AISSESSMENT</span>
    </header>

    <main class="login-shell">
        <img class="login-bg" src="{{ asset('images/psu-campus-background.jpg') }}" alt="PSU Building">
        <div class="login-overlay"></div>

        <section class="login-card p-4">
            <div class="text-center mb-4">
                <img class="login-logo mb-3" src="{{ asset('images/psu-logo-transparent.png') }}" alt="PSU Seal">
                <h1 class="login-title h3 mb-0 fw-bold text-uppercase" style="color: var(--psu-navy);">
                    Pangasinan State University
                </h1>
            </div>

            <form action="{{ route('login.submit') }}" id="loginForm" method="POST">
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

                <div class="mb-3">
                    <label class="form-label fw-bold" for="email">Email Address</label>
                    <div class="position-relative">
                        <span class="material-symbols-outlined form-icon">person</span>
                        <input autocomplete="username" class="form-control form-control-lg with-icon" id="email" name="email" placeholder="Enter your email address" required type="email" value="{{ old('email') }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold" for="password">Password</label>
                    <div class="position-relative">
                        <span class="material-symbols-outlined form-icon">lock</span>
                        <input autocomplete="current-password" class="form-control form-control-lg with-icon pe-5" id="password" name="password" placeholder="Password" required type="password">
                        <button aria-label="Show password" class="password-toggle" id="togglePassword" type="button">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="form-check">
                        <input class="form-check-input" id="remember" name="remember" type="checkbox">
                        <label class="form-check-label" for="remember">Remember Me</label>
                    </div>
                    <a class="auth-link fw-semibold text-decoration-none" href="{{ route('password.request') }}">Forgot Password?</a>
                </div>

                <button class="btn btn-login btn-lg w-100 d-flex align-items-center justify-content-center gap-2 py-3" type="submit">
                    Log In
                    <span class="material-symbols-outlined">login</span>
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
    <script>
        window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        if (window.jQuery && window.csrfToken) {
            window.jQuery.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': window.csrfToken,
                },
            });
        }

        document.getElementById("loginForm").addEventListener("submit", function (event) {
            const button = event.target.querySelector('button[type="submit"]');
            button.innerHTML = '<span class="material-symbols-outlined spinner-icon">progress_activity</span> Authenticating...';
            button.disabled = true;
        });

        document.getElementById("togglePassword").addEventListener("click", function () {
            const password = document.getElementById("password");
            const icon = this.querySelector(".material-symbols-outlined");
            const isPassword = password.type === "password";

            password.type = isPassword ? "text" : "password";
            icon.textContent = isPassword ? "visibility_off" : "visibility";
        });
    </script>
</body>
</html>
