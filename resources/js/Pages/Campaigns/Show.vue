<template>
    <AppLayout>
        <template #header>
            Campaign Details
        </template>

        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Success/Error Messages -->
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.error }}
            </div>

            <!-- Campaign Header -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">{{ campaign.name }}</h2>
                        <p v-if="campaign.description" class="text-gray-600 mt-2">{{ campaign.description }}</p>
                    </div>
                    <div class="flex space-x-2">
                        <span
                            class="px-3 py-1 text-sm font-medium rounded-full"
                            :class="{
                                'bg-green-100 text-green-800': campaign.type === 'whatsapp',
                                'bg-blue-100 text-blue-800': campaign.type === 'email',
                            }"
                        >
                            {{ campaign.type }}
                        </span>
                        <span
                            class="px-3 py-1 text-sm font-medium rounded-full"
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
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 pt-6 border-t border-gray-200">
                    <div>
                        <p class="text-sm text-gray-500">Created</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatDate(campaign.created_at) }}</p>
                    </div>
                    <div v-if="campaign.scheduled_at">
                        <p class="text-sm text-gray-500">Scheduled</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatDate(campaign.scheduled_at) }}</p>
                    </div>
                    <div v-if="campaign.started_at">
                        <p class="text-sm text-gray-500">Started</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatDate(campaign.started_at) }}</p>
                    </div>
                    <div v-if="campaign.completed_at">
                        <p class="text-sm text-gray-500">Completed</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatDate(campaign.completed_at) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Created By</p>
                        <p class="text-sm font-medium text-gray-900">{{ campaign.creator?.name || 'Unknown' }}</p>
                    </div>
                </div>
            </div>

            <!-- Campaign Content -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Campaign Content</h3>
                <div v-if="campaign.subject" class="mb-4">
                    <p class="text-sm text-gray-500">Subject</p>
                    <p class="text-sm font-medium text-gray-900">{{ campaign.subject }}</p>
                </div>
                <div v-if="campaign.image && campaign.type === 'whatsapp'" class="mb-4">
                    <p class="text-sm text-gray-500 mb-2">Image</p>
                    <img
                        :src="`/storage/${campaign.image}`"
                        alt="Campaign Image"
                        class="max-w-md rounded-lg border border-gray-300"
                    />
                </div>
                <div>
                    <p class="text-sm text-gray-500 mb-2">Message</p>
                    <div v-if="campaign.type === 'email'" class="p-4 bg-gray-50 rounded-lg border border-gray-200" v-html="campaign.content"></div>
                    <div v-else class="p-4 bg-gray-50 rounded-lg border border-gray-200 whitespace-pre-wrap">{{ campaign.content }}</div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-sm text-gray-500">Total Recipients</p>
                    <p class="text-2xl font-bold text-gray-900">{{ campaign.recipients?.length || 0 }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-sm text-gray-500">Sent</p>
                    <p class="text-2xl font-bold text-green-600">{{ getStatusCount('sent') }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-sm text-gray-500">Delivered</p>
                    <p class="text-2xl font-bold text-blue-600">{{ getStatusCount('delivered') }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-sm text-gray-500">Failed</p>
                    <p class="text-2xl font-bold text-red-600">{{ getStatusCount('failed') }}</p>
                </div>
            </div>

            <!-- Recipients List -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Recipients</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sent At</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Delivered At</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Error</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="recipient in campaign.recipients" :key="recipient.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ recipient.customer?.name || 'Unknown' }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ recipient.customer?.company_name || '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div v-if="recipient.customer?.contacts && recipient.customer.contacts.length > 0">
                                        <div v-for="contact in recipient.customer.contacts.slice(0, 1)" :key="contact.id">
                                            <span class="text-xs text-gray-400">{{ contact.type }}:</span> {{ contact.value }}
                                        </div>
                                    </div>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="px-2 py-1 text-xs font-medium rounded-full"
                                        :class="{
                                            'bg-yellow-100 text-yellow-800': recipient.status === 'pending',
                                            'bg-blue-100 text-blue-800': recipient.status === 'sent',
                                            'bg-green-100 text-green-800': recipient.status === 'delivered',
                                            'bg-red-100 text-red-800': recipient.status === 'failed',
                                            'bg-purple-100 text-purple-800': recipient.status === 'opened',
                                            'bg-indigo-100 text-indigo-800': recipient.status === 'clicked',
                                        }"
                                    >
                                        {{ recipient.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ recipient.sent_at ? formatDate(recipient.sent_at) : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ recipient.delivered_at ? formatDate(recipient.delivered_at) : '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-red-600">
                                    {{ recipient.error_message || '-' }}
                                </td>
                            </tr>
                            <tr v-if="!campaign.recipients || campaign.recipients.length === 0">
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    No recipients found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-between items-center">
                <Link
                    :href="route('campaigns.index')"
                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                >
                    Back to Campaigns
                </Link>
                <button
                    v-if="campaign.status !== 'running' && campaign.status !== 'completed'"
                    @click="deleteCampaign"
                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                >
                    Delete Campaign
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    campaign: Object,
});

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusCount = (status) => {
    if (!props.campaign.recipients) return 0;
    return props.campaign.recipients.filter(r => r.status === status).length;
};

const deleteCampaign = () => {
    if (confirm('Are you sure you want to delete this campaign?')) {
        router.delete(route('campaigns.destroy', props.campaign.id));
    }
};
</script>
