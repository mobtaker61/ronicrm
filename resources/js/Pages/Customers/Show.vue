<template>
    <AppLayout>
        <template #header>
            Customer Details
        </template>

        <div class="space-y-6">
            <!-- Success Message -->
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>

            <!-- Customer Header -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <!-- Avatar -->
                        <div class="relative">
                            <img
                                v-if="customer.avatar"
                                :src="`/storage/${customer.avatar}`"
                                :alt="customer.name"
                                class="w-16 h-16 rounded-full object-cover border-2 border-gray-200"
                            />
                            <div
                                v-else
                                class="w-16 h-16 rounded-full border-2 border-gray-200 flex items-center justify-center text-white font-semibold text-2xl"
                                :class="getAvatarClass(customer)"
                            >
                                {{ getAvatarInitials(customer) }}
                            </div>
                            
                            <!-- Type Icon Overlay -->
                            <div class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full bg-white border-2 border-gray-200 flex items-center justify-center shadow-sm">
                                <svg
                                    v-if="customer.type === 'person'"
                                    class="w-4 h-4 text-blue-600"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                </svg>
                                <svg
                                    v-else
                                    class="w-4 h-4 text-purple-600"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">{{ customer.name }}</h2>
                            <p v-if="customer.company_name" class="text-gray-500 mt-1">{{ customer.company_name }}</p>
                        </div>
                    </div>
                    <div class="flex space-x-3">
                        <button
                            v-if="customer.share_key"
                            @click="showShareModal = true"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center space-x-2"
                        >
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                            <span>Share via WhatsApp</span>
                        </button>
                        <a
                            v-if="customer.share_key"
                            :href="route('public.customer.card', customer.share_key)"
                            target="_blank"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                        >
                            View Public Card
                        </a>
                        <Link
                            :href="route('customers.edit', customer.id)"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            Edit
                        </Link>
                        <Link
                            :href="route('customers.index')"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Back
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Main Content: Two Columns -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Customer Information (2/3) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div v-if="customer.gender">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Gender</label>
                                <p class="text-gray-900 capitalize">{{ customer.gender }}</p>
                            </div>
                            <div v-if="customer.languages && customer.languages.length">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Languages</label>
                                <p class="text-gray-900">{{ customer.languages.join(', ') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Industry</label>
                                <span
                                    v-if="customer.industry"
                                    class="px-2 py-1 text-xs font-medium rounded-full inline-block"
                                    :style="{ backgroundColor: customer.industry.color + '20', color: customer.industry.color }"
                                >
                                    {{ customer.industry.name }}
                                </span>
                                <span v-else class="text-gray-400">-</span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Status</label>
                                <span
                                    class="px-2 py-1 text-xs font-medium rounded-full"
                                    :class="{
                                        'bg-yellow-100 text-yellow-800': customer.status === 'lead',
                                        'bg-blue-100 text-blue-800': customer.status === 'prospect',
                                        'bg-green-100 text-green-800': customer.status === 'customer',
                                        'bg-gray-100 text-gray-800': customer.status === 'inactive',
                                    }"
                                >
                                    {{ customer.status }}
                                </span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Source</label>
                                <p class="text-gray-900 capitalize">{{ customer.source || '-' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Contact Person</label>
                                <p class="text-gray-900">{{ customer.contact_person || '-' }}</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Address</label>
                                <p class="text-gray-900">{{ customer.address || '-' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Methods -->
                    <div v-if="customer.contacts && customer.contacts.length > 0" class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Contact Methods</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div
                                v-for="contact in customer.contacts"
                                :key="contact.id"
                                class="p-4 bg-gray-50 rounded-lg"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-500 capitalize">{{ contact.type }}</p>
                                        <p class="text-lg font-medium text-gray-900">{{ contact.value }}</p>
                                    </div>
                                    <span v-if="contact.is_primary" class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                        Primary
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Media -->
                    <div v-if="customer.social_media && customer.social_media.length > 0" class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Social Media & Websites</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <a
                                v-for="sm in customer.social_media"
                                :key="sm.id"
                                :href="sm.url || '#'"
                                target="_blank"
                                class="p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors flex items-center justify-between"
                            >
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i v-if="sm.social_media_type?.icon" :class="sm.social_media_type.icon" class="text-blue-600"></i>
                                        <span v-else class="text-blue-600 font-bold">{{ sm.social_media_type?.name?.charAt(0) || '?' }}</span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ sm.social_media_type?.name }}</p>
                                        <p class="text-sm text-gray-500">{{ sm.handle }}</p>
                                    </div>
                                </div>
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Notes Section -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Notes</h3>
                        
                        <form @submit.prevent="addNote" class="mb-6">
                            <textarea
                                v-model="noteForm.note"
                                rows="3"
                                placeholder="Add a note..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                            ></textarea>
                            <div class="mt-2 flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="noteForm.processing"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                                >
                                    Add Note
                                </button>
                            </div>
                        </form>

                        <div class="space-y-4">
                            <div
                                v-for="note in customer.notes"
                                :key="note.id"
                                class="border-l-4 border-blue-500 pl-4 py-2"
                            >
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <p class="text-gray-900">{{ note.note }}</p>
                                        <p class="text-sm text-gray-500 mt-1">
                                            By {{ note.user?.name }} on {{ formatDate(note.created_at) }}
                                        </p>
                                    </div>
                                    <button
                                        @click="deleteNote(note.id)"
                                        class="text-red-600 hover:text-red-900 ml-4"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                            <p v-if="customer.notes.length === 0" class="text-gray-500 text-center py-4">
                                No notes yet
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Recent Activity (1/3) -->
                <div class="space-y-6">
                    <!-- Recent Conversations -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Conversations</h3>
                        <div v-if="recentMessages && recentMessages.length > 0" class="space-y-3 max-h-96 overflow-y-auto">
                            <div
                                v-for="msg in recentMessages"
                                :key="msg.id"
                                class="p-3 rounded-lg"
                                :class="msg.direction === 'incoming' ? 'bg-blue-50' : 'bg-gray-50'"
                            >
                                <div class="flex items-start justify-between mb-1">
                                    <span
                                        class="text-xs font-medium px-2 py-1 rounded"
                                        :class="msg.direction === 'incoming' ? 'bg-blue-200 text-blue-800' : 'bg-gray-200 text-gray-800'"
                                    >
                                        {{ msg.direction === 'incoming' ? 'Received' : 'Sent' }}
                                    </span>
                                    <span class="text-xs text-gray-500">{{ formatTime(msg.created_at) }}</span>
                                </div>
                                <div v-if="msg.media_url" class="mb-2">
                                    <img
                                        :src="msg.media_url"
                                        alt="Media"
                                        class="w-full h-auto rounded-lg max-h-32 object-cover"
                                        @error="handleImageError"
                                    />
                                </div>
                                <p v-if="msg.message" class="text-sm text-gray-900">{{ msg.message }}</p>
                                <p v-else-if="msg.media_url" class="text-sm text-gray-500 italic">Image</p>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <p class="text-sm">No conversations yet</p>
                        </div>
                    </div>

                    <!-- Campaigns -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Campaigns</h3>
                        <div v-if="campaigns && campaigns.length > 0" class="space-y-3 max-h-96 overflow-y-auto">
                            <Link
                                v-for="campaign in campaigns"
                                :key="campaign.id"
                                :href="route('campaigns.show', campaign.id)"
                                class="block p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                            >
                                <div class="flex items-start justify-between mb-1">
                                    <h4 class="font-medium text-gray-900 text-sm">{{ campaign.name }}</h4>
                                    <span
                                        class="text-xs font-medium px-2 py-1 rounded"
                                        :class="{
                                            'bg-blue-100 text-blue-800': campaign.type === 'email',
                                            'bg-green-100 text-green-800': campaign.type === 'whatsapp',
                                            'bg-purple-100 text-purple-800': campaign.type === 'sms',
                                        }"
                                    >
                                        {{ campaign.type }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between mt-2">
                                    <span
                                        class="text-xs px-2 py-1 rounded"
                                        :class="{
                                            'bg-yellow-100 text-yellow-800': campaign.status === 'pending',
                                            'bg-green-100 text-green-800': campaign.status === 'sent',
                                            'bg-red-100 text-red-800': campaign.status === 'failed',
                                        }"
                                    >
                                        {{ campaign.status }}
                                    </span>
                                    <span class="text-xs text-gray-500">{{ formatDate(campaign.created_at) }}</span>
                                </div>
                            </Link>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                            </svg>
                            <p class="text-sm">No campaigns yet</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Share via WhatsApp Modal -->
        <div
            v-if="showShareModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showShareModal = false"
        >
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Share via WhatsApp</h3>
                    <button
                        @click="showShareModal = false"
                        class="text-gray-400 hover:text-gray-500"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="shareViaWhatsApp" class="p-6">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Phone Number *
                        </label>
                        <input
                            v-model="shareForm.phone"
                            type="text"
                            placeholder="e.g., 971501234567"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p class="mt-1 text-xs text-gray-500">Enter phone number with country code (without +)</p>
                    </div>

                    <div v-if="shareForm.errors.phone" class="mb-4 text-sm text-red-600">
                        {{ shareForm.errors.phone }}
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="showShareModal = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="shareForm.processing"
                            class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50"
                        >
                            <span v-if="shareForm.processing">Sending...</span>
                            <span v-else>Send</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    customer: Object,
    recentMessages: Array,
    campaigns: Array,
});

const showShareModal = ref(false);

const noteForm = useForm({
    note: '',
});

const shareForm = useForm({
    phone: '',
});

const addNote = () => {
    noteForm.post(route('customers.notes.store', props.customer.id), {
        onSuccess: () => {
            noteForm.reset();
        },
    });
};

const deleteNote = (noteId) => {
    if (confirm('Are you sure you want to delete this note?')) {
        router.delete(route('customers.notes.destroy', noteId));
    }
};

// Avatar helper functions
const getAvatarClass = (customer) => {
    if (customer.type === 'company') {
        return 'bg-purple-500';
    }
    
    if (customer.gender === 'male') {
        return 'bg-blue-500';
    } else if (customer.gender === 'female') {
        return 'bg-pink-500';
    }
    
    return 'bg-gray-400';
};

const getAvatarInitials = (customer) => {
    if (customer.type === 'company' && customer.company_name) {
        return customer.company_name.substring(0, 2).toUpperCase();
    }
    if (customer.name) {
        const names = customer.name.trim().split(' ');
        if (names.length >= 2) {
            return (names[0].charAt(0) + names[names.length - 1].charAt(0)).toUpperCase();
        }
        return customer.name.substring(0, 2).toUpperCase();
    }
    return '??';
};

// Date formatting
const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatTime = (date) => {
    if (!date) return '-';
    const d = new Date(date);
    const now = new Date();
    const diff = now - d;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;
    return formatDate(date);
};

const handleImageError = (event) => {
    event.target.style.display = 'none';
};

const shareViaWhatsApp = () => {
    shareForm.post(route('customers.share-via-whatsapp', props.customer.id), {
        onSuccess: (page) => {
            shareForm.reset();
            showShareModal.value = false;
            // Success message will be shown via flash message
        },
        onError: (errors) => {
            // Errors will be displayed in the form
            console.error('Error sharing card:', errors);
        },
    });
};
</script>
