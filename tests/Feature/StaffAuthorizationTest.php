<?php

namespace Tests\Feature;

use App\Filament\Pages\PricingSettings;
use App\Filament\Resources\ReservationResource;
use App\Filament\Resources\ReservationResource\Pages\EditReservation;
use App\Filament\Resources\RoomDetailOptionResource;
use App\Filament\Resources\RoomFeatureOptionResource;
use App\Filament\Resources\UserResource;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_access_admin_only_screens_via_direct_url(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
        ]);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($staff);

        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get('/admin/users/'.$admin->id.'/edit')->assertForbidden();
        $this->get('/admin/room-feature-options')->assertForbidden();
        $this->get('/admin/room-feature-options/create')->assertForbidden();
        $this->get('/admin/room-detail-options')->assertForbidden();
        $this->get('/admin/room-detail-options/create')->assertForbidden();
        $this->get(PricingSettings::getUrl())->assertForbidden();

        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(RoomFeatureOptionResource::canViewAny());
        $this->assertFalse(RoomDetailOptionResource::canViewAny());
        $this->assertFalse(PricingSettings::canAccess());
    }

    public function test_admin_can_access_admin_only_screens(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($admin);

        $this->get('/admin/users')->assertOk();
        $this->get('/admin/room-feature-options')->assertOk();
        $this->get('/admin/room-detail-options')->assertOk();
        $this->get(PricingSettings::getUrl())->assertOk();
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(RoomFeatureOptionResource::canViewAny());
        $this->assertTrue(RoomDetailOptionResource::canViewAny());
        $this->assertTrue(PricingSettings::canAccess());
    }

    public function test_staff_cannot_delete_reservations(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
        ]);
        $reservation = $this->createReservation();

        $this->actingAs($staff);

        $this->assertFalse(ReservationResource::canDelete($reservation));
        $this->assertFalse(ReservationResource::canDeleteAny());

        Livewire::test(EditReservation::class, ['record' => $reservation->getRouteKey()])
            ->assertActionHidden('delete');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
        ]);
    }

    public function test_admin_can_delete_reservations(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $reservation = $this->createReservation();

        $this->actingAs($admin);

        $this->assertTrue(ReservationResource::canDelete($reservation));
        $this->assertTrue(ReservationResource::canDeleteAny());

        Livewire::test(EditReservation::class, ['record' => $reservation->getRouteKey()])
            ->assertActionVisible('delete')
            ->callAction('delete');

        $this->assertDatabaseMissing('reservations', [
            'id' => $reservation->id,
        ]);
    }

    private function createReservation(): Reservation
    {
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

        return Reservation::query()->create([
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
            'guest_name' => 'テスト太郎',
            'source' => 'manual',
        ]);
    }
}
