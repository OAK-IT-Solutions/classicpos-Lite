import { ref } from 'vue'
import api from '@/composables/axios'

export function useTickets() {
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchTickets(params?: Record<string, any>) {
    loading.value = true; error.value = null
    try { const { data } = await api.get('/tickets', { params }); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to load tickets'; throw e }
    finally { loading.value = false }
  }

  async function createTicket(payload: { subject: string; description: string; category: string; priority: string; attachments?: File[] }) {
    loading.value = true; error.value = null
    try {
      const body = payload.attachments?.length ? buildFormData(payload) : payload
      const { data } = await api.post('/tickets', body, payload.attachments?.length ? { headers: { 'Content-Type': 'multipart/form-data' } } : {})
      return data
    }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to create ticket'; throw e }
    finally { loading.value = false }
  }

  async function fetchTicket(id: string) {
    loading.value = true; error.value = null
    try { const { data } = await api.get(`/tickets/${id}`); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to load ticket'; throw e }
    finally { loading.value = false }
  }

  async function replyToTicket(id: string, message: string, attachments?: File[]) {
    loading.value = true; error.value = null
    try {
      const body = attachments?.length ? buildFormData({ message, attachments }) : { message }
      const { data } = await api.post(`/tickets/${id}/reply`, body, attachments?.length ? { headers: { 'Content-Type': 'multipart/form-data' } } : {})
      return data
    }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to send reply'; throw e }
    finally { loading.value = false }
  }

  function buildFormData(data: Record<string, any>) {
    const fd = new FormData()
    for (const key of Object.keys(data)) {
      if (key === 'attachments' && Array.isArray(data[key])) {
        data[key].forEach((f: File) => fd.append('attachments[]', f))
      } else {
        fd.append(key, data[key])
      }
    }
    return fd
  }

  async function closeTicket(id: string) {
    loading.value = true; error.value = null
    try { const { data } = await api.post(`/tickets/${id}/close`); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to close ticket'; throw e }
    finally { loading.value = false }
  }

  async function reopenTicket(id: string) {
    loading.value = true; error.value = null
    try { const { data } = await api.post(`/tickets/${id}/reopen`); return data }
    catch (e: any) { error.value = e.response?.data?.error || 'Failed to reopen ticket'; throw e }
    finally { loading.value = false }
  }

  return {
    loading, error,
    fetchTickets, createTicket, fetchTicket, replyToTicket, closeTicket, reopenTicket,
  }
}
