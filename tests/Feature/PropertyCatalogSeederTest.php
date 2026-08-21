<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Room;
use App\Models\RoomUnit;
use Database\Seeders\PropertyCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeder_creates_rooms_units_and_plans(): void
    {
        $this->seed(PropertyCatalogSeeder::class);

        $this->assertSame(5, Room::query()->count());
        $this->assertSame(11, RoomUnit::query()->count());
        $this->assertSame(5, Plan::query()->count());

        $standard = Room::query()->where('name', 'スタンダード和室')->firstOrFail();
        $this->assertSame(6000, $standard->price_per_person);
        $this->assertSame(3, $standard->units()->count());

        $outOfService = RoomUnit::query()->where('code', '103')->firstOrFail();
        $this->assertSame(RoomUnit::OPERATION_OUT_OF_SERVICE, $outOfService->operation_status);
        $this->assertSame('水漏れ修理中（月末まで）', $outOfService->notes);

        $earlyBird = Plan::query()->where('name', '【早割30】30日前までの予約でお得なプラン')->firstOrFail();
        $this->assertTrue($earlyBird->has_early_bird);
        $this->assertSame(2, $earlyBird->rooms()->count());

        $detached = Plan::query()->where('name', '【食事なし】素泊まりプラン')->firstOrFail();
        $this->assertFalse($detached->rooms()->where('name', '離れ「茜」- AKANE -')->exists());
    }
}
