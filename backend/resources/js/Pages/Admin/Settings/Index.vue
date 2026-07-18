<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useSettings } from '@/composables/useAdmin'
import { Save } from 'lucide-vue-next'

const { settings, loading, fetchSettings, updateSettings } = useSettings()
const editedSettings = ref<any[]>([])
const saving = ref(false)
const saveSuccess = ref(false)
const saveError = ref('')

onMounted(async () => {
  await fetchSettings()
  // Flatten settings into editable array
  for (const [group, items] of Object.entries(settings.value)) {
    for (const item of items as any[]) {
      editedSettings.value.push({ ...item, group })
    }
  }
})

async function save() {
  saving.value = true
  saveSuccess.value = false
  saveError.value = ''
  try {
    await updateSettings(editedSettings.value.map(s => ({ key: s.key, value: s.value })))
    saveSuccess.value = true
    setTimeout(() => { saveSuccess.value = false }, 3000)
  } catch (e: any) {
    saveError.value = e?.response?.data?.message || 'Failed to save settings'
    setTimeout(() => { saveError.value = '' }, 5000)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Platform Settings</h1>
        <p class="text-sm text-gray-500 mt-1">Global platform configuration</p>
      </div>
      <button @click="save" :disabled="saving" class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
        <Save class="w-4 h-4" /> {{ saving ? 'Saving...' : 'Save Settings' }}
      </button>
      <div v-if="saveSuccess" class="text-sm text-green-600 bg-green-50 px-3 py-2 rounded-lg">Settings saved successfully</div>
      <div v-else-if="saveError" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ saveError }}</div>
    </div>

    <div v-if="loading" class="text-center py-20 text-gray-400">Loading...</div>

    <div v-else class="space-y-6">
      <div v-for="(items, group) in settings" :key="group" class="bg-white rounded-xl p-6 border border-gray-100">
        <h3 class="font-semibold text-gray-900 mb-4 capitalize">{{ group }} Settings</h3>
        <div class="space-y-4">
          <div v-for="item in items as any[]" :key="item.key" class="flex items-center justify-between gap-4">
            <div>
              <label class="text-sm font-medium text-gray-700">{{ item.key }}</label>
              <p v-if="item.description" class="text-xs text-gray-400">{{ item.description }}</p>
            </div>
            <input
              v-if="editedSettings.find(s => s.key === item.key)"
              v-model="editedSettings.find(s => s.key === item.key)!.value"
              :type="item.type === 'boolean' ? 'checkbox' : item.type === 'integer' ? 'number' : 'text'"
              :class="['px-3 py-2 border border-gray-200 rounded-lg text-sm w-64', item.type === 'boolean' ? 'w-auto' : '']"
            />
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
