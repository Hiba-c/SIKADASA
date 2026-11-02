<?php

namespace App\Services;

use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\CashCategory;
use App\Models\CashTransaction;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPayment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallmentService
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly JournalService $journals,
        private readonly AuditService $audit,
    ) {}

    public function pay(
        Loan $loan,
        string $amount,
        string $date,
        int $userId,
        ?int $startingInstallmentId,
        bool $payFull,
    ): array
    {
        return DB::transaction(function () use ($loan, $amount, $date, $userId, $startingInstallmentId, $payFull): array {
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            if ($locked->status !== LoanStatus::Active) {
                throw ValidationException::withMessages(['amount' => 'Pinjaman ini tidak menerima pembayaran.']);
            }
            $receivedAmount = Money::cents($amount);
            $activeInstallments = LoanInstallment::query()
                ->where('loan_id', $locked->id)
                ->whereIn('status', [InstallmentStatus::Unpaid, InstallmentStatus::Partial])
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->get();
            $outstanding = $activeInstallments->sum(fn (LoanInstallment $item): int =>
                max(0, Money::cents($item->service_due) - Money::cents($item->service_paid))
                + max(0, Money::cents($item->principal_due) - Money::cents($item->principal_paid)));
            if ($receivedAmount <= 0 || $outstanding <= 0) {
                throw ValidationException::withMessages(['amount' => 'Tidak ada sisa angsuran yang dapat dibayar.']);
            }

            if ($payFull && $receivedAmount < $outstanding) {
                throw ValidationException::withMessages(['amount' => 'Nominal pelunasan harus mencakup seluruh sisa kewajiban pinjaman.']);
            }

            if ($payFull) {
                $installments = $activeInstallments;
            } else {
                $startingInstallment = $activeInstallments->firstWhere('id', $startingInstallmentId);
                if ($startingInstallment === null) {
                    throw ValidationException::withMessages(['installment_id' => 'Pilih angsuran yang masih aktif.']);
                }

                $installments = $activeInstallments
                    ->filter(fn (LoanInstallment $item): bool => $item->installment_number >= $startingInstallment->installment_number)
                    ->concat($activeInstallments->filter(
                        fn (LoanInstallment $item): bool => $item->installment_number < $startingInstallment->installment_number,
                    ))
                    ->values();
            }

            $remaining = $receivedAmount;
            $servicePaid = 0;
            $principalPaid = 0;
            foreach ($installments as $installment) {
                if ($remaining === 0) {
                    break;
                }
                $serviceDue = max(0, Money::cents($installment->service_due) - Money::cents($installment->service_paid));
                $principalDue = max(0, Money::cents($installment->principal_due) - Money::cents($installment->principal_paid));
                $paidService = min($remaining, $serviceDue);
                $remaining -= $paidService;
                $paidPrincipal = min($remaining, $principalDue);
                $remaining -= $paidPrincipal;
                $servicePaid += $paidService;
                $principalPaid += $paidPrincipal;

                if ($paidService + $paidPrincipal > 0) {
                    $newServicePaid = Money::cents($installment->service_paid) + $paidService;
                    $newPrincipalPaid = Money::cents($installment->principal_paid) + $paidPrincipal;
                    $complete = $newServicePaid >= Money::cents($installment->service_due)
                        && $newPrincipalPaid >= Money::cents($installment->principal_due);
                    $installment->update([
                        'service_paid' => Money::decimal($newServicePaid),
                        'principal_paid' => Money::decimal($newPrincipalPaid),
                        'status' => $complete ? InstallmentStatus::Paid : InstallmentStatus::Partial,
                    ]);
                }
            }

            $total = $servicePaid + $principalPaid;
            $cashSurplus = $receivedAmount - $total;
            $payment = $locked->payments()->create([
                'payment_number' => $this->sequences->next('ANG'),
                'payment_date' => $date,
                'principal_amount' => Money::decimal($principalPaid),
                'service_amount' => Money::decimal($servicePaid),
                'total_amount' => Money::decimal($total),
                'status' => TransactionStatus::Posted,
                'received_by' => $userId,
            ]);
            if (! $locked->installments()->where('status', '!=', InstallmentStatus::Paid)->exists()) {
                $locked->update(['status' => LoanStatus::PaidOff]);
            }

            $lines = [
                ['account_code' => '1001', 'debit' => $total, 'credit' => 0],
                ['account_code' => '1101', 'debit' => 0, 'credit' => $principalPaid],
            ];
            if ($servicePaid > 0) {
                $lines[] = ['account_code' => '4001', 'debit' => 0, 'credit' => $servicePaid];
            }
            $this->journals->post(
                $payment->payment_number,
                $date,
                'Pembayaran pinjaman '.$locked->loan_number,
                $payment,
                $userId,
                $lines,
            );

            $cashTransaction = null;
            if ($cashSurplus > 0) {
                $locked->loadMissing('member');
                $category = CashCategory::query()->firstOrCreate(
                    ['name' => 'Kelebihan pembayaran pinjaman', 'type' => TransactionType::Income->value],
                    ['description' => 'Pemasukan otomatis dari kelebihan pembayaran pinjaman.', 'is_active' => true],
                );
                if (! $category->is_active) {
                    $category->update(['is_active' => true]);
                }

                $cashTransaction = CashTransaction::query()->create([
                    'transaction_number' => $this->sequences->next('KAS'),
                    'category_id' => $category->id,
                    'transaction_type' => TransactionType::Income,
                    'payer_name' => $locked->member->full_name,
                    'transaction_date' => $date,
                    'amount' => Money::decimal($cashSurplus),
                    'description' => 'Kelebihan pembayaran setelah pelunasan pinjaman '.$locked->loan_number,
                    'status' => TransactionStatus::Posted,
                    'created_by' => $userId,
                ]);
                $this->journals->post(
                    $cashTransaction->transaction_number,
                    $date,
                    $cashTransaction->description,
                    $cashTransaction,
                    $userId,
                    [
                        ['account_code' => '1001', 'debit' => $cashSurplus, 'credit' => 0],
                        ['account_code' => '4002', 'debit' => 0, 'credit' => $cashSurplus],
                    ],
                );
                $this->audit->record('cash', 'created', $cashTransaction, null, $cashTransaction->only([
                    'transaction_number', 'category_id', 'transaction_type', 'payer_name', 'transaction_date', 'amount',
                ]));
            }

            $this->audit->record('loans', 'installment_paid', $payment, null, [
                'loan_id' => $locked->id,
                'principal_amount' => $payment->principal_amount,
                'service_amount' => $payment->service_amount,
                'total_amount' => $payment->total_amount,
                'cash_surplus' => Money::decimal($cashSurplus),
                'cash_transaction_number' => $cashTransaction?->transaction_number,
            ]);

            return [
                'payment' => $payment->load('loan.member'),
                'cash_surplus' => Money::decimal($cashSurplus),
                'cash_transaction_number' => $cashTransaction?->transaction_number,
            ];
        });
    }

}