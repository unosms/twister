<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/><meta name="csrf-token" content="{{ csrf_token() }}"/><meta name="app-base" content="{{ url('/') }}"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Command Builder &amp; Permissions - Twister Device Control</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
                        "display": ["Inter"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
<style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .active-icon { font-variation-settings: 'FILL' 1; }
    </style>
    <script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script></head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 min-h-screen">
<!-- Top Navigation Bar -->
<header class="sticky top-0 z-50 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
<div class="max-w-[1440px] mx-auto flex items-center justify-between px-6 py-3">
<div class="flex items-center gap-8">
<div class="flex items-center gap-3">
<div class="bg-primary text-white p-1.5 rounded-lg">
<span class="material-symbols-outlined block">settings_remote</span>
</div>
<h2 class="text-lg font-bold tracking-tight">Twister Device Control</h2>
</div>
<nav class="hidden md:flex items-center gap-6">
<a class="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-primary dark:hover:text-primary" href="{{ route('dashboard') }}">Dashboard</a>
<a class="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-primary dark:hover:text-primary" href="{{ route('devices.index') }}">Devices</a>
<a class="text-sm font-medium text-primary border-b-2 border-primary pb-4 -mb-4" href="{{ route('commands.builder') }}">Commands</a>
<a class="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-primary dark:hover:text-primary" href="{{ route('users.index') }}">Users</a>
</nav>
</div>
<div class="flex items-center gap-4">
<div class="relative hidden sm:block">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
<input class="bg-slate-100 dark:bg-slate-800 border-none rounded-lg pl-10 pr-4 py-2 text-sm w-64 focus:ring-2 focus:ring-primary" placeholder="Search commands..." type="text" data-live-search data-live-search-target="[data-command-builder-section]"/>
</div>
<div class="h-8 w-8 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden border border-slate-300 dark:border-slate-600" data-alt="User profile avatar placeholder">
<img alt="User Avatar" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDXs7ps9hrnUkdmAwf2nekQ5Ymbc5js8Cr-dTWEc3T_j6QcqjJMW2wvahOifx0jGFCX7IpzFngImnEskAejwpXsIhfT1c8g6Jsf6qu7ExoFvudg59VlweFCuNUADMqVZ6qenZ4zXcCeE4vY2_PNsgbOeD1FvTl2jy1fXMkzHvhBpI2sJkWDB5h1Iur4_DIkI1LlVMVoSgc0xTT3Ufte60elF3Q5kUWIvSLEdt39h-9BlJm-0OjxMO_WLEGdf17RAmtjrnm5o91E5A"/>
</div>
</div>
</div>
</header>
<main class="max-w-[1440px] mx-auto px-6 py-6">
@include('partials.admin_nav_shortcuts')
<!-- Breadcrumbs -->
<div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 mb-4">
<a class="hover:text-primary" href="{{ route('dashboard') }}">Dashboard</a>
<span class="material-symbols-outlined text-xs">chevron_right</span>
<a class="hover:text-primary" href="{{ route('commands.builder') }}">Commands</a>
<span class="material-symbols-outlined text-xs">chevron_right</span>
<span class="text-slate-900 dark:text-slate-200 font-medium">Create New Command</span>
</div>
<!-- Page Heading -->
<div class="flex items-end justify-between mb-8">
<div class="space-y-1">
<h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white">Custom Command Builder</h1>
<p class="text-slate-500 dark:text-slate-400">Design interactive controls and secure execution logic for your hardware fleet.</p>
</div>
<div class="flex gap-3">
<button class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors" type="submit" form="command-form" formaction="{{ route('commands.discard') }}">Discard Draft</button>
<button class="px-6 py-2 bg-primary text-white rounded-lg text-sm font-semibold shadow-lg shadow-primary/20 hover:bg-blue-700 transition-colors" type="submit" form="command-form">Save Command</button>
</div>
</div>
<div class="grid grid-cols-12 gap-8">
<!-- Left Column: Visual Preview -->
<div class="col-span-12 lg:col-span-5 xl:col-span-4">
<div class="sticky top-24 space-y-6">
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm" data-command-builder-section>
<div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
<h3 class="font-bold flex items-center gap-2">
<span class="material-symbols-outlined text-primary">visibility</span>
                                UI Preview
                            </h3>
<span class="text-[10px] uppercase tracking-widest font-bold text-slate-400 px-2 py-0.5 border border-slate-200 dark:border-slate-700 rounded">Interactive</span>
</div>
<div class="p-8 bg-slate-50 dark:bg-slate-950 flex flex-col items-center justify-center min-h-[400px]">
<!-- Preview Card Simulation -->
<div class="w-full max-w-[280px] bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800 p-6 space-y-6">
<div class="flex items-center justify-between">
<span class="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg">lightbulb</span>
<span class="text-xs font-semibold text-slate-400">ãƒªãƒ“ãƒ³ã‚°ãƒ«ãƒ¼ãƒ </span>
</div>
<div>
<h4 class="font-bold text-lg leading-tight">Living Room Main Light</h4>
<p class="text-xs text-slate-500 mt-1 italic">Status: Online</p>
</div>
<!-- Dynamic Component Preview (Toggle example) -->
<div class="pt-4 flex items-center justify-between border-t border-slate-100 dark:border-slate-800">
<span class="text-sm font-medium">Power State</span>
<div class="w-12 h-6 bg-primary rounded-full relative cursor-pointer shadow-inner">
<div class="absolute right-1 top-1 w-4 h-4 bg-white rounded-full"></div>
</div>
</div>
<!-- Slider Preview example (Subtle) -->
<div class="space-y-3">
<div class="flex justify-between text-xs font-bold text-slate-400">
<span>BRIGHTNESS</span>
<span>80%</span>
</div>
<div class="w-full h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
<div class="w-[80%] h-full bg-primary"></div>
</div>
</div>
</div>
<p class="mt-8 text-xs text-slate-400 text-center px-4 leading-relaxed">
                                This is how the command will appear in the User Portal and Mobile App. Interactive components will use the primary theme color.
                            </p>
</div>
</div>
<!-- Side Nav Style Helper -->
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-2" data-command-builder-section>
<nav class="flex flex-col gap-1">
<div class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary/10 text-primary">
<span class="material-symbols-outlined active-icon">terminal</span>
<span class="text-sm font-bold">Logic &amp; Configuration</span>
</div>
<div class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
<span class="material-symbols-outlined">security</span>
<span class="text-sm font-medium">Execution Permissions</span>
</div>
<div class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
<span class="material-symbols-outlined">history</span>
<span class="text-sm font-medium">Change Log</span>
</div>
</nav>
</div>
</div>
</div>
<!-- Right Column: Configuration Form -->
<div class="col-span-12 lg:col-span-7 xl:col-span-8">
<form id="command-form" class="space-y-8 pb-20" method="POST" action="{{ route('commands.store') }}">
@csrf
<input type="hidden" name="action_key" id="command-action-key"/>
<input type="hidden" name="ui_type" id="command-ui-type" value="button"/>
<!-- Section 1: Basic Information -->
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden" data-command-builder-section>
<div class="p-6 border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/20">
<h2 class="text-lg font-bold">1. Command Essentials</h2>
</div>
<div class="p-6 space-y-6">
<details class="group rounded-lg border border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-800/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-400 text-[11px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Command Essentials Help
</span>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-3 pb-3 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li>Use a clear command name that matches the real operation users will run.</li>
<li>Choose <code>Custom Command</code> only when this permission does not already exist in templates.</li>
<li>For custom scripts, keep script name stable and script code minimal for easier auditing.</li>
</ol>
</div>
</details>
<div class="space-y-2" data-custom-command-builder>
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Permission Type</label>
<select class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-transparent focus:ring-primary focus:border-primary" name="command_type" data-custom-command-type>
<option value="" {{ old('command_type') === null || old('command_type') === '' ? 'selected' : '' }}>Standard Command Permission</option>
<option value="custom" {{ old('command_type') === 'custom' ? 'selected' : '' }}>Custom Command</option>
</select>
<p class="text-xs text-slate-500">Choosing <code class="text-primary">Custom Command</code> will require script details and save a new permission entry.</p>
<div class="{{ old('command_type') === 'custom' ? '' : 'hidden' }} grid grid-cols-1 gap-4 pt-2" data-custom-command-fields>
<div class="space-y-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Script Name</label>
<input class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-transparent focus:ring-primary focus:border-primary" name="script_name" type="text" value="{{ old('script_name') }}" placeholder="e.g. custom_vlan_audit"/>
</div>
<div class="space-y-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Script Code</label>
<textarea class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-transparent focus:ring-primary focus:border-primary font-mono text-sm" name="script_code" rows="5" placeholder="#!/bin/bash&#10;echo \"custom command\"">{{ old('script_code') }}</textarea>
</div>
</div>
</div>
<div class="grid grid-cols-2 gap-6">
<div class="space-y-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Command Name</label>
<input class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-transparent focus:ring-primary focus:border-primary" id="command-name" name="name" type="text" value="{{ old('name') }}" required/>
</div>
<div class="space-y-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Target Device Group</label>
<select class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-transparent focus:ring-primary focus:border-primary">
<option>Smart Lighting - Internal</option>
<option>HVAC Controllers</option>
<option>Security Nodes</option>
</select>
</div>
</div>
<div class="space-y-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Description</label>
<textarea class="w-full rounded-lg border-slate-200 dark:border-slate-700 bg-transparent focus:ring-primary focus:border-primary" name="description" placeholder="Briefly describe what this command does..." rows="2">{{ old('description') }}</textarea>
</div>
</div>
</div>
<!-- Section 2: Control Type & UI Mapping -->
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden" data-command-builder-section>
<div class="p-6 border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/20">
<h2 class="text-lg font-bold">2. UI Component &amp; Logic</h2>
</div>
<div class="p-6 space-y-8">
<details class="group rounded-lg border border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-800/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-400 text-[11px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
UI Logic Help
</span>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-3 pb-3 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li>Pick a control type based on user intent: toggle for state, button for one-time action, slider/dropdown for ranged/enum inputs.</li>
<li>Keep JSON payload keys consistent with backend handlers and message broker schema.</li>
<li>Use <code>@{{value}}</code> only where runtime component value should be injected.</li>
</ol>
</div>
</details>
<div class="space-y-4">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Control Type</label>
<div class="grid grid-cols-4 gap-4">
<div class="border-2 border-primary bg-primary/5 p-4 rounded-xl flex flex-col items-center gap-2 cursor-pointer transition-all">
<span class="material-symbols-outlined text-primary text-3xl">toggle_on</span>
<span class="text-xs font-bold text-primary">Toggle Switch</span>
</div>
<div class="border-2 border-slate-100 dark:border-slate-800 p-4 rounded-xl flex flex-col items-center gap-2 cursor-pointer hover:border-slate-200 dark:hover:border-slate-700 transition-all">
<span class="material-symbols-outlined text-slate-400 text-3xl">linear_scale</span>
<span class="text-xs font-bold text-slate-500">Slider</span>
</div>
<div class="border-2 border-slate-100 dark:border-slate-800 p-4 rounded-xl flex flex-col items-center gap-2 cursor-pointer hover:border-slate-200 dark:hover:border-slate-700 transition-all">
<span class="material-symbols-outlined text-slate-400 text-3xl">ads_click</span>
<span class="text-xs font-bold text-slate-500">Action Button</span>
</div>
<div class="border-2 border-slate-100 dark:border-slate-800 p-4 rounded-xl flex flex-col items-center gap-2 cursor-pointer hover:border-slate-200 dark:hover:border-slate-700 transition-all">
<span class="material-symbols-outlined text-slate-400 text-3xl">list</span>
<span class="text-xs font-bold text-slate-500">Dropdown</span>
</div>
</div>
</div>
<div class="space-y-4">
<div class="flex items-center justify-between">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">JSON Payload Configuration</label>
<span class="text-[10px] bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded text-slate-500 font-mono">MQTT / HTTP POST</span>
</div>
<div class="relative group">
<div class="absolute right-4 top-4 z-10 opacity-0 group-hover:opacity-100 transition-opacity">
<button class="p-2 bg-slate-800 text-white rounded-lg hover:bg-slate-700" type="button" data-copy-target="#command-payload">
<span class="material-symbols-outlined text-sm">content_copy</span>
</button>
</div>
<textarea id="command-payload" class="w-full font-mono text-sm p-4 rounded-xl bg-slate-950 text-emerald-400 border-none focus:ring-2 focus:ring-primary" rows="6" spellcheck="false">{
  "device_id": "@{{device.id}}",
  "method": "set_power",
  "params": {
    "state": "@{{value}}",
    "transition": 300
  },
  "timestamp": "@{{system.now}}"
}</textarea>
</div>
<p class="text-[11px] text-slate-500">Use <code class="text-primary font-bold">@{{value}}</code> to inject the component state dynamically.</p>
</div>
</div>
</div>
<!-- Section 3: Permissions -->
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden" data-command-builder-section>
<div class="p-6 border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/20">
<h2 class="text-lg font-bold">3. Access &amp; Permissions</h2>
</div>
<div class="p-6 space-y-6">
<details class="group rounded-lg border border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-800/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-400 text-[11px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Permissions Help
</span>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-3 pb-3 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li>Limit command access to roles that operationally need it.</li>
<li>Enable confirmation or 2FA for risky commands that can impact service availability.</li>
<li>Keep execution logging enabled for traceability and incident reviews.</li>
</ol>
</div>
</details>
<div class="space-y-3">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Assign to Roles</label>
<div class="flex flex-wrap gap-2">
<span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 text-primary rounded-full text-xs font-bold">
                                    Super Admin <button class="material-symbols-outlined text-xs" type="button" data-chip-remove>close</button>
</span>
<span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 text-primary rounded-full text-xs font-bold">
                                    Floor Manager <button class="material-symbols-outlined text-xs" type="button" data-chip-remove>close</button>
</span>
<button class="inline-flex items-center gap-1 px-3 py-1.5 border border-dashed border-slate-300 dark:border-slate-600 rounded-full text-xs font-bold text-slate-400 hover:text-primary hover:border-primary transition-colors" type="button" data-chip-add>
<span class="material-symbols-outlined text-xs">add</span> Add Role
                                </button>
</div>
</div>
<div class="pt-4 space-y-4">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Safety &amp; Compliance</label>
<div class="space-y-3">
<label class="flex items-center gap-3 p-4 rounded-lg border border-slate-200 dark:border-slate-800 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50">
<input checked="" class="rounded text-primary focus:ring-primary" type="checkbox"/>
<div>
<p class="text-sm font-bold">Require confirmation before execution</p>
<p class="text-xs text-slate-500">Prompts user with "Are you sure?" before sending payload.</p>
</div>
</label>
<label class="flex items-center gap-3 p-4 rounded-lg border border-slate-200 dark:border-slate-800 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50">
<input class="rounded text-primary focus:ring-primary" type="checkbox"/>
<div>
<p class="text-sm font-bold">Two-Factor Authorization required</p>
<p class="text-xs text-slate-500">Critical commands require a mobile push notification approval.</p>
</div>
</label>
<label class="flex items-center gap-3 p-4 rounded-lg border border-slate-200 dark:border-slate-800 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50">
<input checked="" class="rounded text-primary focus:ring-primary" type="checkbox"/>
<div>
<p class="text-sm font-bold">Log execution history</p>
<p class="text-xs text-slate-500">Store who, when, and what was sent in the audit trail.</p>
</div>
</label>
</div>
</div>
</div>
</div>
<!-- Footer Actions (Duplicated for convenience) -->
<div class="flex items-center justify-end gap-4">
<a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-950/20 font-medium transition-colors" href="{{ route('auth.logout') }}">
<span class="material-symbols-outlined text-[20px]">logout</span>
<span class="text-sm">Logout</span>
</a>
<button class="text-sm font-bold text-slate-400 hover:text-slate-600 px-4 py-2" type="submit" form="command-form" formaction="{{ route('commands.preview') }}">Preview JSON Schema</button>
<button class="px-8 py-3 bg-primary text-white rounded-xl text-sm font-bold shadow-lg shadow-primary/30 hover:bg-blue-700 transition-all flex items-center gap-2" type="submit" form="command-form" formaction="{{ route('commands.deploy') }}">
<span class="material-symbols-outlined text-lg">save</span>
                        Save &amp; Deploy Command
                    </button>
</div>
</form>
</div>
</div>
</main>
</body></html>





