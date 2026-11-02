<?php

namespace Tests\Feature;

use App\Enums\ArisanCycleStatus;
use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\ArisanCycle;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_defaults_to_the_last_seven_days_when_no_dates_are_provided(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01 12:00:00'));
        $user = User::factory()->create(['role' => UserRole::Bendahara]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/index')
                ->where('filters.from', '2026-10-26')
                ->where('filters.to', '2026-11-01')
                ->has('cash_flow', 7));
    }

    public function test_dashboard_shows_financial_summary_period_flow_and_recent_transactions(): void
    {
        $cash = $this->account('1001', 'Kas Kelompok', 'asset');
        $income = $this->account('4002', 'Pendapatan Lainnya', 'income');
        $expense = $this->account('5001', 'Beban Operasional', 'expense');
        $user = User::factory()->create(['role' => UserRole::Bendahara]);
        $member = Member::factory()->create();
        Member::factory()->create(['status' => MemberStatus::Inactive]);
        SavingsAccount::query()->create([
            'member_id' => $member->id,
            'current_balance' => '125000.00',
        ]);
        $loan = Loan::factory()->create([
            'member_id' => $member->id,
            'status' => LoanStatus::Active,
        ]);
        Loan::factory()->create([
            'member_id' => $member->id,
            'status' => LoanStatus::Pending,
        ]);
        LoanInstallment::query()->create([
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'due_date' => '2026-10-31',
            'principal_due' => '100000.00',
            'service_due' => '500.00',
            'principal_paid' => '10000.00',
            'service_paid' => '50.00',
            'status' => 'partial',
        ]);
        $this->postJournalEntry($user, $cash, $income, 'KAS-001', 15000, 0);
        $this->postJournalEntry($user, $cash, $expense, 'KAS-002', 0, 2000);
        $cycle = ArisanCycle::query()->create([
            'cycle_number' => 'ARS-2026-001',
            'name' => 'Arisan aktif',
            'contribution_amount' => '20000.00',
            'status' => ArisanCycleStatus::Active,
            'started_at' => '2026-10-01',
        ]);
        $cycle->participants()->create(['member_id' => $member->id]);

        $response = $this->actingAs($user)->get('/dashboard?from=2026-10-01&to=2026-10-31');
        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/index')
                ->where('summary.cash_balance', '13000.00')
                ->where('summary.active_members', 1)
                ->where('summary.inactive_members', 1)
                ->where('summary.savings_balance', '125000.00')
                ->where('summary.savings_available', '34550.00')
                ->where('summary.loan_outstanding', '90450.00')
                ->where('summary.active_loans', 1)
                ->where('loan_statuses.pending', 1)
                ->where('loan_statuses.active', 1)
                ->where('summary.period_income', '15000.00')
                ->where('summary.period_expense', '2000.00')
                ->where('active_arisan.count', 1)
                ->where('active_arisan.cycles.0.name', 'Arisan aktif')
                ->where('latest_transactions.0.transaction_number', 'KAS-002')
                ->has('cash_flow', 31)
                ->where('cash_flow.30.income', '15000.00')
                ->where('cash_flow.30.expense', '2000.00'));
    }

    private function account(string $code, string $name, string $type): Account
    {
        return Account::query()->create([
            'account_code' => $code,
            'account_name' => $name,
            'account_type' => $type,
            'is_active' => true,
        ]);
    }

    private function postJournalEntry(
        User $user,
        Account $cash,
        Account $other,
        string $number,
        int $cashDebit,
        int $cashCredit,
    ): void {
        $entry = JournalEntry::query()->create([
            'transaction_number' => $number,
            'transaction_date' => '2026-10-31',
            'description' => $number,
            'status' => 'posted',
            'created_by' => $user->id,
        ]);
        $amount = $cashDebit + $cashCredit;
        $entry->lines()->create([
            'account_id' => $cash->id,
            'debit' => number_format($cashDebit, 2, '.', ''),
            'credit' => number_format($cashCredit, 2, '.', ''),
        ]);
        $entry->lines()->create([
            'account_id' => $other->id,
            'debit' => number_format($cashCredit > 0 ? $amount : 0, 2, '.', ''),
            'credit' => number_format($cashDebit > 0 ? $amount : 0, 2, '.', ''),
        ]);
    }
}
