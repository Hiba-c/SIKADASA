import type { ReactNode } from 'react';

type ConfirmationDialogProps = {
    open: boolean;
    title: string;
    description: string;
    confirmLabel?: string;
    children?: ReactNode;
    onConfirm: () => void;
    onCancel: () => void;
};

export function ConfirmationDialog({
    open,
    title,
    description,
    confirmLabel = 'Konfirmasi',
    children,
    onConfirm,
    onCancel,
}: ConfirmationDialogProps) {
    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
            <section role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h2 id="confirm-title" className="text-lg font-semibold">{title}</h2>
                <p className="mt-2 text-sm text-slate-600">{description}</p>
                {children}
                <div className="mt-6 flex justify-end gap-3">
                    <button type="button" onClick={onCancel} className="rounded-md border px-4 py-2">Batal</button>
                    <button type="button" onClick={onConfirm} className="rounded-md bg-red-700 px-4 py-2 font-medium text-white">{confirmLabel}</button>
                </div>
            </section>
        </div>
    );
}