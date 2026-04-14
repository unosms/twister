<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/><meta name="csrf-token" content="{{ csrf_token() }}"/><meta name="app-base" content="{{ url('/') }}"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Admin Login - 2FA Verification | Twister Device Control</title>
<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<!-- Google Fonts: Inter -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
<!-- Material Symbols -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <script src="{{ asset('js/actions.js') }}" defer></script></head>
<body class="bg-background-light dark:bg-background-dark font-display text-[#0d121b] dark:text-white min-h-screen flex flex-col">
<!-- Top Navigation Bar -->
<header class="flex items-center justify-between whitespace-nowrap border-b border-solid border-[#e7ebf3] dark:border-[#2d3748] px-6 py-4 bg-white dark:bg-background-dark">
<div class="flex items-center gap-4 text-[#0d121b] dark:text-white">
<div class="size-8 flex items-center justify-center text-primary">
<svg class="w-full h-full" fill="none" viewbox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
<path d="M44 4H30.6666V17.3334H17.3334V30.6666H4V44H44V4Z" fill="currentColor"></path>
</svg>
</div>
<h2 class="text-lg font-bold leading-tight tracking-[-0.015em]">Twister Device Control</h2>
</div>
<div class="hidden md:flex gap-4 items-center">
<span class="text-sm font-medium text-slate-500 dark:text-slate-400">Environment: Production</span>
<span class="material-symbols-outlined text-slate-400">security</span>
</div>
</header>
<!-- Main Content Area -->
<main class="flex-grow flex items-center justify-center p-6">
<div class="layout-content-container flex flex-col max-w-[480px] w-full bg-white dark:bg-[#1a2234] rounded-xl shadow-xl border border-[#e7ebf3] dark:border-[#2d3748] overflow-hidden">
<!-- Icon/Visual Header -->
<div class="pt-10 pb-2 flex justify-center">
<div class="bg-primary/10 p-4 rounded-full">
<span class="material-symbols-outlined text-primary text-4xl" style="font-variation-settings: 'FILL' 1">verified_user</span>
</div>
</div>
<!-- Headline Text -->
<h1 class="text-[#0d121b] dark:text-white tracking-tight text-[28px] font-bold leading-tight px-8 text-center pt-4">Two-Factor Authentication</h1>
<!-- Body Text -->
<p class="text-slate-600 dark:text-slate-300 text-sm font-normal leading-relaxed px-10 text-center pt-3">
                To protect your account, please enter the 6-digit verification code sent to <span class="font-semibold text-[#0d121b] dark:text-white">{{ $maskedEmail ?? 'your work email' }}</span>
</p>
<form method="POST" action="{{ route('auth.2fa.verify') }}">
@csrf
<!-- 2FA Input Fields -->
@if (session('status'))
<div class="px-8 pt-6">
<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
{{ session('status') }}
</div>
</div>
@endif
@if ($errors->any())
<div class="px-8 pt-6">
<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
<p class="font-semibold">Verification failed</p>
<p class="mt-1">{{ $errors->first() }}</p>
</div>
</div>
@endif
<div class="flex justify-center px-8 py-8">
<fieldset class="relative flex gap-3 md:gap-4" data-otp>
<input class="flex h-14 w-12 text-center rounded-lg bg-background-light dark:bg-[#101622] border-2 border-[#cfd7e7] dark:border-[#2d3748] focus:border-primary dark:focus:border-primary focus:ring-0 text-xl font-bold leading-normal transition-colors" max="9" maxlength="1" min="0" name="code[]" type="number"/>
<input class="flex h-14 w-12 text-center rounded-lg bg-background-light dark:bg-[#101622] border-2 border-[#cfd7e7] dark:border-[#2d3748] focus:border-primary dark:focus:border-primary focus:ring-0 text-xl font-bold leading-normal transition-colors" max="9" maxlength="1" min="0" name="code[]" type="number"/>
<input class="flex h-14 w-12 text-center rounded-lg bg-background-light dark:bg-[#101622] border-2 border-[#cfd7e7] dark:border-[#2d3748] focus:border-primary dark:focus:border-primary focus:ring-0 text-xl font-bold leading-normal transition-colors" max="9" maxlength="1" min="0" name="code[]" type="number"/>
<input class="flex h-14 w-12 text-center rounded-lg bg-background-light dark:bg-[#101622] border-2 border-[#cfd7e7] dark:border-[#2d3748] focus:border-primary dark:focus:border-primary focus:ring-0 text-xl font-bold leading-normal transition-colors" max="9" maxlength="1" min="0" name="code[]" placeholder="-" type="number"/>
<input class="flex h-14 w-12 text-center rounded-lg bg-background-light dark:bg-[#101622] border-2 border-[#cfd7e7] dark:border-[#2d3748] focus:border-primary dark:focus:border-primary focus:ring-0 text-xl font-bold leading-normal transition-colors" max="9" maxlength="1" min="0" name="code[]" placeholder="-" type="number"/>
<input class="flex h-14 w-12 text-center rounded-lg bg-background-light dark:bg-[#101622] border-2 border-[#cfd7e7] dark:border-[#2d3748] focus:border-primary dark:focus:border-primary focus:ring-0 text-xl font-bold leading-normal transition-colors" max="9" maxlength="1" min="0" name="code[]" placeholder="-" type="number"/>
</fieldset>
</div>
<!-- Timer and Resend -->
<div class="px-8 pb-4">
<div class="flex items-center justify-between bg-background-light dark:bg-[#101622] rounded-lg p-4">
<div class="flex flex-col">
<span class="text-xs text-slate-500 dark:text-slate-400 uppercase font-semibold tracking-wider">Code Expires In</span>
<div class="flex items-center gap-1 text-primary font-bold">
<span class="material-symbols-outlined text-sm">schedule</span>
<span>01:42</span>
</div>
</div>
<button class="text-primary text-sm font-semibold hover:underline" formaction="{{ route('auth.2fa.resend') }}" formmethod="POST" type="submit">Resend Code</button>
</div>
</div>
<!-- Action Buttons -->
<div class="px-8 pb-10 pt-4 flex flex-col gap-3">
<button class="w-full h-12 bg-primary hover:bg-primary/90 text-white font-bold rounded-lg shadow-md transition-all flex items-center justify-center gap-2" type="submit">
                    Verify and Continue
                    <span class="material-symbols-outlined">login</span>
</button>
<a class="w-full h-10 text-slate-600 dark:text-slate-400 font-medium hover:text-primary dark:hover:text-primary transition-colors text-sm flex items-center justify-center" href="{{ route('auth.login') }}">
                    Back to Login
                </a>
</div>
</form>
<!-- Footer Meta -->
<div class="bg-background-light/50 dark:bg-black/20 px-8 py-3 border-t border-[#e7ebf3] dark:border-[#2d3748] flex justify-between items-center text-[11px] text-slate-400">
<span>Security Level: High</span>
<span>Session: 4f29-xa82</span>
</div>
</div>
</main>
<!-- Page Footer -->
<footer class="p-6 text-center text-slate-500 text-sm">
<p>© 2024 Twister Device Control. All rights reserved.</p>
<div class="flex justify-center gap-4 mt-2">
<a class="hover:text-primary" href="#">Help Center</a>
<a class="hover:text-primary" href="#">Privacy Policy</a>
<a class="hover:text-primary" href="#">Security Documentation</a>
</div>
</footer>
</body></html>



