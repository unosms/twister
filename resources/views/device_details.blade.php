<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/><meta name="csrf-token" content="<?php echo e(csrf_token()); ?>"/><meta name="app-base" content="<?php echo e(url('/')); ?>"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Devices List | Twister Device Control</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&display=swap" rel="stylesheet"/>
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
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body { font-family: 'Inter', sans-serif; }
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
    </style>
    @include('partials.admin_sidebar_styles')
    <script src="<?php echo e(asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js'))); ?>" defer></script></head>
<body class="bg-background-light dark:bg-background-dark text-[#0d121b] dark:text-gray-100 h-screen overflow-hidden">
<div class="flex h-screen overflow-hidden">
<!-- Side Navigation -->
<?php echo $__env->make('partials.admin_sidebar', ['sidebarAuthUser' => $authUser ?? null], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<!-- Main Content Area -->
<main class="flex-1 flex flex-col overflow-y-auto">
<!-- Top Navbar -->
<header class="h-16 border-b border-[#e7ebf3] dark:border-gray-800 bg-white dark:bg-background-dark flex items-center justify-between px-8 shrink-0">
<div class="flex items-center gap-4 flex-1">
<button class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#e7ebf3] bg-white text-gray-500 hover:bg-gray-50 dark:border-gray-800 dark:bg-background-dark dark:hover:bg-gray-800" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
<span class="material-symbols-outlined">menu</span>
</button>
<form class="relative w-full max-w-md" method="GET" action="<?php echo e(route('devices.details')); ?>">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">search</span>
<input class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-lg pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-primary/20" placeholder="Search devices by name, ID or location..." type="search" name="search" value="<?php echo e($searchTerm ?? ''); ?>" autocomplete="off" data-live-search data-live-search-target="[data-device-row]" data-live-search-open-details="true" data-live-search-suggest-endpoint="<?php echo e(route('devices.suggest')); ?>" data-live-search-suggest-min-length="1"/>
</form>
</div>
<div class="flex items-center gap-4">
<div class="relative">
<button class="relative p-2 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg" type="button" data-no-dispatch="true" data-notifications-menu-button data-notifications-endpoint="<?php echo e(route('notifications.menu')); ?>">
<span class="material-symbols-outlined">notifications</span>
<span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-red-500 hidden" data-notifications-indicator></span>
</button>
<?php echo $__env->make('partials.notifications_menu', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
<a class="p-2 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg" href="<?php echo e(route('support.index')); ?>">
<span class="material-symbols-outlined">help_outline</span>
</a>
</div>
</header>
<div class="flex-1 flex">
<section class="flex-1 flex flex-col p-8">
<div class="flex flex-wrap justify-between items-end gap-3 mb-8">
<div class="flex flex-col gap-1">
<h2 class="text-3xl font-bold tracking-tight">Devices List</h2>
<p class="text-gray-500 text-sm">
All device metrics and access details for <?php echo e($totalDevices ?? 0); ?> devices
(<span class="text-green-600 font-medium"><?php echo e($activeDevices ?? 0); ?> active</span>)
</p>
</div>
</div>
<div class="bg-white dark:bg-gray-900 border border-[#cfd7e7] dark:border-gray-800 rounded-xl overflow-hidden shadow-sm p-4 space-y-4">
<?php
$devicesPage = collect($devices);
$searchTermNormalized = trim((string) ($searchTerm ?? request()->query('search', '')));
$searchActive = $searchTermNormalized !== '';

$deviceGroups = [
    'router_board' => collect(),
    'switches' => collect(),
    'fiber_optic' => collect(),
    'wireless' => collect(),
    'servers_standalone' => collect(),
    'servers_virtual' => collect(),
    'other' => collect(),
];

foreach ($devicesPage as $device) {
    $type = strtoupper((string) ($device->type ?? ''));

    if ($type === 'MIKROTIK') {
        $deviceGroups['router_board']->push($device);
        continue;
    }

    if ($type === 'CISCO') {
        $deviceGroups['switches']->push($device);
        continue;
    }

    if ($type === 'OLT') {
        $deviceGroups['fiber_optic']->push($device);
        continue;
    }

    if ($type === 'MIMOSA') {
        $deviceGroups['wireless']->push($device);
        continue;
    }

    if ($type === 'SERVER') {
        $serverType = strtolower((string) data_get($device->metadata, 'server.server_type', 'virtual_server'));
        if ($serverType === 'stand_alone_server') {
            $deviceGroups['servers_standalone']->push($device);
        } else {
            $deviceGroups['servers_virtual']->push($device);
        }
        continue;
    }

    $deviceGroups['other']->push($device);
}

$serverTotal = $deviceGroups['servers_standalone']->count() + $deviceGroups['servers_virtual']->count();

$sectionPerPage = max(1, (int) $devicesPage->count());
$sectionPageKeys = [
    'router_board' => 'router_page',
    'switches' => 'switches_page',
    'fiber_optic' => 'fiber_page',
    'wireless' => 'wireless_page',
    'servers_standalone' => 'server_standalone_page',
    'servers_virtual' => 'server_virtual_page',
    'other' => 'other_page',
];
$sectionPaginators = [];
foreach ($sectionPageKeys as $sectionKey => $pageKey) {
    $sectionItems = $deviceGroups[$sectionKey] ?? collect();
    if (!($sectionItems instanceof \Illuminate\Support\Collection)) {
        $sectionItems = collect($sectionItems);
    }

    $requestedPage = (int) request()->query($pageKey, 1);
    $currentPage = $requestedPage > 0 ? $requestedPage : 1;
    $totalItems = $sectionItems->count();
    $lastPage = max(1, (int) ceil($totalItems / $sectionPerPage));
    if ($currentPage > $lastPage) {
        $currentPage = $lastPage;
    }

    $sectionPaginators[$sectionKey] = new \Illuminate\Pagination\LengthAwarePaginator(
        $sectionItems->forPage($currentPage, $sectionPerPage)->values(),
        $totalItems,
        $sectionPerPage,
        $currentPage,
        [
            'path' => request()->url(),
            'pageName' => $pageKey,
            'query' => request()->query(),
        ]
    );
}

$validOpenGroups = array_keys($sectionPageKeys);
$activeOpenGroup = trim((string) request()->query('open_group', ''));
if (!in_array($activeOpenGroup, $validOpenGroups, true)) {
    $activeOpenGroup = '';
}
if (!$searchActive && $activeOpenGroup === '') {
    foreach ($sectionPageKeys as $sectionKey => $pageKey) {
        if ((int) request()->query($pageKey, 1) > 1) {
            $activeOpenGroup = $sectionKey;
            break;
        }
    }
}
$openAttr = static function (bool $isOpen): string {
    return $isOpen ? ' open' : '';
};
$routerOpen = $searchActive ? $deviceGroups['router_board']->count() > 0 : $activeOpenGroup === 'router_board';
$switchesOpen = $searchActive ? $deviceGroups['switches']->count() > 0 : $activeOpenGroup === 'switches';
$fiberOpen = $searchActive ? $deviceGroups['fiber_optic']->count() > 0 : $activeOpenGroup === 'fiber_optic';
$wirelessOpen = $searchActive ? $deviceGroups['wireless']->count() > 0 : $activeOpenGroup === 'wireless';
$serversOpen = $searchActive
    ? $serverTotal > 0
    : in_array($activeOpenGroup, ['servers_standalone', 'servers_virtual'], true);
$standaloneServersOpen = $searchActive
    ? $deviceGroups['servers_standalone']->count() > 0
    : $activeOpenGroup === 'servers_standalone';
$virtualServersOpen = $searchActive
    ? $deviceGroups['servers_virtual']->count() > 0
    : $activeOpenGroup === 'servers_virtual';
$otherOpen = $searchActive ? $deviceGroups['other']->count() > 0 : $activeOpenGroup === 'other';
?>

<?php if($searchActive && $devicesPage->count() === 0): ?>
<div class="rounded-lg border border-dashed border-[#cfd7e7] dark:border-gray-700 px-4 py-6 text-sm text-gray-500 dark:text-gray-300">
No devices matched "<?php echo e($searchTermNormalized); ?>".
</div>
<?php endif; ?>

<details class="group border border-[#d9e2f2] dark:border-gray-800 rounded-lg overflow-hidden"<?php echo $openAttr($routerOpen); ?>>
<summary class="flex items-center justify-between px-4 py-3 cursor-pointer bg-slate-50/80 dark:bg-gray-800/60">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-[18px] text-primary">router</span>
<span class="text-sm font-semibold">Router Board</span>
<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-primary/10 text-primary"><?php echo e($deviceGroups['router_board']->count()); ?></span>
</div>
<span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="p-3 border-t border-[#e7ebf3] dark:border-gray-800">
<?php echo $__env->make('partials.device_details_table', ['groupDevices' => collect($sectionPaginators['router_board']->items()), 'emptyMessage' => 'No Router Board devices.', 'credentialMode' => 'username_password', 'rowStart' => max(1, (int) ($sectionPaginators['router_board']->firstItem() ?? 1))], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php echo $__env->make('partials.device_section_pager', ['paginator' => $sectionPaginators['router_board'], 'pageKey' => 'router_page', 'openGroupKey' => 'router_board'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
</details>

<details class="group border border-[#d9e2f2] dark:border-gray-800 rounded-lg overflow-hidden"<?php echo $openAttr($switchesOpen); ?>>
<summary class="flex items-center justify-between px-4 py-3 cursor-pointer bg-slate-50/80 dark:bg-gray-800/60">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-[18px] text-primary">hub</span>
<span class="text-sm font-semibold">Switches</span>
<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-primary/10 text-primary"><?php echo e($deviceGroups['switches']->count()); ?></span>
</div>
<span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="p-3 border-t border-[#e7ebf3] dark:border-gray-800">
<?php echo $__env->make('partials.device_details_table', ['groupDevices' => collect($sectionPaginators['switches']->items()), 'emptyMessage' => 'No switch devices.', 'credentialMode' => 'password_enable', 'rowStart' => max(1, (int) ($sectionPaginators['switches']->firstItem() ?? 1))], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php echo $__env->make('partials.device_section_pager', ['paginator' => $sectionPaginators['switches'], 'pageKey' => 'switches_page', 'openGroupKey' => 'switches'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
</details>

<details class="group border border-[#d9e2f2] dark:border-gray-800 rounded-lg overflow-hidden"<?php echo $openAttr($fiberOpen); ?>>
<summary class="flex items-center justify-between px-4 py-3 cursor-pointer bg-slate-50/80 dark:bg-gray-800/60">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-[18px] text-primary">settings_input_hdmi</span>
<span class="text-sm font-semibold">Fiber Optic</span>
<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-primary/10 text-primary"><?php echo e($deviceGroups['fiber_optic']->count()); ?></span>
</div>
<span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="p-3 border-t border-[#e7ebf3] dark:border-gray-800">
<?php echo $__env->make('partials.device_details_table', ['groupDevices' => collect($sectionPaginators['fiber_optic']->items()), 'emptyMessage' => 'No fiber optic devices.', 'credentialMode' => 'username_password', 'rowStart' => max(1, (int) ($sectionPaginators['fiber_optic']->firstItem() ?? 1))], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php echo $__env->make('partials.device_section_pager', ['paginator' => $sectionPaginators['fiber_optic'], 'pageKey' => 'fiber_page', 'openGroupKey' => 'fiber_optic'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
</details>

<details class="group border border-[#d9e2f2] dark:border-gray-800 rounded-lg overflow-hidden"<?php echo $openAttr($wirelessOpen); ?>>
<summary class="flex items-center justify-between px-4 py-3 cursor-pointer bg-slate-50/80 dark:bg-gray-800/60">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-[18px] text-primary">wifi</span>
<span class="text-sm font-semibold">Wireless</span>
<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-primary/10 text-primary"><?php echo e($deviceGroups['wireless']->count()); ?></span>
</div>
<span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="p-3 border-t border-[#e7ebf3] dark:border-gray-800">
<?php echo $__env->make('partials.device_details_table', ['groupDevices' => collect($sectionPaginators['wireless']->items()), 'emptyMessage' => 'No wireless devices.', 'credentialMode' => 'username_password', 'rowStart' => max(1, (int) ($sectionPaginators['wireless']->firstItem() ?? 1))], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php echo $__env->make('partials.device_section_pager', ['paginator' => $sectionPaginators['wireless'], 'pageKey' => 'wireless_page', 'openGroupKey' => 'wireless'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
</details>

<details class="group border border-[#d9e2f2] dark:border-gray-800 rounded-lg overflow-hidden"<?php echo $openAttr($serversOpen); ?>>
<summary class="flex items-center justify-between px-4 py-3 cursor-pointer bg-slate-50/80 dark:bg-gray-800/60">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-[18px] text-primary">dns</span>
<span class="text-sm font-semibold">Servers</span>
<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-primary/10 text-primary"><?php echo e($serverTotal); ?></span>
</div>
<span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="p-3 border-t border-[#e7ebf3] dark:border-gray-800 space-y-3">
<details class="group border border-[#e3e9f6] dark:border-gray-800 rounded-lg overflow-hidden"<?php echo $openAttr($standaloneServersOpen); ?>>
<summary class="flex items-center justify-between px-3 py-2.5 cursor-pointer bg-white dark:bg-gray-900">
<div class="flex items-center gap-2">
<span class="text-xs font-semibold text-slate-700 dark:text-gray-200">Stand Alone Server</span>
<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-700 dark:bg-gray-800 dark:text-gray-200"><?php echo e($deviceGroups['servers_standalone']->count()); ?></span>
</div>
<span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="p-3 border-t border-[#e7ebf3] dark:border-gray-800">
<?php echo $__env->make('partials.device_details_table', ['groupDevices' => collect($sectionPaginators['servers_standalone']->items()), 'emptyMessage' => 'No stand alone servers.', 'credentialMode' => 'username_password', 'rowStart' => max(1, (int) ($sectionPaginators['servers_standalone']->firstItem() ?? 1))], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php echo $__env->make('partials.device_section_pager', ['paginator' => $sectionPaginators['servers_standalone'], 'pageKey' => 'server_standalone_page', 'openGroupKey' => 'servers_standalone'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
</details>

<details class="group border border-[#e3e9f6] dark:border-gray-800 rounded-lg overflow-hidden"<?php echo $openAttr($virtualServersOpen); ?>>
<summary class="flex items-center justify-between px-3 py-2.5 cursor-pointer bg-white dark:bg-gray-900">
<div class="flex items-center gap-2">
<span class="text-xs font-semibold text-slate-700 dark:text-gray-200">Virtual Server</span>
<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-700 dark:bg-gray-800 dark:text-gray-200"><?php echo e($deviceGroups['servers_virtual']->count()); ?></span>
</div>
<span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="p-3 border-t border-[#e7ebf3] dark:border-gray-800">
<?php echo $__env->make('partials.device_details_table', ['groupDevices' => collect($sectionPaginators['servers_virtual']->items()), 'emptyMessage' => 'No virtual servers.', 'credentialMode' => 'username_password', 'rowStart' => max(1, (int) ($sectionPaginators['servers_virtual']->firstItem() ?? 1))], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php echo $__env->make('partials.device_section_pager', ['paginator' => $sectionPaginators['servers_virtual'], 'pageKey' => 'server_virtual_page', 'openGroupKey' => 'servers_virtual'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
</details>
</div>
</details>

<?php if($deviceGroups['other']->count() > 0): ?>
<details class="group border border-[#d9e2f2] dark:border-gray-800 rounded-lg overflow-hidden"<?php echo $openAttr($otherOpen); ?>>
<summary class="flex items-center justify-between px-4 py-3 cursor-pointer bg-slate-50/80 dark:bg-gray-800/60">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-[18px] text-primary">inventory_2</span>
<span class="text-sm font-semibold">Other</span>
<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-primary/10 text-primary"><?php echo e($deviceGroups['other']->count()); ?></span>
</div>
<span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="p-3 border-t border-[#e7ebf3] dark:border-gray-800">
<?php echo $__env->make('partials.device_details_table', ['groupDevices' => collect($sectionPaginators['other']->items()), 'emptyMessage' => 'No uncategorized devices.', 'credentialMode' => 'password_enable', 'rowStart' => max(1, (int) ($sectionPaginators['other']->firstItem() ?? 1))], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php echo $__env->make('partials.device_section_pager', ['paginator' => $sectionPaginators['other'], 'pageKey' => 'other_page', 'openGroupKey' => 'other'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
</details>
<?php endif; ?>
</div>
</div>
</section>
</div>
</main>
</div>
</body></html>



<?php /**PATH C:\xampp\htdocs\Laravel\resources\views/device_details.blade.php ENDPATH**/ ?>











