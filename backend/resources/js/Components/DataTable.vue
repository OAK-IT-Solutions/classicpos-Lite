<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next';

export interface Column {
    key: string;
    label: string;
    sortable?: boolean;
    width?: string;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    columns: Column[];
    items: Record<string, unknown>[];
    loading?: boolean;
    error?: string | null;
    pagination?: PaginationMeta | null;
    emptyMessage?: string;
}>();

const emit = defineEmits<{
    'page-change': [page: number];
}>();

function displayValue(item: Record<string, unknown>, key: string): string {
    const val = item[key];
    if (val === null || val === undefined) return '\u2014';
    return String(val);
}
</script>

<template>
    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
        <!-- Loading -->
        <div v-if="loading && items.length === 0" class="flex items-center justify-center py-16">
            <div class="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="flex items-start gap-3 p-4 m-6 bg-danger-light border border-danger-theme/20 rounded-lg">
            <AlertCircle class="w-5 h-5 text-danger-theme flex-shrink-0 mt-0.5" />
            <p class="text-sm text-danger-theme">{{ error }}</p>
        </div>

        <!-- Empty -->
        <div v-else-if="items.length === 0" class="flex items-center justify-center py-16 text-text-tertiary text-sm">
            {{ emptyMessage || 'No records found.' }}
        </div>

        <!-- Table -->
        <table v-else class="w-full text-sm">
            <thead class="bg-table-header border-b border-table-border">
                <tr>
                    <th
                        v-for="col in columns"
                        :key="col.key"
                        :style="col.width ? { width: col.width } : undefined"
                        class="text-left px-6 py-3 font-semibold text-text-secondary"
                    >
                        {{ col.label }}
                    </th>
                    <th v-if="$slots.actions" class="text-right px-6 py-3 font-semibold text-text-secondary">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-light">
                <tr
                    v-for="(item, idx) in items"
                    :key="(item.id as string) ?? idx"
                    class="hover:bg-table-row-hover transition-colors"
                >
                    <td
                        v-for="col in columns"
                        :key="col.key"
                        class="px-6 py-4 text-text-theme"
                    >
                        <slot :name="`cell-${col.key}`" :item="item" :value="displayValue(item, col.key)">
                            {{ displayValue(item, col.key) }}
                        </slot>
                    </td>
                    <td v-if="$slots.actions" class="px-6 py-4 text-right">
                        <slot name="actions" :item="item" />
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination -->
        <div
            v-if="pagination && pagination.last_page > 1"
            class="flex items-center justify-between px-6 py-4 border-t border-border-theme bg-surface-alt"
        >
            <p class="text-sm text-text-tertiary">
                Page {{ pagination.current_page }} of {{ pagination.last_page }}
                &mdash; {{ pagination.total }} total
            </p>
            <div class="flex items-center gap-2">
                <button
                    :disabled="pagination.current_page <= 1"
                    @click="emit('page-change', pagination.current_page - 1)"
                    class="px-3 py-1.5 text-sm font-medium rounded-md border border-border-input text-text-secondary hover:bg-surface-alt disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    Previous
                </button>
                <button
                    :disabled="pagination.current_page >= pagination.last_page"
                    @click="emit('page-change', pagination.current_page + 1)"
                    class="px-3 py-1.5 text-sm font-medium rounded-md border border-border-input text-text-secondary hover:bg-surface-alt disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>
