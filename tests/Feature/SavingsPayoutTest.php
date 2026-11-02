<?php

namespace Tests\Feature;

use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\CashCategory;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsPayoutBatch;
use App\Models\SavingsTransaction;
use App\Models\User;
use App\Services\CashService;
use App\Services\HijriCalendarService;
use App\Services\SavingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingsPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            ['1001', 'Kas Kelompok', 'asset'],
            ['1101', 'Piutang Pinjaman', 'asset'],
            ['2001', 'Kewajiban Tabungan Anggota', 'liability'],
            ['2101', 'Kewajiban Arisan Anggota', 'liability'],
            ['4001', 'Pendapatan Jasa Pinjaman', 'income'],
            ['4002', 'Pendapatan Iuran dan Lainnya', 'income'],
            ['5001', 'Beban Operasional', 'expense'],
        ] as [$code, $name, $type]) {
            Account::query()->create([
                'account_code' => $code,
                'account_name' => $name,
                'account_type' => $type,
                'is_active' => true,
            ]);
        }
    }

    public function test_payout_batch_is_approved_then_paid_pro_rata_and_can_resume_after_cash_is_added(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);
        $members = collect([
            Member::factory()->create(),
            Member::factory()->create(),
        ]);
        $accounts = $members->map(fn (Member $member) => SavingsAccount::query()->create(['member_id' => $member->id]));

        $this->actingAs($bendahara);
        foreach (array_values($accounts->all()) as $index => $account) {
            app(SavingsService::class)->transact($account, [
                'type' => 'deposit',
                'amount' => $index === 0 ? '100.00' : '300.00',
                'transaction_date' => now()->toDateString(),
            ], (int) $bendahara->id);
        }
        $this->recordCash($bendahara, 'expense', '200.00');

        $loan = Loan::factory()->create([
            'member_id' => $members[0]->id,
            'status' => LoanStatus::Active,
        ]);
        LoanInstallment::query()->create([
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'due_date' => now()->subDay()->toDateString(),
            'principal_due' => '1000.00',
            'service_due' => '5.00',
            'principal_paid' => '0.00',
            'service_paid' => '0.00',
            'status' => InstallmentStatus::Unpaid,
        ]);

        $upcoming = app(HijriCalendarService::class)->nextPayout();
        $response = $this->actingAs($ketua)->post('/savings/payouts', [
            'hijri_year' => $upcoming['hijri_year'],
            'payout_date' => $upcoming['payout_date'],
        ]);
        $batch = SavingsPayoutBatch::query()->firstOrFail();
        $response->assertRedirect(route('savings.payouts.show', $batch))
            ->assertSessionHasNoErrors();
        $this->assertSame('400.00', $batch->total_due);
        $this->assertSame('pending_approval', $batch->status->value);

        $this->actingAs($bendahara)->post("/savings/payouts/{$batch->id}/approve")->assertForbidden();
        $this->actingAs($ketua)->post("/savings/payouts/{$batch->id}/approve")
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $batch->fresh()->status->value);

        $this->travelTo(now()->parse($upcoming['payout_date'])->endOfDay());
        $this->actingAs($ketua)->post("/savings/payouts/{$batch->id}/process", ['confirmation' => '1'])->assertForbidden();
        $this->actingAs($bendahara)->post("/savings/payouts/{$batch->id}/process", ['confirmation' => '1'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $batch->refresh();
        $this->assertSame('partially_paid', $batch->status->value);
        $this->assertSame('200.00', $batch->total_paid);
        $this->assertSame('50.00', $accounts[0]->fresh()->current_balance);
        $this->assertSame('150.00', $accounts[1]->fresh()->current_balance);
        $this->assertSame(2, SavingsTransaction::query()->whereNotNull('savings_payout_item_id')->count());
        $this->get("/savings/payouts/{$batch->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('outstandingLoanCount', 1)
                ->where('items.0.has_outstanding_loan', true));

        $this->recordCash($bendahara, 'income', '200.00');
        $this->actingAs($bendahara)->post("/savings/payouts/{$batch->id}/process", ['confirmation' => '1'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('paid', $batch->fresh()->status->value);
        $this->assertSame('400.00', $batch->fresh()->total_paid);
        $this->assertSame('0.00', $accounts[0]->fresh()->current_balance);
        $this->assertSame('0.00', $accounts[1]->fresh()->current_balance);
    }

    public function test_only_chairperson_can_create_and_approve_and_only_treasurer_can_process(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);
        $member = Member::factory()->create();
        $account = SavingsAccount::query()->create(['member_id' => $member->id, 'current_balance' => '10.00']);
        $upcoming = app(HijriCalendarService::class)->nextPayout();

        $this->actingAs($bendahara)->post('/savings/payouts', [
            'hijri_year' => $upcoming['hijri_year'],
        ])->assertForbidden();

        $this->actingAs($ketua)->post('/savings/payouts', [
            'hijri_year' => $upcoming['hijri_year'],
        ])->assertRedirect();
        $batch = SavingsPayoutBatch::query()->firstOrFail();
        $this->actingAs($bendahara)->post("/savings/payouts/{$batch->id}/approve")->assertForbidden();
        $this->actingAs($ketua)->post("/savings/payouts/{$batch->id}/approve")->assertRedirect();

        $this->travelTo(now()->parse($upcoming['payout_date'])->endOfDay());
        $this->actingAs($ketua)->post("/savings/payouts/{$batch->id}/process", ['confirmation' => '1'])->assertForbidden();
        $this->assertSame('10.00', $account->fresh()->current_balance);
    }

    public function test_hijri_payout_is_ten_days_before_umm_al_qura_eid_date(): void
    {
        $calendar = app(HijriCalendarService::class);
        $upcoming = $calendar->nextPayout();

        $this->assertSame(
            $calendar->payoutDateForYear($upcoming['hijri_year'])->addDays(10)->toDateString(),
            $upcoming['eid_date'],
        );
        $this->assertGreaterThanOrEqual(now()->startOfDay()->toDateString(), $upcoming['payout_date']);
    }

    private function recordCash(User $user, string $type, string $amount): void
    {
        $transactionType = TransactionType::from($type);
        $category = CashCategory::query()->create([
            'name' => $type === 'income' ? 'Pemasukan uji' : 'Pengeluaran uji',
            'type' => $transactionType,
            'is_active' => true,
        ]);

        app(CashService::class)->create([
            'category_id' => $category->id,
            'transaction_type' => $type,
            'transaction_date' => now()->toDateString(),
            'amount' => $amount,
            'description' => 'Transaksi uji pencairan',
        ], (int) $user->id);
    }
}
