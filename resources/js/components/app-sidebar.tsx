import { Link, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types/auth';

const navigationGroups = [
    {
        label: 'UTAMA',
        links: [{ href: '/dashboard', label: 'Dashboard', icon: 'dashboard' }],
    },
    {
        label: 'KELOLA ANGGOTA',
        links: [{ href: '/members', label: 'Anggota', icon: 'members' }],
    },
    {
        label: 'KEUANGAN',
        links: [
            { href: '/cash', label: 'Kas', icon: 'cash' },
            { href: '/savings', label: 'Tabungan', icon: 'savings' },
            { href: '/arisan', label: 'Arisan', icon: 'arisan' },
            { href: '/loans', label: 'Pinjaman', icon: 'loans' },
            { href: '/installments', label: 'Angsuran', icon: 'installments' },
            { href: '/installments/payments', label: 'Pembayaran pinjaman', icon: 'payments' },
            { href: '/reports', label: 'Laporan', icon: 'reports' },
        ],
    },
    {
        label: 'AKUN',
        links: [{ href: '/profile', label: 'Profil', icon: 'profile' }],
    },
];

const chairpersonLinks = [
    { href: '/audit-logs', label: 'Audit Log', icon: 'audit' },
    { href: '/backups', label: 'Backup', icon: 'backup' },
    { href: '/users', label: 'Pengguna', icon: 'users' },
];

type AppSidebarProps = {
    open: boolean;
    onClose: () => void;
};

type IconName = (typeof navigationGroups)[number]['links'][number]['icon'] | (typeof chairpersonLinks)[number]['icon'];

function NavigationIcon({ name }: { name: IconName }) {
    const paths: Record<IconName, string> = {
        dashboard: 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
        members: 'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm6-7.5a4 4 0 0 1 0 7.7M20 21v-2a4 4 0 0 0-3-3.87',
        cash: 'M3 6h18v13H3zM3 9h18m-5 5h2M7 3h10v3',
        savings: 'M3 21h18M5 18V9h14v9M4 9l8-6 8 6m-8 0v9m-4-5h.01M16 13h.01',
        arisan: 'M12 3l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z',
        loans: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm0 0v6h6M8 13h8m-8 4h8',
        installments: 'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2zm3 10h3m-3 4h3',
        payments: 'M4 3h16v18l-3-2-3 2-3-2-3 2-4-2zm4 5h8m-8 4h8m-8 4h5',
        reports: 'M3 3v18h18M8 16v-4m5 4V7m5 9v-6',
        profile: 'M20 21a8 8 0 0 0-16 0m8-10a5 5 0 1 0 0-10 5 5 0 0 0 0 10z',
        audit: 'M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
        backup: 'M12 3v12m0 0 4-4m-4 4-4-4M4 17v4h16v-4',
        users: 'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm6-7.5a4 4 0 0 1 0 7.7M20 21v-2a4 4 0 0 0-3-3.87',
    };

    return <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5 shrink-0"><path d={paths[name]} /></svg>;
}

export function AppSidebar({ open, onClose }: AppSidebarProps) {
    const page = usePage<PageProps>();
    const { auth } = page.props;
    const visibleGroups = auth.user?.role === 'ketua'
        ? [...navigationGroups, { label: 'ADMINISTRASI', links: chairpersonLinks }]
        : navigationGroups;

    return (
        <aside id="app-sidebar" className={`fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-[#235347] bg-gradient-to-b from-[#0b2b26] to-[#163832] text-[#daf1de] shadow-xl shadow-emerald-950/10 transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:shrink-0 lg:translate-x-0 lg:transition-[width] ${open ? 'translate-x-0 lg:w-64' : '-translate-x-full lg:w-[4.5rem]'}`}>
            <div className={`flex h-16 shrink-0 items-center border-b border-white/10 px-4 ${open ? 'gap-3' : 'lg:justify-center lg:px-2'}`}>
                <Link href="/dashboard" onClick={() => { if (!window.matchMedia('(min-width: 1024px)').matches) onClose(); }} className="flex min-w-0 items-center gap-3">
                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white ring-white">
                        <img src="/images/logo-sikadasa.png" alt="Logo SIKADASA" className="h-8 w-8 object-contain" />
                    </span>
                    <span className={`truncate font-semibold tracking-wide text-[#daf1de] ${open ? '' : 'lg:hidden'}`}>SIKADASA</span>
                </Link>
                <button type="button" onClick={onClose} aria-label="Tutup navigasi" className="ml-auto rounded-md p-2 text-[#daf1de] hover:bg-white/10 lg:hidden">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5"><path strokeLinecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
            </div>
            <nav aria-label="Navigasi utama" className="flex-1 space-y-5 overflow-y-auto px-3 py-4">
                {visibleGroups.map((group) => (
                    <section key={group.label} className="space-y-1">
                        <h2 className={`px-3 pb-1 text-[10px] font-semibold tracking-[0.14em] text-[#8eb69b] ${open ? '' : 'lg:sr-only'}`}>
                            {group.label}
                        </h2>
                        {group.links.map(({ href, label, icon }) => {
                            const active = page.url === href || (href !== '/dashboard' && href !== '/installments' && page.url.startsWith(`${href}/`));
                            return (
                                <Link
                                    key={href}
                                    href={href}
                                    onClick={() => { if (!window.matchMedia('(min-width: 1024px)').matches) onClose(); }}
                                    aria-current={active ? 'page' : undefined}
                                    title={!open ? label : undefined}
                                    className={`flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors ${open ? '' : 'lg:justify-center lg:px-0'} ${active ? 'bg-[#235347] text-[#daf1de] ring-1 ring-inset ring-[#8eb69b]/40 shadow-sm' : 'text-[#daf1de]/75 hover:bg-white/[0.07] hover:text-white'}`}
                                >
                                    <NavigationIcon name={icon} />
                                    <span className={open ? '' : 'lg:hidden'}>{label}</span>
                                    {active && open && <span aria-hidden="true" className="ml-auto h-1.5 w-1.5 rounded-full bg-emerald-600" />}
                                </Link>
                            );
                        })}
                    </section>
                ))}
            </nav>
        </aside>
    );
}