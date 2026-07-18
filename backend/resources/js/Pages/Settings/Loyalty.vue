<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import api from '@/composables/axios';

defineProps<{ embedded?: boolean }>();

interface MemberLevel {
    name: string;
    min_points: number;
}

interface RewardThreshold {
    points: number;
    value: number;
    label: string;
}

interface LoyaltyRule {
    id: string;
    points_per_amount: number;
    points_earned: number;
    signup_bonus_points: number;
    member_levels: MemberLevel[] | null;
    reward_thresholds: RewardThreshold[] | null;
    is_active: boolean;
}

const rule = ref<LoyaltyRule | null>(null);
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const success = ref('');
const rulesExist = ref(false);

const form = ref({
    points_per_amount: 10,
    points_earned: 1,
    signup_bonus_points: 0,
    is_active: true,
});

const memberLevelsStr = ref('');
const rewardThresholdsStr = ref('');

async function fetchRule() {
    loading.value = true;
    error.value = '';
    try {
        const res = await api.get('/loyalty/current');
        rule.value = res.data.data;
        rulesExist.value = !!rule.value;
        if (rule.value) {
            form.value = {
                points_per_amount: rule.value.points_per_amount,
                points_earned: rule.value.points_earned,
                signup_bonus_points: rule.value.signup_bonus_points,
                is_active: rule.value.is_active,
            };
            memberLevelsStr.value = rule.value.member_levels
                ? JSON.stringify(rule.value.member_levels, null, 2)
                : JSON.stringify([
                    { name: 'Bronze', min_points: 0 },
                    { name: 'Silver', min_points: 100 },
                    { name: 'Gold', min_points: 500 },
                    { name: 'Platinum', min_points: 1000 },
                ], null, 2);
            rewardThresholdsStr.value = rule.value.reward_thresholds
                ? JSON.stringify(rule.value.reward_thresholds, null, 2)
                : JSON.stringify([
                    { points: 100, value: 5, label: '$5 off' },
                    { points: 500, value: 30, label: '$30 off' },
                ], null, 2);
        } else {
            form.value = { points_per_amount: 10, points_earned: 1, signup_bonus_points: 0, is_active: true };
            memberLevelsStr.value = JSON.stringify([
                { name: 'Bronze', min_points: 0 },
                { name: 'Silver', min_points: 100 },
                { name: 'Gold', min_points: 500 },
                { name: 'Platinum', min_points: 1000 },
            ], null, 2);
            rewardThresholdsStr.value = JSON.stringify([
                { points: 100, value: 5, label: '$5 off' },
                { points: 500, value: 30, label: '$30 off' },
            ], null, 2);
        }
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to load loyalty settings.';
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = '';
    success.value = '';
    try {
        let memberLevels: MemberLevel[] = [];
        let rewardThresholds: RewardThreshold[] = [];
        try { memberLevels = JSON.parse(memberLevelsStr.value); } catch { memberLevels = []; }
        try { rewardThresholds = JSON.parse(rewardThresholdsStr.value); } catch { rewardThresholds = []; }

        const payload = {
            ...form.value,
            member_levels: memberLevels,
            reward_thresholds: rewardThresholds,
        };

        if (rulesExist.value && rule.value) {
            await api.put(`/loyalty/${rule.value.id}`, payload);
        } else {
            await api.post('/loyalty', payload);
        }
        success.value = 'Loyalty rules saved successfully.';
        await fetchRule();
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || err.message || 'Failed to save loyalty rules.';
    } finally {
        saving.value = false;
    }
}

onMounted(fetchRule);
</script>

<template>
    <component :is="embedded ? 'div' : AppLayout" :class="embedded ? 'p-4' : ''">
        <SettingsNav v-if="!embedded" />
            <div v-if="error && !loading" class="mb-4 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">{{ error }}</div>
            <div v-if="success" class="mb-4 p-3 bg-success-light border border-success-theme/20 rounded-lg text-sm text-success-theme">{{ success }}</div>
            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>
            <form v-else @submit.prevent="save" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6 space-y-6">
                <h2 class="text-lg font-semibold text-text-theme">Earning Rules</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Spend Amount ($)</label>
                        <input v-model.number="form.points_per_amount" required type="number" min="0.01" step="0.01" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring">
                        <p class="text-xs text-text-tertiary mt-1">Customer earns points per this amount spent</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Points Earned</label>
                        <input v-model.number="form.points_earned" required type="number" min="1" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring">
                        <p class="text-xs text-text-tertiary mt-1">Points awarded per threshold amount</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Signup Bonus Points</label>
                        <input v-model.number="form.signup_bonus_points" type="number" min="0" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring">
                        <p class="text-xs text-text-tertiary mt-1">Points awarded on customer registration</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-border-input">
                    <label class="text-sm font-medium text-text-secondary">Program Active</label>
                </div>

                <hr class="border-border-theme">

                <h2 class="text-lg font-semibold text-text-theme">Member Levels (JSON)</h2>
                <p class="text-sm text-text-tertiary">Define member tiers with minimum points thresholds.</p>
                <textarea v-model="memberLevelsStr" rows="6" class="w-full font-mono text-sm border border-border-input rounded-lg px-3 py-2.5 outline-none focus:ring-2 focus:ring-primary-ring"></textarea>

                <hr class="border-border-theme">

                <h2 class="text-lg font-semibold text-text-theme">Reward Thresholds (JSON)</h2>
                <p class="text-sm text-text-tertiary">Define how many points can be redeemed and the reward value.</p>
                <textarea v-model="rewardThresholdsStr" rows="6" class="w-full font-mono text-sm border border-border-input rounded-lg px-3 py-2.5 outline-none focus:ring-2 focus:ring-primary-ring"></textarea>

                <div class="flex justify-end pt-2">
                    <button type="submit" :disabled="saving" class="px-6 py-2.5 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50 transition-colors">
                        {{ saving ? 'Saving...' : 'Save Loyalty Rules' }}
                    </button>
                </div>
            </form>
    </component>
</template>
