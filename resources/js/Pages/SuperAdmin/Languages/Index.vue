<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <h2 class="text-lg font-semibold text-gray-900">{{ t('superadmin.languages') }}</h2>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                        @click="startCreate"
                    >
                        {{ t('common.add_language') }}
                    </button>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left: list -->
            <div class="space-y-3">
                <div
                    v-for="lang in languages"
                    :key="lang.id"
                    class="p-4 bg-white rounded-lg border border-gray-200 flex justify-between items-center cursor-pointer"
                    :class="editingLang?.id === lang.id ? 'border-blue-300 bg-blue-50' : ''"
                    @click="startEdit(lang)"
                >
                    <div>
                        <div class="font-medium text-gray-900">{{ lang.name }}</div>
                        <div class="text-sm text-gray-500">{{ lang.code }}</div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span v-if="lang.direction === 'rtl'" class="px-2 py-0.5 text-xs bg-amber-100 text-amber-800 rounded-full">{{ t('common.rtl') }}</span>
                            <span v-else class="px-2 py-0.5 text-xs bg-gray-100 text-gray-700 rounded-full">{{ t('common.ltr') }}</span>
                            <span v-if="lang.is_default" class="px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded-full">{{ t('common.default') }}</span>
                            <span v-if="!lang.is_active" class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded-full">{{ t('common.inactive') }}</span>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="text-red-700 hover:text-red-900"
                            @click.stop="destroy(lang)"
                        >
                                {{ t('common.delete') }}
                        </button>
                    </div>
                </div>

                <div v-if="!languages?.length" class="text-center text-sm text-gray-500 py-10">{{ t('superadmin.languages.empty') }}</div>
            </div>

            <!-- Right: form -->
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ editingLang?.id ? t('common.edit_language') : t('common.add_language') }}
                    </h3>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.code') }}</label>
                        <input
                            v-model="form.code"
                            class="w-full px-3 py-2 border rounded-md"
                            :disabled="!!editingLang"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.name') }}</label>
                        <input v-model="form.name" class="w-full px-3 py-2 border rounded-md" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.sort_order') }}</label>
                        <input
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                            class="w-full px-3 py-2 border rounded-md"
                        />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.direction') }}</label>
                            <select v-model="form.direction" class="w-full px-3 py-2 border rounded-md">
                                <option value="ltr">{{ t('common.ltr') }}</option>
                                <option value="rtl">{{ t('common.rtl') }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.font_family') }}</label>
                            <input
                                v-model="form.font_family"
                                class="w-full px-3 py-2 border rounded-md"
                                :placeholder="t('common.font_family_placeholder')"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" v-model="form.is_active" />
                            {{ t('common.active') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" v-model="form.is_default" />
                            {{ t('common.default') }}
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button
                        v-if="editingLang?.id"
                        type="button"
                        class="px-4 py-2 border rounded-lg hover:bg-gray-50"
                        @click="startCreate"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        :disabled="saving"
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
    languages: { type: Array, default: () => [] },
});

const saving = ref(false);
const editingLang = ref(null);

const form = reactive({
    code: '',
    name: '',
    sort_order: 0,
    is_active: true,
    is_default: false,
    direction: 'ltr',
    font_family: '',
});

function startCreate() {
    editingLang.value = null;
    form.code = '';
    form.name = '';
    form.sort_order = (props.languages?.length ? Math.max(...props.languages.map((l) => Number(l.sort_order || 0))) + 1 : 1) || 1;
    form.is_active = true;
    form.is_default = false;
    form.direction = 'ltr';
    form.font_family = '';
}

function startEdit(lang) {
    editingLang.value = lang;
    form.code = lang.code || '';
    form.name = lang.name || '';
    form.sort_order = lang.sort_order ?? 0;
    form.is_active = lang.is_active ?? true;
    form.is_default = lang.is_default ?? false;
    form.direction = lang.direction || 'ltr';
    form.font_family = lang.font_family || '';
}

async function save() {
    saving.value = true;
    try {
        const payload = {
            ...form,
            // backend expects null when empty
            font_family: form.font_family?.trim() ? form.font_family.trim() : null,
        };
        if (editingLang.value?.id) {
            await window.axios.put(route('settings.languages.update', editingLang.value.id), payload);
        } else {
            await window.axios.post(route('settings.languages.store'), payload);
        }
        startCreate();
        router.visit(route('superadmin.languages.index'), { preserveScroll: true, replace: true, preserveState: false });
    } finally {
        saving.value = false;
    }
}

async function destroy(lang) {
    const msg = t('common.confirm_delete_named').replace(':name', lang.name);
    if (!confirm(msg)) return;
    await window.axios.delete(route('settings.languages.destroy', lang.id));
    if (editingLang.value?.id === lang.id) startCreate();
    router.visit(route('superadmin.languages.index'), { preserveScroll: true, replace: true, preserveState: false });
}

watch(
    () => props.languages,
    (langs) => {
        // If current edit language is removed, fallback to create mode.
        if (editingLang.value?.id && !langs.some((l) => l.id === editingLang.value.id)) {
            startCreate();
        }
    },
    { deep: false }
);

startCreate();
</script>

