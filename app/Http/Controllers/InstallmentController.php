<?php

namespace App\Http\Controllers;

use App\Enums\InstallmentStatus;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPayment;
use App\Services\InstallmentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InstallmentController extends Controller
{
    public function __construct(private readonly InstallmentService $installments) {}

    public function index(): Response
    {
        return Inertia::render('installments/index', [
            'installments' => LoanInstallment::query()
                ->with('loan.member')
                ->whereIn('status', [InstallmentStatus::Unpaid, InstallmentStatus::Partial])
                ->orderBy('due_date')
                ->paginate(25),
        ]);
    }

    public function payments(): Response
    {
        return Inertia::render('installments/payments', [
            'payments' => LoanPayment::query()->with('loan.member')->latest('payment_date')->paginate(25),
        ]);
    }

    public function store(Request $request, Loan $loan): RedirectResponse
    {
        $this->authorize('view', $loan);
        $data = $request->validate([
            'payment_scope' => ['required', 'in:installment,full'],
            'installment_id' => ['nullable', 'required_if:payment_scope,installment', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'payment_date' => ['required', 'date'],
        ]);
        $result = $this->installments->pay(
            $loan,
            $data['amount'],
            $data['payment_date'],
            (int) $request->user()->id,
            isset($data['installment_id']) ? (int) $data['installment_id'] : null,
            $data['payment_scope'] === 'full',
        );
        $payment = $result['payment'];
        $message = 'Angsuran berhasil dicatat: '.$payment->payment_number.'.';
        if ($result['cash_transaction_number'] !== null) {
            $message .= ' Kelebihan '.Money::decimal(Money::cents($result['cash_surplus'])).
                ' dicatat sebagai kas masuk '.$result['cash_transaction_number'].'.';
        }

        return back()->with('success', $message);
    }
}