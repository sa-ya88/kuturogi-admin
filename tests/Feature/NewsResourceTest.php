<?php

namespace Tests\Feature;

use App\Filament\Resources\NewsResource;
use App\Filament\Resources\NewsResource\Pages\CreateNews;
use App\Filament\Resources\NewsResource\Pages\EditNews;
use App\Filament\Resources\NewsResource\Pages\ListNews;
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

    public function test_admin_can_delete_news_even_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $news = News::query()->create([
            'title' => '削除するお知らせ',
            'content' => 'この投稿は削除できます。',
            'published_at' => now()->toDateString(),
        ]);

        $this->actingAs($admin);

        $this->assertTrue(NewsResource::canDelete($news));
        $this->assertTrue(NewsResource::canDeleteAny());

        Livewire::test(ListNews::class)
            ->assertTableActionVisible('delete', $news)
            ->callTableAction('delete', $news);

        $this->assertDatabaseMissing('news', [
            'id' => $news->id,
        ]);
    }

    public function test_staff_cannot_delete_news(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
        ]);
        $news = News::query()->create([
            'title' => '閲覧のみのお知らせ',
            'content' => 'スタッフは削除できません。',
            'published_at' => now()->toDateString(),
        ]);

        $this->actingAs($staff);

        $this->assertFalse(NewsResource::canDelete($news));

        Livewire::test(ListNews::class)
            ->assertTableActionHidden('delete', $news);
    }
}
