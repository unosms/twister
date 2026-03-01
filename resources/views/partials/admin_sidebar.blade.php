@php
    $sidebarClass = $sidebarClass ?? 'w-64 h-full flex-shrink-0 border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-background-dark/50 flex flex-col justify-between overflow-y-auto';
@endphp

<aside data-sidebar class="{{ $sidebarClass }}">
    <div class="flex flex-col gap-6 p-4">
        @include('partials.admin_sidebar_navigation', ['sidebarAuthUser' => $sidebarAuthUser ?? $authUser ?? null])

        @if (!empty($extraContentView))
            <div class="flex flex-col gap-4" data-sidebar-extra>
                @include($extraContentView, $extraContentData ?? [])
            </div>
        @endif
    </div>

    @include('partials.admin_sidebar_footer', ['sidebarAuthUser' => $sidebarAuthUser ?? $authUser ?? null])
</aside>
