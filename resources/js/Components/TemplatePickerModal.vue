<template>
    <div v-if="show" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl h-[500px] flex flex-col overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b flex-shrink-0">
                <h3 class="text-lg font-semibold text-gray-900">Select Template</h3>
                <button type="button" @click="$emit('close')" class="p-2 text-gray-500 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="flex-1 flex min-h-0 overflow-hidden">
                <!-- Left: Template list -->
                <div class="w-[40%] border-r border-gray-200 overflow-y-auto flex-shrink-0 rtl:border-r-0 rtl:border-l">
                    <div class="p-2">
                        <div
                            v-for="t in templates"
                            :key="t.id"
                            @click="selectedTemplate = t"
                            :class="[
                                'px-4 py-3 rounded-lg cursor-pointer transition-colors text-left rtl:text-right',
                                selectedTemplate?.id === t.id
                                    ? 'bg-blue-100 border border-blue-300 text-blue-900'
                                    : 'hover:bg-gray-50 border border-transparent'
                            ]"
                        >
                            <p class="font-medium text-gray-900 truncate">{{ t.name }}</p>
                            <p v-if="t.content" class="text-xs text-gray-500 truncate mt-0.5">{{ stripHtml(t.content) }}</p>
                        </div>
                        <p v-if="templates.length === 0" class="text-center text-gray-500 py-8">No templates found.</p>
                    </div>
                </div>

                <!-- Right: Preview -->
                <div class="flex-1 flex flex-col min-w-0 p-6">
                    <div v-if="selectedTemplate" class="flex-1 overflow-y-auto min-h-0">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Preview</h4>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 min-h-[120px]">
                            <img
                                v-if="selectedTemplate.image"
                                :src="selectedTemplate.image"
                                alt="Template"
                                class="max-w-full max-h-48 object-contain rounded-lg mb-3"
                            />
                            <div
                                v-if="selectedTemplate.content"
                                class="text-gray-800 whitespace-pre-wrap text-sm"
                                v-html="selectedTemplate.content"
                            ></div>
                            <p v-else class="text-gray-500 italic">Image only</p>
                        </div>
                    </div>
                    <div v-else class="flex-1 flex items-center justify-center text-gray-500 min-h-0">
                        <p>Select a template from the list</p>
                    </div>

                    <div class="mt-4 flex-shrink-0 flex justify-end gap-2">
                        <button
                            type="button"
                            @click="$emit('close')"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="addToInput"
                            :disabled="!selectedTemplate"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Add to message box
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    templates: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'select']);

const selectedTemplate = ref(null);

watch(() => props.show, (visible) => {
    if (!visible) selectedTemplate.value = null;
});

function stripHtml(html) {
    if (!html) return '';
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}

function addToInput() {
    if (!selectedTemplate.value) return;
    emit('select', {
        content: selectedTemplate.value.content || '',
        image: selectedTemplate.value.image || null,
    });
    emit('close');
}
</script>
