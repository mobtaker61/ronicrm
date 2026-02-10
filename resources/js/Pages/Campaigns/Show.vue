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
                    <div class="flex items-center space-x-3">
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
                        <button
                            v-if="campaign.status === 'draft' || (campaign.status === 'scheduled' && canStartNow)"
                            @click="startCampaign"
                            :disabled="startForm.processing"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center space-x-2"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ startForm.processing ? 'Starting...' : 'Start Campaign' }}</span>
                        </button>
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
                <div v-if="campaign.image" class="mb-4">
                    <p class="text-sm text-gray-500 mb-2">Attachment</p>
                    <div class="inline-block max-w-md border border-gray-300 rounded-lg overflow-hidden bg-white">
                        <img
                            v-if="isImageFile(campaign.image)"
                            :src="`/storage/${campaign.image}`"
                            alt="Campaign Attachment"
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
                                    <template v-if="recipient.customer_contact">
                                        <span class="text-xs text-gray-400">{{ recipient.customer_contact.type }}:</span> {{ recipient.customer_contact.value }}
                                    </template>
                                    <template v-else-if="recipient.customer?.contacts && recipient.customer.contacts.length > 0">
                                        <div v-for="contact in recipient.customer.contacts.slice(0, 1)" :key="contact.id">
                                            <span class="text-xs text-gray-400">{{ contact.type }}:</span> {{ contact.value }}
                                        </div>
                                    </template>
                                    <template v-else-if="recipient.customer?.email">email: {{ recipient.customer.email }}</template>
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

            <!-- Email sent log (for email campaigns: visible record of what was sent) -->
            <div v-if="campaign.type === 'email' && campaign.logs && sentLogs.length > 0" class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Sent emails log</h3>
                <p class="text-sm text-gray-500 mb-4">Emails sent from this campaign (also stored on your mail server if SMTP is configured).</p>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">To</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sent at</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="log in sentLogs" :key="log.id">
                                <td class="px-6 py-3 text-sm text-gray-900">{{ log.details?.to || '-' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ log.details?.subject || '-' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ log.details?.sent_at ? formatDate(log.details.sent_at) : (log.created_at ? formatDate(log.created_at) : '-') }}</td>
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

        <!-- Progress Modal -->
        <div v-if="showProgressModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">ارسال کمپین در حال انجام...</h3>
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
                            <span class="text-sm font-medium text-gray-700">پیشرفت ارسال</span>
                            <span class="text-sm text-gray-600">{{ progressPercentage }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div
                                class="bg-green-600 h-3 rounded-full transition-all duration-300"
                                :style="{ width: progressPercentage + '%' }"
                            ></div>
                        </div>
                        <div class="flex justify-between items-center mt-2 text-sm text-gray-600">
                            <span>ارسال شده: {{ statusCounts.sent + statusCounts.delivered }}</span>
                            <span>ناموفق: {{ statusCounts.failed }}</span>
                            <span>در انتظار: {{ statusCounts.pending }}</span>
                            <span>کل: {{ statusCounts.total }}</span>
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
                            <div class="ml-4">
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
                                {{ statusCounts.failed > 0 ? 'ارسال کمپین به پایان رسید (برخی با خطا)' : 'ارسال کمپین با موفقیت به پایان رسید!' }}
                            </p>
                            <p class="text-xs mt-1" :class="statusCounts.failed > 0 ? 'text-yellow-600' : 'text-green-600'">
                                {{ statusCounts.sent + statusCounts.delivered }} ارسال موفق، 
                                {{ statusCounts.failed }} ناموفق
                            </p>
                        </div>
                        <button
                            @click="closeProgressModal"
                            class="px-4 py-2 rounded-lg text-white"
                            :class="statusCounts.failed > 0 ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700'"
                        >
                            بستن
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

const getStatusLabel = (status) => {
    const labels = {
        'pending': 'در انتظار',
        'sent': 'ارسال شده',
        'delivered': 'تحویل داده شده',
        'failed': 'ناموفق',
    };
    return labels[status] || status;
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
    if (!confirm('آیا مطمئن هستید که می‌خواهید این کمپین را شروع کنید؟ پیام‌ها بلافاصله ارسال خواهند شد.')) {
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
            alert('خطا در شروع کمپین: ' + (response.data.message || 'خطای ناشناخته'));
            showProgressModal.value = false;
        }
    } catch (error) {
        console.error('Error starting campaign:', error);
        alert('خطا در شروع کمپین: ' + (error.response?.data?.message || error.message));
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
    if (confirm('Are you sure you want to delete this campaign?')) {
        router.delete(route('campaigns.destroy', props.campaign.id));
    }
};
</script>
