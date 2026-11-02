<?php

namespace Tests\Feature;

use App\Enums\ArisanCycleStatus;
use App\Enums\ArisanRoundStatus;
use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\ArisanCycle;
use App\Models\ArisanParticipant;
use App\Models\ArisanRound;
use App\Models\Loan;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_officers_can_view_searchable_member_list(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $member = Member::factory()->create(['full_name' => 'Siti Cempaka']);
        Member::factory()->create(['full_name' => 'Rina Mawar']);

        $this->actingAs($ketua)->get('/members?search=Cempaka&status=active')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('members/index')
                ->where('filters.search', 'Cempaka')
                ->where('members.data.0.id', $member->id)
                ->where('members.meta.total', 1));

        $this->actingAs(User::factory()->create(['role' => UserRole::Bendahara]))
            ->get('/members')
            ->assertOk();
    }

    public function test_creating_member_generates_code_and_savings_account(): void
    {
        $user = User::factory()->create(['role' => UserRole::Bendahara]);

        $this->actingAs($user)->post('/members', [
            'full_name' => 'Dewi Cempaka',
            'address' => 'Kagongan RT 46 RW 23',
            'house_number' => '46',
            'phone' => '081234567890',
            'joined_at' => '2026-10-30',
            'notes' => 'Anggota aktif Dasawisma.',
        ])->assertRedirect('/members')->assertSessionHas('success');

        $member = Member::query()->where('full_name', 'Dewi Cempaka')->firstOrFail();

        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertMatchesRegularExpression('/^DWS-\d{6}-\d{3,}$/', $member->registration_code);
        $this->assertSame($user->id, $member->created_by);
        $this->assertSame('Anggota aktif Dasawisma.', $member->notes);
        $this->assertDatabaseHas('savings_accounts', [
            'member_id' => $member->id,
            'current_balance' => '0.00',
        ]);
    }

    public function test_member_validation_requires_core_fields_without_nik(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/members/create')->post('/members', [
            'full_name' => '',
            'address' => 'Banjararum',
            'joined_at' => '2026-10-30',
        ])->assertSessionHasErrors('full_name');
    }

    public function test_member_can_be_updated_without_changing_registration_code(): void
    {
        $user = User::factory()->create();
        $member = Member::factory()->create(['full_name' => 'Nama Lama']);
        $registrationCode = $member->registration_code;

        $this->actingAs($user)->put("/members/{$member->id}", [
            'full_name' => 'Nama Baru',
            'address' => 'Alamat yang diperbarui',
            'house_number' => '47',
            'phone' => '0811111111',
            'joined_at' => $member->joined_at->toDateString(),
            'notes' => 'Catatan diperbarui.',
        ])->assertRedirect("/members/{$member->id}");

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'full_name' => 'Nama Baru',
            'registration_code' => $registrationCode,
            'notes' => 'Catatan diperbarui.',
        ]);
    }

    public function test_only_chairperson_can_change_member_status(): void
    {
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);
        $member = Member::factory()->create();

        $this->actingAs($bendahara)->post("/members/{$member->id}/deactivate", [
            'inactive_reason' => 'Pindah domisili',
        ])->assertForbidden();

        $this->assertSame(MemberStatus::Active, $member->fresh()->status);
    }

    public function test_member_with_unresolved_obligations_cannot_be_deactivated(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $member = Member::factory()->create();
        SavingsAccount::query()->create([
            'member_id' => $member->id,
            'current_balance' => '50000.00',
        ]);
        Loan::factory()->create([
            'member_id' => $member->id,
            'status' => LoanStatus::Active,
            'principal_amount' => '100000.00',
            'service_amount' => '1000.00',
            'total_obligation' => '101000.00',
        ]);
        $cycle = ArisanCycle::query()->create([
            'cycle_number' => 'ARS-TEST',
            'name' => 'Siklus Uji',
            'contribution_amount' => '20000.00',
            'status' => ArisanCycleStatus::Active,
            'started_at' => '2026-10-01',
            'created_by' => $ketua->id,
        ]);
        $participant = ArisanParticipant::query()->create([
            'cycle_id' => $cycle->id,
            'member_id' => $member->id,
        ]);
        ArisanRound::query()->create([
            'cycle_id' => $cycle->id,
            'round_number' => 1,
            'round_date' => '2026-10-31',
            'status' => ArisanRoundStatus::Open,
        ]);

        $this->actingAs($ketua)->get("/members/{$member->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deactivation_check.can_deactivate', false)
                ->where('deactivation_check.savings_balance', '50000.00')
                ->where('deactivation_check.loan_principal', '100000.00')
                ->where('deactivation_check.loan_service', '1000.00')
                ->where('deactivation_check.arisan_contributions', '20000.00'));

        $this->post("/members/{$member->id}/deactivate", [
            'inactive_reason' => 'Pindah domisili',
        ])->assertSessionHasErrors('deactivation');

        $this->assertSame(MemberStatus::Active, $member->fresh()->status);
        $this->assertDatabaseHas('arisan_participants', ['id' => $participant->id]);
    }

    public function test_member_with_no_obligations_can_be_deactivated_and_reactivated(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $member = Member::factory()->create();

        $this->actingAs($ketua)->post("/members/{$member->id}/deactivate", [
            'inactive_reason' => 'Pindah domisili',
        ])->assertRedirect("/members/{$member->id}");

        $this->assertSame(MemberStatus::Inactive, $member->fresh()->status);
        $this->assertSame('Pindah domisili', $member->fresh()->inactive_reason);

        $this->post("/members/{$member->id}/activate")
            ->assertRedirect("/members/{$member->id}");

        $this->assertSame(MemberStatus::Active, $member->fresh()->status);
        $this->assertNull($member->fresh()->inactive_at);
        $this->assertNull($member->fresh()->inactive_reason);
    }

    public function test_member_history_page_is_rendered(): void
    {
        $member = Member::factory()->create();

        $this->actingAs(User::factory()->create())->get("/members/{$member->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('members/show')
                ->where('member.id', $member->id)
                ->where('member.created_at', fn ($value) => is_string($value) && str_contains($value, 'T'))
                ->where('savings.balance', '0.00')
                ->where('loans', [])
                ->where('arisan', [])
                ->where('deactivation_check', null));
    }

    public function test_member_schema_no_longer_stores_nik(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('members', 'nik'));
    }
}