import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { MemberForm } from '@/components/member-form';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import type { MemberFormData } from '@/types/member';

type CreateMemberData = MemberFormData;

export default function CreateMemberPage({ joined_at }: { joined_at: string }) {
    const form = useForm<CreateMemberData>({
        full_name: '',
        address: '',
        house_number: '',
        phone: '',
        joined_at,
        notes: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/members');
    }

    return (
        <AppLayout>
            <Head title="Tambah anggota" />
            <PageHeader title="Tambah Anggota" description="Kode anggota dibuat otomatis saat disimpan." />
            <div className="rounded-lg border border-slate-200 bg-white p-5 sm:p-6">
                <MemberForm
                    data={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(field, value) => form.setData(field, value)}
                    onSubmit={submit}
                    submitLabel="Simpan anggota"
                />
                <div className="mt-4 border-t border-slate-100 pt-4">
                    <Link href="/members" className="text-sm font-medium text-slate-600 hover:text-slate-900">Kembali ke daftar</Link>
                </div>
            </div>
        </AppLayout>
    );
}