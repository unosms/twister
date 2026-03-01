@php
    $sidebarAuthUser = $sidebarAuthUser ?? $authUser ?? null;

    if (!$sidebarAuthUser && session()->has('auth.user_id')) {
        $sidebarAuthUser = \App\Models\User::find((int) session('auth.user_id'));
    }

    $sidebarProfileName = $sidebarAuthUser->name ?? 'Admin';
    $sidebarRole = strtolower((string) ($sidebarAuthUser->role ?? session('auth.role') ?? 'admin'));
    $sidebarProfileRole = $sidebarRole === 'admin' ? 'Super Admin' : 'User';
    $sidebarInitial = strtoupper(substr($sidebarProfileName, 0, 1));
@endphp

<div class="flex flex-col gap-3 border-t border-slate-200 p-4 dark:border-slate-800">
    <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/60">
        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary">
            {{ $sidebarInitial !== '' ? $sidebarInitial : 'A' }}
        </div>
        <div class="min-w-0" data-sidebar-profile>
            <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $sidebarProfileName }}</p>
            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $sidebarProfileRole }}</p>
        </div>
    </div>

    <a class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium text-red-500 transition-colors hover:bg-red-50 dark:hover:bg-red-950/20" href="{{ route('auth.logout') }}" data-sidebar-item>
        <span class="material-symbols-outlined text-[20px]">logout</span>
        <span class="text-sm" data-sidebar-label>Logout</span>
    </a>
</div>
