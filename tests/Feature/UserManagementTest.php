<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_chairperson_can_view_and_manage_user_accounts(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);

        $this->actingAs($bendahara)->get('/users')->assertForbidden();
        $this->get('/users/create')->assertForbidden();

        $this->actingAs($ketua)->get('/users')->assertOk();
        $this->get('/users/create')->assertOk();
        $this->get("/users/{$bendahara->id}/edit")->assertOk();
        $this->get("/users/{$ketua->id}/edit")->assertForbidden();
    }

    public function test_chairperson_can_create_and_update_user_without_exposing_password(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $this->actingAs($ketua)->post('/users', [
            'name' => 'Petugas Baru',
            'username' => 'petugas-baru',
            'email' => 'petugas@example.test',
            'phone' => '08123456789',
            'role' => 'bendahara',
            'password' => 'New-Secure-Password-123',
            'password_confirmation' => 'New-Secure-Password-123',
        ])->assertRedirect('/users')->assertSessionHasNoErrors();

        $user = User::query()->where('username', 'petugas-baru')->firstOrFail();
        $this->assertTrue(Hash::check('New-Secure-Password-123', $user->password));
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'users',
            'action' => 'created',
            'entity_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('audit_logs', ['new_values' => 'New-Secure-Password-123']);

        $this->put("/users/{$user->id}", [
            'name' => 'Petugas Diperbarui',
            'username' => 'petugas-baru',
            'email' => 'petugas@example.test',
            'phone' => '',
            'role' => 'ketua',
            'password' => 'Updated-Secure-Password-456',
            'password_confirmation' => 'Updated-Secure-Password-456',
        ])->assertRedirect('/users')->assertSessionHasNoErrors();

        $this->assertSame(UserRole::Ketua, $user->fresh()->role);
        $this->assertTrue(Hash::check('Updated-Secure-Password-456', $user->fresh()->password));
        $log = AuditLog::query()->where('entity_id', $user->id)->latest('id')->firstOrFail();
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
    }

    public function test_current_chair_cannot_deactivate_or_demote_their_own_account(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);

        $this->actingAs($ketua)->post("/users/{$ketua->id}/deactivate")->assertForbidden();
        $this->put("/users/{$ketua->id}", [
            'name' => $ketua->name,
            'username' => $ketua->username,
            'email' => $ketua->email,
            'phone' => $ketua->phone,
            'role' => 'bendahara',
            'password' => '',
            'password_confirmation' => '',
        ])->assertForbidden();

        $this->post("/users/{$bendahara->id}/deactivate")->assertRedirect('/users')->assertSessionHasNoErrors();
        $this->assertFalse($bendahara->fresh()->is_active);
        $this->post("/users/{$bendahara->id}/activate")->assertRedirect('/users')->assertSessionHasNoErrors();
        $this->assertTrue($bendahara->fresh()->is_active);
    }

    public function test_chairperson_can_manage_another_chair_but_not_their_own_account(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua, 'username' => 'ketua-utama']);
        $secondChair = User::factory()->create(['role' => UserRole::Ketua, 'username' => 'ketua-kedua']);
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);

        $this->actingAs($ketua)->post("/users/{$secondChair->id}/deactivate")
            ->assertRedirect('/users')
            ->assertSessionHasNoErrors();
        $this->post("/users/{$ketua->id}/deactivate")->assertForbidden();

        $this->post("/users/{$secondChair->id}/activate")->assertRedirect('/users');
        $this->put("/users/{$secondChair->id}", [
            'name' => $secondChair->name,
            'username' => $secondChair->username,
            'email' => $secondChair->email,
            'phone' => $secondChair->phone,
            'role' => 'bendahara',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect('/users')->assertSessionHasNoErrors();

        $this->put("/users/{$ketua->id}", [
            'name' => $ketua->name,
            'username' => $ketua->username,
            'email' => $ketua->email,
            'phone' => $ketua->phone,
            'role' => 'bendahara',
            'password' => '',
            'password_confirmation' => '',
        ])->assertForbidden();
        $this->assertSame(UserRole::Bendahara, $bendahara->fresh()->role);
    }
}
