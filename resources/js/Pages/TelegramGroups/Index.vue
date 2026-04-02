<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <span>{{ t('telegram_groups.title') }}</span>
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
        <div v-if="refreshError" class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
            {{ refreshError }}
        </div>

        <div v-if="!telegramConnected && (channelFilter === 'all' || channelFilter === 'telegram')" class="p-6 border border-amber-200 rounded-lg bg-amber-50 mb-6">
            <p class="text-gray-800 mb-4">{{ t('telegram_groups.connect_telegram_first') }}</p>
            <Link :href="route('settings.index')" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700">
                {{ t('telegram_groups.go_to_telegram_settings') }}
            </Link>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <p class="text-sm text-gray-600 mb-4">
                        {{ t('telegram_groups.groups_help_text') }}
                    </p>
                    <div class="flex flex-wrap gap-4 items-center">
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-gray-700">{{ t('telegram_groups.filter_channel') }}:</label>
                            <select
                                v-model="filterChannel"
                                @change="applyFilters"
                                class="rounded-md border-gray-300 text-sm py-1.5"
                            >
                                <option value="all">{{ t('telegram_crawler.all') }}</option>
                                <option value="telegram">{{ t('telegram_groups.channel_telegram') }}</option>
                                <option value="whatsapp">{{ t('telegram_groups.channel_whatsapp') }}</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-gray-700">{{ t('telegram_groups.category_label') }}:</label>
                            <select
                                v-model="filterCategory"
                                @change="applyFilters"
                                class="rounded-md border-gray-300 text-sm py-1.5"
                            >
                                <option value="">{{ t('telegram_crawler.all') }}</option>
                                <option v-for="c in telegramGroupCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-gray-700">{{ t('telegram_groups.language_label') }}:</label>
                            <select
                                v-model="filterLanguage"
                                @change="applyFilters"
                                class="rounded-md border-gray-300 text-sm py-1.5"
                            >
                                <option value="">{{ t('telegram_crawler.all') }}</option>
                                <option v-for="lang in languages" :key="lang.id" :value="lang.code">{{ lang.name }}</option>
                            </select>
                        </div>
                        <div class="ml-auto flex items-center gap-2">
                            <Link
                                v-if="filterCategory || filterLanguage || filterChannel !== 'all'"
                                :href="route('groups.index')"
                                class="text-sm text-blue-600 hover:text-blue-800"
                            >
                                {{ t('telegram_groups.clear_filters') }}
                            </Link>
                            <button
                                v-if="channelFilter !== 'whatsapp'"
                                type="button"
                                @click="refreshGroups"
                                :disabled="refreshing || !telegramConnected"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium disabled:opacity-50"
                            >
                                {{ refreshing ? t('telegram_groups.refreshing') : t('telegram_groups.refresh') }}
                            </button>
                            <Link
                                v-if="channelFilter !== 'whatsapp'"
                                :href="route('telegram-crawler.index')"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium"
                            >
                                {{ t('telegram_groups.crawl') }}
                            </Link>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('telegram_groups.column_channel') }}</th>
                                <th class="px-4 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('telegram_groups.column_title') }}</th>
                                <th class="px-4 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('telegram_groups.column_type') }}</th>
                                <th class="px-4 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('common.category') }}</th>
                                <th class="px-4 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('telegram_groups.column_language') }}</th>
                                <th class="px-4 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('telegram_groups.column_id') }}</th>
                                <th class="px-4 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('telegram_groups.column_can_post') }}</th>
                                <th class="px-4 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('telegram_groups.column_last_synced') }}</th>
                                <th class="px-4 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-if="!groups.data?.length" class="text-center text-gray-500 py-8">
                                <td colspan="9" class="px-4 py-6">
                                    {{ t('telegram_groups.no_groups_found') }}
                                </td>
                            </tr>
                            <tr v-for="g in groups.data" :key="g.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm">
                                    <span
                                        class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full"
                                        :class="g.channel === 'whatsapp' ? 'bg-emerald-100 text-emerald-800' : 'bg-sky-100 text-sky-800'"
                                    >
                                        {{ g.channel === 'whatsapp' ? t('telegram_groups.channel_whatsapp') : t('telegram_groups.channel_telegram') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ g.title || t('common.dash') }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ g.type || t('common.dash') }}</td>
                                <td class="px-4 py-3">
                                    <select
                                        :value="g.category?.id || ''"
                                        @change="(e) => updateGroup(g, 'category', e.target.value)"
                                        :disabled="updatingGroupId === g.id"
                                        class="text-sm rounded border-gray-300 py-1 min-w-[100px] disabled:opacity-50"
                                    >
                                        <option value="">{{ t('common.dash') }}</option>
                                        <option v-for="c in telegramGroupCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <select
                                        :value="g.language || ''"
                                        @change="(e) => updateGroup(g, 'language', e.target.value)"
                                        :disabled="updatingGroupId === g.id"
                                        class="text-sm rounded border-gray-300 py-1 min-w-[100px] disabled:opacity-50"
                                    >
                                        <option value="">{{ t('common.dash') }}</option>
                                        <option v-for="lang in languages" :key="lang.id" :value="lang.code">{{ lang.name }}</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-500 break-all max-w-[14rem]">{{ g.telegram_group_id }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="g.can_post"
                                        class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800"
                                    >
                                        {{ t('common.yes') }}
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-800"
                                        :title="g.last_error"
                                    >
                                        {{ t('common.no') }}
                                    </span>
                                    <span v-if="g.last_error" class="text-xs text-amber-600 block mt-0.5 truncate max-w-[12rem]" :title="g.last_error">{{ g.last_error }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(g.last_synced_at) }}</td>
                                <td class="px-4 py-3">
                                    <Link
                                        v-if="g.channel !== 'whatsapp'"
                                        :href="route('telegram-crawler.index')"
                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                    >
                                        {{ t('telegram_groups.crawl_send') }}
                                    </Link>
                                    <span v-else class="text-sm text-gray-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div v-if="groups.data?.length && groups.last_page > 1" class="px-4 py-3 border-t border-gray-200 flex items-center justify-between">
                    <p class="text-sm text-gray-600">
                        {{ t('telegram_groups.pagination_page_of').replace(':current', String(groups.current_page)).replace(':last', String(groups.last_page)).replace(':total', String(groups.total)) }}
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-if="groups.prev_page_url"
                            :href="groups.prev_page_url"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm"
                        >
                            {{ t('common.previous') }}
                        </Link>
                        <Link
                            v-if="groups.next_page_url"
                            :href="groups.next_page_url"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm"
                        >
                            {{ t('common.next') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    telegramConnected: { type: Boolean, default: false },
    channelFilter: { type: String, default: 'all' },
    groups: { type: Object, default: () => ({ data: [], links: [] }) },
});

const page = usePage();
const telegramGroupCategories = computed(() => page.props.telegramGroupCategories || []);
const languages = computed(() => page.props.languages || []);

const filterCategory = ref('');
const filterLanguage = ref('');
const filterChannel = ref(props.channelFilter || 'all');
const updatingGroupId = ref(null);
const refreshing = ref(false);
const refreshError = ref('');

onMounted(() => {
    const q = new URLSearchParams(window.location.search);
    filterCategory.value = q.get('category') || '';
    filterLanguage.value = q.get('language') || '';
    filterChannel.value = q.get('channel') || props.channelFilter || 'all';
});

const applyFilters = () => {
    const params = {};
    if (filterCategory.value) params.category = filterCategory.value;
    if (filterLanguage.value) params.language = filterLanguage.value;
    if (filterChannel.value && filterChannel.value !== 'all') params.channel = filterChannel.value;
    router.get(route('groups.index'), params, { preserveState: true });
};

const updateGroup = async (g, field, value) => {
    updatingGroupId.value = g.id;
    try {
        const payload = field === 'category'
            ? { telegram_group_category_id: value ? parseInt(value, 10) : null }
            : { language: value || null };
        const { data } = await axios.patch(route('groups.update', g.id), payload);
        if (data.group) {
            g.category = data.group.category;
            g.language = data.group.language;
        }
    } catch (e) {
        console.error('Update failed', e);
    } finally {
        updatingGroupId.value = null;
    }
};

const refreshGroups = async () => {
    refreshing.value = true;
    refreshError.value = '';
    try {
        await axios.get(route('telegram-crawler.groups'), {
            params: { refresh: 1 },
            timeout: 240000,
        });
        applyFilters();
    } catch (e) {
        refreshError.value = e.response?.data?.error || e.message || t('telegram_groups.refresh_failed');
    } finally {
        refreshing.value = false;
    }
};

const formatDate = (v) => {
    if (!v) return t('common.dash');
    try {
        const d = new Date(v);
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return v;
    }
};
</script>
