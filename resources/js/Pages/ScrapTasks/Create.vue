<template>
    <AppLayout>
        <template #header>
            {{ t('scrap_tasks.create_scraping_task') }}
        </template>

        <div class="max-w-4xl mx-auto">
            <form @submit.prevent="submit" class="bg-white rounded-lg shadow p-6 space-y-6">
                <div v-if="Object.keys(form.errors).length" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                    <ul class="list-disc list-inside">
                        <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                    </ul>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('scrap_tasks.task_type_required') }}</label>
                    <div class="flex gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.type" type="radio" value="list" class="rounded border-gray-300" />
                            <span>{{ t('scrap_tasks.list_extraction') }}</span>
                            <span class="text-xs text-gray-500">{{ t('scrap_tasks.list_extraction_help') }}</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.type" type="radio" value="detail" class="rounded border-gray-300" />
                            <span>{{ t('scrap_tasks.detail_extraction') }}</span>
                            <span class="text-xs text-gray-500">{{ t('scrap_tasks.detail_extraction_help') }}</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('scrap_tasks.task_name_required') }}</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :placeholder="t('scrap_tasks.task_name_placeholder')"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('scrap_tasks.description_optional') }}</label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :placeholder="t('scrap_tasks.description_placeholder')"
                    />
                </div>

                <!-- List type: single URL + list selector -->
                <template v-if="form.type === 'list'">
                    <div class="border-t pt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('scrap_tasks.page_url_required') }}</label>
                        <input
                            v-model="form.listUrl"
                            type="url"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                            :placeholder="t('scrap_tasks.list_url_example_placeholder')"
                        />
                    </div>
                    <div class="border-t pt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('scrap_tasks.repeating_selector_required') }}</label>
                        <p class="text-xs text-gray-500 mb-2">{{ t('scrap_tasks.repeating_selector_help') }}</p>
                        <div class="flex flex-wrap items-center gap-3 p-4 bg-gray-50 rounded-lg">
                            <select
                                v-model="form.list_config.selector_type"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm"
                            >
                                <option value="xpath">XPath</option>
                                <option value="class">{{ t('scrap_tasks.class') }}</option>
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
                                <option value="attribute">{{ t('scrap_tasks.attribute') }}</option>
                            </select>
                            <input
                                v-if="form.list_config.value_kind === 'attribute'"
                                v-model="form.list_config.value_attr"
                                type="text"
                                :placeholder="t('scrap_tasks.attribute_placeholder')"
                                class="w-28 px-3 py-2 border border-gray-300 rounded-md text-sm"
                            />
                        </div>
                    </div>
                    <div class="border-t pt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('scrap_tasks.delay_before_extraction') }}</label>
                        <p class="text-xs text-gray-500 mb-2">{{ t('scrap_tasks.delay_before_extraction_help') }}</p>
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
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('scrap_tasks.pagination') }}</label>
                        <p class="text-xs text-gray-500 mb-2">{{ t('scrap_tasks.pagination_help') }}</p>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ t('scrap_tasks.pagination_type') }}</label>
                                <select
                                    v-model="form.list_config.pagination_type"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                >
                                    <option value="">{{ t('scrap_tasks.pagination_none') }}</option>
                                    <option value="next_page">{{ t('scrap_tasks.pagination_next_page') }}</option>
                                    <option value="load_more">{{ t('scrap_tasks.pagination_load_more') }}</option>
                                </select>
                            </div>
                            <template v-if="form.list_config.pagination_type">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ t('scrap_tasks.pagination_selector_required') }}</label>
                                    <div class="flex flex-wrap items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                        <select
                                            v-model="form.list_config.pagination_selector_type"
                                            class="px-3 py-2 border border-gray-300 rounded-md text-sm"
                                        >
                                            <option value="xpath">XPath</option>
                                            <option value="class">{{ t('scrap_tasks.class') }}</option>
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
                                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ t('scrap_tasks.max_pages_required') }}</label>
                                    <input
                                        v-model.number="form.list_config.max_pages"
                                        type="number"
                                        min="1"
                                        max="1000"
                                        class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                        placeholder="10"
                                    />
                                    <p class="text-xs text-gray-500 mt-1">{{ t('scrap_tasks.max_pages_help') }}</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- Detail type: URLs + extract params -->
                <template v-else>
                <!-- URLs -->
                <div class="border-t pt-6">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700">{{ t('scrap_tasks.page_urls_required') }}</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                @click="pasteUrls"
                                class="text-sm text-blue-600 hover:text-blue-800"
                            >
                                {{ t('scrap_tasks.paste_from_clipboard') }}
                            </button>
                            <label class="text-sm text-blue-600 hover:text-blue-800 cursor-pointer">
                                {{ t('scrap_tasks.import_from_file') }}
                                <input type="file" accept=".txt,.csv" class="hidden" @change="importUrlsFromFile" />
                            </label>
                            <template v-if="listTasks?.length">
                                <span class="text-gray-400">|</span>
                                <select
                                    v-model="selectedListTaskId"
                                    class="text-sm border border-gray-300 rounded px-2 py-1"
                                >
                                    <option value="">{{ t('scrap_tasks.select_list_task') }}</option>
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
                                    {{ loadListUrlsLoading ? t('common.loading') + '...' : t('scrap_tasks.load_urls_from_task') }}
                                </button>
                            </template>
                        </div>
                    </div>
                    <textarea
                        v-model="form.urlText"
                        rows="8"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                        :placeholder="t('scrap_tasks.url_text_example_placeholder')"
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        {{ t('scrap_tasks.url_text_help') }}
                    </p>
                    <p v-if="urlCount > 0" class="mt-1 text-sm text-gray-600">{{ t('scrap_tasks.urls_detected').replace(':count', String(urlCount)) }}</p>
                </div>

                <!-- Extract params -->
                <div class="border-t pt-6">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700">{{ t('scrap_tasks.extraction_fields_required') }}</label>
                        <button
                            type="button"
                            @click="addParam"
                            class="px-3 py-1 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300"
                        >
                            + {{ t('scrap_tasks.add_field') }}
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">
                        {{ t('scrap_tasks.extraction_fields_help') }}
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
                                :placeholder="t('scrap_tasks.field_name_placeholder')"
                                class="flex-1 min-w-[120px] px-3 py-2 border border-gray-300 rounded-md text-sm"
                            />
                            <select
                                v-model="param.selector_type"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm"
                            >
                                <option value="xpath">XPath</option>
                                <option value="class">{{ t('scrap_tasks.class') }}</option>
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
                                :title="t('customers.remove')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p v-if="form.extract_params.length === 0" class="text-sm text-amber-600 mt-2">
                        {{ t('scrap_tasks.add_at_least_one_field') }}
                    </p>
                </div>
                </template>

                <div class="flex gap-3 pt-4">
                    <button
                        type="submit"
                        :disabled="!canSubmit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ t('scrap_tasks.save_task') }}
                    </button>
                    <Link
                        :href="route('scrap-tasks.index')"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                    >
                        {{ t('common.cancel') }}
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
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    listTasks: { type: Array, default: () => [] },
});

const selectedListTaskId = ref('');
const loadListUrlsLoading = ref(false);

const form = useForm({
    type: 'detail',
    name: '',
    description: '',
    listUrl: '',
    list_config: {
        selector_type: 'xpath',
        selector_value: '',
        value_kind: 'text',
        value_attr: '',
        delay_seconds: null,
        pagination_type: '',
        pagination_selector_type: 'xpath',
        pagination_selector_value: '',
        max_pages: null,
    },
    urlText: '',
    urls: [],
    extract_params: [
        { name: '', selector_type: 'xpath', selector_value: '' },
    ],
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
        alert(t('scrap_tasks.clipboard_access_not_available'));
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
        alert(t('scrap_tasks.failed_load_urls_from_task'));
    } finally {
        loadListUrlsLoading.value = false;
    }
}

function submit() {
    if (form.type === 'list') {
        const url = (form.listUrl || '').trim();
        if (!/^https?:\/\//i.test(url)) {
            alert(t('scrap_tasks.enter_valid_url'));
            return;
        }
        if (!(form.list_config?.selector_value || '').trim()) {
            alert(t('scrap_tasks.fill_repeating_selector'));
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
        })).post(route('scrap-tasks.store'));
        return;
    }
    const lines = (form.urlText || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
    const urls = lines.filter(line => /^https?:\/\//i.test(line));
    if (urls.length < 1) {
        alert(t('scrap_tasks.enter_at_least_one_valid_url'));
        return;
    }
    const params = form.extract_params.filter(p => p.name && p.selector_value);
    if (params.length < 1) {
        alert(t('scrap_tasks.add_at_least_one_extraction_field'));
        return;
    }
    form.transform(() => ({
        type: 'detail',
        name: form.name,
        description: form.description || null,
        urls,
        extract_params: params,
    })).post(route('scrap-tasks.store'));
}
</script>
