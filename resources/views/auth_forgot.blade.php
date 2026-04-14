<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<title>Forgot Password - Twister Device Control</title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: '#135bec',
                    'background-light': '#f6f6f8',
                    'background-dark': '#101622',
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
            <p class="text-xs font-bold uppercase tracking-[0.28em] text-primary">Password Recovery</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight">Send a reset link to the super admin Telegram bot</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                Enter the username for the account that needs a new password. The system will generate a reset URL for that
                user and send it to the configured super admin Telegram recipient for approval and completion.
            </p>

            @if ($errors->any())
                <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('status'))
                <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-600">
                <p class="font-semibold text-slate-800">What happens next</p>
                <p class="mt-2">A one-time reset URL is generated for the requested user account and delivered to the default protected super admin Telegram chat.</p>
            </div>

            <form class="mt-8 space-y-5" method="POST" action="{{ route('auth.forgot.submit') }}">
                @csrf
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700" for="username">Username</label>
                    <input
                        class="block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 focus:border-primary focus:ring-primary"
                        id="username"
                        name="username"
                        type="text"
                        value="{{ old('username') }}"
                        placeholder="Enter the username to reset"
                        required
                        autofocus
                    />
                </div>

                <button class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-5 py-3.5 text-sm font-bold text-white transition hover:bg-primary/90" type="submit">
                    Send Reset Link to Telegram
                </button>
            </form>

            <div class="mt-6 flex items-center justify-between gap-4 text-sm">
                <a class="font-semibold text-slate-500 transition hover:text-slate-800" href="{{ route('auth.login') }}">Back to login</a>
                <span class="text-slate-400">Reset links expire after 60 minutes.</span>
            </div>
        </div>
    </div>
</main>
</body>
</html>

