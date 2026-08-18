<template>
  <div class="login-page">
    <div class="login-card">
      <div class="logo-icon">
        <svg viewBox="0 0 100 100" width="56" height="56">
          <rect x="10" y="10" width="80" height="80" rx="16" fill="#3b82f6"/>
          <text x="50" y="62" text-anchor="middle" fill="white" font-size="28" font-weight="bold" font-family="system-ui">POS</text>
        </svg>
      </div>
      <h1>ClassicPOS</h1>
      <p class="subtitle">Offline Desktop POS System</p>

      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label>Email</label>
          <input v-model="email" type="email" required placeholder="you@yourbusiness.com" />
        </div>
        <div class="form-group">
          <label>Password</label>
          <input v-model="password" type="password" required placeholder="Your password" />
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <button type="submit" :disabled="loading">
          {{ loading ? 'Signing in...' : 'Sign In' }}
        </button>
      </form>
    </div>
    <p class="version">v{{ version }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { apiRequest, getAppVersion } from '../../services/ElectronBridge';

const router = useRouter();
const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');
const version = ref('1.0.0');

onMounted(async () => {
  try { version.value = await getAppVersion(); } catch {}
});

const handleLogin = async () => {
  loading.value = true;
  error.value = '';
  try {
    const resp = await apiRequest('POST', '/api/v1/auth/login', JSON.stringify({
      email: email.value,
      password: password.value,
    }));
    const data = JSON.parse(resp.body);
    if (resp.status >= 400) {
      error.value = data.message || data.error?.message || 'Login failed';
      return;
    }
    localStorage.setItem('auth_token', data.token);
    localStorage.setItem('user', JSON.stringify(data.user));
    router.push('/dashboard');
  } catch (e: any) {
    error.value = e.message || 'Login failed. Please try again.';
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.login-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  color: #e2e8f0;
  padding: 2rem;
}

.login-card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 16px;
  padding: 3rem;
  max-width: 420px;
  width: 100%;
  text-align: center;
}

.logo-icon {
  margin-bottom: 1.5rem;
}

.login-card h1 {
  margin: 0 0 0.25rem;
  font-size: 2rem;
  font-weight: 700;
  letter-spacing: -0.025em;
}

.subtitle {
  color: #94a3b8;
  margin-bottom: 2rem;
  font-size: 0.95rem;
}

.form-group {
  margin-bottom: 1.25rem;
  text-align: left;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  font-size: 0.875rem;
  color: #cbd5e1;
}

.form-group input {
  width: 100%;
  padding: 0.75rem 1rem;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 10px;
  color: #e2e8f0;
  font-size: 0.95rem;
  outline: none;
  transition: border-color 0.2s;
}

.form-group input::placeholder {
  color: #475569;
}

.form-group input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.error {
  color: #ef4444;
  font-size: 0.875rem;
  margin-bottom: 1rem;
  text-align: left;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  padding: 0.75rem 1rem;
}

button[type="submit"] {
  width: 100%;
  padding: 0.875rem;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: 0.5rem;
}

button[type="submit"]:hover:not(:disabled) {
  background: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

button[type="submit"]:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.version {
  color: #475569;
  font-size: 0.8rem;
  margin-top: 2rem;
}
</style>
