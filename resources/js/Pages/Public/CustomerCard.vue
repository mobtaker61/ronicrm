<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4 sm:px-6 lg:px-8">
        <!-- Success Message -->
        <div v-if="$page.props.flash?.success" class="max-w-5xl mx-auto mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            {{ $page.props.flash.success }}
        </div>

        <!-- Error Message -->
        <div v-if="$page.props.flash?.error" class="max-w-5xl mx-auto mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
            {{ $page.props.flash.error }}
        </div>

        <div class="max-w-5xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-6 gap-6">
                <!-- Avatar & QR Code Card (Left Column - 1/6) -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <!-- Mobile: Horizontal Layout -->
                        <div class="flex flex-row items-center justify-center space-x-6 lg:hidden">
                            <!-- Avatar -->
                            <div class="relative flex-shrink-0">
                                <img
                                    v-if="customer.avatar"
                                    :src="`/storage/${customer.avatar}`"
                                    :alt="customer.name"
                                    class="w-24 h-24 rounded-full object-cover border-4 border-gray-200 shadow-lg"
                                />
                                <div
                                    v-else
                                    class="w-24 h-24 rounded-full border-4 border-gray-200 flex items-center justify-center text-white font-semibold text-3xl shadow-lg"
                                    :class="getAvatarClass(customer)"
                                >
                                    {{ getAvatarInitials(customer) }}
                                </div>
                                
                                <!-- Type Icon Overlay -->
                                <div class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full bg-white border-2 border-gray-200 flex items-center justify-center shadow-md">
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

                            <!-- QR Code -->
                            <div class="flex-shrink-0">
                                <h3 class="text-sm font-semibold text-gray-700 mb-2 text-center">Scan QR Code</h3>
                                <div class="flex justify-center">
                                    <canvas ref="qrCanvasMobile" class="border-2 border-gray-200 rounded-lg bg-white p-2"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Desktop: Vertical Layout -->
                        <div class="hidden lg:flex flex-col items-center">
                            <!-- Avatar -->
                            <div class="relative mb-6">
                                <img
                                    v-if="customer.avatar"
                                    :src="`/storage/${customer.avatar}`"
                                    :alt="customer.name"
                                    class="w-32 h-32 rounded-full object-cover border-4 border-gray-200 shadow-lg"
                                />
                                <div
                                    v-else
                                    class="w-32 h-32 rounded-full border-4 border-gray-200 flex items-center justify-center text-white font-semibold text-4xl shadow-lg"
                                    :class="getAvatarClass(customer)"
                                >
                                    {{ getAvatarInitials(customer) }}
                                </div>
                                
                                <!-- Type Icon Overlay -->
                                <div class="absolute -bottom-2 -right-2 w-8 h-8 rounded-full bg-white border-2 border-gray-200 flex items-center justify-center shadow-md">
                                    <svg
                                        v-if="customer.type === 'person'"
                                        class="w-5 h-5 text-blue-600"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                    <svg
                                        v-else
                                        class="w-5 h-5 text-purple-600"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>

                            <!-- QR Code -->
                            <div class="w-full">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3 text-center">Scan QR Code</h3>
                                <div class="flex justify-center mb-2">
                                    <canvas ref="qrCanvas" class="border-2 border-gray-200 rounded-lg bg-white p-2"></canvas>
                                </div>
                                <p class="text-xs text-gray-500 text-center">Share this card</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Information Card (Right Column - 5/6) -->
                <div class="lg:col-span-5">
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <!-- Header -->
                        <div class="border-b border-gray-200 pb-4 mb-6">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ customer.name }}</h1>
                                    <p v-if="customer.company_name" class="text-xl text-gray-600 mb-3">{{ customer.company_name }}</p>
                                </div>
                                <button
                                    @click="showShareModal = true"
                                    class="ml-4 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center space-x-2 flex-shrink-0"
                                >
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                    </svg>
                                    <span class="hidden sm:inline">Share</span>
                                </button>
                            </div>
                            <div v-if="customer.industry" class="inline-block">
                                <span
                                    class="px-3 py-1 text-sm font-medium rounded-full"
                                    :style="{ backgroundColor: customer.industry.color + '20', color: customer.industry.color }"
                                >
                                    {{ customer.industry.name }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <!-- Contact Information -->
                            <div v-if="customer.contacts && customer.contacts.length > 0">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    Contact Information
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div
                                        v-for="contact in customer.contacts"
                                        :key="contact.id"
                                        class="p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm text-gray-500 capitalize mb-1">{{ contact.type }}</p>
                                                <a
                                                    v-if="contact.type === 'phone'"
                                                    :href="`tel:${contact.value}`"
                                                    class="text-lg font-medium text-blue-600 hover:text-blue-800"
                                                >
                                                    {{ contact.value }}
                                                </a>
                                                <a
                                                    v-else-if="contact.type === 'email'"
                                                    :href="`mailto:${contact.value}`"
                                                    class="text-lg font-medium text-blue-600 hover:text-blue-800"
                                                >
                                                    {{ contact.value }}
                                                </a>
                                                <a
                                                    v-else-if="contact.type === 'whatsapp'"
                                                    :href="`https://wa.me/${contact.value.replace(/[^0-9]/g, '')}`"
                                                    target="_blank"
                                                    class="text-lg font-medium text-blue-600 hover:text-blue-800"
                                                >
                                                    {{ contact.value }}
                                                </a>
                                                <span v-else class="text-lg font-medium text-gray-900">
                                                    {{ contact.value }}
                                                </span>
                                            </div>
                                            <span v-if="contact.is_primary" class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">
                                                Primary
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Social Media -->
                            <div v-if="customer.social_media && customer.social_media.length > 0">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                    Social Media & Websites
                                </h3>
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

                            <!-- Address -->
                            <div v-if="customer.address">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Address
                                </h3>
                                <p class="text-gray-700">{{ customer.address }}</p>
                            </div>

                            <!-- Additional Info -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t">
                                <div v-if="customer.gender">
                                    <p class="text-sm text-gray-500 mb-1">Gender</p>
                                    <p class="text-gray-900 capitalize font-medium">{{ customer.gender }}</p>
                                </div>
                                <div v-if="customer.language">
                                    <p class="text-sm text-gray-500 mb-1">Language</p>
                                    <p class="text-gray-900 uppercase font-medium">{{ customer.language }}</p>
                                </div>
                                <div v-if="customer.contact_person">
                                    <p class="text-sm text-gray-500 mb-1">Contact Person</p>
                                    <p class="text-gray-900 font-medium">{{ customer.contact_person }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-6 text-center text-sm text-gray-500">
                <p>Powered by RoniCRM</p>
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
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import QRCode from 'qrcode';

const props = defineProps({
    customer: Object,
    shareUrl: String,
});

const showShareModal = ref(false);

const qrCanvas = ref(null);
const qrCanvasMobile = ref(null);

const shareForm = useForm({
    phone: '',
});

const generateQRCode = (canvas, size = 150) => {
    if (canvas && props.shareUrl) {
        QRCode.toCanvas(canvas, props.shareUrl, {
            width: size,
            margin: 2,
            color: {
                dark: '#000000',
                light: '#FFFFFF',
            },
        }).catch(err => {
            console.error('QR Code generation error:', err);
        });
    }
};

onMounted(() => {
    // Generate QR code for both mobile and desktop
    generateQRCode(qrCanvas.value, 150);
    generateQRCode(qrCanvasMobile.value, 120);
});

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

const shareViaWhatsApp = () => {
    shareForm.post(route('public.customer.share-via-whatsapp', props.customer.share_key), {
        onSuccess: () => {
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
