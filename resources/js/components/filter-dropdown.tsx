type FilterOption = {
    value: string;
    label: string;
};

type FilterDropdownProps = {
    label: string;
    value: string;
    options: FilterOption[];
    onChange: (value: string) => void;
};

export function FilterDropdown({ label, value, options, onChange }: FilterDropdownProps) {
    return (
        <label className="block text-sm font-medium text-slate-700">
            {label}
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 font-normal"
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>{option.label}</option>
                ))}
            </select>
        </label>
    );
}