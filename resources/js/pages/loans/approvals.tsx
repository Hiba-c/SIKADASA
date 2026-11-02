import { Head, Link, usePage } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { formatDateShort, formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';
import type { Loan } from '@/types/loan';
import type { LaravelPaginator } from '@/types';

export default function LoanApprovalsPage() {
    const { loans } = usePage<PageProps<{ loans: LaravelPaginator<Loan> }>>().props;
    return (
        <AppLayout>
            <Head title="Persetujuan pinjaman" />
            <PageHeader
                title="Persetujuan Pinjaman"
                description="Tinjau rincian pengajuan sebelum mengambil keputusan."
                action={<Link href="/loans" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto">Semua pinjaman</Link>}
            />
            <section aria-label="Pengajuan menunggu persetujuan" className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-4 sm:px-5">
                    <div>
                        <h2 className="font-semibold text-slate-900">Pengajuan menunggu</h2>
                        <p className="mt-1 text-sm text-slate-500">{loans.total.toLocaleString('id-ID')} pengajuan perlu ditinjau</p>
                    </div>
                    <span className="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Menunggu keputusan</span>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-4 py-3.5 font-semibold">Tanggal pengajuan</th>
                                <th className="px-4 py-3.5 font-semibold">Waktu dicatat</th>
                                <th className="px-4 py-3.5 font-semibold">Anggota</th>
                                <th className="px-4 py-3.5 text-right font-semibold">Pokok pinjaman</th>
                                <th className="px-4 py-3.5 text-right font-semibold">Jasa</th>
                                <th className="px-4 py-3.5 text-center font-semibold">Angsuran</th>
                                <th className="px-4 py-3.5 text-right font-semibold">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">
                            {loans.data.map((loan) => (
                                <tr key={loan.id} className="transition-colors hover:bg-emerald-50/30">
                                    <td className="whitespace-nowrap px-4 py-4">{formatDateShort(loan.application_date)}</td>
                                    <td className="whitespace-nowrap px-4 py-4 text-slate-500">{formatDateTime(loan.created_at)}</td>
                                    <td className="px-4 py-4">
                                        <Link href={`/loans/${loan.id}`} className="font-semibold text-emerald-800 hover:underline">
                                            {loan.member?.full_name}
                                        </Link>
                                        <span className="mt-1 block text-xs text-slate-500">{loan.loan_number}</span>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-4 text-right font-semibold text-slate-800">{formatCurrency(Number(loan.principal_amount))}</td>
                                    <td className="whitespace-nowrap px-4 py-4 text-right">{formatCurrency(Number(loan.service_amount))}</td>
                                    <td className="whitespace-nowrap px-4 py-4 text-center">{loan.installment_count} kali</td>
                                    <td className="whitespace-nowrap px-4 py-4 text-right">
                                        <Link
                                            href={`/loans/${loan.id}`}
                                            className="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                        >
                                            Tanggapi
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {loans.data.length === 0 && (
                        <div className="px-5 py-12 text-center">
                            <p className="font-medium text-slate-700">Tidak ada pengajuan menunggu.</p>
                            <p className="mt-1 text-sm text-slate-500">Pengajuan baru akan muncul di daftar ini.</p>
                        </div>
                    )}
                </div>
            </section>
        </AppLayout>
    );
}