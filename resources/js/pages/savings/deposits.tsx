import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import type { SavingsAccount } from '@/types/member';

export default function DepositPage({ account, transaction_date }: { account: SavingsAccount; transaction_date: string }) {
    const form = useForm({ type: 'deposit', amount: '', transaction_date, description: '' });
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/savings/${account.id}/transactions`);
    }
    return (
        <AppLayout>
            <Head title="Setor tabungan" />
            <PageHeader title="Setor Tabungan" description={account.member?.full_name ?? ''} />
            <form onSubmit={submit} className="max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-5">
                <label className="block text-sm font-medium">Jumlah (Rp)<input type="number" min="0.01" step="0.01" required value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} className="mt-1 block w-full rounded-md border px-3 py-2" />{form.errors.amount && <span className="text-red-700">{form.errors.amount}</span>}</label>
                <label className="block text-sm font-medium">Tanggal<input type="date" required value={form.data.transaction_date} onChange={(event) => form.setData('transaction_date', event.target.value)} className="mt-1 block w-full rounded-md border px-3 py-2" /></label>
                <label className="block text-sm font-medium">Keterangan<input value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="mt-1 block w-full rounded-md border px-3 py-2" /></label>
                <div className="flex gap-2"><button disabled={form.processing} className="rounded-md bg-emerald-700 px-4 py-2 text-sm text-white">Simpan setoran</button><Link href={`/savings/${account.id}`} className="rounded-md border px-4 py-2 text-sm">Batal</Link></div>
            </form>
        </AppLayout>
    );
}