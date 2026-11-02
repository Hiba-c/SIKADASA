import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';

type BackupItem = {
    name: string;
    size: number;
    created_at: string;
    type: string;
};

type BackupPageProps = PageProps<{
    backups: BackupItem[];
}>;

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toLocaleString('id-ID', { maximumFractionDigits: 1 })} KB`;
    return `${(bytes / (1024 * 1024)).toLocaleString('id-ID', { maximumFractionDigits: 1 })} MB`;
}

export default function BackupsPage() {
    const { backups } = usePage<BackupPageProps>().props;
    const form = useForm({ backup: '', confirmation: '' });
    const createForm = useForm({});

    function restore(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/backups/restore');
    }

    function createBackup() {
        createForm.post('/backups');
    }

    return (
        <AppLayout>
            <Head title="Backup" />
            <PageHeader title="Backup dan pemulihan" description="Backup database dan file publik dijadwalkan setiap hari pukul 02.00." />

            <div className="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-950">
                Backup disimpan di server selama 30 hari. Pelaksanaan otomatis memerlukan penjadwal server yang menjalankan <code>php artisan schedule:run</code> setiap menit.
                Salin file ke media lain untuk perlindungan jika server atau disk rusak. Data cadangan memuat informasi keuangan dan hanya dapat diakses Ketua.
            </div>

            <section aria-labelledby="backup-list-heading" className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="backup-list-heading" className="font-semibold text-slate-900">Daftar backup</h2>
                        <p className="mt-1 text-sm text-slate-500">Buat salinan sekarang atau unduh arsip yang sudah tersedia.</p>
                    </div>
                    <button
                        type="button"
                        onClick={createBackup}
                        disabled={createForm.processing}
                        className="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {createForm.processing ? 'Membuat backup...' : 'Buat backup sekarang'}
                    </button>
                </div>
                {backups.length === 0 ? (
                    <p className="mt-4 text-sm text-slate-500">Belum ada file backup. Gunakan tombol di atas atau tunggu backup otomatis harian.</p>
                ) : (
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full min-w-[640px] text-left text-sm">
                            <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 font-medium">File</th>
                                    <th className="px-3 py-2 font-medium">Jenis</th>
                                    <th className="px-3 py-2 font-medium">Dibuat</th>
                                    <th className="px-3 py-2 font-medium">Ukuran</th>
                                    <th className="px-3 py-2 font-medium">Unduh</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {backups.map((backup) => (
                                    <tr key={backup.name} className="transition-colors hover:bg-emerald-50/30">
                                        <td className="break-all px-3 py-3 font-medium text-slate-800">{backup.name}</td>
                                        <td className="px-3 py-3 text-slate-600">{backup.type}</td>
                                        <td className="px-3 py-3 text-slate-600">{formatDateTime(backup.created_at)}</td>
                                        <td className="px-3 py-3 text-slate-600">{formatBytes(backup.size)}</td>
                                        <td className="px-3 py-3">
                                            <a href={`/backups/${encodeURIComponent(backup.name)}/download`} className="font-medium text-emerald-700 hover:underline">
                                                Unduh
                                            </a>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            <section aria-labelledby="restore-heading" className="mt-6 rounded-2xl border border-red-200 bg-white p-5 shadow-sm">
                <h2 id="restore-heading" className="font-semibold text-red-800">Pulihkan backup</h2>
                <p className="mt-2 text-sm text-slate-600">
                    Pemulihan mengganti data database saat ini. Sistem akan membuat backup pengaman terlebih dahulu dan Anda akan diminta masuk kembali.
                </p>
                <form onSubmit={restore} className="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                    <label className="block text-sm font-medium text-slate-700">
                        Pilih file backup
                        <select
                            value={form.data.backup}
                            onChange={(event) => form.setData('backup', event.target.value)}
                            required
                            className="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5"
                        >
                            <option value="">Pilih backup</option>
                            {backups.map((backup) => <option key={backup.name} value={backup.name}>{backup.name}</option>)}
                        </select>
                        {form.errors.backup && <span className="mt-1 block text-sm font-normal text-red-700">{form.errors.backup}</span>}
                    </label>
                    <label className="block text-sm font-medium text-slate-700">
                        Ketik PULIHKAN untuk mengonfirmasi
                        <input
                            value={form.data.confirmation}
                            onChange={(event) => form.setData('confirmation', event.target.value)}
                            required
                            autoComplete="off"
                            className="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5"
                        />
                        {form.errors.confirmation && <span className="mt-1 block text-sm font-normal text-red-700">{form.errors.confirmation}</span>}
                    </label>
                    <button
                        type="submit"
                        disabled={form.processing || backups.length === 0}
                        className="rounded-xl bg-red-700 px-4 py-2.5 font-semibold text-white transition hover:bg-red-800 disabled:opacity-60"
                    >
                        {form.processing ? 'Memulihkan...' : 'Pulihkan'}
                    </button>
                </form>
            </section>
        </AppLayout>
    );
}
