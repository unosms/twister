<?php

namespace Database\Seeders;

use App\Models\CommandTemplate;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminPayload = [
            'name' => 'admin',
            'role' => 'admin',
            'password' => 'admin123',
        ];
        if (User::supportsPasswordRevealStorage()) {
            $adminPayload['password_reveal'] = 'admin123';
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin'],
            $adminPayload
        );

        $testUserPayload = [
            'name' => 'testuser',
            'role' => 'user',
            'password' => 'Test1234',
        ];
        if (User::supportsPasswordRevealStorage()) {
            $testUserPayload['password_reveal'] = 'Test1234';
        }

        $testUser = User::updateOrCreate(
            ['email' => 'test.user@company.com'],
            $testUserPayload
        );

        $commands = [
            ['name' => 'Toggle Power', 'action_key' => 'device_toggle_power'],
            ['name' => 'Restart Device', 'action_key' => 'device_restart'],
            ['name' => 'Device Settings', 'action_key' => 'device_settings'],
            ['name' => 'Device Status', 'action_key' => 'device_status'],
            ['name' => 'Reboot Device', 'action_key' => 'device_reboot'],
            ['name' => 'Open CLI', 'action_key' => 'device_cli'],
            ['name' => 'Live Feed', 'action_key' => 'device_live_feed'],
            ['name' => 'View Logs', 'action_key' => 'device_logs'],
            ['name' => 'Fan Control', 'action_key' => 'device_fan'],
            ['name' => 'Timer Control', 'action_key' => 'device_timer'],
            ['name' => 'View Stats', 'action_key' => 'device_stats'],
        ];

        $commandIds = [];
        foreach ($commands as $command) {
            $template = CommandTemplate::updateOrCreate(
                ['action_key' => $command['action_key']],
                [
                    'name' => $command['name'],
                    'description' => $command['name'] . ' command',
                    'ui_type' => 'button',
                    'active' => true,
                    'created_by' => $admin->id,
                ]
            );
            $commandIds[] = $template->id;
        }

        foreach ($commandIds as $commandId) {
            DB::table('command_template_user')->updateOrInsert([
                'command_template_id' => $commandId,
                'user_id' => $testUser->id,
            ], [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $devices = [
            [
                'name' => 'Gateway-HQ-01',
                'serial_number' => 'GT-8821-X9',
                'type' => 'CISCO',
                'status' => 'online',
                'last_seen_at' => now(),
            ],
            [
                'name' => 'Sensor-Warehouse-A4',
                'serial_number' => 'SN-1102-K2',
                'type' => 'MIMOSA',
                'status' => 'error',
                'last_seen_at' => now()->subHour(),
            ],
            [
                'name' => 'Controller-North-B1',
                'serial_number' => 'CT-5541-M0',
                'type' => 'OLT',
                'status' => 'offline',
                'last_seen_at' => now()->subMinutes(5),
            ],
            [
                'name' => 'Gateway-HQ-02',
                'serial_number' => 'GT-8822-X9',
                'type' => 'CISCO',
                'status' => 'online',
                'last_seen_at' => now(),
            ],
        ];

        foreach ($devices as $device) {
            Device::updateOrCreate(
                ['serial_number' => $device['serial_number']],
                [
                    'uuid' => Str::uuid()->toString(),
                    'name' => $device['name'],
                    'type' => $device['type'],
                    'status' => $device['status'],
                    'last_seen_at' => $device['last_seen_at'],
                ]
            );
        }
    }
}
