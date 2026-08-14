<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads_filament_static_assets(): void
    {
        User::factory()->create();

        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('/js/filament/filament/app.js', false);
        $response->assertSee('/css/filament/filament/app.css', false);
        $response->assertDontSee('build/assets/calendars', false);
    }

    public function test_inventory_calendar_loads_filament_and_vite_assets(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($user)->get('/admin/inventory-calendar');

        $response->assertOk();
        $response->assertSee('/js/filament/filament/app.js', false);
        $response->assertSee('/css/filament/filament/app.css', false);
        $response->assertSee('build/assets/calendars', false);
        $response->assertSee('kuturogi-calendar', false);
    }

    public function test_reservation_calendar_loads_filament_and_vite_assets(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($user)->get('/admin/reservations');

        $response->assertOk();
        $response->assertSee('/js/filament/filament/app.js', false);
        $response->assertSee('/css/filament/filament/app.css', false);
        $response->assertSee('build/assets/calendars', false);
        $response->assertSee('kuturogi-calendar', false);
    }
}
