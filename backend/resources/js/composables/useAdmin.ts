import { ref } from 'vue'
import api from './axios'

// Tenant management
export function useTenants() {
  const tenants = ref([])
  const loading = ref(false)

  async function fetchTenants(params = {}) {
    loading.value = true
    try {
      const res = await api.get('/admin/tenants', { params })
      tenants.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function fetchTenant(id) {
    const res = await api.get(`/admin/tenants/${id}`)
    return res.data
  }

  async function createTenant(data) {
    const res = await api.post('/admin/tenants', data)
    return res.data
  }

  async function updateTenant(id, data) {
    const res = await api.put(`/admin/tenants/${id}`, data)
    return res.data
  }

  async function suspendTenant(id, reason) {
    const res = await api.post(`/admin/tenants/${id}/suspend`, { reason })
    return res.data
  }

  async function activateTenant(id) {
    const res = await api.post(`/admin/tenants/${id}/activate`)
    return res.data
  }

  async function impersonateTenant(id) {
    const res = await api.post(`/admin/tenants/${id}/impersonate`)
    return res.data
  }

  return { tenants, loading, fetchTenants, fetchTenant, createTenant, updateTenant, suspendTenant, activateTenant, impersonateTenant }
}

// Plans management
export function usePlans() {
  const plans = ref([])
  const loading = ref(false)

  async function fetchPlans() {
    loading.value = true
    try {
      const res = await api.get('/admin/plans')
      plans.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function createPlan(data) {
    const res = await api.post('/admin/plans', data)
    return res.data
  }

  async function updatePlan(id, data) {
    const res = await api.put(`/admin/plans/${id}`, data)
    return res.data
  }

  async function deletePlan(id) {
    await api.delete(`/admin/plans/${id}`)
  }

  return { plans, loading, fetchPlans, createPlan, updatePlan, deletePlan }
}

// Revenue analytics
export function useRevenue() {
  const dashboard = ref(null)
  const loading = ref(false)

  async function fetchDashboard() {
    loading.value = true
    try {
      const res = await api.get('/admin/dashboard')
      dashboard.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function fetchMRR() {
    const res = await api.get('/admin/revenue/mrr')
    return res.data
  }

  async function fetchARR() {
    const res = await api.get('/admin/revenue/arr')
    return res.data
  }

  async function fetchChurn() {
    const res = await api.get('/admin/revenue/churn')
    return res.data
  }

  async function fetchTrend(months = 12) {
    const res = await api.get('/admin/revenue/trend', { params: { months } })
    return res.data
  }

  return { dashboard, loading, fetchDashboard, fetchMRR, fetchARR, fetchChurn, fetchTrend }
}

// Agents management
export function useAgents() {
  const agents = ref([])
  const loading = ref(false)

  async function fetchAgents(params = {}) {
    loading.value = true
    try {
      const res = await api.get('/admin/agents', { params })
      agents.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function fetchAgent(id) {
    const res = await api.get(`/admin/agents/${id}`)
    return res.data
  }

  async function createAgent(data) {
    const res = await api.post('/admin/agents', data)
    return res.data
  }

  async function updateAgent(id, data) {
    const res = await api.put(`/admin/agents/${id}`, data)
    return res.data
  }

  async function deleteAgent(id) {
    await api.delete(`/admin/agents/${id}`)
  }

  async function fetchPerformance(id, months = 6) {
    const res = await api.get(`/admin/agents/${id}/performance`, { params: { months } })
    return res.data
  }

  return { agents, loading, fetchAgents, fetchAgent, createAgent, updateAgent, deleteAgent, fetchPerformance }
}

// Commissions
export function useCommissions() {
  const commissions = ref([])
  const loading = ref(false)

  async function fetchCommissions(params = {}) {
    loading.value = true
    try {
      const res = await api.get('/admin/commissions', { params })
      commissions.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function approveCommission(id) {
    const res = await api.post(`/admin/commissions/${id}/approve`)
    return res.data
  }

  async function payCommission(id, data) {
    const res = await api.post(`/admin/commissions/${id}/pay`, data)
    return res.data
  }

  async function fetchSummary() {
    const res = await api.get('/admin/commissions/summary')
    return res.data
  }

  return { commissions, loading, fetchCommissions, approveCommission, payCommission, fetchSummary }
}

// Support tickets
export function useTickets() {
  const tickets = ref([])
  const loading = ref(false)

  async function fetchTickets(params = {}) {
    loading.value = true
    try {
      const res = await api.get('/admin/tickets', { params })
      tickets.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function fetchTicket(id) {
    const res = await api.get(`/admin/tickets/${id}`)
    return res.data
  }

  async function replyToTicket(id, message, attachments?) {
    if (attachments?.length) {
      const fd = new FormData()
      fd.append('message', message)
      attachments.forEach((f: File) => fd.append('attachments[]', f))
      const res = await api.post(`/admin/tickets/${id}/reply`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      return res.data
    }
    const res = await api.post(`/admin/tickets/${id}/reply`, { message })
    return res.data
  }

  async function updateTicketStatus(id, status) {
    const res = await api.put(`/admin/tickets/${id}/status`, { status })
    return res.data
  }

  async function assignTicket(id, assignedTo) {
    const res = await api.post(`/admin/tickets/${id}/assign`, { assigned_to: assignedTo })
    return res.data
  }

  async function reopenTicket(id) {
    const res = await api.post(`/admin/tickets/${id}/reopen`)
    return res.data
  }

  return { tickets, loading, fetchTickets, fetchTicket, replyToTicket, updateTicketStatus, assignTicket, reopenTicket }
}

// System health
export function useHealth() {
  const health = ref(null)
  const loading = ref(false)

  async function fetchHealth() {
    loading.value = true
    try {
      const res = await api.get('/admin/health')
      health.value = res.data
    } finally {
      loading.value = false
    }
  }

  return { health, loading, fetchHealth }
}

// Audit logs
export function useAuditLogs() {
  const logs = ref([])
  const loading = ref(false)

  async function fetchLogs(params = {}) {
    loading.value = true
    try {
      const res = await api.get('/admin/audit-logs', { params })
      logs.value = res.data
    } finally {
      loading.value = false
    }
  }

  return { logs, loading, fetchLogs }
}

// Subscriptions
export function useSubscriptions() {
  const subscriptions = ref([])
  const loading = ref(false)

  async function fetchSubscriptions(params = {}) {
    loading.value = true
    try {
      const res = await api.get('/admin/subscriptions', { params })
      subscriptions.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function fetchSubscription(id) {
    const res = await api.get(`/admin/subscriptions/${id}`)
    return res.data
  }

  async function changePlan(id, data) {
    const res = await api.put(`/admin/subscriptions/${id}/change-plan`, data)
    return res.data
  }

  async function cancelSubscription(id) {
    const res = await api.post(`/admin/subscriptions/${id}/cancel`)
    return res.data
  }

  return { subscriptions, loading, fetchSubscriptions, fetchSubscription, changePlan, cancelSubscription }
}

// Platform settings
export function useSettings() {
  const settings = ref({})
  const loading = ref(false)

  async function fetchSettings() {
    loading.value = true
    try {
      const res = await api.get('/admin/settings')
      settings.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function updateSettings(settingsData) {
    await api.put('/admin/settings', { settings: settingsData })
  }

  return { settings, loading, fetchSettings, updateSettings }
}

// Admin auth
export function useAdminAuth() {
  const user = ref(null)
  const loading = ref(false)

  async function login(email: string, password: string) {
    loading.value = true
    try {
      const res = await api.post('/admin/auth/login', { email, password })
      localStorage.setItem('admin_token', res.data.token)
      user.value = res.data.user
      return res.data.user
    } finally {
      loading.value = false
    }
  }

  async function check() {
    const token = localStorage.getItem('admin_token')
    if (!token) return null
    try {
      const res = await api.get('/admin/auth/me')
      user.value = res.data
      return res.data
    } catch {
      localStorage.removeItem('admin_token')
      user.value = null
      return null
    }
  }

  async function fetchProfile() {
    const res = await api.get('/admin/auth/profile')
    return res.data
  }

  async function updateProfile(data: { name?: string; email?: string }) {
    const res = await api.put('/admin/auth/profile', data)
    return res.data
  }

  async function changePassword(data: { current_password: string; password: string; password_confirmation: string }) {
    const res = await api.put('/admin/auth/change-password', data)
    return res.data
  }

  async function logout() {
    await api.post('/admin/auth/logout')
    localStorage.removeItem('admin_token')
    user.value = null
  }

  return { user, loading, login, check, fetchProfile, updateProfile, changePassword, logout }
}

// Features management
export function useFeatures() {
  const features = ref([])
  const loading = ref(false)

  async function fetchFeatures() {
    loading.value = true
    try {
      const res = await api.get('/admin/features')
      features.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function createFeature(data) {
    const res = await api.post('/admin/features', data)
    return res.data
  }

  async function updateFeature(id, data) {
    const res = await api.put(`/admin/features/${id}`, data)
    return res.data
  }

  async function deleteFeature(id) {
    await api.delete(`/admin/features/${id}`)
  }

  return { features, loading, fetchFeatures, createFeature, updateFeature, deleteFeature }
}

// Discounts management
export function useDiscounts() {
  const discounts = ref([])
  const loading = ref(false)

  async function fetchDiscounts() {
    loading.value = true
    try {
      const res = await api.get('/admin/discounts')
      discounts.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function createDiscount(data) {
    const res = await api.post('/admin/discounts', data)
    return res.data
  }

  async function updateDiscount(id, data) {
    const res = await api.put(`/admin/discounts/${id}`, data)
    return res.data
  }

  async function deleteDiscount(id) {
    await api.delete(`/admin/discounts/${id}`)
  }

  return { discounts, loading, fetchDiscounts, createDiscount, updateDiscount, deleteDiscount }
}

// Admin users
export function useAdminUsers() {
  const adminUsers = ref([])
  const loading = ref(false)

  async function fetchAdminUsers() {
    loading.value = true
    try {
      const res = await api.get('/admin/admin-users')
      adminUsers.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function createAdminUser(data) {
    const res = await api.post('/admin/admin-users', data)
    return res.data
  }

  async function updateAdminUser(id, data) {
    const res = await api.put(`/admin/admin-users/${id}`, data)
    return res.data
  }

  async function deleteAdminUser(id) {
    await api.delete(`/admin/admin-users/${id}`)
  }

  return { adminUsers, loading, fetchAdminUsers, createAdminUser, updateAdminUser, deleteAdminUser }
}
