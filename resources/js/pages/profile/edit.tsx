import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { PageProps, User } from '@/types/auth';

type ProfileProps = PageProps<{ user: User }>;

export default function ProfilePage() {
    const { user } = usePage<ProfileProps>().props;
    const profile = useForm({
        name: user.name,
        username: user.username,
        email: user.email,
        phone: user.phone ?? '',
    });
    const password = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function saveProfile(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        profile.put('/profile');
    }

    function savePassword(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        password.put('/profile/password', {
            onSuccess: () => password.reset(),
        });
    }

    return (
        <AppLayout>
            <Head title="Profil" />
            <h1 className="mb-6 text-2xl font-semibold">Profil Pengguna</h1>
            <div className="grid gap-6 lg:grid-cols-2">
                <form onSubmit={saveProfile} className="space-y-4 rounded-lg border border-slate-200 bg-white p-5">
                    <h2 className="font-semibold">Informasi akun</h2>
                    <label className="block text-sm">Nama
                        <input value={profile.data.name} onChange={(event) => profile.setData('name', event.target.value)} className="mt-1 w-full rounded border px-3 py-2" required />
                    </label>
                    <label className="block text-sm">Nama pengguna
                        <input value={profile.data.username} onChange={(event) => profile.setData('username', event.target.value)} className="mt-1 w-full rounded border px-3 py-2" required />
                        {profile.errors.username && <span className="text-red-700">{profile.errors.username}</span>}
                    </label>
                    <label className="block text-sm">Email
                        <input type="email" value={profile.data.email} onChange={(event) => profile.setData('email', event.target.value)} className="mt-1 w-full rounded border px-3 py-2" required />
                        {profile.errors.email && <span className="text-red-700">{profile.errors.email}</span>}
                    </label>
                    <label className="block text-sm">Nomor WhatsApp
                        <input value={profile.data.phone} onChange={(event) => profile.setData('phone', event.target.value)} className="mt-1 w-full rounded border px-3 py-2" />
                    </label>
                    <button disabled={profile.processing} className="rounded bg-emerald-700 px-4 py-2 font-medium text-white disabled:opacity-60">Simpan profil</button>
                </form>
                <form onSubmit={savePassword} className="space-y-4 rounded-lg border border-slate-200 bg-white p-5">
                    <h2 className="font-semibold">Ubah kata sandi</h2>
                    <label className="block text-sm">Kata sandi saat ini
                        <input type="password" autoComplete="current-password" value={password.data.current_password} onChange={(event) => password.setData('current_password', event.target.value)} className="mt-1 w-full rounded border px-3 py-2" required />
                        {password.errors.current_password && <span className="text-red-700">{password.errors.current_password}</span>}
                    </label>
                    <label className="block text-sm">Kata sandi baru (minimal 12 karakter)
                        <input type="password" autoComplete="new-password" value={password.data.password} onChange={(event) => password.setData('password', event.target.value)} className="mt-1 w-full rounded border px-3 py-2" required />
                        {password.errors.password && <span className="text-red-700">{password.errors.password}</span>}
                    </label>
                    <label className="block text-sm">Konfirmasi kata sandi baru
                        <input type="password" autoComplete="new-password" value={password.data.password_confirmation} onChange={(event) => password.setData('password_confirmation', event.target.value)} className="mt-1 w-full rounded border px-3 py-2" required />
                    </label>
                    <button disabled={password.processing} className="rounded bg-emerald-700 px-4 py-2 font-medium text-white disabled:opacity-60">Perbarui kata sandi</button>
                </form>
            </div>
        </AppLayout>
    );
}
