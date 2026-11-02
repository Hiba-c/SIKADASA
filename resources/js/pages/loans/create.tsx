import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';

type MemberOption = { id: number; full_name: string; registration_code: string };
export default function LoanCreatePage({ members, service_rate, application_date }: { members: MemberOption[]; service_rate: string; application_date: string }) {
    const form = useForm({ member_id: '', principal_amount: '', installment_count: '5', application_date });
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/loans');
    }
    return (
        <AppLayout>
            <Head title="Ajukan pinjaman" />
            <PageHeader title="Ajukan Pinjaman" description={`Jasa pinjaman ${service_rate}% sesuai pengaturan organisasi; pengajuan menunggu persetujuan Ketua.`} />
            <form onSubmit={submit} className="max-w-2xl space-y-4 rounded-lg border border-slate-200 bg-white p-5">
                <label className="block text-sm font-medium">Anggota
                    <select required value={form.data.member_id} onChange={(event) => form.setData('member_id', event.target.value)} className="mt-1 block w-full rounded-md border px-3 py-2"><option value="">Pilih anggota aktif</option>{members.map((member) => <option key={member.id} value={member.id}>{member.full_name} · {member.registration_code}</option>)}</select>{form.errors.member_id && <span className="text-red-700">{form.errors.member_id}</span>}
                </label>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium">Pokok pinjaman (Rp)<input type="number" min="0.01" step="0.01" required value={form.data.principal_amount} onChange={(event) => form.setData('principal_amount', event.target.value)} className="mt-1 block w-full rounded-md border px-3 py-2" />{form.errors.principal_amount && <span className="text-red-700">{form.errors.principal_amount}</span>}</label>
                    <label className="block text-sm font-medium">Jumlah angsuran<input type="number" min="1" max="60" required value={form.data.installment_count} onChange={(event) => form.setData('installment_count', event.target.value)} className="mt-1 block w-full rounded-md border px-3 py-2" />{form.errors.installment_count && <span className="text-red-700">{form.errors.installment_count}</span>}</label>
                </div>
                <label className="block text-sm font-medium">Tanggal pengajuan<input type="date" required value={form.data.application_date} onChange={(event) => form.setData('application_date', event.target.value)} className="mt-1 block w-full rounded-md border px-3 py-2" /></label>
                <div className="flex gap-2"><button disabled={form.processing || members.length === 0} className="rounded-md bg-emerald-700 px-4 py-2 text-sm text-white disabled:opacity-50">Simpan pengajuan</button><Link href="/loans" className="rounded-md border px-4 py-2 text-sm">Batal</Link></div>
            </form>
        </AppLayout>
    );
}