import { Head, Link, usePage } from '@inertiajs/react';
import { formatDateShort, formatDateTime } from '@/lib/date';
import { formatCurrency } from '@/lib/currency';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@/types/auth';

type DashboardProps = {
    filters: { from: string; to: string };
    summary: {
        cash_balance: string;
        cash_account_name: string;
        active_members: number;
        inactive_members: number;
        savings_balance: string;
        savings_available: string;
        loan_outstanding: string;
        active_loans: number;
        period_income: string;
        period_expense: string;
    };
    loan_statuses: {
        pending: number;
        active: number;
    };
    cash_flow: Array<{ label: string; income: string; expense: string }>;
    latest_transactions: Array<{
        id: number;
        transaction_number: string;
        description: string;
        transaction_date: string;
        recorded_at: string | null;
        created_by: string;
        direction: 'income' | 'expense';
        amount: string;
    }>;
    active_arisan: {
        count: number;
        cycles: Array<{
            id: number;
            cycle_number: string;
            name: string;
            started_at: string | null;
            participants_count: number;
            rounds_count: number;
        }>;
    };
};

function money(value: string): string {
    return formatCurrency(Number(value));
}

export default function DashboardPage({ filters, summary, loan_statuses, cash_flow, latest_transactions, active_arisan }: DashboardProps) {
    const username = usePage<PageProps>().props.auth.user?.username ?? 'Pengguna';
    const cards = [
        { label: 'Saldo kas', value: money(summary.cash_balance), detail: summary.cash_account_name, marker: 'bg-emerald-500' },
        { label: 'Anggota aktif', value: summary.active_members.toLocaleString('id-ID'), detail: 'Jumlah anggota berstatus aktif', marker: 'bg-emerald-500', href: '/members?status=active' },
        { label: 'Anggota tidak aktif', value: summary.inactive_members.toLocaleString('id-ID'), detail: 'Jumlah anggota berstatus nonaktif', marker: 'bg-slate-400', href: '/members?status=inactive' },
        { label: 'Total tabungan anggota', value: money(summary.savings_balance), detail: 'Saldo tabungan seluruh anggota', marker: 'bg-emerald-500' },
        { label: 'Tabungan siap', value: money(summary.savings_available), detail: 'Setelah dikurangi sisa pinjaman aktif', marker: 'bg-emerald-500' },
        { label: 'Sisa piutang pinjaman', value: money(summary.loan_outstanding), detail: `${summary.active_loans.toLocaleString('id-ID')} pinjaman berjalan`, marker: 'bg-emerald-500' },
        { label: 'Pemasukan periode', value: money(summary.period_income), detail: 'Berdasarkan jurnal terposting', marker: 'bg-emerald-500' },
        { label: 'Pengeluaran periode', value: money(summary.period_expense), detail: 'Berdasarkan jurnal terposting', marker: 'bg-emerald-500' },
    ];
    const maximumFlow = Math.max(
        1,
        ...cash_flow.flatMap((item) => [Number(item.income), Number(item.expense)]),
    );
    const periodIncome = Number(summary.period_income);
    const periodExpense = Number(summary.period_expense);
    const periodBalance = periodIncome - periodExpense;
    const chartIncome = Math.max(0, periodIncome);
    const chartExpense = Math.max(0, periodExpense);
    const periodTotal = chartIncome + chartExpense;
    const incomePercentage = periodTotal > 0 ? (chartIncome / periodTotal) * 100 : 0;
    const expensePercentage = periodTotal > 0 ? (chartExpense / periodTotal) * 100 : 0;
    const pieChartStyle = periodTotal > 0
        ? { background: `conic-gradient(#10b981 0% ${incomePercentage}%, #f43f5e ${incomePercentage}% 100%)` }
        : { background: '#e2e8f0' };

    return (
        <AppLayout>
            <Head title="Dashboard" />
            <div className="page-title-surface mb-6 grid gap-5 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm shadow-emerald-950/[0.04] sm:p-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">Ringkasan Dasawisma</p>
                    <h1 className="mt-1.5 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Dashboard</h1>
                    <p className="mt-2 max-w-lg text-sm text-slate-500">Selamat datang, <span className="font-semibold text-slate-700">{username}</span>.</p>
                </div>
                <form method="get" action="/dashboard" className="flex w-full flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3 lg:w-auto lg:justify-self-end">
                    <p className="w-full text-xs text-slate-500">Tanpa filter, menampilkan 7 hari terakhir.</p>
                    <label className="min-w-0 flex-1 text-xs font-medium text-slate-600 sm:flex-none">
                        Dari tanggal
                        <input name="from" type="date" defaultValue={filters.from} className="mt-1.5 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900" />
                    </label>
                    <label className="min-w-0 flex-1 text-xs font-medium text-slate-600 sm:flex-none">
                        Sampai tanggal
                        <input name="to" type="date" defaultValue={filters.to} className="mt-1.5 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900" />
                    </label>
                    <button type="submit" className="flex-1 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 sm:flex-none">
                        Terapkan
                    </button>
                    <a href="/dashboard" className="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 sm:flex-none">
                        Reset filter
                    </a>
                </form>
            </div>

            <section aria-label="Ringkasan keuangan dan anggota" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map((card) => {
                    const content = (
                        <>
                            <div className="flex items-center gap-2">
                                <span className={`h-2 w-2 rounded-full ${card.marker}`} />
                                <p className="text-sm font-medium text-slate-500">{card.label}</p>
                            </div>
                            <p className="mt-2 break-words text-2xl font-semibold tracking-tight text-slate-900">{card.value}</p>
                            <p className="mt-1 text-xs text-slate-500">{card.detail}</p>
                        </>
                    );

                    return card.href ? (
                        <Link
                            key={card.label}
                            href={card.href}
                            className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700"
                        >
                            {content}
                            <span className="mt-3 inline-block text-xs font-semibold text-emerald-800">Lihat anggota →</span>
                        </Link>
                    ) : (
                        <article key={card.label} className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md">
                            {content}
                        </article>
                    );
                })}
            </section>

            <section aria-labelledby="loan-status-heading" className="mt-6">
                <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 id="loan-status-heading" className="font-semibold text-slate-900">Status pinjaman</h2>
                        <p className="mt-1 text-sm text-slate-500">Pantau pengajuan dan pinjaman yang sedang berjalan.</p>
                    </div>
                    <Link href="/loans" className="text-sm font-medium text-emerald-800 hover:underline">Semua pinjaman</Link>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Link
                        href="/loans?status=pending"
                        className="group flex items-center justify-between rounded-2xl border border-amber-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700"
                    >
                        <div>
                            <p className="text-sm font-medium text-slate-500">Menunggu persetujuan</p>
                            <p className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{loan_statuses.pending.toLocaleString('id-ID')}</p>
                            <p className="mt-1 text-xs text-slate-500">Pengajuan pinjaman</p>
                        </div>
                        <span aria-hidden="true" className="grid h-10 w-10 place-items-center rounded-full bg-amber-50 text-amber-700 transition group-hover:bg-amber-100">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-5 w-5"><path strokeLinecap="round" strokeLinejoin="round" d="M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        </span>
                    </Link>
                    <Link
                        href="/loans?status=active"
                        className="group flex items-center justify-between rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700"
                    >
                        <div>
                            <p className="text-sm font-medium text-slate-500">Sedang berlangsung</p>
                            <p className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{loan_statuses.active.toLocaleString('id-ID')}</p>
                            <p className="mt-1 text-xs text-slate-500">Pinjaman aktif</p>
                        </div>
                        <span aria-hidden="true" className="grid h-10 w-10 place-items-center rounded-full bg-emerald-50 text-emerald-700 transition group-hover:bg-emerald-100">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-5 w-5"><path strokeLinecap="round" strokeLinejoin="round" d="M4 19V5m0 14h16M8 15l3-4 3 2 5-7" /></svg>
                        </span>
                    </Link>
                </div>
            </section>

            <div className="mt-6 grid gap-6 xl:grid-cols-4">
                <section aria-labelledby="income-expense-heading" className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <h2 id="income-expense-heading" className="font-semibold text-slate-900">Pemasukan &amp; pengeluaran</h2>
                            <p className="mt-1 text-sm text-slate-500">Perbandingan periode</p>
                        </div>
                        <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Keuangan</span>
                    </div>
                    <p className="mt-1 text-sm text-slate-500">
                        {formatDateShort(filters.from)} – {formatDateShort(filters.to)}
                    </p>
                    <div className="mt-5 flex flex-wrap items-center justify-center gap-5">
                        <div
                            role="img"
                            aria-label={`Diagram lingkaran: pemasukan ${money(summary.period_income)} (${incomePercentage.toLocaleString('id-ID', { maximumFractionDigits: 1 })}%), pengeluaran ${money(summary.period_expense)} (${expensePercentage.toLocaleString('id-ID', { maximumFractionDigits: 1 })}%), ${periodBalance < 0 ? 'defisit' : 'sisa'} ${money(periodBalance.toFixed(2))}`}
                            className="relative grid h-36 w-36 shrink-0 place-items-center rounded-full shadow-sm"
                            style={pieChartStyle}
                        >
                            <div aria-hidden="true" className="grid h-[68%] w-[68%] place-content-center rounded-full bg-white text-center shadow-inner">
                                <span className="text-[10px] font-medium uppercase tracking-wide text-slate-500">{periodBalance < 0 ? 'Defisit' : 'Sisa'}</span>
                                <span className={`mt-0.5 max-w-[5.5rem] break-words text-xs font-bold tracking-tight ${periodBalance < 0 ? 'text-rose-700' : 'text-slate-900'}`}>
                                    {money(periodBalance.toFixed(2))}
                                </span>
                            </div>
                        </div>
                        <ul className="min-w-32 flex-1 space-y-3 text-sm">
                            <li>
                                <span className="flex items-center gap-2 text-slate-600">
                                    <span className="h-2.5 w-2.5 shrink-0 rounded-sm bg-emerald-500" />
                                    Pemasukan
                                </span>
                                <p className="ml-[18px] mt-0.5 font-semibold text-slate-900">{money(summary.period_income)}</p>
                                <p className="ml-[18px] text-xs text-slate-500">{incomePercentage.toLocaleString('id-ID', { maximumFractionDigits: 1 })}%</p>
                            </li>
                            <li>
                                <span className="flex items-center gap-2 text-slate-600">
                                    <span className="h-2.5 w-2.5 shrink-0 rounded-sm bg-rose-500" />
                                    Pengeluaran
                                </span>
                                <p className="ml-[18px] mt-0.5 font-semibold text-slate-900">{money(summary.period_expense)}</p>
                                <p className="ml-[18px] text-xs text-slate-500">{expensePercentage.toLocaleString('id-ID', { maximumFractionDigits: 1 })}%</p>
                            </li>
                        </ul>
                    </div>
                </section>

                <section aria-labelledby="cash-flow-heading" className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:shadow-md xl:col-span-2">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 id="cash-flow-heading" className="font-semibold text-slate-900">Arus kas</h2>
                            <p className="mt-1 text-sm text-slate-500">
                                {formatDateShort(filters.from)} – {formatDateShort(filters.to)} · Saldo kas berdasarkan jurnal
                            </p>
                        </div>
                        <div className="flex gap-3 text-xs text-slate-600">
                            <span><span className="mr-1.5 inline-block h-2.5 w-2.5 rounded-sm bg-emerald-500" />Pemasukan</span>
                            <span><span className="mr-1.5 inline-block h-2.5 w-2.5 rounded-sm bg-rose-500" />Pengeluaran</span>
                        </div>
                    </div>
                    <div className="mt-5 flex h-56 items-end gap-1 overflow-x-auto rounded-xl border-b border-slate-200 bg-slate-50/70 px-2 pb-2 sm:gap-2">
                        {cash_flow.map((item) => {
                            const incomeHeight = Math.max(2, (Number(item.income) / maximumFlow) * 150);
                            const expenseHeight = Math.max(2, (Number(item.expense) / maximumFlow) * 150);

                            return (
                                <div key={item.label} className="flex min-w-8 flex-1 flex-col items-center justify-end gap-2">
                                    <div className="flex h-40 items-end gap-1">
                                        <span
                                            title={`Pemasukan ${item.label}: ${money(item.income)}`}
                                            className="w-2 rounded-t bg-emerald-500 sm:w-3"
                                            style={{ height: `${incomeHeight}px` }}
                                        />
                                        <span
                                            title={`Pengeluaran ${item.label}: ${money(item.expense)}`}
                                            className="w-2 rounded-t bg-rose-500 sm:w-3"
                                            style={{ height: `${expenseHeight}px` }}
                                        />
                                    </div>
                                    <span className="text-[10px] text-slate-500">{item.label}</span>
                                </div>
                            );
                        })}
                    </div>
                </section>

                <section aria-labelledby="arisan-heading" className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h2 id="arisan-heading" className="font-semibold text-slate-900">Arisan aktif</h2>
                            <p className="mt-1 text-sm text-slate-500">{active_arisan.count.toLocaleString('id-ID')} siklus berjalan</p>
                        </div>
                        <Link href="/arisan" className="text-sm font-medium text-emerald-700 hover:underline">Lihat</Link>
                    </div>
                    {active_arisan.cycles.length === 0 ? (
                        <p className="mt-5 text-sm text-slate-500">Belum ada siklus arisan aktif.</p>
                    ) : (
                        <ul className="mt-4 divide-y divide-slate-100">
                            {active_arisan.cycles.map((cycle) => (
                                <li key={cycle.id} className="py-3 first:pt-0 last:pb-0">
                                    <Link href={`/arisan/${cycle.id}`} className="record-link">{cycle.name}</Link>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {cycle.participants_count.toLocaleString('id-ID')} anggota · {cycle.rounds_count.toLocaleString('id-ID')} putaran
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <section aria-labelledby="recent-transactions-heading" className="mt-6 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="recent-transactions-heading" className="font-semibold text-slate-900">Transaksi terbaru</h2>
                        <p className="mt-1 text-sm text-slate-500">Transaksi yang memengaruhi saldo kas.</p>
                    </div>
                    <Link href="/reports" className="text-sm font-medium text-emerald-700 hover:underline">Lihat laporan</Link>
                </div>
                {latest_transactions.length === 0 ? (
                    <p className="py-5 text-sm text-slate-500">Belum ada transaksi keuangan.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[640px] text-left text-sm">
                            <thead className="border-b border-slate-200 text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Tanggal / waktu dicatat</th>
                                    <th className="px-3 py-2 font-medium">Transaksi</th>
                                    <th className="px-3 py-2 font-medium">Petugas</th>
                                    <th className="px-3 py-2 text-right font-medium">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {latest_transactions.map((transaction) => (
                                    <tr key={transaction.id}>
                                        <td className="px-3 py-3 text-slate-600">
                                            {formatDateShort(transaction.transaction_date)}
                                            {transaction.recorded_at && <span className="mt-1 block text-xs text-slate-500">{formatDateTime(transaction.recorded_at)}</span>}
                                        </td>
                                        <td className="px-3 py-3">
                                            <p className="font-medium text-slate-800">{transaction.description}</p>
                                            <p className="mt-1 text-xs text-slate-500">{transaction.transaction_number}</p>
                                        </td>
                                        <td className="px-3 py-3 text-slate-600">{transaction.created_by}</td>
                                        <td className={`px-3 py-3 text-right font-semibold ${transaction.direction === 'income' ? 'text-emerald-700' : 'text-rose-700'}`}>
                                            {transaction.direction === 'income' ? '+' : '−'}{money(transaction.amount)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}