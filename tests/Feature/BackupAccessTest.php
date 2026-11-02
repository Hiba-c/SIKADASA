<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BackupAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_page_is_restricted_to_chairperson(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);

        $this->actingAs($ketua)->get('/backups')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('backups/index')
                ->has('backups'));

        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);
        $this->actingAs($bendahara)->get('/backups')->assertForbidden();
    }

    public function test_restore_requires_a_valid_backup_name_and_explicit_confirmation(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);

        $this->actingAs($ketua)->post('/backups/restore', [
            'backup' => '../outside.zip',
            'confirmation' => 'lanjut',
        ])->assertSessionHasErrors(['backup', 'confirmation']);
    }

    public function test_chairperson_can_create_a_backup_from_the_backup_page(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $this->mock(BackupService::class, function ($mock): void {
            $mock->shouldReceive('create')
                ->once()
                ->withNoArgs()
                ->andReturn(storage_path('app/private/backups/sikadasa-daily-20261101-190000.zip'));
            $mock->shouldReceive('prune')->once()->andReturn(0);
        });

        $this->actingAs($ketua)->post('/backups')
            ->assertRedirect('/backups')
            ->assertSessionHas('success', 'Backup berhasil dibuat: sikadasa-daily-20261101-190000.zip.');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $ketua->id,
            'module' => 'backups',
            'action' => 'created',
            'new_values->backup_name' => 'sikadasa-daily-20261101-190000.zip',
            'new_values->type' => 'manual',
        ]);
    }

    public function test_only_chairperson_can_create_a_backup(): void
    {
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);

        $this->actingAs($bendahara)->post('/backups')->assertForbidden();
    }

    public function test_backup_errors_return_to_the_page_with_a_clear_message(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $this->mock(BackupService::class, function ($mock): void {
            $mock->shouldReceive('create')
                ->once()
                ->andThrow(new \RuntimeException('Database connection unavailable.'));
        });

        $this->actingAs($ketua)->post('/backups')
            ->assertRedirect('/backups')
            ->assertSessionHas('error', 'Backup gagal dibuat. Pastikan koneksi database aktif dan folder penyimpanan dapat ditulis.');
    }

    public function test_chairperson_can_restore_a_backup_and_is_required_to_sign_in_again(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $this->mock(BackupService::class, function ($mock): void {
            $mock->shouldReceive('restore')
                ->once()
                ->with('sikadasa-daily-20261101-190000.zip')
                ->andReturn('sikadasa-pre-restore-20261101-190100.zip');
        });

        $this->actingAs($ketua)->post('/backups/restore', [
            'backup' => 'sikadasa-daily-20261101-190000.zip',
            'confirmation' => 'PULIHKAN',
        ])->assertRedirect('/login')
            ->assertSessionHas('success', 'Pemulihan selesai. Silakan masuk kembali.')
            ->assertSessionHas('info', 'Backup kondisi sebelum pemulihan disimpan sebagai sikadasa-pre-restore-20261101-190100.zip.');
    }
}
