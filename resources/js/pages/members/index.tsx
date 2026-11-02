import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FilterDropdown } from '@/components/filter-dropdown';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import { StatusBadge } from '@/components/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatDateShort, formatDateTime } from '@/lib/date';
import type { PageProps } from '@/types/auth';
import type { Member, PaginatedMembers } from '@/types/member';

type MembersPageProps = PageProps<{
    members: PaginatedMembers;
    filters: { search: string; status: 'all' | 'active' | 'inactive' };
}>;

const columns: DataTableColumn<Member>[] = [
    {
        key: 'full_name',
        label: 'Anggota',
        render: (member) => (
            <Link href={`/members/${member.id}`} className="record-link flex-col items-start">
                {member.full_name}
                <span className="record-link-meta">{member.registration_code}</span>
            </Link>
        ),
    },
    { key: 'phone', label: 'WhatsApp', render: (member) => member.phone || '—' },
    { key: 'joined_at', label: 'Bergabung', render: (member) => formatDateShort(member.joined_at) },
    { key: 'created_at', label: 'Waktu dicatat', render: (member) => formatDateTime(member.created_at) },
    {
        key: 'status',
        label: 'Status',
        render: (member) => <StatusBadge status={member.status} label={member.status === 'active' ? 'Aktif' : 'Nonaktif'} />,
    },
];

export default function MembersIndexPage() {
    const { members, filters } = usePage<MembersPageProps>().props;
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (search !== filters.search || status !== filters.status) {
                router.get('/members', {
                    search: search || undefined,
                    status: status === 'all' ? undefined : status,
                }, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                });
            }
        }, 250);

        return () => window.clearTimeout(timeout);
    }, [search, status, filters.search, filters.status]);

    return (
        <AppLayout>
            <PageHeader
                title="Data Anggota"
                description={`${members.meta.total} anggota terdaftar`}
                action={
                    <Link href="/members/create" className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">
                        Tambah anggota
                    </Link>
                }
            />
            <div className="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem]">
                <SearchInput value={search} onChange={setSearch} placeholder="Cari nama atau kode anggota" />
                <FilterDropdown
                    label="Status"
                    value={status}
                    onChange={(value) => setStatus(value as typeof status)}
                    options={[
                        { value: 'all', label: 'Semua status' },
                        { value: 'active', label: 'Aktif' },
                        { value: 'inactive', label: 'Nonaktif' },
                    ]}
                />
            </div>
            <DataTable columns={columns} rows={members.data} getRowKey={(member) => member.id} emptyMessage="Anggota tidak ditemukan." />
            {members.meta.last_page > 1 && (
                <nav aria-label="Pagination anggota" className="mt-4 flex flex-wrap justify-end gap-1">
                    {members.links.map((link, index) => link.url ? (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url}
                            aria-current={link.active ? 'page' : undefined}
                            className={`rounded border px-3 py-1.5 text-sm ${link.active ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'}`}
                        >
                            {link.label.replace(/&laquo;/g, '«').replace(/&raquo;/g, '»')}
                        </Link>
                    ) : (
                        <span key={`${link.label}-${index}`} className="rounded border border-slate-200 px-3 py-1.5 text-sm text-slate-400">
                            {link.label.replace(/&laquo;/g, '«').replace(/&raquo;/g, '»')}
                        </span>
                    ))}
                </nav>
            )}
        </AppLayout>
    );
}