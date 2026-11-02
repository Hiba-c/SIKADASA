type SearchInputProps = {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    label?: string;
};

export function SearchInput({
    value,
    onChange,
    placeholder = 'Cari...',
    label = 'Cari data',
}: SearchInputProps) {
    return (
        <label className="block text-sm">
            <span className="sr-only">{label}</span>
            <input
                type="search"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                aria-label={label}
                className="w-full rounded-md border border-slate-300 px-3 py-2"
            />
        </label>
    );
}