<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <h2 class="text-lg font-semibold text-gray-900">{{ t('superadmin.organizations') }}</h2>
                <button class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700" @click="startCreate">{{ t('common.add_organization') }}</button>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- left list -->
            <div class="space-y-3">
                <div
                    v-for="org in organizations"
                    :key="org.id"
                    class="p-4 bg-white rounded-lg border border-gray-200 flex justify-between items-center"
                    :class="editingOrganization?.id === org.id ? 'border-blue-300 bg-blue-50' : ''"
                >
                    <div>
                        <div class="font-medium text-gray-900">{{ org.name }}</div>
                        <div class="text-sm text-gray-500">{{ org.slug }} | Members: {{ org.users_count }}</div>
                        <div class="mt-2">
                            <span v-if="org.is_active" class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">{{ t('common.active') }}</span>
                            <span v-else class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">{{ t('common.inactive') }}</span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button class="text-blue-700 hover:text-blue-900" @click="startEdit(org)">{{ t('common.edit_organization') }}</button>
                        <button class="text-red-700 hover:text-red-900" @click="deleteOrganization(org)">{{ t('common.delete') }}</button>
                    </div>
                </div>
                <div v-if="!organizations?.length" class="text-center text-sm text-gray-500 py-6">{{ t('superadmin.organizations.empty') }}</div>
            </div>

            <!-- right form -->
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ editingOrganization?.id ? t('common.edit_organization') : t('common.add_organization') }}
                    </h3>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.name') }}</label>
                        <input v-model="organizationForm.name" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.slug') }}</label>
                        <input v-model="organizationForm.slug" class="w-full px-3 py-2 border rounded-md" />
                    </div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" v-model="organizationForm.is_active" />
                        {{ t('common.active') }}
                    </label>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button
                        v-if="editingOrganization?.id"
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
                        @click="saveOrganization"
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

const { t } = useI18n();

const props = defineProps({ organizations: { type: Array, default: () => [] }, users: { type: Array, default: () => [] } });

const editingOrganization = ref(null);
const saving = ref(false);
const organizationForm = reactive({ name: '', slug: '', is_active: true });

function startCreate() {
    editingOrganization.value = null;
    organizationForm.name = '';
    organizationForm.slug = '';
    organizationForm.is_active = true;
}

function startEdit(org) {
    editingOrganization.value = org;
    organizationForm.name = org?.name || '';
    organizationForm.slug = org?.slug || '';
    organizationForm.is_active = org?.is_active ?? true;
}

async function saveOrganization() {
    saving.value = true;
    try {
        const payload = { ...organizationForm };
        if (editingOrganization.value?.id) {
            await window.axios.put(route('settings.organizations.update', editingOrganization.value.id), payload);
        } else {
            await window.axios.post(route('settings.organizations.store'), payload);
        }
        startCreate();
        router.visit(route('superadmin.organizations.index'), { preserveScroll: true, replace: true });
    } finally {
        saving.value = false;
    }
}

async function deleteOrganization(organization) {
    const msg = t('common.confirm_delete_named').replace(':name', organization.name);
    if (!confirm(msg)) return;
    await window.axios.delete(route('settings.organizations.destroy', organization.id));
    if (editingOrganization.value?.id === organization.id) {
        startCreate();
    }
    router.visit(route('superadmin.organizations.index'), { preserveScroll: true, replace: true });
}
</script>

