import { Config } from 'ziggy-js';

export type UserRole = 'ketua' | 'bendahara';

export interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    phone: string | null;
    role: UserRole;
    is_active: boolean;
    last_login_at: string | null;
    created_at: string;
    updated_at: string;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: User | null;
    };
    ziggy: Config & { location: string };
    flash: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
};
