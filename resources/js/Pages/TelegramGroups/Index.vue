<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <span>Telegram Groups</span>
                <div class="flex gap-2">
                    <Link
                        :href="route('telegram-crawler.index')"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium"
                    >
                        Crawl
                    </Link>
                </div>
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

        <!-- Not Connected -->
        <div v-if="!telegramConnected" class="p-6 border border-amber-200 rounded-lg bg-amber-50">
            <p class="text-gray-800 mb-4">Connect your Telegram account in Settings to view groups.</p>
            <Link :href="route('settings.index')" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700">
                Go to Telegram Settings
            </Link>
        </div>

        <!-- Connected -->
        <div v-else class="space-y-6">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <p class="text-sm text-gray-600">
                        Groups you're a member of. Use Refresh on the crawl page to sync. Groups you've left are removed from the table.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Can Post</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Synced</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-if="!groups.data?.length" class="text-center text-gray-500 py-8">
                                <td colspan="6" class="px-4 py-6">
                                    No groups found. Click Refresh on the Crawl page to fetch groups.
                                </td>
                            </tr>
                            <tr v-for="g in groups.data" :key="g.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ g.title || '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ g.type || '—' }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-500">{{ g.telegram_group_id }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="g.can_post"
                                        class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800"
                                    >
                                        Yes
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-800"
                                        :title="g.last_error"
                                    >
                                        No
                                    </span>
                                    <span v-if="g.last_error" class="text-xs text-amber-600 block mt-0.5 truncate max-w-[12rem]" :title="g.last_error">{{ g.last_error }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(g.last_synced_at) }}</td>
                                <td class="px-4 py-3">
                                    <Link
                                        :href="route('telegram-crawler.index')"
                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                    >
                                        Crawl / Send
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div v-if="groups.data?.length && groups.last_page > 1" class="px-4 py-3 border-t border-gray-200 flex items-center justify-between">
                    <p class="text-sm text-gray-600">
                        Page {{ groups.current_page }} of {{ groups.last_page }} ({{ groups.total }} groups)
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-if="groups.prev_page_url"
                            :href="groups.prev_page_url"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm"
                        >
                            Previous
                        </Link>
                        <Link
                            v-if="groups.next_page_url"
                            :href="groups.next_page_url"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm"
                        >
                            Next
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    telegramConnected: { type: Boolean, default: false },
    groups: { type: Object, default: () => ({ data: [], links: [] }) },
});

const formatDate = (v) => {
    if (!v) return '—';
    try {
        const d = new Date(v);
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return v;
    }
};
</script>
