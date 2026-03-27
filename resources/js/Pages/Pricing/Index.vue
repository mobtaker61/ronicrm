<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <h2 class="text-lg font-semibold text-gray-900">Pricing</h2>
                <div class="text-sm text-gray-500">Plans & limits</div>
            </div>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <div
                v-for="p in plans"
                :key="p.id"
                class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow transition"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-lg font-semibold text-gray-900">{{ p.name }}</div>
                        <div class="text-xs text-gray-500">{{ p.code }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ money(p.price_cents, p.currency) }}
                        </div>
                        <div class="text-xs text-gray-500">
                            / {{ p.billing_period }} {{ p.billing_interval > 1 ? `x${p.billing_interval}` : '' }}
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="text-sm font-medium text-gray-900 mb-2">Limits</div>
                    <div v-if="!p.limits_json" class="text-sm text-gray-500">No limits defined.</div>
                    <ul v-else class="text-sm text-gray-700 space-y-1">
                        <li v-for="(v, k) in p.limits_json" :key="k" class="flex items-start justify-between gap-3">
                            <span class="text-gray-500">{{ k }}</span>
                            <span class="font-medium break-all text-right">{{ pretty(v) }}</span>
                        </li>
                    </ul>
                </div>

                <div v-if="isSuperAdmin" class="mt-5 pt-4 border-t border-gray-100">
                    <div class="text-xs text-gray-500">
                        SuperAdmin tip: manage in <a class="text-blue-700 hover:underline" :href="route('superadmin.plans.index')">Plans</a>.
                    </div>
                </div>
            </div>

            <div v-if="!plans?.length" class="col-span-full text-center text-sm text-gray-500 py-10">
                No active plans yet.
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    plans: { type: Array, required: true },
    isSuperAdmin: { type: Boolean, default: false },
});

function money(cents, currency) {
    const v = Number(cents || 0) / 100;
    return `${v.toFixed(2)} ${currency || ''}`.trim();
}

function pretty(v) {
    if (v === null || v === undefined) return '';
    if (Array.isArray(v)) return v.join(', ');
    if (typeof v === 'object') return JSON.stringify(v);
    return String(v);
}
</script>

