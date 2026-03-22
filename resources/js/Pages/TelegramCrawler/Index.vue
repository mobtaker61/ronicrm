<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <span>Telegram Group Crawl</span>
                <a :href="route('telegram-groups.index')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium">
                    Groups
                </a>
            </div>
        </template>

        <div v-if="$page.props.flash?.success || $page.props.flash?.error" class="absolute top-20 left-0 right-0 z-50 px-4 lg:px-8">
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg shadow-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg shadow-lg">
                {{ $page.props.flash.error }}
            </div>
        </div>

        <!-- Not Connected -->
        <div v-if="!telegramConnected" class="p-6 border border-amber-200 rounded-lg bg-amber-50">
            <p class="text-gray-800 mb-4">To crawl groups, connect your Telegram account first in Settings.</p>
            <a :href="route('settings.index')" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700">
                Go to Telegram Settings
            </a>
        </div>

        <!-- Connected - Full Height Layout (like Inbox) -->
        <div v-else class="-m-4 lg:-m-8 h-[calc(100vh-64px)] flex flex-col bg-white overflow-hidden">
            <div class="flex flex-1 overflow-hidden">
                <!-- Left Sidebar: Group List - Full Height -->
                <aside class="w-full lg:w-80 flex-shrink-0 bg-white border-r border-gray-200 flex flex-col min-w-0">
                    <div class="flex-shrink-0 p-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-900 mb-3">Groups</h2>
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">فیلتر دسته‌بندی</label>
                            <select
                                v-model="groupCategoryFilter"
                                @change="loadGroups(true)"
                                class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md"
                            >
                                <option value="">همه</option>
                                <option v-for="c in telegramGroupCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <button
                            type="button"
                            @click="loadGroups(true)"
                            :disabled="groupsLoading"
                            title="Refresh from Telegram"
                            class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                        >
                            {{ groupsLoading ? 'Loading...' : 'Refresh' }}
                        </button>
                        <p v-if="groupsError" class="text-red-600 text-sm mt-2">{{ groupsError }}</p>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <div v-if="groups.length > 0" class="p-2 space-y-1">
                            <div
                                v-for="g in groups"
                                :key="g.id"
                                class="flex items-start gap-3 w-full text-left px-3 py-2.5 rounded-lg border transition-colors"
                                :class="[
                                    showSendToGroups && !g.can_post
                                        ? 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed'
                                        : 'hover:bg-gray-50 cursor-pointer',
                                    selectedGroupId === g.id || selectedGroupIds.has(g.id)
                                        ? 'border-blue-500 bg-blue-50 text-blue-900'
                                        : 'border-gray-200 bg-white text-gray-800'
                                ]"
                                :title="(showSendToGroups && !g.can_post && g.last_error) ? g.last_error : ''"
                                @click="showSendToGroups ? (g.can_post !== false && toggleGroupSelection(g.id)) : (selectedGroupId = g.id)"
                            >
                                <input
                                    v-if="showSendToGroups"
                                    type="checkbox"
                                    :checked="selectedGroupIds.has(g.id)"
                                    :disabled="g.can_post === false"
                                    @change.stop="g.can_post !== false && toggleGroupSelection(g.id)"
                                    class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-40"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-sm truncate flex items-center gap-1">
                                        {{ g.title }}
                                        <span v-if="showSendToGroups && !g.can_post" class="text-amber-600" title="Cannot post in this group">⚠</span>
                                    </div>
                                    <div class="flex items-center justify-between mt-0.5 gap-1 flex-wrap">
                                        <span class="text-xs text-gray-500">{{ g.type }}</span>
                                        <span v-if="g.category?.name || g.language" class="text-xs text-blue-600 truncate">
                                            {{ [g.category?.name, g.language].filter(Boolean).join(' · ') }}
                                        </span>
                                        <span class="text-xs text-gray-400 font-mono truncate ml-1">{{ g.id }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else-if="!groupsLoading && groups.length === 0" class="text-gray-500 text-sm p-4">
                            Groups will load automatically. Click Refresh to fetch from Telegram.
                        </p>
                    </div>
                </aside>

                <!-- Main Content: Scrollable -->
                <main class="flex-1 min-w-0 overflow-y-auto p-6 space-y-6">
                    <!-- Send Template to Groups -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Send Template to Groups</h2>
                        <p class="text-sm text-gray-600 mb-4">
                            Select groups from the list and send the template directly to them.
                        </p>
                        <div class="flex flex-wrap gap-3 items-end">
                            <div class="min-w-[200px]">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Template</label>
                                <select
                                    v-model="sendToGroupsTemplateId"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">Select template...</option>
                                    <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}{{ t.image ? ' 📷' : '' }}</option>
                                </select>
                                <div v-if="selectedSendToGroupsTemplate?.image" class="mt-2 p-2 border border-gray-200 rounded-lg bg-gray-50">
                                    <p class="text-xs text-gray-500 mb-1">Template image preview:</p>
                                    <img :src="selectedSendToGroupsTemplate.image" alt="Template" class="max-h-32 rounded object-cover" />
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="showSendToGroups = !showSendToGroups"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium"
                            >
                                {{ showSendToGroups ? 'Cancel' : 'Select Groups' }}
                            </button>
                            <button
                                v-if="showSendToGroups"
                                type="button"
                                @click="sendToSelectedGroups"
                                :disabled="sendToGroupsStarting || selectedGroupIds.size === 0 || !sendToGroupsTemplateId"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 text-sm font-medium"
                            >
                                {{ sendToGroupsStarting ? 'Sending...' : `Send to ${selectedGroupIds.size} groups` }}
                            </button>
                        </div>
                        <div v-if="sendId" class="mt-4 p-4 bg-gray-50 rounded-lg text-sm">
                            <p class="font-medium text-gray-700 mb-2">Send to groups status</p>
                            <p v-if="sendStatus.error" class="text-red-600 mb-2">{{ sendStatus.error }}</p>
                            <div class="flex gap-4 text-sm">
                                <span>Sent: {{ sendStatus.sent ?? 0 }}</span>
                                <span>Failed: {{ sendStatus.failed ?? 0 }}</span>
                                <span>Status: {{ sendStatus.status || 'Pending...' }}</span>
                            </div>
                            <div v-if="sendStatus.results?.length" class="mt-2 max-h-40 overflow-y-auto space-y-1 text-xs">
                                <div
                                    v-for="(r, i) in sendStatus.results"
                                    :key="i"
                                    :class="r.status === 'sent' ? 'text-green-700' : 'text-red-700'"
                                >
                                    Group {{ r.group_id }}: {{ r.status === 'sent' ? '✓ Sent' : '✗ ' + (r.error || 'Error') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sync Contacts from Telegram -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Sync Contact Info from Telegram</h2>
                        <p class="text-sm text-gray-600 mb-4">
                            Fetch full profile data (name, phone, avatar) from Telegram for customers extracted via crawl or inbox. Updates existing contacts.
                        </p>
                        <!-- Queue status -->
                        <div class="mb-4 p-3 bg-gray-50 rounded-lg text-sm flex items-center gap-4">
                            <span>صف: <strong>{{ queueStatus.pending_jobs ?? 0 }}</strong> در انتظار</span>
                            <span v-if="(queueStatus.failed_jobs ?? 0) > 0" class="text-red-600">شکست: <strong>{{ queueStatus.failed_jobs }}</strong></span>
                            <button type="button" @click="fetchQueueStatus" class="text-blue-600 hover:underline text-xs">بروزرسانی</button>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 mb-4">
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" v-model="syncContactsRunNow" class="rounded border-gray-300" />
                                <span>اجرای فوری (بدون Queue)</span>
                            </label>
                            <span v-if="syncContactsRunNow" class="text-xs text-amber-600">
                                درخواست تا اتمام sync منتظر می‌ماند. برای مخاطبین زیاد ممکن است timeout شود.
                            </span>
                        </div>
                        <p v-if="!syncContactsRunNow && (queueStatus.pending_jobs ?? 0) > 0" class="text-xs text-amber-600 mb-2">
                            {{ queueStatus.pending_jobs }} Job در صف است. اگر مدتی است پردازش نشده، گزینه «اجرای فوری» را فعال کنید یا دستی اجرا کنید: <code class="bg-gray-200 px-1 rounded">php artisan telegram:sync-contacts</code>
                        </p>
                        <button
                            type="button"
                            @click="startSyncContacts"
                            :disabled="!telegramConnected || syncContactsStarting"
                            class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 text-sm font-medium"
                        >
                            {{ syncContactsStarting ? 'Starting...' : 'Sync Contacts from Telegram' }}
                        </button>
                        <div v-if="syncId" class="mt-4 p-4 bg-gray-50 rounded-lg text-sm">
                            <p class="font-medium text-gray-700 mb-2">Sync status</p>
                            <p v-if="syncStatus.error" class="text-red-600 mb-2">{{ syncStatus.error }}</p>
                            <div class="flex gap-4 text-sm">
                                <span>Processed: {{ syncStatus.processed ?? 0 }}{{ syncStatus.total ? ' / ' + syncStatus.total : '' }}</span>
                                <span>Updated: {{ syncStatus.updated ?? 0 }}</span>
                                <span v-if="syncStatus.failed">Failed: {{ syncStatus.failed }}</span>
                                <span>Status: {{ syncStatus.status || 'Pending...' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Crawl Settings -->
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
                                <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}{{ t.image ? ' 📷' : '' }}</option>
                            </select>
                            <div v-if="selectedCrawlTemplate?.image" class="mt-2 p-2 border border-gray-200 rounded-lg bg-gray-50">
                                <p class="text-xs text-gray-500 mb-1">Template image preview:</p>
                                <img :src="selectedCrawlTemplate.image" alt="Template" class="max-h-32 rounded object-cover" />
                            </div>
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
                        <p v-if="!selectedGroupId && groups.length && !showSendToGroups" class="text-amber-600 text-sm mt-2">Select a group from the sidebar (exit "Select Groups" mode for crawl).</p>
                    </div>

                    <!-- Progress Panel -->
                    <div v-if="crawlId" class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold mb-4">Crawl Progress</h3>
                        <p v-if="crawlStatus.status === 'pending'" class="text-sm text-amber-600 mb-4">
                            Job queued. Make sure <code class="bg-amber-100 px-1 rounded">php artisan queue:work</code> is running.
                        </p>
                        <div class="space-y-4">
                            <div v-if="crawlStatus.phase" class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-600">{{ phaseLabel }}</span>
                                <span v-if="crawlStatus.phase === 'identifying_authors' && crawlStatus.messages_scanned" class="text-sm text-gray-500">({{ crawlStatus.messages_scanned }} messages scanned)</span>
                            </div>
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

                            <p v-else-if="(crawlStatus.status === 'completed' || crawlStatus.phase === 'identifying_authors' || crawlStatus.phase === 'sending_messages') && (crawlStatus.messages_scanned ?? 0) === 0 && !crawlStatus.messages_preview?.length" class="mt-4 text-amber-600 text-sm">
                                No messages were crawled.
                            </p>

                            <!-- Crawled messages list -->
                            <div v-else-if="crawlStatus.messages_preview?.length" class="mt-6 pt-4 border-t">
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">
                                    Crawled messages ({{ crawlStatus.messages_preview.length }})
                                </h4>
                                <div class="max-h-[24rem] overflow-y-auto space-y-2 text-sm">
                                    <div
                                        v-for="(m, i) in crawlStatus.messages_preview"
                                        :key="m.id || i"
                                        class="rounded border border-gray-200 overflow-hidden"
                                    >
                                        <div class="flex items-start gap-2 p-2 bg-gray-50 hover:bg-gray-100 cursor-pointer" @click="toggleRaw(i)">
                                            <span class="text-gray-400 shrink-0">#{{ m.id }}</span>
                                            <span class="text-xs text-amber-600 shrink-0">{{ m.from_type || '?' }}</span>
                                            <span class="truncate flex-1 min-w-0">{{ m.text || '(no text)' }}</span>
                                            <a v-if="m.link" :href="m.link" target="_blank" rel="noopener" class="text-blue-600 hover:underline shrink-0" @click.stop>Open</a>
                                            <span class="text-xs text-gray-400">{{ expandedRawIndices.has(i) ? '▼' : '▶' }} JSON</span>
                                        </div>
                                        <pre v-if="expandedRawIndices.has(i) && m.raw_json" class="p-3 text-xs bg-gray-900 text-gray-100 overflow-x-auto whitespace-pre-wrap break-all m-0">{{ m.raw_json }}</pre>
                                    </div>
                                </div>

                                <!-- Authors that received messages -->
                                <div v-if="crawlStatus.authors_sent?.length" class="mt-6 pt-4 border-t">
                                    <h4 class="text-sm font-semibold text-gray-700 mb-2">
                                        Authors messaged ({{ crawlStatus.authors_sent.length }})
                                    </h4>
                                    <div class="max-h-48 overflow-y-auto space-y-1.5 text-sm">
                                        <div
                                            v-for="(a, i) in crawlStatus.authors_sent"
                                            :key="i"
                                            class="flex items-center gap-2 px-3 py-2 rounded"
                                            :class="a.status === 'sent' ? 'bg-green-50 text-green-800' : a.status === 'skipped' ? 'bg-amber-50 text-amber-800' : 'bg-red-50 text-red-800'"
                                        >
                                            <span v-if="a.status === 'sent'" class="text-green-600">✓</span>
                                            <span v-else-if="a.status === 'skipped'" class="text-amber-600">⊘</span>
                                            <span v-else class="text-red-600">✗</span>
                                            <span class="font-mono text-xs">{{ a.user_id }}</span>
                                            <span class="text-xs">
                                                {{ a.status === 'sent' ? 'Sent' : a.status === 'skipped' ? 'Skipped (already messaged)' : 'Failed: ' + (a.error || '') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, watch, onUnmounted, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
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
const selectedGroupIds = ref(new Set());
const showSendToGroups = ref(false);
const sendToGroupsTemplateId = ref('');
const sendToGroupsStarting = ref(false);
const sendId = ref('');
const sendStatus = ref({});
const limit = ref(50);
const templateId = ref('');
const messageText = ref('');
const crawlStarting = ref(false);
const crawlId = ref('');
const crawlStatus = ref({});
const expandedRawIndices = ref(new Set());
const syncContactsStarting = ref(false);
const syncContactsRunNow = ref(true);
const syncId = ref('');
const syncStatus = ref({});
const queueStatus = ref({});
let crawlPollTimer = null;
let sendPollTimer = null;
let syncPollTimer = null;

const toggleGroupSelection = (gId) => {
    const s = new Set(selectedGroupIds.value);
    if (s.has(gId)) s.delete(gId);
    else s.add(gId);
    selectedGroupIds.value = s;
};

const toggleRaw = (i) => {
    const s = new Set(expandedRawIndices.value);
    if (s.has(i)) s.delete(i);
    else s.add(i);
    expandedRawIndices.value = s;
};

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

const selectedCrawlTemplate = computed(() =>
    props.templates.find(t => String(t.id) === String(templateId.value)) || null
);
const selectedSendToGroupsTemplate = computed(() =>
    props.templates.find(t => String(t.id) === String(sendToGroupsTemplateId.value)) || null
);

const groupCategoryFilter = ref('');
const page = usePage();
const telegramGroupCategories = computed(() => page.props.telegramGroupCategories || []);

const loadGroups = async (refresh = false) => {
    groupsLoading.value = true;
    groupsError.value = '';
    try {
        const params = new URLSearchParams();
        if (refresh) params.set('refresh', '1');
        if (groupCategoryFilter.value) params.set('category', groupCategoryFilter.value);
        const url = route('telegram-crawler.groups') + (params.toString() ? '?' + params.toString() : '');
        const res = await axios.get(url);
        groups.value = res.data.groups || [];
    } catch (e) {
        groupsError.value = e.response?.data?.error || e.message || 'Failed to load groups';
    } finally {
        groupsLoading.value = false;
    }
};

onMounted(() => {
    if (props.telegramConnected) {
        loadGroups(false);
    }
    fetchQueueStatus();
});

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

const sendToSelectedGroups = async () => {
    if (selectedGroupIds.value.size === 0 || !sendToGroupsTemplateId.value) return;
    sendToGroupsStarting.value = true;
    sendStatus.value = {};
    const groupTitles = {};
    groups.value.forEach(g => {
        if (selectedGroupIds.value.has(g.id)) groupTitles[g.id] = g.title || null;
    });
    try {
        const res = await axios.post(route('telegram-crawler.send-to-groups'), {
            group_ids: Array.from(selectedGroupIds.value),
            template_id: sendToGroupsTemplateId.value,
            group_titles: groupTitles,
        });
        sendId.value = res.data.send_id;
        sendPollTimer = setInterval(pollSendStatus, 2000);
    } catch (e) {
        groupsError.value = e.response?.data?.error || e.message || 'Failed to send';
    } finally {
        sendToGroupsStarting.value = false;
    }
};

const pollSendStatus = async () => {
    if (!sendId.value) return;
    try {
        const res = await axios.get(route('telegram-crawler.send-status', { sendId: sendId.value }));
        sendStatus.value = res.data;
        if (['completed', 'error'].includes(res.data?.status)) {
            if (sendPollTimer) clearInterval(sendPollTimer);
        }
    } catch {}
};

watch(crawlId, (id) => {
    if (id) pollCrawlStatus();
});

watch(sendId, (id) => {
    if (id) pollSendStatus();
});

const fetchQueueStatus = async () => {
    try {
        const res = await axios.get(route('telegram-crawler.queue-status'));
        queueStatus.value = res.data;
    } catch {}
};

const startSyncContacts = async () => {
    syncContactsStarting.value = true;
    syncStatus.value = {};
    try {
        const url = route('telegram-crawler.sync-contacts');
        const res = await axios.post(url, {}, { params: { sync: syncContactsRunNow.value ? 1 : 0 } });
        syncId.value = res.data.sync_id;
        syncPollTimer = setInterval(pollSyncStatus, 2000);
        fetchQueueStatus();
    } catch (e) {
        groupsError.value = e.response?.data?.error || e.message || 'Failed to start sync';
    } finally {
        syncContactsStarting.value = false;
    }
};

const pollSyncStatus = async () => {
    if (!syncId.value) return;
    try {
        const res = await axios.get(route('telegram-crawler.sync-status', { syncId: syncId.value }));
        syncStatus.value = res.data;
        if (['completed', 'error'].includes(res.data?.status)) {
            if (syncPollTimer) clearInterval(syncPollTimer);
            fetchQueueStatus();
        }
    } catch {}
};

watch(syncId, (id) => {
    if (id) pollSyncStatus();
});

onUnmounted(() => {
    if (crawlPollTimer) clearInterval(crawlPollTimer);
    if (sendPollTimer) clearInterval(sendPollTimer);
    if (syncPollTimer) clearInterval(syncPollTimer);
});
</script>
