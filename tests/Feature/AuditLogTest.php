<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_is_limited_to_chairperson_and_can_be_filtered(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);
        app(AuditService::class)->record('members', 'created', null, null, ['full_name' => 'Anggota A']);
        app(AuditService::class)->record('cash', 'created', null, null, ['amount' => '10000.00']);

        $this->actingAs($bendahara)->get('/audit-logs')
            ->assertForbidden()
            ->assertInertia(fn ($page) => $page
                ->component('errors/http')
                ->where('status', 403)
                ->where('title', 'Akses ditolak'));

        $this->actingAs($ketua)->get('/audit-logs?module=members&search=created')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('audit-logs/index')
                ->where('logs.meta.total', 1)
                ->where('logs.data.0.module', 'members')
                ->where('logs.data.0.new_values.full_name', 'Anggota A'));
    }

    public function test_member_activity_and_authentication_events_are_audited_without_passwords(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Ketua,
            'username' => 'ketua-audit',
            'password' => Hash::make('Password-Audit-123!'),
        ]);

        $this->post('/login', [
            'login' => 'ketua-audit',
            'password' => 'Password-Audit-123!',
        ])->assertRedirect('/dashboard');
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'auth',
            'action' => 'login',
        ]);

        $this->post('/members', [
            'full_name' => 'Anggota Audit',
            'address' => 'Banjararum',
            'joined_at' => '2026-10-31',
        ])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'members',
            'action' => 'created',
        ]);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'auth',
            'action' => 'logout',
        ]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Password-Audit-123!']);

        $memberLog = AuditLog::query()->where('module', 'members')->firstOrFail();
        $this->assertArrayNotHasKey('nik', $memberLog->new_values ?? []);
    }

    public function test_audit_log_pagination_matches_the_frontend_response_shape(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);

        for ($index = 1; $index <= 31; $index++) {
            app(AuditService::class)->record('members', 'created', null, null, ['full_name' => "Anggota {$index}"]);
        }

        $this->actingAs($ketua)->get('/audit-logs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('logs.meta.total', 31)
                ->where('logs.meta.last_page', 2)
                ->has('logs.meta.links')
                ->where('logs.links.next', fn ($url) => str_contains($url, 'page=2')));
    }
}
