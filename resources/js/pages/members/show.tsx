import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmationDialog } from '@/components/confirmation-dialog';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { formatDateShort, formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';
import type {
    Member,
    MemberArisanHistory,
    MemberDeactivationCheck,
    MemberLoanHistory,
    MemberSavingsTransaction,
} from '@/types/member';

type MemberShowProps = PageProps<{
    errors: Record<string, string>;
    member: Member;
    savings: { balance: string; transactions: MemberSavingsTransaction[] };
    loans: MemberLoanHistory[];
    arisan: MemberArisanHistory[];
    deactivation_check: MemberDeactivationCheck | null;
}>;

function money(value: string | number): string {
    return formatCurrency(Number(value));
}

export default function MemberShowPage() {
    const { member, savings, loans, arisan, deactivation_check: check, auth, errors } = usePage<MemberShowProps>().props;
    const [deactivateOpen, setDeactivateOpen] = useState(false);
    const [activateOpen, setActivateOpen] = useState(false);
    const deactivation = useForm({ inactive_reason: '' });
    const isKetua = auth.user?.role === 'ketua';

    function deactivate(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        deactivation.post(`/members/${member.id}/deactivate`, {
            onSuccess: () => setDeactivateOpen(false),
        });
    }

    function activate() {
        router.post(`/members/${member.id}/activate`, {}, {
            onSuccess: () => setActivateOpen(false),
        });
    }

    return (
        <AppLayout>
            <Head title={member.full_name} />
            <PageHeader
                title={member.full_name}
                description={`${member.registration_code} · Bergabung ${formatDateShort(member.joined_at)} · Dicatat ${formatDateTime(member.created_at)}`}
                action={
                    <div className="flex flex-wrap gap-2">
                        <Link href="/members" className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">
                            Daftar anggota
                        </Link>
                        <Link href={`/members/${member.id}/edit`} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">
                            Edit
                        </Link>
                        {isKetua && member.status === 'active' && (
                            <button onClick={() => setDeactivateOpen(true)} className="rounded-md border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                                Nonaktifkan
                            </button>
                        )}
                        {isKetua && member.status === 'inactive' && (
                            <button onClick={() => setActivateOpen(true)} className="rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800">
                                Aktifkan kembali
                            </button>
                        )}
                    </div>
                }
            />

            {member.status === 'inactive' && (
                <div className="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm">
                    <div className="flex items-center gap-2">
                        <StatusBadge status="inactive" label="Nonaktif" />
                        {member.inactive_at && <span className="text-slate-600">{formatDateTime(member.inactive_at)}</span>}
                    </div>
                    {member.inactive_reason && <p className="mt-2 text-slate-700">Alasan: {member.inactive_reason}</p>}
                </div>
            )}

            <section aria-labelledby="member-information" className="mb-6 rounded-lg border border-slate-200 bg-white p-5">
                <h2 id="member-information" className="mb-4 font-semibold">Informasi anggota</h2>
                <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt className="text-slate-500">Nomor rumah</dt><dd className="mt-1 font-medium">{member.house_number || '—'}</dd></div>
                    <div><dt className="text-slate-500">WhatsApp</dt><dd className="mt-1 font-medium">{member.phone || '—'}</dd></div>
                    <div className="sm:col-span-2"><dt className="text-slate-500">Alamat</dt><dd className="mt-1 font-medium">{member.address}</dd></div>
                    <div className="sm:col-span-2"><dt className="text-slate-500">Catatan anggota</dt><dd className="mt-1 whitespace-pre-wrap font-medium">{member.notes || 'Belum ada catatan.'}</dd></div>
                    <div><dt className="text-slate-500">Status</dt><dd className="mt-1"><StatusBadge status={member.status} label={member.status === 'active' ? 'Aktif' : 'Nonaktif'} /></dd></div>
                </dl>
            </section>

            <section aria-labelledby="financial-history" className="space-y-6">
                <h2 id="financial-history" className="text-xl font-semibold">Riwayat keuangan</h2>
                <div className="grid gap-4 sm:grid-cols-3">
                    <article className="rounded-lg border border-slate-200 bg-white p-4">
                        <p className="text-sm text-slate-500">Saldo tabungan</p>
                        <p className="mt-1 text-xl font-semibold">{money(savings.balance)}</p>
                    </article>
                    <article className="rounded-lg border border-slate-200 bg-white p-4">
                        <p className="text-sm text-slate-500">Riwayat pinjaman</p>
                        <p className="mt-1 text-xl font-semibold">{loans.length}</p>
                    </article>
                    <article className="rounded-lg border border-slate-200 bg-white p-4">
                        <p className="text-sm text-slate-500">Siklus arisan</p>
                        <p className="mt-1 text-xl font-semibold">{arisan.length}</p>
                    </article>
                </div>

                <section className="rounded-lg border border-slate-200 bg-white p-5">
                    <h3 className="mb-3 font-semibold">Riwayat tabungan</h3>
                    {savings.transactions.length === 0 ? (
                        <p className="text-sm text-slate-500">Belum ada transaksi tabungan.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead><tr className="border-b text-slate-500"><th className="py-2 pr-3">Tanggal</th><th className="py-2 pr-3">Waktu dicatat</th><th className="py-2 pr-3">Nomor</th><th className="py-2 pr-3">Jenis</th><th className="py-2 text-right">Nominal</th></tr></thead>
                                <tbody>
                                    {savings.transactions.map((transaction) => (
                                        <tr key={transaction.id} className="border-b last:border-0">
                                            <td className="py-2 pr-3">{formatDateShort(transaction.transaction_date)}</td>
                                            <td className="py-2 pr-3">{formatDateTime(transaction.created_at)}</td>
                                            <td className="py-2 pr-3">{transaction.transaction_number}</td>
                                            <td className="py-2 pr-3">{transaction.type === 'deposit' ? 'Setoran' : 'Penarikan'}</td>
                                            <td className="py-2 text-right">{money(transaction.amount)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-5">
                    <h3 className="mb-3 font-semibold">Riwayat pinjaman dan angsuran</h3>
                    {loans.length === 0 ? <p className="text-sm text-slate-500">Belum ada riwayat pinjaman.</p> : (
                        <div className="space-y-4">
                            {loans.map((loan) => (
                                <article key={loan.id} className="rounded-md border border-slate-200 p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <h4 className="font-medium">{loan.loan_number}</h4>
                                        <StatusBadge status={loan.status} />
                                    </div>
                                    <p className="mt-2 text-sm text-slate-600">Pokok {money(loan.principal_amount)} · Jasa {money(loan.service_amount)} · Total {money(loan.total_obligation)}</p>
                                    {loan.payments.length > 0 && (
                                        <ul className="mt-3 space-y-1 text-sm text-slate-600">
                                            {loan.payments.map((payment) => (
                                                <li key={payment.payment_number}>{payment.payment_number} · {formatDateShort(payment.payment_date)} · {formatDateTime(payment.created_at)} · {money(payment.total_amount)}</li>
                                            ))}
                                        </ul>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-5">
                    <h3 className="mb-3 font-semibold">Riwayat arisan</h3>
                    {arisan.length === 0 ? <p className="text-sm text-slate-500">Belum ada riwayat arisan.</p> : (
                        <div className="space-y-4">
                            {arisan.map((participant) => (
                                <article key={participant.id} className="rounded-md border border-slate-200 p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <h4 className="font-medium">{participant.cycle_name}</h4>
                                        {participant.has_won && <StatusBadge status="completed" label="Pernah menang" />}
                                    </div>
                                    {participant.payments.length === 0 ? <p className="mt-2 text-sm text-slate-500">Belum ada pembayaran iuran.</p> : (
                                        <ul className="mt-2 space-y-1 text-sm text-slate-600">
                                            {participant.payments.map((payment) => (
                                                <li key={payment.transaction_number}>Putaran {payment.round_number} · {formatDateShort(payment.payment_date)} · {formatDateTime(payment.created_at)} · {money(payment.amount)}</li>
                                            ))}
                                        </ul>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </section>

            {isKetua && check && member.status === 'active' && (
                <section className="mt-6 rounded-lg border border-slate-200 bg-white p-5">
                    <h2 className="font-semibold">Pemeriksaan sebelum penonaktifan</h2>
                    <p className="mt-1 text-sm text-slate-600">Kewajiban harus diselesaikan melalui transaksi resmi. Penonaktifan tidak menghapus atau melunasi kewajiban.</p>
                    <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt className="text-slate-500">Saldo tabungan</dt><dd className="mt-1 font-medium">{money(check.savings_balance)}</dd></div>
                        <div><dt className="text-slate-500">Sisa pokok pinjaman</dt><dd className="mt-1 font-medium">{money(check.loan_principal)}</dd></div>
                        <div><dt className="text-slate-500">Sisa jasa pinjaman</dt><dd className="mt-1 font-medium">{money(check.loan_service)}</dd></div>
                        <div><dt className="text-slate-500">Iuran arisan belum dibayar ({check.unpaid_arisan_rounds} putaran)</dt><dd className="mt-1 font-medium">{money(check.arisan_contributions)}</dd></div>
                    </dl>
                    <p className={`mt-4 text-sm font-medium ${check.can_deactivate ? 'text-green-700' : 'text-red-700'}`}>
                        {check.can_deactivate ? 'Tidak ditemukan kewajiban tertunda.' : 'Penonaktifan ditahan sampai seluruh kewajiban di atas diselesaikan.'}
                    </p>
                </section>
            )}

            <ConfirmationDialog
                open={deactivateOpen}
                title="Nonaktifkan anggota?"
                description="Data dan riwayat anggota tetap disimpan. Pastikan pemeriksaan kewajiban sudah bersih."
                confirmLabel={deactivation.processing ? 'Memproses...' : 'Nonaktifkan'}
                onCancel={() => setDeactivateOpen(false)}
                onConfirm={() => {
                    if (check?.can_deactivate) {
                        const form = document.getElementById('deactivation-form');
                        if (form instanceof HTMLFormElement) {
                            form.requestSubmit();
                        }
                    }
                }}
            >
                <form id="deactivation-form" onSubmit={deactivate} className="mt-4">
                    <label htmlFor="inactive_reason" className="block text-sm font-medium">Alasan penonaktifan</label>
                    <textarea
                        id="inactive_reason"
                        value={deactivation.data.inactive_reason}
                        onChange={(event) => deactivation.setData('inactive_reason', event.target.value)}
                        maxLength={1000}
                        required
                        rows={3}
                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    />
                    {deactivation.errors.inactive_reason && <p className="mt-1 text-sm text-red-700">{deactivation.errors.inactive_reason}</p>}
                    {errors.deactivation && <p className="mt-1 text-sm text-red-700">{errors.deactivation}</p>}
                </form>
            </ConfirmationDialog>

            <ConfirmationDialog
                open={activateOpen}
                title="Aktifkan kembali anggota?"
                description="Anggota akan dapat mengikuti transaksi sesuai hak akses yang berlaku."
                confirmLabel="Aktifkan kembali"
                onCancel={() => setActivateOpen(false)}
                onConfirm={activate}
            />
        </AppLayout>
    );
}