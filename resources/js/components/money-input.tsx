type MoneyInputProps = {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    required?: boolean;
};

export function MoneyInput({ id, label, value, onChange, error, required }: MoneyInputProps) {
    return (
        <div>
            <label htmlFor={id} className="mb-1 block text-sm font-medium">{label}</label>
            <div className="flex rounded-md border border-slate-300 focus-within:ring-2 focus-within:ring-emerald-600">
                <span className="border-r border-slate-200 px-3 py-2 text-sm text-slate-500">Rp</span>
                <input
                    id={id}
                    inputMode="decimal"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    required={required}
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? `${id}-error` : undefined}
                    className="min-w-0 flex-1 rounded-r-md px-3 py-2 outline-none"
                />
            </div>
            {error && <p id={`${id}-error`} className="mt-1 text-sm text-red-700">{error}</p>}
        </div>
    );
}