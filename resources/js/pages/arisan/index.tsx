import { Head, Link, usePage } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { formatDateShort, formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';
import type { ArisanCycle } from '@/types/finance';
import type { LaravelPaginator } from '@/types';

type Props = PageProps<{ cycles: LaravelPaginator<ArisanCycle & { participants_count: number; rounds_count: number }> }>;

export default function ArisanIndexPage() {
    const { cycles, auth } = usePage<Props>().props;
    return (
        <AppLayout>
            <Head title="Arisan" />
            <PageHeader title="Arisan" description="Siklus, iuran, undian, dan penyaluran." action={auth.user?.role === 'ketua' ? <Link href="/arisan/create" className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white">Buat siklus</Link> : undefined} />
            <div className="overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <table className="w-full text-left text-sm">
                    <thead><tr><th className="px-4 py-3">Siklus</th><th className="px-4 py-3">Mulai</th><th className="px-4 py-3">Waktu dicatat</th><th className="px-4 py-3">Iuran / putaran</th><th className="px-4 py-3">Peserta</th><th className="px-4 py-3">Status</th></tr></thead>
                    <tbody className="divide-y divide-slate-100">{cycles.data.map((cycle) => (
                        <tr key={cycle.id}><td className="px-4 py-3"><Link href={`/arisan/${cycle.id}`} className="record-link flex-col items-start">{cycle.name}<span className="record-link-meta">{cycle.cycle_number}</span></Link></td><td className="px-4 py-3">{formatDateShort(cycle.started_at)}</td><td className="px-4 py-3">{formatDateTime(cycle.created_at)}</td><td className="px-4 py-3">{formatCurrency(Number(cycle.contribution_amount))}</td><td className="px-4 py-3">{cycle.participants_count}</td><td className="px-4 py-3"><StatusBadge status={cycle.status} label={cycle.status === 'active' ? 'Aktif' : cycle.status === 'completed' ? 'Selesai' : 'Dibatalkan'} /></td></tr>
                    ))}</tbody>
                </table>
                {cycles.data.length === 0 && <p className="p-5 text-sm text-slate-500">Belum ada siklus arisan.</p>}
            </div>
            {cycles.last_page > 1 && <div className="mt-4 flex justify-end gap-2">{cycles.links.map((link, index) => link.url ? <Link key={index} href={link.url} className="rounded border bg-white px-3 py-1 text-sm">{link.label.replace(/&laquo;|&raquo;/g, '')}</Link> : null)}</div>}
        </AppLayout>
    );
}