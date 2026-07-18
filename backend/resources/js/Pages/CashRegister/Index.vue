<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import api from '@/composables/axios'
import { useAuth } from '@/composables/useAuth'

const auth = useAuth()
const shifts = ref<any[]>([])
const openShift = ref<any>(null)
const selectedShift = ref<any>(null)
const loading = ref(true)
const filterUser = ref('')

const summary = computed(() => {
    const closed = shifts.value.filter(s => s.status === 'closed')
    return {
        total_shifts: closed.length,
        total_revenue: closed.reduce((sum: number, s: any) => sum + Number(s.revenue_to_bank || 0), 0),
        total_variance: closed.reduce((sum: number, s: any) => sum + Number(s.variance || 0), 0),
        today_shifts: closed.filter(s => {
            const d = new Date(s.closed_at || s.created_at)
            const today = new Date()
            return d.toDateString() === today.toDateString()
        }).length,
    }
})

const filteredShifts = computed(() => {
    if (!filterUser.value) return shifts.value
    return shifts.value.filter((s: any) => s.user?.id === filterUser.value)
})

const users = computed(() => {
    const map = new Map<string, string>()
    shifts.value.forEach((s: any) => { if (s.user?.id) map.set(s.user.id, s.user.name) })
    return Array.from(map.entries()).map(([id, name]) => ({ id, name }))
})

function formatCurrency(v: number): string { return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v) }
function formatDate(d: string): string { return new Date(d).toLocaleString() }

async function fetchData() {
    loading.value = true
    try {
        const [statusRes, shiftsRes] = await Promise.all([
            api.get('/cash-register/status').catch(() => ({ data: { data: null } })),
            api.get('/cash-register/shifts').catch(() => ({ data: { data: [] } })),
        ])
        openShift.value = statusRes.data?.data ?? null
        shifts.value = shiftsRes.data?.data ?? []
        if (openShift.value) selectedShift.value = openShift.value
        else if (shifts.value.length) selectedShift.value = shifts.value[0]
    } finally { loading.value = false }
}

onMounted(fetchData)
</script>

<template>
    <AppLayout>
        <div class="flex gap-4">
            <div class="w-72 flex-shrink-0 space-y-3">
                <h1 class="text-lg font-bold text-text-theme">Cash Register</h1>
                <p class="text-xs text-text-tertiary">All shifts for daily audit & reconciliation</p>

                <!-- Summary cards -->
                <div class="grid grid-cols-2 gap-2 text-xs bg-surface-raised rounded-xl p-3 border border-border-theme">
                    <div><p class="text-text-tertiary">Shifts</p><p class="font-bold text-text-theme">{{ summary.total_shifts }}</p></div>
                    <div><p class="text-text-tertiary">Today</p><p class="font-bold text-text-theme">{{ summary.today_shifts }}</p></div>
                    <div><p class="text-text-tertiary">Revenue</p><p class="font-bold text-text-theme">{{ formatCurrency(summary.total_revenue) }}</p></div>
                    <div><p class="text-text-tertiary">Variance</p><p class="font-bold" :class="summary.total_variance === 0 ? 'text-green-600' : 'text-red-600'">{{ formatCurrency(summary.total_variance) }}</p></div>
                </div>

                <!-- Filter by user -->
                <select v-model="filterUser" class="w-full px-2 py-1.5 text-xs border border-border-input rounded-lg">
                    <option value="">All users</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>

                <div v-if="loading" class="text-center py-8 text-text-tertiary text-sm">Loading...</div>
                <div v-else class="space-y-1 overflow-y-auto" style="max-height: calc(100vh - 380px);">
                    <button v-for="s in filteredShifts" :key="s.id" @click="selectedShift = s"
                        class="w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors"
                        :class="selectedShift?.id === s.id ? 'bg-primary text-white' : 'hover:bg-surface-alt text-text-theme'">
                        <p class="font-medium truncate">{{ s.user?.name || 'Unknown' }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs px-1.5 py-0.5 rounded font-medium" :class="s.status === 'open' ? 'bg-green-100 text-green-800' : 'bg-surface-alt text-text-tertiary'">{{ s.status }}</span>
                            <span class="text-xs opacity-80">{{ formatCurrency(s.revenue_to_bank || 0) }}</span>
                        </div>
                    </button>
                    <p v-if="!filteredShifts.length" class="text-center py-8 text-text-tertiary text-sm">No shifts found.</p>
                </div>
            </div>

            <div v-if="selectedShift" class="flex-1 space-y-4">
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6 space-y-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-xl font-bold text-text-theme">Shift: {{ selectedShift.user?.name || 'Unknown' }}</h2>
                                <span class="text-xs px-2 py-0.5 rounded font-medium" :class="selectedShift.status === 'open' ? 'bg-green-100 text-green-800' : 'bg-surface-alt text-text-tertiary'">{{ selectedShift.status }}</span>
                            </div>
                            <p class="text-sm text-text-tertiary mt-1">{{ formatDate(selectedShift.opened_at) }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Opening</p><p class="text-lg font-bold text-text-theme mt-1">{{ formatCurrency(selectedShift.opening_balance) }}</p></div>
                        <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Cash Sales</p><p class="text-lg font-bold text-text-theme mt-1">{{ formatCurrency(selectedShift.cash_sales) }}</p></div>
                        <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Expected</p><p class="text-lg font-bold text-text-theme mt-1">{{ formatCurrency(selectedShift.expected_balance) }}</p></div>
                        <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Actual</p><p class="text-lg font-bold" :class="selectedShift.actual_balance !== null ? 'text-green-600' : 'text-text-theme'">{{ selectedShift.actual_balance !== null ? formatCurrency(selectedShift.actual_balance) : '—' }}</p></div>
                    </div>

                    <div v-if="selectedShift.status === 'closed'" class="grid grid-cols-2 gap-4">
                        <div class="bg-surface-alt rounded-xl p-4 border-2" :class="Number(selectedShift.variance) === 0 ? 'border-green-500' : Number(selectedShift.variance) !== 0 ? 'border-red-500' : ''">
                            <p class="text-xs text-text-tertiary">Variance</p>
                            <p class="text-lg font-bold" :class="Number(selectedShift.variance) === 0 ? 'text-green-600' : 'text-red-600'">
                                {{ selectedShift.variance !== null ? formatCurrency(selectedShift.variance) : '—' }}
                                <span v-if="Number(selectedShift.variance) === 0" class="text-sm ml-1">✓</span>
                            </p>
                        </div>
                        <div class="bg-surface-alt rounded-xl p-4 border-2 border-blue-500">
                            <p class="text-xs text-text-tertiary">Revenue to Bank</p>
                            <p class="text-lg font-bold text-blue-600">{{ formatCurrency(selectedShift.revenue_to_bank) }}</p>
                            <p class="text-xs text-text-tertiary mt-1">Net after opening balance</p>
                        </div>
                    </div>

                    <div v-if="selectedShift.status === 'closed' && selectedShift.closed_at" class="grid grid-cols-2 gap-4">
                        <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Opened</p><p class="text-sm font-semibold text-text-theme mt-1">{{ formatDate(selectedShift.opened_at) }}</p></div>
                        <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Closed</p><p class="text-sm font-semibold text-text-theme mt-1">{{ formatDate(selectedShift.closed_at) }}</p></div>
                    </div>

                    <div v-if="selectedShift.notes" class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Notes</p><p class="text-sm text-text-theme mt-1">{{ selectedShift.notes }}</p></div>
                </div>
            </div>
            <div v-else class="flex-1 flex items-center justify-center text-text-tertiary text-sm bg-surface-raised rounded-xl border border-border-theme">No shift data. Open a register from the POS.</div>
        </div>
    </AppLayout>
</template>
