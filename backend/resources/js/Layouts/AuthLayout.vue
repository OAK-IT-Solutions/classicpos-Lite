<template>
    <div class="auth-layout">
        <div class="auth-bg">
            <div class="bg-orb bg-orb-1"></div>
            <div class="bg-orb bg-orb-2"></div>
        </div>
        <div class="auth-container" :class="wide ? 'auth-wide' : ''">
            <div class="auth-header">
                <div class="auth-logo">
                    <svg viewBox="0 0 100 100" width="48" height="48">
                        <rect x="10" y="10" width="80" height="80" rx="16" fill="#3b82f6"/>
                        <text x="50" y="62" text-anchor="middle" fill="white" font-size="28" font-weight="bold" font-family="system-ui">POS</text>
                    </svg>
                </div>
                <h1>ClassicPOS</h1>
                <p>Offline Desktop Point of Sale</p>
            </div>
            <div class="auth-card">
                <slot />
            </div>
            <p class="auth-footer">&copy; {{ new Date().getFullYear() }} ClassicPOS. All rights reserved.</p>
        </div>
    </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
    wide?: boolean;
}>(), {
    wide: false,
});
</script>

<style scoped>
.auth-layout {
    min-height: 100vh;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    overflow-y: auto;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    color: #e2e8f0;
    padding: 2rem 1rem;
}

.auth-bg {
    position: fixed;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}

.bg-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.15;
}

.bg-orb-1 {
    top: -10%;
    left: 20%;
    width: 500px;
    height: 500px;
    background: #3b82f6;
}

.bg-orb-2 {
    bottom: -10%;
    right: 20%;
    width: 400px;
    height: 400px;
    background: #6366f1;
}

.auth-container {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 440px;
}

.auth-wide {
    max-width: 640px;
}

.auth-header {
    text-align: center;
    margin-bottom: 2rem;
}

.auth-logo {
    margin-bottom: 1rem;
}

.auth-header h1 {
    margin: 0 0 0.25rem;
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.025em;
}

.auth-header p {
    margin: 0;
    color: #94a3b8;
    font-size: 0.875rem;
}

.auth-card {
    background: rgba(30, 41, 59, 0.8);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.auth-footer {
    text-align: center;
    color: #475569;
    font-size: 0.75rem;
    margin-top: 1.5rem;
}
</style>
