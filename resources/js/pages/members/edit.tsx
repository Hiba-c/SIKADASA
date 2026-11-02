import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { MemberForm } from '@/components/member-form';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import type { Member, MemberFormData } from '@/types/member';

export default function EditMemberPage({ member }: { member: Member }) {
    const form = useForm<MemberFormData>({
        full_name: member.full_name,
        address: member.address,
        house_number: member.house_number ?? '',
        phone: member.phone ?? '',
        joined_at: member.joined_at,
        notes: member.notes ?? '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put(`/members/${member.id}`);
    }

    return (
        <AppLayout>
            <Head title={`Edit ${member.full_name}`} />
            <PageHeader title="Edit Data Anggota" description={`Kode anggota: ${member.registration_code}`} />
            <div className="rounded-lg border border-slate-200 bg-white p-5 sm:p-6">
                <MemberForm
                    data={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(field, value) => form.setData(field, value)}
                    onSubmit={submit}
                    submitLabel="Simpan perubahan"
                />
                <div className="mt-4 border-t border-slate-100 pt-4">
                    <Link href={`/members/${member.id}`} className="text-sm font-medium text-slate-600 hover:text-slate-900">Kembali ke detail</Link>
                </div>
            </div>
        </AppLayout>
    );
}