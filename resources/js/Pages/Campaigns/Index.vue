<template>
    <AppLayout>
        <template #header>
            Campaigns
        </template>

        <div class="space-y-6">
            <!-- Success Message -->
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>

            <!-- Header -->
            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-bold text-gray-900">Marketing Campaigns</h2>
                <Link
                    :href="route('campaigns.create')"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Create Campaign
                </Link>
            </div>

            <!-- Campaigns List -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recipients</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="campaign in campaigns.data" :key="campaign.id">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ campaign.name }}</div>
                                <div class="text-sm text-gray-500">{{ campaign.description }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="px-2 py-1 text-xs font-medium rounded-full"
                                    :class="{
                                        'bg-green-100 text-green-800': campaign.type === 'whatsapp',
                                        'bg-blue-100 text-blue-800': campaign.type === 'email',
                                    }"
                                >
                                    {{ campaign.type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="px-2 py-1 text-xs font-medium rounded-full"
                                    :class="{
                                        'bg-gray-100 text-gray-800': campaign.status === 'draft',
                                        'bg-yellow-100 text-yellow-800': campaign.status === 'scheduled',
                                        'bg-blue-100 text-blue-800': campaign.status === 'running',
                                        'bg-green-100 text-green-800': campaign.status === 'completed',
                                        'bg-red-100 text-red-800': campaign.status === 'cancelled',
                                    }"
                                >
                                    {{ campaign.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ campaign.recipients?.length || 0 }} recipients
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ new Date(campaign.created_at).toLocaleDateString() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-4">
                                    <Link
                                        :href="route('campaigns.show', campaign.id)"
                                        class="text-blue-600 hover:text-blue-900"
                                    >
                                        View
                                    </Link>
                                    <button
                                        v-if="campaign.status !== 'running' && campaign.status !== 'completed'"
                                        @click="deleteCampaign(campaign)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ campaigns.from }} to {{ campaigns.to }} of {{ campaigns.total }} results
                        </div>
                        <div class="flex space-x-2">
                            <Link
                                v-if="campaigns.prev_page_url"
                                :href="campaigns.prev_page_url"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Previous
                            </Link>
                            <Link
                                v-if="campaigns.next_page_url"
                                :href="campaigns.next_page_url"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Next
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    campaigns: Object,
});

const deleteCampaign = (campaign) => {
    if (confirm(`Are you sure you want to delete "${campaign.name}"?`)) {
        router.delete(route('campaigns.destroy', campaign.id));
    }
};
</script>
