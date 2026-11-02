export type TransactionType = 'income' | 'expense';
export type TransactionStatus = 'posted' | 'reversed';

export interface CashCategory {
    id: number;
    name: string;
    type: TransactionType;
    description: string | null;
    is_active: boolean;
}

export interface CashTransaction {
    id: number;
    transaction_number: string;
    category_id: number;
    transaction_type: TransactionType;
    payer_name: string | null;
    transaction_date: string;
    amount: number;
    description: string | null;
    status: TransactionStatus;
    created_by: number;
    reversed_by: number | null;
    reversed_at: string | null;
    reversal_reason: string | null;
    created_at: string;
    updated_at: string;
    // Relations
    category?: CashCategory;
    creator?: import('./auth.d').User;
}

export type SavingsTransactionType = 'deposit' | 'withdrawal';

export interface SavingsTransaction {
    id: number;
    transaction_number: string;
    savings_account_id: number;
    type: SavingsTransactionType;
    amount: number;
    transaction_date: string;
    description: string | null;
    status: TransactionStatus;
    created_by: number;
    reversed_at: string | null;
    reversal_reason: string | null;
    created_at: string;
    updated_at: string;
    savings_account?: import('./member.d').SavingsAccount;
}

export type ArisanCycleStatus = 'active' | 'completed' | 'cancelled';
export type ArisanRoundStatus = 'open' | 'drawn' | 'completed' | 'cancelled';

export interface ArisanCycle {
    id: number;
    cycle_number: string;
    name: string;
    contribution_amount: number;
    status: ArisanCycleStatus;
    started_at: string;
    completed_at: string | null;
    created_by: number;
    created_at: string;
    updated_at: string;
    participants?: import('./member.d').ArisanParticipant[];
    rounds?: ArisanRound[];
}

export interface ArisanRound {
    id: number;
    cycle_id: number;
    round_number: number;
    round_date: string;
    status: ArisanRoundStatus;
    winner_participant_id: number | null;
    total_collected: number;
    drawn_at: string | null;
    drawn_by: number | null;
    created_at: string;
    updated_at: string;
    cycle?: ArisanCycle;
    winner?: import('./member.d').ArisanParticipant;
    drawn_by_name?: string | null;
    payments?: ArisanPayment[];
    payouts?: ArisanPayout[];
}

export interface ArisanPayment {
    id: number;
    round_id: number;
    participant_id: number;
    transaction_number: string;
    amount: number;
    payment_date: string;
    status: TransactionStatus;
    created_by: number;
    created_at: string;
    updated_at: string;
    participant?: import('./member.d').ArisanParticipant & { member?: import('./member.d').Member };
}

export interface ArisanPayout {
    id: number;
    round_id: number;
    winner_participant_id: number;
    payout_number: string;
    amount: number;
    payout_date: string;
    status: string;
    created_by: number;
    created_at: string;
    updated_at: string;
    winner?: import('./member.d').ArisanParticipant & { member?: import('./member.d').Member };
}

export interface Account {
    id: number;
    account_code: string;
    account_name: string;
    account_type: 'asset' | 'liability' | 'equity' | 'income' | 'expense';
    is_active: boolean;
}

export interface JournalEntry {
    id: number;
    transaction_number: string;
    transaction_date: string;
    description: string;
    reference_type: string;
    reference_id: number;
    status: 'draft' | 'posted' | 'reversed';
    lines?: JournalLine[];
}

export interface JournalLine {
    id: number;
    journal_entry_id: number;
    account_id: number;
    debit: number;
    credit: number;
    description: string | null;
    account?: Account;
}

export interface OrganizationSetting {
    id: number;
    organization_name: string;
    address: string;
    logo_path: string | null;
    chairman_name: string;
    treasurer_name: string;
    phone: string | null;
    email: string | null;
    arisan_contribution_amount: number;
    loan_service_rate: number;
    receipt_footer: string | null;
}
