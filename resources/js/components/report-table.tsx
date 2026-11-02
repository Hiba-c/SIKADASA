import { Head, Link, router, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { formatCurrency } from '@/lib/currency';
import { formatDateShort, formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';

type Report = {
    title: string;
    summary: Array<{ label: string; amount: string }>;
    columns: string[];
    rows: Array<Array<string | number>>;
    consolidated?: {
        period_label: string;
        fiscal_year: string;
        summary: Array<{ label: string; amount: string; note: string }>;
        savings_rows: Array<{
            member: string;
            unit: string;
            deposits: string;
            withdrawals: string;
            balance: string;
        }>;
    };
};

type ReportPageProps = PageProps<{
    report: Report;
    filters: { from: string; to: string };
    organization: {
        name: string;
        address: string | null;
        chairman_name: string | null;
        treasurer_name: string | null;
        footer: string | null;
        logo: string | null;
    };
}>;

const moneySummaryLabels = new Set([
    'Pemasukan', 'Pengeluaran', 'Selisih', 'Setoran', 'Penarikan', 'Perubahan saldo',
    'Saldo terkini', 'Pinjaman dicairkan', 'Sisa kewajiban pinjaman aktif',
    'Iuran terkumpul', 'Penyaluran', 'Total debit', 'Total kredit', 'Selisih jurnal',
]);

function formatReportPeriod(from: string, to: string): string {
    const format = (value: string, options: Intl.DateTimeFormatOptions) => new Intl.DateTimeFormat('id-ID', options).format(new Date(`${value}T00:00:00`));
    const fromMonth = format(from, { month: 'long', year: 'numeric' });
    const toMonth = format(to, { month: 'long', year: 'numeric' });

    return fromMonth === toMonth ? fromMonth : `${format(from, { day: 'numeric', month: 'short', year: 'numeric' })} – ${format(to, { day: 'numeric', month: 'short', year: 'numeric' })}`;
}

function OrganizationHeader({ organization }: { organization: ReportPageProps['organization'] }) {
    return (
        <header className="grid grid-cols-[4rem_minmax(0,1fr)_4rem] items-center gap-2 border-b-2 border-slate-800 px-1 pb-4 sm:grid-cols-[6rem_minmax(0,1fr)_6rem] sm:gap-3 sm:px-3">
            <div className="flex items-center justify-center">
                {organization.logo && <img src={organization.logo} alt="Logo kelompok" className="h-14 w-14 object-contain sm:h-20 sm:w-20" />}
            </div>
            <div className="mx-auto w-full max-w-4xl text-center">
                <p className="text-xs font-bold tracking-wide text-slate-800 sm:text-sm">PEMBERDAYAAN KESEJAHTERAAN KELUARGA (PKK)</p>
                <h2 className="mt-1 text-base font-bold text-slate-900 sm:text-lg">{organization.name}</h2>
                {organization.address && <p className="mt-1 text-xs leading-relaxed text-slate-600">{organization.address}</p>}
                <p className="mt-1 text-[10px] text-slate-500 sm:text-[11px]">Sistem Informasi Keuangan Dasawisma (SIKADASA) Berbasis Web</p>
            </div>
            <div aria-hidden="true" />
        </header>
    );
}

function ReportSignatures({ organization, date }: { organization: ReportPageProps['organization']; date: string }) {
    return (
        <div className="mt-8 grid grid-cols-2 gap-6 text-center text-xs text-slate-600">
            <div><p>Mengetahui &amp; Menyetujui,</p><p className="mt-1 font-semibold text-slate-900">Ketua {organization.name}</p><p className="mt-14 font-semibold text-slate-900 underline">{organization.chairman_name || '________________________'}</p><p className="mt-1">Pengurus kelompok</p></div>
            <div><p>Tanggal laporan: {formatDateShort(date)}</p><p className="mt-1 font-semibold text-slate-900">Bendahara</p><p className="mt-14 font-semibold text-slate-900 underline">{organization.treasurer_name || '________________________'}</p><p className="mt-1">Pengelola keuangan {organization.name}</p></div>
        </div>
    );
}

export function ReportTable() {
    const { report, filters, organization } = usePage<ReportPageProps>().props;

    function filter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const formData = new FormData(event.currentTarget);
        const from = String(formData.get('from') ?? '');
        const to = String(formData.get('to') ?? '');
        router.get(window.location.pathname, { from, to }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout>
            <Head title={report.title} />
            <PageHeader title={report.title} description="Ringkasan transaksi untuk rentang tanggal yang dipilih." action={<Link href="/reports" className="rounded-md border bg-white px-3 py-2 text-sm">Semua laporan</Link>} />
            <form onSubmit={filter} className="mb-5 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4">
                <label className="text-sm font-medium">Dari tanggal<input type="date" name="from" required defaultValue={filters.from} className="mt-1 block rounded-md border px-3 py-2" /></label>
                <label className="text-sm font-medium">Sampai tanggal<input type="date" name="to" required defaultValue={filters.to} className="mt-1 block rounded-md border px-3 py-2" /></label>
                <button className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white">Tampilkan</button>
                <a href={`${window.location.pathname}/export?from=${encodeURIComponent(filters.from)}&to=${encodeURIComponent(filters.to)}`} className="rounded-md border px-4 py-2 text-sm font-medium">Unduh PDF</a>
            </form>
            {report.consolidated ? (
                <section className="mx-auto max-w-5xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                    <OrganizationHeader organization={organization} />
                    <div className="py-5 text-center">
                        <h3 className="text-sm font-bold text-slate-900">LAPORAN PERTANGGUNGJAWABAN KEUANGAN KONSOLIDASI</h3>
                        <p className="mt-1 text-xs text-slate-500">Periode: {report.consolidated.period_label} · Tahun Buku {report.consolidated.fiscal_year}</p>
                    </div>
                    <h4 className="mb-2 text-xs font-bold uppercase text-slate-800">I. Ringkasan Posisi Keuangan Kelompok</h4>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[690px] border-collapse text-xs">
                            <thead className="bg-slate-50"><tr><th className="border border-slate-300 px-2 py-2 text-left">No</th><th className="border border-slate-300 px-2 py-2 text-left">Posisi Rekening / Komponen</th><th className="border border-slate-300 px-2 py-2 text-right">Saldo Saat Ini</th><th className="border border-slate-300 px-2 py-2 text-left">Keterangan</th></tr></thead>
                            <tbody>{report.consolidated.summary.map((item, index) => <tr key={item.label}>
                                <td className="border border-slate-300 px-2 py-2 text-blue-800">{index + 1}</td>
                                <td className="border border-slate-300 px-2 py-2 font-semibold">{item.label}</td>
                                <td className={`whitespace-nowrap border border-slate-300 px-2 py-2 text-right font-mono font-semibold ${index === 2 ? 'text-orange-700' : index === 3 ? 'text-blue-800' : 'text-emerald-800'}`}>{formatCurrency(Number(item.amount))}</td>
                                <td className="border border-slate-300 px-2 py-2 text-slate-600">{item.note}</td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                    <h4 className="mb-2 mt-6 text-xs font-bold uppercase text-slate-800">II. Rincian Saldo Tabungan Sukarela Anggota</h4>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[690px] border-collapse text-xs">
                            <thead className="bg-slate-50"><tr><th className="border border-slate-300 px-2 py-2 text-left">No</th><th className="border border-slate-300 px-2 py-2 text-left">Nama Anggota</th><th className="border border-slate-300 px-2 py-2 text-left">Unit Rumah</th><th className="border border-slate-300 px-2 py-2 text-right">Total Setoran (+)</th><th className="border border-slate-300 px-2 py-2 text-right">Total Penarikan (−)</th><th className="border border-slate-300 px-2 py-2 text-right">Saldo Tabungan Sukarela</th></tr></thead>
                            <tbody>{report.consolidated.savings_rows.map((row, index) => <tr key={`${row.member}-${index}`}>
                                <td className="border border-slate-300 px-2 py-2 text-blue-800">{index + 1}</td>
                                <td className="border border-slate-300 px-2 py-2 font-medium">{row.member}</td>
                                <td className="border border-slate-300 px-2 py-2 text-slate-600">{row.unit}</td>
                                <td className="whitespace-nowrap border border-slate-300 px-2 py-2 text-right font-mono text-emerald-700">+{formatCurrency(Number(row.deposits))}</td>
                                <td className="whitespace-nowrap border border-slate-300 px-2 py-2 text-right font-mono text-rose-700">-{formatCurrency(Number(row.withdrawals))}</td>
                                <td className="whitespace-nowrap border border-slate-300 px-2 py-2 text-right font-mono font-semibold text-emerald-800">{formatCurrency(Number(row.balance))}</td>
                            </tr>)}</tbody>
                        </table>
                        {report.consolidated.savings_rows.length === 0 && <p className="p-4 text-center text-sm text-slate-500">Belum ada data rekening tabungan anggota.</p>}
                    </div>
                    <ReportSignatures organization={organization} date={filters.to} />
                    <footer className="mt-6 border-t border-slate-200 pt-3 text-center text-[10px] leading-relaxed text-slate-400">{organization.footer || 'Dokumen laporan sah diterbitkan melalui Sistem Informasi Keuangan Dasawisma (SIKADASA).'} {organization.name}{organization.address ? ` · ${organization.address}` : ''}</footer>
                </section>
            ) : (
                <section className="mx-auto max-w-6xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                    <OrganizationHeader organization={organization} />
                    <div className="py-5 text-center">
                        <h3 className="text-sm font-bold uppercase text-slate-900">{report.title}</h3>
                        <p className="mt-1 text-xs text-slate-500">Periode: {formatReportPeriod(filters.from, filters.to)}</p>
                    </div>
                    <h4 className="mb-2 text-xs font-bold uppercase text-slate-800">I. Ringkasan {report.title}</h4>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[560px] border-collapse text-xs">
                            <thead className="bg-slate-50"><tr><th className="border border-slate-300 px-2 py-2 text-left">No</th><th className="border border-slate-300 px-2 py-2 text-left">Komponen</th><th className="border border-slate-300 px-2 py-2 text-right">Nilai</th></tr></thead>
                            <tbody>{report.summary.map((item, index) => <tr key={item.label}>
                                <td className="border border-slate-300 px-2 py-2 text-blue-800">{index + 1}</td>
                                <td className="border border-slate-300 px-2 py-2 font-semibold">{item.label}</td>
                                <td className={`whitespace-nowrap border border-slate-300 px-2 py-2 text-right font-mono font-semibold ${moneySummaryLabels.has(item.label) ? 'text-emerald-800' : 'text-slate-800'}`}>{moneySummaryLabels.has(item.label) ? formatCurrency(Number(item.amount)) : item.amount}</td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                    <h4 className="mb-2 mt-6 text-xs font-bold uppercase text-slate-800">II. Rincian {report.title}</h4>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[700px] border-collapse text-xs">
                            <thead className="bg-slate-50 text-slate-700"><tr>{report.columns.map((column) => <th key={column} className="border border-slate-300 px-2 py-2 text-left">{column}</th>)}</tr></thead>
                            <tbody className="divide-y divide-slate-100">{report.rows.map((row, rowIndex) => <tr key={rowIndex}>{row.map((cell, cellIndex) => {
                                const value = typeof cell === 'string' && /^\d+\.\d{2}$/.test(cell)
                                    ? formatCurrency(Number(cell))
                                    : typeof cell === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(cell)
                                        ? formatDateTime(cell)
                                        : typeof cell === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(cell)
                                            ? formatDateShort(cell)
                                            : cell;

                                return <td key={cellIndex} className={`border border-slate-300 px-2 py-2 ${typeof cell === 'string' && /^\d+\.\d{2}$/.test(cell) ? 'whitespace-nowrap text-right font-mono' : ''}`}>{value}</td>;
                            })}</tr>)}</tbody>
                        </table>
                        {report.rows.length === 0 && <p className="p-4 text-center text-sm text-slate-500">Tidak ada transaksi pada rentang tanggal ini.</p>}
                    </div>
                    <ReportSignatures organization={organization} date={filters.to} />
                    <footer className="mt-6 border-t border-slate-200 pt-3 text-center text-[10px] leading-relaxed text-slate-400">{organization.footer || 'Dokumen laporan sah diterbitkan melalui Sistem Informasi Keuangan Dasawisma (SIKADASA).'} {organization.name}{organization.address ? ` · ${organization.address}` : ''}</footer>
                </section>
            )}
        </AppLayout>
    );
}
