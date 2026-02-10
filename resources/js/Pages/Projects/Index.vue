<template>
    <AppLayout>
        <template #header>
            Projects
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.error }}
            </div>

            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-bold text-gray-900">Projects</h2>
                <button
                    @click="openCreateModal"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Add Project
                </button>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date / Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contacts</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Share Link</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="project in projects" :key="project.id">
                                <td class="px-6 py-4">
                                    <span class="font-medium text-gray-900">{{ project.name }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                    {{ project.description || '—' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <span v-if="project.start_date">{{ formatDate(project.start_date) }}</span>
                                    <span v-if="project.end_date"> to {{ formatDate(project.end_date) }}</span>
                                    <span v-if="project.location" class="block mt-1">{{ project.location }}</span>
                                    <span v-if="!project.start_date && !project.end_date && !project.location">—</span>
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
                                            title="Copy link"
                                        >
                                            Copy
                                        </button>
                                    </div>
                                    <span v-else class="text-gray-400 text-sm">Disabled</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="getShareUrl(project)"
                                            target="_blank"
                                            class="text-blue-600 hover:text-blue-800 text-sm"
                                        >
                                            Preview
                                        </Link>
                                        <button
                                            @click="editProject(project)"
                                            class="text-blue-600 hover:text-blue-800 text-sm"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            @click="deleteProject(project)"
                                            class="text-red-600 hover:text-red-800 text-sm"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="!projects || projects.length === 0" class="text-center py-12 text-gray-500">
                    No projects yet. Create your first project.
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
                        {{ editingProject ? 'Edit Project' : 'Add Project' }}
                    </h3>
                    <form @submit.prevent="saveProject">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Project Name *</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                ></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                    <input
                                        v-model="form.start_date"
                                        type="date"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                    <input
                                        v-model="form.end_date"
                                        type="date"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Location (venue / exhibition)</label>
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
                                <label for="share_enabled" class="ml-2 text-sm text-gray-700">Enable public share link</label>
                            </div>
                            <div class="flex items-center">
                                <input
                                    v-model="form.allow_excel_export"
                                    type="checkbox"
                                    id="allow_excel_export"
                                    class="h-4 w-4 text-blue-600 rounded border-gray-300"
                                />
                                <label for="allow_excel_export" class="ml-2 text-sm text-gray-700">Allow Excel export of contacts on share page</label>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 mt-6">
                            <button
                                type="button"
                                @click="closeModal"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ form.processing ? 'Saving...' : 'Save' }}
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
        alert('Link copied.');
    });
}

function deleteProject(project) {
    if (confirm('Are you sure you want to delete this project? Contacts will not be deleted, only unlinked from the project.')) {
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
