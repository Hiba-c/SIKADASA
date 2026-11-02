export type { User, UserRole, PageProps } from './auth.d';
export type { Member, MemberStatus, SavingsAccount, ArisanParticipant } from './member.d';
export type {
    TransactionType,
    TransactionStatus,
    CashCategory,
    CashTransaction,
    SavingsTransactionType,
    SavingsTransaction,
    ArisanCycleStatus,
    ArisanRoundStatus,
    ArisanCycle,
    ArisanRound,
    ArisanPayment,
    ArisanPayout,
    Account,
    JournalEntry,
    JournalLine,
    OrganizationSetting,
} from './finance.d';
export type {
    LoanStatus,
    InstallmentStatus,
    Loan,
    LoanInstallment,
    LoanPayment,
} from './loan.d';

/** Paginated response from Laravel */
export interface PaginatedData<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
}

export interface LaravelPaginator<T> {
    data: T[];
    last_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}
