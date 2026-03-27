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
                        <div class="text-sm text-gray-500">
                            {{ org.slug }} · {{ t('settings.members') }}: {{ org.users_count }}
                        </div>
                        <div v-if="org.owner_name" class="text-sm text-gray-600 mt-1">
                            {{ t('superadmin.organizations.primary_admin') }}: {{ org.owner_name }}
                        </div>
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

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('superadmin.organizations.primary_admin') }}</label>
                        <select v-model.number="organizationForm.owner_user_id" class="w-full px-3 py-2 border rounded-md">
                            <option :value="emptyOwnerValue">{{ t('superadmin.organizations.owner_not_set') }}</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">{{ t('superadmin.organizations.primary_admin_help') }}</p>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <label class="flex items-center gap-2 mb-3">
                            <input type="checkbox" v-model="organizationForm.restrict_languages" />
                            <span class="text-sm font-medium text-gray-800">{{ t('superadmin.organizations.restrict_languages') }}</span>
                        </label>
                        <p class="text-xs text-gray-500 mb-3">{{ t('superadmin.organizations.allowed_languages_help') }}</p>
                        <div v-if="organizationForm.restrict_languages" class="space-y-2 max-h-48 overflow-y-auto border rounded-md p-3 bg-gray-50">
                            <label v-for="lang in all_languages" :key="lang.id" class="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    :value="lang.id"
                                    v-model="organizationForm.language_ids"
                                />
                                <span>{{ lang.name }} ({{ lang.code }})</span>
                            </label>
                        </div>
                    </div>
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

const props = defineProps({
    organizations: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    all_languages: { type: Array, default: () => [] },
    current_user_id: { type: Number, default: null },
});

const emptyOwnerValue = 0;

const editingOrganization = ref(null);
const saving = ref(false);
const organizationForm = reactive({
    name: '',
    slug: '',
    is_active: true,
    owner_user_id: emptyOwnerValue,
    restrict_languages: false,
    language_ids: [],
});

function startCreate() {
    editingOrganization.value = null;
    organizationForm.name = '';
    organizationForm.slug = '';
    organizationForm.is_active = true;
    organizationForm.owner_user_id = props.current_user_id || emptyOwnerValue;
    organizationForm.restrict_languages = false;
    organizationForm.language_ids = [];
}

function startEdit(org) {
    editingOrganization.value = org;
    organizationForm.name = org?.name || '';
    organizationForm.slug = org?.slug || '';
    organizationForm.is_active = org?.is_active ?? true;
    organizationForm.owner_user_id = org?.owner_user_id || emptyOwnerValue;
    const lids = org?.language_ids || [];
    organizationForm.restrict_languages = lids.length > 0;
    organizationForm.language_ids = lids.length > 0 ? [...lids] : [];
}

function saveOrganization() {
    if (organizationForm.restrict_languages && organizationForm.language_ids.length === 0) {
        window.alert(t('superadmin.organizations.select_at_least_one_language'));
        return;
    }
    saving.value = true;
    const ownerRaw = organizationForm.owner_user_id;
    const payload = {
        name: organizationForm.name,
        slug: organizationForm.slug,
        is_active: organizationForm.is_active,
        owner_user_id: ownerRaw && ownerRaw !== emptyOwnerValue ? ownerRaw : null,
        language_ids: organizationForm.restrict_languages
            ? organizationForm.language_ids.map((id) => Number(id))
            : [],
    };
    const options = {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
        onSuccess: () => {
            startCreate();
        },
        onError: (errors) => {
            const first =
                (errors && typeof errors === 'object' && Object.keys(errors).length
                    ? Object.values(errors).flat()[0]
                    : null) || errors?.message;
            window.alert(first || t('superadmin.organizations.save_failed'));
        },
    };
    if (editingOrganization.value?.id) {
        router.put(route('settings.organizations.update', editingOrganization.value.id), payload, options);
    } else {
        router.post(route('settings.organizations.store'), payload, options);
    }
}

function deleteOrganization(organization) {
    const msg = t('common.confirm_delete_named').replace(':name', organization.name);
    if (!confirm(msg)) return;
    router.delete(route('settings.organizations.destroy', organization.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (editingOrganization.value?.id === organization.id) {
                startCreate();
            }
        },
        onError: () => {
            window.alert(t('superadmin.organizations.save_failed'));
        },
    });
}
</script>

