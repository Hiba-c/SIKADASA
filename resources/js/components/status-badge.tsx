const styles: Record<string, string> = {
    active: 'bg-green-50 text-green-700',
    paid: 'bg-green-50 text-green-700',
    completed: 'bg-green-50 text-green-700',
    posted: 'bg-green-50 text-green-700',
    pending: 'bg-amber-50 text-amber-700',
    partial: 'bg-amber-50 text-amber-700',
    inactive: 'bg-slate-100 text-slate-700',
    cancelled: 'bg-slate-100 text-slate-700',
    reversed: 'bg-red-50 text-red-700',
    rejected: 'bg-red-50 text-red-700',
};

type StatusBadgeProps = {
    status: string;
    label?: string;
};

export function StatusBadge({ status, label = status }: StatusBadgeProps) {
    return (
        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${styles[status] ?? 'bg-slate-100 text-slate-700'}`}>
            {label}
        </span>
    );
}