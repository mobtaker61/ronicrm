<template>
    <AppLayout>
        <template #header>
            Edit Scraping Task
        </template>

        <div class="max-w-4xl mx-auto" dir="ltr">
            <form @submit.prevent="submit" class="bg-white rounded-lg shadow p-6 space-y-6">
                <div v-if="Object.keys(form.errors).length" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                    <ul class="list-disc list-inside">
                        <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                    </ul>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Task type *</label>
                    <div class="flex gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.type" type="radio" value="list" class="rounded border-gray-300" />
                            <span>List extraction</span>
                            <span class="text-xs text-gray-500">— one URL, extract repeating elements</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.type" type="radio" value="detail" class="rounded border-gray-300" />
                            <span>Detail extraction</span>
                            <span class="text-xs text-gray-500">— multiple URLs, multiple fields per page</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Task name *</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g. Scrape store products"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description (optional)</label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Short description of the task"
                    />
                </div>

                <template v-if="form.type === 'list'">
                    <div class="border-t pt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Page URL *</label>
                        <input
                            v-model="form.listUrl"
                            type="url"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                            placeholder="https://example.com/list-page"
                        />
                    </div>
                    <div class="border-t pt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Repeating elements selector *</label>
                        <p class="text-xs text-gray-500 mb-2">XPath / class / id that matches multiple elements.</p>
                        <div class="flex flex-wrap items-center gap-3 p-4 bg-gray-50 rounded-lg">
                            <select
                                v-model="form.list_config.selector_type"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm"
                            >
                                <option value="xpath">XPath</option>
                                <option value="class">Class</option>
                                <option value="id">ID</option>
                            </select>
                            <input
                                v-model="form.list_config.selector_value"
                                type="text"
                                :placeholder="form.list_config.selector_type === 'xpath' ? '//div[@class=\'item\']/a' : form.list_config.selector_type === 'class' ? 'item-link' : 'list-item'"
                                class="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 rounded-md text-sm font-mono"
                            />
                            <select
                                v-model="form.list_config.value_kind"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm"
                            >
                                <option value="text">Element text</option>
                                <option value="attribute">Attribute</option>
                            </select>
                            <input
                                v-if="form.list_config.value_kind === 'attribute'"
                                v-model="form.list_config.value_attr"
                                type="text"
                                placeholder="e.g. href or src"
                                class="w-28 px-3 py-2 border border-gray-300 rounded-md text-sm"
                            />
                        </div>
                    </div>
                    <div class="border-t pt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Delay before extraction (seconds)</label>
                        <p class="text-xs text-gray-500 mb-2">Wait time to allow dynamic content to load. Leave empty for no delay.</p>
                        <input
                            v-model.number="form.list_config.delay_seconds"
                            type="number"
                            min="0"
                            max="300"
                            class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                            placeholder="0"
                        />
                    </div>
                    <div class="border-t pt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pagination</label>
                        <p class="text-xs text-gray-500 mb-2">If the list is paginated, configure how to navigate pages.</p>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Pagination type</label>
                                <select
                                    v-model="form.list_config.pagination_type"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                >
                                    <option value="">None (single page)</option>
                                    <option value="next_page">Next Page (link/button)</option>
                                    <option value="load_more">Load More (button)</option>
                                </select>
                            </div>
                            <template v-if="form.list_config.pagination_type">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Pagination selector *</label>
                                    <div class="flex flex-wrap items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                        <select
                                            v-model="form.list_config.pagination_selector_type"
                                            class="px-3 py-2 border border-gray-300 rounded-md text-sm"
                                        >
                                            <option value="xpath">XPath</option>
                                            <option value="class">Class</option>
                                            <option value="id">ID</option>
                                        </select>
                                        <input
                                            v-model="form.list_config.pagination_selector_value"
                                            type="text"
                                            :placeholder="form.list_config.pagination_selector_type === 'xpath' ? '//a[@class=\'next\']' : form.list_config.pagination_selector_type === 'class' ? 'next-page' : 'load-more-btn'"
                                            class="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 rounded-md text-sm font-mono"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Max pages *</label>
                                    <input
                                        v-model.number="form.list_config.max_pages"
                                        type="number"
                                        min="1"
                                        max="1000"
                                        class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                        placeholder="10"
                                    />
                                    <p class="text-xs text-gray-500 mt-1">Maximum number of pages to scrape.</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <template v-else>
                <div class="border-t pt-6">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700">Page URLs (one per line) *</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                @click="pasteUrls"
                                class="text-sm text-blue-600 hover:text-blue-800"
                            >
                                Paste from clipboard
                            </button>
                            <label class="text-sm text-blue-600 hover:text-blue-800 cursor-pointer">
                                Import from file
                                <input type="file" accept=".txt,.csv" class="hidden" @change="importUrlsFromFile" />
                            </label>
                            <template v-if="listTasks?.length">
                                <span class="text-gray-400">|</span>
                                <select
                                    v-model="selectedListTaskId"
                                    class="text-sm border border-gray-300 rounded px-2 py-1"
                                >
                                    <option value="">Select a list task...</option>
                                    <option v-for="t in listTasks" :key="t.id" :value="t.id">
                                        {{ t.name }} ({{ t.items_count }} urls)
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    @click="loadUrlsFromListTask"
                                    :disabled="!selectedListTaskId || loadListUrlsLoading"
                                    class="text-sm text-blue-600 hover:text-blue-800 disabled:opacity-50"
                                >
                                    {{ loadListUrlsLoading ? 'Loading...' : 'Load URLs from this task' }}
                                </button>
                            </template>
                        </div>
                    </div>
                    <textarea
                        v-model="form.urlText"
                        rows="8"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                        placeholder="https://example.com/page1&#10;https://example.com/page2&#10;..."
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        One URL per line. You can load from a list task, file, or clipboard.
                    </p>
                    <p v-if="urlCount > 0" class="mt-1 text-sm text-gray-600">{{ urlCount }} URLs detected.</p>
                </div>

                <div class="border-t pt-6">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700">Extraction fields *</label>
                        <button
                            type="button"
                            @click="addParam"
                            class="px-3 py-1 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300"
                        >
                            + Add field
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">
                        Define the fields you want to extract from each page.
                    </p>
                    <div class="space-y-4">
                        <div
                            v-for="(param, index) in form.extract_params"
                            :key="index"
                            class="flex flex-wrap items-center gap-3 p-3 bg-gray-50 rounded-lg"
                        >
                            <input
                                v-model="param.name"
                                type="text"
                                placeholder="Field name"
                                class="flex-1 min-w-[120px] px-3 py-2 border border-gray-300 rounded-md text-sm"
                            />
                            <select
                                v-model="param.selector_type"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm"
                            >
                                <option value="xpath">XPath</option>
                                <option value="class">Class</option>
                                <option value="id">ID</option>
                            </select>
                            <input
                                v-model="param.selector_value"
                                type="text"
                                :placeholder="param.selector_type === 'xpath' ? '//h1' : param.selector_type === 'class' ? '.title' : '#product-name'"
                                class="flex-1 min-w-[180px] px-3 py-2 border border-gray-300 rounded-md text-sm font-mono"
                            />
                            <button
                                type="button"
                                @click="removeParam(index)"
                                class="p-2 text-red-600 hover:bg-red-50 rounded"
                                title="Remove"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p v-if="form.extract_params.length === 0" class="text-sm text-amber-600 mt-2">
                        Add at least one field.
                    </p>
                </div>
                </template>

                <div class="flex gap-3 pt-4">
                    <button
                        type="submit"
                        :disabled="!canSubmit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Save changes
                    </button>
                    <Link
                        :href="route('scrap-tasks.show', task.id)"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    task: { type: Object, required: true },
    listTasks: { type: Array, default: () => [] },
});

const task = props.task;
const selectedListTaskId = ref('');
const loadListUrlsLoading = ref(false);

const initialListUrl = task.type === 'list' && task.urls?.length ? (task.urls[0]?.url || '') : '';
const initialListConfig = task.list_config ? {
    selector_type: task.list_config.selector_type || 'xpath',
    selector_value: task.list_config.selector_value || '',
    value_kind: task.list_config.value_kind || 'text',
    value_attr: task.list_config.value_attr || '',
    delay_seconds: task.list_config.delay_seconds || null,
    pagination_type: task.list_config.pagination_type || '',
    pagination_selector_type: task.list_config.pagination_selector_type || 'xpath',
    pagination_selector_value: task.list_config.pagination_selector_value || '',
    max_pages: task.list_config.max_pages || null,
} : {
    selector_type: 'xpath',
    selector_value: '',
    value_kind: 'text',
    value_attr: '',
    delay_seconds: null,
    pagination_type: '',
    pagination_selector_type: 'xpath',
    pagination_selector_value: '',
    max_pages: null,
};
const initialUrlText = task.type === 'detail' && task.urls?.length
    ? task.urls.map(u => u.url).join('\n')
    : '';
const initialParams = task.type === 'detail' && task.extract_params?.length
    ? task.extract_params.map(p => ({
        name: p.name,
        selector_type: p.selector_type || 'xpath',
        selector_value: p.selector_value || '',
    }))
    : [{ name: '', selector_type: 'xpath', selector_value: '' }];

const form = useForm({
    type: task.type || 'detail',
    name: task.name || '',
    description: task.description || '',
    listUrl: initialListUrl,
    list_config: initialListConfig,
    urlText: initialUrlText,
    urls: [],
    extract_params: initialParams,
});

const urlCount = computed(() => {
    const lines = (form.urlText || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
    const urls = lines.filter(line => /^https?:\/\//i.test(line));
    return urls.length;
});

const canSubmit = computed(() => {
    if (form.type === 'list') {
        const u = (form.listUrl || '').trim();
        const okUrl = /^https?:\/\//i.test(u);
        const okSelector = !!((form.list_config?.selector_value || '').trim());
        return okUrl && okSelector;
    }
    return urlCount.value >= 1 && form.extract_params.filter(p => p.name && p.selector_value).length >= 1;
});

function addParam() {
    form.extract_params.push({ name: '', selector_type: 'xpath', selector_value: '' });
}

function removeParam(index) {
    form.extract_params.splice(index, 1);
}

async function pasteUrls() {
    try {
        const text = await navigator.clipboard.readText();
        form.urlText = (form.urlText ? form.urlText + '\n' : '') + text;
    } catch {
        alert('Clipboard access is not available.');
    }
}

function importUrlsFromFile(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => {
        form.urlText = (form.urlText ? form.urlText + '\n' : '') + (ev.target?.result || '');
    };
    reader.readAsText(file);
    e.target.value = '';
}

async function loadUrlsFromListTask() {
    if (!selectedListTaskId.value) return;
    loadListUrlsLoading.value = true;
    try {
        const { data } = await axios.get(route('scrap-tasks.result-urls', selectedListTaskId.value));
        const urls = Array.isArray(data?.urls) ? data.urls : [];
        form.urlText = urls.join('\n');
    } catch {
        alert('Failed to load URLs from the list task.');
    } finally {
        loadListUrlsLoading.value = false;
    }
}

function submit() {
    if (form.type === 'list') {
        const url = (form.listUrl || '').trim();
        if (!/^https?:\/\//i.test(url)) {
            alert('Please enter a valid URL.');
            return;
        }
        if (!(form.list_config?.selector_value || '').trim()) {
            alert('Please fill the repeating elements selector.');
            return;
        }
        form.transform(() => ({
            type: 'list',
            name: form.name,
            description: form.description || null,
            url,
            list_config: {
                selector_type: form.list_config.selector_type,
                selector_value: form.list_config.selector_value.trim(),
                value_kind: form.list_config.value_kind,
                value_attr: form.list_config.value_kind === 'attribute' ? (form.list_config.value_attr || 'href') : null,
                delay_seconds: form.list_config.delay_seconds || null,
                pagination_type: form.list_config.pagination_type || null,
                pagination_selector_type: form.list_config.pagination_type ? form.list_config.pagination_selector_type : null,
                pagination_selector_value: form.list_config.pagination_type ? form.list_config.pagination_selector_value?.trim() : null,
                max_pages: form.list_config.pagination_type ? (form.list_config.max_pages || null) : null,
            },
        })).put(route('scrap-tasks.update', task.id));
        return;
    }
    const lines = (form.urlText || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
    const urls = lines.filter(line => /^https?:\/\//i.test(line));
    if (urls.length < 1) {
        alert('Please enter at least one valid URL, one per line.');
        return;
    }
    const params = form.extract_params.filter(p => p.name && p.selector_value);
    if (params.length < 1) {
        alert('Please add at least one extraction field (name + selector).');
        return;
    }
    form.transform(() => ({
        type: 'detail',
        name: form.name,
        description: form.description || null,
        urls,
        extract_params: params,
    })).put(route('scrap-tasks.update', task.id));
}
</script>
