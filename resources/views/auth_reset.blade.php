<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<title>Reset Password - Twister Device Control</title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: '#135bec',
                    'background-light': '#f6f6f8',
                },
                fontFamily: {
                    display: ['Inter'],
                },
            },
        },
    };
</script>
<style>
    body { font-family: 'Inter', sans-serif; }
    .panel {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.97) 0%, rgba(255, 255, 255, 1) 100%);
        border: 1px solid rgba(207, 215, 231, 0.9);
        box-shadow: 0 18px 45px rgba(16, 22, 34, 0.08);
    }
</style>
</head>
<body class="bg-background-light font-display text-slate-900">
<main class="min-h-screen px-4 py-10 sm:px-6">
    <div class="mx-auto max-w-xl">
        <div class="panel rounded-3xl p-8 sm:p-10">
            <p class="text-xs font-bold uppercase tracking-[0.28em] text-primary">Protected Reset</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight">Set a new password for {{ $username }}</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                This reset link is scoped to the requested user account and was delivered through the protected super admin approval path.
            </p>

            @if ($errors->any())
                <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="mt-8 grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-600 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Username</p>
                    <p class="mt-1 font-semibold text-slate-800">{{ $username }}</p>
                </div>
            </div>

            <form class="mt-8 space-y-5" method="POST" action="{{ route('auth.reset.submit') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}"/>
                <input type="hidden" name="username" value="{{ $username }}"/>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700" for="password">New Password</label>
                    <input
                        class="block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary"
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700" for="password_confirmation">Confirm Password</label>
                    <input
                        class="block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary"
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                    />
                </div>

                <button class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-5 py-3.5 text-sm font-bold text-white transition hover:bg-primary/90" type="submit">
                    Update Password
                </button>
            </form>

            <div class="mt-6 flex items-center justify-between gap-4 text-sm">
                <a class="font-semibold text-slate-500 transition hover:text-slate-800" href="{{ route('auth.login') }}">Back to login</a>
                <span class="text-slate-400">Minimum password length: 8 characters.</span>
            </div>
        </div>
    </div>
</main>
</body>
</html>

