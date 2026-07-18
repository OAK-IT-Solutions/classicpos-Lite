<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import DataTable from '@/Components/DataTable.vue';
import FormSlideOver from '@/Components/FormSlideOver.vue';
import { useCrud } from '@/composables/useCrud';
import { useAuth } from '@/composables/useAuth';
import { Plus, Pencil } from 'lucide-vue-next';

interface Warehouse {
    id: string;
    branch_id: string;
    name: string;
    location: string | null;
    is_active: boolean;
    branch?: { id: string; name: string };
}

defineProps<{ embedded?: boolean }>();

const auth = useAuth();
const { items, loading, error, pagination, fetchAll, create, update } = useCrud<Warehouse>('/warehouses');

const showForm = ref(false);
const editingItem = ref<Warehouse | null>(null);
const form = ref({ branch_id: '', name: '', location: '' });

onMounted(() => fetchAll());

function openAdd() {
    editingItem.value = null;
    form.value = { branch_id: auth.user?.branch_id || '', name: '', location: '' };
    showForm.value = true;
}

function openEdit(item: Warehouse) {
    editingItem.value = item;
    form.value = { branch_id: item.branch_id, name: item.name, location: item.location || '' };
    showForm.value = true;
}

async function submit() {
    if (editingItem.value) {
        await update(editingItem.value.id, form.value);
    } else {
        await create(form.value);
    }
    showForm.value = false;
    await fetchAll(pagination.value.current_page);
}

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'location', label: 'Location' },
    { key: 'is_active', label: 'Active' },
];
</script>

<template>
    <component :is="embedded ? 'div' : AppLayout" :class="embedded ? 'p-4' : ''">
        <SettingsNav v-if="!embedded" />

        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-text-theme">Warehouses</h2>
            <button @click="openAdd" class="flex items-center gap-2 px-4 py-2 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors">
                <Plus class="w-4 h-4" />
                Add Warehouse
            </button>
        </div>

        <DataTable
            :columns="columns"
            :items="items as unknown as Record<string, unknown>[]"
            :loading="loading"
            :error="error"
            :pagination="pagination"
            @page-change="(p: number) => fetchAll(p)"
        >
            <template #cell-is_active="{ item }">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="item.is_active ? 'bg-success-light text-success-theme' : 'bg-surface-alt text-text-tertiary'">
                    {{ item.is_active ? 'Active' : 'Inactive' }}
                </span>
            </template>
            <template #actions="{ item }">
                <button @click="openEdit(item as unknown as Warehouse)" class="p-1.5 text-text-tertiary hover:text-primary hover:bg-primary-light rounded-md transition-colors" title="Edit">
                    <Pencil class="w-4 h-4" />
                </button>
            </template>
        </DataTable>

        <FormSlideOver :title="editingItem ? 'Edit Warehouse' : 'Add Warehouse'" :visible="showForm" :loading="loading" :error="error" @close="showForm = false" @submit="submit">
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Name <span class="text-danger-theme">*</span></label>
                <input v-model="form.name" type="text" required placeholder="Main Warehouse" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent" />
            </div>
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Location</label>
                <input v-model="form.location" type="text" placeholder="Floor 1, Building A" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent" />
            </div>
        </FormSlideOver>
    </component>
</template>
