<?php

namespace App\Http\Controllers;

use App\Models\ArisanPayment;
use App\Models\ArisanPayout;
use App\Models\CashTransaction;
use App\Models\LoanPayment;
use App\Models\OrganizationSetting;
use App\Models\SavingsTransaction;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    public function show(string $type, int $id): Response|StreamedResponse
    {
        $receipt = match ($type) {
            'cash' => $this->cashReceipt($id),
            'savings' => $this->savingsReceipt($id),
            'loan-payment' => $this->loanPaymentReceipt($id),
            'arisan-payment' => $this->arisanPaymentReceipt($id),
            'arisan-payout' => $this->arisanPayoutReceipt($id),
            default => abort(404),
        };
        $organization = OrganizationSetting::query()->first();
        $viewData = [
            'receipt' => $receipt,
            'organization' => [
                'name' => $organization?->organization_name ?? 'Kelompok Dasawisma Cempaka 46',
                'address' => $organization?->address,
                'footer' => $organization?->receipt_footer,
            ],
            'logoDataUri' => $this->logoDataUri(),
            'printedAt' => now()->locale('id')->translatedFormat('d M Y H:i'),
        ];

        if (request()->query('format') === 'pdf') {
            return Pdf::loadView('receipts.show', $viewData)
                ->setPaper('a4')
                ->download(Str::slug($receipt['number']).'.pdf');
        }

        return response()->view('receipts.show', $viewData);
    }

    private function cashReceipt(int $id): array
    {
        $transaction = CashTransaction::query()->with(['category', 'creator'])->findOrFail($id);
        $this->authorize('view', $transaction);
        $income = $transaction->transaction_type->value === 'income';

        return $this->makeReceipt(
            'KUITANSI TRANSAKSI KAS',
            $transaction->transaction_number,
            $transaction->transaction_date,
            $transaction->payer_name,
            $transaction->category?->name ?? 'Kas',
            $income ? 'Pemasukan Kas' : 'Pengeluaran Kas',
            $transaction->amount,
            $transaction->description,
            $transaction->creator?->name,
            $transaction->status->value,
        );
    }

    private function savingsReceipt(int $id): array
    {
        $transaction = SavingsTransaction::query()
            ->with(['savingsAccount.member', 'createdBy'])
            ->findOrFail($id);
        $this->authorize('view', $transaction->savingsAccount);
        $member = $transaction->savingsAccount->member;
        $deposit = $transaction->type === 'deposit';

        return $this->makeReceipt(
            $deposit ? 'KUITANSI SETORAN TABUNGAN' : 'KUITANSI PENARIKAN TABUNGAN',
            $transaction->transaction_number,
            $transaction->transaction_date,
            $member?->full_name,
            'Tabungan Sukarela',
            $deposit ? 'Penyetoran Kas' : 'Penarikan Kas',
            $transaction->amount,
            $transaction->description,
            $transaction->createdBy?->name,
            $transaction->status->value,
            $member?->house_number,
            $this->period($transaction->transaction_date),
            $member?->registration_code,
        );
    }

    private function loanPaymentReceipt(int $id): array
    {
        $payment = LoanPayment::query()->with(['loan.member', 'receivedBy'])->findOrFail($id);
        $this->authorize('view', $payment->loan);
        $member = $payment->loan->member;

        return $this->makeReceipt(
            'KUITANSI PEMBAYARAN ANGSURAN',
            $payment->payment_number,
            $payment->payment_date,
            $member?->full_name,
            'Pinjaman '.$payment->loan->loan_number,
            'Penerimaan Angsuran',
            $payment->total_amount,
            'Pokok: '.$this->currency($payment->principal_amount).' · Jasa: '.$this->currency($payment->service_amount),
            $payment->receivedBy?->name,
            $payment->status->value,
            $member?->house_number,
            $this->period($payment->payment_date),
            $member?->registration_code,
        );
    }

    private function arisanPaymentReceipt(int $id): array
    {
        $payment = ArisanPayment::query()
            ->with(['participant.member', 'round.cycle', 'createdBy'])
            ->findOrFail($id);
        $this->authorize('view', $payment->round->cycle);
        $member = $payment->participant->member;

        return $this->makeReceipt(
            'KUITANSI IURAN ARISAN',
            $payment->transaction_number,
            $payment->payment_date,
            $member?->full_name,
            $payment->round->cycle->name.' · Putaran '.$payment->round->round_number,
            'Penyetoran Kas',
            $payment->amount,
            'Iuran arisan '.$payment->round->cycle->name,
            $payment->createdBy?->name,
            (string) $payment->status,
            $member?->house_number,
            $this->period($payment->payment_date),
            $member?->registration_code,
        );
    }

    private function arisanPayoutReceipt(int $id): array
    {
        $payout = ArisanPayout::query()
            ->with(['winner.member', 'round.cycle', 'createdBy'])
            ->findOrFail($id);
        $this->authorize('view', $payout->round->cycle);
        abort_unless($payout->status === 'paid', 404);
        $member = $payout->winner->member;

        return $this->makeReceipt(
            'KUITANSI PENYALURAN ARISAN',
            $payout->payout_number,
            $payout->payout_date,
            $member?->full_name,
            $payout->round->cycle->name.' · Putaran '.$payout->round->round_number,
            'Penyaluran Kas',
            $payout->amount,
            'Penyaluran hadiah arisan '.$payout->round->cycle->name,
            $payout->createdBy?->name,
            $payout->status,
            $member?->house_number,
            $this->period($payout->payout_date),
            $member?->registration_code,
        );
    }

    private function makeReceipt(
        string $title,
        string $number,
        mixed $date,
        ?string $memberName,
        string $category,
        string $fundFlow,
        string|int|float $amount,
        ?string $description,
        ?string $recorder,
        string $status,
        ?string $unit = null,
        ?string $period = null,
        ?string $memberCode = null,
    ): array {
        $isPosted = in_array($status, ['posted', 'paid'], true);

        return [
            'title' => $title,
            'number' => $number,
            'date' => CarbonImmutable::parse($date)->locale('id')->translatedFormat('d M Y'),
            'member' => $memberName ?: '—',
            'member_code' => $memberCode,
            'unit' => $unit ?: '—',
            'transaction' => $title,
            'period' => $period ?: $this->period($date),
            'category' => $category,
            'fund_flow' => $fundFlow,
            'amount' => $this->currency($amount),
            'description' => $description ?: '—',
            'recorder' => $recorder ?: '—',
            'status' => $isPosted ? 'SAH' : 'DIBATALKAN',
            'is_valid' => $isPosted,
        ];
    }

    private function period(mixed $date): string
    {
        return CarbonImmutable::parse($date)->locale('id')->translatedFormat('F Y');
    }

    private function currency(string|int|float $amount): string
    {
        return 'Rp'.number_format(Money::cents($amount) / 100, 0, ',', '.');
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('images/logo-sikadasa.png');

        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }
}
