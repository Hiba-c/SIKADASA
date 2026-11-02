<?php

namespace Tests\Feature;

use App\Enums\ArisanCycleStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\ArisanCycle;
use App\Models\ArisanPayment;
use App\Models\ArisanPayout;
use App\Models\CashCategory;
use App\Models\CashTransaction;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipts_render_for_all_supported_transaction_types(): void
    {
        $user = User::factory()->create(['role' => UserRole::Bendahara]);
        $member = Member::factory()->create([
            'full_name' => 'Anggota Kuitansi',
            'registration_code' => 'AGT-046',
            'house_number' => '46',
        ]);
        $category = CashCategory::query()->create([
            'name' => 'Iuran warga',
            'type' => TransactionType::Income,
            'is_active' => true,
        ]);
        $cash = CashTransaction::query()->create([
            'transaction_number' => 'KAS-TEST-001',
            'category_id' => $category->id,
            'transaction_type' => TransactionType::Income,
            'payer_name' => 'Anggota Kuitansi',
            'transaction_date' => '2026-10-31',
            'amount' => '25000.00',
            'description' => 'Pembayaran iuran',
            'status' => TransactionStatus::Posted,
            'created_by' => $user->id,
        ]);
        $account = SavingsAccount::query()->create(['member_id' => $member->id]);
        $savings = SavingsTransaction::query()->create([
            'transaction_number' => 'SMP-TEST-001',
            'savings_account_id' => $account->id,
            'type' => 'deposit',
            'amount' => '50000.00',
            'transaction_date' => '2026-10-31',
            'description' => 'Setoran rutin',
            'status' => TransactionStatus::Posted,
            'created_by' => $user->id,
        ]);
        $loan = Loan::factory()->create(['member_id' => $member->id]);
        $loanPayment = LoanPayment::query()->create([
            'payment_number' => 'ANG-TEST-001',
            'loan_id' => $loan->id,
            'payment_date' => '2026-10-31',
            'principal_amount' => '40000.00',
            'service_amount' => '10000.00',
            'total_amount' => '50000.00',
            'status' => TransactionStatus::Posted,
            'received_by' => $user->id,
        ]);
        $cycle = ArisanCycle::query()->create([
            'cycle_number' => 'ARS-TEST-001',
            'name' => 'Arisan Oktober',
            'contribution_amount' => '20000.00',
            'status' => ArisanCycleStatus::Active,
            'started_at' => '2026-10-01',
        ]);
        $participant = $cycle->participants()->create(['member_id' => $member->id]);
        $round = $cycle->rounds()->create(['round_number' => 1, 'round_date' => '2026-10-31']);
        $arisanPayment = ArisanPayment::query()->create([
            'round_id' => $round->id,
            'participant_id' => $participant->id,
            'transaction_number' => 'ARI-TEST-001',
            'amount' => '20000.00',
            'payment_date' => '2026-10-31',
            'status' => 'posted',
            'created_by' => $user->id,
        ]);
        $payout = ArisanPayout::query()->create([
            'round_id' => $round->id,
            'winner_participant_id' => $participant->id,
            'payout_number' => 'ARP-TEST-001',
            'amount' => '20000.00',
            'payout_date' => '2026-10-31',
            'status' => 'paid',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        foreach ([
            ['cash', $cash->id, 'KUITANSI TRANSAKSI KAS'],
            ['savings', $savings->id, 'KUITANSI SETORAN TABUNGAN'],
            ['loan-payment', $loanPayment->id, 'KUITANSI PEMBAYARAN ANGSURAN'],
            ['arisan-payment', $arisanPayment->id, 'KUITANSI IURAN ARISAN'],
            ['arisan-payout', $payout->id, 'KUITANSI PENYALURAN ARISAN'],
        ] as [$type, $id, $title]) {
            $this->get("/receipts/{$type}/{$id}")
                ->assertOk()
                ->assertSee($title)
                ->assertSee('Anggota Kuitansi')
                ->assertSee('Rp');
        }
    }

    public function test_receipt_can_be_downloaded_as_a_pdf(): void
    {
        $user = User::factory()->create();
        $category = CashCategory::query()->create([
            'name' => 'Iuran warga',
            'type' => TransactionType::Income,
            'is_active' => true,
        ]);
        $transaction = CashTransaction::query()->create([
            'transaction_number' => 'KAS-PDF-001',
            'category_id' => $category->id,
            'transaction_type' => TransactionType::Income,
            'payer_name' => 'Pembayar PDF',
            'transaction_date' => '2026-10-31',
            'amount' => '15000.00',
            'status' => TransactionStatus::Posted,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get("/receipts/cash/{$transaction->id}?format=pdf");

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('kas-pdf-001.pdf', $response->headers->get('content-disposition'));
    }

    public function test_receipts_require_authentication_and_pending_arisan_payout_has_no_receipt(): void
    {
        $category = CashCategory::query()->create([
            'name' => 'Iuran warga',
            'type' => TransactionType::Income,
            'is_active' => true,
        ]);
        $transaction = CashTransaction::query()->create([
            'transaction_number' => 'KAS-AUTH-001',
            'category_id' => $category->id,
            'transaction_type' => TransactionType::Income,
            'payer_name' => 'Pembayar',
            'transaction_date' => '2026-10-31',
            'amount' => '15000.00',
            'status' => TransactionStatus::Posted,
        ]);
        $this->get("/receipts/cash/{$transaction->id}")->assertRedirect('/login');

        $user = User::factory()->create();
        $member = Member::factory()->create();
        $cycle = ArisanCycle::query()->create([
            'cycle_number' => 'ARS-TEST-002',
            'name' => 'Arisan Mendatang',
            'contribution_amount' => '20000.00',
            'status' => ArisanCycleStatus::Active,
            'started_at' => '2026-10-01',
        ]);
        $participant = $cycle->participants()->create(['member_id' => $member->id]);
        $round = $cycle->rounds()->create(['round_number' => 1, 'round_date' => '2026-10-31']);
        $payout = ArisanPayout::query()->create([
            'round_id' => $round->id,
            'winner_participant_id' => $participant->id,
            'payout_number' => 'ARP-PENDING-001',
            'amount' => '20000.00',
            'payout_date' => '2026-10-31',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->get("/receipts/arisan-payout/{$payout->id}")->assertNotFound();
    }
}
