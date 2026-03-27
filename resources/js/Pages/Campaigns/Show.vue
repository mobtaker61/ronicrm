<template>
    <AppLayout>
        <template #header>
            {{ t('campaigns.details') }}
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
                    <div class="flex items-center space-x-3 rtl:space-x-reverse">
                        <div class="flex space-x-2 rtl:space-x-reverse">
                            <span
                                class="px-3 py-1 text-sm font-medium rounded-full"
                                :class="{
                                    'bg-green-100 text-green-800': campaign.type === 'whatsapp',
                                    'bg-blue-100 text-blue-800': campaign.type === 'email',
                                }"
                            >
                                {{ campaignTypeLabel(campaign.type) }}
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
                                {{ campaignStatusLabel(campaign.status) }}
                            </span>
                        </div>
                        <button
                            v-if="campaign.status === 'draft' || (campaign.status === 'scheduled' && canStartNow)"
                            @click="startCampaign"
                            :disabled="startForm.processing"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center space-x-2 rtl:space-x-reverse"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ startForm.processing ? t('campaigns.starting') : t('campaigns.start_campaign') }}</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 pt-6 border-t border-gray-200">
                    <div>
                        <p class="text-sm text-gray-500">{{ t('common.created') }}</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatDate(campaign.created_at) }}</p>
                    </div>
                    <div v-if="campaign.scheduled_at">
                        <p class="text-sm text-gray-500">{{ t('campaigns.scheduled') }}</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatDate(campaign.scheduled_at) }}</p>
                    </div>
                    <div v-if="campaign.started_at">
                        <p class="text-sm text-gray-500">{{ t('campaigns.started') }}</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatDate(campaign.started_at) }}</p>
                    </div>
                    <div v-if="campaign.completed_at">
                        <p class="text-sm text-gray-500">{{ t('campaigns.completed') }}</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatDate(campaign.completed_at) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">{{ t('campaigns.created_by') }}</p>
                        <p class="text-sm font-medium text-gray-900">{{ campaign.creator?.name || t('common.unknown') }}</p>
                    </div>
                </div>
            </div>

            <!-- Campaign Content -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('campaigns.content') }}</h3>
                <div v-if="campaign.subject" class="mb-4">
                    <p class="text-sm text-gray-500">{{ t('campaigns.subject') }}</p>
                    <p class="text-sm font-medium text-gray-900">{{ campaign.subject }}</p>
                </div>
                <div v-if="campaign.image" class="mb-4">
                    <p class="text-sm text-gray-500 mb-2">{{ t('campaigns.attachment') }}</p>
                    <div class="inline-block max-w-md border border-gray-300 rounded-lg overflow-hidden bg-white">
                        <img
                            v-if="isImageFile(campaign.image)"
                            :src="`/storage/${campaign.image}`"
                            :alt="t('campaigns.attachment')"
                            class="max-h-[100px] w-auto object-contain"
                        />
                        <div
                            v-else
                            class="flex items-center justify-center p-4 max-h-[100px] bg-gray-50"
                        >
                            <div class="text-center">
                                <svg class="w-12 h-12 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <p class="text-xs text-gray-600 font-medium truncate max-w-[200px]">
                                    {{ getFileName(campaign.image) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <p class="text-sm text-gray-500 mb-2">{{ t('campaigns.message') }}</p>
                    <div v-if="campaign.type === 'email'" class="p-4 bg-gray-50 rounded-lg border border-gray-200 email-preview" v-html="campaignContentDisplay"></div>
                    <div v-else class="p-4 bg-gray-50 rounded-lg border border-gray-200 whitespace-pre-wrap">{{ campaign.content }}</div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-sm text-gray-500">{{ t('campaigns.total_recipients') }}</p>
                    <p class="text-2xl font-bold text-gray-900">{{ campaign.recipients?.length || 0 }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-sm text-gray-500">{{ t('customers.sent') }}</p>
                    <p class="text-2xl font-bold text-green-600">{{ getStatusCount('sent') }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-sm text-gray-500">{{ t('campaigns.delivered') }}</p>
                    <p class="text-2xl font-bold text-blue-600">{{ getStatusCount('delivered') }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-sm text-gray-500">{{ t('campaigns.failed') }}</p>
                    <p class="text-2xl font-bold text-red-600">{{ getStatusCount('failed') }}</p>
                </div>
            </div>

            <!-- Recipients List -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">{{ t('campaigns.recipients') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('customers.customer') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('campaigns.contact') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('common.status') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('campaigns.sent_at') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('campaigns.delivered_at') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('campaigns.error') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="recipient in campaign.recipients" :key="recipient.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ recipient.customer?.name || t('common.unknown') }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ recipient.customer?.company_name || t('common.dash') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <template v-if="recipient.customer_contact">
                                        <span class="text-xs text-gray-400">{{ recipient.customer_contact.type }}:</span> {{ recipient.customer_contact.value }}
                                    </template>
                                    <template v-else-if="recipient.customer?.contacts && recipient.customer.contacts.length > 0">
                                        <div v-for="contact in recipient.customer.contacts.slice(0, 1)" :key="contact.id">
                                            <span class="text-xs text-gray-400">{{ contact.type }}:</span> {{ contact.value }}
                                        </div>
                                    </template>
                                    <template v-else-if="recipient.customer?.email">
                                        <span class="text-xs text-gray-400">{{ t('common.email') }}:</span> {{ recipient.customer.email }}
                                    </template>
                                    <span v-else>{{ t('common.dash') }}</span>
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
                                        {{ getStatusLabel(recipient.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ recipient.sent_at ? formatDate(recipient.sent_at) : t('common.dash') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ recipient.delivered_at ? formatDate(recipient.delivered_at) : t('common.dash') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-red-600">
                                    {{ recipient.error_message || t('common.dash') }}
                                </td>
                            </tr>
                            <tr v-if="!campaign.recipients || campaign.recipients.length === 0">
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    {{ t('campaigns.no_recipients') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Email sent log (for email campaigns: visible record of what was sent) -->
            <div v-if="campaign.type === 'email' && campaign.logs && sentLogs.length > 0" class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('campaigns.sent_emails_log_title') }}</h3>
                <p class="text-sm text-gray-500 mb-4">{{ t('campaigns.sent_emails_log_description') }}</p>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('campaigns.sent_emails_log_to') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('campaigns.sent_emails_log_subject') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('campaigns.sent_emails_log_sent_at') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="log in sentLogs" :key="log.id">
                                <td class="px-6 py-3 text-sm text-gray-900">{{ log.details?.to || t('common.dash') }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ log.details?.subject || t('common.dash') }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500">
                                    {{ log.details?.sent_at ? formatDate(log.details.sent_at) : (log.created_at ? formatDate(log.created_at) : t('common.dash')) }}
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
                    {{ t('campaigns.back_to_campaigns') }}
                </Link>
                <button
                    v-if="campaign.status !== 'running' && campaign.status !== 'completed'"
                    @click="deleteCampaign"
                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                >
                    {{ t('campaigns.delete_campaign') }}
                </button>
            </div>
        </div>

        <!-- Progress Modal -->
        <div v-if="showProgressModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">{{ t('campaigns.sending_progress_title') }}</h3>
                    <button
                        v-if="isCompleted"
                        @click="closeProgressModal"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="px-6 py-4 flex-1 overflow-y-auto">
                    <!-- Progress Bar -->
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700">{{ t('campaigns.sending_progress_label') }}</span>
                            <span class="text-sm text-gray-600">{{ progressPercentage }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div
                                class="bg-green-600 h-3 rounded-full transition-all duration-300"
                                :style="{ width: progressPercentage + '%' }"
                            ></div>
                        </div>
                        <div class="flex justify-between items-center mt-2 text-sm text-gray-600">
                            <span>{{ t('campaigns.sent_delivered_count_label').replace(':count', String(statusCounts.sent + statusCounts.delivered)) }}</span>
                            <span>{{ t('campaigns.failed_count_label').replace(':count', String(statusCounts.failed)) }}</span>
                            <span>{{ t('campaigns.pending_count_label').replace(':count', String(statusCounts.pending)) }}</span>
                            <span>{{ t('campaigns.total_count_label').replace(':count', String(statusCounts.total)) }}</span>
                        </div>
                    </div>

                    <!-- Recipients List -->
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <div
                            v-for="recipient in progressRecipients"
                            :key="recipient.id"
                            class="flex items-center justify-between p-3 border border-gray-200 rounded-lg"
                            :class="{
                                'bg-green-50 border-green-200': recipient.status === 'sent' || recipient.status === 'delivered',
                                'bg-red-50 border-red-200': recipient.status === 'failed',
                                'bg-yellow-50 border-yellow-200': recipient.status === 'pending',
                            }"
                        >
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">{{ recipient.customer_name }}</div>
                                <div v-if="recipient.error_message" 
                                    class="text-sm mt-1"
                                    :class="{
                                        'text-yellow-600': recipient.status === 'sent' || recipient.status === 'delivered',
                                        'text-red-600': recipient.status === 'failed',
                                    }"
                                >
                                    {{ recipient.error_message }}
                                </div>
                            </div>
                            <div class="ltr:ml-4 rtl:mr-4">
                                <span
                                    class="px-3 py-1 text-xs font-medium rounded-full"
                                    :class="{
                                        'bg-green-100 text-green-800': recipient.status === 'sent' || recipient.status === 'delivered',
                                        'bg-red-100 text-red-800': recipient.status === 'failed',
                                        'bg-yellow-100 text-yellow-800': recipient.status === 'pending',
                                    }"
                                >
                                    {{ getStatusLabel(recipient.status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="isCompleted" class="px-6 py-4 border-t border-gray-200" :class="statusCounts.failed > 0 ? 'bg-yellow-50' : 'bg-green-50'">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" :class="statusCounts.failed > 0 ? 'text-yellow-800' : 'text-green-800'">
                                {{ statusCounts.failed > 0 ? t('campaigns.progress_completed_with_errors') : t('campaigns.progress_completed_success') }}
                            </p>
                            <p class="text-xs mt-1" :class="statusCounts.failed > 0 ? 'text-yellow-600' : 'text-green-600'">
                                {{ t('campaigns.progress_completed_summary').replace(':success', String(statusCounts.sent + statusCounts.delivered)).replace(':failed', String(statusCounts.failed)) }}
                            </p>
                        </div>
                        <button
                            @click="closeProgressModal"
                            class="px-4 py-2 rounded-lg text-white"
                            :class="statusCounts.failed > 0 ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700'"
                        >
                            {{ t('common.close') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, ref, onUnmounted } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    campaign: Object,
});

const startForm = useForm({});
const showProgressModal = ref(false);
const progressRecipients = ref([]);
const statusCounts = ref({
    total: 0,
    sent: 0,
    delivered: 0,
    failed: 0,
    pending: 0,
});
const isCompleted = ref(false);
const pollingInterval = ref(null);

// نمایش محتوای ایمیل: اگر HTML نبود، خط‌شکنی با <br> تا به‌هم نریزد
const campaignContentDisplay = computed(() => {
    const c = props.campaign?.content || '';
    if (!c.trim()) return '';
    if (c.includes('<') && c.includes('>')) return c;
    return c.replace(/\n/g, '<br>');
});

const canStartNow = computed(() => {
    if (!props.campaign.scheduled_at) return true;
    const scheduledDate = new Date(props.campaign.scheduled_at);
    return scheduledDate <= new Date();
});

const progressPercentage = computed(() => {
    if (statusCounts.value.total === 0) return 0;
    const completed = statusCounts.value.sent + statusCounts.value.delivered + statusCounts.value.failed;
    return Math.round((completed / statusCounts.value.total) * 100);
});

const sentLogs = computed(() => {
    if (!props.campaign.logs || !Array.isArray(props.campaign.logs)) return [];
    return props.campaign.logs.filter(log => log.action === 'sent');
});

const formatDate = (date) => {
    if (!date) return t('common.dash');
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

const getStatusLabel = (status) => {
    const labels = {
        pending: t('campaigns.status_pending'),
        sent: t('campaigns.status_sent'),
        delivered: t('campaigns.status_delivered'),
        failed: t('campaigns.status_failed'),
        opened: t('campaigns.status_opened'),
        clicked: t('campaigns.status_clicked'),
    };
    return labels[status] || status;
};

const campaignTypeLabel = (type) => {
    if (type === 'whatsapp') return t('campaigns.type_whatsapp');
    if (type === 'email') return t('campaigns.type_email');
    return type || t('common.dash');
};

const campaignStatusLabel = (status) => {
    if (status === 'draft') return t('campaigns.status_draft');
    if (status === 'scheduled') return t('campaigns.status_scheduled');
    if (status === 'running') return t('campaigns.status_running');
    if (status === 'completed') return t('campaigns.status_completed');
    if (status === 'cancelled') return t('campaigns.status_cancelled');
    return status || t('common.dash');
};

const isImageFile = (filePath) => {
    if (!filePath) return false;
    const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp', '.svg'];
    const lowerPath = filePath.toLowerCase();
    return imageExtensions.some(ext => lowerPath.includes(ext));
};

const getFileName = (filePath) => {
    if (!filePath) return '';
    // Extract filename from path (e.g., "campaign-attachments/file.pdf" -> "file.pdf")
    const parts = filePath.split('/');
    return parts[parts.length - 1] || filePath;
};

const startCampaign = async () => {
    if (!confirm(t('campaigns.confirm_start'))) {
        return;
    }

    try {
        showProgressModal.value = true;
        isCompleted.value = false;
        
        // Initialize progress
        statusCounts.value = {
            total: props.campaign.recipients?.filter(r => r.status === 'pending').length || 0,
            sent: 0,
            delivered: 0,
            failed: 0,
            pending: props.campaign.recipients?.filter(r => r.status === 'pending').length || 0,
        };
        progressRecipients.value = [];

        // Start campaign
        const response = await axios.post(route('campaigns.start', props.campaign.id));
        
        if (response.data.success) {
            // Start polling for status
            startPolling();
        } else {
            alert(
                t('campaigns.start_campaign_error')
                    .replace(':message', response.data.message || t('common.unknown_error'))
            );
            showProgressModal.value = false;
        }
    } catch (error) {
        console.error('Error starting campaign:', error);
        alert(
            t('campaigns.start_campaign_error')
                .replace(':message', error.response?.data?.message || error.message || t('common.unknown_error'))
        );
        showProgressModal.value = false;
    }
};

const startPolling = () => {
    // Poll immediately first time
    pollStatus();
    
    // Then poll every 500ms for real-time updates
    pollingInterval.value = setInterval(() => {
        pollStatus();
    }, 500);
};

const pollStatus = async () => {
    try {
        const response = await axios.get(route('campaigns.status', props.campaign.id));
        const data = response.data;
        
        statusCounts.value = {
            total: data.total,
            sent: data.sent,
            delivered: data.delivered,
            failed: data.failed,
            pending: data.pending,
        };
        
        progressRecipients.value = data.recipients || [];
        isCompleted.value = data.is_completed;
        
        // If completed, stop polling and close modal after 3 seconds
        if (data.is_completed) {
            stopPolling();
            // Wait 3 seconds to show final status, then close modal and reload
            setTimeout(() => {
                closeProgressModal();
            }, 3000);
        }
    } catch (error) {
        console.error('Error polling status:', error);
        // Continue polling even if there's an error
    }
};

const stopPolling = () => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
        pollingInterval.value = null;
    }
};

const closeProgressModal = () => {
    stopPolling();
    showProgressModal.value = false;
    // Reload page to show updated campaign status
    router.reload({ only: ['campaign'] });
};

onUnmounted(() => {
    stopPolling();
});

const deleteCampaign = () => {
    if (confirm(t('campaigns.confirm_delete'))) {
        router.delete(route('campaigns.destroy', props.campaign.id));
    }
};
</script>

<style scoped>
.email-preview :deep(p) { margin-bottom: 0.75rem; line-height: 1.6; }
.email-preview :deep(p:last-child) { margin-bottom: 0; }
.email-preview :deep(a) { color: #2563eb; text-decoration: underline; }
.email-preview :deep(ul), .email-preview :deep(ol) { margin: 0.5rem 0; padding-left: 1.5rem; line-height: 1.6; }
.email-preview :deep(li) { margin-bottom: 0.25rem; }
.email-preview :deep(h1), .email-preview :deep(h2), .email-preview :deep(h3) { margin-top: 1rem; margin-bottom: 0.5rem; font-weight: 600; }
.email-preview :deep(h1) { font-size: 1.25rem; }
.email-preview :deep(h2) { font-size: 1.125rem; }
.email-preview :deep(h3) { font-size: 1rem; }
</style>
