import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { UserForm, type UserFormData } from '@/components/user-form';
import AppLayout from '@/layouts/app-layout';

export default function UserCreatePage() {
    const form = useForm<UserFormData>({
        name: '',
        username: '',
        email: '',
        phone: '',
        role: 'bendahara',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/users');
    }

    return (
        <AppLayout>
            <Head title="Tambah pengguna" />
            <PageHeader title="Tambah Pengguna" description="Kata sandi minimal 12 karakter. Akun baru langsung aktif." />
            <UserForm
                data={form.data}
                errors={form.errors}
                processing={form.processing}
                onChange={(data) => form.setData(data)}
                onSubmit={submit}
            />
            <Link href="/users" className="mt-4 inline-block text-sm font-medium text-slate-600 hover:text-slate-900">Kembali ke pengguna</Link>
        </AppLayout>
    );
}
