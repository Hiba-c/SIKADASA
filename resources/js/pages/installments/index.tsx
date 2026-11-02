import { Head, Link, usePage } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { formatDateShort } from '@/lib/date';
import type { PageProps } from '@/types/auth';
import type { LoanInstallment } from '@/types/loan';
import type { LaravelPaginator } from '@/types';

type InstallmentRow = LoanInstallment & { loan: NonNullable<LoanInstallment['loan']> };
export default function InstallmentsIndexPage() {
    const { installments } = usePage<PageProps<{ installments: LaravelPaginator<InstallmentRow> }>>().props;
    return (
        <AppLayout>
            <Head title="Angsuran" />
            <PageHeader
                title="Kartu Angsuran"
                description="Pantau jadwal dan sisa pembayaran pinjaman anggota."
                action={<Link href="/installments/payments" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto">Riwayat pembayaran</Link>}
            />
            <section aria-label="Daftar angsuran belum lunas" className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div className="border-b border-slate-100 px-4 py-4 sm:px-5">
                    <h2 className="font-semibold text-slate-900">Angsuran belum lunas</h2>
                    <p className="mt-1 text-sm text-slate-500">{installments.total.toLocaleString('id-ID')} jadwal pembayaran aktif</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-4 py-3.5 font-semibold">Anggota</th>
                                <th className="px-4 py-3.5 font-semibold">Nomor pinjaman</th>
                                <th className="px-4 py-3.5 text-center font-semibold">Angsuran</th>
                                <th className="px-4 py-3.5 font-semibold">Jatuh tempo</th>
                                <th className="px-4 py-3.5 text-right font-semibold">Sisa pembayaran</th>
                                <th className="px-4 py-3.5 font-semibold">Status</th>
                                <th className="px-4 py-3.5 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">{installments.data.map((item) => {
                const outstanding = Number(item.principal_due) - Number(item.principal_paid) + Number(item.service_due) - Number(item.service_paid);
                return (
                    <tr key={item.id} className="transition-colors hover:bg-emerald-50/30">
                        <td className="px-4 py-4 font-medium text-slate-800">{item.loan.member?.full_name}</td>
                        <td className="px-4 py-4"><Link href={`/loans/${item.loan_id}`} className="record-link">{item.loan.loan_number}</Link></td>
                        <td className="px-4 py-4 text-center">{item.installment_number} <span className="text-slate-400">/ {item.loan.installment_count}</span></td>
                        <td className="whitespace-nowrap px-4 py-4">{formatDateShort(item.due_date)}</td>
                        <td className="whitespace-nowrap px-4 py-4 text-right font-semibold">{formatCurrency(outstanding)}</td>
                        <td className="px-4 py-4">
                            <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${item.status === 'partial' ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-700'}`}>
                                {item.status === 'partial' ? 'Sebagian' : 'Belum bayar'}
                            </span>
                        </td>
                        <td className="whitespace-nowrap px-4 py-4 text-right">
                            <Link
                                href={`/loans/${item.loan_id}?installment_id=${item.id}#bayar-angsuran`}
                                className="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                            >
                                Bayar
                            </Link>
                        </td>
                    </tr>
                );
            })}</tbody>
                    </table>
                    {installments.data.length === 0 && (
                        <div className="px-5 py-12 text-center">
                            <p className="font-medium text-slate-700">Tidak ada angsuran tertunggak.</p>
                            <p className="mt-1 text-sm text-slate-500">Semua jadwal pembayaran tercatat lunas.</p>
                        </div>
                    )}
                </div>
            </section>
        </AppLayout>
    );
}