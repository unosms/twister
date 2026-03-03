<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Room;
use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\RecordAuditActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CabinetPlacementValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureAdminAuthenticated::class,
            RecordAuditActivity::class,
        ]);
    }

    public function test_it_prevents_same_face_overlap_in_a_cabinet(): void
    {
        $cabinet = $this->createCabinet();
        $deviceA = $this->createDevice('Overlap A');
        $deviceB = $this->createDevice('Overlap B');

        $this->postJson(route('devices.cabinet-room.placements.store', $cabinet), [
            'device_id' => $deviceA->id,
            'start_u' => 10,
            'height_u' => 2,
            'face' => 'front',
        ])->assertCreated();

        $this->postJson(route('devices.cabinet-room.placements.store', $cabinet), [
            'device_id' => $deviceB->id,
            'start_u' => 11,
            'height_u' => 2,
            'face' => 'front',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'The selected U range overlaps an existing device on this cabinet face.');
    }

    public function test_it_allows_the_same_u_range_on_the_opposite_face(): void
    {
        $cabinet = $this->createCabinet();
        $deviceA = $this->createDevice('Front Device');
        $deviceB = $this->createDevice('Back Device');

        $this->postJson(route('devices.cabinet-room.placements.store', $cabinet), [
            'device_id' => $deviceA->id,
            'start_u' => 20,
            'height_u' => 2,
            'face' => 'front',
        ])->assertCreated();

        $this->postJson(route('devices.cabinet-room.placements.store', $cabinet), [
            'device_id' => $deviceB->id,
            'start_u' => 20,
            'height_u' => 2,
            'face' => 'back',
        ])->assertCreated();
    }

    public function test_it_prevents_a_device_from_being_placed_twice(): void
    {
        $firstCabinet = $this->createCabinet('Room A', 'Rack A');
        $secondCabinet = $this->createCabinet('Room B', 'Rack B');
        $device = $this->createDevice('Only Once');

        $this->postJson(route('devices.cabinet-room.placements.store', $firstCabinet), [
            'device_id' => $device->id,
            'start_u' => 5,
            'height_u' => 1,
            'face' => 'front',
        ])->assertCreated();

        $this->postJson(route('devices.cabinet-room.placements.store', $secondCabinet), [
            'device_id' => $device->id,
            'start_u' => 7,
            'height_u' => 1,
            'face' => 'front',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'This device is already placed in another cabinet.');
    }

    private function createCabinet(string $roomName = 'Datacenter 1', string $cabinetName = 'Rack A')
    {
        $room = Room::create([
            'name' => $roomName,
            'location' => 'London',
        ]);

        return $room->cabinets()->create([
            'name' => $cabinetName,
            'size_u' => 42,
            'manufacturer' => 'APC',
            'model' => 'NetShelter',
        ]);
    }

    private function createDevice(string $name): Device
    {
        return Device::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'type' => 'SERVER',
            'model' => 'Dell R640',
            'serial_number' => Str::upper(Str::random(12)),
            'status' => 'online',
            'ip_address' => '10.0.0.' . random_int(10, 250),
            'metadata' => [
                'rack' => [
                    'height_u' => 1,
                ],
            ],
        ]);
    }
}
