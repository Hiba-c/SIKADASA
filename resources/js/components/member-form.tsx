import type { FormEvent } from 'react';
import type { MemberFormData } from '@/types/member';

type MemberFormProps = {
    data: MemberFormData;
    errors: Partial<Record<keyof MemberFormData, string>>;
    processing: boolean;
    onChange: (field: keyof MemberFormData, value: string) => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    submitLabel: string;
};

const fields: Array<{
    key: keyof MemberFormData;
    label: string;
    type?: string;
    required?: boolean;
    maxLength?: number;
}> = [
    { key: 'full_name', label: 'Nama lengkap', required: true, maxLength: 255 },
    { key: 'address', label: 'Alamat', required: true },
    { key: 'house_number', label: 'Nomor rumah', maxLength: 30 },
    { key: 'phone', label: 'Nomor WhatsApp', type: 'tel', maxLength: 30 },
    { key: 'joined_at', label: 'Tanggal bergabung', type: 'date', required: true },
    { key: 'notes', label: 'Catatan', maxLength: 2000 },
];

export function MemberForm({
    data,
    errors,
    processing,
    onChange,
    onSubmit,
    submitLabel,
}: MemberFormProps) {
    return (
        <form onSubmit={onSubmit} className="space-y-5">
            <div className="grid gap-5 sm:grid-cols-2">
                {fields.map((field) => (
                    <label
                        key={field.key}
                        htmlFor={field.key}
                        className={`block text-sm font-medium text-slate-700 ${field.key === 'address' || field.key === 'notes' ? 'sm:col-span-2' : ''}`}
                    >
                        {field.label}{field.key === 'notes' && <span className="font-normal text-slate-500"> (opsional)</span>}
                        {field.key === 'address' || field.key === 'notes' ? (
                            <textarea
                                id={field.key}
                                value={data[field.key]}
                                onChange={(event) => onChange(field.key, event.target.value)}
                                required={field.required}
                                rows={field.key === 'notes' ? 4 : 3}
                                maxLength={field.maxLength}
                                placeholder={field.key === 'notes' ? 'Catatan administrasi anggota (maksimal 2.000 karakter)' : undefined}
                                aria-invalid={Boolean(errors[field.key])}
                                className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 font-normal"
                            />
                        ) : (
                            <input
                                id={field.key}
                                type={field.type ?? 'text'}
                                value={data[field.key]}
                                onChange={(event) => onChange(field.key, event.target.value)}
                                required={field.required}
                                maxLength={field.maxLength}
                                aria-invalid={Boolean(errors[field.key])}
                                className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 font-normal"
                            />
                        )}
                        {errors[field.key] && (
                            <span className="mt-1 block text-sm font-normal text-red-700">{errors[field.key]}</span>
                        )}
                    </label>
                ))}
            </div>
            <div className="flex justify-end gap-3">
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-md bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                >
                    {processing ? 'Menyimpan...' : submitLabel}
                </button>
            </div>
        </form>
    );
}
