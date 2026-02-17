<template>
    <AppLayout>
        <template #header>
            Telegram Group Crawl
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
                <p class="text-gray-800 mb-4">To crawl groups, connect your Telegram account first in Settings.</p>
                <a :href="route('settings.index')" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700">
                    Go to Telegram Settings
                </a>
            </div>

            <!-- Connected - Two Column Layout -->
            <div v-else class="flex flex-col lg:flex-row gap-6">
                <!-- Left Sidebar: Group List -->
                <aside class="w-full lg:w-80 flex-shrink-0">
                    <div class="bg-white rounded-lg shadow p-4 sticky top-4">
                        <h2 class="text-lg font-semibold text-gray-900 mb-3">Groups</h2>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="loadGroups(false)"
                                :disabled="groupsLoading"
                                class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 disabled:opacity-50 text-sm font-medium"
                            >
                                {{ groupsLoading ? 'Loading...' : 'Load' }}
                            </button>
                            <button
                                type="button"
                                @click="loadGroups(true)"
                                :disabled="groupsLoading"
                                title="Refresh from Telegram"
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                            >
                                {{ groupsLoading ? '...' : 'Refresh' }}
                            </button>
                        </div>
                        <p v-if="groupsError" class="text-red-600 text-sm mt-2">{{ groupsError }}</p>
                        <div v-if="groups.length > 0" class="mt-4 space-y-1.5 max-h-80 overflow-y-auto">
                            <button
                                v-for="g in groups"
                                :key="g.id"
                                type="button"
                                @click="selectedGroupId = g.id"
                                class="w-full text-left px-4 py-3 rounded-lg border transition-colors"
                                :class="selectedGroupId === g.id
                                    ? 'border-blue-500 bg-blue-50 text-blue-900'
                                    : 'border-gray-200 bg-white hover:bg-gray-50 text-gray-800'"
                            >
                                <div class="font-medium text-sm truncate">{{ g.title }}</div>
                                <div class="flex items-center justify-between mt-0.5">
                                    <span class="text-xs text-gray-500">{{ g.type }}</span>
                                    <span class="text-xs text-gray-400 font-mono">{{ g.id }}</span>
                                </div>
                            </button>
                        </div>
                        <p v-else-if="!groupsLoading && groups.length === 0" class="text-gray-500 text-sm mt-3">
                            Click Load or Refresh to fetch your Telegram groups.
                        </p>
                    </div>
                </aside>

                <!-- Main Content: Crawl Settings & Progress -->
                <main class="flex-1 min-w-0 space-y-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Crawl Settings</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Posts to crawl</label>
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
                            <label class="block text-sm font-medium text-gray-700 mb-2">Template (optional)</label>
                            <select
                                v-model="templateId"
                                @change="onTemplateChange"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">No template (write message below)</option>
                                <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Message for authors *</label>
                            <textarea
                                v-model="messageText"
                                rows="5"
                                placeholder="Message to send to post authors..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            ></textarea>
                            <p class="text-xs text-gray-500 mt-1">Variables: {name}, {company}</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <button
                            type="button"
                            @click="startCrawl"
                            :disabled="crawlStarting || !selectedGroupId || !messageText.trim()"
                            class="px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50"
                        >
                            {{ crawlStarting ? 'Starting...' : 'Start Crawl' }}
                        </button>
                        <p v-if="!selectedGroupId && groups.length" class="text-amber-600 text-sm mt-2">Select a group from the sidebar.</p>
                    </div>

                    <!-- Progress Panel -->
                    <div v-if="crawlId" class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold mb-4">Crawl Progress</h3>
                        <p v-if="crawlStatus.status === 'pending'" class="text-sm text-amber-600 mb-4">
                            Job queued. Make sure <code class="bg-amber-100 px-1 rounded">php artisan queue:work</code> is running. If you see 524 timeout, the queue worker may not be running.
                        </p>
                        <div class="space-y-4">
                            <!-- Phase indicator -->
                            <div v-if="crawlStatus.phase" class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-600">{{ phaseLabel }}</span>
                                <span v-if="crawlStatus.phase === 'identifying_authors' && crawlStatus.messages_scanned" class="text-sm text-gray-500">({{ crawlStatus.messages_scanned }} messages scanned)</span>
                            </div>
                            <!-- Progress bar -->
                            <div v-if="crawlStatus.total" class="space-y-1">
                                <div class="flex justify-between text-sm">
                                    <span>{{ crawlStatus.processed || 0 }} / {{ crawlStatus.total }} authors</span>
                                    <span v-if="crawlStatus.total">{{ Math.round(((crawlStatus.processed || 0) / crawlStatus.total) * 100) }}%</span>
                                </div>
                                <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div
                                        class="h-full bg-blue-600 transition-all duration-300"
                                        :style="{ width: crawlStatus.total ? Math.min(100, ((crawlStatus.processed || 0) / crawlStatus.total) * 100) + '%' : '0%' }"
                                    ></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                <div class="bg-gray-50 rounded p-3">
                                    <span class="text-gray-500 block">Status</span>
                                    <span class="font-medium">{{ statusLabel }}</span>
                                </div>
                                <div v-if="crawlStatus.processed !== undefined" class="bg-gray-50 rounded p-3">
                                    <span class="text-gray-500 block">Processed</span>
                                    <span class="font-medium">{{ crawlStatus.processed }}</span>
                                </div>
                                <div v-if="crawlStatus.sent !== undefined" class="bg-green-50 rounded p-3">
                                    <span class="text-gray-500 block">Sent</span>
                                    <span class="font-medium text-green-700">{{ crawlStatus.sent }}</span>
                                </div>
                                <div v-if="crawlStatus.skipped !== undefined" class="bg-amber-50 rounded p-3">
                                    <span class="text-gray-500 block">Skipped</span>
                                    <span class="font-medium text-amber-700">{{ crawlStatus.skipped }}</span>
                                </div>
                            </div>
                            <p v-if="crawlStatus.error" class="text-red-600 text-sm">{{ crawlStatus.error }}</p>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, watch, onUnmounted, computed } from 'vue';
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

const phaseLabel = computed(() => {
    const p = crawlStatus.value.phase;
    if (!p) return '';
    const labels = {
        queued: 'Job queued, waiting for worker...',
        fetching_messages: 'Fetching messages from group...',
        identifying_authors: 'Identifying unique authors from posts...',
        sending_messages: 'Sending messages to authors...',
        completed: 'Completed',
        error: 'Error',
    };
    return labels[p] || p;
});

const statusLabel = computed(() => {
    const s = crawlStatus.value.status || 'pending';
    const labels = {
        pending: 'Waiting for worker...',
        queued: 'Waiting for worker...',
        running: 'Running',
        completed: 'Completed',
        error: 'Error',
    };
    return labels[s] || s;
});

const loadGroups = async (refresh = false) => {
    groupsLoading.value = true;
    groupsError.value = '';
    try {
        const url = refresh ? route('telegram-crawler.groups') + '?refresh=1' : route('telegram-crawler.groups');
        const res = await axios.get(url);
        groups.value = res.data.groups || [];
    } catch (e) {
        groupsError.value = e.response?.data?.error || e.message || 'Failed to load groups';
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
    crawlStatus.value = {};
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
        groupsError.value = e.response?.data?.error || e.message || 'Failed to start crawl';
    } finally {
        crawlStarting.value = false;
    }
};

const pollCrawlStatus = async () => {
    if (!crawlId.value) return;
    try {
        const res = await axios.get(route('telegram-crawler.crawl-status', { crawlId: crawlId.value }));
        crawlStatus.value = res.data;
        if (['completed', 'error'].includes(res.data?.status)) {
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
