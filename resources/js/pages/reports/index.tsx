import { Head, Link } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';

const reports = [
    { href: '/reports/financial', title: 'Keuangan', description: 'Posisi keuangan konsolidasi dan rincian saldo tabungan anggota.' },
    { href: '/reports/cash', title: 'Kas', description: 'Pemasukan dan pengeluaran berdasarkan kategori.' },
    { href: '/reports/savings', title: 'Tabungan', description: 'Setoran, penarikan, dan saldo rekening.' },
    { href: '/reports/arisan', title: 'Arisan', description: 'Iuran yang terkumpul dan penyaluran.' },
    { href: '/reports/loans', title: 'Pinjaman', description: 'Pengajuan, pencairan, dan sisa kewajiban.' },
];

export default function ReportsIndexPage() {
    return (
        <AppLayout>
            <Head title="Laporan" />
            <PageHeader title="Laporan" description="Pilih laporan dan tentukan rentang tanggal." />
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {reports.map((report) => <Link key={report.href} href={report.href} className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50/40 hover:shadow-md"><h2 className="font-semibold text-slate-900">{report.title}</h2><p className="mt-1 text-sm text-slate-600">{report.description}</p></Link>)}
            </div>
        </AppLayout>
    );
}