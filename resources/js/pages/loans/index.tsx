import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { formatDateShort, formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';
import type { Loan, LoanStatus } from '@/types/loan';
import type { LaravelPaginator } from '@/types';

type Props = PageProps<{ loans: LaravelPaginator<Loan>; status: string }>;
const labels: Record<LoanStatus, string> = { pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak', active: 'Berjalan', paid_off: 'Lunas', cancelled: 'Dibatalkan' };

export default function LoansIndexPage() {
    const { loans, status: initialStatus, auth } = usePage<Props>().props;
    const [status, setStatus] = useState(initialStatus);
    function filter(event: React.FormEvent) {
        event.preventDefault();
        router.get('/loans', { status: status === 'all' ? undefined : status }, { preserveState: true });
    }
    return (
        <AppLayout>
            <Head title="Pinjaman" />
            <PageHeader title="Pinjaman Anggota" description="Pengajuan, persetujuan, pencairan, dan pembayaran." action={<div className="flex gap-2">{auth.user?.role === 'ketua' && <Link href="/loans/approvals" className="rounded-md border bg-white px-3 py-2 text-sm">Persetujuan</Link>}<Link href="/loans/create" className="rounded-md bg-emerald-700 px-4 py-2 text-sm text-white">Buat pengajuan</Link></div>} />
            <form onSubmit={filter} className="mb-4 flex gap-2"><select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-md border px-3 py-2 text-sm"><option value="all">Semua status</option>{Object.entries(labels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select><button className="rounded-md border bg-white px-4 py-2 text-sm">Terapkan</button></form>
            <div className="overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-sm"><table className="w-full text-left text-sm">
                <thead><tr><th className="px-4 py-3">Pinjaman</th><th className="px-4 py-3">Anggota</th><th className="px-4 py-3">Tanggal</th><th className="px-4 py-3">Waktu dicatat</th><th className="px-4 py-3">Pokok</th><th className="px-4 py-3">Total kewajiban</th><th className="px-4 py-3">Status</th></tr></thead>
                <tbody className="divide-y divide-slate-100">{loans.data.map((loan) => <tr key={loan.id}><td className="px-4 py-3"><Link href={`/loans/${loan.id}`} className="record-link">{loan.loan_number}</Link></td><td className="px-4 py-3">{loan.member?.full_name}</td><td className="px-4 py-3">{formatDateShort(loan.application_date)}</td><td className="px-4 py-3">{formatDateTime(loan.created_at)}</td><td className="px-4 py-3">{formatCurrency(Number(loan.principal_amount))}</td><td className="px-4 py-3">{formatCurrency(Number(loan.total_obligation))}</td><td className="px-4 py-3"><StatusBadge status={loan.status} label={labels[loan.status]} /></td></tr>)}</tbody>
            </table>{loans.data.length === 0 && <p className="p-5 text-sm text-slate-500">Belum ada data pinjaman.</p>}</div>
            {loans.last_page > 1 && <div className="mt-4 flex justify-end gap-2">{loans.links.map((link, index) => link.url ? <Link key={index} href={link.url} className="rounded border bg-white px-3 py-1 text-sm">{link.label.replace(/&laquo;|&raquo;/g, '')}</Link> : null)}</div>}
        </AppLayout>
    );
}