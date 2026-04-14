<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<meta name="app-base" content="{{ url('/') }}"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Account Login - Twister Device Control</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#135bec",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "2xl": "1rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
<style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .geometric-bg {
            background-color: #135bec;
            background-image:
                linear-gradient(30deg, #0d121b 12%, transparent 12.5%, transparent 87%, #0d121b 87.5%, #0d121b),
                linear-gradient(150deg, #0d121b 12%, transparent 12.5%, transparent 87%, #0d121b 87.5%, #0d121b),
                linear-gradient(30deg, #0d121b 12%, transparent 12.5%, transparent 87%, #0d121b 87.5%, #0d121b),
                linear-gradient(150deg, #0d121b 12%, transparent 12.5%, transparent 87%, #0d121b 87.5%, #0d121b),
                linear-gradient(60deg, #135bec 25%, transparent 25.5%, transparent 75%, #135bec 75%, #135bec),
                linear-gradient(60deg, #135bec 25%, transparent 25.5%, transparent 75%, #135bec 75%, #135bec);
            background-size: 80px 140px;
            background-position: 0 0, 0 0, 40px 70px, 40px 70px, 0 0, 40px 70px;
        }

        .login-card {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.94) 0%, rgba(255, 255, 255, 0.98) 100%);
            border: 1px solid rgba(207, 215, 231, 0.9);
            box-shadow: 0 18px 45px rgba(16, 22, 34, 0.1);
        }

        .dark .login-card {
            background: linear-gradient(180deg, rgba(16, 22, 34, 0.9) 0%, rgba(16, 22, 34, 0.97) 100%);
            border-color: rgba(55, 65, 81, 0.85);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
        }
    </style>
<script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script>
</head>
<body class="bg-background-light dark:bg-background-dark font-display">
<div class="min-h-screen w-full lg:grid lg:grid-cols-2">
    <section class="hidden lg:flex relative overflow-hidden bg-primary p-12 items-center">
        <div class="absolute inset-0 opacity-20 geometric-bg"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-primary via-primary/80 to-transparent"></div>

        <div class="relative z-10 max-w-xl text-white">
            <div class="mb-10 flex items-center gap-3">
                <div class="size-10 rounded-lg bg-white text-primary flex items-center justify-center">
                    <svg class="size-6" fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                        <path d="M44 4H30.6666V17.3334H17.3334V30.6666H4V44H44V4Z" fill="currentColor"></path>
                    </svg>
                </div>
                <span class="text-2xl font-bold tracking-tight">Twister Device Control</span>
            </div>

            <h1 class="text-5xl font-black leading-tight">Access your account and get straight to operations.</h1>
            <p class="mt-6 text-lg leading-relaxed text-white/80">
                A practical login flow for operators and administrators: sign in quickly, recover access when needed, and continue where you left off.
            </p>

            <div class="mt-10 grid grid-cols-1 gap-3 text-sm text-white/85">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">shield_lock</span>
                    Role-based access and command permissions
                </div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">devices</span>
                    Unified control for device operations and alerts
                </div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">verified_user</span>
                    Encrypted sessions with optional 30-day remember me
                </div>
            </div>

            <p class="mt-14 text-xs text-white/60">Copyright 2026 Twister Device Control</p>
        </div>
    </section>

    <section class="flex items-center justify-center px-4 py-8 sm:px-8 lg:px-12 bg-background-light dark:bg-background-dark">
        <div class="w-full max-w-md">
            <div class="mb-7 lg:hidden flex items-center justify-center gap-2">
                <div class="size-8 rounded bg-primary text-white flex items-center justify-center">
                    <svg class="size-5" fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                        <path d="M44 4H30.6666V17.3334H17.3334V30.6666H4V44H44V4Z" fill="currentColor"></path>
                    </svg>
                </div>
                <span class="text-xl font-bold tracking-tight text-[#0d121b] dark:text-white">Twister Device Control</span>
            </div>

            <div class="login-card rounded-2xl p-6 sm:p-8">
                <div class="mb-6">
                    <p class="text-xs font-bold uppercase tracking-wider text-primary">Account Login</p>
                    <h2 class="mt-2 text-3xl font-black text-[#0d121b] dark:text-white">Sign in to continue</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Use your assigned username and password.</p>
                </div>

                @if ($errors->any())
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <p class="font-semibold">Login failed</p>
                        <p class="mt-1">{{ $errors->first() }}</p>
                    </div>
                @endif

                @if (session('status'))
                    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        <p class="font-semibold">Status</p>
                        <p class="mt-1">{{ session('status') }}</p>
                    </div>
                @endif

                <form class="space-y-5" method="POST" action="{{ route('auth.login.submit') }}" data-login-form>
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 px-3 py-2 text-slate-600 dark:text-slate-300">
                            Use your exact username
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 px-3 py-2 text-slate-600 dark:text-slate-300">
                            Admin and user accounts sign in here
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300" for="username">Username</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                                <span class="material-symbols-outlined text-[20px]">person</span>
                            </span>
                            <input
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-background-dark pl-10 pr-4 py-3.5 text-gray-900 dark:text-white placeholder-gray-400 focus:border-transparent focus:ring-2 focus:ring-primary transition-all"
                                id="username"
                                name="username"
                                type="text"
                                placeholder="e.g. network.ops"
                                value="{{ old('username') }}"
                                required
                                autocomplete="username"
                                autofocus
                            />
                        </div>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300" for="password">Password</label>
                            <a class="text-sm font-bold text-primary hover:underline" href="{{ route('auth.forgot') }}">Forgot password?</a>
                        </div>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                                <span class="material-symbols-outlined text-[20px]">lock</span>
                            </span>
                            <input
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-background-dark pl-10 pr-11 py-3.5 text-gray-900 dark:text-white placeholder-gray-400 focus:border-transparent focus:ring-2 focus:ring-primary transition-all"
                                id="password"
                                name="password"
                                type="password"
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                                data-caps-lock-target="login-password-warning"
                            />
                            <button class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" type="button" data-toggle="password" data-target="#password" data-no-dispatch="true" aria-pressed="false" aria-label="Toggle password visibility">
                                <span class="material-symbols-outlined">visibility_off</span>
                            </button>
                        </div>
                        <p class="mt-2 hidden text-xs text-amber-700 dark:text-amber-300" data-caps-lock-warning="login-password-warning">Caps Lock is on.</p>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <label class="inline-flex items-center gap-2">
                            <input class="h-4 w-4 rounded border-gray-300 dark:border-gray-700 text-primary focus:ring-primary" id="remember-me" name="remember-me" type="checkbox" {{ old('remember-me') ? 'checked' : '' }}/>
                            <span class="text-sm text-gray-700 dark:text-gray-300">Stay logged in for 30 days</span>
                        </label>
                    </div>

                    <button class="w-full flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3.5 text-base font-bold text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-all" type="submit" data-login-submit>
                        <span data-login-label>Sign In</span>
                        <span class="material-symbols-outlined text-lg">arrow_forward</span>
                    </button>
                </form>

                <div class="mt-6 border-t border-slate-200 dark:border-slate-700 pt-4">
                    <p class="text-sm text-center text-slate-500 dark:text-slate-400">
                        Need admin onboarding?
                        <a class="font-bold text-primary hover:underline" href="{{ route('auth.request') }}">Request access</a>
                    </p>
                </div>
            </div>

            <p class="mt-5 text-center text-xs text-slate-500 dark:text-slate-400">Secure session protected with encrypted transport.</p>
        </div>
    </section>
</div>
</body>
</html>

