<template>
    <AppLayout>
        <template #header>
            Scraping Task: {{ displayTask.name }}
        </template>

        <div class="max-w-6xl mx-auto space-y-6" dir="ltr">
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.error }}
            </div>

            <!-- Task info -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-start flex-wrap gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ displayTask.name }}</h2>
                        <p v-if="displayTask.description" class="text-gray-500 mt-1">{{ displayTask.description }}</p>
                        <div class="mt-2 flex items-center gap-3 text-sm">
                            <span
                                class="px-2 py-1 rounded-full text-xs font-medium"
                                :class="{
                                    'bg-gray-100 text-gray-800': displayTask.status === 'draft',
                                    'bg-blue-100 text-blue-800': displayTask.status === 'running',
                                    'bg-green-100 text-green-800': displayTask.status === 'completed',
                                    'bg-red-100 text-red-800': displayTask.status === 'failed',
                                }"
                            >
                                {{ statusLabel(displayTask.status) }}
                            </span>
                            <span class="text-gray-500">{{ displayTask.urls?.length ?? 0 }} URLs</span>
                            <span class="text-gray-500">{{ displayTask.extract_params?.length ?? 0 }} fields</span>
                            <span v-if="displayTask.completed_at" class="text-gray-500">
                                Completed: {{ new Date(displayTask.completed_at).toLocaleString('en-US') }}
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="route('scrap-tasks.index')"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                        >
                            Back to list
                        </Link>
                        <Link
                            :href="route('scrap-tasks.edit', displayTask.id)"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                        >
                            Edit task
                        </Link>
                        <a
                            v-if="displayTask.results?.length > 0"
                            :href="route('scrap-tasks.export-excel', displayTask.id)"
                            target="_blank"
                            rel="noopener"
                            class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 inline-flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Download CSV
                        </a>
                        <button
                            v-if="['draft', 'failed', 'completed'].includes(displayTask.status)"
                            type="button"
                            @click="runTaskLive"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                            title="Requires queue worker: php artisan queue:work"
                        >
                            {{ displayTask.status === 'completed' ? 'Re-run (Live progress)' : 'Run (Live progress)' }}
                        </button>
                        <button
                            v-if="['draft', 'failed', 'completed'].includes(displayTask.status)"
                            type="button"
                            @click="runTaskSync"
                            class="px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700"
                            title="No worker needed; results appear after the request finishes"
                        >
                            {{ displayTask.status === 'completed' ? 'Re-run (Sync)' : 'Run (Sync)' }}
                        </button>
                        <button
                            v-if="displayTask.status === 'running'"
                            type="button"
                            @click="resetTask"
                            class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700"
                        >
                            Reset status
                        </button>
                        <button
                            v-if="displayTask.status === 'draft'"
                            type="button"
                            @click="deleteTask"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <!-- Progress (when running): for list type just message; for detail type show bar -->
            <div
                v-if="displayTask.status === 'running'"
                class="bg-white rounded-lg shadow p-4"
            >
                <template v-if="displayTask.type === 'list'">
                    <p class="text-sm text-gray-600">Fetching page and extracting list...</p>
                </template>
                <template v-else-if="progress.total > 0">
                    <div class="flex justify-between text-sm text-gray-600 mb-2">
                        <span>Extracting... {{ progress.done }} / {{ progress.total }} URLs</span>
                        <span>{{ progress.percent }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                        <div
                            class="h-full bg-blue-600 rounded-full transition-all duration-300"
                            :style="{ width: progress.percent + '%' }"
                        />
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        If you don't see progress, run: <code class="bg-gray-100 px-1 rounded">php artisan queue:work</code>
                    </p>
                </template>
            </div>

            <!-- URLs & Params summary -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="font-medium text-gray-900 mb-2">{{ displayTask.type === 'list' ? 'Page URL' : 'URLs' }}</h3>
                    <ul class="text-sm text-gray-600 space-y-1 max-h-48 overflow-y-auto">
                        <li v-for="(u, i) in displayTask.urls" :key="u.id" class="truncate" :title="u.url">
                            {{ i + 1 }}. {{ u.url }}
                        </li>
                    </ul>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <template v-if="displayTask.type === 'list'">
                        <h3 class="font-medium text-gray-900 mb-2">List selector</h3>
                        <p v-if="displayTask.list_config" class="text-sm text-gray-600">
                            {{ displayTask.list_config.selector_type }}: {{ displayTask.list_config.selector_value }}
                            <span class="text-gray-500">— value: {{ displayTask.list_config.value_kind === 'attribute' ? displayTask.list_config.value_attr : 'text' }}</span>
                        </p>
                    </template>
                    <template v-else>
                        <h3 class="font-medium text-gray-900 mb-2">Extraction fields</h3>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li v-for="p in displayTask.extract_params" :key="p.id">
                                <span class="font-medium">{{ p.name }}</span>
                                <span class="text-gray-500"> — {{ p.selector_type }}: {{ p.selector_value }}</span>
                            </li>
                        </ul>
                    </template>
                </div>
            </div>

            <!-- Report: list type = list of values; detail type = table per URL -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-3 bg-gray-50 font-medium text-gray-900 border-b flex flex-wrap items-center justify-between gap-2">
                    <span>Extraction report</span>
                    <template v-if="displayTask.type === 'list'">
                        <span v-if="listItems.length > 0" class="text-sm font-normal text-gray-600">
                            Extracted: {{ listItems.length }} items
                        </span>
                        <button
                            v-if="displayTask.list_config && displayTask.urls?.length"
                            type="button"
                            @click="testListSelector"
                            :disabled="testSelectorLoading"
                            class="text-sm px-3 py-1.5 bg-slate-100 text-slate-700 rounded hover:bg-slate-200 disabled:opacity-50"
                        >
                            {{ testSelectorLoading ? 'Testing...' : 'Test selector' }}
                        </button>
                    </template>
                </div>
                <div v-if="displayTask.type === 'list' && testSelectorResult !== null" class="px-6 py-2 bg-blue-50 border-b text-sm text-blue-800">
                    {{ testSelectorResult }}
                </div>
                <template v-if="displayTask.type === 'list'">
                    <div v-if="displayTask.status === 'running' && !listItems.length" class="p-8 text-center text-gray-500">
                        Fetching page and extracting list...
                    </div>
                    <div v-else-if="hasListResultButZeroItems" class="p-8 text-center">
                        <p class="text-amber-700 font-medium">Task finished, but no matching elements were found in the fetched HTML.</p>
                        <p class="text-sm text-gray-600 mt-2">
                            Many sites render content via JavaScript; in that case server-side HTML scraping won't see the final content. Use “Test selector” to preview the match count.
                        </p>
                    </div>
                    <div v-else-if="!listItems.length" class="p-8 text-center text-gray-500">
                        No run yet. Click “Run” or “Test selector” to start.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 report-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase w-16">#</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Value</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(item, idx) in listItems" :key="idx">
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ idx + 1 }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        <div class="cell-wrap">{{ item }}</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
                <template v-else>
                    <div v-if="!displayResults.length && displayTask.status !== 'running'" class="p-8 text-center text-gray-500">
                        No run yet. Click “Run” to start extraction.
                    </div>
                    <div v-else-if="displayTask.status === 'running' && !displayResults.length" class="p-8 text-center text-gray-500">
                        Starting extraction...
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 report-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase w-12">#</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 min-w-[200px]">URL</th>
                                    <th
                                        v-for="p in displayTask.extract_params"
                                        :key="p.id"
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 min-w-[120px]"
                                    >
                                        {{ p.name }}
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 w-24">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(res, idx) in displayResults" :key="res.id">
                                    <td class="px-4 py-2 text-sm text-gray-500 align-top">{{ idx + 1 }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600 align-top">
                                        <div class="cell-wrap" :title="res.url">{{ res.url }}</div>
                                    </td>
                                    <td
                                        v-for="p in displayTask.extract_params"
                                        :key="p.id"
                                        class="px-4 py-2 text-sm text-gray-900 align-top"
                                    >
                                        <div class="cell-wrap">{{ (res.extracted_data || {})[p.name] ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-2 align-top">
                                        <span
                                            class="px-2 py-0.5 text-xs rounded-full whitespace-nowrap"
                                            :class="{
                                                'bg-green-100 text-green-800': res.status === 'success',
                                                'bg-red-100 text-red-800': res.status === 'failed',
                                                'bg-gray-100 text-gray-800': res.status === 'pending',
                                            }"
                                        >
                                            {{ res.status === 'success' ? 'Success' : res.status === 'failed' ? 'Failed' : 'Pending' }}
                                        </span>
                                        <p v-if="res.error_message" class="text-xs text-red-600 mt-1">{{ res.error_message }}</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    task: Object,
});

const pollInterval = ref(null);
const liveTask = ref(null);
const testSelectorLoading = ref(false);
const testSelectorResult = ref(null);

const displayTask = computed(() => liveTask.value ?? props.task);

const displayResults = computed(() => {
    const t = displayTask.value;
    if (!t?.results?.length) return [];
    return t.results.map((r) => ({
        id: r.id,
        url: r.scrap_task_url?.url ?? r.url ?? '',
        status: r.status,
        error_message: r.error_message,
        extracted_data: r.extracted_data,
    }));
});

const listItems = computed(() => {
    const t = displayTask.value;
    if (t?.type !== 'list' || !t?.results?.length) return [];
    const first = t.results[0];
    const items = first?.extracted_data?.items;
    return Array.isArray(items) ? items : [];
});

const hasListResultButZeroItems = computed(() => {
    const t = displayTask.value;
    return t?.type === 'list' && t?.results?.length > 0 && listItems.value.length === 0;
});

const progress = computed(() => {
    const t = displayTask.value;
    const total = t?.urls?.length ?? 0;
    const done = t?.results?.length ?? 0;
    return {
        total,
        done,
        percent: total > 0 ? Math.round((done / total) * 100) : 0,
    };
});

const statusLabel = (status) => {
    const map = { draft: 'Draft', running: 'Running', completed: 'Completed', failed: 'Failed' };
    return map[status] ?? status;
};

function fetchRunStatus() {
    if (!props.task?.id) return;
    fetch(route('scrap-tasks.run-status', props.task.id), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then((res) => res.json())
        .then((data) => {
            liveTask.value = {
                ...props.task,
                type: props.task.type ?? data.task.type,
                status: data.task.status,
                started_at: data.task.started_at,
                completed_at: data.task.completed_at,
                urls: props.task.urls,
                extract_params: props.task.extract_params,
                list_config: props.task.list_config,
                results: data.results.map((r) => ({
                    id: r.id,
                    scrap_task_url_id: r.scrap_task_url_id,
                    scrap_task_url: { url: r.url },
                    url: r.url,
                    status: r.status,
                    error_message: r.error_message,
                    extracted_data: r.extracted_data,
                })),
            };
        })
        .catch(() => {});
}

function startPolling() {
    if (pollInterval.value) return;
    fetchRunStatus();
    pollInterval.value = setInterval(fetchRunStatus, 1500);
}

function stopPolling() {
    if (pollInterval.value) {
        clearInterval(pollInterval.value);
        pollInterval.value = null;
    }
}

const runTaskLive = () => {
    if (confirm('Start live run? (Queue worker must be running to see progress)')) {
        router.post(route('scrap-tasks.run', props.task.id));
    }
};

const runTaskSync = () => {
    if (confirm('Start sync run? Please wait until the request finishes.')) {
        router.post(route('scrap-tasks.run-sync', props.task.id));
    }
};

const resetTask = () => {
    if (confirm('Reset task status to “Failed” so it can be run again?')) {
        router.post(route('scrap-tasks.reset', props.task.id));
    }
};

const deleteTask = () => {
    if (confirm('Are you sure you want to delete this task?')) {
        router.delete(route('scrap-tasks.destroy', props.task.id));
    }
};

async function testListSelector() {
    testSelectorLoading.value = true;
    testSelectorResult.value = null;
    try {
        const res = await fetch(route('scrap-tasks.test-list-selector', props.task.id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        testSelectorResult.value = data.message || (data.count !== undefined ? `Matched elements: ${data.count}` : 'Error');
    } catch {
        testSelectorResult.value = 'Server communication error.';
    } finally {
        testSelectorLoading.value = false;
    }
}

onMounted(() => {
    if (props.task?.status === 'running') {
        startPolling();
    }
});

watch(
    () => displayTask.value?.status,
    (status) => {
        if (status === 'running') {
            startPolling();
        } else if (status === 'completed' || status === 'failed') {
            stopPolling();
        }
    }
);

onUnmounted(() => {
    stopPolling();
});
</script>

<style scoped>
.cell-wrap {
    white-space: normal;
    word-break: break-word;
    max-width: 280px;
    max-height: 7rem;
    overflow-y: auto;
    font-size: 0.8125rem;
    line-height: 1.35;
}
.report-table td,
.report-table th {
    vertical-align: top;
}
</style>
