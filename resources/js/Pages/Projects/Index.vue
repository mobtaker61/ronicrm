<template>
    <AppLayout>
        <template #header>
            {{ t('sidebar.projects') }}
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.error }}
            </div>

            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-bold text-gray-900">{{ t('sidebar.projects') }}</h2>
                <button
                    @click="openCreateModal"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    {{ t('projects.add_project') }}
                </button>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.name') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.description') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('projects.date_location') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('projects.contacts') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('projects.share_link') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="project in projects" :key="project.id">
                                <td class="px-6 py-4">
                                    <span class="font-medium text-gray-900">{{ project.name }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                    {{ project.description || t('common.dash') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <span v-if="project.start_date">{{ formatDate(project.start_date) }}</span>
                                    <span v-if="project.end_date"> {{ t('common.to') }} {{ formatDate(project.end_date) }}</span>
                                    <span v-if="project.location" class="block mt-1">{{ project.location }}</span>
                                    <span v-if="!project.start_date && !project.end_date && !project.location">{{ t('common.dash') }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ project.customers_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="project.is_share_enabled" class="flex items-center gap-2">
                                        <input
                                            :ref="el => shareInputRefs[project.id] = el"
                                            :value="getShareUrl(project)"
                                            type="text"
                                            readonly
                                            class="w-48 px-2 py-1 text-xs border border-gray-300 rounded bg-gray-50"
                                        />
                                        <button
                                            type="button"
                                            @click="copyShareLink(project)"
                                            class="px-2 py-1 text-xs text-blue-600 hover:bg-blue-50 rounded"
                                            :title="t('projects.copy_link')"
                                        >
                                            {{ t('common.copy') }}
                                        </button>
                                    </div>
                                    <span v-else class="text-gray-400 text-sm">{{ t('common.disabled') }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="getShareUrl(project)"
                                            target="_blank"
                                            class="text-blue-600 hover:text-blue-800 text-sm"
                                        >
                                            {{ t('common.preview') }}
                                        </Link>
                                        <button
                                            @click="editProject(project)"
                                            class="text-blue-600 hover:text-blue-800 text-sm"
                                        >
                                            {{ t('common.edit') }}
                                        </button>
                                        <button
                                            @click="deleteProject(project)"
                                            class="text-red-600 hover:text-red-800 text-sm"
                                        >
                                            {{ t('common.delete') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="!projects || projects.length === 0" class="text-center py-12 text-gray-500">
                    {{ t('projects.empty') }}
                </div>
            </div>

            <!-- Create/Edit Modal -->
            <div
                v-if="showModal"
                class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
                @click.self="closeModal"
            >
                <div class="relative top-10 mx-auto p-6 border w-full max-w-lg shadow-lg rounded-lg bg-white max-h-[90vh] overflow-y-auto">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        {{ editingProject ? t('projects.edit_project') : t('projects.add_project') }}
                    </h3>
                    <form @submit.prevent="saveProject">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('projects.project_name_required') }}</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.description') }}</label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                ></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('projects.start_date') }}</label>
                                    <input
                                        v-model="form.start_date"
                                        type="date"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('projects.end_date') }}</label>
                                    <input
                                        v-model="form.end_date"
                                        type="date"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('projects.location_venue') }}</label>
                                <input
                                    v-model="form.location"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div class="flex items-center">
                                <input
                                    v-model="form.is_share_enabled"
                                    type="checkbox"
                                    id="share_enabled"
                                    class="h-4 w-4 text-blue-600 rounded border-gray-300"
                                />
                                <label for="share_enabled" class="ltr:ml-2 rtl:mr-2 text-sm text-gray-700">{{ t('projects.enable_public_share') }}</label>
                            </div>
                            <div class="flex items-center">
                                <input
                                    v-model="form.allow_excel_export"
                                    type="checkbox"
                                    id="allow_excel_export"
                                    class="h-4 w-4 text-blue-600 rounded border-gray-300"
                                />
                                <label for="allow_excel_export" class="ltr:ml-2 rtl:mr-2 text-sm text-gray-700">{{ t('projects.allow_excel_export') }}</label>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 mt-6">
                            <button
                                type="button"
                                @click="closeModal"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                {{ t('common.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ form.processing ? t('common.saving') : t('common.save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    projects: Array,
});

const showModal = ref(false);
const editingProject = ref(null);
const shareInputRefs = ref({});

const form = useForm({
    name: '',
    description: '',
    start_date: '',
    end_date: '',
    location: '',
    is_share_enabled: true,
    allow_excel_export: true,
});

function getShareUrl(project) {
    if (!project.share_token) return '';
    return window.location.origin + '/p/' + project.share_token;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return new Intl.DateTimeFormat('en-US', { year: 'numeric', month: 'short', day: 'numeric' }).format(d);
}

function openCreateModal() {
    editingProject.value = null;
    form.reset();
    form.is_share_enabled = true;
    form.allow_excel_export = true;
    showModal.value = true;
}

function editProject(project) {
    editingProject.value = project;
    form.name = project.name;
    form.description = project.description || '';
    form.start_date = project.start_date ? project.start_date.slice(0, 10) : '';
    form.end_date = project.end_date ? project.end_date.slice(0, 10) : '';
    form.location = project.location || '';
    form.is_share_enabled = project.is_share_enabled !== false;
    form.allow_excel_export = project.allow_excel_export !== false;
    showModal.value = true;
}

function copyShareLink(project) {
    const url = getShareUrl(project);
    navigator.clipboard.writeText(url).then(() => {
        alert(t('projects.link_copied'));
    });
}

function deleteProject(project) {
    if (confirm(t('projects.confirm_delete'))) {
        router.delete(route('projects.destroy', project.id));
    }
}

function saveProject() {
    if (editingProject.value) {
        form.put(route('projects.update', editingProject.value.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('projects.store'), {
            onSuccess: () => closeModal(),
        });
    }
}

function closeModal() {
    showModal.value = false;
    editingProject.value = null;
    form.reset();
}
</script>
