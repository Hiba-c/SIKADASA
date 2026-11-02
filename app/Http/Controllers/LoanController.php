<?php

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Models\Loan;
use App\Models\Member;
use App\Services\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    public function __construct(private readonly LoanService $loans) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate(['status' => ['nullable', 'in:all,pending,approved,rejected,active,paid_off,cancelled']]);
        $loans = Loan::query()->with('member')->latest('id')
            ->when(! empty($filters['status']) && $filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->paginate(20)->withQueryString();

        return Inertia::render('loans/index', ['loans' => $loans, 'status' => $filters['status'] ?? 'all']);
    }

    public function approvals(): Response
    {
        $this->authorize('viewApprovals', Loan::class);

        return Inertia::render('loans/approvals', [
            'loans' => Loan::query()->with('member')->where('status', LoanStatus::Pending)->oldest('application_date')->paginate(20),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Loan::class);

        return Inertia::render('loans/create', [
            'members' => Member::query()->where('status', MemberStatus::Active)->orderBy('full_name')->get(['id', 'full_name', 'registration_code']),
            'service_rate' => $this->loans->serviceRate(),
            'application_date' => now()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Loan::class);
        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'principal_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'installment_count' => ['required', 'integer', 'min:1', 'max:60'],
            'application_date' => ['required', 'date'],
        ]);
        $loan = $this->loans->apply($data, (int) $request->user()->id);

        return redirect()->route('loans.index')->with('success', 'Pengajuan pinjaman berhasil dibuat.');
    }

    public function show(Loan $loan): Response
    {
        $this->authorize('view', $loan);
        $loan->load(['member', 'approvedBy', 'installments' => fn ($query) => $query->orderBy('installment_number'), 'payments' => fn ($query) => $query->latest('payment_date')]);

        return Inertia::render('loans/show', ['loan' => $loan, 'payment_date' => now()->toDateString()]);
    }

    public function approve(Request $request, Loan $loan): RedirectResponse
    {
        $this->authorize('approve', $loan);
        $this->loans->approve($loan, (int) $request->user()->id);

        return back()->with('success', 'Pinjaman disetujui.');
    }

    public function reject(Loan $loan): RedirectResponse
    {
        $this->authorize('reject', $loan);
        $this->loans->reject($loan);

        return back()->with('success', 'Pengajuan pinjaman ditolak.');
    }

    public function disburse(Request $request, Loan $loan): RedirectResponse
    {
        $this->authorize('disburse', $loan);
        $data = $request->validate(['disbursed_at' => ['required', 'date']]);
        $this->loans->disburse($loan, $data['disbursed_at'], (int) $request->user()->id);

        return back()->with('success', 'Pinjaman dicairkan dan jadwal angsuran dibuat.');
    }
}