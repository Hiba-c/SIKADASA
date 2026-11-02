<?php

namespace App\Http\Controllers;

use App\Models\SavingsAccount;
use App\Services\SavingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavingsController extends Controller
{
    public function __construct(private readonly SavingsService $savings) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SavingsAccount::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $accounts = SavingsAccount::query()
            ->with('member')
            ->when(! empty($filters['search']), function ($query) use ($filters): void {
                $search = trim($filters['search']);
                $query->whereHas('member', fn ($member) => $member
                    ->where('full_name', 'like', '%'.$search.'%')
                    ->orWhere('registration_code', 'like', '%'.$search.'%'));
            })
            ->latest('id')->paginate(20)->withQueryString();

        return Inertia::render('savings/index', [
            'accounts' => $accounts,
            'search' => $filters['search'] ?? '',
        ]);
    }

    public function show(SavingsAccount $account): Response
    {
        $this->authorize('view', $account);
        $account->load('member');
        $transactions = $account->transactions()->latest('transaction_date')->latest('id')->paginate(25);

        return Inertia::render('savings/show', ['account' => $account, 'transactions' => $transactions]);
    }

    public function createTransaction(SavingsAccount $account, string $type): Response
    {
        abort_unless(in_array($type, ['deposit', 'withdrawal'], true), 404);
        $this->authorize('transact', $account);

        return Inertia::render($type === 'deposit' ? 'savings/deposits' : 'savings/withdrawals', [
            'account' => $account->load('member'),
            'transaction_date' => now()->toDateString(),
            'type' => $type,
        ]);
    }

    public function storeTransaction(Request $request, SavingsAccount $account): RedirectResponse
    {
        $this->authorize('transact', $account);
        $data = $request->validate([
            'type' => ['required', 'in:deposit,withdrawal'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $transaction = $this->savings->transact($account, $data, (int) $request->user()->id);

        return redirect()->route('savings.index')->with('success', 'Transaksi tabungan berhasil dicatat. '.$transaction->transaction_number);
    }

}