import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { formatDateShort, formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';
import type { CashTransaction } from '@/types/finance';

export default function CashShowPage() {
    const { transaction, auth } = usePage<PageProps<{ transaction: CashTransaction }>>().props;
    const form = useForm({ reversal_reason: '' });
    function reverse(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/cash/${transaction.id}/reverse`);
    }

    return (
        <AppLayout>
            <Head title={transaction.transaction_number} />
            <PageHeader title={transaction.transaction_number} description="Detail transaksi kas." action={<Link href="/cash" className="rounded-md border bg-white px-3 py-2 text-sm">Kembali</Link>} />
            <section className="max-w-2xl space-y-4 rounded-lg border border-slate-200 bg-white p-5">
                <a href={`/receipts/cash/${transaction.id}`} target="_blank" rel="noreferrer" className="inline-flex rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Lihat / cetak kuitansi</a>
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt className="text-slate-500">Jenis</dt><dd className="font-medium">{transaction.transaction_type === 'income' ? 'Pemasukan' : 'Pengeluaran'}</dd></div>
                    <div><dt className="text-slate-500">Status</dt><dd><StatusBadge status={transaction.status} label={transaction.status === 'posted' ? 'Tercatat' : 'Dibatalkan'} /></dd></div>
                    <div><dt className="text-slate-500">Kategori</dt><dd className="font-medium">{transaction.category?.name}</dd></div>
                    <div><dt className="text-slate-500">Jumlah</dt><dd className="font-semibold">{formatCurrency(Number(transaction.amount))}</dd></div>
                    <div><dt className="text-slate-500">Tanggal</dt><dd>{formatDateShort(transaction.transaction_date)}</dd></div>
                    <div><dt className="text-slate-500">Waktu dicatat</dt><dd>{formatDateTime(transaction.created_at)}</dd></div>
                    <div><dt className="text-slate-500">Nama akun</dt><dd>{transaction.creator?.name ?? '—'}</dd></div>
                    <div><dt className="text-slate-500">{transaction.transaction_type === 'income' ? 'Pembayar' : 'Penerima'}</dt><dd>{transaction.payer_name || '—'}</dd></div>
                    <div className="sm:col-span-2"><dt className="text-slate-500">Keterangan</dt><dd>{transaction.description || '—'}</dd></div>
                </dl>
                {transaction.status === 'reversed' && <p className="rounded bg-amber-50 p-3 text-sm">Dibatalkan {transaction.reversed_at ? formatDateTime(transaction.reversed_at) : ''}: {transaction.reversal_reason}</p>}
                {auth.user?.role === 'ketua' && transaction.status === 'posted' && (
                    <form onSubmit={reverse} className="border-t pt-4">
                        <label className="block text-sm font-medium">Alasan pembatalan
                            <input required minLength={5} value={form.data.reversal_reason} onChange={(event) => form.setData('reversal_reason', event.target.value)} className="mt-1 block w-full rounded-md border px-3 py-2" />
                        </label>
                        {form.errors.reversal_reason && <p className="text-sm text-red-700">{form.errors.reversal_reason}</p>}
                        <button disabled={form.processing} className="mt-3 rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700">Batalkan transaksi</button>
                    </form>
                )}
            </section>
        </AppLayout>
    );
}