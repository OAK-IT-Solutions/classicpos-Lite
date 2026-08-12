import { ref, computed, onMounted, onUnmounted } from 'vue';

const isOnline = ref<boolean>(typeof navigator !== 'undefined' ? navigator.onLine : true);
const lastOnlineAt = ref<number | null>(null);
const lastOfflineAt = ref<number | null>(null);
const connectionType = ref<string>('unknown');
let initialized = false;

function setOnline() {
    const wasOffline = !isOnline.value;
    isOnline.value = true;
    if (wasOffline) {
        lastOnlineAt.value = Date.now();
    }
}

function setOffline() {
    const wasOnline = isOnline.value;
    isOnline.value = false;
    if (wasOnline) {
        lastOfflineAt.value = Date.now();
    }
}

function detectConnectionType(): string {
    if (typeof navigator === 'undefined') return 'unknown';
    const conn = (navigator as any).connection
        || (navigator as any).mozConnection
        || (navigator as any).webkitConnection;

    if (!conn) return 'unknown';

    return conn.effectiveType || conn.type || 'unknown';
}

function init() {
    if (initialized || typeof window === 'undefined') return;
    initialized = true;

    window.addEventListener('online', setOnline);
    window.addEventListener('offline', setOffline);

    const conn = (navigator as any).connection;
    if (conn) {
        conn.addEventListener?.('change', () => {
            connectionType.value = detectConnectionType();
        });
    }

    connectionType.value = detectConnectionType();
}

export function useNetwork() {
    init();

    const isOffline = computed(() => !isOnline.value);
    const hasBeenOffline = computed(() => lastOfflineAt.value !== null);

    return {
        isOnline,
        isOffline,
        lastOnlineAt,
        lastOfflineAt,
        connectionType,
        hasBeenOffline,
    };
}

export function getNetworkState() {
    return {
        isOnline: isOnline.value,
        isOffline: !isOnline.value,
        lastOnlineAt: lastOnlineAt.value,
        lastOfflineAt: lastOfflineAt.value,
        connectionType: connectionType.value,
    };
}

export async function checkServerReachable(timeout = 5000): Promise<boolean> {
    if (typeof window === 'undefined') return false;

    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);
        const response = await fetch('/api/v1/sync/status', {
            method: 'GET',
            signal: controller.signal,
            headers: { 'Accept': 'application/json' },
        });
        clearTimeout(timeoutId);
        return response.ok;
    } catch {
        return false;
    }
}

export function useNetworkAutoInit() {
    onMounted(() => {
        init();
    });
}
