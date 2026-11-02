<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_rendered_through_inertia(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
    }

    public function test_validation_errors_are_displayed_in_indonesian(): void
    {
        $this->from('/login')->post('/login', [
            'login' => '',
            'password' => '',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors([
                'login' => 'nama pengguna atau email wajib diisi.',
                'password' => 'kata sandi wajib diisi.',
            ]);
    }

    public function test_http_errors_show_their_status_code_in_indonesian(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Bendahara]))
            ->get('/reports/not-a-report')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/http')
                ->where('status', 404)
                ->where('title', 'Halaman tidak ditemukan'));
    }

    public function test_unexpected_errors_show_a_generic_500_without_internal_details(): void
    {
        $this->mock(ReportService::class, function ($mock): void {
            $mock->shouldReceive('build')->once()->andThrow(new \RuntimeException('private internal detail'));
        });

        $this->actingAs(User::factory()->create(['role' => UserRole::Bendahara]))
            ->get('/reports/cash')
            ->assertStatus(500)
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/http')
                ->where('status', 500)
                ->where('title', 'Terjadi kesalahan')
                ->where('message', 'Permintaan belum dapat diproses. Silakan coba kembali.'));
    }

    public function test_user_can_login_with_username_and_logout(): void
    {
        User::factory()->create([
            'username' => 'ketua',
            'password' => 'correct horse battery',
            'role' => UserRole::Ketua,
        ]);

        $this->post('/login', [
            'login' => 'ketua',
            'password' => 'correct horse battery',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'username' => 'ketua',
        ]);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_inactive_accounts_cannot_login(): void
    {
        User::factory()->inactive()->create([
            'username' => 'nonaktif',
            'password' => 'correct horse battery',
        ]);

        $this->from('/login')->post('/login', [
            'login' => 'nonaktif',
            'password' => 'correct horse battery',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_active_account_can_view_dashboard(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('dashboard/index'));
    }

    public function test_disabled_account_is_logged_out_on_authenticated_routes(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_password_can_be_changed_from_profile(): void
    {
        $user = User::factory()->create([
            'password' => 'original pass phrase',
            'is_active' => true,
        ]);

        $this->from('/profile')->actingAs($user)->put('/profile/password', [
            'current_password' => 'original pass phrase',
            'password' => 'new secure passphrase',
            'password_confirmation' => 'new secure passphrase',
        ])->assertRedirect('/profile')
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated();
        $this->assertTrue(password_verify('new secure passphrase', $user->fresh()->password));
    }
}