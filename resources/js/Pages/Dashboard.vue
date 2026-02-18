<template>
    <AppLayout>
        <template #header>
            Dashboard
        </template>

        <div class="space-y-6">
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    :href="route('customers.index')"
                    class="bg-white rounded-lg shadow p-5 hover:shadow-md transition-shadow border border-gray-100"
                >
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-500 rounded-lg p-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div class="ml-4 flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-500">Customers</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ stats.total_customers }}</p>
                            <p v-if="stats.customers_this_week > 0" class="text-xs text-green-600">+{{ stats.customers_this_week }} this week</p>
                        </div>
                    </div>
                </Link>

                <Link
                    :href="route('inbox.index')"
                    class="bg-white rounded-lg shadow p-5 hover:shadow-md transition-shadow border border-gray-100 relative"
                >
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-emerald-500 rounded-lg p-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <div class="ml-4 flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-500">Unread Inbox</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ stats.unread_inbox_total }}</p>
                        </div>
                    </div>
                    <span
                        v-if="stats.unread_inbox_total > 0"
                        class="absolute top-3 right-3 bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"
                    >
                        {{ stats.unread_inbox_total }}
                    </span>
                </Link>

                <Link
                    :href="route('campaigns.index')"
                    class="bg-white rounded-lg shadow p-5 hover:shadow-md transition-shadow border border-gray-100"
                >
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-amber-500 rounded-lg p-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        </div>
                        <div class="ml-4 flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-500">Campaigns</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ stats.total_campaigns }}</p>
                            <p v-if="stats.active_campaigns > 0" class="text-xs text-amber-600">{{ stats.active_campaigns }} active</p>
                        </div>
                    </div>
                </Link>

                <div class="bg-white rounded-lg shadow p-5 border border-gray-100">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-500 rounded-lg p-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="ml-4 flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-500">Templates</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ stats.total_templates }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Connections & Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <Link
                    :href="route('inbox.index', { channel: 'telegram' })"
                    class="flex items-center justify-between p-4 bg-white rounded-lg shadow border border-gray-100 hover:border-blue-200 hover:shadow-md transition-all"
                >
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" :class="stats.telegram_connected ? 'bg-blue-100' : 'bg-gray-100'">
                            <svg class="w-5 h-5" :class="stats.telegram_connected ? 'text-blue-600' : 'text-gray-400'" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.295-.6.295-.002 0-.003 0-.005 0l.213-3.054 5.56-5.022c.24-.213-.054-.334-.373-.121l-6.869 4.326-2.96-.924c-.64-.203-.658-.64.135-.954l11.566-4.458c.538-.196 1.006.128.832.941z"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="font-medium text-gray-900">Telegram</p>
                            <p class="text-sm" :class="stats.telegram_connected ? 'text-green-600' : 'text-gray-500'">
                                {{ stats.telegram_connected ? 'Connected' : 'Not connected' }}
                            </p>
                        </div>
                    </div>
                    <span v-if="inboxUnread.telegram > 0" class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">{{ inboxUnread.telegram }}</span>
                </Link>
                <Link
                    :href="route('inbox.index', { channel: 'whatsapp' })"
                    class="flex items-center justify-between p-4 bg-white rounded-lg shadow border border-gray-100 hover:border-green-200 hover:shadow-md transition-all"
                >
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="font-medium text-gray-900">WhatsApp</p>
                            <p class="text-sm text-gray-500">Inbox</p>
                        </div>
                    </div>
                    <span v-if="inboxUnread.whatsapp > 0" class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">{{ inboxUnread.whatsapp }}</span>
                </Link>
                <Link
                    :href="route('inbox.index', { channel: 'instagram' })"
                    class="flex items-center justify-between p-4 bg-white rounded-lg shadow border border-gray-100 hover:border-pink-200 hover:shadow-md transition-all"
                >
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" :class="stats.instagram_connected ? 'bg-pink-100' : 'bg-gray-100'">
                            <svg class="w-5 h-5" :class="stats.instagram_connected ? 'text-pink-600' : 'text-gray-400'" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="font-medium text-gray-900">Instagram</p>
                            <p class="text-sm" :class="stats.instagram_connected ? 'text-green-600' : 'text-gray-500'">
                                {{ stats.instagram_connected ? 'Connected' : 'Not connected' }}
                            </p>
                        </div>
                    </div>
                    <span v-if="inboxUnread.instagram > 0" class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">{{ inboxUnread.instagram }}</span>
                </Link>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Inbox -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Inbox</h3>
                        <Link :href="route('inbox.index')" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                            Open Inbox →
                        </Link>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="msg in recentInbox"
                            :key="msg.id"
                            class="flex items-start gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <span
                                class="flex-shrink-0 px-2 py-0.5 text-xs font-medium rounded"
                                :class="{
                                    'bg-blue-100 text-blue-800': msg.channel === 'telegram',
                                    'bg-green-100 text-green-800': msg.channel === 'whatsapp',
                                    'bg-pink-100 text-pink-800': msg.channel === 'instagram',
                                }"
                            >
                                {{ msg.channel }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ msg.from }}</p>
                                <p class="text-sm text-gray-600 truncate">{{ msg.message || '(media)' }}</p>
                            </div>
                            <div class="flex-shrink-0 flex items-center gap-1">
                                <span v-if="msg.unread" class="w-2 h-2 bg-blue-500 rounded-full" title="Unread"></span>
                                <span class="text-xs text-gray-500">{{ formatTime(msg.created_at) }}</span>
                            </div>
                        </div>
                        <p v-if="recentInbox.length === 0" class="text-sm text-gray-500 text-center py-8">No recent messages</p>
                    </div>
                </div>

                <!-- Customers by Status -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Customers by Status</h3>
                    <div class="space-y-3">
                        <div
                            v-for="(count, status) in customersByStatus"
                            :key="status"
                            class="flex items-center justify-between"
                        >
                            <span class="text-sm font-medium text-gray-700 capitalize">{{ status || 'Unknown' }}</span>
                            <div class="flex items-center space-x-3">
                                <div class="w-28 bg-gray-200 rounded-full h-2">
                                    <div
                                        class="h-2 rounded-full transition-all"
                                        :class="{
                                            'bg-yellow-500': status === 'lead',
                                            'bg-blue-500': status === 'prospect',
                                            'bg-green-500': status === 'customer',
                                            'bg-gray-500': status === 'inactive' || status === 'unknown',
                                        }"
                                        :style="{ width: `${stats.total_customers ? (count / stats.total_customers) * 100 : 0}%` }"
                                    ></div>
                                </div>
                                <span class="text-sm font-bold text-gray-900 w-8 text-right">{{ count }}</span>
                            </div>
                        </div>
                        <p v-if="Object.keys(customersByStatus).length === 0" class="text-sm text-gray-500 py-4">No customers yet</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Customers by Source -->
                <div v-if="Object.keys(customersBySource).length > 0" class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Customers by Source</h3>
                    <div class="space-y-2">
                        <div
                            v-for="(count, source) in customersBySource"
                            :key="source"
                            class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0"
                        >
                            <span class="text-sm font-medium text-gray-700 capitalize">{{ source }}</span>
                            <span class="text-sm font-semibold text-gray-900">{{ count }}</span>
                        </div>
                    </div>
                </div>

                <!-- Top Industries -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Industries</h3>
                    <div class="space-y-2">
                        <div
                            v-for="industry in customersByIndustry"
                            :key="industry.name"
                            class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0"
                        >
                            <span class="text-sm font-medium text-gray-700">{{ industry.name }}</span>
                            <span class="text-sm font-semibold text-gray-900">{{ industry.count }} customers</span>
                        </div>
                        <p v-if="customersByIndustry.length === 0" class="text-sm text-gray-500 py-4">No industries assigned</p>
                    </div>
                </div>
            </div>

            <!-- Recent Campaigns -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Campaigns</h3>
                    <Link :href="route('campaigns.index')" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                        View All →
                    </Link>
                </div>
                <div class="space-y-3">
                    <div
                        v-for="campaign in recentCampaigns"
                        :key="campaign.id"
                        class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ campaign.name }}</p>
                            <p class="text-xs text-gray-500">
                                {{ campaign.type }} • {{ campaign.status }} • {{ campaign.creator?.name }}
                            </p>
                        </div>
                        <span class="text-xs text-gray-500">
                            {{ new Date(campaign.created_at).toLocaleDateString() }}
                        </span>
                    </div>
                    <p v-if="recentCampaigns.length === 0" class="text-sm text-gray-500 text-center py-8">No campaigns yet</p>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <Link
                    :href="route('telegram-groups.index')"
                    class="flex flex-col items-center p-4 bg-white rounded-lg shadow border border-gray-100 hover:border-blue-200 hover:shadow-md transition-all"
                >
                    <span class="text-2xl font-bold text-gray-900">{{ stats.telegram_groups }}</span>
                    <span class="text-sm text-gray-600 mt-1">Telegram Groups</span>
                </Link>
                <Link
                    :href="route('campaign-templates.index')"
                    class="flex flex-col items-center p-4 bg-white rounded-lg shadow border border-gray-100 hover:border-purple-200 hover:shadow-md transition-all"
                >
                    <span class="text-2xl font-bold text-gray-900">{{ stats.total_templates }}</span>
                    <span class="text-sm text-gray-600 mt-1">Templates</span>
                </Link>
                <Link
                    :href="route('telegram-crawler.index')"
                    class="flex flex-col items-center p-4 bg-white rounded-lg shadow border border-gray-100 hover:border-sky-200 hover:shadow-md transition-all"
                >
                    <span class="text-2xl font-bold text-gray-900">Crawl</span>
                    <span class="text-sm text-gray-600 mt-1">Telegram Groups</span>
                </Link>
                <Link
                    :href="route('scrap-tasks.index')"
                    class="flex flex-col items-center p-4 bg-white rounded-lg shadow border border-gray-100 hover:border-amber-200 hover:shadow-md transition-all"
                >
                    <span class="text-2xl font-bold text-gray-900">Scrap</span>
                    <span class="text-sm text-gray-600 mt-1">Web Tasks</span>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_customers: 0,
            customers_this_week: 0,
            total_campaigns: 0,
            active_campaigns: 0,
            total_industries: 0,
            total_templates: 0,
            telegram_groups: 0,
            telegram_connected: false,
            instagram_connected: false,
            unread_inbox_total: 0,
        }),
    },
    inboxUnread: {
        type: Object,
        default: () => ({ telegram: 0, whatsapp: 0, instagram: 0 }),
    },
    customersByStatus: {
        type: Object,
        default: () => ({}),
    },
    customersBySource: {
        type: Object,
        default: () => ({}),
    },
    customersByIndustry: {
        type: Array,
        default: () => [],
    },
    recentInbox: {
        type: Array,
        default: () => [],
    },
    recentCampaigns: {
        type: Array,
        default: () => [],
    },
});

function formatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    if (minutes < 1) return 'Now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;
    return date.toLocaleDateString();
}
</script>
