export function AppFooter({ className = '' }: { className?: string }) {
    return (
        <footer className={`flex w-full items-center justify-center gap-2 border-t border-slate-200/80 bg-white/70 px-4 py-3 text-center text-xs text-slate-500 ${className}`}>
            <img src="/images/logo-sikadasa.png" alt="" aria-hidden="true" className="h-5 w-5 shrink-0 object-contain opacity-45" />
            <span>© {new Date().getFullYear()} SIKADASA · Dasawisma Cempaka 46</span>
        </footer>
    );
}
