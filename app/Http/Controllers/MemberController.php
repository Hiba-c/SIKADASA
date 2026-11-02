<?php

namespace App\Http\Controllers;

use App\Http\Requests\Member\DeactivateMemberRequest;
use App\Http\Requests\Member\StoreMemberRequest;
use App\Http\Requests\Member\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\ArisanParticipant;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MemberController extends Controller
{
    public function __construct(private readonly MemberService $members) {}

    public function index(Request $request): InertiaResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,active,inactive'],
        ]);

        $query = Member::query()->latest('id');
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? 'all';

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('full_name', 'like', '%'.$search.'%')
                    ->orWhere('registration_code', 'like', '%'.$search.'%');
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return Inertia::render('members/index', [
            'members' => MemberResource::collection($query->paginate(config('sikadasa.per_page'))->withQueryString()),
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function create(): InertiaResponse
    {
        $this->authorize('create', Member::class);

        return Inertia::render('members/create', [
            'joined_at' => now()->toDateString(),
        ]);
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $member = $this->members->create($request->validated(), (int) $request->user()->id);

        return redirect()->route('members.index')->with('success', 'Anggota berhasil ditambahkan.');
    }

    public function show(Request $request, Member $member): InertiaResponse
    {
        $this->authorize('view', $member);

        $member->load([
            'savingsAccount.transactions' => fn ($query) => $query->latest('transaction_date')->latest('id')->limit(10),
            'loans' => fn ($query) => $query->latest('id')->with([
                'installments' => fn ($installments) => $installments->orderBy('installment_number'),
                'payments' => fn ($payments) => $payments->latest('payment_date')->latest('id'),
            ]),
            'arisanParticipants.cycle',
            'arisanParticipants.payments.round',
        ]);

        return Inertia::render('members/show', [
            'member' => (new MemberResource($member))->resolve(),
            'savings' => [
                'balance' => $member->savingsAccount?->current_balance ?? '0.00',
                'transactions' => $member->savingsAccount?->transactions
                    ->map(fn ($transaction): array => [
                        'id' => $transaction->id,
                        'transaction_number' => $transaction->transaction_number,
                        'type' => $transaction->type,
                        'amount' => $transaction->amount,
                        'transaction_date' => $transaction->transaction_date->toDateString(),
                        'created_at' => $transaction->created_at->toIso8601String(),
                        'status' => $transaction->status->value,
                    ])->values() ?? [],
            ],
            'loans' => $member->loans->map(fn ($loan): array => [
                'id' => $loan->id,
                'loan_number' => $loan->loan_number,
                'principal_amount' => $loan->principal_amount,
                'service_amount' => $loan->service_amount,
                'total_obligation' => $loan->total_obligation,
                'status' => $loan->status->value,
                'created_at' => $loan->created_at->toIso8601String(),
                'installments' => $loan->installments->map(fn ($installment): array => [
                    'installment_number' => $installment->installment_number,
                    'due_date' => $installment->due_date->toDateString(),
                    'principal_due' => $installment->principal_due,
                    'service_due' => $installment->service_due,
                    'principal_paid' => $installment->principal_paid,
                    'service_paid' => $installment->service_paid,
                    'status' => $installment->status->value,
                ])->values(),
                'payments' => $loan->payments->map(fn ($payment): array => [
                    'payment_number' => $payment->payment_number,
                    'payment_date' => $payment->payment_date->toDateString(),
                    'total_amount' => $payment->total_amount,
                    'created_at' => $payment->created_at->toIso8601String(),
                    'status' => $payment->status->value,
                ])->values(),
            ])->values(),
            'arisan' => $member->arisanParticipants->map(fn (ArisanParticipant $participant): array => [
                'id' => $participant->id,
                'cycle_name' => $participant->cycle->name,
                'has_won' => $participant->has_won,
                'won_at' => $participant->won_at?->toDateTimeString(),
                'payments' => $participant->payments->map(fn ($payment): array => [
                    'transaction_number' => $payment->transaction_number,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date->toDateString(),
                    'created_at' => $payment->created_at->toIso8601String(),
                    'round_number' => $payment->round->round_number,
                    'status' => $payment->status,
                ])->values(),
            ])->values(),
            'deactivation_check' => $request->user()->isKetua()
                ? $this->members->deactivationCheck($member)
                : null,
        ]);
    }

    public function edit(Member $member): InertiaResponse
    {
        $this->authorize('update', $member);

        return Inertia::render('members/edit', [
            'member' => (new MemberResource($member))->resolve(),
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $this->members->update($member, $request->validated());

        return redirect()->route('members.show', $member)->with('success', 'Data anggota berhasil diperbarui.');
    }

    public function deactivate(DeactivateMemberRequest $request, Member $member): RedirectResponse
    {
        $this->members->deactivate($member, $request->validated('inactive_reason'));

        return redirect()->route('members.show', $member)->with('success', 'Anggota berhasil dinonaktifkan.');
    }

    public function activate(Request $request, Member $member): RedirectResponse
    {
        $this->authorize('changeStatus', $member);
        $this->members->activate($member);

        return redirect()->route('members.show', $member)->with('success', 'Anggota berhasil diaktifkan kembali.');
    }
}