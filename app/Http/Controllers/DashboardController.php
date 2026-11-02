<?php

namespace App\Http\Controllers;

use App\Enums\ArisanCycleStatus;
use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Models\ArisanCycle;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $today = CarbonImmutable::today();
        $from = isset($filters['from'])
            ? CarbonImmutable::parse($filters['from'])
            : $today->subDays(6);
        $to = isset($filters['to'])
            ? CarbonImmutable::parse($filters['to'])
            : $today;

        $cashAccount = DB::table('accounts')->where('account_code', '1001')->first();
        $cashBalance = $cashAccount
            ? $this->accountBalance('1001')
            : 0;
        $savingsBalance = Money::cents(SavingsAccount::query()->sum('current_balance'));
        $loanOutstanding = Money::cents(
            DB::table('loan_installments')
                ->join('loans', 'loans.id', '=', 'loan_installments.loan_id')
                ->where('loans.status', LoanStatus::Active->value)
                ->sum(DB::raw(
                    '(loan_installments.principal_due - loan_installments.principal_paid) + '.
                    '(loan_installments.service_due - loan_installments.service_paid)',
                )),
        );
        $savingsAvailable = max(0, $savingsBalance - $loanOutstanding);
        $activeLoanCount = Loan::query()->where('status', LoanStatus::Active)->count();
        $pendingLoanCount = Loan::query()->where('status', LoanStatus::Pending)->count();

        $cashFlow = $this->cashFlow($from, $to);
        $latestTransactions = JournalEntry::query()
            ->where('status', 'posted')
            ->whereHas('lines.account', fn ($query) => $query->where('account_code', '1001'))
            ->with(['createdBy:id,name', 'lines.account:id,account_code'])
            ->latest('created_at')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function (JournalEntry $entry): array {
                $cashLines = $entry->lines->filter(
                    fn ($line): bool => $line->account?->account_code === '1001',
                );
                $inflow = $cashLines->sum(fn ($line): int => Money::cents($line->debit));
                $outflow = $cashLines->sum(fn ($line): int => Money::cents($line->credit));

                return [
                    'id' => $entry->id,
                    'transaction_number' => $entry->transaction_number,
                    'description' => $entry->description,
                    'transaction_date' => $entry->transaction_date->toDateString(),
                    'recorded_at' => $entry->created_at?->toIso8601String(),
                    'created_by' => $entry->createdBy?->name ?? 'Pengguna dihapus',
                    'direction' => $inflow >= $outflow ? 'income' : 'expense',
                    'amount' => Money::decimal(abs($inflow - $outflow)),
                ];
            })
            ->values();

        $activeArisanCycles = ArisanCycle::query()
            ->where('status', ArisanCycleStatus::Active)
            ->withCount(['participants', 'rounds'])
            ->orderByDesc('started_at')
            ->limit(5)
            ->get(['id', 'cycle_number', 'name', 'started_at'])
            ->map(fn (ArisanCycle $cycle): array => [
                'id' => $cycle->id,
                'cycle_number' => $cycle->cycle_number,
                'name' => $cycle->name,
                'started_at' => $cycle->started_at?->toDateString(),
                'participants_count' => $cycle->participants_count,
                'rounds_count' => $cycle->rounds_count,
            ]);

        return Inertia::render('dashboard/index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'cash_balance' => Money::decimal($cashBalance),
                'cash_account_name' => $cashAccount->account_name ?? 'Kas Kelompok',
                'active_members' => Member::query()->where('status', MemberStatus::Active)->count(),
                'inactive_members' => Member::query()->where('status', MemberStatus::Inactive)->count(),
                'savings_balance' => Money::decimal($savingsBalance),
                'savings_available' => Money::decimal($savingsAvailable),
                'loan_outstanding' => Money::decimal($loanOutstanding),
                'active_loans' => $activeLoanCount,
                'period_income' => $this->accountTypeTotal('income', $from, $to, 'credit', 'debit'),
                'period_expense' => $this->accountTypeTotal('expense', $from, $to, 'debit', 'credit'),
            ],
            'loan_statuses' => [
                'pending' => $pendingLoanCount,
                'active' => $activeLoanCount,
            ],
            'cash_flow' => $cashFlow,
            'latest_transactions' => $latestTransactions,
            'active_arisan' => [
                'count' => ArisanCycle::query()->where('status', ArisanCycleStatus::Active)->count(),
                'cycles' => $activeArisanCycles,
            ],
        ]);
    }

    private function accountBalance(string $accountCode): int
    {
        $balance = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.status', 'posted')
            ->where('accounts.account_code', $accountCode)
            ->selectRaw('COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as balance')
            ->value('balance');

        return Money::cents($balance);
    }

    private function accountTypeTotal(
        string $accountType,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $positiveSide,
        string $negativeSide,
    ): string {
        $total = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.status', 'posted')
            ->where('accounts.account_type', $accountType)
            ->whereDate('journal_entries.transaction_date', '>=', $from->toDateString())
            ->whereDate('journal_entries.transaction_date', '<=', $to->toDateString())
            ->selectRaw(
                "COALESCE(SUM(journal_lines.{$positiveSide} - journal_lines.{$negativeSide}), 0) as total",
            )
            ->value('total');

        return Money::decimal(Money::cents($total));
    }

    private function cashFlow(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $monthly = $from->diffInDays($to) > 61;
        $dailyTotals = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.status', 'posted')
            ->where('accounts.account_code', '1001')
            ->whereDate('journal_entries.transaction_date', '>=', $from->toDateString())
            ->whereDate('journal_entries.transaction_date', '<=', $to->toDateString())
            ->groupByRaw('DATE(journal_entries.transaction_date)')
            ->selectRaw(
                'DATE(journal_entries.transaction_date) as date, '.
                'COALESCE(SUM(journal_lines.debit), 0) as income, '.
                'COALESCE(SUM(journal_lines.credit), 0) as expense',
            )
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row): string => $monthly
                ? CarbonImmutable::parse($row->date)->format('Y-m')
                : CarbonImmutable::parse($row->date)->toDateString());

        $cursor = $monthly ? $from->startOfMonth() : $from;
        $last = $monthly ? $to->startOfMonth() : $to;
        $interval = $monthly ? 'month' : 'day';
        $flow = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $key = $monthly ? $cursor->format('Y-m') : $cursor->toDateString();
            $row = $dailyTotals->get($key);
            $flow[] = [
                'label' => $monthly ? $cursor->format('m/Y') : $cursor->format('d/m'),
                'income' => Money::decimal(Money::cents($row?->income ?? '0.00')),
                'expense' => Money::decimal(Money::cents($row?->expense ?? '0.00')),
            ];
            $cursor = $cursor->add(1, $interval);
        }

        return $flow;
    }
}
