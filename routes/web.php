<?php

use App\Http\Controllers\ActionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebugController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DeviceWizardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScriptController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TelemetryController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('auth.login');
});

Route::get('/auth/login', [AuthController::class, 'loginForm'])->name('auth.login');
Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login.submit');
Route::match(['GET', 'POST'], '/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
Route::get('/auth/forgot', [AuthController::class, 'forgot'])->name('auth.forgot');
Route::post('/auth/forgot', [AuthController::class, 'sendResetLink'])->name('auth.forgot.submit');
Route::get('/auth/reset/{token}', [AuthController::class, 'showResetForm'])->name('auth.reset');
Route::post('/auth/reset', [AuthController::class, 'reset'])->name('auth.reset.submit');
Route::get('/auth/request-access', [AuthController::class, 'requestAccess'])->name('auth.request');

Route::middleware(['auth.session', 'audit.log'])->group(function () {
    Route::post('/actions/dispatch', [ActionController::class, 'dispatch'])->name('actions.dispatch');
    Route::get('/portal', [PortalController::class, 'index'])->name('portal.index');
    Route::get('/exec.php', [ScriptController::class, 'execLegacy'])->name('scripts.exec');
});

Route::middleware(['admin.auth', 'audit.log'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/menu', [NotificationController::class, 'menu'])->name('notifications.menu');
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/create', [DeviceController::class, 'create'])->name('devices.create');
    Route::get('/devices/details', [DeviceController::class, 'details'])->name('devices.details');
    Route::get('/devices/status-snapshot', [DeviceController::class, 'statusSnapshot'])->name('devices.statusSnapshot');
    Route::post('/devices/status-snapshot', [DeviceController::class, 'statusSnapshot'])->name('devices.statusSnapshot.post');
    Route::get('/devices/{device}/probe', [DeviceController::class, 'probeDevice'])->name('devices.probe');
    Route::get('/devices/assign', [DeviceWizardController::class, 'index'])->name('devices.wizard');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');

    Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::post('/devices/export', [DeviceController::class, 'export'])->name('devices.export');
    Route::post('/devices/filter', [DeviceController::class, 'filter'])->name('devices.filter');
    Route::post('/devices/history', [DeviceController::class, 'history'])->name('devices.history');
    Route::post('/devices/assign', [DeviceWizardController::class, 'assign'])->name('devices.assign');
    Route::post('/devices/{device}/update', [DeviceController::class, 'update'])->name('devices.update');
    Route::post('/devices/{device}/refresh', [DeviceController::class, 'refreshStatus'])->name('devices.refresh');
    Route::post('/devices/{device}/activate', [DeviceController::class, 'activate'])->name('devices.activate');
    Route::post('/devices/{device}/deactivate', [DeviceController::class, 'deactivate'])->name('devices.deactivate');
    Route::post('/devices/{device}/delete', [DeviceController::class, 'destroy'])->name('devices.delete');

    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::post('/users/export', [UserController::class, 'export'])->name('users.export');
    Route::post('/users/filter', [UserController::class, 'filter'])->name('users.filter');
    Route::match(['GET', 'POST'], '/users/status', [UserController::class, 'updateStatus'])->name('users.status');
    Route::post('/users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
    Route::post('/users/{user}/delete', [UserController::class, 'destroy'])->whereNumber('user')->name('users.delete');
    Route::post('/users/{user}/telegram/test', [UserController::class, 'telegramTest'])->whereNumber('user')->name('users.telegram.test');

    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
    Route::post('/notifications/filter', [NotificationController::class, 'filter'])->name('notifications.filter');
    Route::post('/notifications/archive', [NotificationController::class, 'archive'])->name('notifications.archive');
    Route::post('/notifications/dismiss', [NotificationController::class, 'dismiss'])->name('notifications.dismiss');
    Route::post('/notifications/investigate', [NotificationController::class, 'investigate'])->name('notifications.investigate');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::post('/support/auto-debug', [SupportController::class, 'autoDebug'])->name('support.auto-debug');
    Route::post('/support/run-diagnostic', [SupportController::class, 'runDiagnostic'])->name('support.run-diagnostic');
    Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::get('/telemetry', [TelemetryController::class, 'index'])->name('telemetry.index');
    Route::get('/showbackup.php', [ScriptController::class, 'backupLegacy'])->name('scripts.backup');
    Route::get('/devices/{device}/events', [ScriptController::class, 'showEventsPage'])->name('devices.events.show');
    Route::get('/devices/graphs', [ScriptController::class, 'showGraphsPage'])->name('devices.graphs');
    Route::get('/devices/{device}/backups', [ScriptController::class, 'showBackupsPage'])->name('devices.backups.show');
    Route::post('/devices/{device}/backups/run', [ScriptController::class, 'runBackupNow'])->name('devices.backups.run');
    Route::get('/devices/{device}/backups/list', [ScriptController::class, 'listBackups'])->name('devices.backups.index');
    Route::get('/devices/{device}/backups/{file}', [ScriptController::class, 'downloadBackup'])
        ->where('file', '[^/]+')
        ->name('devices.backups.download');
    Route::post('/debug/provisioning-log', [DebugController::class, 'toggleProvisioningLog'])->name('debug.provisioning-log');
    Route::get('/debug/provisioning-log', [DebugController::class, 'viewProvisioningLog'])->name('debug.provisioning-log.view');
});












