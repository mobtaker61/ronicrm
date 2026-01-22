<template>
    <AppLayout>
        <template #header>
            Reports
        </template>

        <div class="space-y-6">
            <!-- Customer Reports -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Customer Reports</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- By Industry -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-700 mb-3">By Industry</h3>
                        <div class="space-y-2">
                            <div
                                v-for="item in customersByIndustry"
                                :key="item.id"
                                class="flex justify-between items-center p-2 bg-gray-50 rounded"
                            >
                                <span class="text-sm text-gray-700">{{ item.name }}</span>
                                <span class="text-sm font-medium text-gray-900">{{ item.customers_count }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- By Status -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-700 mb-3">By Status</h3>
                        <div class="space-y-2">
                            <div
                                v-for="item in customersByStatus"
                                :key="item.status"
                                class="flex justify-between items-center p-2 bg-gray-50 rounded"
                            >
                                <span class="text-sm text-gray-700 capitalize">{{ item.status }}</span>
                                <span class="text-sm font-medium text-gray-900">{{ item.count }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- By Source -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-700 mb-3">By Source</h3>
                        <div class="space-y-2">
                            <div
                                v-for="item in customersBySource"
                                :key="item.source"
                                class="flex justify-between items-center p-2 bg-gray-50 rounded"
                            >
                                <span class="text-sm text-gray-700">{{ item.source }}</span>
                                <span class="text-sm font-medium text-gray-900">{{ item.count }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Campaign Reports -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Campaign Reports</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-blue-600 font-medium">Total Campaigns</p>
                        <p class="text-2xl font-bold text-blue-900">{{ campaignStats.total }}</p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-sm text-green-600 font-medium">Completed</p>
                        <p class="text-2xl font-bold text-green-900">{{ campaignStats.completed }}</p>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <p class="text-sm text-yellow-600 font-medium">Running</p>
                        <p class="text-2xl font-bold text-yellow-900">{{ campaignStats.running }}</p>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <p class="text-sm text-purple-600 font-medium">Scheduled</p>
                        <p class="text-2xl font-bold text-purple-900">{{ campaignStats.scheduled }}</p>
                    </div>
                </div>

                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">By Type</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div
                            v-for="item in campaignsByType"
                            :key="item.type"
                            class="flex justify-between items-center p-4 bg-gray-50 rounded-lg"
                        >
                            <span class="text-sm font-medium text-gray-700 capitalize">{{ item.type }}</span>
                            <span class="text-lg font-bold text-gray-900">{{ item.count }}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">Recent Campaigns</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recipients</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="campaign in recentCampaigns" :key="campaign.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ campaign.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">
                                        {{ campaign.type }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="px-2 py-1 text-xs font-medium rounded-full"
                                            :class="{
                                                'bg-gray-100 text-gray-800': campaign.status === 'draft',
                                                'bg-yellow-100 text-yellow-800': campaign.status === 'scheduled',
                                                'bg-blue-100 text-blue-800': campaign.status === 'running',
                                                'bg-green-100 text-green-800': campaign.status === 'completed',
                                            }"
                                        >
                                            {{ campaign.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ campaign.recipients?.length || 0 }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ new Date(campaign.created_at).toLocaleDateString() }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    customersByIndustry: Array,
    customersByStatus: Array,
    customersBySource: Array,
    campaignStats: Object,
    campaignsByType: Array,
    recentCampaigns: Array,
});
</script>
