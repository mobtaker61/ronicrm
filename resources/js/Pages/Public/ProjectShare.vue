<template>
    <PublicLayout app-name="RoniCRM">
        <div class="bg-gradient-to-br from-slate-50 to-blue-50 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-5xl mx-auto">
            <!-- Project Info Card -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 border border-gray-100">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ project.name }}</h1>
                <p v-if="project.description" class="text-gray-600 mb-4 whitespace-pre-line">{{ project.description }}</p>
                <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                    <span v-if="project.start_date" class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        {{ formatDate(project.start_date) }} <template v-if="project.end_date"> to {{ formatDate(project.end_date) }}</template>
                    </span>
                    <span v-if="project.location" class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg>
                        {{ project.location }}
                    </span>
                </div>
            </div>

            <!-- Customers List -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50/50 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-gray-900">Contacts in this project ({{ customers.length }})</h2>
                    <a
                        v-if="project.allow_excel_export && customers.length > 0"
                        :href="exportExcelUrl"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export to Excel
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Industry</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr
                                v-for="c in customers"
                                :key="c.id"
                                class="hover:bg-blue-50/50 cursor-pointer transition-colors"
                                @click="openCustomerCard(c)"
                            >
                                <td class="px-6 py-4">
                                    <span class="font-medium text-gray-900">{{ c.name }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ c.company_name || '—' }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span v-if="c.phone">{{ c.phone }}</span>
                                    <span v-else-if="c.email">{{ c.email }}</span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ c.industry?.name || '—' }}</td>
                                <td class="px-6 py-4">
                                    <button
                                        type="button"
                                        @click.stop="openCustomerCard(c)"
                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                    >
                                        View card
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="!customers || customers.length === 0" class="text-center py-12 text-gray-500">
                    No contacts in this project yet.
                </div>
            </div>

            <div class="mt-6 text-center text-sm text-gray-500">
                    <p>RoniCRM – Project Share</p>
                </div>
            </div>
        </div>

        <!-- Customer Card Modal -->
        <div
            v-if="showCardModal"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="closeCardModal"
        >
            <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col" @click.stop>
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
                    <h3 class="text-lg font-semibold text-gray-900">Contact Card</h3>
                    <button
                        type="button"
                        @click="closeCardModal"
                        class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div v-if="cardLoading" class="p-12 text-center text-gray-500">
                    Loading...
                </div>
                <div v-else-if="cardCustomer" class="p-6 overflow-y-auto flex-1">
                    <!-- Avatar & Name -->
                    <div class="flex items-center gap-4 mb-6">
                        <img
                            v-if="cardCustomer.avatar"
                            :src="`/storage/${cardCustomer.avatar}`"
                            :alt="cardCustomer.name"
                            class="w-16 h-16 rounded-full object-cover border-2 border-gray-200"
                        />
                        <div
                            v-else
                            class="w-16 h-16 rounded-full border-2 border-gray-200 flex items-center justify-center text-white font-semibold text-xl"
                            :class="getAvatarClass(cardCustomer)"
                        >
                            {{ getAvatarInitials(cardCustomer) }}
                        </div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900">{{ cardCustomer.name }}</h4>
                            <p v-if="cardCustomer.company_name" class="text-gray-600">{{ cardCustomer.company_name }}</p>
                            <span
                                v-if="cardCustomer.industry"
                                class="inline-block mt-1 px-2 py-0.5 text-xs rounded-full"
                                :style="{ backgroundColor: (cardCustomer.industry.color || '#6b7280') + '20', color: cardCustomer.industry.color || '#374151' }"
                            >
                                {{ cardCustomer.industry.name }}
                            </span>
                        </div>
                    </div>

                    <!-- Language, Gender (person), Contact Person (company) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                        <div v-if="cardCustomer.language">
                            <h5 class="text-sm font-semibold text-gray-700 mb-1">Language</h5>
                            <p class="text-gray-600 text-sm">{{ cardCustomer.language }}</p>
                        </div>
                        <div v-if="cardCustomer.type === 'person' && cardCustomer.gender">
                            <h5 class="text-sm font-semibold text-gray-700 mb-1">Gender</h5>
                            <p class="text-gray-600 text-sm capitalize">{{ cardCustomer.gender }}</p>
                        </div>
                        <div v-if="cardCustomer.type === 'company' && cardCustomer.contact_person">
                            <h5 class="text-sm font-semibold text-gray-700 mb-1">Contact Person</h5>
                            <p class="text-gray-600 text-sm">{{ cardCustomer.contact_person }}</p>
                        </div>
                    </div>

                    <!-- Contacts -->
                    <div v-if="cardCustomer.contacts && cardCustomer.contacts.length" class="mb-4">
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Contact Information</h5>
                        <div class="space-y-2">
                            <div
                                v-for="contact in cardCustomer.contacts"
                                :key="contact.id"
                                class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg"
                            >
                                <span class="text-sm text-gray-500 capitalize">{{ contact.type }}</span>
                                <a
                                    v-if="contact.type === 'email'"
                                    :href="`mailto:${contact.value}`"
                                    class="text-blue-600 hover:underline"
                                >{{ contact.value }}</a>
                                <a
                                    v-else-if="contact.type === 'phone' || contact.type === 'whatsapp'"
                                    :href="contact.type === 'whatsapp' ? `https://wa.me/${contact.value.replace(/\D/g,'')}` : `tel:${contact.value}`"
                                    class="text-blue-600 hover:underline"
                                    :target="contact.type === 'whatsapp' ? '_blank' : undefined"
                                >{{ contact.value }}</a>
                                <span v-else class="text-gray-900">{{ contact.value }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Social Media -->
                    <div v-if="cardCustomer.social_media && cardCustomer.social_media.length" class="mb-4">
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Social Media</h5>
                        <div class="flex flex-wrap gap-2">
                            <a
                                v-for="sm in cardCustomer.social_media"
                                :key="sm.id"
                                :href="sm.url || '#'"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 rounded-lg text-sm text-gray-700 hover:bg-gray-200"
                            >
                                {{ sm.social_media_type?.name }}: {{ sm.handle }}
                            </a>
                        </div>
                    </div>

                    <!-- Address -->
                    <div v-if="cardCustomer.address" class="mb-4">
                        <h5 class="text-sm font-semibold text-gray-700 mb-1">Address</h5>
                        <p class="text-gray-600 text-sm">{{ cardCustomer.address }}</p>
                    </div>

                    <!-- Notes -->
                    <div v-if="cardCustomer.notes" class="text-sm text-gray-500">
                        <h5 class="font-semibold text-gray-700 mb-1">Notes</h5>
                        <p class="text-gray-600">{{ cardCustomer.notes }}</p>
                    </div>
</div>
                </div>
            </div>
    </PublicLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({
    project: Object,
    customers: Array,
});

const exportExcelUrl = computed(() => {
    if (!props.project?.share_token) return '';
    return `/p/${props.project.share_token}/export-excel`;
});

const showCardModal = ref(false);
const cardCustomer = ref(null);
const cardLoading = ref(false);
const selectedShareKey = ref(null);

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return new Intl.DateTimeFormat('en-US', { year: 'numeric', month: 'short', day: 'numeric' }).format(d);
}

function getAvatarClass(customer) {
    if (customer.type === 'company') return 'bg-purple-500';
    if (customer.gender === 'male') return 'bg-blue-500';
    if (customer.gender === 'female') return 'bg-pink-500';
    return 'bg-gray-400';
}

function getAvatarInitials(customer) {
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
}

async function openCustomerCard(c) {
    if (!c.share_key || !props.project?.share_token) return;
    selectedShareKey.value = c.share_key;
    showCardModal.value = true;
    cardCustomer.value = null;
    cardLoading.value = true;
    try {
        const url = `/p/${props.project.share_token}/customer/${c.share_key}`;
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('Failed to load');
        const data = await res.json();
        cardCustomer.value = data.customer;
    } catch (e) {
        console.error(e);
        alert('Failed to load contact.');
        closeCardModal();
    } finally {
        cardLoading.value = false;
    }
}

function closeCardModal() {
    showCardModal.value = false;
    cardCustomer.value = null;
    selectedShareKey.value = null;
}
</script>
