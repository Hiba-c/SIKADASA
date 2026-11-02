import type { PropsWithChildren } from 'react';
import { AppFooter } from '@/components/app-footer';

function LandscapeIllustration() {
    return (
        <svg aria-hidden="true" viewBox="0 0 520 230" fill="none" className="absolute inset-x-0 bottom-0 h-44 w-full sm:h-52">
            <path d="M0 104c68-38 126-40 190-14 68 27 126 22 185-8 52-26 99-26 145-2v150H0V104Z" fill="#40B878" fillOpacity=".42" />
            <path d="M0 143c70-35 132-33 195-7 73 30 134 27 198-2 48-22 88-20 127-1v97H0v-87Z" fill="#77CC67" fillOpacity=".78" />
            <path d="M0 176c55-24 112-26 174-9 68 19 135 23 202 4 54-15 99-10 144 7v52H0v-54Z" fill="#0B7952" fillOpacity=".85" />
            <g fill="#075E43" fillOpacity=".8">
                <circle cx="64" cy="169" r="20" /><circle cx="87" cy="158" r="26" /><circle cx="112" cy="174" r="18" />
                <circle cx="419" cy="161" r="24" /><circle cx="446" cy="172" r="19" /><circle cx="390" cy="174" r="17" />
            </g>
            <path d="m174 147 61-42 63 42v57H174v-57Z" fill="#E9F8E7" />
            <path d="m163 148 72-51 74 51" stroke="#075E43" strokeWidth="9" strokeLinejoin="round" />
            <path d="M193 158h26v46h-26zm62-1h27v47h-27z" fill="#91D59B" />
            <path d="M229 161h16v43h-16z" fill="#08734D" />
            <path d="M189 151h101" stroke="#08734D" strokeWidth="5" />
            <g fill="#075E43">
                <path d="M73 144c-18-16-18-35-10-49 14 12 20 29 10 49Zm0 0c-2-24 8-39 25-45 3 19-5 35-25 45Zm346 7c-20-15-23-33-16-48 16 11 23 27 16 48Zm0 0c0-23 11-38 28-42 2 19-7 34-28 42Z" />
            </g>
        </svg>
    );
}

export default function AuthLayout({ children }: PropsWithChildren) {
    return (
        <main className="relative flex min-h-screen flex-col overflow-hidden bg-[#effaf3]">
            <div aria-hidden="true" className="pointer-events-none absolute -left-36 -top-52 h-[34rem] w-[34rem] rounded-full border-[5rem] border-white/65" />
            <div aria-hidden="true" className="pointer-events-none absolute -right-36 -top-56 h-[36rem] w-[36rem] rounded-full bg-gradient-to-br from-emerald-200/70 to-emerald-400/40" />
            <div aria-hidden="true" className="pointer-events-none absolute -bottom-48 -left-24 h-[30rem] w-[52rem] rotate-[-12deg] rounded-[50%] bg-gradient-to-r from-emerald-200/60 to-green-100/20" />
            <div aria-hidden="true" className="pointer-events-none absolute left-8 top-10 hidden grid-cols-5 gap-3 opacity-60 sm:grid">
                {Array.from({ length: 20 }, (_, index) => <span key={index} className="h-1.5 w-1.5 rounded-full bg-emerald-600/45" />)}
            </div>

            <div className="relative z-10 flex w-full flex-1 items-center justify-center px-4 py-6 sm:px-8 sm:py-10">
                <section className="grid w-full max-w-[1060px] overflow-hidden rounded-[1.75rem] bg-white shadow-[0_28px_90px_-40px_rgba(4,88,59,0.42)] ring-1 ring-emerald-950/5 md:min-h-[570px] md:grid-cols-[40%_60%]">
                    <aside className="relative isolate flex min-h-[220px] flex-col overflow-hidden bg-gradient-to-br from-emerald-500 via-emerald-700 to-emerald-900 px-7 py-7 text-center text-white sm:px-10 md:min-h-[570px] md:justify-start md:px-10 md:pt-14">
                        <div aria-hidden="true" className="absolute -right-20 -top-28 -z-10 h-72 w-72 rounded-full bg-emerald-300/20 blur-2xl" />
                        <div className="relative z-10 mx-auto flex items-center gap-3 md:flex-col md:gap-0">
                            <img
                                src="/images/logo-sikadasa.png"
                                alt="Logo SIKADASA"
                                className="h-14 w-14 shrink-0 rounded-xl bg-white p-1 object-contain shadow-sm md:mb-2 md:h-24 md:w-24"
                            />
                            <span className="text-xl font-bold tracking-wide md:text-2xl">SIKADASA</span>
                        </div>
                        <div className="relative z-10 mx-auto mt-4 hidden h-px w-full max-w-[300px] bg-white/20 md:block" />
                        <h2 className="relative z-10 mt-2 text-base font-semibold sm:text-lg md:mt-5 md:text-2xl">Dasawisma Cempaka 46</h2>
                        <p className="relative z-10 mx-auto mt-2 max-w-[290px] text-sm leading-6 text-emerald-50 sm:mt-3 md:text-base">
                            Masuk untuk mengakses sistem pengelolaan keuangan dasawisma dengan mudah dan aman.
                        </p>
                        <div className="hidden md:block">
                            <LandscapeIllustration />
                        </div>
                    </aside>

                    <div className="flex items-center px-6 py-8 sm:px-10 sm:py-10 md:px-14 md:py-12 lg:px-16">
                        <div className="mx-auto w-full max-w-[520px]">{children}</div>
                    </div>
                </section>
            </div>
            <AppFooter className="relative z-10 border-emerald-900/5 bg-white/40 text-emerald-900/60" />
        </main>
    );
}
