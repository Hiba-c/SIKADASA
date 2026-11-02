import { Head, Link, usePage } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { formatDateShort, formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';
import type { LoanPayment } from '@/types/loan';
import type { LaravelPaginator } from '@/types';

type PaymentRow = LoanPayment & { loan: NonNullable<LoanPayment['loan']> };
export default function LoanPaymentsPage() {
    const { payments } = usePage<PageProps<{ payments: LaravelPaginator<PaymentRow> }>>().props;
    return (
        <AppLayout>
            <Head title="Pembayaran pinjaman" />
            <PageHeader
                title="Riwayat Pembayaran Pinjaman"
                description="Riwayat pembayaran angsuran yang sudah dicatat."
                action={<Link href="/installments" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto">Kartu angsuran</Link>}
            />
            <section aria-label="Daftar riwayat pembayaran" className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div className="border-b border-slate-100 px-4 py-4 sm:px-5">
                    <h2 className="font-semibold text-slate-900">Pembayaran tercatat</h2>
                    <p className="mt-1 text-sm text-slate-500">{payments.total.toLocaleString('id-ID')} transaksi pembayaran</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[920px] text-left text-sm">
                        <thead className="bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-4 py-3.5 font-semibold">Nomor pembayaran</th>
                                <th className="px-4 py-3.5 font-semibold">Tanggal</th>
                                <th className="px-4 py-3.5 font-semibold">Waktu dicatat</th>
                                <th className="px-4 py-3.5 font-semibold">Anggota</th>
                                <th className="px-4 py-3.5 font-semibold">Pinjaman</th>
                                <th className="px-4 py-3.5 text-right font-semibold">Pokok</th>
                                <th className="px-4 py-3.5 text-right font-semibold">Jasa</th>
                                <th className="px-4 py-3.5 text-right font-semibold">Total dibayar</th>
                                <th className="px-4 py-3.5 font-semibold">Kuitansi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">{payments.data.map((payment) => (
                            <tr key={payment.id} className="transition-colors hover:bg-emerald-50/30">
                                <td className="whitespace-nowrap px-4 py-4 font-medium">{payment.payment_number}</td>
                                <td className="whitespace-nowrap px-4 py-4">{formatDateShort(payment.payment_date)}</td>
                                <td className="whitespace-nowrap px-4 py-4 text-slate-500">{formatDateTime(payment.created_at)}</td>
                                <td className="px-4 py-4 font-medium text-slate-800">{payment.loan.member?.full_name}</td>
                                <td className="px-4 py-4"><Link href={`/loans/${payment.loan_id}`} className="record-link">{payment.loan.loan_number}</Link></td>
                                <td className="whitespace-nowrap px-4 py-4 text-right">{formatCurrency(Number(payment.principal_amount))}</td>
                                <td className="whitespace-nowrap px-4 py-4 text-right">{formatCurrency(Number(payment.service_amount))}</td>
                                <td className="whitespace-nowrap px-4 py-4 text-right font-semibold text-slate-900">{formatCurrency(Number(payment.total_amount))}</td>
                                <td className="whitespace-nowrap px-4 py-4"><a className="record-link" href={`/receipts/loan-payment/${payment.id}`} target="_blank" rel="noreferrer">Lihat</a></td>
                            </tr>
                        ))}</tbody>
                    </table>
                    {payments.data.length === 0 && (
                        <div className="px-5 py-12 text-center">
                            <p className="font-medium text-slate-700">Belum ada pembayaran pinjaman.</p>
                            <p className="mt-1 text-sm text-slate-500">Pembayaran yang sudah dicatat akan muncul di sini.</p>
                        </div>
                    )}
                </div>
            </section>
        </AppLayout>
    );
}