<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/><meta name="csrf-token" content="<?php echo e(csrf_token()); ?>"/><meta name="app-base" content="<?php echo e(url('/')); ?>"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>User Management - Device Control Manager</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
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
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
    <?php echo $__env->make('partials.admin_sidebar_styles', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <script src="<?php echo e(asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js'))); ?>" defer></script></head>
<body class="bg-background-light dark:bg-background-dark text-[#0d121b] dark:text-white h-screen overflow-hidden flex flex-col font-display" data-user-form-checkbox-filters="1">
<!-- Top Navigation Bar -->
<header class="flex items-center justify-between whitespace-nowrap border-b border-solid border-[#e7ebf3] dark:border-gray-800 bg-white dark:bg-background-dark px-10 py-3 sticky top-0 z-30">
<div class="flex items-center gap-3">
<button class="h-10 w-10 flex items-center justify-center rounded-lg border border-[#e7ebf3] dark:border-gray-800 bg-white dark:bg-background-dark hover:bg-gray-50 dark:hover:bg-gray-800" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
<span class="material-symbols-outlined text-[#4c669a] dark:text-gray-400">menu</span>
</button>
<div class="flex items-center gap-4 text-[#0d121b] dark:text-white">
<div class="size-6 text-primary">
<svg fill="currentColor" viewbox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
<path d="M44 4H30.6666V17.3334H17.3334V30.6666H4V44H44V4Z"></path>
</svg>
</div>
<h2 class="text-[#0d121b] dark:text-white text-lg font-bold leading-tight tracking-[-0.015em]">Device Control Manager</h2>
</div>
<label class="flex flex-col min-w-40 !h-10 max-w-64">
<div class="flex w-full flex-1 items-stretch rounded-lg h-full bg-[#e7ebf3] dark:bg-gray-800">
<div class="text-[#4c669a] dark:text-gray-400 flex border-none items-center justify-center pl-4 rounded-l-lg" data-icon="search">
<span class="material-symbols-outlined">search</span>
</div>
<input class="form-input flex w-full min-w-0 flex-1 resize-none overflow-hidden rounded-lg text-[#0d121b] dark:text-white focus:outline-0 focus:ring-0 border-none bg-transparent placeholder:text-[#4c669a] dark:placeholder:text-gray-500 px-4 rounded-l-none pl-2 text-base font-normal" placeholder="Search users..." value="" data-live-search data-live-search-target="[data-user-table-row]"/>
</div>
</label>
</div>
<div class="flex flex-1 justify-end gap-6 items-center">
<?php if($canManageUserIdentity ?? false): ?>
<a href="<?php echo e(route('users.create')); ?>" class="flex min-w-[84px] max-w-[480px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-10 px-4 bg-primary text-white text-sm font-bold leading-normal tracking-[0.015em] hover:bg-primary/90 transition-colors">
<span class="truncate">Create User</span>
</a>
<?php else: ?>
<span class="flex min-w-[84px] max-w-[480px] items-center justify-center overflow-hidden rounded-lg h-10 px-4 bg-slate-100 text-slate-400 text-sm font-bold leading-normal tracking-[0.015em] cursor-not-allowed dark:bg-slate-800 dark:text-slate-500">Create User</span>
<?php endif; ?>
<div class="flex items-center gap-2">
<div class="relative">
<button class="relative flex h-10 w-10 items-center justify-center rounded-lg text-[#4c669a] dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800" type="button" data-no-dispatch="true" data-notifications-menu-button data-notifications-endpoint="<?php echo e(route('notifications.menu')); ?>">
<span class="material-symbols-outlined">notifications</span>
<span class="absolute top-2.5 right-2.5 h-2 w-2 rounded-full bg-red-500 hidden" data-notifications-indicator></span>
</button>
<?php echo $__env->make('partials.notifications_menu', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
<?php echo $__env->make('partials.user_avatar', ['user' => $authUser ?? null, 'name' => $authUser->name ?? 'Admin', 'sizeClass' => 'h-9 w-9', 'textClass' => 'text-xs'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
</div>
</header>
<div class="flex flex-1 overflow-hidden relative">
<!-- Sidebar Navigation -->
<?php echo $__env->make('partials.admin_sidebar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<!-- Main Content Area -->
<main class="flex-1 overflow-y-auto bg-background-light dark:bg-background-dark relative">
<div class="max-w-6xl mx-auto p-8">
<!-- Page Heading -->
<div class="flex flex-wrap justify-between items-end gap-3 mb-8">
<div class="flex flex-col gap-1">
<p class="text-[#0d121b] dark:text-white text-3xl font-black leading-tight tracking-[-0.033em]">User Management</p>
<p class="text-[#4c669a] dark:text-gray-400 text-base font-normal">Manage system users, roles, and device access permissions.</p>
</div>
<div class="flex gap-3">
<form id="user-export-form" method="POST" action="<?php echo e(route('users.export')); ?>">
<?php echo csrf_field(); ?>
<input type="hidden" name="search" value="<?php echo e($filters['search'] ?? ''); ?>"/>
<input type="hidden" name="role" value="<?php echo e($filters['role'] ?? 'all'); ?>"/>
<input type="hidden" name="status" value="<?php echo e($filters['status'] ?? 'all'); ?>"/>
<button class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-[#cfd7e7] dark:border-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors" type="submit">
<span class="material-symbols-outlined text-base">download</span>
Export
</button>
</form>
<a class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-[#cfd7e7] dark:border-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors" href="<?php echo e(route('users.index')); ?>">
<span class="material-symbols-outlined text-base">refresh</span>
                            Refresh
                        </a>
</div>
</div>
<form id="user-filters" class="flex flex-wrap items-center gap-3 mb-6" method="GET" action="<?php echo e(route('users.index')); ?>">
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">search</span>
<input class="pl-10 pr-4 py-2 bg-white dark:bg-gray-800 border border-[#cfd7e7] dark:border-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-primary/50" name="search" placeholder="Search users" type="text" value="<?php echo e($filters['search'] ?? ''); ?>"/>
</div>
<div class="relative">
<select class="pl-3 pr-8 py-2 bg-white dark:bg-gray-800 border border-[#cfd7e7] dark:border-gray-700 rounded-lg text-sm appearance-none" name="role">
<option value="all">Role: All</option>
<option value="admin" <?php if(($filters['role'] ?? '') === 'admin'): echo 'selected'; endif; ?>>Role: Admin</option>
<option value="user" <?php if(($filters['role'] ?? '') === 'user'): echo 'selected'; endif; ?>>Role: User</option>
</select>
<span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 text-base">expand_more</span>
</div>
<div class="relative">
<select class="pl-3 pr-8 py-2 bg-white dark:bg-gray-800 border border-[#cfd7e7] dark:border-gray-700 rounded-lg text-sm appearance-none" name="status">
<option value="all">Status: All</option>
<option value="active" <?php if(($filters['status'] ?? '') === 'active'): echo 'selected'; endif; ?>>Status: Active</option>
<option value="inactive" <?php if(($filters['status'] ?? '') === 'inactive'): echo 'selected'; endif; ?>>Status: Inactive</option>
</select>
<span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 text-base">expand_more</span>
</div>
<button class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold" type="submit">Apply</button>
<a class="text-sm font-semibold text-slate-500 hover:text-primary" href="<?php echo e(route('users.index')); ?>">Clear</a>
</form><?php if(session('status')): ?>
<div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
<?php echo e(session('status')); ?>

</div>
<?php endif; ?>
<?php if($errors->any()): ?>
<div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
<p class="font-semibold">Could not save user changes</p>
<ul class="mt-2 list-disc pl-5 space-y-1">
<?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<li><?php echo e($error); ?></li>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</ul>
</div>
<?php endif; ?>
<!-- Table Container -->
<div class="bg-white dark:bg-gray-900 rounded-xl border border-[#cfd7e7] dark:border-gray-800 shadow-sm overflow-hidden">
<div class="overflow-x-auto">
<table class="min-w-[980px] w-full text-left border-collapse">
<thead>
<tr class="bg-gray-50 dark:bg-gray-800/50 border-b border-[#cfd7e7] dark:border-gray-800">
<th class="px-6 py-4 text-[#0d121b] dark:text-gray-200 text-xs font-bold uppercase tracking-wider">User</th>
<th class="px-6 py-4 text-[#0d121b] dark:text-gray-200 text-xs font-bold uppercase tracking-wider">Username</th>
<th class="px-6 py-4 text-[#0d121b] dark:text-gray-200 text-xs font-bold uppercase tracking-wider">Role</th>
<th class="px-6 py-4 text-[#0d121b] dark:text-gray-200 text-xs font-bold uppercase tracking-wider">Status</th>
<th class="px-6 py-4 text-[#0d121b] dark:text-gray-200 text-xs font-bold uppercase tracking-wider text-center">Devices</th>
<th class="px-6 py-4 text-[#4c669a] dark:text-gray-400 text-xs font-bold uppercase tracking-wider text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-[#cfd7e7] dark:divide-gray-800">
<?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<?php
$role = $user->role ?? 'user';
$currentAuthUserId = session('auth.user_id');
$isCurrentAuthUser = is_numeric($currentAuthUserId) && (int) $currentAuthUserId === (int) $user->id;
$isSuperAdmin = $user->isSuperAdmin();
$canEditProtectedSelf = $isSuperAdmin && $isCurrentAuthUser;
$isEditTarget = (int) ($editUserId ?? 0) === (int) $user->id;
$editQuery = request()->query();
$editQuery['edit_user'] = (int) $user->id;
$closeQuery = request()->query();
unset($closeQuery['edit_user']);
$editUrl = route('users.index', $editQuery);
$closeUrl = route('users.index', $closeQuery);
$roleClass = $isSuperAdmin
    ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-200'
    : (($role === 'admin')
    ? 'bg-primary/10 text-primary'
    : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300');
$roleLabel = $isSuperAdmin ? 'Super Admin' : ucfirst($role);
?>
<tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors" data-user-table-row>
<td class="px-6 py-4">
<div class="flex items-center gap-3">
<?php echo $__env->make('partials.user_avatar', ['user' => $user, 'name' => $user->name, 'sizeClass' => 'h-10 w-10', 'textClass' => 'text-xs'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<span class="text-[#0d121b] dark:text-white font-semibold text-sm"><?php echo e($user->name); ?></span>
</div>
</td>
<td class="px-6 py-4 text-[#4c669a] dark:text-gray-400 text-sm"><?php echo e($user->name); ?></td>
<td class="px-6 py-4">
<span class="inline-flex px-2.5 py-1 rounded-full <?php echo e($roleClass); ?> text-xs font-bold"><?php echo e($roleLabel); ?></span>
</td>
<?php
$status = $user->status ?? 'active';
$isActive = $status === 'active';
$statusClass = $isActive
    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
    : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400';
?>
<td class="px-6 py-4">
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full <?php echo e($statusClass); ?> text-xs font-bold">
<span class="w-1.5 h-1.5 rounded-full <?php echo e($isActive ? 'bg-green-500' : 'bg-gray-400'); ?>"></span>
<?php echo e(ucfirst($status)); ?>

</span>
</td>
<td class="px-6 py-4 text-center text-[#0d121b] dark:text-white font-medium text-sm"><?php echo e($user->devices_count ?? 0); ?></td>
<td class="px-6 py-4 text-right">
<div class="inline-flex flex-wrap items-center justify-end gap-2">
<?php if($isSuperAdmin): ?>
<?php if($canEditProtectedSelf): ?>
<a class="text-primary hover:text-primary/70 font-bold text-sm" href="<?php echo e($editUrl); ?>">Edit</a>
<?php endif; ?>
<span class="px-3 py-1.5 text-xs font-semibold text-amber-700 border border-amber-200 rounded-lg bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-200">Protected Super Admin</span>
<?php if($isCurrentAuthUser): ?>
<span class="px-3 py-1.5 text-xs font-semibold text-slate-400 border border-slate-200 rounded-lg cursor-not-allowed">Current Account</span>
<?php endif; ?>
<?php else: ?>
<a class="text-primary hover:text-primary/70 font-bold text-sm" href="<?php echo e($editUrl); ?>">Edit</a>
<?php if($role !== 'admin'): ?>
<form method="POST" action="<?php echo e(route('users.status')); ?>" class="inline">
<?php echo csrf_field(); ?>
<input type="hidden" name="user_id" value="<?php echo e($user->id); ?>"/>
<input type="hidden" name="status" value="<?php echo e($isActive ? 'inactive' : 'active'); ?>"/>
<button class="px-3 py-1.5 text-xs font-semibold <?php echo e($isActive ? 'text-gray-600 border-gray-200 hover:bg-gray-50' : 'text-green-700 border-green-200 hover:bg-green-50'); ?> border rounded-lg" type="submit">
<?php echo e($isActive ? 'Deactivate' : 'Activate'); ?>

</button>
</form>
<?php endif; ?>
<?php if(!$isCurrentAuthUser): ?>
<form method="POST" action="<?php echo e(route('users.delete', $user)); ?>" class="inline" onsubmit="return confirm('Delete user <?php echo e($user->name); ?>? This cannot be undone.');">
<?php echo csrf_field(); ?>
<button class="px-3 py-1.5 text-xs font-semibold text-red-600 border border-red-200 rounded-lg hover:bg-red-50" type="submit">Delete</button>
</form>
<?php else: ?>
<span class="px-3 py-1.5 text-xs font-semibold text-slate-400 border border-slate-200 rounded-lg cursor-not-allowed">Current Account</span>
<?php endif; ?>
<?php endif; ?>
</div>
</td>
</tr>
<?php if((!$isSuperAdmin || $canEditProtectedSelf) && $isEditTarget): ?>
<tr class="bg-gray-50/60 dark:bg-gray-900/40" data-user-edit-row="<?php echo e($user->id); ?>">
<td class="px-6 py-4" colspan="6">
<form class="space-y-6" method="POST" action="<?php echo e(route('users.update', $user)); ?>" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<?php
$storedCurrentPassword = null;
if ($canManageUserIdentity ?? false) {
    $storedCurrentPassword = $user->currentPasswordRevealValue();
}
$assignedDeviceIds = $devices->where('assigned_user_id', $user->id)->pluck('id')->map(static fn ($id) => (int) $id)->all();
$selectedPermissionDeviceIds = old('device_permission_ids', $user->permittedDevices->pluck('id')->all());
if (!is_array($selectedPermissionDeviceIds)) {
    $selectedPermissionDeviceIds = [];
}
$selectedPermissionDeviceIds = array_values(array_unique(array_map(
    'intval',
    array_filter($selectedPermissionDeviceIds, static fn ($id): bool => is_numeric($id))
)));

$permissionPortMap = [];
foreach ($user->permittedDevices as $permittedDevice) {
    $permissionPortMap[(int) $permittedDevice->id] = trim((string) ($permittedDevice->pivot->allowed_ports ?? ''));
}

$permissionCommandTemplateMap = [];
if (($deviceCommandRestrictionsReady ?? false) && \App\Models\DevicePermission::supportsAllowedCommandTemplateIds()) {
    $storedPermissionCommandTemplateMap = \Illuminate\Support\Facades\DB::table('device_permissions')
        ->where('user_id', $user->id)
        ->pluck('allowed_command_template_ids', 'device_id')
        ->all();

    foreach ($storedPermissionCommandTemplateMap as $deviceId => $value) {
        if (!is_numeric($deviceId)) {
            continue;
        }

        $permissionCommandTemplateMap[(int) $deviceId] = \App\Models\DevicePermission::decodeAllowedCommandTemplateIds($value);
    }
}

$oldPermissionPortMap = old('device_permission_ports');
if (is_array($oldPermissionPortMap)) {
    foreach ($oldPermissionPortMap as $deviceId => $value) {
        if (!is_numeric($deviceId)) {
            continue;
        }
        $permissionPortMap[(int) $deviceId] = trim((string) $value);
    }
}

$permissionPortSelectedLookupMap = [];
foreach ($permissionPortMap as $deviceId => $expression) {
    $tokens = preg_split('/\s*,\s*/', trim((string) $expression)) ?: [];
    $lookup = [];
    foreach ($tokens as $token) {
        $token = strtolower(trim((string) $token));
        if ($token !== '') {
            $lookup[$token] = true;
        }
    }
    $permissionPortSelectedLookupMap[(int) $deviceId] = $lookup;
}

$oldPermissionCommandTemplateMap = old('device_permission_command_template_ids');
if (is_array($oldPermissionCommandTemplateMap)) {
    foreach ($oldPermissionCommandTemplateMap as $deviceId => $templateIds) {
        if (!is_numeric($deviceId) || !is_array($templateIds)) {
            continue;
        }

        $permissionCommandTemplateMap[(int) $deviceId] = array_values(array_unique(array_map(
            'intval',
            array_filter($templateIds, static fn ($id): bool => is_numeric($id))
        )));
    }
}

$selectedTelegramDevices = old('telegram_devices', $user->telegram_devices ?? []);
if (!is_array($selectedTelegramDevices) || empty($selectedTelegramDevices)) {
    $selectedTelegramDevices = $assignedDeviceIds;
}
$selectedTelegramDevices = array_values(array_unique(array_map(
    'intval',
    array_filter($selectedTelegramDevices, static fn ($id): bool => is_numeric($id))
)));
$telegramDeviceInterfaceScopeReady = (bool) ($telegramDeviceInterfaceScopeReady ?? false);
$telegramDeviceInterfacesMap = old('telegram_device_interfaces', $user->telegram_device_interfaces ?? []);
if (!is_array($telegramDeviceInterfacesMap)) {
    $telegramDeviceInterfacesMap = [];
}

$severityOptions = $telegramSeverityOptions ?? ['info', 'average', 'high', 'disaster'];
$severityOptions = array_values(array_unique(array_map(
    static fn ($value): string => strtolower(trim((string) $value)),
    (array) $severityOptions
)));
$selectedTelegramSeverities = old('telegram_severities', $user->telegram_severities ?? ['high', 'disaster']);
if (!is_array($selectedTelegramSeverities) || empty($selectedTelegramSeverities)) {
    $selectedTelegramSeverities = ['high', 'disaster'];
}
$selectedTelegramSeverities = array_values(array_unique(array_map(
    static fn ($value): string => strtolower(trim((string) $value)),
    $selectedTelegramSeverities
)));
$severityOptionLookup = array_fill_keys($severityOptions, true);
$selectedTelegramSeverities = array_values(array_filter(
    $selectedTelegramSeverities,
    static fn ($value): bool => isset($severityOptionLookup[$value])
));

$eventTypeOptions = $telegramEventTypeOptions ?? ['device_down', 'link_down', 'link_up', 'speed_changed', 'device_up'];
$eventTypeOptions = array_values(array_unique(array_map(
    static fn ($value): string => strtolower(trim((string) $value)),
    (array) $eventTypeOptions
)));
$selectedTelegramEventTypes = old('telegram_event_types', $user->telegram_event_types ?? ['device_down', 'link_down']);
if (!is_array($selectedTelegramEventTypes) || empty($selectedTelegramEventTypes)) {
    $selectedTelegramEventTypes = ['device_down', 'link_down'];
}
$selectedTelegramEventTypes = array_values(array_unique(array_map(
    static fn ($value): string => strtolower(trim((string) $value)),
    $selectedTelegramEventTypes
)));
$eventTypeOptionLookup = array_fill_keys($eventTypeOptions, true);
$selectedTelegramEventTypes = array_values(array_filter(
    $selectedTelegramEventTypes,
    static fn ($value): bool => isset($eventTypeOptionLookup[$value])
));
$selectedCommandTemplateIds = old('command_template_ids', $user->commandTemplates->pluck('id')->all());
if (!is_array($selectedCommandTemplateIds)) {
    $selectedCommandTemplateIds = [];
}
$selectedCommandTemplateIds = array_values(array_unique(array_map(
    'intval',
    array_filter($selectedCommandTemplateIds, static fn ($id): bool => is_numeric($id))
)));
$selectedCommandTemplateLookup = array_fill_keys($selectedCommandTemplateIds, true);
$customCommandType = old('custom_command_type', '');
$customCommandScriptName = old('custom_command_script_name', '');
$customCommandScriptCode = old('custom_command_script_code', '');
$showCustomCommandFields = $customCommandType === 'custom';
$assignedDeviceCount = count($assignedDeviceIds);
$commandDeviceCount = count($selectedPermissionDeviceIds);
$commandTemplateCount = count($selectedCommandTemplateIds);
$telegramEnabled = (bool) old('telegram_enabled', (bool) ($user->telegram_enabled ?? false));
$canViewAssignedDeviceGraphs = (bool) old(
    'can_view_assigned_device_graphs',
    (bool) ($user->can_view_assigned_device_graphs ?? false)
);
$canViewAssignedDeviceEvents = (bool) old(
    'can_view_assigned_device_events',
    (bool) ($user->can_view_assigned_device_events ?? false)
);
$assignedDeviceGraphAccessReady = (bool) ($assignedDeviceGraphAccessReady ?? false);
$assignedDeviceEventAccessReady = (bool) ($assignedDeviceEventAccessReady ?? false);
$deviceGraphScopeReady = (bool) ($deviceGraphScopeReady ?? false);
$deviceEventScopeReady = (bool) ($deviceEventScopeReady ?? false);
$deviceEventInterfaceScopeReady = (bool) ($deviceEventInterfaceScopeReady ?? false);
$graphInterfaceOptionsByDevice = is_array($graphInterfaceOptionsByDevice ?? null) ? $graphInterfaceOptionsByDevice : [];
$selectedEventDeviceIds = [];
$eventInterfaceMap = [];
if ($deviceEventScopeReady && \App\Models\DeviceEventPermission::supportsScopedAccess()) {
    $eventScopeColumns = ['device_id'];
    if ($deviceEventInterfaceScopeReady) {
        $eventScopeColumns[] = 'allowed_interfaces';
    }

    $storedEventScopeRows = \Illuminate\Support\Facades\DB::table('device_event_permissions')
        ->where('user_id', $user->id)
        ->get($eventScopeColumns);

    foreach ($storedEventScopeRows as $row) {
        $deviceId = (int) ($row->device_id ?? 0);
        if ($deviceId <= 0) {
            continue;
        }

        $selectedEventDeviceIds[] = $deviceId;
        if ($deviceEventInterfaceScopeReady) {
            $eventInterfaceMap[$deviceId] = trim((string) ($row->allowed_interfaces ?? ''));
        }
    }
}

$oldSelectedEventDeviceIds = old('event_device_ids');
if (is_array($oldSelectedEventDeviceIds)) {
    $selectedEventDeviceIds = array_values(array_unique(array_map(
        'intval',
        array_filter($oldSelectedEventDeviceIds, static fn ($id): bool => is_numeric($id))
    )));
}
$selectedEventDeviceIds = array_values(array_unique(array_map(
    'intval',
    array_filter($selectedEventDeviceIds, static fn ($id): bool => is_numeric($id))
)));

if ($deviceEventInterfaceScopeReady) {
    $oldEventInterfaceMap = old('event_device_interfaces');
    if (is_array($oldEventInterfaceMap)) {
        foreach ($oldEventInterfaceMap as $deviceId => $value) {
            if (!is_numeric($deviceId)) {
                continue;
            }
            $eventInterfaceMap[(int) $deviceId] = trim((string) $value);
        }
    }
}

$eventInterfaceSelectedLookupMap = [];
foreach ($eventInterfaceMap as $deviceId => $expression) {
    $tokens = preg_split('/\s*,\s*/', trim((string) $expression)) ?: [];
    $lookup = [];
    foreach ($tokens as $token) {
        $token = strtolower(trim((string) $token));
        if ($token !== '') {
            $lookup[$token] = true;
        }
    }
    $eventInterfaceSelectedLookupMap[(int) $deviceId] = $lookup;
}

$selectedGraphDeviceIds = [];
$graphInterfaceMap = [];
if ($deviceGraphScopeReady && \App\Models\DeviceGraphPermission::supportsScopedAccess()) {
    $storedGraphScopeMap = \Illuminate\Support\Facades\DB::table('device_graph_permissions')
        ->where('user_id', $user->id)
        ->pluck('allowed_interfaces', 'device_id')
        ->all();

    foreach ($storedGraphScopeMap as $deviceId => $value) {
        if (!is_numeric($deviceId)) {
            continue;
        }
        $deviceId = (int) $deviceId;
        $selectedGraphDeviceIds[] = $deviceId;
        $graphInterfaceMap[$deviceId] = trim((string) $value);
    }
}

$oldSelectedGraphDeviceIds = old('graph_device_ids');
if (is_array($oldSelectedGraphDeviceIds)) {
    $selectedGraphDeviceIds = array_values(array_unique(array_map(
        'intval',
        array_filter($oldSelectedGraphDeviceIds, static fn ($id): bool => is_numeric($id))
    )));
}

$oldGraphInterfaceMap = old('graph_device_interfaces');
if (is_array($oldGraphInterfaceMap)) {
    foreach ($oldGraphInterfaceMap as $deviceId => $value) {
        if (!is_numeric($deviceId)) {
            continue;
        }
        $graphInterfaceMap[(int) $deviceId] = trim((string) $value);
    }
}
$selectedGraphDeviceIds = array_values(array_unique(array_map(
    'intval',
    array_filter($selectedGraphDeviceIds, static fn ($id): bool => is_numeric($id))
)));
$graphInterfaceSelectedLookupMap = [];
foreach ($graphInterfaceMap as $deviceId => $expression) {
    $tokens = preg_split('/\s*,\s*/', trim((string) $expression)) ?: [];
    $lookup = [];
    foreach ($tokens as $token) {
        $token = strtolower(trim((string) $token));
        if ($token !== '') {
            $lookup[$token] = true;
        }
    }
    $graphInterfaceSelectedLookupMap[(int) $deviceId] = $lookup;
}
?>
<div class="rounded-lg border border-[#cfd7e7] dark:border-gray-700 bg-white dark:bg-gray-900/50 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
<div>
<p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Editing <?php echo e($user->name); ?></p>
<p class="text-xs text-gray-500">Update account, access, and notifications using the sections below.</p>
</div>
<div class="flex flex-wrap items-center gap-2">
<span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-200"><?php echo e($assignedDeviceCount); ?> assigned</span>
<span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-200"><?php echo e($commandDeviceCount); ?> command devices</span>
<span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-200"><?php echo e($commandTemplateCount); ?> commands</span>
<span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-bold <?php echo e($statusClass); ?>">
<span class="w-1.5 h-1.5 rounded-full <?php echo e($isActive ? 'bg-green-500' : 'bg-gray-400'); ?>"></span>
<?php echo e(ucfirst($status)); ?>
</span>
</div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
<details class="lg:col-span-12 group rounded-lg border border-[#cfd7e7] dark:border-gray-700 bg-white dark:bg-gray-800/40 p-4" data-user-edit-section data-section-id="account" open>
<summary class="list-none flex cursor-pointer items-center justify-between gap-3" data-user-edit-section-toggle>
<div>
<p class="text-xs font-bold uppercase tracking-wider text-gray-500">1) Account</p>
<p class="text-xs text-gray-400 mt-1">Identity, role, and password rotation.</p>
</div>
<span class="material-symbols-outlined text-gray-400 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="mt-4 grid grid-cols-1 lg:grid-cols-12 gap-4" data-user-edit-section-body>
<?php if($isSuperAdmin): ?>
<input type="hidden" name="username" value="<?php echo e($user->name); ?>"/>
<input type="hidden" name="role" value="<?php echo e($role); ?>"/>
<?php else: ?>
<div class="flex flex-col gap-2 lg:col-span-5">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Username</label>
<?php if($canManageUserIdentity ?? false): ?>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="username" type="text" value="<?php echo e(old('username', $user->name)); ?>" required/>
<?php else: ?>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 bg-slate-50 dark:bg-gray-800/60 dark:text-white text-slate-500 h-11 cursor-not-allowed" type="text" value="<?php echo e($user->name); ?>" readonly/>
<input type="hidden" name="username" value="<?php echo e($user->name); ?>"/>
<p class="text-xs text-amber-600 dark:text-amber-300">Only super admin can change username, role, or password.</p>
<?php endif; ?>
</div>
<div class="flex flex-col gap-2 lg:col-span-3">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Role</label>
<?php if($canManageUserIdentity ?? false): ?>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="role">
<option value="admin" <?php if($role === 'admin'): echo 'selected'; endif; ?>>Admin</option>
<option value="user" <?php if($role !== 'admin'): echo 'selected'; endif; ?>>User</option>
</select>
<?php else: ?>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 bg-slate-50 dark:bg-gray-800/60 dark:text-white text-slate-500 h-11 cursor-not-allowed" type="text" value="<?php echo e($roleLabel); ?>" readonly/>
<input type="hidden" name="role" value="<?php echo e($role); ?>"/>
<?php endif; ?>
</div>
<?php endif; ?>
<div class="flex flex-col gap-2 <?php echo e($isSuperAdmin ? 'lg:col-span-12' : 'lg:col-span-4'); ?>">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Set New Password</label>
<?php if($canManageUserIdentity ?? false): ?>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" id="new_password_<?php echo e($user->id); ?>" name="password" placeholder="Leave blank to keep current" type="password" data-new-password-input/>
<p class="text-xs text-gray-400">Use this only when rotating credentials.</p>
<?php else: ?>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 bg-slate-50 dark:bg-gray-800/60 dark:text-white text-slate-500 h-11 cursor-not-allowed" placeholder="Only super admin can change password" type="password" disabled/>
<p class="text-xs text-gray-400">Password changes are restricted to super admin.</p>
<?php endif; ?>
</div>
<div class="flex flex-col gap-2 lg:col-span-12">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300" for="current_password_view_<?php echo e($user->id); ?>">Current Password</label>
<?php if($canManageUserIdentity ?? false): ?>
<?php
$currentPasswordServerValue = trim((string) ($storedCurrentPassword ?? ''));
$hasStoredCurrentPassword = $currentPasswordServerValue !== '';
$passwordRevealStorageReady = (bool) ($passwordRevealStorageReady ?? false);
?>
<div class="relative">
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11 w-full pr-11" id="current_password_view_<?php echo e($user->id); ?>" type="password" value="<?php echo e($currentPasswordServerValue); ?>" data-current-password-display data-server-value="<?php echo e($currentPasswordServerValue); ?>" placeholder="<?php echo e($hasStoredCurrentPassword ? 'Current password' : 'Current password not available yet'); ?>" autocomplete="off" readonly/>
<button class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" type="button" data-toggle="password" data-target="#current_password_view_<?php echo e($user->id); ?>" data-no-dispatch="true" aria-pressed="false" aria-label="Toggle current password visibility">
<span class="material-symbols-outlined">visibility_off</span>
</button>
</div>
<?php if($hasStoredCurrentPassword): ?>
<p class="text-xs text-emerald-600 dark:text-emerald-300">Saved password is available for this user account.</p>
<?php elseif(!$passwordRevealStorageReady): ?>
<p class="text-xs text-amber-600 dark:text-amber-300">Password reveal storage is not enabled on this server yet. Run <code>php artisan migrate --force</code>, then set a new password once.</p>
<?php else: ?>
<p class="text-xs text-gray-400">No retrievable password stored yet for this user. Set a new password once to enable always-on viewing.</p>
<?php endif; ?>
<?php else: ?>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 bg-slate-50 dark:bg-gray-800/60 dark:text-white text-slate-500 h-11 cursor-not-allowed" type="password" placeholder="Only super admin can manage passwords" disabled/>
<p class="text-xs text-gray-400">Only super admin can view or rotate passwords.</p>
<?php endif; ?>
</div>
</div>
</details>
<details class="lg:col-span-12 group rounded-lg border border-[#cfd7e7] dark:border-gray-700 bg-white dark:bg-gray-800/40 p-4" data-user-edit-section data-section-id="device_scope" open>
<summary class="list-none flex cursor-pointer items-center justify-between gap-3" data-user-edit-section-toggle>
<div>
<p class="text-xs font-bold uppercase tracking-wider text-gray-500">2) Device Scope</p>
<p class="text-xs text-gray-400 mt-1">Assign owned devices first, then grant command-only device access.</p>
</div>
<span class="material-symbols-outlined text-gray-400 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="mt-4 grid grid-cols-1 lg:grid-cols-12 gap-4" data-user-edit-section-body>
<div class="flex flex-col gap-2 lg:col-span-6" data-checkbox-group>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Assigned Devices</label>
<span class="text-[11px] font-semibold text-gray-500"><span data-checkbox-count>0</span> selected</span>
</div>
<div class="max-h-44 overflow-y-auto rounded-lg border border-[#cfd7e7] bg-white p-3 dark:border-gray-700 dark:bg-gray-800 space-y-2">
<?php $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" name="device_ids[]" value="<?php echo e($device->id); ?>" data-checkbox-item <?php if(in_array((int) $device->id, $assignedDeviceIds, true)): echo 'checked'; endif; ?>/>
<span><?php echo e($device->name); ?> <?php if($device->serial_number): ?>(<?php echo e($device->serial_number); ?>)<?php endif; ?></span>
</label>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="invert">Invert</button>
</div>
<p class="text-xs text-gray-400">Use quick actions for faster selection.</p>
</div>
<div class="flex flex-col gap-2 lg:col-span-6" data-checkbox-group>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Command Device Access</label>
<span class="text-[11px] font-semibold text-gray-500"><span data-checkbox-count>0</span> selected</span>
</div>
<div class="max-h-44 overflow-y-auto rounded-lg border border-[#cfd7e7] bg-white p-3 dark:border-gray-700 dark:bg-gray-800 space-y-2">
<?php $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" name="device_permission_ids[]" value="<?php echo e($device->id); ?>" data-checkbox-item data-device-permission-checkbox <?php if(in_array((int) $device->id, $selectedPermissionDeviceIds, true)): echo 'checked'; endif; ?>/>
<span><?php echo e($device->name); ?> <?php if($device->serial_number): ?>(<?php echo e($device->serial_number); ?>)<?php endif; ?></span>
</label>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="invert">Invert</button>
</div>
<p class="text-xs text-gray-400">Grant command access to devices even if assigned to another user.</p>
</div>
<div class="flex flex-col gap-2 lg:col-span-12 order-12">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Event Access</label>
<?php if($assignedDeviceEventAccessReady): ?>
<label class="inline-flex items-center gap-2 rounded-lg border border-[#cfd7e7] bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" name="can_view_assigned_device_events" value="1" <?php if($canViewAssignedDeviceEvents): echo 'checked'; endif; ?> />
<span>Enable event visibility for this user account.</span>
</label>
<p class="text-xs text-gray-400">Admins always have event access; this toggle applies to user accounts.</p>
<?php if($deviceEventScopeReady): ?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
<div class="flex flex-col gap-2" data-checkbox-group>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Event Devices</label>
<span class="text-[11px] font-semibold text-gray-500"><span data-checkbox-count>0</span> selected</span>
</div>
<div class="max-h-44 overflow-y-auto rounded-lg border border-[#cfd7e7] bg-white p-3 dark:border-gray-700 dark:bg-gray-800 space-y-2">
<?php $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" name="event_device_ids[]" value="<?php echo e($device->id); ?>" data-checkbox-item data-event-device-checkbox <?php if(in_array((int) $device->id, $selectedEventDeviceIds, true)): echo 'checked'; endif; ?>/>
<span><?php echo e($device->name); ?> <?php if($device->serial_number): ?>(<?php echo e($device->serial_number); ?>)<?php endif; ?></span>
</label>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="invert">Invert</button>
</div>
</div>
<?php if($deviceEventInterfaceScopeReady): ?>
<div class="flex flex-col gap-2" data-device-event-interface-permissions>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Event Interfaces Per Device (optional)</label>
<div class="border border-[#cfd7e7] dark:border-gray-700 rounded-lg p-3 space-y-3 bg-white dark:bg-gray-800">
<?php $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
$deviceId = (int) $device->id;
$eventAllowedInterfaces = trim((string) ($eventInterfaceMap[$deviceId] ?? ''));
$hasEventDevice = in_array($deviceId, $selectedEventDeviceIds, true);
$interfaceOptions = $graphInterfaceOptionsByDevice[$deviceId] ?? [];
$selectedEventInterfaceLookup = $eventInterfaceSelectedLookupMap[$deviceId] ?? [];
?>
<div class="<?php if(!$hasEventDevice): echo 'hidden'; endif; ?> rounded-lg border border-slate-200 dark:border-gray-700 p-3" data-device-event-interface-item data-device-id="<?php echo e($deviceId); ?>">
<div class="text-xs font-semibold text-slate-500 mb-2"><?php echo e($device->name); ?> <?php if($device->serial_number): ?>(<?php echo e($device->serial_number); ?>)<?php endif; ?></div>
<input type="hidden" name="event_device_interfaces[<?php echo e($deviceId); ?>]" value="<?php echo e($eventAllowedInterfaces); ?>" data-event-interface-hidden/>
<?php if(!empty($interfaceOptions)): ?>
<div class="space-y-2">
<div class="flex flex-wrap items-center justify-between gap-2">
<span class="text-[11px] font-semibold text-gray-500"><span data-event-interface-count>0</span> selected</span>
<div class="flex flex-wrap gap-2">
<button class="px-2 py-1 text-[11px] font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-event-interface-action="all">Select all</button>
<button class="px-2 py-1 text-[11px] font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-event-interface-action="none">Clear</button>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-40 overflow-y-auto rounded-lg border border-[#cfd7e7] dark:border-gray-700 p-2">
<?php $__currentLoopData = $interfaceOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
$optionValue = trim((string) ($option['value'] ?? ''));
$optionLabel = trim((string) ($option['label'] ?? $optionValue));
$isChecked = $optionValue !== '' && isset($selectedEventInterfaceLookup[strtolower($optionValue)]);
?>
<?php if($optionValue !== ''): ?>
<label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" value="<?php echo e($optionValue); ?>" data-event-interface-option <?php if($isChecked): echo 'checked'; endif; ?>/>
<span><?php echo e($optionLabel); ?></span>
</label>
<?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
</div>
<?php else: ?>
<p class="text-xs text-gray-400">No discovered interfaces yet for this device.</p>
<?php if($eventAllowedInterfaces !== ''): ?>
<p class="text-xs text-gray-500">Current saved scope: <code><?php echo e($eventAllowedInterfaces); ?></code></p>
<?php endif; ?>
<?php endif; ?>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<p class="text-xs text-gray-400 <?php if(!empty($selectedEventDeviceIds)): echo 'hidden'; endif; ?>" data-device-event-interface-empty>Select one or more event devices to set interface scope.</p>
</div>
<p class="text-xs text-gray-400">Leave blank to allow all interfaces on that device.</p>
</div>
<?php else: ?>
<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
Run <code>php artisan migrate --force</code> to enable per-device event interface scope controls.
</div>
<?php endif; ?>
</div>
<p class="text-xs text-gray-400">If no event devices are selected, enabled users can view events for all assigned/permitted devices.</p>
<?php else: ?>
<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
Run <code>php artisan migrate --force</code> to enable per-device event scope controls.
</div>
<?php endif; ?>
<?php else: ?>
<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
Run <code>php artisan migrate --force</code> to enable user event access toggles.
</div>
<?php endif; ?>
</div>
<div class="flex flex-col gap-2 lg:col-span-12 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Graph Access</label>
<?php if($assignedDeviceGraphAccessReady): ?>
<label class="inline-flex items-center gap-2 rounded-lg border border-[#cfd7e7] bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" name="can_view_assigned_device_graphs" value="1" <?php if($canViewAssignedDeviceGraphs): echo 'checked'; endif; ?> />
<span>Enable graph visibility for this user account.</span>
</label>
<p class="text-xs text-gray-400">Admins always have graph access; this toggle applies to user accounts.</p>
<?php if($deviceGraphScopeReady): ?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
<div class="flex flex-col gap-2" data-checkbox-group>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Graph Devices</label>
<span class="text-[11px] font-semibold text-gray-500"><span data-checkbox-count>0</span> selected</span>
</div>
<div class="max-h-44 overflow-y-auto rounded-lg border border-[#cfd7e7] bg-white p-3 dark:border-gray-700 dark:bg-gray-800 space-y-2">
<?php $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" name="graph_device_ids[]" value="<?php echo e($device->id); ?>" data-checkbox-item data-graph-device-checkbox <?php if(in_array((int) $device->id, $selectedGraphDeviceIds, true)): echo 'checked'; endif; ?>/>
<span><?php echo e($device->name); ?> <?php if($device->serial_number): ?>(<?php echo e($device->serial_number); ?>)<?php endif; ?></span>
</label>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="invert">Invert</button>
</div>
</div>
<div class="flex flex-col gap-2" data-device-graph-interface-permissions>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Graph Interfaces Per Device (optional)</label>
<div class="border border-[#cfd7e7] dark:border-gray-700 rounded-lg p-3 space-y-3 bg-white dark:bg-gray-800">
<?php $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
$deviceId = (int) $device->id;
$graphAllowedInterfaces = trim((string) ($graphInterfaceMap[$deviceId] ?? ''));
$hasGraphDevice = in_array($deviceId, $selectedGraphDeviceIds, true);
$interfaceOptions = $graphInterfaceOptionsByDevice[$deviceId] ?? [];
$selectedGraphInterfaceLookup = $graphInterfaceSelectedLookupMap[$deviceId] ?? [];
?>
<div class="<?php if(!$hasGraphDevice): echo 'hidden'; endif; ?> rounded-lg border border-slate-200 dark:border-gray-700 p-3" data-device-graph-interface-item data-device-id="<?php echo e($deviceId); ?>">
<div class="text-xs font-semibold text-slate-500 mb-2"><?php echo e($device->name); ?> <?php if($device->serial_number): ?>(<?php echo e($device->serial_number); ?>)<?php endif; ?></div>
<input type="hidden" name="graph_device_interfaces[<?php echo e($deviceId); ?>]" value="<?php echo e($graphAllowedInterfaces); ?>" data-graph-interface-hidden/>
<?php if(!empty($interfaceOptions)): ?>
<div class="space-y-2">
<div class="flex flex-wrap items-center justify-between gap-2">
<span class="text-[11px] font-semibold text-gray-500"><span data-graph-interface-count>0</span> selected</span>
<div class="flex flex-wrap gap-2">
<button class="px-2 py-1 text-[11px] font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-graph-interface-action="all">Select all</button>
<button class="px-2 py-1 text-[11px] font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-graph-interface-action="none">Clear</button>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-40 overflow-y-auto rounded-lg border border-[#cfd7e7] dark:border-gray-700 p-2">
<?php $__currentLoopData = $interfaceOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
$optionValue = trim((string) ($option['value'] ?? ''));
$optionLabel = trim((string) ($option['label'] ?? $optionValue));
$isChecked = $optionValue !== '' && isset($selectedGraphInterfaceLookup[strtolower($optionValue)]);
?>
<?php if($optionValue !== ''): ?>
<label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" value="<?php echo e($optionValue); ?>" data-graph-interface-option <?php if($isChecked): echo 'checked'; endif; ?>/>
<span><?php echo e($optionLabel); ?></span>
</label>
<?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
</div>
<?php else: ?>
<p class="text-xs text-gray-400">No discovered interfaces yet for this device.</p>
<?php if($graphAllowedInterfaces !== ''): ?>
<p class="text-xs text-gray-500">Current saved scope: <code><?php echo e($graphAllowedInterfaces); ?></code></p>
<?php endif; ?>
<?php endif; ?>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<p class="text-xs text-gray-400 <?php if(!empty($selectedGraphDeviceIds)): echo 'hidden'; endif; ?>" data-device-graph-interface-empty>Select one or more graph devices to set interface scope.</p>
</div>
<p class="text-xs text-gray-400">Leave blank to allow all interfaces on that device.</p>
</div>
</div>
<p class="text-xs text-gray-400">If no graph devices are selected, enabled users can view graphs for all assigned/permitted devices.</p>
<?php else: ?>
<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
Run <code>php artisan migrate --force</code> to enable per-device and per-interface graph scope controls.
</div>
<?php endif; ?>
<?php else: ?>
<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
Run <code>php artisan migrate --force</code> to enable user graph access toggles.
</div>
<?php endif; ?>
</div>
<div class="flex flex-col gap-2 lg:col-span-12 order-11" data-device-port-permissions>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Port Access Per Device (optional)</label>
<div class="border border-[#cfd7e7] dark:border-gray-700 rounded-lg p-3 space-y-3 bg-white dark:bg-gray-800">
<?php $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
$deviceId = (int) $device->id;
$deviceAllowedPorts = trim((string) ($permissionPortMap[$deviceId] ?? ''));
$hasDevicePermission = in_array($deviceId, $selectedPermissionDeviceIds, true);
$interfaceOptions = $graphInterfaceOptionsByDevice[$deviceId] ?? [];
$selectedPortLookup = $permissionPortSelectedLookupMap[$deviceId] ?? [];
?>
<div class="<?php if(!$hasDevicePermission): echo 'hidden'; endif; ?> rounded-lg border border-slate-200 dark:border-gray-700 p-3" data-device-port-item data-device-id="<?php echo e($deviceId); ?>">
<div class="text-xs font-semibold text-slate-500 mb-2"><?php echo e($device->name); ?> <?php if($device->serial_number): ?>(<?php echo e($device->serial_number); ?>)<?php endif; ?></div>
<input type="hidden" name="device_permission_ports[<?php echo e($deviceId); ?>]" value="<?php echo e($deviceAllowedPorts); ?>" data-device-port-hidden/>
<?php if(!empty($interfaceOptions)): ?>
<div class="space-y-2">
<div class="flex flex-wrap items-center justify-between gap-2">
<span class="text-[11px] font-semibold text-gray-500"><span data-device-port-count>0</span> selected</span>
<div class="flex flex-wrap gap-2">
<button class="px-2 py-1 text-[11px] font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-device-port-action="all">Select all</button>
<button class="px-2 py-1 text-[11px] font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-device-port-action="none">Clear</button>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-40 overflow-y-auto rounded-lg border border-[#cfd7e7] dark:border-gray-700 p-2">
<?php $__currentLoopData = $interfaceOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
$optionValue = trim((string) ($option['value'] ?? ''));
$optionLabel = trim((string) ($option['label'] ?? $optionValue));
$isChecked = $optionValue !== '' && isset($selectedPortLookup[strtolower($optionValue)]);
?>
<?php if($optionValue !== ''): ?>
<label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" value="<?php echo e($optionValue); ?>" data-device-port-option <?php if($isChecked): echo 'checked'; endif; ?>/>
<span><?php echo e($optionLabel); ?></span>
</label>
<?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
</div>
<?php else: ?>
<p class="text-xs text-gray-400">No discovered interfaces yet for this device.</p>
<?php if($deviceAllowedPorts !== ''): ?>
<p class="text-xs text-gray-500">Current saved scope: <code><?php echo e($deviceAllowedPorts); ?></code></p>
<?php endif; ?>
<?php endif; ?>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<p class="text-xs text-gray-400 <?php if(!empty($selectedPermissionDeviceIds)): echo 'hidden'; endif; ?>" data-device-port-empty>Select one or more command devices to set port-level access.</p>
</div>
<p class="text-xs text-gray-400">Leave clear to allow all ports on that device.</p>
</div>
<div class="flex flex-col gap-2 lg:col-span-12 order-9 rounded-lg border border-[#cfd7e7] bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
<p class="text-xs font-bold uppercase tracking-wider text-gray-500">Command Access Scope</p>
<p class="mt-1 text-xs text-gray-400">These controls refine access for devices selected in Command Device Access.</p>
</div>
<div class="flex flex-col gap-2 lg:col-span-12 order-10" data-device-command-permissions>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Command Scope Per Device (optional)</label>
<?php if($deviceCommandRestrictionsReady ?? false): ?>
<div class="border border-[#cfd7e7] dark:border-gray-700 rounded-lg p-3 space-y-3 bg-white dark:bg-gray-800">
<?php $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
$deviceId = (int) $device->id;
$selectedDeviceCommandTemplateIds = $permissionCommandTemplateMap[$deviceId] ?? [];
$hasDevicePermission = in_array($deviceId, $selectedPermissionDeviceIds, true);
?>
<div class="<?php if(!$hasDevicePermission): echo 'hidden'; endif; ?> rounded-lg border border-slate-200 dark:border-gray-700 p-3" data-device-command-item data-device-id="<?php echo e($deviceId); ?>">
<div class="flex items-center justify-between gap-3 mb-2">
<div class="text-xs font-semibold text-slate-500"><?php echo e($device->name); ?> <?php if($device->serial_number): ?>(<?php echo e($device->serial_number); ?>)<?php endif; ?></div>
<span class="text-[11px] text-gray-400">Leave all unchecked to inherit the global command list.</span>
</div>
<?php if($commandTemplates->isNotEmpty()): ?>
<input type="hidden" name="device_permission_command_template_ids[<?php echo e($deviceId); ?>][]" value=""/>
<div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-44 overflow-y-auto">
<?php $__currentLoopData = $commandTemplates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $template): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" name="device_permission_command_template_ids[<?php echo e($deviceId); ?>][]" value="<?php echo e($template->id); ?>" <?php if(in_array((int) $template->id, $selectedDeviceCommandTemplateIds, true)): echo 'checked'; endif; ?>/>
<span><?php echo e($template->name); ?></span>
</label>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php else: ?>
<p class="text-xs text-gray-400">Create or enable command permissions below before scoping them per device.</p>
<?php endif; ?>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<p class="text-xs text-gray-400 <?php if(!empty($selectedPermissionDeviceIds)): echo 'hidden'; endif; ?>" data-device-command-empty>Select one or more command devices to scope commands on each device.</p>
</div>
<p class="text-xs text-gray-400">When you select commands here, this device uses that list. Leave everything unchecked to inherit the global command list from section 3.</p>
<?php else: ?>
<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
Run <code>php artisan migrate --force</code> to enable per-device command restrictions on this server.
</div>
<?php endif; ?>
</div>
</div>
</details>
<details class="lg:col-span-12 group rounded-lg border border-[#cfd7e7] dark:border-gray-700 bg-white dark:bg-gray-800/40 p-4" data-user-edit-section data-section-id="command_permissions">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3" data-user-edit-section-toggle>
<div>
<p class="text-xs font-bold uppercase tracking-wider text-gray-500">3) Command Permissions</p>
<p class="text-xs text-gray-400 mt-1">Keep only the commands this user should be allowed to execute.</p>
</div>
<span class="material-symbols-outlined text-gray-400 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="mt-4 grid grid-cols-1 lg:grid-cols-12 gap-4" data-user-edit-section-body>
<div class="flex flex-col gap-2 lg:col-span-12" data-checkbox-group>
<div class="flex flex-wrap items-center justify-between gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Command Permissions</label>
<div class="flex items-center gap-2">
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
</div>
</div>
<div class="rounded-lg border border-[#cfd7e7] dark:border-gray-700 p-3 bg-white dark:bg-gray-800 space-y-3" data-custom-command-builder>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Add Command Permission</label>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-primary focus:ring-primary h-11" name="custom_command_type" data-custom-command-type>
<option value="">Use existing command permissions</option>
<option value="custom" <?php if($showCustomCommandFields): echo 'selected'; endif; ?>>Custom Command</option>
</select>
<p class="text-xs text-gray-400">Choose <code>Custom Command</code> to create a new permission from script details.</p>
</div>
<div class="<?php if(!$showCustomCommandFields): echo 'hidden'; endif; ?> grid grid-cols-1 gap-3" data-custom-command-fields>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Script Name</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-primary focus:ring-primary h-11" type="text" name="custom_command_script_name" value="<?php echo e($customCommandScriptName); ?>" placeholder="e.g. show_custom_vlan"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Script Code</label>
<textarea class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-primary focus:ring-primary min-h-[110px] font-mono text-xs" name="custom_command_script_code" placeholder="#!/bin/bash&#10;echo \"custom command\""><?php echo e($customCommandScriptCode); ?></textarea>
<p class="text-xs text-gray-400">The new permission will be saved as <code>Custom Command: &lt;Script Name&gt;</code> and added to the list below.</p>
</div>
</div>
</div>
<div class="border border-[#cfd7e7] dark:border-gray-700 rounded-lg p-3 max-h-56 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-2 bg-white dark:bg-gray-800">
<?php $__empty_2 = true; $__currentLoopData = $commandTemplates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $template): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" name="command_template_ids[]" value="<?php echo e($template->id); ?>" data-checkbox-item <?php if(isset($selectedCommandTemplateLookup[(int) $template->id])): echo 'checked'; endif; ?>/>
<span><?php echo e($template->name); ?></span>
</label>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
<p class="text-xs text-gray-400">No command templates available.</p>
<?php endif; ?>
</div>
<p class="text-xs text-gray-400"><span class="font-semibold" data-checkbox-count>0</span> commands selected.</p>
</div>
</div>
</details>
<details class="lg:col-span-12 group rounded-lg border border-[#cfd7e7] dark:border-gray-700 bg-white dark:bg-gray-800/40 p-4" data-user-edit-section data-section-id="telegram_notifications">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3" data-user-edit-section-toggle>
<div>
<p class="text-sm font-semibold text-gray-700 dark:text-gray-200">4) Telegram Notifications</p>
<p class="text-xs text-gray-400">Optional delivery settings for device and port alert events.</p>
</div>
<span class="material-symbols-outlined text-gray-400 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4" data-user-edit-section-body>
<input type="hidden" name="telegram_severities_present" value="1"/>
<input type="hidden" name="telegram_event_types_present" value="1"/>
<div class="md:col-span-2">
<label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" name="telegram_enabled" value="1" <?php if($telegramEnabled): echo 'checked'; endif; ?> />
<span>Enabled</span>
</label>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Telegram Chat ID</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="telegram_chat_id" type="text" value="<?php echo e(old('telegram_chat_id', $user->telegram_chat_id)); ?>" placeholder="e.g. 123456789, -1001234567890"/>
</div>

<div class="flex flex-col gap-2 md:col-span-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Telegram Bot Token (optional)</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="telegram_bot_token" type="text" value="<?php echo e(old('telegram_bot_token', $user->telegram_bot_token)); ?>" placeholder="123456:ABC..."/>
<p class="text-xs text-gray-400">Leave blank to use the global TELEGRAM_BOT_TOKEN.</p>
</div>

<div class="flex flex-col gap-2" data-checkbox-group>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Telegram Devices</label>
<span class="text-[11px] font-semibold text-gray-500"><span data-checkbox-count>0</span> selected</span>
</div>
<div class="max-h-44 overflow-y-auto rounded-lg border border-[#cfd7e7] bg-white p-3 dark:border-gray-700 dark:bg-gray-800 space-y-2">
<?php $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" name="telegram_devices[]" value="<?php echo e($device->id); ?>" data-checkbox-item <?php if(in_array((int) $device->id, $selectedTelegramDevices, true)): echo 'checked'; endif; ?>/>
<span><?php echo e($device->name); ?> <?php if($device->serial_number): ?>(<?php echo e($device->serial_number); ?>)<?php endif; ?></span>
</label>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
</div>
<p class="text-xs text-gray-400">Defaults to assigned devices when empty.</p>
</div>
<?php if($telegramDeviceInterfaceScopeReady): ?>
<div class="flex flex-col gap-2 md:col-span-2" data-telegram-device-interface-permissions>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Telegram Interfaces Per Device</label>
<div class="border border-[#cfd7e7] dark:border-gray-700 rounded-lg p-3 space-y-3 bg-white dark:bg-gray-800">
<?php $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
$deviceId = (int) $device->id;
$hasTelegramDevice = in_array($deviceId, $selectedTelegramDevices, true);
$telegramInterfaceOptions = $graphInterfaceOptionsByDevice[$deviceId] ?? [];
$telegramInterfaceValuesRaw = $telegramDeviceInterfacesMap[$deviceId] ?? null;
$explicitTelegramInterfaceScope = false;
$selectedTelegramInterfaceLookup = [];
 $selectedTelegramInterfaceValues = [];
$telegramInterfaceValueTokens = [];
if (is_array($telegramInterfaceValuesRaw)) {
    $telegramInterfaceValueTokens = $telegramInterfaceValuesRaw;
} elseif (is_string($telegramInterfaceValuesRaw)) {
    $telegramInterfaceValueTokens = preg_split('/\s*,\s*/', $telegramInterfaceValuesRaw) ?: [];
}
foreach ($telegramInterfaceValueTokens as $interfaceValueRaw) {
    $interfaceValue = trim((string) $interfaceValueRaw);
    $interfaceKey = strtolower($interfaceValue);
    if ($interfaceKey === '') { continue; }
    if (!isset($selectedTelegramInterfaceLookup[$interfaceKey])) {
        $selectedTelegramInterfaceLookup[$interfaceKey] = true;
        $selectedTelegramInterfaceValues[] = $interfaceValue;
    }
}
 $explicitTelegramInterfaceScope = !empty($selectedTelegramInterfaceLookup);
if (!$explicitTelegramInterfaceScope && $hasTelegramDevice) {
    foreach ($telegramInterfaceOptions as $telegramInterfaceOption) {
        $interfaceValue = trim((string) ($telegramInterfaceOption['value'] ?? ''));
        $interfaceKey = strtolower($interfaceValue);
        if ($interfaceKey !== '' && !isset($selectedTelegramInterfaceLookup[$interfaceKey])) {
            $selectedTelegramInterfaceLookup[$interfaceKey] = true;
            $selectedTelegramInterfaceValues[] = $interfaceValue;
        }
    }
}
$telegramInterfaceHiddenValue = implode(',', $selectedTelegramInterfaceValues);
?>
<div class="<?php if(!$hasTelegramDevice): echo 'hidden'; endif; ?> rounded-lg border border-slate-200 dark:border-gray-700" data-telegram-device-interface-item data-device-id="<?php echo e($deviceId); ?>" data-default-select-all="<?php echo e($explicitTelegramInterfaceScope ? '0' : '1'); ?>">
<details class="group" data-telegram-device-interface-panel>
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<div class="min-w-0">
<p class="truncate text-xs font-semibold text-slate-500"><?php echo e($device->name); ?> <?php if($device->serial_number): ?>(<?php echo e($device->serial_number); ?>)<?php endif; ?></p>
<?php if(!empty($telegramInterfaceOptions)): ?>
<p class="mt-1 text-[11px] font-semibold text-gray-500"><span data-telegram-device-interface-count>0</span> selected</p>
<?php else: ?>
<p class="mt-1 text-[11px] text-gray-400">No discovered interfaces yet for this device.</p>
<?php endif; ?>
</div>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<?php if(!empty($telegramInterfaceOptions)): ?>
<div class="space-y-2 border-t border-[#cfd7e7] dark:border-gray-700 px-3 pb-3 pt-2">
<input type="hidden" name="telegram_device_interfaces[<?php echo e($deviceId); ?>]" value="<?php echo e($telegramInterfaceHiddenValue); ?>" data-telegram-device-interface-hidden/>
<div class="flex flex-wrap items-center justify-end gap-2">
<button class="px-2 py-1 text-[11px] font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-telegram-device-interface-action="all">Select all</button>
<button class="px-2 py-1 text-[11px] font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-telegram-device-interface-action="none">Clear</button>
</div>
<div class="grid grid-cols-1 gap-2 max-h-40 overflow-y-auto rounded-lg border border-[#cfd7e7] dark:border-gray-700 p-2 md:grid-cols-2">
<?php $__currentLoopData = $telegramInterfaceOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $telegramInterfaceOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
$telegramInterfaceValue = trim((string) ($telegramInterfaceOption['value'] ?? ''));
$telegramInterfaceLabel = trim((string) ($telegramInterfaceOption['label'] ?? $telegramInterfaceValue));
$telegramInterfaceChecked = $telegramInterfaceValue !== '' && isset($selectedTelegramInterfaceLookup[strtolower($telegramInterfaceValue)]);
?>
<?php if($telegramInterfaceValue !== ''): ?>
<label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" value="<?php echo e($telegramInterfaceValue); ?>" data-telegram-device-interface-option <?php if($telegramInterfaceChecked): echo 'checked'; endif; ?>/>
<span><?php echo e($telegramInterfaceLabel); ?></span>
</label>
<?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
</div>
<?php endif; ?>
</details>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<p class="text-xs text-gray-400 <?php if(!empty($selectedTelegramDevices)): echo 'hidden'; endif; ?>" data-telegram-device-interface-empty>Select one or more Telegram devices to scope interfaces.</p>
</div>
<p class="text-xs text-gray-400">When a Telegram device is selected, all discovered interfaces start selected by default.</p>
</div>
<?php else: ?>
<div class="md:col-span-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
Run <code>php artisan migrate --force</code> to enable Telegram per-device interface scope controls.
</div>
<?php endif; ?>

<div class="flex flex-col gap-2" data-checkbox-group>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Telegram Severities</label>
<span class="text-[11px] font-semibold text-gray-500"><span data-checkbox-count>0</span> selected</span>
</div>
<div class="border border-[#cfd7e7] dark:border-gray-700 rounded-lg p-3 bg-white dark:bg-gray-800 space-y-2">
<?php $__currentLoopData = $severityOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $severityOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200 mr-4">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" name="telegram_severities[]" value="<?php echo e($severityOption); ?>" data-checkbox-item <?php if(in_array(strtolower((string) $severityOption), $selectedTelegramSeverities, true)): echo 'checked'; endif; ?>/>
<span class="capitalize"><?php echo e($severityOption); ?></span>
</label>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
</div>
</div>

<div class="flex flex-col gap-2" data-checkbox-group>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Telegram Event Types</label>
<span class="text-[11px] font-semibold text-gray-500"><span data-checkbox-count>0</span> selected</span>
</div>
<div class="max-h-44 overflow-y-auto rounded-lg border border-[#cfd7e7] bg-white p-3 dark:border-gray-700 dark:bg-gray-800 space-y-2">
<?php $__currentLoopData = $eventTypeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventTypeOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" name="telegram_event_types[]" value="<?php echo e($eventTypeOption); ?>" data-checkbox-item <?php if(in_array(strtolower((string) $eventTypeOption), $selectedTelegramEventTypes, true)): echo 'checked'; endif; ?>/>
<span><?php echo e($eventTypeOption); ?></span>
</label>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
</div>
<p class="text-xs text-gray-400">Global custom tags and message templates are managed in Settings.</p>
</div>
</div>
</details>
</div>
<div class="-mx-1 mt-4 px-1 py-3 flex flex-wrap items-center justify-end gap-3 border-t border-[#cfd7e7] dark:border-gray-700 bg-white dark:bg-gray-900">
<?php if (\Illuminate\Support\Facades\Route::has('users.telegram.test')): ?>
<button class="px-4 py-2 text-sm font-semibold text-blue-700 border border-blue-200 rounded-lg hover:bg-blue-50" type="submit" formaction="<?php echo e(route('users.telegram.test', $user)); ?>" formnovalidate>Save + Send Telegram Test</button>
<?php endif; ?>
<a class="px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50" href="<?php echo e($closeUrl); ?>">Close</a>
<button class="px-4 py-2 text-sm font-semibold text-white bg-primary rounded-lg hover:bg-primary/90" type="submit">Save Changes</button>
</div>
</div>
</form>
<?php if($role !== 'admin'): ?>
<form class="mt-4" method="POST" action="<?php echo e(route('users.status')); ?>">
<?php echo csrf_field(); ?>
<input type="hidden" name="user_id" value="<?php echo e($user->id); ?>"/>
<input type="hidden" name="status" value="<?php echo e($isActive ? 'inactive' : 'active'); ?>"/>
<button class="px-4 py-2 text-sm font-semibold <?php echo e($isActive ? 'text-gray-600 border-gray-200 hover:bg-gray-50' : 'text-green-700 border-green-200 hover:bg-green-50'); ?> border rounded-lg" type="submit">
<?php echo e($isActive ? 'Deactivate User' : 'Activate User'); ?>

</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr>
<td class="px-6 py-6 text-sm text-gray-500" colspan="6">No users found.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
<div class="px-6 py-4 flex items-center justify-between border-t border-[#cfd7e7] dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30">
<p class="text-sm text-[#4c669a] dark:text-gray-400">Showing <?php echo e($users->firstItem() ?? 0); ?> to <?php echo e($users->lastItem() ?? 0); ?> of <?php echo e($users->total() ?? 0); ?> users</p>
<div class="flex gap-2">
<a class="px-3 py-1 border border-[#cfd7e7] dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-sm <?php echo e($users->previousPageUrl() ? '' : 'opacity-50 pointer-events-none'); ?>" href="<?php echo e($users->previousPageUrl() ?? '#'); ?>">Previous</a>
<span class="px-3 py-1 bg-primary text-white rounded text-sm"><?php echo e($users->currentPage()); ?></span>
<a class="px-3 py-1 border border-[#cfd7e7] dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-sm <?php echo e($users->nextPageUrl() ? '' : 'opacity-50 pointer-events-none'); ?>" href="<?php echo e($users->nextPageUrl() ?? '#'); ?>">Next</a>
</div>
</div>
</div>
</div>
</div>
</main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var filterForm = document.getElementById('user-filters');
    var exportForm = document.getElementById('user-export-form');

    function setupPasswordPreviewMirrors() {
        document.querySelectorAll('form').forEach(function (form) {
            var newPasswordInput = form.querySelector('[data-new-password-input]');
            var currentPasswordDisplay = form.querySelector('[data-current-password-display]');

            if (!newPasswordInput || !currentPasswordDisplay) {
                return;
            }

            var serverValue = currentPasswordDisplay.dataset.serverValue || '';
            var syncPreview = function () {
                currentPasswordDisplay.value = newPasswordInput.value !== ''
                    ? newPasswordInput.value
                    : serverValue;
            };

            newPasswordInput.addEventListener('input', syncPreview);
            newPasswordInput.addEventListener('change', syncPreview);
        });
    }

    function syncExportFilters() {
        if (!filterForm || !exportForm) {
            return;
        }

        ['search', 'role', 'status'].forEach(function (name) {
            var source = filterForm.elements.namedItem(name);
            var target = exportForm.elements.namedItem(name);

            if (!source || !target) {
                return;
            }

            target.value = source.value;
        });
    }

    if (exportForm) {
        exportForm.addEventListener('submit', syncExportFilters);
    }

    setupPasswordPreviewMirrors();
});
</script>
</body></html>














<?php /**PATH C:\xampp\htdocs\Laravel\resources\views\user_management_tables_drawer.blade.php ENDPATH**/ ?>




