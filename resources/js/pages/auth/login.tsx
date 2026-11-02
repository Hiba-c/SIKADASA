import { Head, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AuthLayout from '@/layouts/auth-layout';
import type { PageProps } from '@/types/auth';

function FieldIcon({ type }: { type: 'email' | 'lock' }) {
    return (
        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
            {type === 'email' ? (
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" className="h-5 w-5">
                    <path d="M4 6.5h16v11H4z" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" />
                    <path d="m4.5 7 7.5 6 7.5-6" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            ) : (
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" className="h-5 w-5">
                    <rect x="5" y="10" width="14" height="11" rx="2.5" stroke="currentColor" strokeWidth="1.8" />
                    <path d="M8 10V7a4 4 0 1 1 8 0v3m-4 5v2" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
                </svg>
            )}
        </span>
    );
}

export default function LoginPage() {
    const { data, setData, post, processing, errors } = useForm({
        login: '',
        password: '',
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);
    const flash = usePage<PageProps>().props.flash;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/login');
    }

    return (
        <AuthLayout>
            <Head title="Masuk" />
            <div className="mb-7">
                <span aria-hidden="true" className="mb-3 block h-1.5 w-14 rounded-full bg-emerald-600" />
                <h1 className="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Selamat Datang!</h1>
                <p className="mt-2 text-base text-slate-500 sm:text-lg">Silakan masuk untuk melanjutkan ke sistem.</p>
            </div>

            {flash.error && <p role="alert" className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{flash.error}</p>}

            <form onSubmit={submit} className="space-y-5">
                <div className="flex items-start gap-4">
                    <FieldIcon type="email" />
                    <div className="min-w-0 flex-1">
                        <label htmlFor="login" className="mb-2 block text-sm font-semibold text-slate-800">Nama pengguna atau email</label>
                        <input
                            id="login"
                            autoComplete="username"
                            value={data.login}
                            onChange={(event) => setData('login', event.target.value)}
                            placeholder="Masukkan nama pengguna atau email"
                            className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
                            required
                        />
                        {errors.login && <p role="alert" className="mt-1.5 text-sm text-red-700">{errors.login}</p>}
                    </div>
                </div>

                <div className="flex items-start gap-4">
                    <FieldIcon type="lock" />
                    <div className="min-w-0 flex-1">
                        <label htmlFor="password" className="mb-2 block text-sm font-semibold text-slate-800">Kata sandi</label>
                        <div className="relative">
                            <input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                placeholder="Masukkan kata sandi"
                                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 pr-12 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
                                required
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword((visible) => !visible)}
                                aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                                aria-pressed={showPassword}
                                className="absolute inset-y-0 right-0 flex items-center px-4 text-slate-500 transition hover:text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
                            >
                                {showPassword ? (
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" className="h-5 w-5">
                                        <path d="M3 3 21 21M10.6 10.7a2 2 0 0 0 2.7 2.7M9.9 5.2A10.7 10.7 0 0 1 12 5c5.4 0 9 5 9 7a9.7 9.7 0 0 1-3.1 4.6M6.2 6.3C3.9 7.7 3 10.2 3 12c0 2 3.6 7 9 7 1.1 0 2.2-.3 3.2-.7" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                ) : (
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" className="h-5 w-5">
                                        <path d="M3 12s3.2-7 9-7 9 7 9 7-3.2 7-9 7-9-7-9-7Z" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" />
                                        <circle cx="12" cy="12" r="2.5" stroke="currentColor" strokeWidth="1.8" />
                                    </svg>
                                )}
                            </button>
                        </div>
                        {errors.password && <p role="alert" className="mt-1.5 text-sm text-red-700">{errors.password}</p>}
                    </div>
                </div>

                <label className="flex min-h-8 cursor-pointer items-center gap-2.5 pl-16 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        checked={data.remember}
                        onChange={(event) => setData('remember', event.target.checked)}
                        className="h-5 w-5 rounded border-slate-300 text-emerald-700 accent-emerald-700 focus:ring-emerald-600"
                    />
                    Ingat saya
                </label>

                <button
                    type="submit"
                    disabled={processing}
                    className="ml-16 flex w-[calc(100%-4rem)] items-center justify-center gap-3 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-700 px-4 py-3.5 font-semibold text-white shadow-[0_10px_22px_-12px_rgba(5,150,105,0.8)] transition hover:from-emerald-600 hover:to-emerald-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500/30 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {processing ? (
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" className="h-5 w-5 animate-spin">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity=".3" strokeWidth="3" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
                        </svg>
                    ) : (
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" className="h-5 w-5">
                            <path d="M13 5h4a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-4m-2-4 4-3-4-3m4 3H4" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                    )}
                    {processing ? 'Memproses...' : 'Masuk'}
                </button>
            </form>
        </AuthLayout>
    );
}
