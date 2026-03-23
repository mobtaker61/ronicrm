<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <span>Telegram Scheduled Sends</span>
            </div>
        </template>

        <div v-if="$page.props.flash?.success || $page.props.flash?.error" class="mb-4">
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.error }}
            </div>
        </div>
        <div v-if="error" class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
            {{ error }}
        </div>

        <!-- Not Connected -->
        <div v-if="!telegramConnected" class="p-6 border border-amber-200 rounded-lg bg-amber-50">
            <p class="text-gray-800 mb-4">Connect your Telegram account in Settings first.</p>
            <Link :href="route('settings.index')" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700">
                Go to Telegram Settings
            </Link>
        </div>

        <div v-else class="space-y-6">
            <!-- Create Form -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Add Scheduled Send</h2>
                <p class="text-sm text-gray-600 mb-4">
                    Choose template or post link, select a category. Sends run daily at the specified time for the given number of days. Only groups with can_post are used. Time uses server timezone ({{ timezone }}).
                </p>
                <form @submit.prevent="create" class="space-y-4">
                    <div class="flex flex-wrap gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select v-model="form.type" class="rounded-md border-gray-300 text-sm py-2 min-w-[160px]">
                                <option value="template">Template</option>
                                <option value="forward">Forward Post</option>
                            </select>
                        </div>
                        <div v-if="form.type === 'template'" class="min-w-[200px]">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Template</label>
                            <select v-model="form.campaign_template_id" required class="w-full rounded-md border-gray-300 text-sm py-2">
                                <option value="">Select...</option>
                                <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div v-else class="min-w-[280px]">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Post link</label>
                            <input
                                v-model="form.post_link"
                                type="text"
                                placeholder="t.me/channel/123 or t.me/c/1234567890/123"
                                class="w-full rounded-md border-gray-300 text-sm py-2"
                            />
                        </div>
                        <div class="min-w-[180px]">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select v-model="form.telegram_group_category_id" required class="w-full rounded-md border-gray-300 text-sm py-2">
                                <option value="">Select...</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Time (HH:MM)</label>
                            <input
                                v-model="form.send_at_time"
                                type="time"
                                required
                                class="rounded-md border-gray-300 text-sm py-2"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Days</label>
                            <input
                                v-model.number="form.days_count"
                                type="number"
                                min="1"
                                max="365"
                                required
                                class="rounded-md border-gray-300 text-sm py-2 w-20"
                            />
                        </div>
                        <div class="flex items-end">
                            <button
                                type="submit"
                                :disabled="saving"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                            >
                                {{ saving ? 'Adding...' : 'Add' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- List -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Scheduled Sends</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Content</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progress</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-if="!schedules.length" class="text-center text-gray-500 py-8">
                                <td colspan="7" class="px-4 py-6">No scheduled sends yet.</td>
                            </tr>
                            <template v-for="s in schedules" :key="s.id">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ s.type_label }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 max-w-[200px] truncate" :title="s.template?.name || s.post_link">
                                        {{ s.type === 'template' ? (s.template?.name || '—') : (s.post_link || '—') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ s.category?.name || '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ s.send_at_time }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ s.runs_count }} / {{ s.days_count }}</td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="[
                                                'inline-flex px-2 py-0.5 text-xs font-medium rounded-full',
                                                s.status === 'active' ? 'bg-green-100 text-green-800' : '',
                                                s.status === 'stopped' ? 'bg-gray-100 text-gray-800' : '',
                                                s.status === 'completed' ? 'bg-blue-100 text-blue-800' : ''
                                            ]"
                                        >
                                            {{ s.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="openEdit(s)"
                                            class="text-indigo-600 hover:text-indigo-800 text-sm font-medium"
                                            title="Edit"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            @click="openReport(s)"
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                            title="Execution report"
                                        >
                                            Report
                                        </button>
                                        <button
                                            v-if="s.status === 'active'"
                                            type="button"
                                            @click="stop(s)"
                                            :disabled="stoppingId === s.id"
                                            class="text-red-600 hover:text-red-800 text-sm font-medium disabled:opacity-50"
                                        >
                                            {{ stoppingId === s.id ? '...' : 'Stop' }}
                                        </button>
                                        <span v-else-if="s.status !== 'active'" class="text-gray-400 text-sm">—</span>
                                    </td>
                                </tr>
                                <tr v-if="s.runs?.length" class="bg-gray-50/50">
                                    <td colspan="7" class="px-4 py-2">
                                        <div class="text-xs text-gray-600 space-y-1">
                                            <div v-for="r in s.runs" :key="r.id" class="flex gap-4">
                                                <span>{{ r.run_date }}</span>
                                                <span :class="r.status === 'completed' ? 'text-green-700' : 'text-amber-700'">{{ r.status }}</span>
                                                <span>✓ {{ r.sent_count }}</span>
                                                <span v-if="r.failed_count" class="text-red-700">✗ {{ r.failed_count }}</span>
                                                <span v-if="r.pending_count" class="text-gray-500">⏳ {{ r.pending_count }}</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Report Modal -->
            <Teleport to="body">
                <div
                    v-if="reportModal.show"
                    class="fixed inset-0 z-50 overflow-y-auto"
                    aria-labelledby="modal-title"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="flex min-h-screen items-center justify-center p-4">
                        <div class="fixed inset-0 bg-gray-500/75 transition-opacity" @click="closeReport" />
                        <div class="relative bg-white rounded-xl shadow-xl max-w-3xl w-full max-h-[90vh] flex flex-col">
                            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    Execution Report — {{ reportModal.schedule?.type_label }}: {{ reportModal.schedule?.content || '—' }}
                                </h3>
                                <button
                                    type="button"
                                    @click="closeReport"
                                    class="text-gray-400 hover:text-gray-600 rounded-lg p-1"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <div class="px-6 py-4 overflow-auto flex-1">
                                <div v-if="reportModal.loading" class="text-center py-12 text-gray-500">Loading...</div>
                                <div v-else-if="reportModal.error" class="text-red-600 py-4">{{ reportModal.error }}</div>
                                <div v-else-if="!reportModal.runs?.length" class="text-gray-500 py-8 text-center">No runs recorded yet.</div>
                                <div v-else class="space-y-6">
                                    <div v-for="r in reportModal.runs" :key="r.id" class="border border-gray-200 rounded-lg overflow-hidden">
                                        <div class="px-4 py-2 bg-gray-50 flex items-center justify-between gap-4 flex-wrap">
                                            <span class="font-medium text-gray-900">{{ r.run_date }}</span>
                                            <div class="flex gap-3 text-sm">
                                                <span :class="r.status === 'completed' ? 'text-green-700' : 'text-amber-700'">{{ r.status }}</span>
                                                <span class="text-green-600">✓ {{ r.sent_count }}</span>
                                                <span v-if="r.failed_count" class="text-red-600">✗ {{ r.failed_count }}</span>
                                                <span v-if="r.pending_count" class="text-gray-500">⏳ {{ r.pending_count }}</span>
                                            </div>
                                        </div>
                                        <div class="divide-y divide-gray-100">
                                            <div
                                                v-for="item in r.items"
                                                :key="item.id"
                                                class="px-4 py-2 flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 text-sm"
                                            >
                                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                                    <span class="text-gray-700 truncate" :title="item.telegram_group_id">{{ item.group_title }}</span>
                                                    <span
                                                        :class="[
                                                            'shrink-0 px-2 py-0.5 rounded text-xs font-medium',
                                                            item.status === 'sent' && 'bg-green-100 text-green-800',
                                                            item.status === 'failed' && 'bg-red-100 text-red-800',
                                                            item.status === 'pending' && 'bg-gray-100 text-gray-600'
                                                        ]"
                                                    >
                                                        {{ item.status === 'sent' ? 'Sent' : item.status === 'failed' ? 'Failed' : 'Pending' }}
                                                    </span>
                                                    <span v-if="item.sent_at" class="text-xs text-gray-400 shrink-0">{{ formatDate(item.sent_at) }}</span>
                                                </div>
                                                <span v-if="item.error" class="text-xs text-red-600 truncate" :title="item.error">{{ item.error }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Teleport>

            <!-- Edit Modal -->
            <Teleport to="body">
                <div
                    v-if="editModal.show"
                    class="fixed inset-0 z-50 overflow-y-auto"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="flex min-h-screen items-center justify-center p-4">
                        <div class="fixed inset-0 bg-gray-500/75 transition-opacity" @click="closeEdit" />
                        <div class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full">
                            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">Edit Scheduled Send</h3>
                                <button type="button" @click="closeEdit" class="text-gray-400 hover:text-gray-600 rounded-lg p-1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <form @submit.prevent="update" class="p-6 space-y-4">
                                <div class="flex flex-wrap gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                        <select v-model="editForm.type" class="rounded-md border-gray-300 text-sm py-2 min-w-[140px]">
                                            <option value="template">Template</option>
                                            <option value="forward">Forward Post</option>
                                        </select>
                                    </div>
                                    <div v-if="editForm.type === 'template'" class="min-w-[200px] flex-1">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Template</label>
                                        <select v-model="editForm.campaign_template_id" required class="w-full rounded-md border-gray-300 text-sm py-2">
                                            <option value="">Select...</option>
                                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                                        </select>
                                    </div>
                                    <div v-else class="min-w-[240px] flex-1">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Post link</label>
                                        <input
                                            v-model="editForm.post_link"
                                            type="text"
                                            placeholder="t.me/channel/123"
                                            class="w-full rounded-md border-gray-300 text-sm py-2"
                                        />
                                    </div>
                                    <div class="min-w-[160px]">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                        <select v-model="editForm.telegram_group_category_id" required class="w-full rounded-md border-gray-300 text-sm py-2">
                                            <option value="">Select...</option>
                                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Time (HH:MM)</label>
                                        <input
                                            v-model="editForm.send_at_time"
                                            type="time"
                                            required
                                            class="rounded-md border-gray-300 text-sm py-2"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Days</label>
                                        <input
                                            v-model.number="editForm.days_count"
                                            type="number"
                                            min="1"
                                            max="365"
                                            required
                                            class="rounded-md border-gray-300 text-sm py-2 w-20"
                                        />
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" @click="closeEdit" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 text-sm font-medium">Cancel</button>
                                    <button type="submit" :disabled="editModal.loading" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 text-sm font-medium">
                                        {{ editModal.loading ? 'Saving...' : 'Save' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    telegramConnected: { type: Boolean, default: false },
    templates: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    schedules: { type: Array, default: () => [] },
    timezone: { type: String, default: 'UTC' },
    currentTime: { type: String, default: '09:00' },
});

const error = ref('');
const saving = ref(false);
const stoppingId = ref(null);

const form = reactive({
    type: 'template',
    campaign_template_id: '',
    post_link: '',
    telegram_group_category_id: '',
    send_at_time: props.currentTime || '09:00',
    days_count: 7,
});

const schedules = ref(props.schedules);

const resetForm = () => {
    form.type = 'template';
    form.campaign_template_id = '';
    form.post_link = '';
    form.telegram_group_category_id = '';
    form.send_at_time = props.currentTime || '09:00';
    form.days_count = 7;
};

const create = async () => {
    if (form.type === 'template' && !form.campaign_template_id) {
        error.value = 'Select a template.';
        return;
    }
    if (form.type === 'forward' && !form.post_link?.trim()) {
        error.value = 'Enter post link.';
        return;
    }
    error.value = '';
    saving.value = true;
    try {
        const payload = {
            type: form.type,
            campaign_template_id: form.type === 'template' ? form.campaign_template_id : null,
            post_link: form.type === 'forward' ? form.post_link : null,
            telegram_group_category_id: form.telegram_group_category_id,
            send_at_time: form.send_at_time,
            days_count: form.days_count,
        };
        const { data } = await axios.post(route('telegram.scheduled-sends.store'), payload);
        if (data.schedule) {
            schedules.value = [data.schedule, ...schedules.value];
            resetForm();
        }
    } catch (e) {
        error.value = e.response?.data?.error || e.message || 'Failed to add.';
    } finally {
        saving.value = false;
    }
};

const stop = async (s) => {
    stoppingId.value = s.id;
    try {
        await axios.post(route('telegram.scheduled-sends.stop', { schedule: s.id }));
        const idx = schedules.value.findIndex(x => x.id === s.id);
        if (idx >= 0) {
            schedules.value[idx] = { ...schedules.value[idx], status: 'stopped' };
        }
    } catch (e) {
        error.value = e.response?.data?.error || e.message || 'Failed to stop.';
    } finally {
        stoppingId.value = null;
    }
};

const reportModal = reactive({
    show: false,
    loading: false,
    error: '',
    schedule: null,
    runs: [],
});

const openReport = async (s) => {
    reportModal.show = true;
    reportModal.loading = true;
    reportModal.error = '';
    reportModal.schedule = { type_label: s.type_label, content: s.type === 'template' ? (s.template?.name || '—') : (s.post_link || '—') };
    reportModal.runs = [];
    try {
        const { data } = await axios.get(route('telegram.scheduled-sends.report', { schedule: s.id }));
        reportModal.schedule = data.schedule;
        reportModal.runs = data.runs;
    } catch (e) {
        reportModal.error = e.response?.data?.error || e.message || 'Failed to load report.';
    } finally {
        reportModal.loading = false;
    }
};

const closeReport = () => {
    reportModal.show = false;
};

const formatDate = (iso) => {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        return d.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch {
        return iso;
    }
};

const editModal = reactive({
    show: false,
    schedule: null,
    loading: false,
});

const editForm = reactive({
    type: 'template',
    campaign_template_id: '',
    post_link: '',
    telegram_group_category_id: '',
    send_at_time: '09:00',
    days_count: 7,
});

const openEdit = (s) => {
    editModal.schedule = s;
    editForm.type = s.type;
    editForm.campaign_template_id = s.type === 'template' ? String(s.template?.id || '') : '';
    editForm.post_link = s.post_link || '';
    editForm.telegram_group_category_id = String(s.category?.id || '');
    editForm.send_at_time = s.send_at_time || props.currentTime || '09:00';
    editForm.days_count = s.days_count || 7;
    editModal.show = true;
};

const closeEdit = () => {
    editModal.show = false;
    editModal.schedule = null;
};

const update = async () => {
    const s = editModal.schedule;
    if (!s) return;
    if (editForm.type === 'template' && !editForm.campaign_template_id) {
        error.value = 'Select a template.';
        return;
    }
    if (editForm.type === 'forward' && !editForm.post_link?.trim()) {
        error.value = 'Enter post link.';
        return;
    }
    error.value = '';
    editModal.loading = true;
    try {
        const payload = {
            type: editForm.type,
            campaign_template_id: editForm.type === 'template' ? editForm.campaign_template_id : null,
            post_link: editForm.type === 'forward' ? editForm.post_link : null,
            telegram_group_category_id: editForm.telegram_group_category_id,
            send_at_time: editForm.send_at_time,
            days_count: editForm.days_count,
        };
        const { data } = await axios.put(route('telegram.scheduled-sends.update', { schedule: s.id }), payload);
        if (data.schedule) {
            const idx = schedules.value.findIndex(x => x.id === s.id);
            if (idx >= 0) schedules.value[idx] = data.schedule;
            closeEdit();
        }
    } catch (e) {
        error.value = e.response?.data?.error || e.message || 'Failed to update.';
    } finally {
        editModal.loading = false;
    }
};
</script>
