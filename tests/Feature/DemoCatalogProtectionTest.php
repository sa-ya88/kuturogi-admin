<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\NewsResource;
use App\Filament\Resources\PlanResource;
use App\Filament\Resources\ReservationResource;
use App\Filament\Resources\RoomResource;
use App\Filament\Resources\UserResource;
use App\Models\Customer;
use App\Models\News;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCatalogProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_mode_blocks_master_and_record_deletes_for_admin(): void
    {
        config(['app.demo_mode' => true]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $this->actingAs($admin);

        $room = Room::query()->create([
            'name' => 'テスト客室',
            'price_per_person' => 10000,
            'is_active' => true,
        ]);
        $plan = Plan::query()->create([
            'name' => 'テストプラン',
            'price_per_person' => 10000,
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'name' => '山田 太郎',
            'email' => 'taro@example.com',
            'type' => Customer::TYPE_GUEST,
        ]);
        $news = News::query()->create([
            'title' => 'テストお知らせ',
            'content' => 'デモ削除禁止の確認用です。',
            'published_at' => now()->toDateString(),
        ]);
        $reservation = Reservation::query()->create([
            'room_id' => $room->id,
            'plan_id' => $plan->id,
            'checkin_date' => now()->toDateString(),
            'checkout_date' => now()->addDay()->toDateString(),
            'guest_count' => 2,
            'room_count' => 1,
            'adult_count' => 2,
            'child_count' => 0,
            'total_price' => 20000,
            'status' => Reservation::STATUS_CONFIRMED,
            'guest_name' => '山田 太郎',
            'source' => 'manual',
        ]);

        $this->assertFalse(RoomResource::canDelete($room));
        $this->assertFalse(PlanResource::canDelete($plan));
        $this->assertFalse(UserResource::canDelete($staff));
        $this->assertFalse(UserResource::canDeleteAny());
        $this->assertFalse(CustomerResource::canDelete($customer));
        $this->assertFalse(ReservationResource::canDelete($reservation));
        $this->assertTrue(NewsResource::canDelete($news));
    }

    public function test_demo_banner_explains_reset_and_delete_policy(): void
    {
        config(['app.demo_mode' => true]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('客室、プラン、料金、スタッフなどのマスタは削除できません')
            ->assertSee('時間ごとに初期化されます');
    }
}
