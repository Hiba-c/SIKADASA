import { useState, type PropsWithChildren } from 'react';
import { usePage } from '@inertiajs/react';
import { AppHeader } from '@/components/app-header';
import { AppFooter } from '@/components/app-footer';
import { AppSidebar } from '@/components/app-sidebar';
import type { PageProps } from '@/types/auth';

export default function AppLayout({ children }: PropsWithChildren) {
    const { flash } = usePage<PageProps>().props;
    const [sidebarOpen, setSidebarOpen] = useState(
        () => typeof window !== 'undefined' && window.matchMedia('(min-width: 1024px)').matches,
    );

    return (
        <div id="app-shell" className="flex min-h-screen bg-white text-slate-900">
            <AppSidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} />
            {sidebarOpen && <button type="button" aria-label="Tutup menu navigasi" onClick={() => setSidebarOpen(false)} className="fixed inset-0 z-30 bg-slate-950/40 lg:hidden" />}
            <div className="flex min-h-screen min-w-0 flex-1 flex-col overflow-x-hidden">
                <AppHeader sidebarOpen={sidebarOpen} onToggleSidebar={() => setSidebarOpen((open) => !open)} />
                <main className="mx-auto w-full max-w-[1440px] flex-1 p-4 sm:p-6 lg:p-8">
                    <div className="mb-5 space-y-2" aria-live="polite">
                        {flash.success && <p role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-sm text-emerald-800">{flash.success}</p>}
                        {flash.error && <p role="alert" className="rounded-xl border border-red-200 bg-red-50 p-3.5 text-sm text-red-800">{flash.error}</p>}
                        {flash.warning && <p role="status" className="rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-800">{flash.warning}</p>}
                        {flash.info && <p role="status" className="rounded-xl border border-blue-200 bg-blue-50 p-3.5 text-sm text-blue-800">{flash.info}</p>}
                    </div>
                    {children}
                </main>
                <AppFooter />
            </div>
        </div>
    );
}