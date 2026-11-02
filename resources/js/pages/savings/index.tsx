import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';
import type { LaravelPaginator } from '@/types';
import type { SavingsAccount } from '@/types/member';

type Props = PageProps<{ accounts: LaravelPaginator<SavingsAccount>; search: string }>;

const columns: DataTableColumn<SavingsAccount>[] = [
    {
        key: 'member_id',
        label: 'Anggota',
        render: (account) => (
            <Link href={`/savings/${account.id}`} className="record-link flex-col items-start gap-0.5">
                <span className="font-semibold">{account.member?.full_name}</span>
                <span className="text-xs font-medium text-emerald-900/80">{account.member?.registration_code}</span>
            </Link>
        ),
    },
    { key: 'current_balance', label: 'Saldo', render: (account) => formatCurrency(Number(account.current_balance)) },
    { key: 'created_at', label: 'Rekening dibuat', render: (account) => formatDateTime(account.created_at) },
    { key: 'status', label: 'Status', render: (account) => account.status === 'active' ? 'Aktif' : account.status },
];

export default function SavingsIndexPage() {
    const { accounts, search: initialSearch } = usePage<Props>().props;
    const [search, setSearch] = useState(initialSearch);
    function submit(event: React.FormEvent) {
        event.preventDefault();
        router.get('/savings', { search: search || undefined }, { preserveState: true });
    }

    return (
        <AppLayout>
            <Head title="Tabungan" />
            <PageHeader
                title="Tabungan Anggota"
                description={`${accounts.total} rekening`}
                action={<Link href="/savings/payouts" className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium">Pencairan Idulfitri</Link>}
            />
            <form onSubmit={submit} className="mb-4 flex gap-2">
                <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau kode anggota" className="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <button className="rounded-md border bg-white px-4 py-2 text-sm">Cari</button>
            </form>
            <DataTable columns={columns} rows={accounts.data} getRowKey={(account) => account.id} emptyMessage="Belum ada rekening tabungan." />
            {accounts.last_page > 1 && <div className="mt-4 flex justify-end gap-2">{accounts.links.map((link, index) => link.url ? <Link key={index} href={link.url} className="rounded border bg-white px-3 py-1 text-sm">{link.label.replace(/&laquo;|&raquo;/g, '')}</Link> : null)}</div>}
        </AppLayout>
    );
}