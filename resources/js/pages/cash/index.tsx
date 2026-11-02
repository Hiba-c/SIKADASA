import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { formatDateShort, formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';
import type { CashCategory, CashTransaction, TransactionStatus, TransactionType } from '@/types/finance';
import type { LaravelPaginator } from '@/types';

type Props = PageProps<{
    transactions: LaravelPaginator<CashTransaction>;
    categories: CashCategory[];
    filters: { search: string; type: string; status: string };
    totals: { income: number; expense: number };
}>;

const columns: DataTableColumn<CashTransaction>[] = [
    { key: 'transaction_number', label: 'Nomor', render: (row) => <Link className="record-link" href={`/cash/${row.id}`}>{row.transaction_number}</Link> },
    { key: 'transaction_date', label: 'Tanggal', render: (row) => formatDateShort(row.transaction_date) },
    { key: 'created_at', label: 'Waktu dicatat', render: (row) => formatDateTime(row.created_at) },
    { key: 'creator', label: 'Nama akun', render: (row) => row.creator?.name ?? '—' },
    { key: 'payer_name', label: 'Pembayar / penerima', render: (row) => row.payer_name ?? '—' },
    { key: 'category_id', label: 'Kategori', render: (row) => row.category?.name ?? '—' },
    { key: 'transaction_type', label: 'Jenis', render: (row) => row.transaction_type === 'income' ? 'Pemasukan' : 'Pengeluaran' },
    { key: 'amount', label: 'Jumlah', render: (row) => formatCurrency(Number(row.amount)) },
    { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status as TransactionStatus} label={row.status === 'posted' ? 'Tercatat' : 'Dibatalkan'} /> },
    { key: 'receipt', label: 'Kuitansi', render: (row) => <a className="record-link" href={`/receipts/cash/${row.id}`} target="_blank" rel="noreferrer">Lihat</a> },
];

export default function CashIndexPage() {
    const { transactions, filters, totals } = usePage<Props>().props;
    const [search, setSearch] = useState(filters.search);
    const [type, setType] = useState(filters.type);
    const [status, setStatus] = useState(filters.status);

    function filter(event: React.FormEvent) {
        event.preventDefault();
        router.get('/cash', { search: search || undefined, type: type === 'all' ? undefined : type, status: status === 'all' ? undefined : status }, { preserveState: true });
    }

    return (
        <AppLayout>
            <Head title="Kas" />
            <PageHeader title="Kas Kelompok" description="Pencatatan pemasukan dan pengeluaran." action={<Link href="/cash/create" className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white">Catat transaksi</Link>} />
            <div className="mb-5 grid gap-3 sm:grid-cols-2">
                <article className="rounded-lg border border-slate-200 bg-white p-4"><p className="text-sm text-slate-500">Total pemasukan</p><p className="mt-1 text-xl font-semibold">{formatCurrency(Number(totals.income))}</p></article>
                <article className="rounded-lg border border-slate-200 bg-white p-4"><p className="text-sm text-slate-500">Total pengeluaran</p><p className="mt-1 text-xl font-semibold">{formatCurrency(Number(totals.expense))}</p></article>
            </div>
            <form onSubmit={filter} className="mb-4 grid gap-2 sm:grid-cols-[1fr_10rem_10rem_auto]">
                <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari nomor, kategori, deskripsi" className="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <select value={type} onChange={(event) => setType(event.target.value as TransactionType | 'all')} className="rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="all">Semua jenis</option><option value="income">Pemasukan</option><option value="expense">Pengeluaran</option></select>
                <select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="all">Semua status</option><option value="posted">Tercatat</option><option value="reversed">Dibatalkan</option></select>
                <button className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm">Terapkan</button>
            </form>
            <DataTable columns={columns} rows={transactions.data} getRowKey={(row) => row.id} emptyMessage="Belum ada transaksi kas." />
            {transactions.last_page > 1 && <div className="mt-4 flex justify-end gap-2">{transactions.links.map((link, index) => link.url ? <Link key={index} href={link.url} className="rounded border bg-white px-3 py-1 text-sm">{link.label.replace(/&laquo;|&raquo;/g, '')}</Link> : null)}</div>}
        </AppLayout>
    );
}