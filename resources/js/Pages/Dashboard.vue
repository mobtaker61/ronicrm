<template>
    <AppLayout>
        <template #header>
            Dashboard
        </template>

        <div class="space-y-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Total Customers</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ stats.total_customers }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Total Conversations</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ stats.total_campaigns }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Active Conversations</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ stats.active_campaigns }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Industries</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ stats.total_industries }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Customers by Status -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Customers by Status</h3>
                    <div class="space-y-3">
                        <div
                            v-for="(count, status) in customersByStatus"
                            :key="status"
                            class="flex items-center justify-between"
                        >
                            <span class="text-sm font-medium text-gray-700 capitalize">{{ status }}</span>
                            <div class="flex items-center space-x-3">
                                <div class="w-32 bg-gray-200 rounded-full h-2">
                                    <div
                                        class="h-2 rounded-full"
                                        :class="{
                                            'bg-yellow-500': status === 'lead',
                                            'bg-blue-500': status === 'prospect',
                                            'bg-green-500': status === 'customer',
                                            'bg-gray-500': status === 'inactive',
                                        }"
                                        :style="{ width: `${(count / stats.total_customers) * 100}%` }"
                                    ></div>
                                </div>
                                <span class="text-sm font-bold text-gray-900 w-8 text-right">{{ count }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Industries -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Industries</h3>
                    <div class="space-y-3">
                        <div
                            v-for="industry in customersByIndustry"
                            :key="industry.name"
                            class="flex items-center justify-between"
                        >
                            <span class="text-sm font-medium text-gray-700">{{ industry.name }}</span>
                            <span class="text-sm font-bold text-gray-900">{{ industry.count }} customers</span>
                        </div>
                        <p v-if="customersByIndustry.length === 0" class="text-sm text-gray-500 text-center py-4">
                            No data available
                        </p>
                    </div>
                </div>
            </div>

            <!-- Recent Campaigns -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Conversations</h3>
                    <Link
                        href="/campaigns"
                        class="text-sm text-blue-600 hover:text-blue-800"
                    >
                        View All
                    </Link>
                </div>
                <div class="space-y-3">
                    <div
                        v-for="campaign in recentCampaigns"
                        :key="campaign.id"
                        class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ campaign.name }}</p>
                            <p class="text-xs text-gray-500">
                                {{ campaign.type }} • {{ campaign.status }} • Created by {{ campaign.creator?.name }}
                            </p>
                        </div>
                        <span class="text-xs text-gray-500">
                            {{ new Date(campaign.created_at).toLocaleDateString() }}
                        </span>
                    </div>
                    <p v-if="recentCampaigns.length === 0" class="text-sm text-gray-500 text-center py-4">
                        No campaigns yet
                    </p>
                </div>
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
            total_campaigns: 0,
            active_campaigns: 0,
            total_industries: 0,
        }),
    },
    customersByStatus: {
        type: Object,
        default: () => ({}),
    },
    customersByIndustry: {
        type: Array,
        default: () => [],
    },
    recentCampaigns: {
        type: Array,
        default: () => [],
    },
});
</script>
