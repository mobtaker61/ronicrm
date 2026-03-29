<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">{{ t('settings.users_management') }}</h2>
                    <p v-if="userManagementScope === 'organization'" class="text-sm text-gray-500 mt-1">{{ t('settings.users_org_scope_hint') }}</p>
                </div>
                <Link
                    :href="route('settings.index', { tab: 'organization' })"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                    {{ t('settings.back_to_settings') }}
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Success/Error Messages -->
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.error }}
            </div>

            <!-- Users List -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ userManagementScope === 'organization' ? t('settings.org_members') : t('settings.all_users') }}
                    </h3>
                    <button
                        @click="showCreateModal = true"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium"
                    >
                        {{ t('settings.add_new_user') }}
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.name') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.username') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.email') }}</th>
                                <th
                                    v-if="userManagementScope === 'global'"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    {{ t('common.roles') }}
                                </th>
                                <template v-else>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('settings.org_role') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.status') }}</th>
                                </template>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.created') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">{{ user.username || t('common.dash') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">{{ user.email }}</div>
                                </td>
                                <td v-if="userManagementScope === 'global'" class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="role in user.roles"
                                            :key="role"
                                            class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full"
                                        >
                                            {{ role }}
                                        </span>
                                        <span v-if="!user.roles || user.roles.length === 0" class="text-sm text-gray-400">{{ t('settings.no_roles') }}</span>
                                    </div>
                                </td>
                                <template v-else>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-900">{{ formatOrgRole(user.role_in_org) }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="px-2 py-1 text-xs rounded-full"
                                            :class="user.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
                                        >
                                            {{ user.status === 'active' ? t('common.active') : t('common.inactive') }}
                                        </span>
                                    </td>
                                </template>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">{{ formatDate(user.created_at) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button
                                        @click="editUser(user)"
                                        class="text-blue-600 hover:text-blue-900 ltr:mr-4 rtl:ml-4"
                                    >
                                        {{ t('common.edit') }}
                                    </button>
                                    <button
                                        @click="deleteUser(user)"
                                        :disabled="user.id === $page.props.auth?.user?.id"
                                        :class="[
                                            'text-red-600 hover:text-red-900',
                                            user.id === $page.props.auth?.user?.id ? 'opacity-50 cursor-not-allowed' : ''
                                        ]"
                                    >
                                        {{ userManagementScope === 'organization' ? t('settings.remove_from_organization') : t('common.delete') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="users.length === 0" class="px-6 py-8 text-center text-gray-500">
                        <p>{{ t('settings.no_users_found') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit User Modal -->
        <div
            v-if="showCreateModal || showEditModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="closeModal"
        >
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ showEditModal ? t('settings.edit_user') : t('settings.create_new_user') }}
                    </h3>
                    <button
                        @click="closeModal"
                        class="text-gray-400 hover:text-gray-500"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="saveUser" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.name') }} *</label>
                        <input
                            v-model="userForm.name"
                            type="text"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <div v-if="userForm.errors.name" class="mt-1 text-sm text-red-600">{{ userForm.errors.name }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.username') }} *</label>
                        <input
                            v-model="userForm.username"
                            type="text"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <div v-if="userForm.errors.username" class="mt-1 text-sm text-red-600">{{ userForm.errors.username }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.email') }} *</label>
                        <input
                            v-model="userForm.email"
                            type="email"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <div v-if="userForm.errors.email" class="mt-1 text-sm text-red-600">{{ userForm.errors.email }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ t('auth.password') }} {{ showEditModal ? t('settings.leave_blank_keep_current') : '*' }}
                        </label>
                        <input
                            v-model="userForm.password"
                            type="password"
                            :required="!showEditModal"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <div v-if="userForm.errors.password" class="mt-1 text-sm text-red-600">{{ userForm.errors.password }}</div>
                    </div>

                    <div v-if="!showEditModal || userForm.password">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.confirm_password_required') }}</label>
                        <input
                            v-model="userForm.password_confirmation"
                            type="password"
                            :required="!showEditModal || !!userForm.password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <div v-if="userForm.errors.password_confirmation" class="mt-1 text-sm text-red-600">{{ userForm.errors.password_confirmation }}</div>
                    </div>

                    <div v-if="userManagementScope === 'global'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.roles') }}</label>
                        <div class="space-y-2">
                            <label
                                v-for="role in roles"
                                :key="role.id"
                                class="flex items-center space-x-2 rtl:space-x-reverse"
                            >
                                <input
                                    v-model="userForm.roles"
                                    type="checkbox"
                                    :value="role.name"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="text-sm text-gray-700">{{ role.name }}</span>
                            </label>
                        </div>
                        <div v-if="userForm.errors.roles" class="mt-1 text-sm text-red-600">{{ userForm.errors.roles }}</div>
                    </div>
                    <div v-else class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.org_role') }} *</label>
                            <select v-model="userForm.role_in_org" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="org_admin">{{ t('settings.org_admin') }}</option>
                                <option value="org_manager">{{ t('settings.org_manager') }}</option>
                                <option value="org_agent">{{ t('settings.org_agent') }}</option>
                            </select>
                            <div v-if="userForm.errors.role_in_org" class="mt-1 text-sm text-red-600">{{ userForm.errors.role_in_org }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.status') }} *</label>
                            <select v-model="userForm.status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="active">{{ t('common.active') }}</option>
                                <option value="inactive">{{ t('common.inactive') }}</option>
                            </select>
                            <div v-if="userForm.errors.status" class="mt-1 text-sm text-red-600">{{ userForm.errors.status }}</div>
                        </div>
                        <label class="flex items-center gap-2">
                            <input v-model="userForm.is_default" type="checkbox" class="rounded border-gray-300 text-blue-600" />
                            <span class="text-sm text-gray-700">{{ t('settings.default_organization_for_user') }}</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3 rtl:space-x-reverse pt-4">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                        >
                            {{ t('common.cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="userForm.processing"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ userForm.processing ? t('profile.saving') : t('common.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    roles: {
        type: Array,
        default: () => [],
    },
    userManagementScope: {
        type: String,
        default: 'global',
    },
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingUser = ref(null);

const userForm = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [],
    role_in_org: 'org_agent',
    status: 'active',
    is_default: false,
});

const formatOrgRole = (role) => {
    if (role === 'org_admin') {
        return t('settings.org_admin');
    }
    if (role === 'org_manager') {
        return t('settings.org_manager');
    }
    if (role === 'org_agent') {
        return t('settings.org_agent');
    }
    return role || '—';
};

const editUser = (user) => {
    editingUser.value = user;
    userForm.name = user.name;
    userForm.username = user.username;
    userForm.email = user.email;
    userForm.password = '';
    userForm.password_confirmation = '';
    if (props.userManagementScope === 'organization') {
        userForm.roles = [];
        userForm.role_in_org = user.role_in_org || 'org_agent';
        userForm.status = user.status || 'active';
        userForm.is_default = !!user.is_default;
    } else {
        userForm.roles = user.roles || [];
    }
    showEditModal.value = true;
};

const deleteUser = (user) => {
    const msg =
        props.userManagementScope === 'organization'
            ? t('settings.confirm_remove_user_from_org').replace(':name', user.name)
            : t('settings.confirm_delete_user').replace(':name', user.name);
    if (confirm(msg)) {
        router.delete(route('settings.users.destroy', user.id), {
            preserveState: true,
            preserveScroll: true,
        });
    }
};

const saveUser = () => {
    if (showEditModal.value && editingUser.value) {
        userForm.put(route('settings.users.update', editingUser.value.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
            },
        });
    } else {
        userForm.post(route('settings.users.store'), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
            },
        });
    }
};

const closeModal = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    editingUser.value = null;
    userForm.reset();
    userForm.role_in_org = 'org_agent';
    userForm.status = 'active';
    userForm.is_default = false;
    userForm.clearErrors();
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>
