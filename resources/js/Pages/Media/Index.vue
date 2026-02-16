<template>
    <AppLayout>
        <template #header>
            Media
        </template>

        <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg">
            {{ $page.props.flash.success }}
        </div>

        <div class="flex gap-6">
            <!-- Sidebar: Folder tree -->
            <div class="w-64 flex-shrink-0 bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-900">پوشه‌ها</h2>
                    <button
                        type="button"
                        @click="showNewFolderModal = true"
                        class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg"
                        title="پوشه جدید"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                </div>
                <Link
                    :href="route('media.index')"
                    :class="['block px-3 py-2 rounded-lg text-sm font-medium', !currentFolderId ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100']"
                >
                    همه فایل‌ها
                </Link>
                <Link
                    v-for="f in foldersTree"
                    :key="f.id"
                    :href="route('media.index', { folder_id: f.id })"
                    :class="['flex items-center gap-2 px-3 py-2 rounded-lg text-sm mt-1', currentFolderId == f.id ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100']"
                >
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                    <span class="truncate">{{ f.name }}</span>
                </Link>
            </div>

            <!-- Main: Breadcrumbs + Toolbar + Files -->
            <div class="flex-1 min-w-0">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <!-- Breadcrumbs -->
                    <div class="flex items-center gap-2 text-sm text-gray-600 mb-4 flex-wrap">
                        <Link v-for="(b, i) in breadcrumbs" :key="b.id ?? 'root'" :href="route('media.index', i === 0 ? {} : { folder_id: b.id })" class="hover:text-blue-600">
                            {{ b.name }}
                        </Link>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-medium text-gray-900">{{ currentFolder?.name || 'همه فایل‌ها' }}</h3>
                        <label class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg cursor-pointer hover:bg-blue-700 text-sm font-medium">
                            <input type="file" class="hidden" @change="handleUpload" multiple />
                            آپلود فایل
                        </label>
                    </div>

                    <!-- Child folders -->
                    <div v-if="childFolders.length" class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3 mb-6">
                        <Link
                            v-for="f in childFolders"
                            :key="f.id"
                            :href="route('media.index', { folder_id: f.id })"
                            class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50"
                        >
                            <div class="w-12 h-12 rounded-lg bg-amber-100 flex items-center justify-center mb-2">
                                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-900 truncate w-full text-center">{{ f.name }}</span>
                        </Link>
                    </div>

                    <!-- Files grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-4">
                        <div
                            v-for="file in files"
                            :key="file.id"
                            class="group relative border border-gray-200 rounded-lg overflow-hidden hover:shadow-md"
                        >
                            <a :href="file.url" target="_blank" class="block aspect-square bg-gray-100">
                                <img v-if="file.is_image" :src="file.url" :alt="file.name" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            </a>
                            <p class="p-2 text-xs text-gray-700 truncate" :title="file.name">{{ file.name }}</p>
                            <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button
                                    type="button"
                                    @click.prevent="deleteFile(file)"
                                    class="p-1.5 bg-red-600 text-white rounded hover:bg-red-700"
                                    title="حذف"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-if="files.length === 0 && childFolders.length === 0" class="text-center text-gray-500 py-12">فایلی در این پوشه نیست. آپلود کنید یا پوشه جدید بسازید.</p>
                </div>
            </div>
        </div>

        <!-- New folder modal -->
        <div v-if="showNewFolderModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showNewFolderModal = false">
            <div class="bg-white rounded-lg p-6 w-full max-w-sm shadow-xl">
                <h3 class="text-lg font-semibold mb-4">پوشه جدید</h3>
                <form @submit.prevent="createFolder">
                    <input v-model="newFolderName" type="text" required placeholder="نام پوشه" class="w-full px-3 py-2 border border-gray-300 rounded-lg mb-4" />
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="showNewFolderModal = false" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg">انصراف</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">ایجاد</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    foldersTree: { type: Array, default: () => [] },
    currentFolder: { type: Object, default: null },
    childFolders: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
});

const showNewFolderModal = ref(false);
const newFolderName = ref('');

const currentFolderId = computed(() => props.currentFolder?.id ?? null);

function createFolder() {
    router.post(route('media.folders.store'), {
        name: newFolderName.value,
        parent_id: currentFolderId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showNewFolderModal.value = false;
            newFolderName.value = '';
        },
    });
}

function handleUpload(e) {
    const list = e.target.files;
    if (!list?.length) return;
    const formData = new FormData();
    formData.append('folder_id', currentFolderId.value ?? '');
    if (list.length === 1) {
        formData.append('file', list[0]);
    } else {
        for (let i = 0; i < list.length; i++) formData.append('files[]', list[i]);
    }
    router.post(route('media.files.store'), formData, { preserveScroll: true, forceFormData: true });
    e.target.value = '';
}

function deleteFile(file) {
    if (!confirm('حذف این فایل؟')) return;
    router.delete(route('media.files.destroy', file.id), { preserveScroll: true });
}
</script>
