<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharedDatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_and_site_members_use_separate_tables(): void
    {
        $this->assertTrue(Schema::hasTable('staff_users'));
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasColumn('reservations', 'user_id'));
        $this->assertTrue(Schema::hasColumn('reservations', 'guest_zip_code'));
        $this->assertTrue(Schema::hasTable('news'));
        $this->assertTrue(Schema::hasColumn('news', 'title'));
        $this->assertTrue(Schema::hasColumn('news', 'content'));
        $this->assertTrue(Schema::hasColumn('news', 'published_at'));

        $staff = User::factory()->create([
            'email' => 'staff-schema@example.com',
        ]);

        $this->assertDatabaseHas('staff_users', [
            'id' => $staff->id,
            'email' => 'staff-schema@example.com',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'staff-schema@example.com',
        ]);
    }
}
