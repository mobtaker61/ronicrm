<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Organization Admin Users</h2>
                    <p class="text-sm text-gray-500 mt-1">Users who have <code class="bg-gray-100 px-1">org_admin</code> role in at least one organization.</p>
                </div>
                <button
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium"
                    @click="openCreate()"
                >
                    Create admin
                </button>
            </div>
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.error }}
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Admins</h3>
                    <input
                        v-model="q"
                        type="text"
                        class="w-80 max-w-full px-3 py-2 border rounded-md text-sm"
                        placeholder="Search: name/email/username/organization…"
                    />
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Organizations</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="u in filteredUsers" :key="u.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ u.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">{{ u.username || '—' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">{{ u.email }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="o in u.organizations"
                                            :key="o.id"
                                            class="px-2 py-1 text-xs rounded-full"
                                            :class="o.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'"
                                            :title="`default=${o.is_default ? 1 : 0}`"
                                        >
                                            {{ o.name }}
                                        </span>
                                        <span v-if="!u.organizations?.length" class="text-sm text-gray-400">—</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button class="text-blue-600 hover:text-blue-900" @click="openEdit(u)">Edit</button>
                                </td>
                            </tr>
                            <tr v-if="filteredUsers.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">No results.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create/Edit modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="closeModal"
        >
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">{{ editingUser ? 'Edit admin' : 'Create admin' }}</h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form class="p-6 space-y-4" @submit.prevent="save">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Organization *</label>
                        <select v-model.number="form.organization_id" required class="w-full px-3 py-2 border rounded-md">
                            <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                        </select>
                        <div v-if="form.errors.organization_id" class="mt-1 text-sm text-red-600">{{ form.errors.organization_id }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                        <input v-model="form.name" type="text" required class="w-full px-3 py-2 border rounded-md" />
                        <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                        <input v-model="form.username" type="text" required class="w-full px-3 py-2 border rounded-md" />
                        <div v-if="form.errors.username" class="mt-1 text-sm text-red-600">{{ form.errors.username }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input v-model="form.email" type="email" required class="w-full px-3 py-2 border rounded-md" />
                        <div v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password {{ editingUser ? '(optional)' : '*' }}</label>
                        <input v-model="form.password" type="password" :required="!editingUser" class="w-full px-3 py-2 border rounded-md" />
                        <div v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</div>
                    </div>

                    <div v-if="!editingUser || form.password">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm password {{ editingUser ? '(when password is set)' : '*' }}</label>
                        <input v-model="form.password_confirmation" type="password" :required="!editingUser || !!form.password" class="w-full px-3 py-2 border rounded-md" />
                        <div v-if="form.errors.password_confirmation" class="mt-1 text-sm text-red-600">{{ form.errors.password_confirmation }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select v-model="form.status" class="w-full px-3 py-2 border rounded-md">
                                <option value="active">active</option>
                                <option value="inactive">inactive</option>
                            </select>
                            <div v-if="form.errors.status" class="mt-1 text-sm text-red-600">{{ form.errors.status }}</div>
                        </div>
                        <label class="flex items-center gap-2 mt-6">
                            <input v-model="form.is_default" type="checkbox" />
                            <span class="text-sm text-gray-700">Default</span>
                        </label>
                    </div>

                    <label class="flex items-center gap-2">
                        <input v-model="form.set_as_owner" type="checkbox" />
                        <span class="text-sm text-gray-700">Set as organization owner</span>
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 border rounded-lg hover:bg-gray-50" @click="closeModal">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : 'Save' }}
                        </button>
                    </div>

                    <div v-if="editingUser && editingUser.organizations?.length" class="pt-4 border-t border-gray-100">
                        <div class="text-sm font-medium text-gray-900 mb-2">Remove from organization</div>
                        <div class="space-y-2">
                            <button
                                v-for="o in editingUser.organizations"
                                :key="o.id"
                                type="button"
                                class="w-full text-left px-3 py-2 border rounded-md text-sm hover:bg-gray-50 flex items-center justify-between"
                                @click="removeFromOrg(o.id)"
                            >
                                <span>{{ o.name }}</span>
                                <span class="text-red-600">Remove</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    organizations: { type: Array, default: () => [] },
});

const q = ref('');
const users = computed(() => props.users || []);
const organizations = computed(() => props.organizations || []);

const filteredUsers = computed(() => {
    const s = String(q.value || '').trim().toLowerCase();
    if (!s) return users.value;
    return users.value.filter((u) => {
        const orgText = (u.organizations || []).map((o) => o.name).join(' ').toLowerCase();
        return (
            String(u.name || '').toLowerCase().includes(s) ||
            String(u.email || '').toLowerCase().includes(s) ||
            String(u.username || '').toLowerCase().includes(s) ||
            orgText.includes(s)
        );
    });
});

const showModal = ref(false);
const editingUser = ref(null);
const form = useForm({
    organization_id: 0,
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    status: 'active',
    is_default: true,
    set_as_owner: false,
});

function openCreate() {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    form.organization_id = organizations.value[0]?.id || 0;
    form.status = 'active';
    form.is_default = true;
    form.set_as_owner = true;
    showModal.value = true;
}

function openEdit(u) {
    editingUser.value = u;
    form.reset();
    form.clearErrors();
    form.organization_id = u.organizations?.[0]?.id || organizations.value[0]?.id || 0;
    form.name = u.name || '';
    form.username = u.username || '';
    form.email = u.email || '';
    form.password = '';
    form.password_confirmation = '';
    form.status = u.organizations?.[0]?.status || 'active';
    form.is_default = !!u.organizations?.[0]?.is_default;
    form.set_as_owner = false;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
}

function save() {
    if (editingUser.value) {
        form.put(route('superadmin.admin-users.update', editingUser.value.id), { preserveScroll: true });
    } else {
        form.post(route('superadmin.admin-users.store'), { preserveScroll: true });
    }
}

function removeFromOrg(orgId) {
    if (!editingUser.value) return;
    if (!confirm('Remove from this organization?')) return;
    const f = useForm({ organization_id: orgId });
    f.delete(route('superadmin.admin-users.organization.destroy', editingUser.value.id), { preserveScroll: true });
}
</script>

