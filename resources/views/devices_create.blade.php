<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<meta name="app-base" content="{{ url('/') }}"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Create Device</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                primary: "#135bec",
                "background-light": "#f6f6f8",
                "background-dark": "#101622",
            },
            fontFamily: {
                display: ["Inter"],
            },
            borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
        },
    },
}
</script>
<style>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
body { font-family: 'Inter', sans-serif; }
</style>
@include('partials.admin_sidebar_styles')
<script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script>
</head>
<body class="bg-background-light dark:bg-background-dark text-[#0d121b] dark:text-gray-100 h-screen overflow-hidden">
<div class="flex h-screen overflow-hidden">
@include('partials.admin_sidebar')
<main class="flex-1 flex flex-col overflow-y-auto">
<header class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur dark:border-slate-800 dark:bg-background-dark/80 sm:px-6 lg:px-8">
<div class="flex min-w-0 items-center gap-3">
<button class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-300 dark:hover:bg-slate-800" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
<span class="material-symbols-outlined">menu</span>
</button>
<div class="min-w-0">
<p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary">Devices</p>
<h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">Add Device</h1>
<p class="mt-1 text-sm text-slate-500">Register a new device with the same form used in the management workspace.</p>
</div>
</div>
<a class="inline-flex items-center gap-2 rounded-lg border border-[#cfd7e7] bg-white px-4 py-2 text-sm font-semibold hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700/70" href="{{ route('devices.index') }}">
<span class="material-symbols-outlined text-lg">arrow_back</span>
Back to Devices
</a>
</header>

<div class="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
@if ($errors->any())
<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
<p class="font-semibold mb-1">Could not save device. Please fix the following:</p>
<ul class="list-disc pl-5 space-y-0.5">
@foreach ($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif

<div class="rounded-xl border border-[#cfd7e7] bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
<div class="mb-4 flex items-center justify-between gap-3">
<div>
<h2 class="text-lg font-bold">Device Registration</h2>
<p class="mt-1 text-xs text-gray-500">Use the same full registration form that was previously embedded in device management.</p>
</div>
</div>
@include('partials.device_create_form')
</div>
</div>
</main>
</div>
</body>
</html>
