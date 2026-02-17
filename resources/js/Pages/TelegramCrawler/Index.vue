<template>
    <AppLayout>
        <template #header>
            پیمایش گروه تلگرام
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.error }}
            </div>

            <!-- Not Connected -->
            <div v-if="!telegramConnected" class="p-6 border border-amber-200 rounded-lg bg-amber-50">
                <p class="text-gray-800 mb-4">برای پیمایش گروه‌ها ابتدا باید به اکانت تلگرام خود در تنظیمات متصل شوید.</p>
                <a :href="route('settings.index')" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700">
                    رفتن به تنظیمات تلگرام
                </a>
            </div>

            <!-- Connected - Crawler UI -->
            <div v-else class="space-y-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">انتخاب گروه</h2>
                    <div class="mb-4">
                        <button
                            type="button"
                            @click="loadGroups"
                            :disabled="groupsLoading"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ groupsLoading ? 'در حال بارگذاری...' : 'بارگذاری لیست گروه‌ها' }}
                        </button>
                    </div>
                    <div v-if="groupsError" class="text-red-600 text-sm mb-4">{{ groupsError }}</div>
                    <div v-if="groups.length > 0" class="border rounded-lg max-h-60 overflow-y-auto">
                        <label
                            v-for="g in groups"
                            :key="g.id"
                            class="flex items-center px-4 py-2 hover:bg-gray-50 cursor-pointer border-b last:border-b-0"
                        >
                            <input v-model="selectedGroupId" type="radio" :value="g.id" class="mr-3" />
                            <span class="font-medium">{{ g.title }}</span>
                            <span class="text-xs text-gray-500 mr-2">({{ g.id }})</span>
                        </label>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">تنظیمات پیمایش</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">تعداد پست برای پیمایش</label>
                            <input
                                v-model.number="limit"
                                type="number"
                                min="1"
                                max="200"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">تمپلت (اختیاری)</label>
                        <select
                            v-model="templateId"
                            @change="onTemplateChange"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">بدون تمپلت (متن زیر را بنویسید)</option>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">متن پیام برای نویسندگان *</label>
                        <textarea
                            v-model="messageText"
                            rows="5"
                            placeholder="متن پیامی که به نویسندگان پست ارسال می‌شود..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        ></textarea>
                        <p class="text-xs text-gray-500 mt-1">متغیرهای قابل استفاده: {name}, {company}</p>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <button
                        type="button"
                        @click="startCrawl"
                        :disabled="crawlStarting || !selectedGroupId || !messageText.trim()"
                        class="px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ crawlStarting ? 'در حال شروع...' : 'شروع پیمایش' }}
                    </button>
                </div>

                <!-- Progress -->
                <div v-if="crawlId" class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-2">وضعیت پیمایش</h3>
                    <div class="space-y-2">
                        <p><span class="font-medium">وضعیت:</span> {{ crawlStatus.status || 'در حال اجرا...' }}</p>
                        <p v-if="crawlStatus.processed !== undefined">پردازش‌شده: {{ crawlStatus.processed }}</p>
                        <p v-if="crawlStatus.sent !== undefined">ارسال‌شده: {{ crawlStatus.sent }}</p>
                        <p v-if="crawlStatus.skipped !== undefined">رد‌شده (قبلاً تماس): {{ crawlStatus.skipped }}</p>
                        <p v-if="crawlStatus.error" class="text-red-600">{{ crawlStatus.error }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, watch, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    telegramConnected: { type: Boolean, default: false },
    templates: { type: Array, default: () => [] },
});

const groups = ref([]);
const groupsLoading = ref(false);
const groupsError = ref('');
const selectedGroupId = ref('');
const limit = ref(50);
const templateId = ref('');
const messageText = ref('');
const crawlStarting = ref(false);
const crawlId = ref('');
const crawlStatus = ref({});
let crawlPollTimer = null;

const loadGroups = async () => {
    groupsLoading.value = true;
    groupsError.value = '';
    try {
        const res = await axios.get(route('telegram-crawler.groups'));
        groups.value = res.data.groups || [];
    } catch (e) {
        groupsError.value = e.response?.data?.error || e.message || 'خطا در بارگذاری';
    } finally {
        groupsLoading.value = false;
    }
};

const onTemplateChange = () => {
    const t = props.templates.find(x => x.id == templateId.value);
    if (t) messageText.value = t.content || '';
};

const startCrawl = async () => {
    if (!selectedGroupId.value || !messageText.value.trim()) return;
    crawlStarting.value = true;
    try {
        const res = await axios.post(route('telegram-crawler.crawl'), {
            group_id: selectedGroupId.value,
            limit: limit.value,
            message: messageText.value,
            template_id: templateId.value || null,
        });
        crawlId.value = res.data.crawl_id;
        crawlPollTimer = setInterval(pollCrawlStatus, 2000);
    } catch (e) {
        groupsError.value = e.response?.data?.error || e.message || 'خطا';
    } finally {
        crawlStarting.value = false;
    }
};

const pollCrawlStatus = async () => {
    if (!crawlId.value) return;
    try {
        const res = await axios.get(route('telegram-crawler.crawl-status', { crawlId: crawlId.value }));
        crawlStatus.value = res.data;
        if (['completed', 'error'].includes(res.data.status)) {
            if (crawlPollTimer) clearInterval(crawlPollTimer);
        }
    } catch {}
};

watch(crawlId, (id) => {
    if (id) pollCrawlStatus();
});

onUnmounted(() => {
    if (crawlPollTimer) clearInterval(crawlPollTimer);
});
</script>
