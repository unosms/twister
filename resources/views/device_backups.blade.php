<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="app-base" content="{{ url('/') }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Device Backups</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#135bec",
                        "background-dark": "#101622",
                    },
                },
            },
        };
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
    @include('partials.admin_sidebar_styles')
    <script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script>
</head>
<body class="bg-slate-100 text-slate-900 h-screen overflow-hidden dark:bg-background-dark dark:text-slate-100">
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
                        <h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">Device Backups</h1>
                        <p class="mt-1 text-sm text-slate-500">Review saved backup artifacts and trigger a fresh backup for {{ $device->name }}.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('devices.details') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800">Back to Devices</a>
                    <form method="POST" action="{{ route('devices.backups.run', ['device' => $device->id]) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Run Backup Now</button>
                    </form>
                </div>
            </header>

            <div class="mx-auto w-full max-w-6xl p-6 md:p-8">
                <div class="mb-6">
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        Device #{{ $device->id }} - {{ $device->name }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">Folder: {{ $backupFolder ?? 'N/A' }}</p>
                </div>

                @if (session('status'))
                    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ session('error') }}
                    </div>
                @endif

                @php
                    $output = session('backup_output');
                @endphp
                @if (!empty($output))
                    <details class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white" open>
                        <summary class="cursor-pointer bg-slate-50 px-4 py-3 text-sm font-semibold">Backup Output</summary>
                        <pre class="overflow-x-auto whitespace-pre-wrap p-4 text-xs">{{ $output }}</pre>
                    </details>
                @endif

                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Available Backup Files</h2>
                    </div>

                    @if (!empty($backupFiles))
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="border-b border-slate-200">
                                    <tr>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">File</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Size</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Modified</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    @foreach ($backupFiles as $file)
                                        <tr>
                                            <td class="px-4 py-3 text-sm font-medium text-slate-700">{{ $file['name'] ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-slate-600">{{ $file['size_human'] ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-slate-600">{{ $file['modified_at'] ?? '-' }}</td>
                                            <td class="px-4 py-3 text-right">
                                                <a class="inline-flex rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-slate-50" href="{{ $file['download_url'] ?? '#' }}">Download</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="px-4 py-5 text-sm text-slate-500">
                            No backup files found yet for this device.
                        </div>
                    @endif
                </section>
            </div>
        </main>
    </div>
</body>
</html>
