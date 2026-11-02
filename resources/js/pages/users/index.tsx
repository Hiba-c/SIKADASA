import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/date';
import type { PageProps, User, UserRole } from '@/types/auth';
import type { LaravelPaginator } from '@/types';

type Props = PageProps<{
    users: LaravelPaginator<User>;
    filters: { search: string; role: string; status: string };
}>;

const roleLabel: Record<UserRole, string> = { ketua: 'Ketua', bendahara: 'Bendahara' };

export default function UsersIndexPage() {
    const { users, filters, auth } = usePage<Props>().props;
    const [search, setSearch] = useState(filters.search);
    const [role, setRole] = useState(filters.role);
    const [status, setStatus] = useState(filters.status);

    function filter(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/users', {
            search: search || undefined,
            role: role === 'all' ? undefined : role,
            status: status === 'all' ? undefined : status,
        }, { preserveState: true, replace: true });
    }

    function changeStatus(user: User) {
        const action = user.is_active ? 'deactivate' : 'activate';
        const prompt = user.is_active
            ? `Nonaktifkan akun ${user.name}? Pengguna tidak dapat masuk setelah dinonaktifkan.`
            : `Aktifkan kembali akun ${user.name}?`;
        if (window.confirm(prompt)) {
            router.post(`/users/${user.id}/${action}`);
        }
    }

    return (
        <AppLayout>
            <Head title="Manajemen pengguna" />
            <PageHeader title="Manajemen Pengguna" description={`${users.total} akun terdaftar`} action={<Link href="/users/create" className="w-full rounded-xl bg-emerald-700 px-4 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-emerald-800 sm:w-auto">Tambah pengguna</Link>} />
            <form onSubmit={filter} className="mb-5 grid gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_12rem_12rem_auto]">
                <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama, username, email" className="min-w-0 rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                <select value={role} onChange={(event) => setRole(event.target.value)} aria-label="Filter peran" className="min-w-0 rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><option value="all">Semua peran</option><option value="ketua">Ketua</option><option value="bendahara">Bendahara</option></select>
                <select value={status} onChange={(event) => setStatus(event.target.value)} aria-label="Filter status" className="min-w-0 rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><option value="all">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select>
                <button className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Terapkan</button>
            </form>
            <div className="overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <table className="w-full min-w-[720px] text-left text-sm">
                    <thead className="bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-4 py-3.5 font-semibold">Pengguna</th><th className="px-4 py-3.5 font-semibold">Peran</th><th className="px-4 py-3.5 font-semibold">Status</th><th className="px-4 py-3.5 font-semibold">Login terakhir</th><th className="px-4 py-3.5 font-semibold">Aksi</th></tr></thead>
                    <tbody className="divide-y divide-slate-100">{users.data.map((user) => (
                        <tr key={user.id} className="transition-colors hover:bg-emerald-50/30">
                            <td className="px-4 py-3.5"><span className="font-semibold text-slate-800">{user.name}</span><span className="block text-xs text-slate-500">{user.username} · {user.email}</span><span className="text-xs text-slate-500">{user.phone || 'Telepon belum diisi'}</span></td>
                            <td className="px-4 py-3.5">{roleLabel[user.role]}</td>
                            <td className="px-4 py-3.5"><StatusBadge status={user.is_active ? 'active' : 'inactive'} label={user.is_active ? 'Aktif' : 'Nonaktif'} /></td>
                            <td className="whitespace-nowrap px-4 py-3.5">{user.last_login_at ? formatDateTime(user.last_login_at) : 'Belum pernah'}</td>
                            <td className="whitespace-nowrap px-4 py-3.5">
                                <div className="flex items-center gap-3">
                                    {auth.user?.id !== user.id && <Link href={`/users/${user.id}/edit`} className="font-medium text-emerald-800 hover:underline">Edit</Link>}
                                    {auth.user?.id !== user.id && <button onClick={() => changeStatus(user)} className={`font-medium hover:underline ${user.is_active ? 'text-red-700' : 'text-green-700'}`}>{user.is_active ? 'Nonaktifkan' : 'Aktifkan'}</button>}
                                    {auth.user?.id === user.id && <span className="text-slate-500">Akun Anda</span>}
                                </div>
                            </td>
                        </tr>
                    ))}</tbody>
                </table>
                {users.data.length === 0 && <p className="p-5 text-sm text-slate-500">Pengguna tidak ditemukan.</p>}
            </div>
            {users.last_page > 1 && <nav aria-label="Pagination pengguna" className="mt-4 flex justify-end gap-2">{users.links.map((link, index) => link.url ? <Link key={index} href={link.url} aria-current={link.active ? 'page' : undefined} className={`rounded border px-3 py-1 text-sm ${link.active ? 'border-emerald-700 bg-emerald-700 text-white' : 'bg-white'}`}>{link.label.replace(/&laquo;|&raquo;/g, '')}</Link> : null)}</nav>}
        </AppLayout>
    );
}