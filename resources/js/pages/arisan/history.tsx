import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { formatDateShort, formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';
import type { ArisanCycle, ArisanRound } from '@/types/finance';

type CycleDetail = ArisanCycle & {
    participants: Array<{ id: number; member: { id: number; full_name: string }; has_won: boolean; won_at: string | null }>;
    rounds: Array<ArisanRound & { payments: NonNullable<ArisanRound['payments']>; payouts: NonNullable<ArisanRound['payouts']> }>;
};

export default function ArisanHistoryPage() {
    const { cycle, auth } = usePage<PageProps<{ cycle: CycleDetail }>>().props;
    const [paymentRound, setPaymentRound] = useState<number | null>(null);
    const payment = useForm({ participant_id: '', payment_date: new Date().toISOString().slice(0, 10) });
    const payout = useForm({ payout_date: new Date().toISOString().slice(0, 10) });
    const ketua = auth.user?.role === 'ketua';
    function record(event: FormEvent<HTMLFormElement>, roundId: number) {
        event.preventDefault();
        payment.post(`/arisan/rounds/${roundId}/payments`, { onSuccess: () => { setPaymentRound(null); payment.reset('participant_id'); } });
    }
    const eligibleCount = cycle.participants.filter((participant) => !participant.has_won).length;
    const drawnRounds = cycle.rounds.filter((round) => round.winner?.member);

    return (
        <AppLayout>
            <Head title={cycle.name} />
            <PageHeader title={cycle.name} description={`${cycle.cycle_number} · ${formatDateShort(cycle.started_at)} · Dicatat ${formatDateTime(cycle.created_at)} · ${formatCurrency(Number(cycle.contribution_amount))} per putaran`} action={<Link href="/arisan" className="rounded-md border bg-white px-3 py-2 text-sm">Daftar siklus</Link>} />
            <section aria-label="Ringkasan undian arisan" className="mb-5 grid gap-3 sm:grid-cols-3">
                <article className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <p className="text-sm text-slate-500">Peserta program</p>
                    <p className="mt-1 text-2xl font-semibold text-slate-900">{cycle.participants.length.toLocaleString('id-ID')}</p>
                    <p className="mt-1 text-xs text-slate-500">Peserta tetap dalam siklus ini</p>
                </article>
                <article className="rounded-2xl border border-emerald-200/80 bg-emerald-50/60 p-4 shadow-sm">
                    <p className="text-sm text-emerald-800">Belum pernah menang</p>
                    <p className="mt-1 text-2xl font-semibold text-emerald-900">{eligibleCount.toLocaleString('id-ID')}</p>
                    <p className="mt-1 text-xs text-emerald-800">Hanya mereka yang dapat terpilih berikutnya</p>
                </article>
                <article className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <p className="text-sm text-slate-500">Putaran selesai diundi</p>
                    <p className="mt-1 text-2xl font-semibold text-slate-900">{drawnRounds.length.toLocaleString('id-ID')}</p>
                    <p className="mt-1 text-xs text-slate-500">Dari {cycle.rounds.length.toLocaleString('id-ID')} putaran program</p>
                </article>
            </section>
            <section className="mb-5 overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5">
                <div className="mb-3">
                    <h2 className="font-semibold text-slate-900">Peserta undian ({cycle.participants.length})</h2>
                    <p className="mt-1 text-sm text-slate-500">Pemenang tidak dapat terpilih lagi pada siklus ini, tetapi boleh ikut program arisan yang berbeda.</p>
                </div>
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    {cycle.participants.map((participant) => (
                        <div key={participant.id} className={`flex min-w-0 items-center justify-between gap-3 rounded-xl border px-3 py-2.5 ${participant.has_won ? 'border-emerald-100 bg-emerald-50/60' : 'border-slate-100 bg-slate-50/70'}`}>
                            <span className="truncate text-sm font-medium text-slate-800">{participant.member?.full_name ?? 'Anggota'}</span>
                            <span className={`shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold ${participant.has_won ? 'bg-emerald-100 text-emerald-800' : 'bg-white text-slate-600'}`}>
                                {participant.has_won ? 'Sudah menang' : 'Belum menang'}
                            </span>
                        </div>
                    ))}
                </div>
            </section>
            <div className="space-y-4">{cycle.rounds.map((round) => {
                const payments = round.payments ?? [];
                const payouts = round.payouts ?? [];
                const paid = new Set(payments.map((entry) => entry.participant_id));
                const payoutEntry = payouts[0];
                const paidCount = payments.filter((entry) => entry.status === 'posted').length;
                const drawReady = round.status === 'open' && paidCount === cycle.participants.length && eligibleCount > 0;
                return <section key={round.id} className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 bg-slate-50/60 px-4 py-4 sm:px-5">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div><h2 className="font-semibold text-slate-900">Putaran {round.round_number}</h2><p className="mt-1 text-sm text-slate-500">{formatDateShort(round.round_date)} · Iuran terkumpul {formatCurrency(Number(round.total_collected))}</p><p className="mt-1 text-xs text-slate-500">Putaran dibuat {formatDateTime(round.created_at)}</p></div>
                        <StatusBadge status={round.status} label={round.status === 'open' ? 'Terbuka' : round.status === 'drawn' ? 'Diundi' : round.status === 'completed' ? 'Selesai' : 'Dibatalkan'} />
                    </div>
                    </div>
                    {round.winner?.member && <div className="border-b border-emerald-100 bg-emerald-50/60 px-4 py-4 sm:px-5">
                        <p className="text-xs font-semibold uppercase tracking-wide text-emerald-800">Hasil undian putaran {round.round_number}</p>
                        <p className="mt-1 text-lg font-semibold text-slate-900">{round.winner.member.full_name}</p>
                        <p className="mt-1 text-sm text-slate-600">
                            Menang {round.drawn_at ? formatDateTime(round.drawn_at) : '—'}
                            {round.drawn_by_name ? ` · Diundi oleh ${round.drawn_by_name}` : ''}
                            {payoutEntry ? ` · Hadiah ${formatCurrency(Number(payoutEntry.amount))}` : ''}
                        </p>
                        {payoutEntry && <p className="mt-1 text-xs font-medium text-emerald-800">
                            {payoutEntry.status === 'paid' ? `Penyaluran ${payoutEntry.payout_number} tercatat ${formatDateShort(payoutEntry.payout_date)}` : `Menunggu penyaluran · ${payoutEntry.payout_number}`}
                        </p>}
                    </div>}
                    <div className="px-4 py-4 sm:px-5">
                    {round.status === 'open' && <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
                        <div>
                            <p className="font-medium text-slate-800">Undian putaran {round.round_number}</p>
                            <p className="mt-1 text-sm text-slate-500">
                                {paidCount} dari {cycle.participants.length} peserta sudah membayar · {eligibleCount} peserta memenuhi syarat menang
                            </p>
                            <p className="mt-1 text-xs text-slate-500">Hadiah saat ini {formatCurrency(Number(round.total_collected))}</p>
                        </div>
                        {ketua && <button
                            type="button"
                            disabled={!drawReady}
                            onClick={() => router.post(`/arisan/rounds/${round.id}/draw`)}
                            className="w-full rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:bg-slate-300 sm:w-auto"
                        >
                            Mulai undian
                        </button>}
                        {!ketua && <span className="text-xs text-slate-500">Undian dilakukan oleh Ketua setelah semua iuran tercatat.</span>}
                    </div>}
                    <div className="overflow-x-auto rounded-xl border border-slate-200">
                        <table className="w-full min-w-[650px] text-left text-sm"><thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-3 py-3 font-semibold">Peserta</th><th className="px-3 py-3 font-semibold">Pembayaran</th><th className="px-3 py-3 font-semibold">Waktu dicatat</th><th className="px-3 py-3 font-semibold">Kuitansi</th></tr></thead><tbody className="divide-y divide-slate-100">{cycle.participants.map((participant) => { const paymentEntry = payments.find((entry) => entry.participant_id === participant.id); return <tr key={participant.id}><td className="px-3 py-3 font-medium text-slate-800">{participant.member?.full_name ?? 'Anggota'}{participant.has_won && <span className="mt-0.5 block text-xs font-normal text-emerald-700">Sudah menang pada siklus ini</span>}</td><td className="px-3 py-3">{paid.has(participant.id) ? <span className="font-medium text-emerald-700">Lunas</span> : <span className="font-medium text-amber-700">Belum bayar</span>}</td><td className="px-3 py-3 text-slate-500">{paymentEntry ? formatDateTime(paymentEntry.created_at) : '—'}</td><td className="px-3 py-3">{paymentEntry ? <a className="record-link" href={`/receipts/arisan-payment/${paymentEntry.id}`} target="_blank" rel="noreferrer">Lihat</a> : '—'}</td></tr>; })}</tbody></table>
                    </div>
                    {round.status === 'open' && <div className="mt-3 flex flex-wrap gap-2">
                        <button onClick={() => setPaymentRound(round.id)} className="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Catat iuran</button>
                    </div>}
                    {paymentRound === round.id && <form onSubmit={(event) => record(event, round.id)} className="mt-3 grid gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3 sm:grid-cols-[1fr_12rem_auto]">
                        <select required value={payment.data.participant_id} onChange={(event) => payment.setData('participant_id', event.target.value)} className="rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><option value="">Pilih peserta belum bayar</option>{cycle.participants.filter((participant) => !paid.has(participant.id)).map((participant) => <option key={participant.id} value={participant.id}>{participant.member?.full_name ?? 'Anggota'}</option>)}</select>
                        <input type="date" required value={payment.data.payment_date} onChange={(event) => payment.setData('payment_date', event.target.value)} className="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                        <button disabled={payment.processing} className="rounded-xl bg-emerald-700 px-3.5 py-2.5 text-sm font-semibold text-white disabled:opacity-60">Simpan iuran</button>
                    </form>}
                    {ketua && payoutEntry?.status === 'pending' && <form onSubmit={(event) => { event.preventDefault(); payout.post(`/arisan/payouts/${payoutEntry.id}/pay`); }} className="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-amber-200 bg-amber-50/50 p-3">
                        <p className="mr-auto text-sm">Penyaluran {formatCurrency(Number(payoutEntry.amount))} kepada {payoutEntry.winner?.member?.full_name ?? 'pemenang'}</p>
                        <label className="text-sm font-medium text-slate-700">Tanggal penyaluran<input type="date" required value={payout.data.payout_date} onChange={(event) => payout.setData('payout_date', event.target.value)} className="mt-1 block rounded-xl border border-slate-300 px-3 py-2.5" /></label>
                        <button disabled={payout.processing} className="rounded-xl bg-emerald-700 px-3.5 py-2.5 text-sm font-semibold text-white disabled:opacity-60">Catat penyaluran</button>
                    </form>}
                    {payoutEntry?.status === 'paid' && <div className="mt-3 flex flex-wrap items-center gap-3 text-sm text-slate-600"><span>Penyaluran telah dicatat pada {formatDateTime(payoutEntry.updated_at)}.</span><a className="record-link" href={`/receipts/arisan-payout/${payoutEntry.id}`} target="_blank" rel="noreferrer">Lihat kuitansi penyaluran</a></div>}
                    </div>
                </section>;
            })}</div>
        </AppLayout>
    );
}