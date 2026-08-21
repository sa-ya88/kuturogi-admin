<?php

namespace Tests\Feature;

use App\Filament\Pages\InventoryCalendar;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Services\ReservationStayService;
use Database\Seeders\PropertyCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryCalendarAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_calendar_tip_lists_vacant_unit_numbers(): void
    {
        $this->seed(PropertyCatalogSeeder::class);

        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $room = Room::query()->where('name', 'スタンダード和室')->firstOrFail();
        $occupied = RoomUnit::query()->where('code', '102')->firstOrFail();
        $plan = Plan::query()->firstOrFail();
        $today = now()->toDateString();

        $reservation = Reservation::query()->create([
            'room_id' => $room->id,
            'plan_id' => $plan->id,
            'checkin_date' => $today,
            'checkout_date' => now()->addDay()->toDateString(),
            'guest_count' => 2,
            'room_count' => 1,
            'adult_count' => 2,
            'child_count' => 0,
            'total_price' => 20000,
            'status' => Reservation::STATUS_CONFIRMED,
            'guest_name' => 'テスト太郎',
            'source' => 'manual',
        ]);

        $stayService = app(ReservationStayService::class);
        $stayService->syncStaysForReservation($reservation);
        $stay = $reservation->fresh('stays')->stays->first();
        $stayService->assignUnit($stay, $occupied->id);

        $data = Livewire::actingAs($user)
            ->test(InventoryCalendar::class)
            ->instance()
            ->getCalendarData();

        $row = collect($data['rows'])->firstWhere('room', 'スタンダード和室');
        $this->assertNotNull($row);
        $this->assertSame('空き号室: 101号室', $row['cells'][$today]['tip']);
    }

    public function test_inventory_management_screen_is_removed(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($user)
            ->get('/admin/room-inventories')
            ->assertNotFound();
    }
}
