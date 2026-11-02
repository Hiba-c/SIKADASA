export type MemberStatus = 'active' | 'inactive';

export interface Member {
    id: number;
    registration_code: string;
    full_name: string;
    address: string;
    house_number: string | null;
    phone: string | null;
    joined_at: string;
    notes: string | null;
    status: MemberStatus;
    inactive_at: string | null;
    inactive_reason: string | null;
    created_by: number | null;
    created_at: string;
    updated_at: string;
    // Relations
    savings_account?: SavingsAccount;
    loans?: Loan[];
    arisan_participants?: ArisanParticipant[];
}

export interface MemberFormData {
    full_name: string;
    address: string;
    house_number: string;
    phone: string;
    joined_at: string;
    notes: string;
}

export interface PaginatedMembers {
    data: Member[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
}

export interface MemberDeactivationCheck {
    savings_balance: string;
    loan_principal: string;
    loan_service: string;
    arisan_contributions: string;
    unpaid_arisan_rounds: number;
    can_deactivate: boolean;
}

export interface MemberSavingsTransaction {
    id: number;
    transaction_number: string;
    type: 'deposit' | 'withdrawal';
    amount: string;
    transaction_date: string;
    created_at: string;
    status: 'posted' | 'reversed';
}

export interface MemberLoanHistory {
    id: number;
    loan_number: string;
    principal_amount: string;
    service_amount: string;
    total_obligation: string;
    status: string;
    created_at: string;
    installments: Array<{
        installment_number: number;
        due_date: string;
        principal_due: string;
        service_due: string;
        principal_paid: string;
        service_paid: string;
        status: string;
    }>;
    payments: Array<{
        payment_number: string;
        payment_date: string;
        total_amount: string;
        created_at: string;
        status: string;
    }>;
}

export interface MemberArisanHistory {
    id: number;
    cycle_name: string;
    has_won: boolean;
    won_at: string | null;
    payments: Array<{
        transaction_number: string;
        amount: string;
        payment_date: string;
        created_at: string;
        round_number: number;
        status: string;
    }>;
}

export interface SavingsAccount {
    id: number;
    member_id: number;
    current_balance: number;
    status: string;
    created_at: string;
    updated_at: string;
    member?: Member;
}

export interface ArisanParticipant {
    id: number;
    cycle_id: number;
    member_id: number;
    has_won: boolean;
    won_at: string | null;
    member?: Member;
}

export interface Loan {
    id: number;
    loan_number: string;
    member_id: number;
    principal_amount: number;
    service_rate: number;
    service_amount: number;
    total_obligation: number;
    installment_count: number;
    status: string;
    member?: Member;
}
