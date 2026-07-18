<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 px-4">
        <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center">
            <div class="w-20 h-20 mx-auto mb-6 bg-blue-100 rounded-full flex items-center justify-center">
                <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18.364 5.636a9 9 0 010 12.728M5.636 18.364a9 9 0 010-12.728M12 9v4m0 4h.01" />
                </svg>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-2">You're Offline</h1>
            <p class="text-gray-600 mb-6">
                ClassicPOS is running in offline mode. Your sales are being saved locally
                and will automatically sync to the server when you reconnect.
            </p>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6 text-left">
                <h2 class="font-semibold text-yellow-900 mb-2 text-sm">Available Offline:</h2>
                <ul class="text-sm text-yellow-800 space-y-1">
                    <li>✓ Process sales and payments</li>
                    <li>✓ Print receipts and open cash drawer</li>
                    <li>✓ Scan barcodes</li>
                    <li>✓ View cached products</li>
                </ul>
            </div>

            <button @click="retry"
                class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors">
                Retry Connection
            </button>

            <p class="text-xs text-gray-500 mt-4">
                Your offline data is safe and will sync when you go back online.
            </p>
        </div>
    </div>
</template>

<script setup>
function retry() {
    window.location.reload()
}
</script>
