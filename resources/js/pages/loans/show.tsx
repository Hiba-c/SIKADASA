import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { formatDateShort, formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';
import type { Loan, LoanStatus } from '@/types/loan';

type LoanDetail = Loan & { installments: NonNullable<Loan['installments']>; payments: NonNullable<Loan['payments']> };
const labels: Record<LoanStatus, string> = { pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak', active: 'Berjalan', paid_off: 'Lunas', cancelled: 'Dibatalkan' };

function installmentBalanceCents(installment: LoanDetail['installments'][number]): number {
    const principal = Math.max(0, Math.round((Number(installment.principal_due) - Number(installment.principal_paid)) * 100));
    const service = Math.max(0, Math.round((Number(installment.service_due) - Number(installment.service_paid)) * 100));

    return principal + service;
}

function amountInput(cents: number): string {
    return (cents / 100).toFixed(2);
}

export default function LoanShowPage() {
    const page = usePage<PageProps<{ loan: LoanDetail; payment_date: string }>>();
    const { loan, auth, payment_date } = page.props;
    const queryString = page.url.split('?')[1]?.split('#')[0] ?? '';
    const requestedInstallmentId = new URLSearchParams(queryString).get('installment_id');
    const openInstallments = loan.installments.filter((installment) =>
        installment.status !== 'paid' && installmentBalanceCents(installment) > 0,
    );
    const firstOpenInstallment = openInstallments.find((installment) => String(installment.id) === requestedInstallmentId)
        ?? openInstallments[0];
    const totalOutstandingCents = openInstallments.reduce((total, installment) => total + installmentBalanceCents(installment), 0);
    const payment = useForm({
        amount: firstOpenInstallment ? amountInput(installmentBalanceCents(firstOpenInstallment)) : '',
        payment_date,
        payment_scope: 'installment',
        installment_id: firstOpenInstallment ? String(firstOpenInstallment.id) : '',
    });
    const disbursement = useForm({ disbursed_at: payment_date });
    const isKetua = auth.user?.role === 'ketua';

    function selectPaymentTarget(value: string) {
        if (value === 'full') {
            payment.setData({
                ...payment.data,
                payment_scope: 'full',
                installment_id: '',
                amount: amountInput(totalOutstandingCents),
            });

            return;
        }

        const installment = openInstallments.find((item) => String(item.id) === value);
        if (installment) {
            payment.setData({
                ...payment.data,
                payment_scope: 'installment',
                installment_id: value,
                amount: amountInput(installmentBalanceCents(installment)),
            });
        }
    }

    function pay(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        payment.post(`/loans/${loan.id}/payments`);
    }

    return (
        <AppLayout>
            <Head title={loan.loan_number} />
            <PageHeader
                title="Kartu Pinjaman & Angsuran"
                description={`${loan.loan_number} · ${loan.member?.full_name ?? ''} · Pengajuan ${formatDateShort(loan.application_date)}`}
                action={<Link href="/loans" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto">Daftar pinjaman</Link>}
            />
            <section className="mb-5 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-4">
                    <div className="min-w-0">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Ringkasan pinjaman</p>
                        <p className="mt-1 truncate font-semibold text-slate-900">{loan.member?.full_name ?? 'Anggota'}</p>
                        <p className="mt-0.5 text-xs text-slate-500">Nomor {loan.loan_number} · Dicatat {formatDateTime(loan.created_at)}</p>
                    </div>
                    <StatusBadge status={loan.status} label={labels[loan.status]} />
                </div>
                <div className="grid gap-px bg-slate-100 sm:grid-cols-2 xl:grid-cols-4">
                    <div className="bg-white px-5 py-4"><p className="text-xs font-medium text-slate-500">Pokok pinjaman</p><p className="mt-1.5 text-lg font-semibold tracking-tight text-slate-900">{formatCurrency(Number(loan.principal_amount))}</p></div>
                    <div className="bg-white px-5 py-4"><p className="text-xs font-medium text-slate-500">Jasa ({loan.service_rate}%)</p><p className="mt-1.5 text-lg font-semibold tracking-tight text-slate-900">{formatCurrency(Number(loan.service_amount))}</p></div>
                    <div className="bg-white px-5 py-4"><p className="text-xs font-medium text-slate-500">Total kewajiban</p><p className="mt-1.5 text-lg font-semibold tracking-tight text-slate-900">{formatCurrency(Number(loan.total_obligation))}</p></div>
                    <div className="bg-white px-5 py-4"><p className="text-xs font-medium text-slate-500">Jumlah angsuran</p><p className="mt-1.5 text-lg font-semibold tracking-tight text-slate-900">{loan.installment_count} kali</p></div>
                </div>
                {(loan.approved_at || loan.disbursed_at) && <p className="border-t border-slate-100 px-5 py-3 text-sm text-slate-600">{loan.approved_at && <>Disetujui {formatDateTime(loan.approved_at)}</>}{loan.approved_at && loan.disbursed_at && ' · '}{loan.disbursed_at && <>Dicairkan {formatDateTime(loan.disbursed_at)}</>}</p>}
                <div className="border-t border-slate-100 px-5 py-4">
                <div className="flex flex-wrap gap-2">
                    {isKetua && loan.status === 'pending' && <>
                        <button onClick={() => router.post(`/loans/${loan.id}/approve`)} className="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800">Setujui</button>
                        <button onClick={() => router.post(`/loans/${loan.id}/reject`)} className="rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-50">Tolak</button>
                    </>}
                    {loan.status === 'approved' && <form onSubmit={(event) => { event.preventDefault(); disbursement.post(`/loans/${loan.id}/disburse`); }} className="flex flex-wrap items-end gap-2">
                        <label className="text-sm font-medium text-slate-600">Tanggal pencairan<input type="date" required value={disbursement.data.disbursed_at} onChange={(event) => disbursement.setData('disbursed_at', event.target.value)} className="mt-1 block rounded-xl border border-slate-300 px-3 py-2.5" /></label>
                        <button disabled={disbursement.processing} className="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:opacity-60">Cairkan pinjaman</button>
                    </form>}
                </div>
                </div>
            </section>
            {loan.status === 'active' && totalOutstandingCents > 0 && <form id="bayar-angsuran" onSubmit={pay} className="mb-5 scroll-mt-24 rounded-2xl border border-emerald-200/80 bg-white p-4 shadow-sm sm:p-5">
                <div className="mb-4">
                    <h2 className="font-semibold text-slate-900">Bayar Angsuran</h2>
                    <p className="mt-1 text-sm text-slate-500">Pilih angsuran yang dibayar atau lunasi seluruh sisa pinjaman.</p>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(14rem,1.2fr)_minmax(12rem,1fr)_12rem_auto]">
                    <label className="text-sm font-medium text-slate-700">
                        Angsuran
                        <select
                            required
                            value={payment.data.payment_scope === 'full' ? 'full' : payment.data.installment_id}
                            onChange={(event) => selectPaymentTarget(event.target.value)}
                            className="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5"
                        >
                            <option value="full">Lunasi seluruh sisa pinjaman · {formatCurrency(totalOutstandingCents / 100)}</option>
                            {loan.installments.map((installment) => {
                                const balance = installmentBalanceCents(installment);
                                const isPaid = installment.status === 'paid' || balance === 0;
                                return (
                                    <option key={installment.id} value={String(installment.id)} disabled={isPaid}>
                                        Angsuran ke-{installment.installment_number} · {isPaid ? 'Lunas' : `Sisa ${formatCurrency(balance / 100)}`}
                                    </option>
                                );
                            })}
                        </select>
                        {payment.errors.installment_id && <span className="mt-1 block text-sm font-normal text-red-700">{payment.errors.installment_id}</span>}
                    </label>
                    <label className="text-sm font-medium text-slate-700">
                        Nominal diterima
                        <input
                            type="number"
                            min="0.01"
                            step="0.01"
                            required
                            value={payment.data.amount}
                            onChange={(event) => payment.setData('amount', event.target.value)}
                            className="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5"
                        />
                        {payment.errors.amount && <span className="mt-1 block text-sm font-normal text-red-700">{payment.errors.amount}</span>}
                    </label>
                    <label className="text-sm font-medium text-slate-700">
                        Tanggal pembayaran
                        <input
                            type="date"
                            required
                            value={payment.data.payment_date}
                            onChange={(event) => payment.setData('payment_date', event.target.value)}
                            className="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5"
                        />
                    </label>
                    <button disabled={payment.processing} className="self-end rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:opacity-60">
                        {payment.processing ? 'Mencatat pembayaran...' : 'Bayar angsuran'}
                    </button>
                </div>
                {payment.data.payment_scope === 'full' ? (
                    <p className="mt-3 text-xs leading-5 text-slate-500">Nominal awal mencakup seluruh sisa kewajiban. Jika uang diterima melebihi jumlah ini, kelebihannya dicatat otomatis sebagai pemasukan kas.</p>
                ) : (
                    <p className="mt-3 text-xs leading-5 text-slate-500">Nominal awal sebesar sisa angsuran terpilih. Pembayaran lebih diteruskan ke angsuran berikutnya, lalu ke angsuran lain yang masih terbuka sebelum kelebihan setelah lunas masuk kas.</p>
                )}
            </form>}
            {loan.status === 'active' && totalOutstandingCents === 0 && (
                <p role="status" className="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Tidak ada sisa angsuran aktif yang dapat dibayar.</p>
            )}
            <section className="mb-5 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div className="border-b border-slate-100 px-5 py-4">
                    <h2 className="font-semibold text-slate-900">Jadwal angsuran</h2>
                    <p className="mt-1 text-sm text-slate-500">{loan.installments.length} jadwal pembayaran</p>
                </div>
                <div className="overflow-x-auto">
                <table className="w-full min-w-[860px] text-left text-sm"><thead className="bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-4 py-3.5 font-semibold">Ke</th><th className="px-4 py-3.5 font-semibold">Jatuh tempo</th><th className="px-4 py-3.5 font-semibold">Jadwal dibuat</th><th className="px-4 py-3.5 text-right font-semibold">Pokok</th><th className="px-4 py-3.5 text-right font-semibold">Jasa</th><th className="px-4 py-3.5 text-right font-semibold">Terbayar</th><th className="px-4 py-3.5 font-semibold">Status</th></tr></thead>
                    <tbody className="divide-y divide-slate-100 text-slate-700">{loan.installments.map((installment) => <tr key={installment.id} className="transition-colors hover:bg-emerald-50/30"><td className="px-4 py-3.5 font-medium">{installment.installment_number} <span className="text-slate-400">/ {loan.installment_count}</span></td><td className="whitespace-nowrap px-4 py-3.5">{formatDateShort(installment.due_date)}</td><td className="whitespace-nowrap px-4 py-3.5 text-slate-500">{formatDateTime(installment.created_at)}</td><td className="whitespace-nowrap px-4 py-3.5 text-right">{formatCurrency(Number(installment.principal_due))}</td><td className="whitespace-nowrap px-4 py-3.5 text-right">{formatCurrency(Number(installment.service_due))}</td><td className="whitespace-nowrap px-4 py-3.5 text-right">{formatCurrency(Number(installment.principal_paid) + Number(installment.service_paid))}</td><td className="px-4 py-3.5"><span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${installment.status === 'paid' ? 'bg-emerald-50 text-emerald-800' : installment.status === 'partial' ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-700'}`}>{installment.status === 'paid' ? 'Lunas' : installment.status === 'partial' ? 'Sebagian' : 'Belum bayar'}</span></td></tr>)}</tbody>
                </table>
                {loan.installments.length === 0 && <p className="px-5 py-10 text-center text-sm text-slate-500">Jadwal dibuat setelah pinjaman dicairkan.</p>}
                </div>
            </section>
            <section className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div className="border-b border-slate-100 px-5 py-4">
                    <h2 className="font-semibold text-slate-900">Riwayat pembayaran</h2>
                    <p className="mt-1 text-sm text-slate-500">{loan.payments.length} pembayaran tercatat</p>
                </div>
                <div className="overflow-x-auto">
                <table className="w-full min-w-[800px] text-left text-sm"><thead className="bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-4 py-3.5 font-semibold">Nomor</th><th className="px-4 py-3.5 font-semibold">Tanggal</th><th className="px-4 py-3.5 font-semibold">Waktu dicatat</th><th className="px-4 py-3.5 text-right font-semibold">Pokok</th><th className="px-4 py-3.5 text-right font-semibold">Jasa</th><th className="px-4 py-3.5 text-right font-semibold">Total dibayar</th><th className="px-4 py-3.5 font-semibold">Kuitansi</th></tr></thead><tbody className="divide-y divide-slate-100 text-slate-700">{loan.payments.map((entry) => <tr key={entry.id} className="transition-colors hover:bg-emerald-50/30"><td className="whitespace-nowrap px-4 py-3.5 font-medium">{entry.payment_number}</td><td className="whitespace-nowrap px-4 py-3.5">{formatDateShort(entry.payment_date)}</td><td className="whitespace-nowrap px-4 py-3.5 text-slate-500">{formatDateTime(entry.created_at)}</td><td className="whitespace-nowrap px-4 py-3.5 text-right">{formatCurrency(Number(entry.principal_amount))}</td><td className="whitespace-nowrap px-4 py-3.5 text-right">{formatCurrency(Number(entry.service_amount))}</td><td className="whitespace-nowrap px-4 py-3.5 text-right font-semibold text-slate-900">{formatCurrency(Number(entry.total_amount))}</td><td className="whitespace-nowrap px-4 py-3.5"><a className="record-link" href={`/receipts/loan-payment/${entry.id}`} target="_blank" rel="noreferrer">Lihat</a></td></tr>)}</tbody></table>
                {loan.payments.length === 0 && <p className="px-5 py-10 text-center text-sm text-slate-500">Belum ada pembayaran.</p>}
                </div>
            </section>
        </AppLayout>
    );
}