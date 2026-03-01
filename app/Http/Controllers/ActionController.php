<?php

namespace App\Http\Controllers;

use App\Models\CommandTemplate;
use App\Models\DevicePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActionController extends Controller
{
    public function dispatch(Request $request)
    {
        $action = (string) $request->input('action', 'unknown');
        $role = $request->session()->get('auth.role');
        $userId = $request->session()->get('auth.user_id');

        if ($role === 'user') {
            $template = CommandTemplate::where('action_key', $action)->first();
            if (!$template) {
                return response()->json([
                    'status' => 'forbidden',
                    'reason' => 'Command not assigned.',
                ], 403);
            }

            if ($request->filled('device_id')) {
                $permissionColumns = ['device_id'];
                if (DevicePermission::supportsAllowedCommandTemplateIds()) {
                    $permissionColumns[] = 'allowed_command_template_ids';
                }

                $permissionRow = DB::table('device_permissions')
                    ->where('device_id', $request->input('device_id'))
                    ->where('user_id', $userId)
                    ->first($permissionColumns);

                $deviceAssigned = DB::table('device_assignments')
                    ->where('device_id', $request->input('device_id'))
                    ->where('user_id', $userId)
                    ->whereNull('unassigned_at')
                    ->exists();

                if (!$deviceAssigned) {
                    $deviceAssigned = DB::table('devices')
                        ->where('id', $request->input('device_id'))
                        ->where('assigned_user_id', $userId)
                        ->exists();
                }

                if (!$deviceAssigned) {
                    $deviceAssigned = $permissionRow !== null;
                }

                if (!$deviceAssigned) {
                    return response()->json([
                        'status' => 'forbidden',
                        'reason' => 'Device not assigned.',
                    ], 403);
                }

                $allowedCommandTemplateIds = DevicePermission::supportsAllowedCommandTemplateIds()
                    ? DevicePermission::decodeAllowedCommandTemplateIds($permissionRow?->allowed_command_template_ids ?? null)
                    : [];

                if (!empty($allowedCommandTemplateIds) && !in_array((int) $template->id, $allowedCommandTemplateIds, true)) {
                    return response()->json([
                        'status' => 'forbidden',
                        'reason' => 'Command not permitted for this device.',
                    ], 403);
                }

                if (!empty($allowedCommandTemplateIds)) {
                    return response()->json([
                        'status' => 'ok',
                        'action' => $action,
                        'source' => $request->input('source'),
                        'received_at' => now()->toISOString(),
                    ]);
                }
            }

            $assigned = DB::table('command_template_user')
                ->where('command_template_id', $template->id)
                ->where('user_id', $userId)
                ->exists();

            if (!$assigned) {
                return response()->json([
                    'status' => 'forbidden',
                    'reason' => 'Command not assigned.',
                ], 403);
            }
        }

        return response()->json([
            'status' => 'ok',
            'action' => $action,
            'source' => $request->input('source'),
            'received_at' => now()->toISOString(),
        ]);
    }
}
