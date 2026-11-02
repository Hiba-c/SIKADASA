import type { Key, ReactNode } from 'react';

export type DataTableColumn<T> = {
    key: string;
    label: string;
    render?: (row: T) => ReactNode;
};

type DataTableProps<T> = {
    columns: DataTableColumn<T>[];
    rows: T[];
    getRowKey: (row: T) => Key;
    emptyMessage?: string;
};

export function DataTable<T>({ columns, rows, getRowKey, emptyMessage = 'Belum ada data.' }: DataTableProps<T>) {
    return (
        <div className="overflow-x-auto rounded-2xl border border-emerald-900/10 bg-white shadow-md shadow-emerald-950/[0.04]">
            <table className="w-full min-w-[640px] text-left text-sm">
                <thead className="bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500">
                    <tr>{columns.map((column) => <th key={column.key} scope="col" className="whitespace-nowrap px-4 py-3.5 font-semibold">{column.label}</th>)}</tr>
                </thead>
                <tbody className="divide-y divide-emerald-900/[0.07] text-slate-700">
                    {rows.length === 0 ? (
                        <tr><td colSpan={columns.length} className="px-4 py-12 text-center text-slate-500">{emptyMessage}</td></tr>
                    ) : rows.map((row) => (
                        <tr key={getRowKey(row)} className="transition-colors hover:bg-emerald-50/70">
                            {columns.map((column) => (
                                <td key={column.key} className="px-4 py-3.5">
                                    {column.render ? column.render(row) : String((row as Record<string, unknown>)[column.key] ?? '')}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}