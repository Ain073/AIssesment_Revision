<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Login | PSU Assessment Portal</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Open+Sans:wght@400;600;700&family=Lora&display=swap" rel="stylesheet">
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#000000",
                        "digital-blue": "#2EA3F2",
                        "on-surface": "#001550",
                        "heritage-blue": "#030351",
                        "on-error-container": "#93000a",
                        "surface-container-lowest": "#ffffff",
                        "primary-container": "#0d0f58",
                        "tertiary-fixed-dim": "#95ccff",
                        "on-secondary-fixed": "#221b00",
                        "secondary-fixed-dim": "#e8c400",
                        "on-tertiary": "#ffffff",
                        error: "#ba1a1a",
                        "surface-container-highest": "#dce1ff",
                        "academic-gold": "#FFDA27",
                        "outline-variant": "#c7c5d2",
                        "on-background": "#001550",
                        tertiary: "#000000",
                        "primary-fixed": "#e0e0ff",
                        "on-secondary-container": "#705e00",
                        "surface-dim": "#d1d8ff",
                        "surface-white": "#FFFFFF",
                        "on-primary-container": "#797dc7",
                        "tertiary-container": "#001d32",
                        "error-container": "#ffdad6",
                        "on-primary-fixed-variant": "#3b4084",
                        "secondary-fixed": "#ffe168",
                        secondary: "#6f5d00",
                        "on-surface-variant": "#464650",
                        "inverse-primary": "#bfc2ff",
                        "on-secondary": "#ffffff",
                        "on-tertiary-fixed-variant": "#004a75",
                        surface: "#faf8ff",
                        "surface-container-high": "#e4e7ff",
                        outline: "#777681",
                        "tertiary-fixed": "#cde5ff",
                        "surface-tint": "#53589e",
                        "on-secondary-fixed-variant": "#544600",
                        "on-primary": "#ffffff",
                        "surface-container": "#ebedff",
                        "primary-fixed-dim": "#bfc2ff",
                        "on-primary-fixed": "#0d0f58",
                        "on-tertiary-fixed": "#001d32",
                        "deep-navy": "#001A5D",
                        "secondary-container": "#fdd824",
                        "inverse-on-surface": "#eff0ff",
                        background: "#faf8ff",
                        "on-error": "#ffffff",
                        "surface-variant": "#dce1ff",
                        "surface-container-low": "#f3f2ff",
                        "surface-bright": "#faf8ff",
                        "on-tertiary-container": "#008ad4",
                        "inverse-surface": "#162b6c"
                    },
                    borderRadius: {
                        DEFAULT: "0.125rem",
                        lg: "0.25rem",
                        xl: "0.5rem",
                        full: "0.75rem"
                    },
                    spacing: {
                        "margin-desktop": "48px",
                        "section-gap": "80px",
                        "margin-mobile": "16px",
                        "container-max": "1280px",
                        gutter: "24px",
                        base: "8px"
                    },
                    fontFamily: {
                        "label-md": ["Open Sans"],
                        "headline-lg-mobile": ["Oswald"],
                        "label-lg": ["Open Sans"],
                        "body-sm": ["Open Sans"],
                        "headline-md": ["Oswald"],
                        "headline-lg": ["Oswald"],
                        "headline-xl": ["Oswald"],
                        "body-lg": ["Open Sans"],
                        "body-md": ["Open Sans"],
                        "display-accent": ["Lora"]
                    },
                    fontSize: {
                        "label-md": ["12px", { lineHeight: "14px", fontWeight: "600" }],
                        "headline-lg-mobile": ["28px", { lineHeight: "36px", fontWeight: "600" }],
                        "label-lg": ["14px", { lineHeight: "16px", fontWeight: "700" }],
                        "body-sm": ["14px", { lineHeight: "20px", fontWeight: "400" }],
                        "headline-md": ["24px", { lineHeight: "32px", fontWeight: "500" }],
                        "headline-lg": ["32px", { lineHeight: "40px", letterSpacing: "0.01em", fontWeight: "600" }],
                        "headline-xl": ["48px", { lineHeight: "60px", letterSpacing: "0.02em", fontWeight: "700" }],
                        "body-lg": ["18px", { lineHeight: "28px", fontWeight: "400" }],
                        "body-md": ["16px", { lineHeight: "24px", fontWeight: "400" }],
                        "display-accent": ["20px", { lineHeight: "28px", fontWeight: "400" }]
                    }
                }
            }
        };
    </script>
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .material-symbols-outlined {
            font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col font-body-md text-on-surface bg-black">
    <header class="fixed top-0 left-0 w-full z-50 flex justify-between items-center px-margin-mobile md:px-margin-desktop h-14 bg-heritage-blue shadow-md">
        <div class="flex items-center gap-4">
            <span class="font-headline-md text-headline-md font-bold text-surface-white hidden lg:block">AISSESSMENT</span>
        </div>
        <nav class="flex items-center gap-8">
            <a class="text-surface-white font-label-lg text-label-lg hover:text-academic-gold transition-colors duration-200" href="#">Help Desk</a>
        </nav>
    </header>

    <main class="flex-grow flex items-center justify-center relative overflow-hidden pt-20 pb-4">
        <div class="absolute inset-0 z-0">
            <img alt="PSU Building" class="w-full h-full object-cover object-center" src="{{ asset('images/psu-campus-background.jpg') }}">
            <div class="absolute inset-0 bg-black/35"></div>
        </div>

        <div class="relative z-10 w-full max-w-sm px-margin-mobile md:px-0">
            <div class="glass-card rounded-xl shadow-2xl overflow-hidden border-t-4 border-heritage-blue">
                <div class="p-5 md:p-6">
                    <div class="text-center mb-5">
                        <img alt="PSU Seal" class="h-20 md:h-24 mx-auto mb-3 object-contain" src="{{ asset('images/psu-logo-transparent.png') }}">
                        <div class="font-headline-md text-[22px] leading-7 font-bold text-heritage-blue tracking-wide uppercase">
                            Pangasinan State University
                        </div>
                    </div>

                    <form class="space-y-4" id="loginForm">
                        <div class="space-y-2">
                            <label class="block font-label-lg text-label-lg text-on-surface" for="id_number">Student/Faculty ID</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">person</span>
                                <input class="w-full pl-10 pr-4 py-2.5 bg-surface border border-outline-variant rounded focus:ring-2 focus:ring-digital-blue focus:border-heritage-blue outline-none transition-all font-body-md text-body-md" id="id_number" placeholder="Enter your ID number" required type="text">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="block font-label-lg text-label-lg text-on-surface" for="password">Password</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">lock</span>
                                <input class="w-full pl-10 pr-12 py-2.5 bg-surface border border-outline-variant rounded focus:ring-2 focus:ring-digital-blue focus:border-heritage-blue outline-none transition-all font-body-md text-body-md" id="password" placeholder="Password" required type="password">
                                <button aria-label="Show password" class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-heritage-blue" id="togglePassword" type="button">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input class="w-4 h-4 rounded border-outline-variant text-heritage-blue focus:ring-digital-blue" type="checkbox">
                                <span class="font-body-sm text-body-sm text-on-surface-variant group-hover:text-on-surface transition-colors">Remember Me</span>
                            </label>
                            <a class="font-label-md text-label-md text-digital-blue hover:text-heritage-blue hover:underline transition-all" href="#">Forgot Password?</a>
                        </div>

                        <button class="w-full bg-heritage-blue hover:bg-primary-container text-academic-gold font-headline-md text-[24px] leading-8 py-3 rounded-lg shadow-lg transform active:scale-[0.98] transition-all flex items-center justify-center gap-2" type="submit">
                            Log In
                            <span class="material-symbols-outlined">login</span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="mt-3 text-center">
                <p class="font-label-md text-label-md text-white/80">
                    <span class="material-symbols-outlined text-[14px] align-middle mr-1">verified_user</span>
                    Secured by PSU Information &amp; Communication Technology Office
                </p>
            </div>
        </div>
    </main>

    <footer class="w-full py-2 px-margin-mobile md:px-margin-desktop flex flex-col md:flex-row justify-between items-center gap-2 bg-surface-container border-t border-outline-variant">
        <div class="flex items-center gap-4">
            <span class="font-headline-md text-headline-md text-heritage-blue">PSU</span>
            <p class="font-body-sm text-body-sm text-on-surface-variant">&copy; 2024 Pangasinan State University. All Rights Reserved.</p>
        </div>
        <div class="flex flex-wrap justify-center gap-x-5 gap-y-1">
            <a class="font-label-md text-label-md text-on-surface-variant hover:text-digital-blue underline transition-all" href="#">Privacy Policy</a>
            <a class="font-label-md text-label-md text-on-surface-variant hover:text-digital-blue underline transition-all" href="#">Terms of Service</a>
            <a class="font-label-md text-label-md text-on-surface-variant hover:text-digital-blue underline transition-all" href="#">Contact Us</a>
            <a class="font-label-md text-label-md text-on-surface-variant hover:text-digital-blue underline transition-all" href="#">PSU Main Website</a>
        </div>
    </footer>

    <script>
        document.getElementById("loginForm").addEventListener("submit", function (event) {
            event.preventDefault();

            const button = event.target.querySelector('button[type="submit"]');
            const originalContent = button.innerHTML;

            button.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span> Authenticating...';
            button.disabled = true;

            setTimeout(function () {
                button.innerHTML = originalContent;
                button.disabled = false;
                alert("Authentication service is currently processing. Please wait for official credentials validation.");
            }, 1500);
        });

        document.getElementById("togglePassword").addEventListener("click", function () {
            const password = document.getElementById("password");
            const icon = this.querySelector(".material-symbols-outlined");
            const isPassword = password.type === "password";

            password.type = isPassword ? "text" : "password";
            icon.textContent = isPassword ? "visibility_off" : "visibility";
        });

        document.querySelectorAll("input").forEach(function (input) {
            input.addEventListener("focus", function () {
                const icon = input.parentElement.querySelector(".material-symbols-outlined");
                if (icon) {
                    icon.style.color = "#2EA3F2";
                }
            });

            input.addEventListener("blur", function () {
                const icon = input.parentElement.querySelector(".material-symbols-outlined");
                if (icon) {
                    icon.style.color = "";
                }
            });
        });
    </script>
</body>
</html>
