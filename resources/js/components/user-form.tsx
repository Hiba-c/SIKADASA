import type { FormEvent } from 'react';
import type { User, UserRole } from '@/types/auth';

type UserFormData = {
    name: string;
    username: string;
    email: string;
    phone: string;
    role: UserRole;
    password: string;
    password_confirmation: string;
};

type Props = {
    data: UserFormData;
    errors: Partial<Record<keyof UserFormData, string>>;
    processing: boolean;
    existingUser?: Pick<User, 'id' | 'name' | 'username' | 'email' | 'phone' | 'role' | 'is_active'>;
    onChange: (data: UserFormData) => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

export type { UserFormData };

export function UserForm({ data, errors, processing, existingUser, onChange, onSubmit }: Props) {
    return (
        <form onSubmit={onSubmit} className="max-w-3xl space-y-5 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
            <label className="block text-sm font-medium">Nama lengkap
                <input required maxLength={255} autoComplete="name" value={data.name} onChange={(event) => onChange({ ...data, name: event.target.value })} className="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5" />
                {errors.name && <span className="text-sm text-red-700">{errors.name}</span>}
            </label>
            <div className="grid gap-4 sm:grid-cols-2">
                <label className="block text-sm font-medium">Username
                    <input required maxLength={50} autoComplete="username" value={data.username} onChange={(event) => onChange({ ...data, username: event.target.value })} className="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5" />
                    {errors.username && <span className="text-sm text-red-700">{errors.username}</span>}
                </label>
                <label className="block text-sm font-medium">Email
                    <input required type="email" maxLength={255} autoComplete="email" value={data.email} onChange={(event) => onChange({ ...data, email: event.target.value })} className="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5" />
                    {errors.email && <span className="text-sm text-red-700">{errors.email}</span>}
                </label>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <label className="block text-sm font-medium">Nomor telepon
                    <input maxLength={30} autoComplete="tel" value={data.phone} onChange={(event) => onChange({ ...data, phone: event.target.value })} className="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5" />
                    {errors.phone && <span className="text-sm text-red-700">{errors.phone}</span>}
                </label>
                <label className="block text-sm font-medium">Peran
                    <select value={data.role} onChange={(event) => onChange({ ...data, role: event.target.value as UserRole })} className="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5">
                        <option value="bendahara">Bendahara</option>
                        <option value="ketua">Ketua</option>
                    </select>
                    {errors.role && <span className="text-sm text-red-700">{errors.role}</span>}
                </label>
            </div>
            <div className="border-t border-slate-100 pt-4">
                <h2 className="font-medium">{existingUser ? 'Atur ulang kata sandi (opsional)' : 'Kata sandi awal'}</h2>
                {existingUser && <p className="mt-1 text-sm text-slate-500">Kosongkan jika tidak ingin mengganti kata sandi.</p>}
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <label className="block text-sm font-medium">Kata sandi
                    <input required={!existingUser} type="password" minLength={12} autoComplete="new-password" value={data.password} onChange={(event) => onChange({ ...data, password: event.target.value })} className="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5" />
                    {errors.password && <span className="text-sm text-red-700">{errors.password}</span>}
                </label>
                <label className="block text-sm font-medium">Konfirmasi kata sandi
                    <input required={!existingUser && data.password.length > 0} type="password" minLength={12} autoComplete="new-password" value={data.password_confirmation} onChange={(event) => onChange({ ...data, password_confirmation: event.target.value })} className="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5" />
                </label>
            </div>
            {errors.password_confirmation && <p className="text-sm text-red-700">{errors.password_confirmation}</p>}
            <button disabled={processing} className="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:opacity-50">
                {existingUser ? 'Simpan perubahan' : 'Buat pengguna'}
            </button>
        </form>
    );
}
