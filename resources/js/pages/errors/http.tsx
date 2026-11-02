import { Head, Link, usePage } from '@inertiajs/react';
import { AppFooter } from '@/components/app-footer';
import type { PageProps } from '@/types/auth';

type Props = PageProps<{
    status: number;
    title: string;
    message: string;
}>;

export default function HttpErrorPage() {
    const { status, title, message, auth } = usePage<Props>().props;

    return (
        <main className="flex min-h-screen flex-col bg-slate-50">
            <Head title={`${status} — ${title}`} />
            <div className="flex flex-1 items-center justify-center px-4 py-10">
                <section className="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm sm:p-8">
                    <img src="/images/logo-sikadasa.png" alt="Logo SIKADASA" className="mx-auto mb-4 h-16 w-16 object-contain" />
                    <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">SIKADASA</p>
                    <p className="mt-5 text-5xl font-bold tracking-tight text-slate-900">HTTP {status}</p>
                    <h1 className="mt-3 text-xl font-semibold text-slate-900">{title}</h1>
                    <p className="mt-2 text-sm leading-6 text-slate-600">{message}</p>
                    <div className="mt-6 flex flex-col justify-center gap-2 sm:flex-row">
                        <button type="button" onClick={() => window.history.back()} className="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                            Kembali
                        </button>
                        <Link href={auth.user ? '/dashboard' : '/login'} className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">
                            {auth.user ? 'Ke dashboard' : 'Ke halaman masuk'}
                        </Link>
                    </div>
                </section>
            </div>
            <AppFooter />
        </main>
    );
}
