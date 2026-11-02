import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { formatDateShort } from '@/lib/date';
import type { PageProps } from '@/types/auth';

type Batch = {
    id: number;
    hijri_year: number;
    payout_date: string;
    status: string;
    total_due: string;
    total_paid: string;
    items_count?: number;
    created_by?: { name: string } | null;
};

type Props = PageProps<{
    upcoming: { hijri_year: number; payout_date: string; eid_date: string };
    existingBatch: Batch | null;
    batches: Batch[];
    outstandingLoanCount: number;
}>;

const statusLabels: Record<string, string> = {
    pending_approval: 'Menunggu persetujuan',
    approved: 'Disetujui',
    partially_paid: 'Dibayar sebagian',
    paid: 'Lunas',
};

export default function SavingsPayoutIndexPage() {
    const { upcoming, existingBatch, batches, outstandingLoanCount, auth } = usePage<Props>().props;
    const form = useForm<{ hijri_year: number; payout_date: string; batch?: string }>({
        hijri_year: upcoming.hijri_year,
        payout_date: upcoming.payout_date,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.post('/savings/payouts');
    }

    return (
        <AppLayout>
            <Head title="Pencairan Tabungan Idulfitri" />
            <PageHeader
                title="Pencairan Tabungan Idulfitri"
                description="Batch tahunan H-10 dengan persetujuan Ketua dan pencatatan oleh Bendahara."
                action={<Link href="/savings" className="rounded-md border bg-white px-3 py-2 text-sm">Kembali ke tabungan</Link>}
            />

            <section className="mb-5 rounded-lg border border-slate-200 bg-white p-5">
                <h2 className="font-semibold">Jadwal berikutnya</h2>
                <p className="mt-2 text-sm text-slate-600">
                    Perkiraan H-10 Umm al-Qura: <strong>{formatDateShort(upcoming.payout_date)}</strong>
                    {' · '}Idulfitri: <strong>{formatDateShort(upcoming.eid_date)}</strong>
                    {' · '}Tahun Hijriah {upcoming.hijri_year}
                </p>
                <p className="mt-2 text-xs text-slate-500">
                    Tanggal perkiraan dapat berbeda dari penetapan resmi Indonesia. Ketua dapat menyesuaikan tanggal maksimal dua hari dari perkiraan sebelum membuat batch.
                </p>
                {outstandingLoanCount > 0 && (
                    <p className="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-900">
                        Peringatan: {outstandingLoanCount} anggota masih memiliki pinjaman aktif. Sesuai keputusan, kondisi ini tidak memblokir pencairan.
                    </p>
                )}

                {existingBatch ? (
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-md bg-slate-50 p-3">
                        <span className="text-sm">Batch tahun {upcoming.hijri_year} sudah tersedia.</span>
                        <Link href={`/savings/payouts/${existingBatch.id}`} className="rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white">Buka batch</Link>
                    </div>
                ) : auth.user?.role === 'ketua' ? (
                    <form onSubmit={submit} className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <input type="hidden" value={form.data.hijri_year} />
                        <label className="block text-sm font-medium">
                            Tanggal pencairan yang dikonfirmasi
                            <input
                                type="date"
                                required
                                value={form.data.payout_date}
                                onChange={(event) => form.setData('payout_date', event.target.value)}
                                className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2"
                            />
                            {form.errors.payout_date && <span className="mt-1 block text-xs text-red-700">{form.errors.payout_date}</span>}
                        </label>
                        {form.errors.hijri_year && <span className="text-sm text-red-700">{form.errors.hijri_year}</span>}
                        {form.errors.batch && <span className="text-sm text-red-700">{form.errors.batch}</span>}
                        <button disabled={form.processing} className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                            {form.processing ? 'Membuat...' : 'Buat pratinjau batch'}
                        </button>
                    </form>
                ) : (
                    <p className="mt-4 text-sm text-slate-600">Ketua akan membuat dan meninjau batch sebelum Bendahara memproses pencairan.</p>
                )}
            </section>

            <section className="overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <h2 className="px-4 py-3 font-semibold">Riwayat batch</h2>
                <table className="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr><th className="px-4 py-3">Tahun Hijriah</th><th className="px-4 py-3">Tanggal</th><th className="px-4 py-3">Anggota</th><th className="px-4 py-3">Total kewajiban</th><th className="px-4 py-3">Dibayar</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Aksi</th></tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {batches.map((batch) => (
                            <tr key={batch.id}>
                                <td className="px-4 py-3">{batch.hijri_year}</td>
                                <td className="px-4 py-3">{formatDateShort(batch.payout_date)}</td>
                                <td className="px-4 py-3">{batch.items_count ?? '—'}</td>
                                <td className="px-4 py-3">{formatCurrency(Number(batch.total_due))}</td>
                                <td className="px-4 py-3">{formatCurrency(Number(batch.total_paid))}</td>
                                <td className="px-4 py-3">{statusLabels[batch.status] ?? batch.status}</td>
                                <td className="px-4 py-3"><Link href={`/savings/payouts/${batch.id}`} className="font-medium text-emerald-800 hover:underline">Detail</Link></td>
                            </tr>
                        ))}
                        {batches.length === 0 && <tr><td colSpan={7} className="px-4 py-6 text-center text-slate-500">Belum ada batch pencairan.</td></tr>}
                    </tbody>
                </table>
            </section>
        </AppLayout>
    );
}
