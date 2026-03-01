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
<body class="bg-background-light dark:bg-background-dark text-[#0d121b] dark:text-white h-screen overflow-hidden flex flex-col font-display">
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
<button class="text-primary hover:text-primary/70 font-bold text-sm" type="button" data-no-dispatch="true" data-user-edit="<?php echo e($user->id); ?>">Edit</button>
<?php endif; ?>
<span class="px-3 py-1.5 text-xs font-semibold text-amber-700 border border-amber-200 rounded-lg bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-200">Protected Super Admin</span>
<?php if($isCurrentAuthUser): ?>
<span class="px-3 py-1.5 text-xs font-semibold text-slate-400 border border-slate-200 rounded-lg cursor-not-allowed">Current Account</span>
<?php endif; ?>
<?php else: ?>
<button class="text-primary hover:text-primary/70 font-bold text-sm" type="button" data-no-dispatch="true" data-user-edit="<?php echo e($user->id); ?>">Edit</button>
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
<?php if(!$isSuperAdmin || $canEditProtectedSelf): ?>
<tr class="hidden bg-gray-50/60 dark:bg-gray-900/40" data-user-edit-row="<?php echo e($user->id); ?>">
<td class="px-6 py-4" colspan="6">
<form class="space-y-6" method="POST" action="<?php echo e(route('users.update', $user)); ?>" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<?php
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

$oldPermissionPortMap = old('device_permission_ports');
if (is_array($oldPermissionPortMap)) {
    foreach ($oldPermissionPortMap as $deviceId => $value) {
        if (!is_numeric($deviceId)) {
            continue;
        }
        $permissionPortMap[(int) $deviceId] = trim((string) $value);
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

$severityOptions = $telegramSeverityOptions ?? ['low', 'medium', 'high', 'critical'];
$selectedTelegramSeverities = old('telegram_severities', $user->telegram_severities ?? ['high', 'critical']);
if (!is_array($selectedTelegramSeverities) || empty($selectedTelegramSeverities)) {
    $selectedTelegramSeverities = ['high', 'critical'];
}
$selectedTelegramSeverities = array_values(array_unique(array_map(
    static fn ($value): string => strtolower(trim((string) $value)),
    $selectedTelegramSeverities
)));

$eventTypeOptions = $telegramEventTypeOptions ?? ['device.offline', 'port.down'];
$selectedTelegramEventTypes = old('telegram_event_types', $user->telegram_event_types ?? ['device.offline', 'port.down']);
if (!is_array($selectedTelegramEventTypes) || empty($selectedTelegramEventTypes)) {
    $selectedTelegramEventTypes = ['device.offline', 'port.down'];
}
$selectedTelegramEventTypes = array_values(array_unique(array_map(
    static fn ($value): string => strtolower(trim((string) $value)),
    $selectedTelegramEventTypes
)));
$customTelegramEventTypes = old(
    'telegram_event_types_custom',
    implode(',', array_values(array_diff($selectedTelegramEventTypes, $eventTypeOptions)))
);
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
<div class="lg:col-span-12">
<p class="text-xs font-bold uppercase tracking-wider text-gray-500">1) Account</p>
<p class="text-xs text-gray-400 mt-1">Identity, role, and password rotation.</p>
</div>
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
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="password" placeholder="Leave blank to keep current" type="password"/>
<p class="text-xs text-gray-400">Use this only when rotating credentials.</p>
<?php else: ?>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 bg-slate-50 dark:bg-gray-800/60 dark:text-white text-slate-500 h-11 cursor-not-allowed" placeholder="Only super admin can change password" type="password" disabled/>
<p class="text-xs text-gray-400">Password changes are restricted to super admin.</p>
<?php endif; ?>
</div>
<div class="flex flex-col gap-3 lg:col-span-12">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Profile Picture</label>
<div class="flex flex-col gap-4 rounded-lg border border-[#cfd7e7] bg-white p-4 dark:border-gray-700 dark:bg-gray-800/40 md:flex-row md:items-center">
<?php echo $__env->make('partials.user_avatar', ['user' => $user, 'name' => $user->name, 'sizeClass' => 'h-16 w-16', 'textClass' => 'text-lg'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<div class="min-w-0 flex-1 space-y-2">
<input class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:font-semibold file:text-white hover:file:bg-primary/90 dark:text-slate-300" type="file" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif"/>
<p class="text-xs text-gray-400">Upload a PNG, JPG, WEBP, or GIF up to 2 MB. A new upload replaces the current photo.</p>
<?php if(!empty($user->avatar_path)): ?>
<label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" name="remove_avatar" value="1"/>
<span>Remove current profile picture</span>
</label>
<?php endif; ?>
</div>
</div>
</div>
<div class="lg:col-span-12 pt-1">
<p class="text-xs font-bold uppercase tracking-wider text-gray-500">2) Device Scope</p>
<p class="text-xs text-gray-400 mt-1">Assign owned devices first, then grant command-only device access.</p>
</div>
<div class="flex flex-col gap-2 lg:col-span-6" data-multi-select>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Assigned Devices</label>
<span class="text-[11px] font-semibold text-gray-500"><span data-selected-count>0</span> selected</span>
</div>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-28" multiple name="device_ids[]" data-multi-select-input>
<?php $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<option value="<?php echo e($device->id); ?>" <?php if($device->assigned_user_id === $user->id): echo 'selected'; endif; ?>>
<?php echo e($device->name); ?> <?php if($device->serial_number): ?>(<?php echo e($device->serial_number); ?>)<?php endif; ?>
</option>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</select>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-select-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-select-action="none">Clear</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-select-action="invert">Invert</button>
</div>
<p class="text-xs text-gray-400">Use quick actions for faster selection.</p>
</div>
<div class="flex flex-col gap-2 lg:col-span-6" data-multi-select>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Command Device Access</label>
<span class="text-[11px] font-semibold text-gray-500"><span data-selected-count>0</span> selected</span>
</div>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-28" multiple name="device_permission_ids[]" data-device-permission-select data-multi-select-input>
<?php $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<option value="<?php echo e($device->id); ?>" <?php if(in_array((int) $device->id, $selectedPermissionDeviceIds, true)): echo 'selected'; endif; ?>>
<?php echo e($device->name); ?> <?php if($device->serial_number): ?>(<?php echo e($device->serial_number); ?>)<?php endif; ?>
</option>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</select>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-select-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-select-action="none">Clear</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-select-action="invert">Invert</button>
</div>
<p class="text-xs text-gray-400">Grant command access to devices even if assigned to another user.</p>
</div>
<div class="flex flex-col gap-2 lg:col-span-12" data-device-port-permissions>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Port Access Per Device (optional)</label>
<div class="border border-[#cfd7e7] dark:border-gray-700 rounded-lg p-3 space-y-3 bg-white dark:bg-gray-800">
<?php $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
$deviceId = (int) $device->id;
$deviceAllowedPorts = trim((string) ($permissionPortMap[$deviceId] ?? ''));
$hasDevicePermission = in_array($deviceId, $selectedPermissionDeviceIds, true);
?>
<div class="<?php if(!$hasDevicePermission): echo 'hidden'; endif; ?> rounded-lg border border-slate-200 dark:border-gray-700 p-3" data-device-port-item data-device-id="<?php echo e($deviceId); ?>">
<div class="text-xs font-semibold text-slate-500 mb-2"><?php echo e($device->name); ?> <?php if($device->serial_number): ?>(<?php echo e($device->serial_number); ?>)<?php endif; ?></div>
<input class="w-full rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-primary focus:ring-primary h-10" type="text" name="device_permission_ports[<?php echo e($deviceId); ?>]" value="<?php echo e($deviceAllowedPorts); ?>" placeholder="e.g. Gi1/0/1,Gi1/0/2,Gi1/0/* or *"/>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<p class="text-xs text-gray-400 <?php if(!empty($selectedPermissionDeviceIds)): echo 'hidden'; endif; ?>" data-device-port-empty>Select one or more command devices to set port-level access.</p>
</div>
<p class="text-xs text-gray-400">Leave blank to allow all ports on that device. Use comma-separated patterns, for example: <code>Gi1/0/1,Gi1/0/2,Gi1/0/*</code>.</p>
</div>
<div class="lg:col-span-12 pt-1">
<p class="text-xs font-bold uppercase tracking-wider text-gray-500">3) Command Permissions</p>
<p class="text-xs text-gray-400 mt-1">Keep only the commands this user should be allowed to execute.</p>
</div>
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
<div class="lg:col-span-12 rounded-lg border border-[#cfd7e7] dark:border-gray-700 p-4 bg-white dark:bg-gray-800/40 space-y-4">
<div class="flex flex-wrap items-center justify-between gap-3">
<div>
<p class="text-sm font-semibold text-gray-700 dark:text-gray-200">4) Telegram Notifications</p>
<p class="text-xs text-gray-400">Optional delivery settings for device and port alert events.</p>
</div>
<label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
<input class="rounded border-gray-300 text-primary focus:ring-primary" type="checkbox" name="telegram_enabled" value="1" <?php if($telegramEnabled): echo 'checked'; endif; ?> />
<span>Enabled</span>
</label>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Telegram Chat ID</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="telegram_chat_id" type="text" value="<?php echo e(old('telegram_chat_id', $user->telegram_chat_id)); ?>" placeholder="e.g. 123456789, -1001234567890"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Telegram Ports</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="telegram_ports" type="text" value="<?php echo e(old('telegram_ports', $user->telegram_ports)); ?>" placeholder="80,443,1000-1010"/>
<p class="text-xs text-gray-400">Optional comma-separated ports and ranges.</p>
</div>

<div class="flex flex-col gap-2 md:col-span-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Telegram Bot Token (optional)</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="telegram_bot_token" type="text" value="<?php echo e(old('telegram_bot_token', $user->telegram_bot_token)); ?>" placeholder="123456:ABC..."/>
<p class="text-xs text-gray-400">Leave blank to use the global TELEGRAM_BOT_TOKEN.</p>
</div>

<div class="flex flex-col gap-2" data-multi-select>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Telegram Devices</label>
<span class="text-[11px] font-semibold text-gray-500"><span data-selected-count>0</span> selected</span>
</div>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-32" multiple name="telegram_devices[]" data-multi-select-input>
<?php $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<option value="<?php echo e($device->id); ?>" <?php if(in_array((int) $device->id, $selectedTelegramDevices, true)): echo 'selected'; endif; ?>>
<?php echo e($device->name); ?> <?php if($device->serial_number): ?>(<?php echo e($device->serial_number); ?>)<?php endif; ?>
</option>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</select>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-select-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-select-action="none">Clear</button>
</div>
<p class="text-xs text-gray-400">Defaults to assigned devices when empty.</p>
</div>

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

<div class="flex flex-col gap-2" data-multi-select>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Telegram Event Types</label>
<span class="text-[11px] font-semibold text-gray-500"><span data-selected-count>0</span> selected</span>
</div>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-32" multiple name="telegram_event_types[]" data-multi-select-input>
<?php $__currentLoopData = $eventTypeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventTypeOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<option value="<?php echo e($eventTypeOption); ?>" <?php if(in_array(strtolower((string) $eventTypeOption), $selectedTelegramEventTypes, true)): echo 'selected'; endif; ?>><?php echo e($eventTypeOption); ?></option>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</select>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-select-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700/30" type="button" data-no-dispatch="true" data-select-action="none">Clear</button>
</div>
<p class="text-xs text-gray-400">Select defaults; add custom tags below.</p>
</div>

<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Custom Event Types</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="telegram_event_types_custom" type="text" value="<?php echo e($customTelegramEventTypes); ?>" placeholder="device.*, custom.tag"/>
<p class="text-xs text-gray-400">Comma-separated custom tags, supports wildcard like device.*</p>
</div>

<div class="flex flex-col gap-2 md:col-span-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Custom Message Template (optional)</label>
<textarea class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary min-h-[96px]" name="telegram_template" placeholder="[{severity}] {type}\nDevice: {deviceName} ({deviceIp})\nPort: {port}\n{message}\nTime: {timestamp}"><?php echo e(old('telegram_template', $user->telegram_template)); ?></textarea>
<p class="text-xs text-gray-400">Available placeholders: {deviceName}, {deviceIp}, {port}, {severity}, {type}, {timestamp}, {message}</p>
</div>
</div>
</div>
</div>
<div class="sticky bottom-0 z-10 -mx-1 mt-1 px-1 py-3 flex flex-wrap items-center justify-end gap-3 border-t border-[#cfd7e7] dark:border-gray-700 bg-white/95 dark:bg-gray-900/95 backdrop-blur">
<?php if (\Illuminate\Support\Facades\Route::has('users.telegram.test')): ?>
<button class="px-4 py-2 text-sm font-semibold text-blue-700 border border-blue-200 rounded-lg hover:bg-blue-50" type="submit" formaction="<?php echo e(route('users.telegram.test', $user)); ?>" formnovalidate>Send Telegram Test</button>
<?php endif; ?>
<button class="px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50" type="button" data-no-dispatch="true" data-user-edit-close="<?php echo e($user->id); ?>">Close</button>
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
});
</script>
</body></html>














<?php /**PATH C:\xampp\htdocs\Laravel\resources\views\user_management_tables_drawer.blade.php ENDPATH**/ ?>




