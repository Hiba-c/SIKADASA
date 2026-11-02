import type { UserRole } from '@/types/auth.d';

/**
 * Check if a user has a specific role.
 */
export function hasRole(userRole: UserRole, role: UserRole): boolean {
    return userRole === role;
}

/**
 * Check if a user is Ketua.
 */
export function isKetua(role: UserRole): boolean {
    return role === 'ketua';
}

/**
 * Check if a user is Bendahara.
 */
export function isBendahara(role: UserRole): boolean {
    return role === 'bendahara';
}

/**
 * Check if the user can access a specific feature.
 * Based on the access matrix defined in the PRD.
 */
export function canAccess(role: UserRole, feature: string): boolean {
    const ketuaOnly: string[] = [
        'manage_users',
        'deactivate_member',
        'activate_member',
        'approve_loan',
        'validate_report',
        'audit_log',
        'organization_settings',
        'backup_restore',
    ];

    if (ketuaOnly.includes(feature)) {
        return role === 'ketua';
    }

    // Both roles can access everything else
    return true;
}

/**
 * Get the display name for a role.
 */
export function getRoleLabel(role: UserRole): string {
    const labels: Record<UserRole, string> = {
        ketua: 'Ketua',
        bendahara: 'Bendahara',
    };
    return labels[role] || role;
}
