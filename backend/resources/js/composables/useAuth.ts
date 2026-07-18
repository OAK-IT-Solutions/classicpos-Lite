import { ref, computed } from 'vue';
import api from './axios';

interface BranchInfo {
    id: string;
    name: string;
    location?: string;
    business_type?: string;
    timezone?: string;
}

interface User {
    id: string;
    name: string;
    email: string;
    avatar_url?: string | null;
    branch_id: string | null;
    branch?: BranchInfo | null;
    assigned_branches?: BranchInfo[];
    roles?: string[];
    permissions?: string[];
    onboarding_completed?: boolean;
}

const user = ref<User | null>(null);
const loading = ref(false);
const activeBranch = ref<BranchInfo | null>(null);

export function useAuth() {
    const isAuthenticated = computed(() => !!user.value);

    function setActiveBranch(branch: BranchInfo | null) {
        activeBranch.value = branch;
        if (branch) {
            localStorage.setItem('active_branch', JSON.stringify(branch));
        } else {
            localStorage.removeItem('active_branch');
        }
    }

    function getAssignedBranches(): BranchInfo[] {
        return user.value?.assigned_branches || [];
    }

    function needsBranchSelection(): boolean {
        const branches = getAssignedBranches();
        return branches.length > 1;
    }

    function autoSelectBranch(): BranchInfo | null {
        const branches = getAssignedBranches();
        if (branches.length === 1) {
            setActiveBranch(branches[0]);
            return branches[0];
        }
        return null;
    }

    async function login(email: string, password: string): Promise<{ token: string; user: User }> {
        const response = await api.post('/auth/login', { email, password });
        const data = response.data;

        localStorage.setItem('auth_token', data.token);
        localStorage.setItem('auth_user', JSON.stringify(data.user));
        user.value = data.user;

        const restored = restoreActiveBranch();
        if (!restored) {
            autoSelectBranch();
        }

        return data;
    }

    async function register(payload: {
        name: string;
        email: string;
        password: string;
        business_name: string;
        business_type: string;
        location: string;
        timezone: string;
        currency: string;
        country?: string;
        plan?: string;
        billing_cycle?: string;
        referral_code?: string;
    }): Promise<{ token: string; user: User }> {
        const response = await api.post('/auth/register', payload);
        const data = response.data;

        localStorage.setItem('auth_token', data.token);
        localStorage.setItem('auth_user', JSON.stringify(data.user));
        user.value = data.user;

        autoSelectBranch();

        return data;
    }

    async function check(): Promise<boolean> {
        const token = localStorage.getItem('auth_token');
        if (!token) {
            user.value = null;
            return false;
        }

        try {
            const response = await api.get('/auth/me');
            user.value = response.data.user;
            localStorage.setItem('auth_user', JSON.stringify(response.data.user));

            const restored = restoreActiveBranch();
            if (!restored) {
                autoSelectBranch();
            }

            return true;
        } catch {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            localStorage.removeItem('active_branch');
            user.value = null;
            activeBranch.value = null;
            return false;
        }
    }

    async function logout(): Promise<void> {
        try {
            await api.post('/auth/logout');
        } catch {
            // Ignore logout errors
        }

        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
        localStorage.removeItem('active_branch');
        user.value = null;
        activeBranch.value = null;
    }

    function restore() {
        const stored = localStorage.getItem('auth_user');
        if (stored) {
            try {
                user.value = JSON.parse(stored);
            } catch {
                user.value = null;
            }
        }
        restoreActiveBranch();
    }

    function restoreActiveBranch(): boolean {
        const stored = localStorage.getItem('active_branch');
        if (stored) {
            try {
                const parsed = JSON.parse(stored) as BranchInfo;
                const assigned = getAssignedBranches();
                const stillAssigned = assigned.some(b => b.id === parsed.id);
                if (stillAssigned) {
                    activeBranch.value = parsed;
                    return true;
                }
            } catch {
                // Invalid stored data
            }
        }
        return false;
    }

    return {
        user,
        loading,
        isAuthenticated,
        activeBranch,
        setActiveBranch,
        getAssignedBranches,
        needsBranchSelection,
        autoSelectBranch,
        login,
        register,
        check,
        logout,
        restore,
    };
}
