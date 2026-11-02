import { usePage, router } from '@inertiajs/react';
import type { PageProps } from '@/types/auth';

type AppHeaderProps = {
    sidebarOpen: boolean;
    onToggleSidebar: () => void;
};

export function AppHeader({ sidebarOpen, onToggleSidebar }: AppHeaderProps) {
    const user = usePage<PageProps>().props.auth.user;

    return (
        <header className="sticky top-0 z-20 flex min-h-16 items-center justify-between border-b border-[#235347] bg-[#163832] px-4 py-3 text-[#daf1de] shadow-sm shadow-emerald-950/10 sm:px-6">
            <button
                type="button"
                aria-label={sidebarOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi'}
                aria-expanded={sidebarOpen}
                aria-controls="app-sidebar"
                onClick={onToggleSidebar}
                className="rounded-xl p-2 text-[#daf1de] hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#8eb69b]"
            >
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-6 w-6">
                    <path strokeLinecap="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <div className="flex min-w-0 items-center gap-3 text-sm">
                <div className="hidden min-w-0 text-right sm:block">
                    <p className="truncate font-medium text-[#daf1de]">{user?.name}</p>
                    <p className="text-xs capitalize text-[#8eb69b]">{user?.role === 'ketua' ? 'Ketua' : 'Bendahara'}</p>
                </div>
                <button
                    type="button"
                    className="rounded-xl border border-[#8eb69b]/60 bg-[#235347] px-3.5 py-2 font-medium text-[#daf1de] transition hover:border-[#8eb69b] hover:bg-[#0b2b26]"
                    onClick={() => router.post('/logout')}
                >
                    Keluar
                </button>
            </div>
        </header>
    );
}