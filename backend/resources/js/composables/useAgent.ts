import { ref } from 'vue'
import axios from 'axios'

const api = axios.create({
  baseURL: '/api/v1',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

export function useAgent() {
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchDashboard() {
    loading.value = true; error.value = null
    try { const { data } = await api.get('/agent/dashboard'); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to load dashboard'; throw e }
    finally { loading.value = false }
  }

  async function fetchProfile() {
    loading.value = true; error.value = null
    try { const { data } = await api.get('/agent/profile'); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to load profile'; throw e }
    finally { loading.value = false }
  }

  async function fetchReferrals(params?: Record<string, any>) {
    loading.value = true; error.value = null
    try { const { data } = await api.get('/agent/referrals', { params }); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to load referrals'; throw e }
    finally { loading.value = false }
  }

  async function createReferral(landingUrl?: string) {
    loading.value = true; error.value = null
    try { const { data } = await api.post('/agent/referrals', { landing_url: landingUrl }); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to create referral'; throw e }
    finally { loading.value = false }
  }

  async function fetchReferral(id: string) {
    loading.value = true; error.value = null
    try { const { data } = await api.get(`/agent/referrals/${id}`); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to load referral'; throw e }
    finally { loading.value = false }
  }

  async function fetchCommissions(params?: Record<string, any>) {
    loading.value = true; error.value = null
    try { const { data } = await api.get('/agent/commissions', { params }); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to load commissions'; throw e }
    finally { loading.value = false }
  }

  async function fetchCommissionSummary() {
    loading.value = true; error.value = null
    try { const { data } = await api.get('/agent/commissions/summary'); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to load summary'; throw e }
    finally { loading.value = false }
  }

  async function fetchCommission(id: string) {
    loading.value = true; error.value = null
    try { const { data } = await api.get(`/agent/commissions/${id}`); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to load commission'; throw e }
    finally { loading.value = false }
  }

  async function fetchPayouts(params?: Record<string, any>) {
    loading.value = true; error.value = null
    try { const { data } = await api.get('/agent/payouts', { params }); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to load payouts'; throw e }
    finally { loading.value = false }
  }

  async function requestPayout(payload: { amount: number; method: string; account_details: Record<string, any> }) {
    loading.value = true; error.value = null
    try { const { data } = await api.post('/agent/payouts/request', payload); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to request payout'; throw e }
    finally { loading.value = false }
  }

  async function fetchPayout(id: string) {
    loading.value = true; error.value = null
    try { const { data } = await api.get(`/agent/payouts/${id}`); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to load payout'; throw e }
    finally { loading.value = false }
  }

  async function fetchAgentProfile() {
    loading.value = true; error.value = null
    try { const { data } = await api.get('/agent/auth/profile'); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to load profile'; throw e }
    finally { loading.value = false }
  }

  async function updateAgentProfile(payload: { name?: string; email?: string; phone?: string }) {
    loading.value = true; error.value = null
    try { const { data } = await api.put('/agent/auth/profile', payload); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to update profile'; throw e }
    finally { loading.value = false }
  }

  async function changeAgentPassword(payload: { current_password: string; password: string; password_confirmation: string }) {
    loading.value = true; error.value = null
    try { const { data } = await api.put('/agent/auth/change-password', payload); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to change password'; throw e }
    finally { loading.value = false }
  }

  return {
    loading, error,
    fetchDashboard, fetchProfile,
    fetchReferrals, createReferral, fetchReferral,
    fetchCommissions, fetchCommissionSummary, fetchCommission,
    fetchPayouts, requestPayout, fetchPayout,
    fetchAgentProfile, updateAgentProfile, changeAgentPassword,
  }
}
