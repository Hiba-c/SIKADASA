<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CashTransaction;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_chairperson_can_approve_loans_or_change_member_status(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);
        $loan = Loan::factory()->create();
        $member = Member::factory()->create();

        $this->assertTrue(Gate::forUser($ketua)->allows('approve', $loan));
        $this->assertFalse(Gate::forUser($bendahara)->allows('approve', $loan));
        $this->assertTrue(Gate::forUser($ketua)->allows('changeStatus', $member));
        $this->assertFalse(Gate::forUser($bendahara)->allows('changeStatus', $member));
    }

    public function test_only_chairperson_can_manage_user_accounts(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);

        $this->assertTrue(Gate::forUser($ketua)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($bendahara)->allows('viewAny', User::class));
    }

    public function test_only_chairperson_can_reverse_cash_transactions(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);
        $transaction = new CashTransaction();

        $this->assertTrue(Gate::forUser($ketua)->allows('reverse', $transaction));
        $this->assertFalse(Gate::forUser($bendahara)->allows('reverse', $transaction));
    }

    public function test_guests_cannot_access_authenticated_profile_routes(): void
    {
        $this->get('/profile')->assertRedirect('/login');
        $this->put('/profile', [])->assertRedirect('/login');
    }
}