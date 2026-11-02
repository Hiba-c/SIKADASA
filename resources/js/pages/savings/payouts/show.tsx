import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { formatDateShort, formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';

type Batch = {
    id: number;
    hijri_year: number;
    payout_date: string;
    status: string;
    total_due: string;
    total_paid: string;
    approved_at: string | null;
    last_processed_at: string | null;
    created_by?: { name: string } | null;
    approved_by?: { name: string } | null;
    last_processed_by?: { name: string } | null;
};

type Item = {
    id: number;
    amount_due: string;
    amount_paid: string;
    has_outstanding_loan: boolean;
    outstanding_loan_amount: string;
    member: { name: string; registration_code: string };
};

type Props = PageProps<{
    batch: Batch;
    items: Item[];
    availableCash: string;
    payoutDateReached: boolean;
    outstandingLoanCount: number;
}>;

const statusLabels: Record<string, string> = {
    pending_approval: 'Menunggu persetujuan Ketua',
    approved: 'Disetujui, menunggu Bendahara',
    partially_paid: 'Dibayar sebagian',
    paid: 'Lunas',
};

export default function SavingsPayoutShowPage() {
    const { batch, items, availableCash, payoutDateReached, outstandingLoanCount, auth } = usePage<Props>().props;
    const processForm = useForm<{ confirmation: string; batch?: string }>({ confirmation: '1' });
    const canApprove = auth.user?.role === 'ketua' && batch.status === 'pending_approval';
    const canProcess = auth.user?.role === 'bendahara' && ['approved', 'partially_paid'].includes(batch.status);

    function approve() {
        if (window.confirm('Setujui batch ini setelah memeriksa daftar anggota dan nominalnya?')) {
            router.post(`/savings/payouts/${batch.id}/approve`);
        }
    }

    function process(event: React.FormEvent) {
        event.preventDefault();
        if (window.confirm('Proses pencairan sekarang? Transaksi tabungan yang tercatat tidak dapat dibatalkan.')) {
            processForm.post(`/savings/payouts/${batch.id}/process`);
        }
    }

    return (
        <AppLayout>
            <Head title={`Batch Pencairan ${batch.hijri_year}`} />
            <PageHeader
                title={`Batch Pencairan ${batch.hijri_year}`}
                description={`Tanggal H-10 yang dikonfirmasi: ${formatDateShort(batch.payout_date)} · Status: ${statusLabels[batch.status] ?? batch.status}`}
                action={<Link href="/savings/payouts" className="rounded-md border bg-white px-3 py-2 text-sm">Daftar batch</Link>}
            />

            {outstandingLoanCount > 0 && (
                <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    Peringatan: {outstandingLoanCount} anggota memiliki pinjaman aktif. Ini hanya peringatan dan tidak menghalangi pencairan.
                </div>
            )}

            <section className="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-lg border bg-white p-4"><p className="text-sm text-slate-500">Total kewajiban batch</p><p className="mt-1 text-lg font-semibold">{formatCurrency(Number(batch.total_due))}</p></div>
                <div className="rounded-lg border bg-white p-4"><p className="text-sm text-slate-500">Sudah dicairkan</p><p className="mt-1 text-lg font-semibold">{formatCurrency(Number(batch.total_paid))}</p></div>
                <div className="rounded-lg border bg-white p-4"><p className="text-sm text-slate-500">Sisa batch</p><p className="mt-1 text-lg font-semibold">{formatCurrency(Math.max(0, Number(batch.total_due) - Number(batch.total_paid)))}</p></div>
                <div className="rounded-lg border bg-white p-4"><p className="text-sm text-slate-500">Kas tersedia sekarang</p><p className="mt-1 text-lg font-semibold">{formatCurrency(Number(availableCash))}</p></div>
            </section>

            {(canApprove || canProcess) && (
                <section className="mb-5 flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white p-4">
                    {canApprove && (
                        <button type="button" onClick={approve} className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white">
                            Setujui batch
                        </button>
                    )}
                    {canProcess && (
                        <>
                            {!payoutDateReached && <span className="text-sm text-slate-600">Pencairan dapat diproses mulai tanggal yang dikonfirmasi.</span>}
                            <form onSubmit={process}>
                                <button disabled={!payoutDateReached || processForm.processing} className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                                    {processForm.processing ? 'Memproses...' : 'Proses pencairan'}
                                </button>
                                {processForm.errors.confirmation && <span className="ml-2 text-sm text-red-700">{processForm.errors.confirmation}</span>}
                                {processForm.errors.batch && <span className="ml-2 text-sm text-red-700">{processForm.errors.batch}</span>}
                            </form>
                        </>
                    )}
                </section>
            )}

            <p className="mb-3 text-sm text-slate-600">
                Batch dibuat {batch.created_by?.name ? `oleh ${batch.created_by.name}` : ''}.
                {batch.approved_at && ` Disetujui ${batch.approved_by?.name ?? 'Ketua'} pada ${formatDateTime(batch.approved_at)}.`}
                {batch.last_processed_at && ` Terakhir diproses ${batch.last_processed_by?.name ?? 'Bendahara'} pada ${formatDateTime(batch.last_processed_at)}.`}
            </p>

            <section className="overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <table className="w-full min-w-[760px] text-left text-sm">
                    <thead>
                        <tr><th className="px-4 py-3">Anggota</th><th className="px-4 py-3">Saldo saat batch dibuat</th><th className="px-4 py-3">Sudah dibayar</th><th className="px-4 py-3">Sisa</th><th className="px-4 py-3">Pinjaman</th></tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {items.map((item) => (
                            <tr key={item.id}>
                                <td className="px-4 py-3 font-medium">{item.member.name}<span className="mt-0.5 block text-xs font-normal text-slate-500">{item.member.registration_code}</span></td>
                                <td className="px-4 py-3">{formatCurrency(Number(item.amount_due))}</td>
                                <td className="px-4 py-3">{formatCurrency(Number(item.amount_paid))}</td>
                                <td className="px-4 py-3">{formatCurrency(Math.max(0, Number(item.amount_due) - Number(item.amount_paid)))}</td>
                                <td className="px-4 py-3">{item.has_outstanding_loan ? `Belum lunas · ${formatCurrency(Number(item.outstanding_loan_amount))}` : 'Tidak ada tunggakan'}</td>
                            </tr>
                        ))}
                        {items.length === 0 && <tr><td colSpan={5} className="px-4 py-6 text-center text-slate-500">Batch tidak memiliki anggota.</td></tr>}
                    </tbody>
                </table>
            </section>
        </AppLayout>
    );
}
