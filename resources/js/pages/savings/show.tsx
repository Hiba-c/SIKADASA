import { Head, Link, usePage } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { formatDateShort, formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';
import type { SavingsAccount } from '@/types/member';
import type { SavingsTransaction } from '@/types/finance';
import type { LaravelPaginator } from '@/types';

type AccountDetail = SavingsAccount;

export default function SavingsShowPage() {
    const { account, transactions } = usePage<PageProps<{ account: AccountDetail; transactions: LaravelPaginator<SavingsTransaction> }>>().props;

    return (
        <AppLayout>
            <Head title={`Tabungan ${account.member?.full_name ?? ''}`} />
            <PageHeader title={account.member?.full_name ?? 'Rekening tabungan'} description={account.member?.registration_code ?? ''} action={<Link href="/savings" className="rounded-md border bg-white px-3 py-2 text-sm">Daftar rekening</Link>} />
            <section className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                <div><p className="text-sm text-slate-500">Saldo saat ini</p><p className="mt-1 text-2xl font-semibold">{formatCurrency(Number(account.current_balance))}</p></div>
                <div className="flex gap-2">
                    <Link href={`/savings/${account.id}/deposit`} className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white">Setor</Link>
                    <Link href={`/savings/${account.id}/withdrawal`} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium">Tarik</Link>
                </div>
            </section>
            <section className="overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <table className="w-full text-left text-sm">
                    <thead><tr><th className="px-4 py-3">Nomor</th><th className="px-4 py-3">Tanggal</th><th className="px-4 py-3">Waktu dicatat</th><th className="px-4 py-3">Jenis</th><th className="px-4 py-3">Jumlah</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Keterangan</th><th className="px-4 py-3">Kuitansi</th></tr></thead>
                    <tbody className="divide-y divide-slate-100">{transactions.data.map((transaction) => (
                        <tr key={transaction.id}>
                            <td className="px-4 py-3 font-medium">{transaction.transaction_number}</td><td className="px-4 py-3">{formatDateShort(transaction.transaction_date)}</td><td className="px-4 py-3">{formatDateTime(transaction.created_at)}</td>
                            <td className="px-4 py-3">{transaction.type === 'deposit' ? 'Setor' : 'Tarik'}</td><td className="px-4 py-3">{formatCurrency(Number(transaction.amount))}</td>
                            <td className="px-4 py-3"><StatusBadge status={transaction.status} label={transaction.status === 'posted' ? 'Tercatat' : 'Dibatalkan'} />{transaction.reversed_at && <span className="mt-1 block text-xs text-slate-500">{formatDateTime(transaction.reversed_at)}</span>}</td><td className="px-4 py-3">{transaction.description || '—'}</td><td className="px-4 py-3"><a className="record-link" href={`/receipts/savings/${transaction.id}`} target="_blank" rel="noreferrer">Lihat</a></td>
                        </tr>
                    ))}</tbody>
                </table>
                {transactions.data.length === 0 && <p className="p-5 text-sm text-slate-500">Belum ada transaksi tabungan.</p>}
            </section>
            {transactions.last_page > 1 && <div className="mt-4 flex justify-end gap-2">{transactions.links.map((link, index) => link.url ? <Link key={index} href={link.url} className="rounded border bg-white px-3 py-1 text-sm">{link.label.replace(/&laquo;|&raquo;/g, '')}</Link> : null)}</div>}
        </AppLayout>
    );
}