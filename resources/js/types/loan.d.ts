export type LoanStatus = 'pending' | 'approved' | 'rejected' | 'active' | 'paid_off' | 'cancelled';
export type InstallmentStatus = 'unpaid' | 'partial' | 'paid';

export interface Loan {
    id: number;
    loan_number: string;
    member_id: number;
    principal_amount: number;
    service_rate: number;
    service_amount: number;
    total_obligation: number;
    installment_count: number;
    application_date: string;
    approved_at: string | null;
    disbursed_at: string | null;
    status: LoanStatus;
    approved_by: number | null;
    created_by: number;
    created_at: string;
    updated_at: string;
    // Relations
    member?: import('./member.d').Member;
    installments?: LoanInstallment[];
    payments?: LoanPayment[];
}

export interface LoanInstallment {
    id: number;
    loan_id: number;
    installment_number: number;
    due_date: string;
    principal_due: number;
    service_due: number;
    principal_paid: number;
    service_paid: number;
    status: InstallmentStatus;
    created_at: string;
    updated_at: string;
    loan?: Loan;
}

export interface LoanPayment {
    id: number;
    payment_number: string;
    loan_id: number;
    payment_date: string;
    principal_amount: number;
    service_amount: number;
    total_amount: number;
    status: string;
    received_by: number;
    created_at: string;
    updated_at: string;
    loan?: Loan;
}
