<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\News;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomInventory;
use App\Models\RoomUnit;
use App\Models\RoomUnitDateOccupancy;
use App\Models\User;
use App\Support\DemoMode;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_guest_user_and_fictional_data(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertDatabaseHas('staff_users', [
            'email' => User::DEMO_EMAIL,
            'login_id' => User::DEMO_LOGIN_ID,
            'role' => User::ROLE_ADMIN,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'guest@example.com',
            'name' => 'ゲスト 太郎',
        ]);

        $this->assertDatabaseHas('customers', [
            'email' => DemoMode::dummyEmail(),
            'name' => DemoMode::dummyName(),
        ]);

        $count = Customer::query()->count();
        $this->assertGreaterThanOrEqual(30, $count);
        $this->assertFalse(
            Customer::query()->where('email', 'like', '%gmail.com')->exists()
        );

        $this->assertGreaterThanOrEqual(3, News::query()->count());
        $this->assertDatabaseHas('news', [
            'title' => '春の特別会席のご案内',
        ]);

        $this->assertTrue(Reservation::query()->where('source', 'demo')->exists());
        $this->assertGreaterThanOrEqual(
            1,
            Reservation::query()
                ->where('source', 'demo')
                ->where('status', Reservation::STATUS_CONFIRMED)
                ->whereDate('checkout_date', Carbon::today()->toDateString())
                ->count()
        );
        $this->assertGreaterThan(
            1,
            Reservation::query()->where('source', 'demo')->pluck('plan_id')->unique()->count()
        );
    }

    public function test_demo_occupancy_is_spread_across_units_of_the_same_room_type(): void
    {
        $this->seed(DemoSeeder::class);

        $from = Carbon::today();
        $until = Carbon::today()->addMonth();

        foreach (Room::query()->orderBy('id')->get() as $room) {
            if ($room->inServiceUnitsCount() < 2) {
                continue;
            }

            $occupiedUnits = RoomUnitDateOccupancy::query()
                ->whereDate('date', '>=', $from->toDateString())
                ->whereDate('date', '<', $until->toDateString())
                ->whereHas(
                    'roomUnit',
                    fn ($query) => $query
                        ->where('room_id', $room->id)
                        ->where('operation_status', RoomUnit::OPERATION_IN_SERVICE)
                )
                ->pluck('room_unit_id')
                ->unique()
                ->count();

            $this->assertGreaterThan(
                1,
                $occupiedUnits,
                "Occupancy for {$room->name} should not stay on a single unit."
            );
        }
    }

    public function test_demo_customers_follow_member_repeater_and_vip_ratios(): void
    {
        $this->seed(DemoSeeder::class);

        $segmented = fn () => Customer::query()->where('email', '!=', 'guest@example.com');
        $count = $segmented()->count();

        $this->assertSame((int) round($count * 0.3), $segmented()->members()->count());
        $this->assertSame((int) round($count * 0.7), $segmented()->guests()->count());
        $this->assertSame((int) round($count * 0.3), $segmented()->repeaters()->count());
        $this->assertSame((int) round($count * 0.1), $segmented()->vip()->count());
    }

    public function test_demo_seeder_fills_about_eighty_percent_occupancy_for_one_month(): void
    {
        $this->seed(DemoSeeder::class);

        $inService = RoomUnit::query()
            ->where('operation_status', RoomUnit::OPERATION_IN_SERVICE)
            ->count();
        $expectedOccupied = (int) round($inService * 0.8);

        $from = Carbon::today();
        $until = Carbon::today()->addMonth();

        for ($date = $from->copy(); $date->lt($until); $date->addDay()) {
            $occupied = RoomUnitDateOccupancy::query()
                ->whereDate('date', $date->toDateString())
                ->whereHas(
                    'roomUnit',
                    fn ($query) => $query->where('operation_status', RoomUnit::OPERATION_IN_SERVICE)
                )
                ->count();

            $this->assertSame(
                $expectedOccupied,
                $occupied,
                "Occupancy on {$date->toDateString()} should be {$expectedOccupied} of {$inService} in-service rooms."
            );
        }
    }

    public function test_demo_seeder_can_be_run_again_without_duplicating_demo_data(): void
    {
        $this->seed(DemoSeeder::class);

        $customerCount = Customer::query()->count();
        $reservationCount = Reservation::query()->where('source', 'demo')->count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($customerCount, Customer::query()->count());
        $this->assertSame(
            $reservationCount,
            Reservation::query()->where('source', 'demo')->count()
        );
    }

    public function test_demo_seeder_inventory_remains_match_occupancy(): void
    {
        $this->seed(DemoSeeder::class);

        $from = Carbon::today();
        $to = Carbon::today()->addDays(60);

        foreach (Room::query()->orderBy('id')->get() as $room) {
            $stock = $room->inServiceUnitsCount();

            $this->assertFalse(
                RoomInventory::query()
                    ->where('room_id', $room->id)
                    ->where(function ($query) use ($from, $to): void {
                        $query->whereDate('date', '<', $from->toDateString())
                            ->orWhereDate('date', '>', $to->toDateString());
                    })
                    ->exists(),
                "Inventory for {$room->name} should stay within the seeded date range."
            );

            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                $occupied = RoomUnitDateOccupancy::query()
                    ->whereDate('date', $date->toDateString())
                    ->whereHas(
                        'roomUnit',
                        fn ($query) => $query
                            ->where('room_id', $room->id)
                            ->where('operation_status', RoomUnit::OPERATION_IN_SERVICE)
                    )
                    ->count();

                $inventory = RoomInventory::query()
                    ->where('room_id', $room->id)
                    ->whereDate('date', $date->toDateString())
                    ->first();

                $this->assertNotNull($inventory, "Missing inventory for {$room->name} on {$date->toDateString()}");
                $this->assertSame(
                    max(0, $stock - $occupied),
                    (int) $inventory->remains,
                    "Remains for {$room->name} on {$date->toDateString()} should match occupancy."
                );
            }
        }
    }
}
