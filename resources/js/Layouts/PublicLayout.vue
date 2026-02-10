<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Minimal header -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center">
                <div class="flex items-center gap-3">
                    <div class="bg-blue-600 rounded-lg p-2">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <span class="text-lg font-bold text-gray-900">{{ appName }}</span>
                </div>
            </div>
        </header>

        <slot />
    </div>
</template>

<script setup>
defineProps({
    appName: { type: String, default: 'RoniCRM' },
});
</script>
