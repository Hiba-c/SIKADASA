import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { UserForm, type UserFormData } from '@/components/user-form';
import AppLayout from '@/layouts/app-layout';
import type { User } from '@/types/auth';

type EditableUser = Pick<User, 'id' | 'name' | 'username' | 'email' | 'phone' | 'role' | 'is_active'>;

export default function UserEditPage({ user }: { user: EditableUser }) {
    const form = useForm<UserFormData>({
        name: user.name,
        username: user.username,
        email: user.email,
        phone: user.phone ?? '',
        role: user.role,
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put(`/users/${user.id}`);
    }

    return (
        <AppLayout>
            <Head title={`Edit ${user.name}`} />
            <PageHeader title="Edit Pengguna" description={`Akun ${user.is_active ? 'aktif' : 'nonaktif'} · kata sandi kosongkan jika tidak diubah.`} />
            <UserForm
                data={form.data}
                errors={form.errors}
                processing={form.processing}
                existingUser={user}
                onChange={(data) => form.setData(data)}
                onSubmit={submit}
            />
            <Link href="/users" className="mt-4 inline-block text-sm font-medium text-slate-600 hover:text-slate-900">Kembali ke pengguna</Link>
        </AppLayout>
    );
}
