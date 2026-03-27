<template>
    <div v-if="show" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[80vh] flex flex-col">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="text-lg font-semibold">{{ t('media_picker.title') }}</h3>
                <button type="button" @click="$emit('close')" class="p-2 text-gray-500 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="p-4 border-b flex items-center gap-2 flex-wrap">
                <button
                    type="button"
                    @click="loadFolder(null)"
                    :class="['px-3 py-1.5 rounded-lg text-sm', currentFolderId === null ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']"
                >
                    {{ t('common.all') }}
                </button>
                <template v-for="b in breadcrumbs" :key="b.id ?? 'root'">
                    <span class="text-gray-400">/</span>
                    <button
                        type="button"
                        @click="loadFolder(b.id)"
                        :class="['px-3 py-1.5 rounded-lg text-sm', currentFolderId === b.id ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']"
                    >
                        {{ b.name }}
                    </button>
                </template>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                <div v-if="loading" class="text-center py-8 text-gray-500">{{ t('common.loading') }}</div>
                <template v-else>
                    <div class="grid grid-cols-4 sm:grid-cols-6 gap-3 mb-4">
                        <button
                            v-for="f in folders"
                            :key="f.id"
                            type="button"
                            @click="loadFolder(f.id)"
                            class="flex flex-col items-center p-3 border rounded-lg hover:bg-gray-50"
                        >
                            <svg class="w-8 h-8 text-amber-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            <span class="text-xs truncate w-full text-center">{{ f.name }}</span>
                        </button>
                    </div>
                    <div class="grid grid-cols-4 sm:grid-cols-6 gap-3">
                        <button
                            v-for="file in files"
                            :key="file.id"
                            type="button"
                            @click="selectFile(file)"
                            class="flex flex-col items-center p-2 border rounded-lg hover:bg-blue-50 hover:border-blue-200"
                        >
                            <img v-if="file.is_image" :src="file.url" :alt="file.name" class="w-full aspect-square object-cover rounded mb-1" />
                            <div v-else class="w-full aspect-square bg-gray-100 rounded flex items-center justify-center mb-1">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span class="text-xs truncate w-full text-center">{{ file.name }}</span>
                        </button>
                    </div>
                    <p v-if="!loading && folders.length === 0 && files.length === 0" class="text-center text-gray-500 py-8">{{ t('media_picker.no_files_in_folder') }}</p>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { useI18n } from '@/composables/useI18n';

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(['close', 'select']);
const { t } = useI18n();

const loading = ref(false);
const currentFolderId = ref(null);
const folders = ref([]);
const files = ref([]);
const breadcrumbs = ref([]);

async function loadFolder(folderId) {
    currentFolderId.value = folderId;
    loading.value = true;
    try {
        const { data } = await axios.get(route('media.list'), { params: { folder_id: folderId || '' } });
        folders.value = data.folders || [];
        files.value = data.files || [];
        breadcrumbs.value = data.breadcrumbs || [];
    } finally {
        loading.value = false;
    }
}

function selectFile(file) {
    emit('select', { path: file.path, url: file.url, name: file.name, id: file.id, is_image: file.is_image });
    emit('close');
}

watch(() => props.show, (visible) => {
    if (visible) loadFolder(null);
});
</script>
