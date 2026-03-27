<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <h2 class="text-lg font-semibold text-gray-900">{{ t('superadmin.organization_subscriptions') }}</h2>
            </div>
        </template>

        <div class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
            <div class="flex items-center gap-2">
                <input
                    v-model="search"
                    @keyup.enter="applySearch"
                    type="text"
                    :placeholder="t('superadmin.organization_subscriptions.search_placeholder')"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md"
                />
                <button class="px-3 py-2 border rounded-md hover:bg-gray-50" @click="applySearch">{{ t('common.search') }}</button>
            </div>
        </div>

        <div class="space-y-4">
            <div
                v-for="row in rows"
                :key="row.organization.id"
                class="bg-white border border-gray-200 rounded-lg p-4"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-base font-semibold text-gray-900">
                            {{ row.organization.name }}
                            <span class="text-sm text-gray-500 ml-2">({{ row.organization.slug }})</span>
                        </div>
                        <div class="text-sm mt-1">
                            <span class="text-gray-500">{{ t('common.status') }}</span>
                            <span class="font-medium" :class="statusColor(row.subscription.status)">
                                {{ row.subscription.status }}
                            </span>
                        </div>
                    </div>
                    <button class="px-3 py-2 border rounded-md hover:bg-gray-50" @click="toggleOpen(row.organization.id)">
                        {{ openOrgId === row.organization.id ? t('common.close') : t('common.manage') }}
                    </button>
                </div>

                <div v-if="openOrgId === row.organization.id" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.plan') }}</label>
                        <select v-model.number="forms[row.organization.id].plan_id" class="w-full px-3 py-2 border rounded-md">
                            <option :value="null">{{ t('common.dash') }}</option>
                            <option v-for="p in plans" :key="p.id" :value="p.id">
                                {{ p.name }} ({{ money(p.price_cents, p.currency) }})
                            </option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2 mt-6 md:mt-0">
                        <input :id="`cancel_${row.organization.id}`" type="checkbox" v-model="forms[row.organization.id].cancel_at_period_end" />
                        <label :for="`cancel_${row.organization.id}`" class="text-sm text-gray-700">{{ t('common.cancel_at_period_end') }}</label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.started_at') }}</label>
                        <input v-model="forms[row.organization.id].started_at" type="date" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.trial_ends_at') }}</label>
                        <input v-model="forms[row.organization.id].trial_ends_at" type="date" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.ends_at') }}</label>
                        <input v-model="forms[row.organization.id].ends_at" type="date" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.grace_ends_at') }}</label>
                        <input v-model="forms[row.organization.id].grace_ends_at" type="date" class="w-full px-3 py-2 border rounded-md" />
                    </div>

                    <div class="md:col-span-2 flex justify-end">
                        <button
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            :disabled="savingOrgId === row.organization.id"
                            @click="saveOrg(row.organization.id)"
                        >
                            {{ savingOrgId === row.organization.id ? t('common.saving') : t('common.save_subscription') }}
                        </button>
                    </div>

                    <div class="md:col-span-2 border-t pt-4">
                        <div class="text-sm font-semibold text-gray-900 mb-2">{{ t('common.add_manual_payment') }}</div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">{{ t('common.amount_cents') }}</label>
                                <input v-model.number="payments[row.organization.id].amount_cents" type="number" min="0" class="w-full px-3 py-2 border rounded-md" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">{{ t('common.currency') }}</label>
                                <input v-model="payments[row.organization.id].currency" class="w-full px-3 py-2 border rounded-md" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">{{ t('common.paid_at') }}</label>
                                <input v-model="payments[row.organization.id].paid_at" type="date" class="w-full px-3 py-2 border rounded-md" />
                            </div>
                            <div class="flex items-end">
                                <button
                                    class="w-full px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:opacity-50"
                                    :disabled="savingPaymentOrgId === row.organization.id"
                                    @click="addPayment(row.organization.id)"
                                >
                                    {{ savingPaymentOrgId === row.organization.id ? t('common.saving') : t('common.add_payment') }}
                                </button>
                            </div>
                            <div class="md:col-span-4">
                                <label class="block text-xs text-gray-600 mb-1">{{ t('common.reference_notes') }}</label>
                                <input v-model="payments[row.organization.id].reference" class="w-full px-3 py-2 border rounded-md" :placeholder="t('common.ref_placeholder')" />
                                <textarea v-model="payments[row.organization.id].notes" rows="2" class="w-full mt-2 px-3 py-2 border rounded-md" :placeholder="t('common.notes_placeholder')" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!rows?.length" class="text-center text-sm text-gray-500 py-10">
                {{ t('superadmin.organization_subscriptions.empty') }}
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    plans: { type: Array, required: true },
    rows: { type: Array, required: true },
});

const search = ref(props.filters?.q || '');
const openOrgId = ref(null);
const savingOrgId = ref(null);
const savingPaymentOrgId = ref(null);

const forms = reactive({});
const payments = reactive({});

function ensureState(orgId) {
    if (!forms[orgId]) {
        const row = props.rows.find((r) => r.organization.id === orgId);
        forms[orgId] = {
            plan_id: row?.subscription?.plan_id ?? null,
            started_at: row?.subscription?.started_at ?? null,
            trial_ends_at: row?.subscription?.trial_ends_at ?? null,
            ends_at: row?.subscription?.ends_at ?? null,
            grace_ends_at: row?.subscription?.grace_ends_at ?? null,
            cancel_at_period_end: !!row?.subscription?.cancel_at_period_end,
            notes: null,
        };
    }
    if (!payments[orgId]) {
        payments[orgId] = {
            amount_cents: 0,
            currency: 'AED',
            paid_at: null,
            reference: null,
            notes: null,
        };
    }
}

function toggleOpen(orgId) {
    if (openOrgId.value === orgId) {
        openOrgId.value = null;
        return;
    }
    openOrgId.value = orgId;
    ensureState(orgId);
}

function applySearch() {
    router.get(route('superadmin.subscriptions.index'), { q: search.value || '' }, { preserveState: true, preserveScroll: true });
}

function money(cents, currency) {
    const v = Number(cents || 0) / 100;
    return `${v.toFixed(2)} ${currency || ''}`.trim();
}

function statusColor(status) {
    switch (status) {
        case 'active': return 'text-emerald-700';
        case 'trial': return 'text-blue-700';
        case 'grace': return 'text-amber-700';
        case 'expired': return 'text-red-700';
        default: return 'text-gray-700';
    }
}

async function saveOrg(orgId) {
    ensureState(orgId);
    savingOrgId.value = orgId;
    try {
        await window.axios.put(route('superadmin.subscriptions.organizations.update', orgId), forms[orgId]);
        router.reload({ preserveScroll: true });
    } finally {
        savingOrgId.value = null;
    }
}

async function addPayment(orgId) {
    ensureState(orgId);
    savingPaymentOrgId.value = orgId;
    try {
        await window.axios.post(route('superadmin.subscriptions.organizations.payments.store', orgId), payments[orgId]);
        payments[orgId].amount_cents = 0;
        payments[orgId].reference = null;
        payments[orgId].notes = null;
        router.reload({ preserveScroll: true });
    } finally {
        savingPaymentOrgId.value = null;
    }
}
</script>

