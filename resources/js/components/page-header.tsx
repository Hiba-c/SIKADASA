import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: string;
    description?: string;
    action?: ReactNode;
};

export function PageHeader({ title, description, action }: PageHeaderProps) {
    return (
        <div className="page-title-surface mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white px-5 py-4 shadow-sm shadow-emerald-950/[0.04]">
            <div className="min-w-0">
                <h1 className="flex items-center gap-2.5 text-2xl font-semibold tracking-tight text-slate-900 sm:text-[1.75rem]">
                    <span aria-hidden="true" className="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500" />
                    {title}
                </h1>
                {description && <p className="mt-1.5 max-w-2xl pl-5 text-sm leading-6 text-slate-500">{description}</p>}
            </div>
            {action && <div className="flex w-full shrink-0 flex-wrap gap-2 sm:w-auto">{action}</div>}
        </div>
    );
}