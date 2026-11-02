<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\CashCategory;
use App\Models\CashTransaction;
use App\Services\AuditService;
use App\Services\CashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CashController extends Controller
{
    public function __construct(
        private readonly CashService $cash,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:income,expense'],
            'status' => ['nullable', 'in:posted,reversed'],
        ]);
        $query = CashTransaction::query()->with(['category', 'creator:id,name'])->latest('transaction_date')->latest('id');
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('transaction_number', 'like', '%'.$search.'%')
                    ->orWhere('payer_name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', '%'.$search.'%'));
            });
        }
        if (! empty($filters['type'])) {
            $query->where('transaction_type', $filters['type']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return Inertia::render('cash/index', [
            'transactions' => $query->paginate(20)->withQueryString(),
            'categories' => CashCategory::query()->where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'filters' => ['search' => $filters['search'] ?? '', 'type' => $filters['type'] ?? 'all', 'status' => $filters['status'] ?? 'all'],
            'totals' => [
                'income' => CashTransaction::query()->where('status', TransactionStatus::Posted)->where('transaction_type', TransactionType::Income)->sum('amount'),
                'expense' => CashTransaction::query()->where('status', TransactionStatus::Posted)->where('transaction_type', TransactionType::Expense)->sum('amount'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', CashTransaction::class);
        $categories = CashCategory::query()->where('is_active', true)->orderBy('type')->orderBy('name')->get();
        $selectedCategory = $categories->firstWhere('id', $request->integer('category_id'));

        return Inertia::render('cash/create', [
            'categories' => $categories,
            'transaction_date' => now()->toDateString(),
            'selected_category_id' => $selectedCategory?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'payer_name' => trim((string) $request->input('payer_name')),
            'description' => trim((string) $request->input('description')),
        ]);
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:cash_categories,id'],
            'transaction_type' => ['required', 'in:income,expense'],
            'payer_name' => ['required', 'string', 'min:2', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'description' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $transaction = $this->cash->create($data, (int) $request->user()->id);

        return redirect()->route('cash.index')->with('success', 'Transaksi kas berhasil dicatat.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorize('create', CashTransaction::class);
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('cash_categories', 'name')->where('type', $request->input('type')),
            ],
            'type' => ['required', 'in:income,expense'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $category = CashCategory::query()->create([
            ...$data,
            'is_active' => true,
        ]);
        $this->audit->record('cash', 'category_created', $category, null, [
            'name' => $category->name,
            'type' => $category->type->value,
        ]);

        return redirect()->route('cash.create', ['category_id' => $category->id])
            ->with('success', 'Kategori kas berhasil ditambahkan.');
    }

    public function show(CashTransaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        return Inertia::render('cash/show', ['transaction' => $transaction->load('category', 'creator', 'reversedBy')]);
    }

    public function reverse(Request $request, CashTransaction $transaction): RedirectResponse
    {
        $this->authorize('reverse', $transaction);
        $data = $request->validate(['reversal_reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->cash->reverse($transaction, (int) $request->user()->id, $data['reversal_reason']);

        return back()->with('success', 'Transaksi kas berhasil dibatalkan.');
    }
}