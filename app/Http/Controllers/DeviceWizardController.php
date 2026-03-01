<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\User;
use Illuminate\Http\Request;

class DeviceWizardController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')
            ->withCount('devices')
            ->get();
        $devices = Device::with('assignedUser')
            ->orderBy('name')
            ->get();

        $selectedUserId = request()->query('user_id');
        $selectedUser = null;
        if ($selectedUserId) {
            $selectedUser = $users->firstWhere('id', (int) $selectedUserId);
        }
        if (!$selectedUser) {
            $selectedUser = $users->first();
        }

        $assignmentMap = DeviceAssignment::whereNull('unassigned_at')
            ->pluck('user_id', 'device_id')
            ->all();

        $selectedUserDeviceCount = 0;
        $assignedDevices = collect();
        if ($selectedUser) {
            foreach ($devices as $device) {
                $assignedUserId = $assignmentMap[$device->id] ?? $device->assigned_user_id;
                if ($assignedUserId === $selectedUser->id) {
                    $selectedUserDeviceCount += 1;
                    $assignedDevices->push($device);
                }
            }
        }

        return view('device_management_wizard', [
            'users' => $users,
            'devices' => $devices,
            'selectedUser' => $selectedUser,
            'assignmentMap' => $assignmentMap,
            'userLookup' => $users->keyBy('id'),
            'selectedUserDeviceCount' => $selectedUserDeviceCount,
            'assignedDevices' => $assignedDevices,
        ]);
    }

    public function assign(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['nullable', 'integer'],
            'device' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer'],
            'user_email' => ['nullable', 'email'],
        ]);

        $deviceLookup = $data['device_id'] ?? $data['device'] ?? null;
        if (!$deviceLookup) {
            return back()->with('status', 'Device not found.');
        }

        $device = Device::where('serial_number', $deviceLookup)
            ->orWhere('id', $deviceLookup)
            ->first();

        if (!$device) {
            return back()->with('status', 'Device not found.');
        }

        $user = null;
        if (!empty($data['user_id'])) {
            $user = User::find($data['user_id']);
        } elseif (!empty($data['user_email'])) {
            $user = User::where('email', $data['user_email'])->first();
        }

        if (!$user) {
            return back()->with('status', 'User not found.');
        }

        DeviceAssignment::where('device_id', $device->id)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        DeviceAssignment::create([
            'device_id' => $device->id,
            'user_id' => $user->id,
            'assigned_by' => $request->session()->get('auth.user_id'),
            'assigned_at' => now(),
        ]);

        $device->update(['assigned_user_id' => $user->id]);

        return back()->with('status', "Device {$device->serial_number} assigned to {$user->email}.");
    }
}
