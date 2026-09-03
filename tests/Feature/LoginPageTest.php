<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_admin(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_guest_login_button_is_not_shown(): void
    {
        config(['app.demo_mode' => true]);

        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('ゲストとしてログイン');
    }

    public function test_login_page_shows_title_image_instead_of_duplicate_app_name(): void
    {
        $html = $this->get('/admin/login')
            ->assertOk()
            ->assertSee('images/login-title.webp', false)
            ->assertSee('images/favicon.webp', false)
            ->getContent();

        $this->assertSame(
            1,
            substr_count(strip_tags($html), (string) config('app.name')),
            'App name should appear only in the document title, not as duplicate on-page text.'
        );
    }

    public function test_demo_password_notice_is_shown_when_demo_mode_is_on(): void
    {
        config(['app.demo_mode' => true]);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('架空のデモユーザーでログインしてください')
            ->assertSee('パスワードは「demo」です')
            ->assertSee('客室、プラン、料金、スタッフは削除できません')
            ->assertSee('時間ごとに初期化されます');
    }

    public function test_demo_password_notice_is_hidden_when_demo_mode_is_off(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('パスワードは「demo」です');
    }

    public function test_wrong_password_shows_an_error_instead_of_not_found(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'login_id' => $user->login_id,
                'password' => 'not-the-password',
            ])
            ->call('authenticate')
            ->assertHasErrors(['data.password'])
            ->assertNoRedirect()
            ->assertOk();

        $this->assertGuest();
    }
}
