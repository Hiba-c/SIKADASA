import type { ReactNode } from 'react';

type EmptyStateProps = {
    title: string;
    description?: string;
    action?: ReactNode;
};

export function EmptyState({ title, description, action }: EmptyStateProps) {
    return (
        <div className="rounded-lg border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
            <h2 className="font-medium text-slate-900">{title}</h2>
            {description && <p className="mt-1 text-sm text-slate-600">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}