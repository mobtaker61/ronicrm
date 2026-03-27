<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <h2 class="text-lg font-semibold text-gray-900">{{ t('superadmin.social_media_platforms') }}</h2>
                <button
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    @click="startCreate"
                >
                    {{ t('common.add_platform') }}
                </button>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left: list -->
            <div class="space-y-3">
                <div
                    v-for="type in socialMediaTypes"
                    :key="type.id"
                    class="p-4 bg-white rounded-lg border border-gray-200 flex justify-between items-center"
                    :class="editingType?.id === type.id ? 'border-blue-300 bg-blue-50' : ''"
                >
                    <div class="flex items-center gap-3">
                        <div v-if="type.icon" class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 border border-gray-200">
                            <i :class="type.icon" class="text-xl text-gray-700"></i>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900">{{ type.name }}</div>
                            <div v-if="type.base_url" class="text-sm text-gray-500 break-all">{{ type.base_url }}</div>
                            <div v-else class="text-sm text-gray-500">{{ t('common.dash') }}</div>
                            <div class="mt-2">
                                <span v-if="type.is_active" class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">{{ t('common.active') }}</span>
                                <span v-else class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">{{ t('common.inactive') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button class="text-blue-700 hover:text-blue-900" @click="startEdit(type)">{{ t('common.edit_platform') }}</button>
                        <button class="text-red-700 hover:text-red-900" @click="destroy(type)">
                            {{ t('common.delete') }}
                        </button>
                    </div>
                </div>
                <div v-if="!socialMediaTypes?.length" class="text-center text-sm text-gray-500 py-6">{{ t('superadmin.social_media_platforms.empty') }}</div>
            </div>

            <!-- Right: form -->
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ editingType?.id ? t('common.edit_platform') : t('common.add_platform') }}
                    </h3>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.name') }}</label>
                        <input v-model="form.name" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.icon_class') }}</label>
                        <input v-model="form.icon" class="w-full px-3 py-2 border rounded-md" :placeholder="t('settings.icon_placeholder')" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.base_url') }}</label>
                        <input v-model="form.base_url" class="w-full px-3 py-2 border rounded-md" :placeholder="t('settings.base_url_placeholder')" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.sort_order') }}</label>
                            <input v-model.number="form.sort_order" type="number" min="0" class="w-full px-3 py-2 border rounded-md" />
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 mb-1">
                                <input type="checkbox" v-model="form.is_active" />
                                {{ t('common.active') }}
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button
                        v-if="editingType?.id"
                        class="px-4 py-2 border rounded-lg hover:bg-gray-50"
                        @click="startCreate"
                        type="button"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        :disabled="saving"
                        @click="save"
                        type="button"
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
import { reactive, ref } from 'vue';
import { useI18n } from '@/composables/useI18n';

const props = defineProps({ socialMediaTypes: { type: Array, default: () => [] } });
const { t } = useI18n();

const editingType = ref(null);
const saving = ref(false);
const form = reactive({ name: '', icon: '', base_url: '', sort_order: 0, is_active: true });

function resetForm() {
    form.name = '';
    form.icon = '';
    form.base_url = '';
    form.sort_order = 0;
    form.is_active = true;
    editingType.value = null;
}

function startCreate() {
    resetForm();
}

function startEdit(v) {
    editingType.value = v;
    form.name = v?.name || '';
    form.icon = v?.icon || '';
    form.base_url = v?.base_url || '';
    form.sort_order = v?.sort_order ?? 0;
    form.is_active = v?.is_active ?? true;
}

async function save() {
    saving.value = true;
    try {
        const payload = { ...form };
        if (editingType.value?.id) {
            await window.axios.put(route('settings.social-media-types.update', editingType.value.id), payload);
        } else {
            await window.axios.post(route('settings.social-media-types.store'), payload);
        }
        startCreate();
        router.visit(route('superadmin.social-media-platforms.index'), { preserveScroll: true, replace: true });
    } finally {
        saving.value = false;
    }
}

async function destroy(v) {
    const msg = t('common.confirm_delete_named').replace(':name', v.name);
    if (!confirm(msg)) return;
    await window.axios.delete(route('settings.social-media-types.destroy', v.id));
    if (editingType.value?.id === v.id) {
        startCreate();
    }
    router.visit(route('superadmin.social-media-platforms.index'), { preserveScroll: true, replace: true });
}
</script>

