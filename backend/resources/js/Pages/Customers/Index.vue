<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCustomers, type Customer } from '@/composables/useCustomers';
import { useAuth } from '@/composables/useAuth';
import { UserPlus, Pencil, Trash2, AlertCircle } from 'lucide-vue-next';

const { customers, loading, error, pagination, fetchCustomers, createCustomer, updateCustomer, deleteCustomer } = useCustomers();
const _auth = useAuth();

const searchQuery = ref('');
const showForm = ref(false);
const editingCustomer = ref<Customer | null>(null);
const formData = ref({
    name: '',
    phone: '',
    email: '',
    location: '',
    member_level: 'bronze',
    loyalty_points: 0,
});

let debounceTimer: ReturnType<typeof setTimeout>;

watch(searchQuery, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applySearch, 300);
});

onMounted(() => {
    fetchCustomers(1);
});

function applySearch() {
    const params: Record<string, string> = {};
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
    fetchCustomers(1, params);
}

function changePage(page: number) {
    const params: Record<string, string> = {};
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
    fetchCustomers(page, params);
}

function openAddForm() {
    editingCustomer.value = null;
    formData.value = { name: '', phone: '', email: '', location: '', member_level: 'bronze', loyalty_points: 0 };
    showForm.value = true;
}

function openEditForm(customer: Customer) {
    editingCustomer.value = customer;
    formData.value = {
        name: customer.name,
        phone: customer.phone,
        email: customer.email || '',
        location: customer.location || '',
        member_level: customer.member_level || 'bronze',
        loyalty_points: customer.loyalty_points || 0,
    };
    showForm.value = true;
}

async function submitForm() {
    try {
        if (editingCustomer.value) {
            await updateCustomer(editingCustomer.value.id, formData.value);
        } else {
            await createCustomer(formData.value);
        }
        showForm.value = false;
        await changePage(pagination.value.current_page);
    } catch {
        // error is already set by the composable
    }
}

async function handleDelete(id: string) {
    if (!window.confirm('Delete this customer?')) return;
    try {
        await deleteCustomer(id);
    } catch {
        // error is already set by the composable
    }
}

function memberLevelBadgeClass(level: Customer['member_level']): string {
    switch (level) {
        case 'bronze':
            return 'bg-warning-light text-amber-800';
        case 'silver':
            return 'bg-surface-alt text-text-secondary';
        case 'gold':
            return 'bg-yellow-100 text-yellow-800';
        case 'platinum':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-surface-alt text-text-secondary';
    }
}
</script>

<template>
    <AppLayout>
        <!-- Page header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-text-theme">Customers</h1>
            <button
                @click="openAddForm"
                class="flex items-center gap-2 px-4 py-2 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors"
            >
                <UserPlus class="w-4 h-4" />
                Add Customer
            </button>
        </div>

        <!-- Search bar -->
        <div class="mb-4">
            <div class="flex items-center gap-3">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search by name, phone, or email..."
                    class="flex-1 px-4 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                    @keydown.enter="applySearch"
                />
                <button
                    @click="applySearch"
                    class="px-4 py-2 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors"
                >
                    Search
                </button>
                <button
                    v-if="searchQuery"
                    @click="searchQuery = ''; applySearch()"
                    class="px-3 py-2 text-sm font-medium text-text-secondary bg-surface-raised border border-border-input rounded-lg hover:bg-surface-alt transition-colors"
                >
                    Clear
                </button>
            </div>
        </div>

        <!-- Inline error alert -->
        <div v-if="error" class="flex items-start gap-3 p-4 mb-6 bg-danger-light border border-danger-theme/20 rounded-lg">
            <AlertCircle class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
            <p class="text-sm text-danger-theme">{{ error }}</p>
        </div>

        <!-- Loading state -->
        <div v-if="loading && customers.length === 0" class="flex items-center justify-center py-16">
            <div class="w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Customers table -->
        <div v-else class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface-alt border-b border-border-theme">
                    <tr>
                        <th class="text-left px-6 py-3 font-semibold text-text-secondary">Name</th>
                        <th class="text-left px-6 py-3 font-semibold text-text-secondary">Phone</th>
                        <th class="text-left px-6 py-3 font-semibold text-text-secondary">Email</th>
                        <th class="text-left px-6 py-3 font-semibold text-text-secondary">Location</th>
                        <th class="text-left px-6 py-3 font-semibold text-text-secondary">Member Level</th>
                        <th class="text-left px-6 py-3 font-semibold text-text-secondary">Points</th>
                        <th class="text-right px-6 py-3 font-semibold text-text-secondary">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="customers.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center text-text-tertiary">No customers found.</td>
                    </tr>
                    <tr v-for="customer in customers" :key="customer.id" @click="router.visit(`/customers/${customer.id}`)" class="hover:bg-surface-alt cursor-pointer transition-colors">
                        <td class="px-6 py-4 font-medium text-primary hover:underline cursor-pointer">
                            {{ customer.name }}
                        </td>
                        <td class="px-6 py-4 text-text-secondary">{{ customer.phone }}</td>
                        <td class="px-6 py-4 text-text-secondary">{{ customer.email || '—' }}</td>
                        <td class="px-6 py-4 text-text-secondary">{{ customer.location || '—' }}</td>
                        <td class="px-6 py-4">
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                :class="memberLevelBadgeClass(customer.member_level)"
                            >
                                {{ customer.member_level }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-text-secondary">{{ customer.loyalty_points.toLocaleString() }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    @click="openEditForm(customer)"
                                    class="p-1.5 text-text-tertiary hover:text-primary hover:bg-primary-light rounded-md transition-colors"
                                    title="Edit customer"
                                >
                                    <Pencil class="w-4 h-4" />
                                </button>
                                <button
                                    @click="handleDelete(customer.id)"
                                    class="p-1.5 text-text-tertiary hover:text-danger-theme hover:bg-danger-light rounded-md transition-colors"
                                    title="Delete customer"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div
                v-if="pagination.last_page > 1"
                class="flex items-center justify-between px-6 py-4 border-t border-border-theme bg-surface-alt"
            >
                <p class="text-sm text-text-tertiary">
                    Page {{ pagination.current_page }} of {{ pagination.last_page }}
                    &mdash; {{ pagination.total }} total
                </p>
                <div class="flex items-center gap-2">
                    <button
                        :disabled="pagination.current_page <= 1"
                        @click="changePage(pagination.current_page - 1)"
                        class="px-3 py-1.5 text-sm font-medium rounded-md border border-border-input text-text-secondary hover:bg-surface-alt disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    >
                        Previous
                    </button>
                    <button
                        :disabled="pagination.current_page >= pagination.last_page"
                        @click="changePage(pagination.current_page + 1)"
                        class="px-3 py-1.5 text-sm font-medium rounded-md border border-border-input text-text-secondary hover:bg-surface-alt disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <!-- Slide-over panel -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div v-if="showForm" class="fixed inset-0 z-40 flex justify-end">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/30" @click="showForm = false" />

                <!-- Panel -->
                <Transition
                    enter-active-class="transition-transform duration-200"
                    leave-active-class="transition-transform duration-200"
                    enter-from-class="translate-x-full"
                    leave-to-class="translate-x-full"
                >
                    <div v-if="showForm" class="relative z-50 w-full max-w-md bg-surface-raised shadow-xl flex flex-col">
                        <!-- Panel header -->
                        <div class="flex items-center justify-between px-6 py-5 border-b border-border-theme">
                            <h2 class="text-lg font-semibold text-text-theme">
                                {{ editingCustomer ? 'Edit Customer' : 'Add Customer' }}
                            </h2>
                            <button
                                @click="showForm = false"
                                class="p-1.5 text-text-tertiary hover:text-text-secondary hover:bg-surface-alt rounded-md transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Form body -->
                        <form @submit.prevent="submitForm" class="flex-1 overflow-y-auto px-6 py-6 space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">
                                    Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="formData.name"
                                    type="text"
                                    required
                                    placeholder="Customer name"
                                    class="w-full px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">
                                    Phone <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="formData.phone"
                                    type="tel"
                                    required
                                    placeholder="+1 234 567 8900"
                                    class="w-full px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Email</label>
                                <input
                                    v-model="formData.email"
                                    type="email"
                                    placeholder="customer@example.com"
                                    class="w-full px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Location</label>
                                <input
                                    v-model="formData.location"
                                    type="text"
                                    placeholder="City, region, or address"
                                    class="w-full px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Member Level</label>
                                <select
                                    v-model="formData.member_level"
                                    class="w-full px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                                >
                                    <option value="bronze">Bronze</option>
                                    <option value="silver">Silver</option>
                                    <option value="gold">Gold</option>
                                    <option value="platinum">Platinum</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Loyalty Points</label>
                                <input
                                    v-model.number="formData.loyalty_points"
                                    type="number"
                                    min="0"
                                    class="w-full px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                                />
                            </div>

                            <!-- Form error -->
                            <div v-if="error" class="flex items-start gap-2 p-3 bg-danger-light border border-danger-theme/20 rounded-lg">
                                <AlertCircle class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
                                <p class="text-xs text-danger-theme">{{ error }}</p>
                            </div>
                        </form>

                        <!-- Panel footer -->
                        <div class="flex items-center gap-3 px-6 py-5 border-t border-border-theme bg-surface-alt">
                            <button
                                type="submit"
                                @click="submitForm"
                                :disabled="loading"
                                class="flex-1 px-4 py-2 text-sm font-medium bg-btn-primary text-white rounded-lg hover:bg-btn-primary-hover disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                <span v-if="loading">Saving…</span>
                                <span v-else>{{ editingCustomer ? 'Save Changes' : 'Add Customer' }}</span>
                            </button>
                            <button
                                type="button"
                                @click="showForm = false"
                                class="flex-1 px-4 py-2 text-sm font-medium text-text-secondary bg-surface-raised border border-border-input rounded-lg hover:bg-surface-alt transition-colors"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </AppLayout>
</template>
