<template>
    <AppLayout>
        <template #header>
            ویرایش تسک اسکرپ
        </template>

        <div class="max-w-4xl mx-auto">
            <form @submit.prevent="submit" class="bg-white rounded-lg shadow p-6 space-y-6">
                <div v-if="Object.keys(form.errors).length" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                    <ul class="list-disc list-inside">
                        <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                    </ul>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">نوع تسک *</label>
                    <div class="flex gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.type" type="radio" value="list" class="rounded border-gray-300" />
                            <span>استخراج لیست</span>
                            <span class="text-xs text-gray-500">— یک آدرس، استخراج المان‌های تکراری</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.type" type="radio" value="detail" class="rounded border-gray-300" />
                            <span>استخراج جزئیات</span>
                            <span class="text-xs text-gray-500">— چند آدرس، چند پارامتر برای هر صفحه</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">نام تسک *</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="مثلاً: استخراج محصولات فروشگاه"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">توضیحات (اختیاری)</label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="توضیح کوتاه درباره هدف تسک"
                    />
                </div>

                <template v-if="form.type === 'list'">
                    <div class="border-t pt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">آدرس صفحه *</label>
                        <input
                            v-model="form.listUrl"
                            type="url"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                            placeholder="https://example.com/list-page"
                        />
                    </div>
                    <div class="border-t pt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">سلکتور المان‌های تکراری *</label>
                        <p class="text-xs text-gray-500 mb-2">XPath یا class یا id که به چند المان اشاره می‌کند.</p>
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
                                <option value="text">متن المنت</option>
                                <option value="attribute">ویژگی (attribute)</option>
                            </select>
                            <input
                                v-if="form.list_config.value_kind === 'attribute'"
                                v-model="form.list_config.value_attr"
                                type="text"
                                placeholder="مثلاً href یا src"
                                class="w-28 px-3 py-2 border border-gray-300 rounded-md text-sm"
                            />
                        </div>
                    </div>
                </template>

                <template v-else>
                <div class="border-t pt-6">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700">آدرس‌های صفحه (هر خط یک URL) *</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                @click="pasteUrls"
                                class="text-sm text-blue-600 hover:text-blue-800"
                            >
                                چسباندن از کلیپبورد
                            </button>
                            <label class="text-sm text-blue-600 hover:text-blue-800 cursor-pointer">
                                ایمپورت از فایل
                                <input type="file" accept=".txt,.csv" class="hidden" @change="importUrlsFromFile" />
                            </label>
                            <template v-if="listTasks?.length">
                                <span class="text-gray-400">|</span>
                                <select
                                    v-model="selectedListTaskId"
                                    class="text-sm border border-gray-300 rounded px-2 py-1"
                                >
                                    <option value="">انتخاب تسک لیست...</option>
                                    <option v-for="t in listTasks" :key="t.id" :value="t.id">
                                        {{ t.name }} ({{ t.items_count }} آدرس)
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    @click="loadUrlsFromListTask"
                                    :disabled="!selectedListTaskId || loadListUrlsLoading"
                                    class="text-sm text-blue-600 hover:text-blue-800 disabled:opacity-50"
                                >
                                    {{ loadListUrlsLoading ? 'در حال بارگذاری...' : 'بارگذاری آدرس‌ها از این تسک' }}
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
                        هر URL در یک خط. می‌توانید از تسک لیست، فایل یا کلیپبورد بارگذاری کنید.
                    </p>
                    <p v-if="urlCount > 0" class="mt-1 text-sm text-gray-600">{{ urlCount }} آدرس تشخیص داده شد.</p>
                </div>

                <div class="border-t pt-6">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700">پارامترهای استخراج *</label>
                        <button
                            type="button"
                            @click="addParam"
                            class="px-3 py-1 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300"
                        >
                            + افزودن پارامتر
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">
                        برای هر فیلدی که می‌خواهید از صفحه استخراج شود، یک پارامتر تعریف کنید.
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
                                placeholder="نام فیلد"
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
                                title="حذف"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p v-if="form.extract_params.length === 0" class="text-sm text-amber-600 mt-2">
                        حداقل یک پارامتر استخراج اضافه کنید.
                    </p>
                </div>
                </template>

                <div class="flex gap-3 pt-4">
                    <button
                        type="submit"
                        :disabled="!canSubmit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        ذخیره تغییرات
                    </button>
                    <Link
                        :href="route('scrap-tasks.show', task.id)"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                    >
                        انصراف
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
} : { selector_type: 'xpath', selector_value: '', value_kind: 'text', value_attr: '' };
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
        alert('دسترسی به کلیپبورد ممکن نیست.');
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
        alert('بارگذاری آدرس‌ها از تسک لیست با خطا مواجه شد.');
    } finally {
        loadListUrlsLoading.value = false;
    }
}

function submit() {
    if (form.type === 'list') {
        const url = (form.listUrl || '').trim();
        if (!/^https?:\/\//i.test(url)) {
            alert('یک آدرس معتبر وارد کنید.');
            return;
        }
        if (!(form.list_config?.selector_value || '').trim()) {
            alert('سلکتور المان‌های تکراری را پر کنید.');
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
            },
        })).put(route('scrap-tasks.update', task.id));
        return;
    }
    const lines = (form.urlText || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
    const urls = lines.filter(line => /^https?:\/\//i.test(line));
    if (urls.length < 1) {
        alert('حداقل یک آدرس معتبر در هر خط وارد کنید.');
        return;
    }
    const params = form.extract_params.filter(p => p.name && p.selector_value);
    if (params.length < 1) {
        alert('حداقل یک پارامتر استخراج با نام و مقدار سلکتور پر کنید.');
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
