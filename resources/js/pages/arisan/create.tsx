import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';

type MemberOption = { id: number; full_name: string; registration_code: string };
type FormData = { name: string; contribution_amount: string; started_at: string; member_ids: number[] };

export default function ArisanCreatePage({ members, started_at }: { members: MemberOption[]; started_at: string }) {
    const form = useForm<FormData>({ name: '', contribution_amount: '', started_at, member_ids: [] });
    function toggleMember(id: number, checked: boolean) {
        form.setData('member_ids', checked ? [...form.data.member_ids, id] : form.data.member_ids.filter((selected) => selected !== id));
    }
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/arisan');
    }
    return (
        <AppLayout>
            <Head title="Buat siklus arisan" />
            <PageHeader title="Buat Siklus Arisan" description="Putaran dijadwalkan setiap 35 hari (selapan). Jumlah putaran mengikuti peserta aktif; sekitar 10 kali setahun." />
            <form onSubmit={submit} className="max-w-3xl space-y-4 rounded-lg border border-slate-200 bg-white p-5">
                <label className="block text-sm font-medium">Nama siklus<input required maxLength={150} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className="mt-1 block w-full rounded-md border px-3 py-2" />{form.errors.name && <span className="text-red-700">{form.errors.name}</span>}</label>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium">Iuran per putaran (Rp)<input type="number" min="0.01" step="0.01" required value={form.data.contribution_amount} onChange={(event) => form.setData('contribution_amount', event.target.value)} className="mt-1 block w-full rounded-md border px-3 py-2" />{form.errors.contribution_amount && <span className="text-red-700">{form.errors.contribution_amount}</span>}</label>
                    <label className="block text-sm font-medium">Tanggal putaran pertama<input type="date" required value={form.data.started_at} onChange={(event) => form.setData('started_at', event.target.value)} className="mt-1 block w-full rounded-md border px-3 py-2" /></label>
                </div>
                <fieldset><legend className="mb-2 text-sm font-medium">Peserta aktif</legend><div className="max-h-72 overflow-y-auto rounded-md border border-slate-200 divide-y divide-slate-100">
                    {members.map((member) => <label key={member.id} className="flex items-center gap-3 px-3 py-2 text-sm"><input type="checkbox" checked={form.data.member_ids.includes(member.id)} onChange={(event) => toggleMember(member.id, event.target.checked)} /><span>{member.full_name}<span className="ml-2 text-xs text-slate-500">{member.registration_code}</span></span></label>)}
                    {members.length === 0 && <p className="p-3 text-sm text-slate-500">Tidak ada anggota aktif.</p>}
                </div>{form.errors.member_ids && <p className="text-sm text-red-700">{form.errors.member_ids}</p>}</fieldset>
                <div className="flex gap-2"><button disabled={form.processing || form.data.member_ids.length === 0} className="rounded-md bg-emerald-700 px-4 py-2 text-sm text-white disabled:opacity-50">Simpan siklus</button><Link href="/arisan" className="rounded-md border px-4 py-2 text-sm">Batal</Link></div>
            </form>
        </AppLayout>
    );
}
