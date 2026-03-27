<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <h2 class="text-lg font-semibold text-gray-900">{{ t('superadmin.plans') }}</h2>
                <button
                    type="button"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    @click="openModal()"
                >
                    {{ t('common.add_plan') }}
                </button>
            </div>
        </template>

        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('common.name') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('common.code') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('common.billing') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('common.price') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('common.active') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="p in plans" :key="p.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ p.name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ p.code }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ p.billing_period }} / {{ p.billing_interval }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ formatMoney(p.price_cents, p.currency) }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span :class="p.is_active ? 'text-emerald-700' : 'text-gray-500'">
                                    {{ p.is_active ? t('common.yes') : t('common.no') }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-right space-x-2">
                                <button class="text-blue-700 hover:text-blue-900" @click="openModal(p)">{{ t('common.edit_plan') }}</button>
                                <button class="text-red-700 hover:text-red-900" @click="deletePlan(p)">{{ t('common.delete') }}</button>
                            </td>
                        </tr>
                        <tr v-if="!plans?.length">
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">{{ t('superadmin.plans.empty') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="closeModal">
            <div class="bg-white rounded-lg shadow-xl max-w-xl w-full mx-4 p-6">
                <h3 class="text-lg font-semibold mb-4">{{ editing ? t('common.edit_plan') : t('common.add_plan') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.name') }}</label>
                        <input v-model="form.name" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.code') }}</label>
                        <input v-model="form.code" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.billing_period') }}</label>
                        <select v-model="form.billing_period" class="w-full px-3 py-2 border rounded-md">
                            <option value="monthly">{{ t('common.monthly') }}</option>
                            <option value="yearly">{{ t('common.yearly') }}</option>
                            <option value="custom">{{ t('common.custom') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.interval') }}</label>
                        <input v-model.number="form.billing_interval" type="number" min="1" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.price') }}</label>
                        <input v-model.number="form.price_amount" type="number" min="0" step="0.01" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.currency') }}</label>
                        <input v-model="form.currency" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.limits_json') }}</label>
                        <textarea v-model="limitsText" rows="4" class="w-full px-3 py-2 border rounded-md" placeholder='{"users":5,"channels":["whatsapp"]}' />
                    </div>
                    <div class="flex items-center gap-2">
                        <input id="is_active" type="checkbox" v-model="form.is_active" />
                        <label for="is_active" class="text-sm text-gray-700">{{ t('common.active') }}</label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.sort_order') }}</label>
                        <input v-model.number="form.sort_order" type="number" min="0" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button class="px-4 py-2 border rounded-lg hover:bg-gray-50" @click="closeModal">{{ t('common.cancel') }}</button>
                    <button
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        :disabled="saving || !form.name || !form.code"
                        @click="save"
                    >
                        {{ saving ? t('common.saving') : t('common.save') }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    plans: { type: Array, required: true },
});

const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const form = reactive({
    name: '',
    code: '',
    billing_period: 'monthly',
    billing_interval: 1,
    price_amount: 0,
    currency: 'AED',
    limits_json: null,
    is_active: true,
    sort_order: 0,
});
const limitsText = ref('');

function openModal(p = null) {
    editing.value = p;
    form.name = p?.name || '';
    form.code = p?.code || '';
    form.billing_period = p?.billing_period || 'monthly';
    form.billing_interval = p?.billing_interval ?? 1;
    form.price_amount = p?.price_amount ?? ((Number(p?.price_cents || 0) / 100) || 0);
    form.currency = p?.currency || 'AED';
    form.is_active = p?.is_active ?? true;
    form.sort_order = p?.sort_order ?? 0;
    limitsText.value = p?.limits_json ? JSON.stringify(p.limits_json, null, 2) : '';
    showModal.value = true;
}
function closeModal() {
    showModal.value = false;
    editing.value = null;
}

function formatMoney(cents, currency) {
    const v = Number(cents || 0) / 100;
    return `${v.toFixed(2)} ${currency || ''}`.trim();
}

async function save() {
    saving.value = true;
    try {
        let limits = null;
        if (limitsText.value && limitsText.value.trim() !== '') {
            limits = JSON.parse(limitsText.value);
        }
        const payload = { ...form, limits_json: limits };
        if (editing.value?.id) {
            await window.axios.put(route('superadmin.plans.update', editing.value.id), payload);
        } else {
            await window.axios.post(route('superadmin.plans.store'), payload);
        }
        closeModal();
        router.visit(route('superadmin.plans.index'), { preserveScroll: true, replace: true });
    } catch (e) {
        alert(t('common.invalid_limits_json_or_save_failed'));
    } finally {
        saving.value = false;
    }
}

async function deletePlan(p) {
    if (!p?.id) return;
    if (!confirm(t('common.confirm_delete_plan'))) return;
    await window.axios.delete(route('superadmin.plans.destroy', p.id));
    router.visit(route('superadmin.plans.index'), { preserveScroll: true, replace: true });
}
</script>

