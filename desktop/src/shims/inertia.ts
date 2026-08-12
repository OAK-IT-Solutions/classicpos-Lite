/**
 * Inertia.js Adapter Shim for ClassicPOS Desktop
 *
 * Replaces @inertiajs/vue3 imports so existing backend components
 * work in the Tauri desktop shell without modification.
 *
 * Minimal API surface:
 * - router.visit(url) — navigation via vue-router
 * - usePage().url — current pathname (reactive)
 * - Link — no-op (existing components use plain <a> tags)
 */

import { ref, computed, onMounted, onUnmounted, reactive } from 'vue';

// ─── Reactive URL State ────────────────────────────────────────────────────────

const currentPath = ref(window.location.pathname);

function updatePath() {
  currentPath.value = window.location.pathname;
}

// Listen for vue-router navigation
if (typeof window !== 'undefined') {
  window.addEventListener('popstate', updatePath);

  // Intercept pushState/replaceState for SPA navigation
  const originalPushState = history.pushState;
  const originalReplaceState = history.replaceState;

  history.pushState = function (...args) {
    originalPushState.apply(this, args);
    updatePath();
  };

  history.replaceState = function (...args) {
    originalReplaceState.apply(this, args);
    updatePath();
  };
}

// ─── Router ───────────────────────────────────────────────────────────────────

export const router = {
  /**
   * Navigate to a URL. Uses vue-router in desktop, falls back to window.location.
   */
  visit(url: string, options?: { replace?: boolean }) {
    // Strip any query params for vue-router
    const path = url.split('?')[0];

    if (typeof window !== 'undefined' && (window as any).__VUE_ROUTER__) {
      if (options?.replace) {
        (window as any).__VUE_ROUTER__.replace(path);
      } else {
        (window as any).__VUE_ROUTER__.push(path);
      }
      updatePath();
    } else {
      window.location.href = url;
    }
  },

  reload() {
    window.location.reload();
  },

  post() { /* no-op in desktop */ },
  put() { /* no-op in desktop */ },
  patch() { /* no-op in desktop */ },
  delete() { /* no-op in desktop */ },
  flush() { /* no-op in desktop */ },
};

// ─── usePage ──────────────────────────────────────────────────────────────────

export function usePage() {
  return {
    get url() {
      return currentPath.value;
    },
    props: {},
    component: '',
    propsValue: {},
    reload: (only?: string[]) => router.reload(),
    visit: (url: string, options?: any) => router.visit(url, options),
  };
}

// ─── useForm ───────────────────────────────────────────────────────────────────

export function useForm<T extends Record<string, any>>(initialData: T) {
  const data = reactive({ ...initialData }) as any;
  const errors = ref<Record<string, string>>({});
  const processing = ref(false);
  const successful = ref(false);
  const recentlySuccessful = ref(false);

  let timeout: ReturnType<typeof setTimeout>;

  const setErrors = (e: Record<string, string>) => { errors.value = e; };
  const clearErrors = () => { errors.value = {}; };
  const reset = (...fields: string[]) => {
    if (fields.length === 0) {
      Object.assign(data, initialData);
    } else {
      for (const f of fields) data[f] = initialData[f];
    }
    clearErrors();
  };

  const submit = async (method: string, url: string) => {
    processing.value = true;
    successful.value = false;
    clearErrors();
    try {
      const { default: api } = await import('@/composables/axios');
      const response = await (api as any)[method.toLowerCase()]?.(url, data) ?? await api.post(url, data);
      successful.value = true;
      recentlySuccessful.value = true;
      clearTimeout(timeout);
      timeout = setTimeout(() => { recentlySuccessful.value = false; }, 2000);
      return response;
    } catch (e: any) {
      if (e.response?.status === 422) {
        setErrors(e.response.data.errors || {});
      }
      throw e;
    } finally {
      processing.value = false;
    }
  };

  return {
    data, errors, processing, successful, recentlySuccessful,
    post: (url: string) => submit('post', url),
    put: (url: string) => submit('put', url),
    patch: (url: string) => submit('patch', url),
    delete: (url: string) => submit('delete', url),
    reset, clearErrors, setErrors,
  };
}

// ─── Link ─────────────────────────────────────────────────────────────────────

export const Link = {
  template: `<a :href="href" @click.prevent="navigate"><slot /></a>`,
  props: ['href', 'replace', 'preserveScroll', 'preserveState', 'only', 'method', 'as'],
  setup(props: any) {
    const navigate = () => router.visit(props.href, { replace: props.replace });
    return { navigate };
  },
};

// ─── Head ─────────────────────────────────────────────────────────────────────

export const Head = {
  template: '',
  props: ['title'],
  setup() {},
};
