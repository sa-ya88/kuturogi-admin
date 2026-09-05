<?php

namespace Tests\Feature;

use App\Filament\Resources\NewsResource\Pages\CreateNews;
use App\Filament\Resources\NewsResource\Pages\EditNews;
use App\Models\News;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NewsResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_news(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($admin);

        Livewire::test(CreateNews::class)
            ->fillForm([
                'title' => '露天風呂の点検のお知らせ',
                'content' => '明日は午前中、露天風呂の点検を行います。',
                'published_at' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('news', [
            'title' => '露天風呂の点検のお知らせ',
            'content' => '明日は午前中、露天風呂の点検を行います。',
        ]);

        $news = News::query()->firstOrFail();

        Livewire::test(EditNews::class, ['record' => $news->getRouteKey()])
            ->fillForm([
                'title' => '露天風呂の点検時間変更のお知らせ',
                'content' => '点検は午後に変更しました。',
                'published_at' => now()->toDateString(),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('news', [
            'id' => $news->id,
            'title' => '露天風呂の点検時間変更のお知らせ',
            'content' => '点検は午後に変更しました。',
        ]);
    }

    public function test_staff_cannot_create_news(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
        ]);

        $this->actingAs($staff);

        Livewire::test(CreateNews::class)
            ->assertForbidden();
    }
}
