<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-gray-900">{{ t('superadmin.translations') }}</h2>
                    <span class="text-xs text-gray-500">{{ t('common.db_json_cache') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="runBuildJson"
                        :disabled="buildingJson"
                        class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {{ buildingJson ? t('common.building') : t('common.build_json') }}
                    </button>
                    <button
                        type="button"
                        @click="openKeyModal()"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    >
                        {{ t('common.add_key') }}
                    </button>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-3">
                    <input
                        v-model="search"
                        @keyup.enter="applySearch"
                        type="text"
                        :placeholder="t('common.search_keys')"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                    />
                    <button
                        class="px-3 py-2 border rounded-md hover:bg-gray-50"
                        @click="applySearch"
                    >
                        {{ t('common.search') }}
                    </button>
                </div>

                <div class="space-y-2 max-h-[70vh] overflow-auto">
                    <button
                        v-for="k in keys.data"
                        :key="k.id"
                        type="button"
                        @click="selectKey(k)"
                        class="w-full text-left px-3 py-2 rounded-md border transition"
                        :class="selectedKey?.id === k.id ? 'border-blue-300 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'"
                    >
                        <div class="text-sm font-medium text-gray-900 truncate">{{ k.full_key }}</div>
                        <div v-if="k.description" class="text-xs text-gray-500 truncate">{{ k.description }}</div>
                    </button>
                    <div v-if="!keys.data?.length" class="text-sm text-gray-500 py-6 text-center">
                        {{ t('superadmin.translations.empty') }}
                    </div>
                </div>

                <div v-if="keys.links?.length" class="mt-4 flex flex-wrap gap-1">
                    <button
                        v-for="lnk in keys.links"
                        :key="lnk.label"
                        type="button"
                        :disabled="!lnk.url"
                        @click="goTo(lnk.url)"
                        class="px-2 py-1 text-xs rounded border"
                        :class="lnk.active ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-200 hover:bg-gray-50 disabled:opacity-50'"
                        v-html="lnk.label"
                    />
                </div>
            </div>

            <div class="lg:col-span-2 bg-white rounded-lg border border-gray-200 p-4">
                <div v-if="!selectedKey" class="text-gray-500 py-10 text-center">
                    {{ t('superadmin.translations.select_key') }}
                </div>

                <div v-else>
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div>
                            <div class="text-sm text-gray-500">{{ t('common.key') }}</div>
                            <div class="text-base font-semibold text-gray-900">{{ selectedKey.full_key }}</div>
                            <div v-if="selectedKey.description" class="text-sm text-gray-600 mt-1">{{ selectedKey.description }}</div>
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="px-3 py-2 border rounded-md hover:bg-gray-50"
                                @click="openKeyModal(selectedKey)"
                            >
                                {{ t('common.edit_key') }}
                            </button>
                            <button
                                type="button"
                                class="px-3 py-2 border border-red-200 text-red-700 rounded-md hover:bg-red-50"
                                @click="deleteKey(selectedKey)"
                            >
                                {{ t('common.delete') }}
                            </button>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="lang in languages"
                            :key="lang.id"
                            class="border border-gray-200 rounded-lg p-4"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ lang.name }} ({{ lang.code }})
                                    <span v-if="lang.is_default" class="ml-2 text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">{{ t('common.default') }}</span>
                                </div>
                                <button
                                    type="button"
                                    class="text-sm text-blue-700 hover:text-blue-900"
                                    :disabled="savingValue"
                                    @click="saveValue(lang)"
                                >
                                    {{ t('common.save') }}
                                </button>
                            </div>
                            <textarea
                                v-model="valuesByLanguage[lang.id]"
                                rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                :placeholder="t('common.translation_placeholder')"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Modal -->
        <div
            v-if="showKeyModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="closeKeyModal"
        >
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 p-6">
                <h3 class="text-lg font-semibold mb-4">{{ editingKey ? t('common.edit_key') : t('common.add_key') }}</h3>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.namespace') }}</label>
                        <input v-model="keyForm.namespace" type="text" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.key') }}</label>
                        <input v-model="keyForm.key" type="text" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.description') }}</label>
                        <input v-model="keyForm.description" type="text" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button class="px-4 py-2 border rounded-lg hover:bg-gray-50" @click="closeKeyModal">{{ t('common.cancel') }}</button>
                    <button
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        :disabled="savingKey || !keyForm.namespace || !keyForm.key"
                        @click="saveKey"
                    >
                        {{ savingKey ? t('common.saving') : t('common.save') }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from '@/composables/useI18n';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    keys: { type: Object, required: true },
    languages: { type: Array, required: true },
});

const { t } = useI18n();

const search = ref(props.filters?.q || '');
const selectedKey = ref(null);
const valuesByLanguage = reactive({});
const savingValue = ref(false);
const buildingJson = ref(false);

const showKeyModal = ref(false);
const editingKey = ref(null);
const savingKey = ref(false);
const keyForm = reactive({ namespace: 'app', key: '', description: '' });

function applySearch() {
    router.get(route('superadmin.translations.index'), { q: search.value || '' }, { preserveState: true, preserveScroll: true });
}

function goTo(url) {
    if (!url) return;
    router.visit(url, { preserveState: true, preserveScroll: true });
}

async function selectKey(k) {
    selectedKey.value = k;
    // reset
    props.languages.forEach((l) => {
        valuesByLanguage[l.id] = '';
    });
    const { data } = await window.axios.get(route('superadmin.translations.keys.values', k.id));
    const vals = data?.values || {};
    Object.keys(vals).forEach((langId) => {
        valuesByLanguage[Number(langId)] = vals[langId] ?? '';
    });
}

async function saveValue(lang) {
    if (!selectedKey.value) return;
    savingValue.value = true;
    try {
        await window.axios.post(route('superadmin.translations.values.upsert'), {
            translation_key_id: selectedKey.value.id,
            language_id: lang.id,
            value: valuesByLanguage[lang.id] ?? null,
        });
    } finally {
        savingValue.value = false;
    }
}

function openKeyModal(k = null) {
    editingKey.value = k;
    keyForm.namespace = k?.namespace || 'app';
    keyForm.key = k?.key || '';
    keyForm.description = k?.description || '';
    showKeyModal.value = true;
}

function closeKeyModal() {
    showKeyModal.value = false;
    editingKey.value = null;
}

async function saveKey() {
    savingKey.value = true;
    try {
        if (editingKey.value?.id) {
            await window.axios.put(route('superadmin.translations.keys.update', editingKey.value.id), { ...keyForm });
        } else {
            await window.axios.post(route('superadmin.translations.keys.store'), { ...keyForm });
        }
        closeKeyModal();
        router.reload({ preserveScroll: true });
    } finally {
        savingKey.value = false;
    }
}

async function deleteKey(k) {
    if (!k?.id) return;
    if (!confirm(t('common.confirm_delete_key'))) return;
    await window.axios.delete(route('superadmin.translations.keys.destroy', k.id));
    selectedKey.value = null;
    router.reload({ preserveScroll: true });
}

async function runBuildJson() {
    buildingJson.value = true;
    try {
        await window.axios.post(route('superadmin.translations.build-json'));
        router.reload({ preserveScroll: true });
    } finally {
        buildingJson.value = false;
    }
}
</script>

