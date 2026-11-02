<?php

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\SavingsPayoutBatch;
use App\Services\HijriCalendarService;
use App\Services\SavingsPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SavingsPayoutController extends Controller
{
    public function __construct(
        private readonly HijriCalendarService $calendar,
        private readonly SavingsPayoutService $payouts,
    ) {}

    public function index(): Response
    {
        $upcoming = $this->calendar->nextPayout();
        $batch = SavingsPayoutBatch::query()
            ->where('hijri_year', $upcoming['hijri_year'])
            ->first();

        return Inertia::render('savings/payouts/index', [
            'upcoming' => $upcoming,
            'existingBatch' => $batch,
            'batches' => SavingsPayoutBatch::query()
                ->with('createdBy:id,name')
                ->withCount('items')
                ->orderByDesc('hijri_year')
                ->limit(20)
                ->get(),
            'outstandingLoanCount' => $this->outstandingLoanCount(),
        ]);
    }

    public function show(SavingsPayoutBatch $batch): Response
    {
        $batch->load([
            'createdBy:id,name',
            'approvedBy:id,name',
            'lastProcessedBy:id,name',
            'items.savingsAccount.member:id,full_name,registration_code',
        ]);

        $loanWarnings = Loan::query()
            ->where('status', LoanStatus::Active)
            ->whereHas('installments', fn ($query) => $query->whereIn('status', ['unpaid', 'partial']))
            ->withSum(['installments as outstanding_amount' => fn ($query) => $query->whereIn('status', ['unpaid', 'partial'])], DB::raw('principal_due + service_due - principal_paid - service_paid'))
            ->get(['id', 'member_id'])
            ->keyBy('member_id');

        $items = $batch->items->map(function ($item) use ($loanWarnings): array {
            $loan = $loanWarnings->get($item->savingsAccount->member_id);

            return [
                'id' => $item->id,
                'amount_due' => $item->amount_due,
                'amount_paid' => $item->amount_paid,
                'member' => [
                    'name' => $item->savingsAccount->member->full_name,
                    'registration_code' => $item->savingsAccount->member->registration_code,
                ],
                'has_outstanding_loan' => $loan !== null,
                'outstanding_loan_amount' => $loan?->outstanding_amount ?? '0.00',
            ];
        });

        return Inertia::render('savings/payouts/show', [
            'batch' => $batch,
            'items' => $items,
            'availableCash' => $this->payouts->availableCash(),
            'payoutDateReached' => $batch->payout_date->lte(today(config('app.timezone'))),
            'outstandingLoanCount' => $loanWarnings->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hijri_year' => ['required', 'integer', 'between:1300,1600'],
            'payout_date' => ['nullable', 'date'],
        ], [
            'hijri_year.required' => 'Tahun Hijriah wajib diisi.',
            'hijri_year.integer' => 'Tahun Hijriah tidak valid.',
            'hijri_year.between' => 'Tahun Hijriah berada di luar rentang yang didukung.',
            'payout_date.date' => 'Tanggal konfirmasi tidak valid.',
        ]);

        $batch = $this->payouts->create(
            (int) $data['hijri_year'],
            $data['payout_date'] ?? null,
            (int) $request->user()->id,
        );

        return redirect()->route('savings.payouts.show', $batch)
            ->with('success', 'Pratinjau batch pencairan dibuat. Periksa kembali sebelum menyetujui.');
    }

    public function approve(Request $request, SavingsPayoutBatch $batch): RedirectResponse
    {
        $this->payouts->approve($batch, (int) $request->user()->id);

        return back()->with('success', 'Batch pencairan berhasil disetujui.');
    }

    public function process(Request $request, SavingsPayoutBatch $batch): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['accepted'],
        ], [
            'confirmation.accepted' => 'Konfirmasi proses pencairan wajib diberikan.',
        ]);
        $this->payouts->process($batch, (int) $request->user()->id);

        return back()->with('success', 'Pencairan batch berhasil diproses dan dicatat pada tabungan serta jurnal.');
    }

    private function outstandingLoanCount(): int
    {
        return Loan::query()
            ->where('status', LoanStatus::Active)
            ->whereHas('installments', fn ($query) => $query->whereIn('status', ['unpaid', 'partial']))
            ->distinct('member_id')
            ->count('member_id');
    }
}
