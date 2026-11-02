<?php

namespace Tests\Feature;

use App\Enums\ArisanCycleStatus;
use App\Enums\LoanStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\ArisanCycle;
use App\Models\ArisanPayment;
use App\Models\ArisanPayout;
use App\Models\CashCategory;
use App\Models\CashTransaction;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPayment;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            ['1001', 'Kas Kelompok', 'asset'],
            ['2001', 'Kewajiban Tabungan Anggota', 'liability'],
        ] as [$code, $name, $type]) {
            Account::query()->create([
                'account_code' => $code,
                'account_name' => $name,
                'account_type' => $type,
                'is_active' => true,
            ]);
        }
    }

    public function test_report_index_and_financial_reports_are_available_to_officers(): void
    {
        $user = User::factory()->create(['role' => UserRole::Bendahara]);
        $this->actingAs($user)->get('/reports')->assertOk();

        foreach (['cash', 'savings', 'loans', 'arisan', 'financial'] as $type) {
            $this->get("/reports/{$type}?from=2026-10-01&to=2026-10-31")
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('organization.logo', fn ($logo): bool => is_string($logo) && str_starts_with($logo, 'data:image/')));
        }

        $this->get('/reports/not-a-report')->assertNotFound();
    }

    public function test_cash_report_filters_date_range_and_exports_pdf(): void
    {
        $category = CashCategory::query()->create([
            'name' => 'Iuran',
            'type' => TransactionType::Income,
            'is_active' => true,
        ]);
        CashTransaction::query()->create([
            'transaction_number' => 'KAS-TEST-001',
            'category_id' => $category->id,
            'transaction_type' => TransactionType::Income,
            'payer_name' => 'Ibu Sari',
            'transaction_date' => '2026-10-12',
            'amount' => '15000.00',
            'description' => '=HYPERLINK("https://example.test")',
            'status' => TransactionStatus::Posted,
        ]);
        CashTransaction::query()->create([
            'transaction_number' => 'KAS-TEST-002',
            'category_id' => $category->id,
            'transaction_type' => TransactionType::Income,
            'transaction_date' => '2026-09-30',
            'amount' => '5000.00',
            'status' => TransactionStatus::Posted,
        ]);
        $user = User::factory()->create(['role' => UserRole::Bendahara]);

        $this->actingAs($user)->get('/reports/cash?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/cash')
                ->where('report.summary.0.amount', '15000.00')
                ->where('report.columns.3', 'Pembayar / penerima')
                ->where('report.columns.7', 'Waktu dicatat')
                ->where('report.rows.0.3', 'Ibu Sari')
                ->where('report.rows.0.7', fn ($value) => is_string($value) && str_contains($value, 'T'))
                ->has('report.rows', 1));

        $exportReport = app(ReportService::class)->build(
            'cash',
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31'),
        );
        $this->assertNotContains('Waktu dicatat', $exportReport['columns']);
        $this->assertCount(7, $exportReport['rows'][0]);

        $response = $this->get('/reports/cash/export?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="sikadasa-cash-20261001-20261031.pdf"');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_every_report_type_can_be_exported_as_pdf(): void
    {
        $user = User::factory()->create(['role' => UserRole::Bendahara]);
        $this->actingAs($user);

        foreach (['cash', 'savings', 'loans', 'arisan', 'financial'] as $type) {
            $this->get("/reports/{$type}/export?from=2026-10-01&to=2026-10-31")
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }

        $this->assertDatabaseCount('audit_logs', 5);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'reports',
            'action' => 'exported',
            'new_values->report' => 'financial',
            'new_values->format' => 'pdf',
        ]);
    }

    public function test_financial_report_balances_posted_journals_and_chair_can_validate_consistency(): void
    {
        $member = Member::factory()->create();
        $account = SavingsAccount::query()->create([
            'member_id' => $member->id,
            'current_balance' => '2000.00',
        ]);
        SavingsTransaction::query()->create([
            'transaction_number' => 'SMP-TEST-001',
            'savings_account_id' => $account->id,
            'type' => 'deposit',
            'amount' => '2000.00',
            'transaction_date' => '2026-10-10',
            'status' => TransactionStatus::Posted,
        ]);
        $entry = JournalEntry::query()->create([
            'transaction_number' => 'SMP-TEST-001',
            'transaction_date' => '2026-10-10',
            'description' => 'Setoran uji',
            'status' => 'posted',
        ]);
        $entry->lines()->create([
            'account_id' => Account::query()->where('account_code', '1001')->value('id'),
            'debit' => '2000.00',
            'credit' => '0.00',
        ]);
        $entry->lines()->create([
            'account_id' => Account::query()->where('account_code', '2001')->value('id'),
            'debit' => '0.00',
            'credit' => '2000.00',
        ]);

        $ketua = User::factory()->create(['role' => UserRole::Ketua]);
        $this->actingAs($ketua)->get('/reports/financial?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/financial')
                ->where('report.summary.2.amount', '0.00')
                ->where('report.summary.3.amount', 'Seimbang'));
        $this->post('/reports/validate')->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $ketua->id,
            'module' => 'reports',
            'action' => 'validated',
            'new_values->unbalanced_journals' => 0,
            'new_values->savings_discrepancies' => 0,
        ]);

        $bendahara = User::factory()->create(['role' => UserRole::Bendahara]);
        $this->actingAs($bendahara)->post('/reports/validate')->assertForbidden();
    }

    public function test_consolidated_financial_report_shows_closing_positions_and_member_savings(): void
    {
        $member = Member::factory()->create([
            'full_name' => 'Ibu Laporan',
            'house_number' => '47A',
        ]);
        $cashCategory = CashCategory::query()->create([
            'name' => 'Iuran',
            'type' => TransactionType::Income,
            'is_active' => true,
        ]);
        CashTransaction::query()->create([
            'transaction_number' => 'KAS-REPORT-IN',
            'category_id' => $cashCategory->id,
            'transaction_type' => TransactionType::Income,
            'transaction_date' => '2026-10-05',
            'amount' => '100000.00',
            'status' => TransactionStatus::Posted,
        ]);
        CashTransaction::query()->create([
            'transaction_number' => 'KAS-REPORT-OUT',
            'category_id' => $cashCategory->id,
            'transaction_type' => TransactionType::Expense,
            'transaction_date' => '2026-10-08',
            'amount' => '25000.00',
            'status' => TransactionStatus::Posted,
        ]);
        $cashEntry = JournalEntry::query()->create([
            'transaction_number' => 'CASH-REPORT-CLOSE',
            'transaction_date' => '2026-10-31',
            'description' => 'Saldo kas',
            'status' => 'posted',
        ]);
        $cashEntry->lines()->create([
            'account_id' => Account::query()->where('account_code', '1001')->value('id'),
            'debit' => '125000.00',
            'credit' => '0.00',
        ]);

        $account = SavingsAccount::query()->create(['member_id' => $member->id]);
        foreach ([
            ['deposit', '300000.00', '2026-09-10'],
            ['withdrawal', '50000.00', '2026-10-10'],
            ['deposit', '1000000.00', '2026-11-02'],
        ] as $index => [$type, $amount, $date]) {
            SavingsTransaction::query()->create([
                'transaction_number' => 'SMP-REPORT-'.($index + 1),
                'savings_account_id' => $account->id,
                'type' => $type,
                'amount' => $amount,
                'transaction_date' => $date,
                'status' => TransactionStatus::Posted,
            ]);
        }

        $loan = Loan::factory()->create([
            'member_id' => $member->id,
            'principal_amount' => '300000.00',
            'service_amount' => '30000.00',
            'total_obligation' => '330000.00',
            'status' => LoanStatus::Active,
            'disbursed_at' => '2026-10-01',
        ]);
        LoanPayment::query()->create([
            'payment_number' => 'ANG-REPORT-1',
            'loan_id' => $loan->id,
            'payment_date' => '2026-10-10',
            'principal_amount' => '90000.00',
            'service_amount' => '10000.00',
            'total_amount' => '100000.00',
            'status' => TransactionStatus::Posted,
        ]);
        LoanPayment::query()->create([
            'payment_number' => 'ANG-REPORT-2',
            'loan_id' => $loan->id,
            'payment_date' => '2026-11-02',
            'principal_amount' => '45000.00',
            'service_amount' => '5000.00',
            'total_amount' => '50000.00',
            'status' => TransactionStatus::Posted,
        ]);
        LoanInstallment::query()->create([
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'due_date' => '2026-10-10',
            'principal_due' => '100000.00',
            'service_due' => '10000.00',
            'principal_paid' => '90000.00',
            'service_paid' => '10000.00',
            'status' => 'partial',
        ]);

        $cycle = ArisanCycle::query()->create([
            'cycle_number' => 'ARS-REPORT-1',
            'name' => 'Arisan Laporan',
            'contribution_amount' => '20000.00',
            'status' => ArisanCycleStatus::Active,
            'started_at' => '2026-10-01',
        ]);
        $participant = $cycle->participants()->create(['member_id' => $member->id, 'has_won' => true]);
        $round = $cycle->rounds()->create(['round_number' => 1, 'round_date' => '2026-10-31']);
        ArisanPayment::query()->create([
            'round_id' => $round->id,
            'participant_id' => $participant->id,
            'transaction_number' => 'ARI-REPORT-1',
            'amount' => '80000.00',
            'payment_date' => '2026-10-31',
            'status' => 'posted',
        ]);
        ArisanPayout::query()->create([
            'round_id' => $round->id,
            'winner_participant_id' => $participant->id,
            'payout_number' => 'ARP-REPORT-1',
            'amount' => '20000.00',
            'payout_date' => '2026-10-31',
            'status' => 'paid',
        ]);

        $report = app(ReportService::class)->build(
            'financial',
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31'),
        );

        $this->assertSame('125000.00', $report['consolidated']['summary'][0]['amount']);
        $this->assertSame('Oktober 2026', $report['consolidated']['period_label']);
        $this->assertSame('2026', $report['consolidated']['fiscal_year']);
        $this->assertSame('Pemasukan Rp 100.000 - Pengeluaran Rp 25.000', $report['consolidated']['summary'][0]['note']);
        $this->assertSame('250000.00', $report['consolidated']['summary'][1]['amount']);
        $this->assertSame('230000.00', $report['consolidated']['summary'][2]['amount']);
        $this->assertSame('1 pinjaman aktif (1 menunggak)', $report['consolidated']['summary'][2]['note']);
        $this->assertSame('60000.00', $report['consolidated']['summary'][3]['amount']);
        $this->assertSame('Rumah 47A', $report['consolidated']['savings_rows'][0]['unit']);
        $this->assertSame('250000.00', $report['consolidated']['savings_rows'][0]['balance']);
    }

    public function test_report_dates_must_be_valid_and_ordered(): void
    {
        $user = User::factory()->create(['role' => UserRole::Bendahara]);
        $this->actingAs($user)->get('/reports/cash?from=2026-10-31&to=2026-10-01')
            ->assertSessionHasErrors('to');
    }
}