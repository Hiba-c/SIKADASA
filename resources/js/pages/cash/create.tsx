import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import type { CashCategory, TransactionType } from '@/types/finance';
import type { PageProps } from '@/types/auth';

export default function CashCreatePage({ categories, transaction_date, selected_category_id }: { categories: CashCategory[]; transaction_date: string; selected_category_id: number | null }) {
    const { errors, auth } = usePage<PageProps<{ errors: Record<string, string> }>>().props;
    const [type, setType] = useState<TransactionType>('income');
    const [showCategoryForm, setShowCategoryForm] = useState(false);
    const form = useForm({ category_id: selected_category_id ? String(selected_category_id) : '', transaction_type: type, payer_name: '', transaction_date, amount: '', description: '' });
    const categoryForm = useForm({ name: '', type, description: '' });
    const available = useMemo(() => categories.filter((category) => category.type === type), [categories, type]);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/cash');
    }

    function changeType(value: TransactionType) {
        setType(value);
        form.setData((current) => ({ ...current, transaction_type: value, category_id: '' }));
        categoryForm.setData('type', value);
    }

    function addCategory(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        categoryForm.post('/cash/categories', {
            preserveScroll: true,
            onSuccess: () => {
                categoryForm.reset();
                categoryForm.setData('type', type);
                setShowCategoryForm(false);
            },
        });
    }

    return (
        <AppLayout>
            <Head title="Catat transaksi kas" />
            <PageHeader title="Catat Transaksi Kas" description="Transaksi yang disimpan akan langsung dibukukan." />
            <div className="max-w-2xl">
                <form onSubmit={submit} className="space-y-4 rounded-lg border border-slate-200 bg-white p-5">
                    <label className="block text-sm font-medium">Jenis transaksi
                        <select value={type} onChange={(event) => changeType(event.target.value as TransactionType)} className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2"><option value="income">Pemasukan</option><option value="expense">Pengeluaran</option></select>
                    </label>
                    <div>
                        <label className="block text-sm font-medium">Kategori</label>
                        <select required value={form.data.category_id} onChange={(event) => form.setData('category_id', event.target.value)} className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2"><option value="">Pilih kategori</option>{available.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select>
                        {form.errors.category_id && <span className="text-sm text-red-700">{form.errors.category_id}</span>}
                        <button type="button" onClick={() => setShowCategoryForm((visible) => !visible)} className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-emerald-800 hover:underline">
                            <span aria-hidden="true" className="text-lg leading-none">+</span> Tambah kategori
                        </button>
                    </div>
                    <label className="block text-sm font-medium">Nama akun (pencatat)
                        <input readOnly value={auth.user?.name ?? ''} className="mt-1 block w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-slate-600" />
                        <span className="mt-1 block text-xs font-normal text-slate-500">Nama akun yang sedang masuk akan tercatat otomatis.</span>
                    </label>
                    <label className="block text-sm font-medium">{type === 'income' ? 'Nama pembayar' : 'Nama penerima'}
                        <input required minLength={2} maxLength={255} value={form.data.payer_name} onChange={(event) => form.setData('payer_name', event.target.value)} className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2" />
                        {form.errors.payer_name && <span className="text-sm text-red-700">{form.errors.payer_name}</span>}
                    </label>
                    <label className="block text-sm font-medium">Tanggal
                        <input type="date" required value={form.data.transaction_date} onChange={(event) => form.setData('transaction_date', event.target.value)} className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="block text-sm font-medium">Jumlah (Rp)
                        <input type="number" min="0.01" step="0.01" required value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2" />
                        {form.errors.amount && <span className="text-sm text-red-700">{form.errors.amount}</span>}
                    </label>
                    <label className="block text-sm font-medium">Keterangan
                        <textarea required minLength={3} maxLength={2000} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2" rows={3} />
                        {form.errors.description && <span className="text-sm text-red-700">{form.errors.description}</span>}
                    </label>
                    {errors.journal && <p className="text-sm text-red-700">{errors.journal}</p>}
                    <div className="flex gap-2"><button disabled={form.processing} className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">Simpan</button><Link href="/cash" className="rounded-md border px-4 py-2 text-sm">Batal</Link></div>
                </form>
                {showCategoryForm && <form onSubmit={addCategory} className="mt-3 space-y-3 rounded-md border border-slate-200 bg-slate-50 p-3">
                    <p className="text-sm font-medium">Kategori untuk {type === 'income' ? 'pemasukan' : 'pengeluaran'}</p>
                    <label className="block text-sm">Nama kategori
                        <input required maxLength={255} value={categoryForm.data.name} onChange={(event) => categoryForm.setData('name', event.target.value)} className="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2" />
                        {categoryForm.errors.name && <span className="text-sm text-red-700">{categoryForm.errors.name}</span>}
                    </label>
                    <label className="block text-sm">Keterangan (opsional)
                        <input maxLength={1000} value={categoryForm.data.description} onChange={(event) => categoryForm.setData('description', event.target.value)} className="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2" />
                        {categoryForm.errors.description && <span className="text-sm text-red-700">{categoryForm.errors.description}</span>}
                    </label>
                    <div className="flex gap-2">
                        <button disabled={categoryForm.processing} className="rounded-md bg-emerald-700 px-3 py-2 text-sm text-white disabled:opacity-50">Simpan kategori</button>
                        <button type="button" onClick={() => setShowCategoryForm(false)} className="rounded-md border bg-white px-3 py-2 text-sm">Tutup</button>
                    </div>
                </form>}
            </div>
        </AppLayout>
    );
}