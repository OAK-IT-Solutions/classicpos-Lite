<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormSlideOver from '@/Components/FormSlideOver.vue'
import { useAdminUsers } from '@/composables/useAdmin'
import { UserCog, Plus, Edit3, Trash2 } from 'lucide-vue-next'

const { adminUsers, loading, fetchAdminUsers, createAdminUser, updateAdminUser, deleteAdminUser } = useAdminUsers()
const showForm = ref(false)
const editingUser = ref<any>(null)
const formLoading = ref(false)
const formError = ref('')
const form = ref({ name: '', email: '', password: '', role: 'admin' as string })
const actionLoading = ref<string | null>(null)

onMounted(() => fetchAdminUsers())

function openCreate() {
  editingUser.value = null
  form.value = { name: '', email: '', password: '', role: 'admin' }
  formError.value = ''
  showForm.value = true
}

function openEdit(user: any) {
  editingUser.value = user
  form.value = { name: user.name, email: user.email, password: '', role: user.role }
  formError.value = ''
  showForm.value = true
}

async function handleSubmit() {
  formLoading.value = true
  formError.value = ''
  try {
    if (editingUser.value) {
      const data: any = { name: form.value.name, email: form.value.email, role: form.value.role }
      if (form.value.password) data.password = form.value.password
      await updateAdminUser(editingUser.value.id, data)
    } else {
      await createAdminUser(form.value)
    }
    showForm.value = false
    fetchAdminUsers()
  } catch (e: any) {
    formError.value = e?.response?.data?.error || e?.response?.data?.message || 'Failed to save user'
  } finally {
    formLoading.value = false
  }
}

async function confirmDelete(id: string, name: string) {
  if (!confirm(`Delete admin user "${name}"?`)) return
  actionLoading.value = id
  try {
    await deleteAdminUser(id)
    fetchAdminUsers()
  } catch (e: any) {
    alert(e?.response?.data?.error || 'Failed to delete user')
  } finally {
    actionLoading.value = null
  }
}

function roleColor(role: string) {
  return { super_admin: 'bg-purple-50 text-purple-700', admin: 'bg-blue-50 text-blue-700', support: 'bg-gray-100 text-gray-600' }[role] || 'bg-gray-100'
}
</script>

<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Admin Users</h1>
        <p class="text-sm text-gray-500 mt-1">Manage platform administrators</p>
      </div>
      <button @click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
        <Plus class="w-4 h-4" /> Add User
      </button>
    </div>

    <div class="bg-white rounded-xl border border-gray-100">
      <div v-if="loading" class="p-8 text-center text-gray-400">Loading...</div>

      <table v-else class="w-full">
        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3">User</th>
            <th class="px-4 py-3">Role</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Last Login</th>
            <th class="px-4 py-3">Created</th>
            <th class="px-4 py-3 w-20"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="u in adminUsers" :key="u.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                  <span class="text-sm font-medium text-blue-700">{{ u.name?.charAt(0) || '?' }}</span>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-900">{{ u.name }}</p>
                  <p class="text-xs text-gray-500">{{ u.email }}</p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3">
              <span :class="['text-xs font-medium px-2 py-1 rounded-full', roleColor(u.role)]">
                {{ u.role === 'super_admin' ? 'Super Admin' : u.role === 'admin' ? 'Admin' : 'Support' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span :class="['text-xs font-medium px-2 py-1 rounded-full', u.is_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-500']">
                {{ u.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ u.last_login_at ? new Date(u.last_login_at).toLocaleString() : 'Never' }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ new Date(u.created_at).toLocaleDateString() }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1">
                <button @click="openEdit(u)" class="p-1.5 hover:bg-gray-100 rounded-lg inline-flex" title="Edit">
                  <Edit3 class="w-4 h-4 text-gray-400" />
                </button>
                <button @click="confirmDelete(u.id, u.name)" :disabled="actionLoading === u.id" class="p-1.5 hover:bg-red-50 rounded-lg inline-flex" title="Delete">
                  <Trash2 class="w-4 h-4 text-red-400" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!adminUsers?.length">
            <td colspan="6" class="px-4 py-12 text-center text-gray-400">No admin users found</td>
          </tr>
        </tbody>
      </table>
    </div>

    <FormSlideOver :title="editingUser ? 'Edit User' : 'Create User'" :visible="showForm" :loading="formLoading" :error="formError" @close="showForm = false" @submit="handleSubmit">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
          <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input v-model="form.email" type="email" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password {{ editingUser ? '(leave blank to keep current)' : '' }}</label>
          <input v-model="form.password" :required="!editingUser" type="password" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
          <select v-model="form.role" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="super_admin">Super Admin</option>
            <option value="admin">Admin</option>
            <option value="support">Support</option>
          </select>
          <p class="text-xs text-gray-400 mt-1">
            <span v-if="form.role === 'super_admin'">Full access to all sections</span>
            <span v-else-if="form.role === 'admin'">Access to most sections except admin user management</span>
            <span v-else>Access only to tickets</span>
          </p>
        </div>
      </div>
    </FormSlideOver>
  </AdminLayout>
</template>
