import axios from 'axios';

const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

function isLoginPage(): boolean {
    const path = window.location.pathname;
    return path === '/login' || path === '/admin/login' || path === '/agent/login';
}

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('admin_token') || localStorage.getItem('auth_token') || localStorage.getItem('agent_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

let isRefreshing = false;
let failedQueue: { resolve: (token: string) => void; reject: (err: unknown) => void }[] = [];

function processQueue(error: unknown, token: string | null = null) {
    failedQueue.forEach(({ resolve, reject }) => {
        if (error) {
            reject(error);
        } else {
            resolve(token!);
        }
    });
    failedQueue = [];
}

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;

        if (error.response?.status === 401 && !originalRequest._retry) {
            // Don't redirect away from login pages
            if (isLoginPage()) {
                return Promise.reject(error);
            }

            const isAdminPage = window.location.pathname.startsWith('/admin');
            const adminToken = localStorage.getItem('admin_token');

            // Don't attempt refresh for login/register/refresh endpoints themselves
            const url = originalRequest.url || '';
            if (url.includes('/auth/login') || url.includes('/auth/register') || url.includes('/auth/refresh')) {
                localStorage.removeItem(adminToken ? 'admin_token' : 'auth_token');
                localStorage.removeItem('auth_user');
                window.location.href = isAdminPage ? '/admin/login' : '/login';
                return Promise.reject(error);
            }

            const token = adminToken || localStorage.getItem('auth_token') || localStorage.getItem('agent_token');
            if (!token) {
                localStorage.removeItem('auth_user');
                window.location.href = isAdminPage ? '/admin/login' : '/login';
                return Promise.reject(error);
            }

            if (isRefreshing) {
                return new Promise((resolve, reject) => {
                    failedQueue.push({ resolve, reject });
                }).then((newToken) => {
                    originalRequest.headers.Authorization = `Bearer ${newToken}`;
                    return api(originalRequest);
                });
            }

            originalRequest._retry = true;
            isRefreshing = true;

            try {
                const { data } = await axios.post('/api/v1/auth/refresh', null, {
                    headers: {
                        Authorization: `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                });

                localStorage.setItem(adminToken ? 'admin_token' : 'auth_token', data.token);
                localStorage.setItem('auth_user', JSON.stringify(data.user));

                processQueue(null, data.token);

                originalRequest.headers.Authorization = `Bearer ${data.token}`;
                return api(originalRequest);
            } catch (refreshError) {
                processQueue(refreshError, null);
                localStorage.removeItem(adminToken ? 'admin_token' : 'auth_token');
                localStorage.removeItem('auth_user');
                window.location.href = isAdminPage ? '/admin/login' : '/login';
                return Promise.reject(refreshError);
            } finally {
                isRefreshing = false;
            }
        }

        return Promise.reject(error);
    }
);

export default api;

export function useAxios() {
    return { api };
}
