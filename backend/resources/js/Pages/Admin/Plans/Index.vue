<script setup lang="ts">
import { onMounted, ref, watch, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormSlideOver from '@/Components/FormSlideOver.vue'
import { usePlans } from '@/composables/useAdmin'
import { Plus, Edit2, Trash2, Users, Percent, Star } from 'lucide-vue-next'

const { plans, loading, fetchPlans, createPlan, updatePlan, deletePlan } = usePlans()
const allFeatures = ref<any[]>([])
const showForm = ref(false)
const editingPlan = ref<any>(null)
const formLoading = ref(false)
const formError = ref('')
const form = ref({
  name: '', slug: '', description: '',
  price_monthly: 0, price_yearly: 0, discount_percent_yearly: null as number | null,
  max_branches: 1, max_users_per_branch: 3, max_devices_per_branch: 2,
  features: [] as string[], feature_ids: [] as string[],
  is_active: true, is_default: false, is_popular: false,
  highlight_color: '', cta_text: '',
  sort_order: 0,
})
const featureInput = ref('')
const editSlug = ref(false)

const MAX_BIGINT = 9223372036854775807

onMounted(async () => {
  await fetchPlans()
  await fetchAllFeatures()
})

async function fetchAllFeatures() {
  try {
    const token = localStorage.getItem('admin_token')
    const res = await fetch('/api/v1/admin/features', { headers: { Authorization: `Bearer ${token}` } })
    allFeatures.value = await res.json()
  } catch { allFeatures.value = [] }
}

watch(() => form.value.name, (name) => {
  if (!editSlug.value && !editingPlan.value) {
    form.value.slug = name.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '')
  }
})

const availableFeatures = computed(() =>
  allFeatures.value.filter((f: any) => !form.value.feature_ids.includes(f.id))
)

const selectedFeatures = computed(() =>
  allFeatures.value.filter((f: any) => form.value.feature_ids.includes(f.id))
)

function openCreate() {
  editingPlan.value = null
  form.value = { name: '', slug: '', description: '', price_monthly: 0, price_yearly: 0, discount_percent_yearly: null, max_branches: 1, max_users_per_branch: 3, max_devices_per_branch: 2, features: [], feature_ids: [], is_active: true, is_default: false, is_popular: false, highlight_color: '', cta_text: '', sort_order: 0 }
  editSlug.value = false
  featureInput.value = ''
  formError.value = ''
  showForm.value = true
}

function openEdit(plan: any) {
  editingPlan.value = plan
  form.value = {
    name: plan.name, slug: plan.slug, description: plan.description || '',
    price_monthly: plan.price_monthly, price_yearly: plan.price_yearly,
    discount_percent_yearly: plan.discount_percent_yearly,
    max_branches: plan.max_branches, max_users_per_branch: plan.max_users_per_branch,
    max_devices_per_branch: plan.max_devices_per_branch,
    features: [...(plan.features || [])],
    feature_ids: plan.features?.map((f: any) => f.id || f) || [],
    is_active: plan.is_active, is_default: plan.is_default, is_popular: plan.is_popular || false,
    highlight_color: plan.highlight_color || '', cta_text: plan.cta_text || '',
    sort_order: plan.sort_order || 0,
  }
  editSlug.value = true
  featureInput.value = ''
  formError.value = ''
  showForm.value = true
}

function addFeatureTag() {
  const f = featureInput.value.trim()
  if (f && !form.value.features.includes(f)) { form.value.features.push(f) }
  featureInput.value = ''
}

function removeFeatureTag(idx: number) {
  form.value.features.splice(idx, 1)
}

function toggleFeatureAssignment(featureId: string) {
  const idx = form.value.feature_ids.indexOf(featureId)
  if (idx >= 0) {
    form.value.feature_ids.splice(idx, 1)
  } else {
    form.value.feature_ids.push(featureId)
  }
}

async function save() {
  formLoading.value = true
  formError.value = ''
  try {
    if (editingPlan.value) {
      await updatePlan(editingPlan.value.id, form.value)
    } else {
      await createPlan(form.value)
    }
    showForm.value = false
    fetchPlans()
  } catch (e: any) {
    formError.value = e?.response?.data?.message || e?.response?.data?.error || 'Failed to save plan'
  } finally {
    formLoading.value = false
  }
}

async function handleDelete(id: string) {
  if (!confirm('Delete this plan? This cannot be undone if there are no active subscriptions.')) return
  try {
    await deletePlan(id)
    fetchPlans()
  } catch (e: any) {
    alert(e?.response?.data?.error || 'Failed to delete plan')
  }
}

function formatPrice(amount: number) {
  return amount === 0 ? 'Free' : `$${amount.toLocaleString()}`
}

function showLimit(val: number) {
  return val >= MAX_BIGINT ? 'Unlimited' : val
}

function getSavingsPercent(plan: any) {
  if (!plan.price_monthly || !plan.price_yearly) return 0
  return Math.round((1 - plan.price_yearly / (plan.price_monthly * 12)) * 100)
}

const SAVING_COLORS = ['blue-600', 'purple-600', 'green-600', 'orange-600']
</script>

<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Subscription Plans</h1>
        <p class="text-sm text-gray-500 mt-1">Manage pricing plans for tenants</p>
      </div>
      <button @click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
        <Plus class="w-4 h-4" /> Add Plan
      </button>
    </div>

    <div v-if="loading" class="text-center py-20 text-gray-400">Loading...</div>

    <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="plan in plans" :key="plan.id" :class="['bg-white rounded-xl p-6 border relative', plan.is_default ? 'border-blue-200 ring-1 ring-blue-100' : 'border-gray-100']">
        <div v-if="plan.is_popular" class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-xs font-bold px-4 py-1 rounded-full">
          Most Popular
        </div>

        <div class="flex items-start justify-between mb-4">
          <div>
            <h3 class="font-semibold text-gray-900 flex items-center gap-2">
              {{ plan.name }}
              <span v-if="plan.is_default" class="text-[10px] font-medium text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">Default</span>
              <span v-if="plan.is_popular" class="text-[10px] font-medium text-orange-600 bg-orange-50 px-1.5 py-0.5 rounded flex items-center gap-0.5"><Star class="w-3 h-3" /> Popular</span>
            </h3>
            <span class="text-xs text-gray-500">{{ plan.slug }}</span>
          </div>
          <div class="flex items-center gap-1">
            <button @click="openEdit(plan)" class="p-1.5 hover:bg-gray-100 rounded-lg"><Edit2 class="w-4 h-4 text-gray-400" /></button>
            <button @click="handleDelete(plan.id)" class="p-1.5 hover:bg-red-50 rounded-lg"><Trash2 class="w-4 h-4 text-red-400" /></button>
          </div>
        </div>

        <div class="mb-2">
          <span class="text-3xl font-bold">{{ formatPrice(plan.price_monthly) }}</span>
          <span v-if="plan.price_monthly > 0" class="text-sm text-gray-500">/mo</span>
        </div>

        <div v-if="plan.price_yearly > 0 && getSavingsPercent(plan) > 0" class="mb-3">
          <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-0.5 rounded-full">
            Save {{ getSavingsPercent(plan) }}% annually
          </span>
        </div>

        <p class="text-sm text-gray-500 mb-4">{{ plan.description || 'No description' }}</p>

        <div class="text-sm space-y-1.5 mb-4">
          <div class="flex justify-between"><span class="text-gray-500">Branches</span><span class="font-medium">{{ showLimit(plan.max_branches) }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">Users/Branch</span><span class="font-medium">{{ showLimit(plan.max_users_per_branch) }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">Devices/Branch</span><span class="font-medium">{{ showLimit(plan.max_devices_per_branch) }}</span></div>
        </div>

        <div v-if="plan.features?.length" class="pt-4 border-t border-gray-100">
          <div class="flex flex-wrap gap-1">
            <span v-for="f in plan.features.slice(0, 4)" :key="typeof f === 'string' ? f : f.id" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ typeof f === 'string' ? f.replace(/_/g, ' ') : f.name }}</span>
            <span v-if="plan.features.length > 4" class="text-xs text-gray-400">+{{ plan.features.length - 4 }} more</span>
          </div>
        </div>

        <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between">
          <Link :href="`/admin/subscriptions?plan_id=${plan.id}`" class="text-xs text-blue-600 hover:text-blue-700 inline-flex items-center gap-1">
            <Users class="w-3 h-3" /> {{ plan.subscriptions_count ?? 0 }} subscriptions
          </Link>
        </div>
      </div>
    </div>

    <FormSlideOver :title="editingPlan ? 'Edit Plan' : 'Create Plan'" :visible="showForm" :loading="formLoading" :error="formError" @close="showForm = false" @submit="save">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
          <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Slug
            <button @click="editSlug = !editSlug" type="button" class="text-xs text-blue-600 ml-1">{{ editSlug ? 'auto' : 'edit' }}</button>
          </label>
          <input v-model="form.slug" :disabled="!editSlug" required :class="['w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500', !editSlug && 'bg-gray-50 text-gray-400']" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
          <textarea v-model="form.description" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" rows="2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Price Monthly ($)</label>
            <input v-model.number="form.price_monthly" type="number" min="0" step="0.01" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Price Yearly ($)</label>
            <input v-model.number="form.price_yearly" type="number" min="0" step="0.01" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            <Percent class="w-3.5 h-3.5 inline" /> Yearly Discount (%) <span class="text-gray-400 font-normal">— additional savings on top of yearly price</span>
          </label>
          <input v-model.number="form.discount_percent_yearly" type="number" min="0" max="100" step="1" placeholder="e.g. 20 for 20% off yearly" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Max Branches</label>
            <input v-model.number="form.max_branches" type="number" min="-1" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Max Users</label>
            <input v-model.number="form.max_users_per_branch" type="number" min="-1" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Max Devices</label>
            <input v-model.number="form.max_devices_per_branch" type="number" min="-1" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Feature Tags <span class="text-gray-400 font-normal">(legacy text tags)</span></label>
          <div class="flex gap-2 mb-2">
            <input v-model="featureInput" @keydown.enter.prevent="addFeatureTag" placeholder="Add text feature..." class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <button @click="addFeatureTag" type="button" class="px-3 py-2 text-sm font-medium text-white bg-gray-600 rounded-lg hover:bg-gray-700">Add</button>
          </div>
          <div class="flex flex-wrap gap-1">
            <span v-for="(f, i) in form.features" :key="i" class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
              {{ f.replace(/_/g, ' ') }}
              <button @click="removeFeatureTag(i)" type="button" class="text-gray-400 hover:text-red-500">&times;</button>
            </span>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Features <span class="text-gray-400 font-normal">(from feature library)</span></label>
          <div v-if="!allFeatures.length" class="text-xs text-gray-400">No features created yet. <Link href="/admin/features" class="text-blue-600">Manage features</Link></div>
          <div v-else class="flex flex-wrap gap-2 max-h-32 overflow-y-auto p-3 border border-gray-200 rounded-lg">
            <button
              v-for="f in allFeatures"
              :key="f.id"
              @click="toggleFeatureAssignment(f.id)"
              type="button"
              :class="['text-xs px-2.5 py-1 rounded-full border transition-colors', form.feature_ids.includes(f.id) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:border-blue-300']"
            >
              {{ f.name }}
            </button>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Highlight Color</label>
            <select v-model="form.highlight_color" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">None</option>
              <option value="blue">Blue</option>
              <option value="purple">Purple</option>
              <option value="green">Green</option>
              <option value="orange">Orange</option>
              <option value="red">Red</option>
              <option value="gray">Gray</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">CTA Button Text</label>
            <input v-model="form.cta_text" placeholder="e.g. Get Started" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>

        <div class="flex items-center gap-6">
          <label class="flex items-center gap-2">
            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
            <span class="text-sm font-medium text-gray-700">Active</span>
          </label>
          <label class="flex items-center gap-2">
            <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
            <span class="text-sm font-medium text-gray-700">Default Plan</span>
          </label>
          <label class="flex items-center gap-2">
            <input v-model="form.is_popular" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
            <span class="text-sm font-medium text-gray-700">Most Popular</span>
          </label>
        </div>
      </div>
    </FormSlideOver>
  </AdminLayout>
</template>
