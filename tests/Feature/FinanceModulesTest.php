<?php

namespace Tests\Feature;

use App\Enums\ArisanRoundStatus;
use App\Enums\ArisanCycleStatus;
use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\ArisanCycle;
use App\Models\CashCategory;
use App\Models\CashTransaction;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceModulesTest extends TestCase
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

    public function test_financial_module_pages_render_for_authenticated_users(): void
    {
        $user = User::factory()->create(['role' => UserRole::Bendahara]);
        $member = Member::factory()->create();
        $account = SavingsAccount::query()->create(['member_id' => $member->id]);
        $category = CashCategory::query()->create([
            'name' => 'Pengeluaran uji',
            'type' => TransactionType::Expense,
            'is_active' => true,
        ]);
        $cash = \App\Models\CashTransaction::query()->create([
            'transaction_number' => 'KAS-TEST-001',
            'category_id' => $category->id,
            'transaction_type' => TransactionType::Expense,
            'transaction_date' => '2026-10-31',
            'amount' => '1000.00',
            'status' => TransactionStatus::Posted,
        ]);
        $cycle = ArisanCycle::query()->create([
            'cycle_number' => 'ARS-TEST',
            'name' => 'Siklus halaman',
            'contribution_amount' => '1000.00',
            'status' => ArisanCycleStatus::Active,
            'started_at' => '2026-10-31',
        ]);
        $cycle->participants()->create(['member_id' => $member->id]);
        $cycle->rounds()->create(['round_number' => 1, 'round_date' => '2026-10-31']);
        $loan = Loan::factory()->create(['member_id' => $member->id]);

        $this->actingAs($user);

        $this->get('/cash')->assertOk();
        $this->get("/cash/{$cash->id}")->assertOk();
        $this->get('/savings')->assertOk();
        $this->get("/savings/{$account->id}")->assertOk();
        $this->get("/savings/{$account->id}/deposit")->assertOk();
        $this->get('/arisan')->assertOk();
        $this->get("/arisan/{$cycle->id}")->assertOk();
        $this->get('/loans')->assertOk();
        $this->get("/loans/{$loan->id}")->assertOk();
        $this->get('/loans/create')->assertOk();
        $this->get('/installments')->assertOk();
        $this->get('/installments/payments')->assertOk();

        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $this->actingAs($ketua)->get('/arisan/create')->assertOk();
        $this->get('/loans/approvals')->assertOk();
    }

    public function test_cash_transaction_posts_balanced_journal_and_requires_chair_for_reversal(): void
    {
        $category = CashCategory::query()->create([
            'name' => 'Sumbangan',
            'type' => TransactionType::Income,
            'is_active' => true,
        ]);
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);
        $transaction = $this->actingAs($bendahara)->post('/cash', [
            'category_id' => $category->id,
            'transaction_type' => 'income',
            'payer_name' => 'Ibu Sari',
            'transaction_date' => '2026-10-31',
            'amount' => '12500.50',
            'description' => 'Sumbangan warga',
        ])->assertRedirect('/cash')->assertSessionHas('success')->assertSessionHasNoErrors();

        $cash = \App\Models\CashTransaction::query()->firstOrFail();
        $entry = JournalEntry::query()->where('reference_id', $cash->id)->firstOrFail();
        $this->assertSame('KAS-'.now()->format('Ym').'-001', $cash->transaction_number);
        $this->assertSame('Ibu Sari', $cash->payer_name);
        $this->assertSame($bendahara->id, $cash->created_by);
        $this->assertEquals(12500.5, $entry->lines()->sum('debit'));
        $this->assertEquals(12500.5, $entry->lines()->sum('credit'));
        $this->actingAs($bendahara)->get("/cash/{$cash->id}")
            ->assertInertia(fn ($page) => $page->where('transaction.creator.name', $bendahara->name));

        $this->post("/cash/{$cash->id}/reverse", ['reversal_reason' => 'Salah pencatatan'])
            ->assertForbidden();
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $this->actingAs($ketua)->post("/cash/{$cash->id}/reverse", ['reversal_reason' => 'Salah pencatatan'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(TransactionStatus::Reversed, $cash->fresh()->status);
        $this->assertDatabaseHas('journal_entries', ['transaction_number' => $cash->transaction_number.'-REV']);
    }

    public function test_cash_transaction_requires_counterparty_amount_and_description(): void
    {
        $category = CashCategory::query()->create([
            'name' => 'Sumbangan wajib',
            'type' => TransactionType::Income,
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role' => UserRole::Bendahara]);

        $this->actingAs($user)->from('/cash/create')->post('/cash', [
            'category_id' => $category->id,
            'transaction_type' => 'income',
            'transaction_date' => '2026-10-31',
            'amount' => '',
            'payer_name' => '   ',
            'description' => '   ',
        ])->assertSessionHasErrors(['payer_name', 'amount', 'description']);

        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_cash_category_can_be_created_from_transaction_flow(): void
    {
        $user = User::factory()->create(['role' => UserRole::Bendahara]);

        $response = $this->actingAs($user)->from('/cash/create')->post('/cash/categories', [
            'name' => '  Iuran Kebersihan  ',
            'type' => 'income',
            'description' => 'Iuran warga',
        ]);

        $category = CashCategory::query()->where('name', 'Iuran Kebersihan')->firstOrFail();
        $response->assertRedirect(route('cash.create', ['category_id' => $category->id]))
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();
        $this->assertSame(TransactionType::Income, $category->type);
        $this->assertTrue($category->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'cash',
            'action' => 'category_created',
            'entity_id' => $category->id,
        ]);
        $this->actingAs($user)->get("/cash/create?category_id={$category->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('selected_category_id', $category->id));

        $this->from('/cash/create')->post('/cash/categories', [
            'name' => 'Iuran Kebersihan',
            'type' => 'income',
        ])->assertSessionHasErrors('name');

        $expenseResponse = $this->from('/cash/create')->post('/cash/categories', [
            'name' => 'Iuran Kebersihan',
            'type' => 'expense',
        ]);
        $expenseCategory = CashCategory::query()->where('type', TransactionType::Expense)->firstOrFail();
        $expenseResponse->assertRedirect(route('cash.create', ['category_id' => $expenseCategory->id]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cash_categories', 2);
    }

    public function test_savings_deposit_withdrawal_and_reversal_keep_balance_consistent(): void
    {
        $member = Member::factory()->create();
        $account = SavingsAccount::query()->create(['member_id' => $member->id]);
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);

        $this->actingAs($bendahara)->post("/savings/{$account->id}/transactions", [
            'type' => 'deposit',
            'amount' => '50000',
            'transaction_date' => '2026-10-31',
        ])->assertRedirect('/savings')->assertSessionHas('success')->assertSessionHasNoErrors();
        $this->assertSame('50000.00', $account->fresh()->current_balance);

        $this->post("/savings/{$account->id}/transactions", [
            'type' => 'withdrawal',
            'amount' => '20000',
            'transaction_date' => '2026-10-31',
        ])->assertRedirect('/savings')->assertSessionHas('success')->assertSessionHasNoErrors();
        $this->assertSame('30000.00', $account->fresh()->current_balance);

        $this->post("/savings/{$account->id}/transactions", [
            'type' => 'withdrawal',
            'amount' => '30000.01',
            'transaction_date' => '2026-10-31',
        ])->assertSessionHasErrors('amount');

        $deposit = SavingsTransaction::query()->where('type', 'deposit')->firstOrFail();
        $withdrawal = SavingsTransaction::query()->where('type', 'withdrawal')->firstOrFail();
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        foreach ([$deposit, $withdrawal] as $transaction) {
            $this->actingAs($bendahara)
                ->post("/savings/transactions/{$transaction->id}/reverse", ['reversal_reason' => 'Tidak boleh'])
                ->assertNotFound();
            $this->actingAs($ketua)
                ->post("/savings/transactions/{$transaction->id}/reverse", ['reversal_reason' => 'Tidak boleh'])
                ->assertNotFound();
            $this->assertSame(TransactionStatus::Posted, $transaction->fresh()->status);
        }
        $this->assertSame('30000.00', $account->fresh()->current_balance);
    }

    public function test_arisan_cycle_requires_all_contributions_before_draw_and_payout(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $members = Member::factory()->count(2)->create();
        $this->actingAs($ketua)->post('/arisan', [
            'name' => 'Siklus Uji',
            'contribution_amount' => '20000',
            'started_at' => '2026-10-31',
            'member_ids' => $members->pluck('id')->all(),
        ])->assertRedirect('/arisan')->assertSessionHas('success')->assertSessionHasNoErrors();
        $cycle = ArisanCycle::query()->with('rounds', 'participants')->firstOrFail();
        $this->assertCount(2, $cycle->rounds);
        $this->assertSame('2026-10-31', $cycle->rounds[0]->round_date->toDateString());
        $this->assertSame('2026-12-05', $cycle->rounds[1]->round_date->toDateString());

        $round = $cycle->rounds->first();
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);
        $participant = $cycle->participants->first();
        $this->actingAs($bendahara)->post("/arisan/rounds/{$round->id}/payments", [
            'participant_id' => $participant->id,
            'payment_date' => '2026-10-31',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($ketua)->post("/arisan/rounds/{$round->id}/draw")
            ->assertSessionHasErrors('round');
        $secondParticipant = $cycle->participants->last();
        $this->post("/arisan/rounds/{$round->id}/payments", [
            'participant_id' => $secondParticipant->id,
            'payment_date' => '2026-10-31',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post("/arisan/rounds/{$round->id}/draw")
            ->assertRedirect()->assertSessionHasNoErrors();

        $round->refresh();
        $this->assertSame(ArisanRoundStatus::Drawn, $round->status);
        $this->assertSame(1, $round->payouts()->count());
        $payout = $round->payouts()->firstOrFail();
        $winnerFromFirstRound = $round->winner_participant_id;
        $this->assertDatabaseHas('arisan_participants', [
            'id' => $winnerFromFirstRound,
            'has_won' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'arisan',
            'action' => 'round_drawn',
            'entity_id' => $round->id,
        ]);
        $drawLog = \App\Models\AuditLog::query()
            ->where('module', 'arisan')
            ->where('action', 'round_drawn')
            ->where('entity_id', $round->id)
            ->firstOrFail();
        $this->assertSame($winnerFromFirstRound, $drawLog->new_values['winner_participant_id']);
        $this->assertSame('40000.00', $drawLog->new_values['amount']);
        $this->assertSame($ketua->id, $drawLog->new_values['drawn_by']);
        $this->post("/arisan/payouts/{$payout->id}/pay", ['payout_date' => '2026-10-31'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame(ArisanRoundStatus::Completed, $round->fresh()->status);

        $nextRound = $cycle->rounds->last();
        foreach ($cycle->participants as $cycleParticipant) {
            $this->post("/arisan/rounds/{$nextRound->id}/payments", [
                'participant_id' => $cycleParticipant->id,
                'payment_date' => '2026-12-05',
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->post("/arisan/rounds/{$nextRound->id}/draw")
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotSame($winnerFromFirstRound, $nextRound->fresh()->winner_participant_id);
        $this->assertSame(2, $cycle->participants()->where('has_won', true)->count());

        $secondCycleResponse = $this->post('/arisan', [
            'name' => 'Siklus Arisan Lain',
            'contribution_amount' => '20000',
            'started_at' => '2027-01-09',
            'member_ids' => $members->pluck('id')->all(),
        ]);
        $secondCycleResponse->assertRedirect('/arisan')->assertSessionHasNoErrors();
        $secondCycle = ArisanCycle::query()->where('name', 'Siklus Arisan Lain')->firstOrFail();
        $this->assertSame(0, $secondCycle->participants()->where('has_won', true)->count());
    }

    public function test_loan_approval_disbursement_and_installment_payment_flow(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);
        $member = Member::factory()->create();
        \App\Models\OrganizationSetting::query()->create([
            'organization_name' => 'Uji',
            'loan_service_rate' => '0.50',
        ]);

        $this->actingAs($bendahara)->post('/loans', [
            'member_id' => $member->id,
            'principal_amount' => '100000',
            'installment_count' => 2,
            'application_date' => '2026-10-31',
        ])->assertRedirect('/loans')->assertSessionHas('success')->assertSessionHasNoErrors();
        $loan = Loan::query()->firstOrFail();
        $this->post("/loans/{$loan->id}/approve")->assertForbidden();

        $this->actingAs($ketua)->post("/loans/{$loan->id}/approve")
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($bendahara)->post("/loans/{$loan->id}/disburse", ['disbursed_at' => '2026-10-31'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $loan->refresh();
        $this->assertSame(LoanStatus::Active, $loan->status);
        $this->assertSame(2, $loan->installments()->count());
        $this->assertSame('50000.00', $loan->installments()->first()->principal_due);
        $this->assertSame('500.00', $loan->service_amount);

        $this->post("/loans/{$loan->id}/payments", [
            'payment_scope' => 'installment',
            'installment_id' => $loan->installments()->orderBy('installment_number')->first()->id,
            'amount' => '50750',
            'payment_date' => '2026-10-31',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(LoanStatus::Active, $loan->fresh()->status);
        $this->assertSame(1, LoanInstallment::query()->where('loan_id', $loan->id)->where('status', 'paid')->count());
        $this->assertSame('partial', $loan->installments()->orderBy('installment_number')->skip(1)->first()->status->value);
        $this->assertDatabaseHas('loan_payments', [
            'loan_id' => $loan->id,
            'principal_amount' => '50250.00',
            'service_amount' => '500.00',
            'total_amount' => '50750.00',
        ]);

        $this->post("/loans/{$loan->id}/payments", [
            'payment_scope' => 'installment',
            'installment_id' => $loan->installments()->orderBy('installment_number')->first()->id,
            'amount' => '100',
            'payment_date' => '2026-10-31',
        ])->assertSessionHasErrors('installment_id');

        $this->post("/loans/{$loan->id}/payments", [
            'payment_scope' => 'full',
            'amount' => '49750',
            'payment_date' => '2026-11-30',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(LoanStatus::PaidOff, $loan->fresh()->status);
        $this->assertSame(MemberStatus::Active, $member->fresh()->status);
    }

    public function test_overpayment_after_full_repayment_is_recorded_as_cash_income(): void
    {
        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);
        $member = Member::factory()->create();
        \App\Models\OrganizationSetting::query()->create([
            'organization_name' => 'Uji',
            'loan_service_rate' => '0.50',
        ]);

        $this->actingAs($bendahara)->post('/loans', [
            'member_id' => $member->id,
            'principal_amount' => '1000',
            'installment_count' => 1,
            'application_date' => '2026-10-31',
        ])->assertRedirect('/loans')->assertSessionHasNoErrors();
        $loan = Loan::query()->firstOrFail();
        $this->actingAs($ketua)->post("/loans/{$loan->id}/approve")->assertSessionHasNoErrors();
        $this->actingAs($bendahara)->post("/loans/{$loan->id}/disburse", [
            'disbursed_at' => '2026-10-31',
        ])->assertSessionHasNoErrors();

        $installment = $loan->installments()->firstOrFail();
        $this->post("/loans/{$loan->id}/payments", [
            'payment_scope' => 'full',
            'amount' => '1200',
            'payment_date' => '2026-11-30',
        ])->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'KAS-'));

        $payment = $loan->payments()->firstOrFail();
        $cash = CashTransaction::query()->where('transaction_type', TransactionType::Income)->firstOrFail();
        $this->assertSame('1005.00', $payment->total_amount);
        $this->assertSame('195.00', $cash->amount);
        $this->assertSame($member->full_name, $cash->payer_name);
        $this->assertSame('Kelebihan pembayaran pinjaman', $cash->category->name);
        $this->assertSame('paid', $installment->fresh()->status->value);
        $this->assertSame(LoanStatus::PaidOff, $loan->fresh()->status);

        $cashJournal = JournalEntry::query()
            ->where('reference_type', CashTransaction::class)
            ->where('reference_id', $cash->id)
            ->with('lines.account')
            ->firstOrFail();
        $this->assertEquals(195, $cashJournal->lines->sum('debit'));
        $this->assertEquals(195, $cashJournal->lines->sum('credit'));
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'cash',
            'action' => 'created',
            'entity_id' => $cash->id,
        ]);
    }
}
