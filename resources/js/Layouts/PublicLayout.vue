<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Minimal header -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center">
                <div class="flex items-center gap-3 min-w-0">
                    <img
                        src="/brand/logo-full-96px.png"
                        :alt="appName"
                        class="h-8 w-auto max-w-[200px] object-contain object-left"
                    />
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
