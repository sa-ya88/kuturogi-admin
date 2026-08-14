<?php

namespace Tests\Feature;

use App\Filament\Pages\InventoryCalendar;
use App\Filament\Resources\ReservationResource\Pages\ListReservations;
use App\Filament\Resources\ReservationResource\Pages\ReservationCalendar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarLockedPropertiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_calendar_locks_applied_filter_state(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(InventoryCalendar::class)
            ->assertSet('appliedFrom', now()->format('Y-m-d'))
            ->tap(function ($component): void {
                $this->expectException(CannotUpdateLockedPropertyException::class);
                $component->set('appliedFrom', '2099-01-01');
            });
    }

    public function test_reservation_calendar_locks_applied_filter_state(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ReservationCalendar::class)
            ->assertSet('appliedRowMode', 'room')
            ->tap(function ($component): void {
                $this->expectException(CannotUpdateLockedPropertyException::class);
                $component->set('appliedRowMode', 'plan');
            });
    }

    public function test_reservation_list_locks_query_filters_from_mount(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->withQueryParams(['room_id' => 5, 'date' => '2026-08-01'])
            ->test(ListReservations::class)
            ->assertSet('filterRoomId', 5)
            ->assertSet('filterDate', '2026-08-01')
            ->tap(function ($component): void {
                $this->expectException(CannotUpdateLockedPropertyException::class);
                $component->set('filterRoomId', 999);
            });
    }
}
