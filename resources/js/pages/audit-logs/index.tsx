import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';
import type { PaginatedData } from '@/types';

type AuditLog = {
    id: number;
    user_name: string;
    action: string;
    module: string;
    entity_type: string | null;
    entity_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string | null;
};

type Props = PageProps<{
    logs: PaginatedData<AuditLog>;
    modules: string[];
    filters: { search: string; module: string; from: string; to: string };
}>;

const moduleLabels: Record<string, string> = {
    auth: 'Autentikasi',
    profile: 'Profil',
    members: 'Anggota',
    cash: 'Kas',
    savings: 'Tabungan',
    arisan: 'Arisan',
    loans: 'Pinjaman',
    users: 'Pengguna',
    reports: 'Laporan',
};

const actionLabels: Record<string, string> = {
    login: 'Masuk',
    logout: 'Keluar',
    created: 'Dibuat',
    category_created: 'Kategori dibuat',
    updated: 'Diperbarui',
    deactivated: 'Dinonaktifkan',
    activated: 'Diaktifkan kembali',
    reversed: 'Dibatalkan',
    deposit: 'Setoran',
    withdrawal: 'Penarikan',
    applied: 'Diajukan',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    disbursed: 'Dicairkan',
    installment_paid: 'Angsuran dibayar',
    cycle_created: 'Siklus dibuat',
    payment_recorded: 'Iuran dicatat',
    round_drawn: 'Undian dilakukan',
    payout_paid: 'Penyaluran dibayar',
    password_changed: 'Kata sandi diubah',
    exported: 'Laporan diunduh',
    validated: 'Konsistensi diperiksa',
};

function Values({ values }: { values: Record<string, unknown> | null }) {
    if (!values || Object.keys(values).length === 0) {
        return <span className="text-slate-400">—</span>;
    }

    return <pre className="max-w-xl overflow-x-auto whitespace-pre-wrap break-words rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-5 text-slate-700">{JSON.stringify(values, null, 2)}</pre>;
}

export default function AuditLogsIndexPage() {
    const { logs, modules, filters } = usePage<Props>().props;
    const [search, setSearch] = useState(filters.search);
    const [module, setModule] = useState(filters.module);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/audit-logs', {
            search: search || undefined,
            module: module === 'all' ? undefined : module,
            from: from || undefined,
            to: to || undefined,
        }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout>
            <Head title="Audit Log" />
            <PageHeader title="Audit Log" description={`${logs.meta.total} catatan aktivitas · hanya dapat dilihat Ketua`} />
            <form onSubmit={submit} className="mb-5 grid gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[minmax(12rem,1fr)_12rem_11rem_11rem_auto]">
                <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari pengguna, aksi, entitas" aria-label="Cari aktivitas" className="min-w-0 rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                <select value={module} onChange={(event) => setModule(event.target.value)} aria-label="Filter modul" className="min-w-0 rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="all">Semua modul</option>
                    {modules.map((item) => <option key={item} value={item}>{moduleLabels[item] ?? item}</option>)}
                </select>
                <input type="date" aria-label="Dari tanggal" value={from} onChange={(event) => setFrom(event.target.value)} className="min-w-0 rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                <input type="date" aria-label="Sampai tanggal" value={to} onChange={(event) => setTo(event.target.value)} className="min-w-0 rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                <button className="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800">Terapkan</button>
            </form>
            <div className="overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <table className="w-full min-w-[900px] text-left text-sm">
                    <thead className="bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500"><tr><th className="whitespace-nowrap px-4 py-3.5 font-semibold">Waktu</th><th className="px-4 py-3.5 font-semibold">Pengguna</th><th className="px-4 py-3.5 font-semibold">Modul / aksi</th><th className="px-4 py-3.5 font-semibold">Entitas</th><th className="px-4 py-3.5 font-semibold">Perubahan</th><th className="px-4 py-3.5 font-semibold">Sumber</th></tr></thead>
                    <tbody className="divide-y divide-slate-100 text-slate-700">{logs.data.map((log) => (
                        <tr key={log.id} className="align-top">
                            <td className="whitespace-nowrap px-4 py-3.5">{log.created_at ? formatDateTime(log.created_at) : '—'}</td>
                            <td className="px-4 py-3.5 font-medium">{log.user_name}</td>
                            <td className="whitespace-nowrap px-4 py-3.5"><span className="block font-medium">{moduleLabels[log.module] ?? log.module}</span><span className="text-xs text-slate-500">{actionLabels[log.action] ?? log.action}</span></td>
                            <td className="px-4 py-3.5">{log.entity_type?.split('\\').pop() ?? '—'}{log.entity_id !== null && <span className="text-slate-500"> #{log.entity_id}</span>}</td>
                            <td className="min-w-64 px-4 py-3.5"><details><summary className="cursor-pointer font-medium text-emerald-800 marker:text-emerald-700">Lihat detail</summary><div className="mt-2 space-y-2"><div><p className="mb-1 text-xs font-medium text-slate-500">Sebelum</p><Values values={log.old_values} /></div><div><p className="mb-1 text-xs font-medium text-slate-500">Sesudah</p><Values values={log.new_values} /></div></div></details></td>
                            <td className="min-w-48 px-4 py-3.5 text-xs text-slate-500">{log.ip_address ?? '—'}{log.user_agent && <span className="mt-1 block max-w-48 truncate" title={log.user_agent}>{log.user_agent}</span>}</td>
                        </tr>
                    ))}</tbody>
                </table>
                {logs.data.length === 0 && <p className="p-5 text-sm text-slate-500">Tidak ada aktivitas pada filter ini.</p>}
            </div>
            {logs.meta.last_page > 1 && <nav aria-label="Pagination audit log" className="mt-4 flex flex-wrap justify-end gap-2">{logs.meta.links.map((link, index) => link.url ? <Link key={index} href={link.url} aria-current={link.active ? 'page' : undefined} className={`rounded-xl border px-3.5 py-2 text-sm ${link.active ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'}`}>{link.label.replace(/&laquo;|&raquo;/g, '')}</Link> : null)}</nav>}
        </AppLayout>
    );
}